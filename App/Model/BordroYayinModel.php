<?php
namespace App\Model;

use App\Helper\Security;
use App\Service\BordroYayinIcerikService;
use DomainException;
use PDO;
use Throwable;

final class BordroYayinModel extends Model
{
    protected $table = 'bordro_yayin';

    private function sorgu(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function atomik(callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function donem(int $firma, int $donem, bool $kilit = false): object
    {
        $row = $this->sorgu('SELECT * FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL' . ($kilit ? ' FOR UPDATE' : ''), [$donem, $firma])->fetch(PDO::FETCH_OBJ);
        if (!$row) throw new DomainException('Dönem bulunamadı.');
        return $row;
    }

    public function onizle(int $firma, int $donem): array
    {
        return $this->atomik(fn() => $this->hazirla($firma, $donem));
    }

    private function hazirla(int $firma, int $donem): array
    {
        $d = $this->donem($firma, $donem, true);
        if (!(int) $d->kapali_mi) throw new DomainException('Yalnız kapalı dönem yayınlanabilir.');
        $bordro = new BordroPersonelModel();
        $parametre = new BordroParametreModel();
        $asgari = $parametre->getGenelAyar('asgari_ucret_net', $d->baslangic_tarihi);
        if ($asgari === null || (float) $asgari <= 0) throw new DomainException('Dönemin asgari net ücret ayarı bulunamadı.');
        $liste = $bordro->getPersonellerByDonem($donem);
        $hazir = []; $hatalar = []; $dislananlar = [];
        foreach ($liste as $p) {
            try {
                if (empty($p->hesaplama_tarihi)) throw new DomainException('Bordro henüz hesaplanmamış.');
                $sahip = $this->sorgu(
                    'SELECT id, aktif_mi, silinme_tarihi, isten_cikis_tarihi FROM personel WHERE id = ? AND firma_id = ?',
                    [$p->personel_id, $firma]
                )->fetch(PDO::FETCH_ASSOC);
                if (!$sahip) throw new DomainException('Personel firma kaydı uygun değil.');
                if (
                    !(int) $sahip['aktif_mi']
                    || !empty($sahip['silinme_tarihi'])
                    || (!empty($sahip['isten_cikis_tarihi']) && $sahip['isten_cikis_tarihi'] !== '0000-00-00')
                ) {
                    $dislananlar[] = [
                        'personel' => (string) $p->adi_soyadi,
                        'mesaj' => 'Pasif veya işten ayrılmış personel; PWA alıcısı olmadığı için yayına dahil edilmedi.',
                    ];
                    continue;
                }
                $h = $bordro->hesaplaOrtakGosterimDegerleri($p, $d, (float) $asgari);
                $icerik = BordroYayinIcerikService::olustur($p, $d, $h, $bordro->getMuhasebeOdemeOzeti($h));
                $hazir[] = ['personel_id' => (int) $p->personel_id, 'bordro_id' => (int) $p->id, 'icerik' => $icerik];
            } catch (DomainException $e) {
                $hatalar[] = ['personel' => (string) $p->adi_soyadi, 'mesaj' => $e->getMessage()];
            }
        }
        if (!$liste) $hatalar[] = ['personel' => '', 'mesaj' => 'Dönemde bordro bulunamadı.'];
        // Önizleme ile yayın arasındaki değişikliklerin yeniden inceleme gerektirmesi için.
        $hash = hash('sha256', json_encode($hazir, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return [
            'kayitlar' => $hazir,
            'hatalar' => $hatalar,
            'dislananlar' => $dislananlar,
            'onizleme_hash' => $hash,
        ];
    }

    public function yayinla(int $firma, int $donem, int $user, string $hash): array
    {
        return $this->atomik(function () use ($firma, $donem, $user, $hash) {
            $data = $this->hazirla($firma, $donem);
            return $this->yayiniKaydet($firma, $donem, $user, $hash, $data);
        });
    }

    /** Tek personele görünür, bildirim üretmeyen kontrollü PWA testi. */
    public function testYayinla(int $firma, int $donem, int $user, string $hash, int $personel): array
    {
        return $this->atomik(function () use ($firma, $donem, $user, $hash, $personel) {
            $data = $this->hazirla($firma, $donem);
            if (!hash_equals($data['onizleme_hash'], $hash)) {
                throw new DomainException('Döküm değişti. Önizlemeyi yeniden kontrol edin.');
            }
            $secili = null;
            foreach ($data['kayitlar'] as $kayit) {
                if ((int) $kayit['personel_id'] === $personel) { $secili = $kayit; break; }
            }
            if (!$secili) throw new DomainException('Seçilen personelin yayına hazır dökümü bulunamadı.');

            // Dönemde aynı anda yalnızca bir personel test dökümü aktif kalır.
            $this->sorgu("UPDATE bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id SET d.is_active = 0, d.deleted_at = NOW() WHERE y.firma_id = ? AND y.donem_id = ? AND y.durum = 'test' AND d.is_active = 1", [$firma, $donem]);
            $surum = (int) $this->sorgu("SELECT COALESCE(MIN(CASE WHEN durum = 'test' THEN surum END), 0) - 1 FROM bordro_yayin WHERE firma_id = ? AND donem_id = ?", [$firma, $donem])->fetchColumn();
            $this->sorgu("INSERT INTO bordro_yayin (firma_id, donem_id, surum, durum, yayinlayan_id) VALUES (?, ?, ?, 'test', ?)", [$firma, $donem, $surum, $user]);
            $yayin = (int) $this->db->lastInsertId();
            $json = json_encode($secili['icerik'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->sorgu('INSERT INTO bordro_yayin_dokum (yayin_id, personel_id, bordro_personel_id, icerik, icerik_hash) VALUES (?, ?, ?, ?, ?)', [$yayin, $personel, $secili['bordro_id'], $json, hash('sha256', $json)]);
            $dokum = (int) $this->db->lastInsertId();
            $this->olay($dokum, 'test_yayini', 'user', $user, 'Bildirim gönderilmeden tek personel testi');
            return ['yayin_token' => Security::encrypt($yayin), 'surum' => $surum, 'adet' => 1, 'bildirim' => false];
        });
    }

    /** Çağıran hazırlığı aynı transaction ve dönem kilidi altında tamamlar. */
    private function yayiniKaydet(int $firma, int $donem, int $user, string $hash, array $data): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Yayın kaydı transaction gerektirir.');
        if ($data['hatalar']) throw new DomainException('Hatalı bordrolar düzeltilmeden yayın yapılamaz.');
        if (!hash_equals($data['onizleme_hash'], $hash)) throw new DomainException('Döküm değişti. Önizlemeyi yeniden kontrol edin.');
        $aktif = $this->sorgu("SELECT id FROM bordro_yayin WHERE firma_id = ? AND donem_id = ? AND durum = 'yayinda' AND is_active = 1", [$firma, $donem])->fetchColumn();
        if ($aktif) return ['yayin_token' => Security::encrypt((int) $aktif), 'mevcut' => true];
        $surum = (int) $this->sorgu("SELECT COALESCE(MAX(CASE WHEN durum <> 'test' THEN surum END), 0) + 1 FROM bordro_yayin WHERE firma_id = ? AND donem_id = ?", [$firma, $donem])->fetchColumn();
        $this->sorgu("UPDATE bordro_yayin SET durum = 'arsiv' WHERE firma_id = ? AND donem_id = ? AND is_active = 1", [$firma, $donem]);
        $this->sorgu('INSERT INTO bordro_yayin (firma_id, donem_id, surum, yayinlayan_id) VALUES (?, ?, ?, ?)', [$firma, $donem, $surum, $user]);
        $id = (int) $this->db->lastInsertId();
        foreach ($data['kayitlar'] as $kayit) {
            $json = json_encode($kayit['icerik'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->sorgu('INSERT INTO bordro_yayin_dokum (yayin_id, personel_id, bordro_personel_id, icerik, icerik_hash) VALUES (?, ?, ?, ?, ?)', [$id, $kayit['personel_id'], $kayit['bordro_id'], $json, hash('sha256', $json)]);
            $dokum = (int) $this->db->lastInsertId();
            $this->olay($dokum, 'yayinlandi', 'user', $user, 'Sürüm ' . $surum);
            foreach ([0, 3, 7] as $gun) {
                $this->sorgu('INSERT INTO bordro_yayin_kuyruk (dokum_id, gun, planlanan) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))', [$dokum, $gun, $gun]);
            }
        }
        return ['yayin_token' => Security::encrypt($id), 'surum' => $surum, 'adet' => count($data['kayitlar'])];
    }

    /** Yayınla/beyan/yeniden aç aynı dönem kilidini kullanır. */
    public function yenidenAc(int $firma, int $donem, int $user): void
    {
        $this->atomik(function () use ($firma, $donem, $user) {
            $this->donem($firma, $donem, true);
            $this->sorgu('UPDATE bordro_donemi SET kapali_mi = 0 WHERE id = ? AND firma_id = ?', [$donem, $firma]);
            $ids = $this->sorgu("SELECT d.id FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id WHERE y.firma_id = ? AND y.donem_id = ? AND y.durum = 'yayinda'", [$firma, $donem])->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) $this->olay((int) $id, 'revizyon', 'user', $user);
            $this->sorgu("UPDATE bordro_yayin SET durum = 'revizyon' WHERE firma_id = ? AND donem_id = ? AND durum = 'yayinda'", [$firma, $donem]);
        });
    }

    private function olay(int $dokum, string $tur, string $aktor, int $id, ?string $detay = null): void
    {
        $this->sorgu('INSERT INTO bordro_yayin_olay (dokum_id, tur, aktor_tipi, aktor_id, detay) VALUES (?, ?, ?, ?, ?)', [$dokum, $tur, $aktor, $id, $detay]);
    }

    public function personelListe(int $firma, int $personel): array
    {
        $rows = $this->sorgu('SELECT d.*, y.surum, y.durum, y.yayin_tarihi FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id JOIN personel p ON p.id = d.personel_id WHERE d.personel_id = ? AND p.firma_id = ? AND y.firma_id = ? AND d.is_active = 1 AND y.is_active = 1 ORDER BY y.yayin_tarihi DESC, y.id DESC', [$personel, $firma, $firma])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($row) {
            $i = $this->icerik($row);
            $surum = $row['durum'] === 'test' ? 'T' . abs((int) $row['surum']) : (int) $row['surum'];
            return ['token' => Security::encrypt((int) $row['id']), 'donem' => $i['donem'], 'baslangic' => $i['baslangic'], 'surum' => $surum, 'durum' => $row['durum'], 'yayin_tarihi' => $row['yayin_tarihi'], 'beyan_tarihi' => $row['beyan_tarihi'], 'goruntuleme_tarihi' => $row['goruntuleme_tarihi'], 'banka_net_kurus' => $i['banka_net_kurus']];
        }, $rows);
    }

    private function icerik(array $row): array
    {
        if (!hash_equals($row['icerik_hash'], hash('sha256', $row['icerik']))) throw new DomainException('Döküm bütünlüğü doğrulanamadı.');
        return json_decode($row['icerik'], true, 512, JSON_THROW_ON_ERROR);
    }

    private function dokum(int $firma, int $id, ?int $personel = null, bool $kilit = false): array
    {
        $sql = 'SELECT d.*, y.donem_id, y.firma_id, y.surum, y.durum, y.yayin_tarihi FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id JOIN personel p ON p.id = d.personel_id WHERE d.id = ? AND y.firma_id = ? AND p.firma_id = ? AND d.is_active = 1 AND y.is_active = 1';
        $args = [$id, $firma, $firma];
        if ($personel !== null) { $sql .= ' AND d.personel_id = ?'; $args[] = $personel; }
        $row = $this->sorgu($sql . ($kilit ? ' FOR UPDATE' : ''), $args)->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new DomainException('Döküm bulunamadı.');
        $this->icerik($row);
        return $row;
    }

    public function detay(int $firma, int $id, ?int $personel = null): array
    {
        $r = $this->dokum($firma, $id, $personel);
        $talepler = $this->sorgu('SELECT id, mesaj, durum, tarih FROM bordro_yayin_talep WHERE dokum_id = ? AND is_active = 1 ORDER BY id', [$id])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($talepler as &$t) {
            $t['yanitlar'] = $this->sorgu(
                'SELECT y.mesaj, y.tarih, COALESCE(NULLIF(TRIM(u.adi_soyadi), \'\'), u.user_name, \'Yetkili\') AS kullanici ' .
                'FROM bordro_yayin_yanit y ' .
                'LEFT JOIN users u ON u.id = y.kullanici_id ' .
                'WHERE y.talep_id = ? AND y.is_active = 1 ' .
                'ORDER BY y.id',
                [$t['id']]
            )->fetchAll(PDO::FETCH_ASSOC);
            $t['token'] = Security::encrypt((int) $t['id']); unset($t['id']);
        }
        $olaylar = $personel === null ? $this->sorgu('SELECT tur, aktor_tipi, detay, tarih FROM bordro_yayin_olay WHERE dokum_id = ? AND is_active = 1 ORDER BY id', [$id])->fetchAll(PDO::FETCH_ASSOC) : [];
        $surum = $r['durum'] === 'test' ? 'T' . abs((int) $r['surum']) : (int) $r['surum'];
        return ['olaylar' => $olaylar, 'token' => Security::encrypt($id), 'icerik' => $this->icerik($r), 'icerik_hash' => $r['icerik_hash'], 'surum' => $surum, 'durum' => $r['durum'], 'yayin_tarihi' => $r['yayin_tarihi'], 'goruntuleme_tarihi' => $r['goruntuleme_tarihi'], 'beyan_tarihi' => $r['beyan_tarihi'], 'beyan_metni' => $r['beyan_metni'] ?: BordroYayinIcerikService::BEYAN, 'beyan_metin_surumu' => (int) ($r['beyan_metin_surumu'] ?: BordroYayinIcerikService::METIN_SURUMU), 'talepler' => $talepler];
    }

    public function personelIslem(int $firma, int $id, int $personel, string $islem, string $mesaj = ''): void
    {
        $this->atomik(function () use ($firma, $id, $personel, $islem, $mesaj) {
            $ilk = $this->dokum($firma, $id, $personel);
            $d = $this->donem($firma, (int) $ilk['donem_id'], true);
            // Kilitli okuma InnoDB'nin eski transaction snapshot'ını kullanmaz.
            $r = $this->dokum($firma, $id, $personel, true);
            if ($islem === 'goruntule') {
                if (!$r['goruntuleme_tarihi']) {
                    $this->sorgu('UPDATE bordro_yayin_dokum SET goruntuleme_tarihi = NOW() WHERE id = ?', [$id]);
                    $this->olay($id, 'goruntulendi', 'personel', $personel);
                }
                return;
            }
            if ($islem === 'beyan') {
                if (!in_array($r['durum'], ['yayinda', 'test'], true) || !(int) $d->kapali_mi) throw new DomainException('Bu sürüm için yeni beyan alınamıyor.');
                if ($r['beyan_tarihi']) return;
                if (!$r['goruntuleme_tarihi']) throw new DomainException('Önce dökümü görüntüleyin.');
                $this->sorgu('UPDATE bordro_yayin_dokum SET beyan_tarihi = NOW(), beyan_metni = ?, beyan_metin_surumu = ? WHERE id = ?', [BordroYayinIcerikService::BEYAN, BordroYayinIcerikService::METIN_SURUMU, $id]);
                $this->olay($id, 'okuma_beyani', 'personel', $personel, BordroYayinIcerikService::BEYAN);
                return;
            }
            if ($islem !== 'talep') throw new DomainException('Geçersiz işlem.');
            $mesaj = trim($mesaj);
            if (mb_strlen($mesaj) < 10 || mb_strlen($mesaj) > 4000) throw new DomainException('Talep açıklaması 10–4000 karakter olmalıdır.');
            $acik = $this->sorgu("SELECT id FROM bordro_yayin_talep WHERE dokum_id = ? AND durum = 'acik' AND is_active = 1 FOR UPDATE", [$id])->fetchColumn();
            if ($acik) throw new DomainException('Bu döküm için açık inceleme talebiniz bulunuyor.');
            $this->sorgu('INSERT INTO bordro_yayin_talep (dokum_id, mesaj) VALUES (?, ?)', [$id, $mesaj]);
            $this->olay($id, 'inceleme_talebi', 'personel', $personel, $mesaj);
        });
    }

    public function takip(int $firma, int $donem): array
    {
        $this->donem($firma, $donem);
        $rows = $this->sorgu('SELECT d.*, y.surum, y.durum, y.yayin_tarihi, (SELECT COUNT(*) FROM bordro_yayin_talep t WHERE t.dokum_id = d.id AND t.is_active = 1) talep_sayisi, (SELECT COUNT(*) FROM bordro_yayin_talep t WHERE t.dokum_id = d.id AND t.is_active = 1 AND t.durum = \'acik\') acik_talep, (SELECT q.durum FROM bordro_yayin_kuyruk q WHERE q.dokum_id = d.id AND q.gun = 0 AND q.is_active = 1) ilk_bildirim_durumu, (SELECT COUNT(*) FROM bordro_yayin_kuyruk q WHERE q.dokum_id = d.id AND q.durum = \'basarisiz\' AND q.is_active = 1) basarisiz_bildirim FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id WHERE y.firma_id = ? AND y.donem_id = ? AND y.is_active = 1 AND d.is_active = 1 ORDER BY y.surum DESC, d.id', [$firma, $donem])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $i = $this->icerik($r);
            $surum = $r['durum'] === 'test' ? 'T' . abs((int) $r['surum']) : (int) $r['surum'];
            return ['token' => Security::encrypt((int) $r['id']), 'personel' => $i['personel'], 'donem' => $i['donem'], 'surum' => $surum, 'durum' => $r['durum'], 'yayin_tarihi' => $r['yayin_tarihi'], 'goruntuleme_tarihi' => $r['goruntuleme_tarihi'], 'beyan_tarihi' => $r['beyan_tarihi'], 'banka_net_kurus' => $i['banka_net_kurus'], 'talep_sayisi' => (int) $r['talep_sayisi'], 'acik_talep' => (int) $r['acik_talep'], 'ilk_bildirim_durumu' => $r['ilk_bildirim_durumu'], 'basarisiz_bildirim' => (int) $r['basarisiz_bildirim']];
        }, $rows);
    }

    public function yanitla(int $firma, int $talep, int $user, string $mesaj): void
    {
        $mesaj = trim($mesaj);
        if (mb_strlen($mesaj) < 10 || mb_strlen($mesaj) > 4000) throw new DomainException('Yanıt 10–4000 karakter olmalıdır.');
        $this->atomik(function () use ($firma, $talep, $user, $mesaj) {
            $t = $this->sorgu('SELECT t.* FROM bordro_yayin_talep t JOIN bordro_yayin_dokum d ON d.id = t.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE t.id = ? AND y.firma_id = ? AND y.is_active = 1 AND d.is_active = 1 AND t.is_active = 1 FOR UPDATE', [$talep, $firma])->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['durum'] !== 'acik') throw new DomainException('Açık talep bulunamadı.');
            $this->sorgu('INSERT INTO bordro_yayin_yanit (talep_id, kullanici_id, mesaj) VALUES (?, ?, ?)', [$talep, $user, $mesaj]);
            $this->sorgu("UPDATE bordro_yayin_talep SET durum = 'sonuclandi' WHERE id = ?", [$talep]);
            $this->olay((int) $t['dokum_id'], 'talep_yanitlandi', 'user', $user, $mesaj);
        });
    }

    /** Tek işçi kuyruğu sahiplenir; kesilen iş 15 dakika sonra tekrar denenebilir. */
    public function bildirimAl(): ?array
    {
        return $this->atomik(function () {
            $this->sorgu("UPDATE bordro_yayin_kuyruk SET durum = 'basarisiz', kilit_token = NULL, son_hata = 'Son gönderim denemesi zaman aşımına uğradı.' WHERE durum = 'isleniyor' AND deneme >= 5 AND kilit_tarihi < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $q = $this->sorgu("SELECT q.id FROM bordro_yayin_kuyruk q WHERE q.is_active = 1 AND q.deneme < 5 AND q.planlanan <= NOW() AND (q.durum = 'bekliyor' OR (q.durum = 'isleniyor' AND q.kilit_tarihi < DATE_SUB(NOW(), INTERVAL 15 MINUTE))) ORDER BY q.planlanan, q.id LIMIT 1 FOR UPDATE")->fetchColumn();
            if (!$q) return null;
            $token = bin2hex(random_bytes(16));
            $this->sorgu("UPDATE bordro_yayin_kuyruk SET durum = 'isleniyor', deneme = deneme + 1, kilit_tarihi = NOW(), kilit_token = ? WHERE id = ?", [$token, $q]);
            return $this->sorgu('SELECT q.*, d.personel_id, d.beyan_tarihi, y.durum yayin_durumu, bd.kapali_mi, p.aktif_mi, p.isten_cikis_tarihi, p.silinme_tarihi FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id JOIN bordro_donemi bd ON bd.id = y.donem_id JOIN personel p ON p.id = d.personel_id WHERE q.id = ?', [$q])->fetch(PDO::FETCH_ASSOC);
        });
    }

    public function bildirimUygula(array $q, callable $gonder): string
    {
        return $this->atomik(function () use ($q, $gonder) {
            $donem = $this->sorgu('SELECT y.firma_id, y.donem_id FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE q.id = ?', [$q['id']])->fetch(PDO::FETCH_ASSOC);
            if (!$donem) return 'atlandi';
            $this->donem((int) $donem['firma_id'], (int) $donem['donem_id'], true);
            $r = $this->sorgu('SELECT q.*, d.personel_id, d.beyan_tarihi, d.is_active dokum_aktif, y.is_active yayin_aktif, y.firma_id, y.durum yayin_durumu, bd.kapali_mi, p.firma_id personel_firma, p.aktif_mi, p.isten_cikis_tarihi, p.silinme_tarihi FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id JOIN bordro_donemi bd ON bd.id = y.donem_id JOIN personel p ON p.id = d.personel_id WHERE q.id = ? AND q.kilit_token = ? FOR UPDATE', [$q['id'], $q['kilit_token']])->fetch(PDO::FETCH_ASSOC);
            if (!$r) return 'atlandi';
            $uygun = $r['yayin_durumu'] === 'yayinda' && (int) $r['kapali_mi'] === 1
                && $r['dokum_aktif'] && $r['yayin_aktif'] && $r['aktif_mi']
                && !$r['silinme_tarihi'] && (!$r['isten_cikis_tarihi'] || $r['isten_cikis_tarihi'] === '0000-00-00')
                && (int) $r['firma_id'] === (int) $r['personel_firma'] && !$r['beyan_tarihi'];
            $durum = $uygun ? $gonder($r) : 'atlandi';
            $this->bildirimBitir($q, $durum, $durum === 'bekliyor' ? 'Push gönderimi başarısız.' : null);
            return $durum;
        });
    }

    public function bildirimBitir(array $q, string $durum, ?string $hata = null): void
    {
        if (!in_array($durum, ['gonderildi', 'atlandi', 'bekliyor'], true)) throw new DomainException('Geçersiz kuyruk durumu.');
        if ($durum === 'bekliyor' && (int) $q['deneme'] >= 5) $durum = 'basarisiz';
        $this->sorgu('UPDATE bordro_yayin_kuyruk SET durum = ?, son_hata = ?, gonderim_tarihi = IF(? = \'gonderildi\', NOW(), NULL), planlanan = IF(? = \'bekliyor\', DATE_ADD(NOW(), INTERVAL 1 HOUR), planlanan), kilit_token = NULL WHERE id = ? AND kilit_token = ?', [$durum, $hata, $durum, $durum, $q['id'], $q['kilit_token']]);
    }
}
