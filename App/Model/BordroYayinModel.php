<?php
namespace App\Model;

use App\Helper\Security;
use App\Service\BordroPusulaService;
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

    private const PARASAL_ALANLAR = [
        'banka_net_kurus', 'kalemler', 'calisma_gun', 'fiili_gun', 'kazanc_toplami_kurus',
        'yasal_kesinti_toplami_kurus', 'ozel_kesinti_toplami_kurus', 'toplam_kesinti_kurus',
        'yasal_kesintiler', 'resmi_matrahlar', 'calisma_ve_izin_gunleri', 'vergi_indirimleri',
    ];

    private const IC_SNAPSHOT_ALANLARI = [
        'brut_maas', 'net_maas', 'sgk_isci', 'issizlik_isci', 'gelir_vergisi', 'damga_vergisi',
        'kesinti_tutar', 'prim_tutar', 'fazla_mesai_tutar',
        'banka_odemesi', 'elden_odeme', 'sodexo_odemesi', 'diger_odeme',
    ];

    private function kilitModel(): BordroOnayKilidiModel
    {
        return new BordroOnayKilidiModel($this->db);
    }

    public static function parasalParmakIzi(array $icerik): string
    {
        $secim = [];
        foreach (self::PARASAL_ALANLAR as $alan) {
            $secim[$alan] = $icerik[$alan] ?? null;
        }
        return hash('sha256', json_encode($secim, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function onayliIcerik(array $kilit): array
    {
        if (!hash_equals((string) $kilit['icerik_hash'], hash('sha256', (string) $kilit['icerik']))) {
            throw new DomainException('Onaylı döküm bütünlüğü doğrulanamadı.');
        }
        return json_decode((string) $kilit['icerik'], true, 512, JSON_THROW_ON_ERROR);
    }

    private function guncelDokumPersonelleri(int $firma, int $donem): array
    {
        $ids = $this->sorgu(
            "SELECT DISTINCT d.personel_id FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id
             WHERE y.firma_id = ? AND y.donem_id = ? AND y.is_active = 1 AND y.durum = 'yayinda'
               AND d.is_active = 1 AND d.revizyon_tarihi IS NULL",
            [$firma, $donem]
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_fill_keys(array_map('intval', $ids), true);
    }

    public function onizle(int $firma, int $donem): array
    {
        return $this->atomik(fn() => $this->hazirla($firma, $donem));
    }

    public function onayliDokumMutabakatHatalari(int $firma, int $donem): array
    {
        $d = $this->donem($firma, $donem);
        $kilitler = $this->kilitModel()->donemKilitleri($donem);
        if (!$kilitler) return [];
        $firmaKaydi = $this->sorgu('SELECT * FROM firmalar WHERE id = ? AND silinme_tarihi IS NULL', [$firma])->fetch(PDO::FETCH_OBJ);
        if (!$firmaKaydi) throw new DomainException('Firma bilgileri bulunamadı.');
        $bordro = new BordroPersonelModel();
        $satirlar = [];
        foreach ($bordro->getPersonellerByDonem($donem, array_keys($kilitler)) as $satir) {
            $satirlar[(int) $satir->id] = $satir;
        }
        $hatalar = [];
        foreach ($kilitler as $bpId => $kilit) {
            $ad = (string) $kilit['adi_soyadi'];
            try {
                if (!isset($satirlar[$bpId])) throw new DomainException('Bordro kaydı bulunamadı.');
                $guncel = BordroPusulaService::yayinIcerigi($satirlar[$bpId], $d, $firmaKaydi, $bordro);
                if (!hash_equals(self::parasalParmakIzi($this->onayliIcerik($kilit)), self::parasalParmakIzi($guncel))) {
                    $hatalar[] = $ad . ': Personelin onayladığı döküm ile kayıtlı bordro uyuşmuyor. Onayı geri alıp yeniden hesaplayın.';
                }
            } catch (DomainException $e) {
                $hatalar[] = $ad . ': Onaylı döküm doğrulanamadı (' . $e->getMessage() . ')';
            }
        }
        return $hatalar;
    }

    private function hazirla(int $firma, int $donem): array
    {
        $d = $this->donem($firma, $donem, true);
        if (!(int) $d->kapali_mi) throw new DomainException('Yalnız kapalı dönem yayınlanabilir.');
        $bordro = new BordroPersonelModel();
        $firmaKaydi = $this->sorgu('SELECT * FROM firmalar WHERE id = ? AND silinme_tarihi IS NULL', [$firma])->fetch(PDO::FETCH_OBJ);
        if (!$firmaKaydi) throw new DomainException('Firma bilgileri bulunamadı.');
        $liste = $bordro->getPersonellerByDonem($donem);
        $kilitler = $this->kilitModel()->donemKilitleri($donem);
        $guncel = $this->guncelDokumPersonelleri($firma, $donem);
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
                // Yönetim raporu ve dijital onay aynı kayıtlı resmî pusula üreticisini kullanır.
                $icerik = BordroPusulaService::yayinIcerigi($p, $d, $firmaKaydi, $bordro);
                $kilit = $kilitler[(int) $p->id] ?? null;
                if ($kilit !== null && !hash_equals(self::parasalParmakIzi($this->onayliIcerik($kilit)), self::parasalParmakIzi($icerik))) {
                    throw new DomainException('Personelin onayladığı döküm ile kayıtlı bordro uyuşmuyor. Onayı geri alıp yeniden hesaplayın.');
                }
                $yayinDurumu = isset($guncel[(int) $p->personel_id]) ? ($kilit !== null ? 'onayli' : 'yayinda') : 'yeni';
                $hazir[] = ['personel_id' => (int) $p->personel_id, 'bordro_id' => (int) $p->id, 'yayin_durumu' => $yayinDurumu, 'icerik' => $icerik];
            } catch (DomainException $e) {
                $hatalar[] = ['personel' => (string) $p->adi_soyadi, 'mesaj' => $e->getMessage()];
            }
        }
        if (!$liste) $hatalar[] = ['personel' => '', 'mesaj' => 'Dönemde bordro bulunamadı.'];
        // Önizleme ile yayın arasındaki değişikliklerin yeniden inceleme gerektirmesi için.
        $hash = hash('sha256', json_encode($hazir, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $yeniAdet = count(array_filter($hazir, static fn($k) => $k['yayin_durumu'] === 'yeni'));
        return [
            'kayitlar' => $hazir,
            'hatalar' => $hatalar,
            'dislananlar' => $dislananlar,
            'onizleme_hash' => $hash,
            'yeni_adet' => $yeniAdet,
            'korunan_adet' => count($hazir) - $yeniAdet,
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
            return $this->testYayiniKaydet($firma, $donem, $user, $personel, $secili);
        });
    }

    /** Hazırlanmış tek personel snapshot'ını diğer personellerin testlerini bozmadan kaydeder. */
    private function testYayiniKaydet(int $firma, int $donem, int $user, int $personel, array $secili): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Test yayını transaction gerektirir.');
        // Her personelin yalnız son test dökümü aktif kalır; başka personellerin testleri korunur.
        $this->sorgu("UPDATE bordro_yayin_dokum SET is_active = 0, deleted_at = NOW() WHERE personel_id = ? AND is_active = 1 AND yayin_id IN (SELECT id FROM bordro_yayin WHERE firma_id = ? AND donem_id = ? AND durum = 'test' AND is_active = 1)", [$personel, $firma, $donem]);
        $surum = (int) $this->sorgu("SELECT COALESCE(MIN(CASE WHEN durum = 'test' THEN surum END), 0) - 1 FROM bordro_yayin WHERE firma_id = ? AND donem_id = ?", [$firma, $donem])->fetchColumn();
        $this->sorgu("INSERT INTO bordro_yayin (firma_id, donem_id, surum, durum, yayinlayan_id) VALUES (?, ?, ?, 'test', ?)", [$firma, $donem, $surum, $user]);
        $yayin = (int) $this->db->lastInsertId();
        $json = json_encode($secili['icerik'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->sorgu('INSERT INTO bordro_yayin_dokum (yayin_id, personel_id, bordro_personel_id, icerik, icerik_hash) VALUES (?, ?, ?, ?, ?)', [$yayin, $personel, $secili['bordro_id'], $json, hash('sha256', $json)]);
        $dokum = (int) $this->db->lastInsertId();
        $this->sorgu('UPDATE bordro_yayin_dokum SET pusula_no = ?, gonderim_tarihi = NOW() WHERE id = ?', [$this->pusulaNo($secili['icerik'], $dokum), $dokum]);
        $this->olay($dokum, 'test_yayini', 'user', $user, 'Bildirim gönderilmeden tek personel testi');
        return ['yayin_token' => Security::encrypt($yayin), 'surum' => $surum, 'adet' => 1, 'bildirim' => false];
    }

    /** Çağıran hazırlığı aynı transaction ve dönem kilidi altında tamamlar. */
    private function yayiniKaydet(int $firma, int $donem, int $user, string $hash, array $data): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Yayın kaydı transaction gerektirir.');
        if ($data['hatalar']) throw new DomainException('Hatalı bordrolar düzeltilmeden yayın yapılamaz.');
        if (!hash_equals($data['onizleme_hash'], $hash)) throw new DomainException('Döküm değişti. Önizlemeyi yeniden kontrol edin.');
        $guncel = $this->guncelDokumPersonelleri($firma, $donem);
        $yeniKayitlar = array_values(array_filter($data['kayitlar'], static fn($k) => !isset($guncel[(int) $k['personel_id']])));
        if (!$yeniKayitlar) {
            $aktif = $this->sorgu("SELECT id FROM bordro_yayin WHERE firma_id = ? AND donem_id = ? AND durum = 'yayinda' AND is_active = 1 ORDER BY surum DESC LIMIT 1", [$firma, $donem])->fetchColumn();
            return ['yayin_token' => $aktif ? Security::encrypt((int) $aktif) : null, 'mevcut' => true, 'adet' => 0, 'korunan' => count($guncel)];
        }
        $surum = (int) $this->sorgu("SELECT COALESCE(MAX(CASE WHEN durum <> 'test' THEN surum END), 0) + 1 FROM bordro_yayin WHERE firma_id = ? AND donem_id = ?", [$firma, $donem])->fetchColumn();
        $this->sorgu(
            "UPDATE bordro_yayin SET durum = 'arsiv' WHERE firma_id = ? AND donem_id = ? AND is_active = 1
             AND (durum IN ('test','revizyon') OR NOT EXISTS (
                 SELECT 1 FROM bordro_yayin_dokum d WHERE d.yayin_id = bordro_yayin.id AND d.is_active = 1 AND d.revizyon_tarihi IS NULL
             ))",
            [$firma, $donem]
        );
        $this->sorgu('INSERT INTO bordro_yayin (firma_id, donem_id, surum, yayinlayan_id) VALUES (?, ?, ?, ?)', [$firma, $donem, $surum, $user]);
        $id = (int) $this->db->lastInsertId();
        foreach ($yeniKayitlar as $kayit) {
            $json = json_encode($kayit['icerik'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->sorgu('INSERT INTO bordro_yayin_dokum (yayin_id, personel_id, bordro_personel_id, icerik, icerik_hash) VALUES (?, ?, ?, ?, ?)', [$id, $kayit['personel_id'], $kayit['bordro_id'], $json, hash('sha256', $json)]);
            $dokum = (int) $this->db->lastInsertId();
            $this->sorgu('UPDATE bordro_yayin_dokum SET pusula_no = ?, gonderim_tarihi = NOW() WHERE id = ?', [$this->pusulaNo($kayit['icerik'], $dokum), $dokum]);
            $this->olay($dokum, 'yayinlandi', 'user', $user, 'Sürüm ' . $surum);
            foreach ([0, 3, 7] as $gun) {
                $this->sorgu('INSERT INTO bordro_yayin_kuyruk (dokum_id, gun, planlanan) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))', [$dokum, $gun, $gun]);
            }
        }
        return ['yayin_token' => Security::encrypt($id), 'surum' => $surum, 'adet' => count($yeniKayitlar), 'korunan' => count($guncel)];
    }

    private function pusulaNo(array $icerik, int $dokumId): string
    {
        $ts = strtotime((string) ($icerik['baslangic'] ?? '')) ?: time();
        return date('Y/m', $ts) . '/' . str_pad((string) $dokumId, 6, '0', STR_PAD_LEFT);
    }

    private function revizyonKaydi(int $firma, int $donem, string $tur, int $user, ?string $gerekce, array $sayilar = [], ?string $hash = null): int
    {
        $this->sorgu(
            'INSERT INTO bordro_yayin_revizyon (firma_id, donem_id, tur, gerekce, yapan_id, incelenen_sayisi, revize_sayisi, ic_guncellenen_sayisi, korunan_sayisi, onizleme_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$firma, $donem, $tur, $gerekce, $user, (int) ($sayilar['incelenen'] ?? 0), (int) ($sayilar['revize'] ?? 0), (int) ($sayilar['ic_guncellenen'] ?? 0), (int) ($sayilar['korunan'] ?? 0), $hash]
        );
        return (int) $this->db->lastInsertId();
    }

    private function revizyonaAl(array $dokumIds, int $revizyonId, int $user, string $olayTuru, ?string $detay): void
    {
        foreach ($dokumIds as $dokumId) {
            $this->sorgu('UPDATE bordro_yayin_dokum SET revizyon_tarihi = NOW(), revizyon_id = ? WHERE id = ? AND revizyon_tarihi IS NULL', [$revizyonId, (int) $dokumId]);
            $this->olay((int) $dokumId, $olayTuru, 'user', $user, $detay);
        }
    }

    private function acikDokumlar(int $firma, int $donem, ?int $personel = null): array
    {
        $sql = "SELECT d.id, d.personel_id FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id
                WHERE y.firma_id = ? AND y.donem_id = ? AND y.is_active = 1 AND y.durum IN ('yayinda','test')
                  AND d.is_active = 1 AND d.revizyon_tarihi IS NULL";
        $args = [$firma, $donem];
        if ($personel !== null) { $sql .= ' AND d.personel_id = ?'; $args[] = $personel; }
        return $this->sorgu($sql . ' ORDER BY d.id', $args)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Onaylı personelin dökümleri korunur; yalnız onaysız veya itirazlı dökümler revizyona alınır. */
    public function yenidenAc(int $firma, int $donem, int $user): array
    {
        return $this->atomik(function () use ($firma, $donem, $user) {
            $this->donem($firma, $donem, true);
            $this->sorgu('UPDATE bordro_donemi SET kapali_mi = 0 WHERE id = ? AND firma_id = ?', [$donem, $firma]);
            $kilitliPersoneller = [];
            foreach ($this->kilitModel()->donemKilitleri($donem) as $kilit) {
                $kilitliPersoneller[(int) $kilit['personel_id']] = true;
            }
            $revize = [];
            foreach ($this->acikDokumlar($firma, $donem) as $dokum) {
                if (!isset($kilitliPersoneller[(int) $dokum['personel_id']])) $revize[] = (int) $dokum['id'];
            }
            if ($revize) {
                $revizyonId = $this->revizyonKaydi($firma, $donem, 'donem_acilis', $user, 'Dönem yeniden açıldı', [
                    'incelenen' => count($revize) + count($kilitliPersoneller),
                    'revize' => count($revize),
                    'korunan' => count($kilitliPersoneller),
                ]);
                $this->revizyonaAl($revize, $revizyonId, $user, 'revizyon', 'Dönem yeniden açıldı; onaylanmamış döküm revizyona alındı');
            }
            return ['revize' => count($revize), 'korunan' => count($kilitliPersoneller)];
        });
    }

    public function onayGeriAl(int $firma, int $dokumId, int $user, string $gerekce): array
    {
        $gerekce = trim($gerekce);
        if (mb_strlen($gerekce) < 10 || mb_strlen($gerekce) > 1000) throw new DomainException('Gerekçe 10–1000 karakter olmalıdır.');
        return $this->atomik(function () use ($firma, $dokumId, $user, $gerekce) {
            $ilk = $this->dokum($firma, $dokumId);
            $d = $this->donem($firma, (int) $ilk['donem_id'], true);
            if ((int) $d->kapali_mi) throw new DomainException('Onayı geri almak için önce dönemi açın.');
            $r = $this->dokum($firma, $dokumId, null, true);
            if (!empty($r['revizyon_tarihi']) || !in_array($r['durum'], ['yayinda', 'test'], true)) throw new DomainException('Bu döküm zaten revizyonda veya arşivde.');
            if (empty($r['beyan_tarihi'])) throw new DomainException('Yalnız personelin onayladığı döküm için onay geri alınabilir.');
            $ids = array_map(static fn($x) => (int) $x['id'], $this->acikDokumlar($firma, (int) $r['donem_id'], (int) $r['personel_id']));
            $revizyonId = $this->revizyonKaydi($firma, (int) $r['donem_id'], 'tekli', $user, $gerekce, ['incelenen' => 1, 'revize' => 1]);
            $this->revizyonaAl($ids, $revizyonId, $user, 'onay_geri_alindi', $gerekce);
            $icerik = $this->icerik($r);
            return ['personel' => (string) ($icerik['personel'] ?? ''), 'pusula_no' => (string) $r['pusula_no'], 'donem_id' => (int) $r['donem_id']];
        });
    }

    private function snapshotOzeti(int $bordroPersonelId): array
    {
        $row = $this->sorgu('SELECT ' . implode(', ', self::IC_SNAPSHOT_ALANLARI) . ' FROM bordro_personel WHERE id = ?', [$bordroPersonelId])->fetch(PDO::FETCH_ASSOC) ?: [];
        $ozet = [];
        foreach (self::IC_SNAPSHOT_ALANLARI as $alan) {
            $ozet[$alan] = BordroYayinIcerikService::kurus((float) ($row[$alan] ?? 0));
        }
        return $ozet;
    }

    private static function kalemFarklari(array $eski, array $yeni): array
    {
        $topla = static function (array $icerik): array {
            $harita = [];
            foreach ($icerik['kalemler'] ?? [] as $k) {
                $etiket = (string) ($k['etiket'] ?? '');
                $harita[$etiket] = ($harita[$etiket] ?? 0) + (int) ($k['kurus'] ?? 0);
            }
            return $harita;
        };
        $e = $topla($eski); $y = $topla($yeni);
        $farklar = [];
        foreach (array_unique(array_merge(array_keys($e), array_keys($y))) as $etiket) {
            if (($e[$etiket] ?? 0) !== ($y[$etiket] ?? 0)) {
                $farklar[] = ['etiket' => $etiket, 'eski_kurus' => $e[$etiket] ?? 0, 'yeni_kurus' => $y[$etiket] ?? 0];
            }
        }
        return $farklar;
    }

    /** Çağıran transaction açar; değişmeyen veya hatalı personelin hesabı savepoint ile geri alınır. */
    private function revizyonDegerlendir(int $firma, int $donem, array $dokumIds, bool $uygula, int $user, string $userAd): array
    {
        if (!$this->db->inTransaction()) throw new \LogicException('Revizyon değerlendirmesi transaction gerektirir.');
        $d = $this->donem($firma, $donem, true);
        if ((int) $d->kapali_mi) throw new DomainException('Toplu revizyon için önce dönemi açın.');
        $kilitler = $this->kilitModel()->donemKilitleri($donem);
        if ($dokumIds) {
            $yer = implode(',', array_fill(0, count($dokumIds), '?'));
            $secili = $this->sorgu(
                "SELECT DISTINCT d.personel_id FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id WHERE d.id IN ($yer) AND y.firma_id = ? AND y.donem_id = ?",
                array_merge(array_map('intval', $dokumIds), [$firma, $donem])
            )->fetchAll(PDO::FETCH_COLUMN);
            $secili = array_fill_keys(array_map('intval', $secili), true);
            $kilitler = array_filter($kilitler, static fn($k) => isset($secili[(int) $k['personel_id']]));
        }
        if (!$kilitler) throw new DomainException('Seçimde onaylı (kilitli) bordro bulunamadı.');
        $firmaKaydi = $this->sorgu('SELECT * FROM firmalar WHERE id = ? AND silinme_tarihi IS NULL', [$firma])->fetch(PDO::FETCH_OBJ);
        if (!$firmaKaydi) throw new DomainException('Firma bilgileri bulunamadı.');

        $hesap = new BordroPersonelModel();
        $hesap->batchMode = true;
        $hesap->onayKilidiDenetimi = false;
        $satirlar = [];
        $ozet = ['incelenen' => 0, 'revize' => 0, 'ic_guncellenen' => 0, 'korunan' => 0, 'hata' => 0];
        foreach ($kilitler as $bpId => $kilit) {
            $ozet['incelenen']++;
            $savepoint = 'bordro_rev_' . (int) $bpId;
            $this->db->exec('SAVEPOINT ' . $savepoint);
            $eski = $this->onayliIcerik($kilit);
            $eskiIc = $this->snapshotOzeti((int) $bpId);
            $yeni = null; $hata = null;
            try {
                $hesap->bulkDeleteAutoGeneratedRecords([(int) $bpId], $donem);
                if (!$hesap->hesaplaMaas((int) $bpId, $user, $userAd)) throw new DomainException('Bordro kaydı hesaplanamadı.');
                $satir = (new BordroPersonelModel())->getPersonellerByDonem($donem, [(int) $bpId])[0] ?? null;
                if (!$satir) throw new DomainException('Bordro kaydı bulunamadı.');
                $yeni = BordroPusulaService::yayinIcerigi($satir, $d, $firmaKaydi, $hesap);
            } catch (DomainException $e) {
                $hata = $e->getMessage();
            }
            $yeniIc = $this->snapshotOzeti((int) $bpId);
            if ($hata !== null) {
                $sonuc = 'hata';
            } elseif (!hash_equals(self::parasalParmakIzi($eski), self::parasalParmakIzi($yeni))) {
                $sonuc = 'degisti';
            } elseif ($eskiIc !== $yeniIc) {
                $sonuc = 'ic_degisti';
            } else {
                $sonuc = 'degismedi';
            }
            if (!$uygula || in_array($sonuc, ['hata', 'degismedi'], true)) {
                $this->db->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            }
            $this->db->exec('RELEASE SAVEPOINT ' . $savepoint);
            $ozet[match ($sonuc) { 'degisti' => 'revize', 'ic_degisti' => 'ic_guncellenen', 'degismedi' => 'korunan', default => 'hata' }]++;
            $satirlar[] = [
                'bordro_personel_id' => (int) $bpId,
                'personel_id' => (int) $kilit['personel_id'],
                'dokum_id' => (int) $kilit['dokum_id'],
                'personel' => (string) $kilit['adi_soyadi'],
                'sonuc' => $sonuc,
                'hata' => $hata,
                'eski_banka_kurus' => (int) ($eski['banka_net_kurus'] ?? 0),
                'yeni_banka_kurus' => $yeni !== null ? (int) ($yeni['banka_net_kurus'] ?? 0) : null,
                'eski_elden_kurus' => $eskiIc['elden_odeme'],
                'yeni_elden_kurus' => $hata === null ? $yeniIc['elden_odeme'] : null,
                'kalem_farklari' => $yeni !== null ? self::kalemFarklari($eski, $yeni) : [],
                'parmak_izi' => $yeni !== null ? self::parasalParmakIzi($yeni) : null,
                'ic_ozet' => $hata === null ? $yeniIc : null,
            ];
        }
        $hash = hash('sha256', json_encode(array_map(static fn($s) => [$s['bordro_personel_id'], $s['sonuc'], $s['parmak_izi'], $s['ic_ozet']], $satirlar), JSON_THROW_ON_ERROR));
        return ['satirlar' => $satirlar, 'ozet' => $ozet, 'onizleme_hash' => $hash];
    }

    public function revizyonOnizle(int $firma, int $donem, array $dokumIds, int $user, string $userAd): array
    {
        $this->db->beginTransaction();
        try {
            return $this->revizyonDegerlendir($firma, $donem, $dokumIds, false, $user, $userAd);
        } finally {
            if ($this->db->inTransaction()) $this->db->rollBack();
        }
    }

    public function revizyonUygula(int $firma, int $donem, array $dokumIds, int $user, string $userAd, string $gerekce, string $hash, int $onaySayisi): array
    {
        $gerekce = trim($gerekce);
        if (mb_strlen($gerekce) < 10 || mb_strlen($gerekce) > 1000) throw new DomainException('Gerekçe 10–1000 karakter olmalıdır.');
        return $this->atomik(function () use ($firma, $donem, $dokumIds, $user, $userAd, $gerekce, $hash, $onaySayisi) {
            $sonuc = $this->revizyonDegerlendir($firma, $donem, $dokumIds, true, $user, $userAd);
            if (!hash_equals($sonuc['onizleme_hash'], $hash)) throw new DomainException('Hesap sonucu önizlemeden sonra değişti. Önizlemeyi yeniden alın.');
            $ozet = $sonuc['ozet'];
            if ($ozet['revize'] + $ozet['ic_guncellenen'] === 0) throw new DomainException('Seçilen onaylı bordrolarda değişiklik bulunmadı; uygulanacak revizyon yok.');
            if ($onaySayisi !== $ozet['revize']) throw new DomainException('Onay için yazılan sayı, onayı kaldırılacak personel sayısıyla uyuşmuyor.');
            $revizyonId = $this->revizyonKaydi($firma, $donem, 'toplu', $user, $gerekce, $ozet, $hash);
            foreach ($sonuc['satirlar'] as $s) {
                if ($s['sonuc'] === 'degisti') {
                    $ids = array_map(static fn($x) => (int) $x['id'], $this->acikDokumlar($firma, $donem, $s['personel_id']));
                    $this->revizyonaAl($ids, $revizyonId, $user, 'toplu_revizyon', $gerekce);
                } elseif ($s['sonuc'] === 'ic_degisti') {
                    $this->olay($s['dokum_id'], 'ic_hesap_guncellendi', 'user', $user, 'Toplu revizyon: resmî döküm değişmedi, onay korundu. ' . $gerekce);
                }
            }
            return $sonuc + ['revizyon_id' => $revizyonId];
        });
    }

    public function yayinSil(int $firma, int $dokumId, int $user): array
    {
        return $this->atomik(function () use ($firma, $dokumId, $user) {
            $row = $this->dokum($firma, $dokumId, null, true);
            if (!empty($row['beyan_tarihi'])) throw new DomainException('Okuma beyanı verilmiş döküm silinemez.');
            $talep = (int) $this->sorgu('SELECT COUNT(*) FROM bordro_yayin_talep WHERE dokum_id = ? AND is_active = 1', [$dokumId])->fetchColumn();
            if ($talep > 0) throw new DomainException('İnceleme talebi bulunan döküm silinemez.');
            $this->sorgu('UPDATE bordro_yayin_dokum SET is_active = 0, deleted_at = NOW() WHERE id = ?', [$dokumId]);
            $this->sorgu('UPDATE bordro_yayin_kuyruk SET is_active = 0, deleted_at = NOW() WHERE dokum_id = ? AND is_active = 1', [$dokumId]);
            $this->olay($dokumId, 'silindi', 'user', $user, 'Onaylanmamış yayın yönetici tarafından silindi');
            $kalan = (int) $this->sorgu('SELECT COUNT(*) FROM bordro_yayin_dokum WHERE yayin_id = ? AND is_active = 1', [(int) $row['yayin_id']])->fetchColumn();
            if ($kalan === 0) $this->sorgu('UPDATE bordro_yayin SET is_active = 0, deleted_at = NOW() WHERE id = ?', [(int) $row['yayin_id']]);
            $icerik = $this->icerik($row);
            return ['personel' => (string) ($icerik['personel'] ?? ''), 'pusula_no' => (string) $row['pusula_no']];
        });
    }

    private function olay(int $dokum, string $tur, string $aktor, int $id, ?string $detay = null): void
    {
        $this->sorgu('INSERT INTO bordro_yayin_olay (dokum_id, tur, aktor_tipi, aktor_id, detay) VALUES (?, ?, ?, ?, ?)', [$dokum, $tur, $aktor, $id, $detay]);
    }

    private static function gorunenDurum(array $row): string
    {
        return !empty($row['revizyon_tarihi']) && in_array($row['durum'] ?? '', ['yayinda', 'test'], true) ? 'revizyon' : (string) ($row['durum'] ?? '');
    }

    private function islemDurumu(array $row): string
    {
        if (in_array($row['durum'] ?? '', ['revizyon', 'arsiv'], true) || !empty($row['revizyon_tarihi'])) return 'revize_edildi';
        if ((int) ($row['acik_talep'] ?? 0) > 0) return 'itiraz_edildi';
        if (!empty($row['beyan_tarihi'])) return 'onaylandi';
        if (!empty($row['goruntuleme_tarihi'])) return 'goruntulendi';
        return 'bekliyor';
    }

    public function personelListe(int $firma, int $personel): array
    {
        $rows = $this->sorgu("SELECT d.*, y.surum, y.durum, y.yayin_tarihi,
            (SELECT COUNT(*) FROM bordro_yayin_talep t WHERE t.dokum_id = d.id AND t.durum IN ('alindi','inceleniyor','ek_bilgi_bekleniyor','acik') AND t.is_active = 1) acik_talep
            FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id JOIN personel p ON p.id = d.personel_id
            WHERE d.personel_id = ? AND p.firma_id = ? AND y.firma_id = ? AND d.is_active = 1 AND y.is_active = 1
            ORDER BY y.yayin_tarihi DESC, y.id DESC", [$personel, $firma, $firma])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($row) {
            $i = $this->icerik($row);
            $surum = $row['durum'] === 'test' ? 'T' . abs((int) $row['surum']) : (int) $row['surum'];
            return ['token' => Security::encrypt((int) $row['id']), 'pusula_no' => $row['pusula_no'], 'donem' => $i['donem'], 'baslangic' => $i['baslangic'], 'surum' => $surum, 'durum' => self::gorunenDurum($row), 'islem_durumu' => $this->islemDurumu($row), 'yayin_tarihi' => $row['yayin_tarihi'], 'gonderim_tarihi' => $row['gonderim_tarihi'] ?: $row['yayin_tarihi'], 'beyan_tarihi' => $row['beyan_tarihi'], 'goruntuleme_tarihi' => $row['goruntuleme_tarihi'], 'banka_net_kurus' => $i['banka_net_kurus']];
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
        $talepler = $this->sorgu('SELECT id, konu, mesaj, durum, tarih, sonuc_tarihi FROM bordro_yayin_talep WHERE dokum_id = ? AND is_active = 1 ORDER BY id', [$id])->fetchAll(PDO::FETCH_ASSOC);
        foreach ($talepler as &$t) {
            $t['yanitlar'] = $this->sorgu(
                'SELECT y.mesaj, y.tarih, COALESCE(NULLIF(TRIM(u.adi_soyadi), \'\'), u.user_name, \'Yetkili\') AS kullanici ' .
                'FROM bordro_yayin_yanit y ' .
                'LEFT JOIN users u ON u.id = y.kullanici_id ' .
                'WHERE y.talep_id = ? AND y.is_active = 1 ' .
                'ORDER BY y.id',
                [$t['id']]
            )->fetchAll(PDO::FETCH_ASSOC);
            $t['ekler'] = $this->sorgu('SELECT id, dosya_adi, mime_tipi, dosya_boyutu, created_at FROM bordro_yayin_talep_ek WHERE talep_id = ? AND is_active = 1 ORDER BY id', [$t['id']])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($t['ekler'] as &$ek) { $ek['token'] = Security::encrypt((int) $ek['id']); unset($ek['id']); }
            $t['token'] = Security::encrypt((int) $t['id']); unset($t['id']);
        }
        $olaylar = $personel === null ? $this->sorgu('SELECT tur, aktor_tipi, detay, tarih FROM bordro_yayin_olay WHERE dokum_id = ? AND is_active = 1 ORDER BY id', [$id])->fetchAll(PDO::FETCH_ASSOC) : [];
        $surum = $r['durum'] === 'test' ? 'T' . abs((int) $r['surum']) : (int) $r['surum'];
        $r['acik_talep'] = count(array_filter($talepler, static fn($t) => in_array($t['durum'], ['acik','alindi','inceleniyor','ek_bilgi_bekleniyor'], true)));
        $onayKaniti = $personel === null ? ['onay_ip' => $r['onay_ip'] ?: null, 'onay_cihaz' => $r['onay_user_agent'] ?: null] : [];
        return $onayKaniti + ['olaylar' => $olaylar, 'token' => Security::encrypt($id), 'pusula_no' => $r['pusula_no'], 'icerik' => $this->icerik($r), 'icerik_hash' => $r['icerik_hash'], 'surum' => $surum, 'durum' => self::gorunenDurum($r), 'islem_durumu' => $this->islemDurumu($r), 'yayin_tarihi' => $r['yayin_tarihi'], 'gonderim_tarihi' => $r['gonderim_tarihi'] ?: $r['yayin_tarihi'], 'goruntuleme_tarihi' => $r['goruntuleme_tarihi'], 'beyan_tarihi' => $r['beyan_tarihi'], 'beyan_metni' => $r['beyan_metni'] ?: BordroYayinIcerikService::BEYAN, 'beyan_metin_surumu' => (int) ($r['beyan_metin_surumu'] ?: BordroYayinIcerikService::METIN_SURUMU), 'talepler' => $talepler];
    }

    public function personelIslem(int $firma, int $id, int $personel, string $islem, string $mesaj = '', array $kanit = []): ?int
    {
        return $this->atomik(function () use ($firma, $id, $personel, $islem, $mesaj, $kanit) {
            $ilk = $this->dokum($firma, $id, $personel);
            $d = $this->donem($firma, (int) $ilk['donem_id'], true);
            // Kilitli okuma InnoDB'nin eski transaction snapshot'ını kullanmaz.
            $r = $this->dokum($firma, $id, $personel, true);
            if ($islem === 'goruntule') {
                if (!$r['goruntuleme_tarihi']) {
                    $this->sorgu('UPDATE bordro_yayin_dokum SET goruntuleme_tarihi = NOW() WHERE id = ?', [$id]);
                }
                $this->olay($id, 'goruntulendi', 'personel', $personel);
                return null;
            }
            if ($islem === 'beyan') {
                if (!in_array($r['durum'], ['yayinda', 'test'], true) || !empty($r['revizyon_tarihi']) || !(int) $d->kapali_mi) throw new DomainException('Bu sürüm için yeni beyan alınamıyor.');
                if ($r['beyan_tarihi']) return null;
                if (!$r['goruntuleme_tarihi']) throw new DomainException('Önce dökümü görüntüleyin.');
                $this->sorgu('UPDATE bordro_yayin_dokum SET beyan_tarihi = NOW(), beyan_metni = ?, beyan_metin_surumu = ?, onay_ip = ?, onay_user_agent = ?, onay_session_hash = ?, onay_icerik_hash = ? WHERE id = ?', [BordroYayinIcerikService::BEYAN, BordroYayinIcerikService::METIN_SURUMU, $kanit['ip'] ?? null, mb_substr((string) ($kanit['user_agent'] ?? ''), 0, 500), $kanit['session_hash'] ?? null, $r['icerik_hash'], $id]);
                $this->olay($id, 'okuma_beyani', 'personel', $personel, BordroYayinIcerikService::BEYAN);
                return null;
            }
            if ($islem !== 'talep') throw new DomainException('Geçersiz işlem.');
            $mesaj = trim($mesaj);
            if (mb_strlen($mesaj) < 10 || mb_strlen($mesaj) > 4000) throw new DomainException('Talep açıklaması 10–4000 karakter olmalıdır.');
            $konu = (string) ($kanit['konu'] ?? 'diger');
            if (!in_array($konu, ['eksik_calisma','fazla_mesai','prim','avans','kesinti','yemek_yol','diger'], true)) throw new DomainException('Geçersiz itiraz konusu.');
            $acik = $this->sorgu("SELECT id FROM bordro_yayin_talep WHERE dokum_id = ? AND durum IN ('acik','alindi','inceleniyor','ek_bilgi_bekleniyor') AND is_active = 1 FOR UPDATE", [$id])->fetchColumn();
            if ($acik) throw new DomainException('Bu döküm için açık inceleme talebiniz bulunuyor.');
            $this->sorgu("INSERT INTO bordro_yayin_talep (dokum_id, konu, mesaj, durum) VALUES (?, ?, ?, 'alindi')", [$id, $konu, $mesaj]);
            $talepId = (int) $this->db->lastInsertId();
            $this->olay($id, 'itiraz_alindi', 'personel', $personel, $konu . ': ' . $mesaj);
            return $talepId;
        });
    }

    public function talepEkiKaydet(int $firma, int $talep, int $personel, array $dosya): void
    {
        $sahip = $this->sorgu('SELECT t.id FROM bordro_yayin_talep t JOIN bordro_yayin_dokum d ON d.id = t.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE t.id = ? AND d.personel_id = ? AND y.firma_id = ? AND t.is_active = 1 AND d.is_active = 1 AND y.is_active = 1', [$talep, $personel, $firma])->fetchColumn();
        if (!$sahip) throw new DomainException('İtiraz kaydı bulunamadı.');
        $this->sorgu('INSERT INTO bordro_yayin_talep_ek (talep_id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu) VALUES (?, ?, ?, ?, ?)', [$talep, $dosya['adi'], $dosya['yol'], $dosya['mime'], $dosya['boyut']]);
    }

    public function talepEki(int $firma, int $ek, ?int $personel = null): array
    {
        $sql = 'SELECT e.* FROM bordro_yayin_talep_ek e JOIN bordro_yayin_talep t ON t.id = e.talep_id JOIN bordro_yayin_dokum d ON d.id = t.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE e.id = ? AND y.firma_id = ? AND e.is_active = 1 AND t.is_active = 1 AND d.is_active = 1 AND y.is_active = 1';
        $args = [$ek, $firma];
        if ($personel !== null) { $sql .= ' AND d.personel_id = ?'; $args[] = $personel; }
        $row = $this->sorgu($sql, $args)->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new DomainException('İtiraz eki bulunamadı.');
        return $row;
    }

    public function takip(int $firma, int $donem): array
    {
        $this->donem($firma, $donem);
        $rows = $this->sorgu("SELECT d.*, y.surum, y.durum, y.yayin_tarihi,
            (SELECT COUNT(*) FROM bordro_yayin_talep t WHERE t.dokum_id = d.id AND t.is_active = 1) talep_sayisi,
            (SELECT COUNT(*) FROM bordro_yayin_talep t WHERE t.dokum_id = d.id AND t.is_active = 1 AND t.durum IN ('acik','alindi','inceleniyor','ek_bilgi_bekleniyor')) acik_talep,
            (SELECT q.durum FROM bordro_yayin_kuyruk q WHERE q.dokum_id = d.id AND q.gun = 0 AND q.is_active = 1) ilk_bildirim_durumu,
            (SELECT COUNT(*) FROM bordro_yayin_kuyruk q WHERE q.dokum_id = d.id AND q.durum = 'basarisiz' AND q.is_active = 1) basarisiz_bildirim
            FROM bordro_yayin_dokum d JOIN bordro_yayin y ON y.id = d.yayin_id
            WHERE y.firma_id = ? AND y.donem_id = ? AND y.is_active = 1 AND d.is_active = 1
            ORDER BY y.surum DESC, d.id", [$firma, $donem])->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $i = $this->icerik($r);
            $surum = $r['durum'] === 'test' ? 'T' . abs((int) $r['surum']) : (int) $r['surum'];
            return ['token' => Security::encrypt((int) $r['id']), 'pusula_no' => $r['pusula_no'], 'personel' => $i['personel'], 'donem' => $i['donem'], 'surum' => $surum, 'durum' => self::gorunenDurum($r), 'islem_durumu' => $this->islemDurumu($r), 'yayin_tarihi' => $r['yayin_tarihi'], 'gonderim_tarihi' => $r['gonderim_tarihi'] ?: $r['yayin_tarihi'], 'goruntuleme_tarihi' => $r['goruntuleme_tarihi'], 'beyan_tarihi' => $r['beyan_tarihi'], 'banka_net_kurus' => $i['banka_net_kurus'], 'talep_sayisi' => (int) $r['talep_sayisi'], 'acik_talep' => (int) $r['acik_talep'], 'ilk_bildirim_durumu' => $r['ilk_bildirim_durumu'], 'basarisiz_bildirim' => (int) $r['basarisiz_bildirim']];
        }, $rows);
    }

    public function yanitla(int $firma, int $talep, int $user, string $mesaj): array
    {
        $mesaj = trim($mesaj);
        if (mb_strlen($mesaj) < 10 || mb_strlen($mesaj) > 4000) throw new DomainException('Yanıt 10–4000 karakter olmalıdır.');
        return $this->atomik(function () use ($firma, $talep, $user, $mesaj) {
            $t = $this->sorgu('SELECT t.*, d.personel_id FROM bordro_yayin_talep t JOIN bordro_yayin_dokum d ON d.id = t.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE t.id = ? AND y.firma_id = ? AND y.is_active = 1 AND d.is_active = 1 AND t.is_active = 1 FOR UPDATE', [$talep, $firma])->fetch(PDO::FETCH_ASSOC);
            if (!$t || !in_array($t['durum'], ['acik','alindi','inceleniyor','ek_bilgi_bekleniyor'], true)) throw new DomainException('Açık talep bulunamadı.');
            $this->sorgu('INSERT INTO bordro_yayin_yanit (talep_id, kullanici_id, mesaj) VALUES (?, ?, ?)', [$talep, $user, $mesaj]);
            $this->sorgu("UPDATE bordro_yayin_talep SET durum = 'sonuclandi', atanan_kullanici_id = COALESCE(atanan_kullanici_id, ?), sonuc_tarihi = NOW() WHERE id = ?", [$user, $talep]);
            $this->olay((int) $t['dokum_id'], 'talep_yanitlandi', 'user', $user, $mesaj);
            return ['personel_id' => (int) $t['personel_id'], 'dokum_token' => Security::encrypt((int) $t['dokum_id'])];
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
            return $this->sorgu('SELECT q.*, d.personel_id, d.beyan_tarihi, y.surum, y.durum yayin_durumu, bd.kapali_mi, p.aktif_mi, p.isten_cikis_tarihi, p.silinme_tarihi FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id JOIN bordro_donemi bd ON bd.id = y.donem_id JOIN personel p ON p.id = d.personel_id WHERE q.id = ?', [$q])->fetch(PDO::FETCH_ASSOC);
        });
    }

    public function bildirimUygula(array $q, callable $gonder): string
    {
        return $this->atomik(function () use ($q, $gonder) {
            $donem = $this->sorgu('SELECT y.firma_id, y.donem_id FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id WHERE q.id = ?', [$q['id']])->fetch(PDO::FETCH_ASSOC);
            if (!$donem) return 'atlandi';
            $this->donem((int) $donem['firma_id'], (int) $donem['donem_id'], true);
            $r = $this->sorgu('SELECT q.*, d.personel_id, d.beyan_tarihi, d.revizyon_tarihi, d.is_active dokum_aktif, y.is_active yayin_aktif, y.firma_id, y.surum, y.durum yayin_durumu, bd.kapali_mi, p.firma_id personel_firma, p.aktif_mi, p.isten_cikis_tarihi, p.silinme_tarihi FROM bordro_yayin_kuyruk q JOIN bordro_yayin_dokum d ON d.id = q.dokum_id JOIN bordro_yayin y ON y.id = d.yayin_id JOIN bordro_donemi bd ON bd.id = y.donem_id JOIN personel p ON p.id = d.personel_id WHERE q.id = ? AND q.kilit_token = ? FOR UPDATE', [$q['id'], $q['kilit_token']])->fetch(PDO::FETCH_ASSOC);
            if (!$r) return 'atlandi';
            $uygun = $r['yayin_durumu'] === 'yayinda' && (int) $r['kapali_mi'] === 1
                && $r['dokum_aktif'] && $r['yayin_aktif'] && $r['aktif_mi'] && empty($r['revizyon_tarihi'])
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
