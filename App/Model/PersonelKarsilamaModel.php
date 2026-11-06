<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class PersonelKarsilamaModel extends Model
{
    protected $table = 'personel_karsilama_mesajlari';
    private const SABLON_TABLO = 'personel_karsilama_sablonlari';
    private const GECERLILIK_GUN = 30;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function varsayilanSablon(): array
    {
        return [
            'mesaj_metni' => "Merhaba {ad_soyad},\n\n{firma_adi} adına işe alım bilgilendirme mesajıdır.\nGerekli evrakları ve banka hesabı bilgilerini aşağıdaki sayfada bulabilirsiniz:\n\n{link}",
            'sayfa_baslik' => 'İşe Alımda Gerekli Evraklar',
            'evraklar' => "Kimlik Fotokopisi\nEhliyet Fotokopisi\nDiploma Fotokopisi\n[elden] 2 Adet Biyometrik ya da Vesikalık Fotoğraf\n- Son 6 ayda çekilmiş olacak\nİkametgah Belgesi (E-Devlet)\nAdli Sicil Kaydı (E-Devlet)\nNüfus Kayıt Örneği (E-Devlet)\n- Evlilerde eş çocuk bilgileri dahil, bekarlar için tüm aile bireyleri dahil\nSağlık Raporu",
            'banka_adimi' => 'QNB Bankasından kaşedeki firma bilgileriyle MAAŞ HESABI açılacak.',
            'kase_bilgisi' => "ER-SAN ELEKTRİK İNŞ.TAAH.TİC.LTD.ŞTİ.\nŞ.Pamir Cad. Dökmeci İş.Han. No:41/5\nTel&Fax: 0326 613 3860\nAkdeniz V.D. 336 054 1355",
        ];
    }

    public function getSablon(int $firmaId): array
    {
        $stmt = $this->db->prepare("SELECT mesaj_metni, sayfa_baslik, evraklar, banka_adimi, kase_bilgisi
            FROM " . self::SABLON_TABLO . "
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['firma_id' => $firmaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? array_merge(self::varsayilanSablon(), array_filter($row, static fn($v) => $v !== null)) : self::varsayilanSablon();
    }

    public function saveSablon(int $firmaId, array $data, int $userId): void
    {
        $stmt = $this->db->prepare("INSERT INTO " . self::SABLON_TABLO . "
            (firma_id, mesaj_metni, sayfa_baslik, evraklar, banka_adimi, kase_bilgisi, guncelleyen_id)
            VALUES (:firma_id, :mesaj_metni, :sayfa_baslik, :evraklar, :banka_adimi, :kase_bilgisi, :user_id)
            ON DUPLICATE KEY UPDATE
                mesaj_metni = VALUES(mesaj_metni),
                sayfa_baslik = VALUES(sayfa_baslik),
                evraklar = VALUES(evraklar),
                banka_adimi = VALUES(banka_adimi),
                kase_bilgisi = VALUES(kase_bilgisi),
                guncelleyen_id = VALUES(guncelleyen_id),
                is_active = 1,
                deleted_at = NULL");
        $stmt->execute([
            'firma_id' => $firmaId,
            'mesaj_metni' => $data['mesaj_metni'],
            'sayfa_baslik' => $data['sayfa_baslik'],
            'evraklar' => $data['evraklar'],
            'banka_adimi' => $data['banka_adimi'],
            'kase_bilgisi' => $data['kase_bilgisi'],
            'user_id' => $userId,
        ]);
    }

    public static function telefonNormalize(?string $telefon): string
    {
        $d = preg_replace('/\D/', '', (string) $telefon);
        if ($d === '') {
            return '';
        }
        if (strncmp($d, '90', 2) === 0 && strlen($d) === 12) {
            return $d;
        }
        if ($d[0] === '0' && strlen($d) === 11) {
            return '9' . $d;
        }
        if (strlen($d) === 10 && $d[0] === '5') {
            return '90' . $d;
        }
        return strlen($d) >= 11 && strlen($d) <= 15 ? $d : '';
    }

    public function hazirla(int $firmaId, int $personelId, int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = :firma_id AND personel_id = :personel_id AND is_active = 1 AND deleted_at IS NULL
              AND gecerlilik_tarihi > NOW()
            ORDER BY id DESC LIMIT 1");
        $stmt->execute(['firma_id' => $firmaId, 'personel_id' => $personelId]);
        $kayit = $stmt->fetch(PDO::FETCH_OBJ);
        if ($kayit) {
            return ['kayit' => $kayit, 'token' => (string) Security::decrypt($kayit->token_sifreli)];
        }

        $token = bin2hex(random_bytes(24));
        $ins = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, personel_id, token_hash, token_sifreli, gecerlilik_tarihi, olusturan_id)
            VALUES (:firma_id, :personel_id, :hash, :sifreli, DATE_ADD(NOW(), INTERVAL " . self::GECERLILIK_GUN . " DAY), :user_id)");
        $ins->execute([
            'firma_id' => $firmaId,
            'personel_id' => $personelId,
            'hash' => hash('sha256', $token),
            'sifreli' => Security::encrypt($token),
            'user_id' => $userId > 0 ? $userId : null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return ['kayit' => $stmt->fetch(PDO::FETCH_OBJ), 'token' => $token];
    }

    public function findForFirma(int $id, int $firmaId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function durumGuncelle(int $id, int $firmaId, string $durum, ?string $telefon): bool
    {
        if (!in_array($durum, ['gonderildi', 'atlandi'], true)) {
            return false;
        }
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = :durum,
                telefon = COALESCE(:telefon, telefon),
                gonderim_tarihi = IF(:durum2 = 'gonderildi', NOW(), gonderim_tarihi)
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([
            'durum' => $durum,
            'durum2' => $durum,
            'telefon' => $telefon !== '' ? $telefon : null,
            'id' => $id,
            'firma_id' => $firmaId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function sonDurum(int $firmaId, int $personelId)
    {
        $stmt = $this->db->prepare("SELECT durum, gonderim_tarihi, acilma_sayisi, ilk_acilma_tarihi FROM {$this->table}
            WHERE firma_id = :firma_id AND personel_id = :personel_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY id DESC LIMIT 1");
        $stmt->execute(['firma_id' => $firmaId, 'personel_id' => $personelId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function findByToken(string $token)
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT k.*, p.adi_soyadi
            FROM {$this->table} k
            INNER JOIN personel p ON p.id = k.personel_id AND p.firma_id = k.firma_id
            WHERE k.token_hash = :hash AND k.is_active = 1 AND k.deleted_at IS NULL AND k.gecerlilik_tarihi > NOW()");
        $stmt->execute(['hash' => hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function acilmaKaydet(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET acilma_sayisi = acilma_sayisi + 1,
                ilk_acilma_tarihi = COALESCE(ilk_acilma_tarihi, NOW()),
                son_acilma_tarihi = NOW()
            WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function firmaAdi(int $firmaId): string
    {
        $stmt = $this->db->prepare("SELECT COALESCE(NULLIF(firma_unvan, ''), firma_adi) AS ad FROM firmalar WHERE id = :id");
        $stmt->execute(['id' => $firmaId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    private const MADDE_ELDEN_ISARET = '[elden]';

    private static function sadelestir(string $metin): string
    {
        return strtolower(strtr($metin, [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c',
        ]));
    }

    public static function maddeleriAyir(string $evraklar): array
    {
        $maddeler = [];
        foreach (preg_split('/\n/', $evraklar) as $satir) {
            $satir = trim($satir);
            if ($satir === '') {
                continue;
            }
            if ($satir[0] === '-' && $maddeler) {
                $maddeler[count($maddeler) - 1]['not'][] = trim(ltrim($satir, '- '));
                continue;
            }
            $elden = false;
            if (stripos($satir, self::MADDE_ELDEN_ISARET) === 0) {
                $elden = true;
                $satir = trim(substr($satir, strlen(self::MADDE_ELDEN_ISARET)));
            }
            $sade = self::sadelestir($satir);
            if (preg_match('/fotograf|vesikalik|biyometrik/', $sade)) {
                $elden = true;
            }
            $turu = 'diger';
            foreach (['kimlik' => 'kimlik', 'ehliyet' => 'ehliyet', 'diploma' => 'diploma', 'ikamet' => 'ikametgah', 'adli sicil' => 'adli_sicil_kaydi', 'nufus' => 'nufus_kayit_ornegi', 'saglik' => 'saglik_raporu'] as $anahtar => $deger) {
                if (strpos($sade, $anahtar) !== false) {
                    $turu = $deger;
                    break;
                }
            }
            $maddeler[] = ['ad' => $satir, 'not' => [], 'elden' => $elden, 'turu' => $turu];
        }
        return $maddeler;
    }

    public function yuklemeler(int $karsilamaId): array
    {
        $stmt = $this->db->prepare("SELECT id, madde_index, dosya_boyutu, dosya_tipi, created_at
            FROM personel_karsilama_yuklemeleri
            WHERE karsilama_id = :id AND durum = 'basarili' AND is_active = 1 AND deleted_at IS NULL
            ORDER BY id ASC");
        $stmt->execute(['id' => $karsilamaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function yuklemeSayilari(int $karsilamaId, int $maddeIndex): array
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS toplam, COALESCE(SUM(madde_index = :madde), 0) AS madde
            FROM personel_karsilama_yuklemeleri
            WHERE karsilama_id = :id AND durum = 'basarili' AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute(['id' => $karsilamaId, 'madde' => $maddeIndex]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['toplam' => (int) $r['toplam'], 'madde' => (int) $r['madde']];
    }

    public function sonSaatDeneme(string $ip, int $karsilamaId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM personel_karsilama_yuklemeleri
            WHERE (ip_adresi = :ip OR karsilama_id = :id) AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute(['ip' => $ip, 'id' => $karsilamaId]);
        return (int) $stmt->fetchColumn();
    }

    public function ayniDosyaVarMi(int $karsilamaId, string $sha256): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM personel_karsilama_yuklemeleri
            WHERE karsilama_id = :id AND dosya_sha256 = :h AND durum = 'basarili' AND is_active = 1 LIMIT 1");
        $stmt->execute(['id' => $karsilamaId, 'h' => $sha256]);
        return (bool) $stmt->fetchColumn();
    }

    public function yuklemeKaydet(array $d): int
    {
        $stmt = $this->db->prepare("INSERT INTO personel_karsilama_yuklemeleri
            (karsilama_id, firma_id, personel_id, evrak_id, madde_index, madde_adi, ip_adresi, dosya_sha256, dosya_boyutu, dosya_tipi, durum, red_nedeni)
            VALUES (:karsilama_id, :firma_id, :personel_id, :evrak_id, :madde_index, :madde_adi, :ip, :sha, :boyut, :tip, :durum, :red)");
        $stmt->execute([
            'karsilama_id' => $d['karsilama_id'],
            'firma_id' => $d['firma_id'],
            'personel_id' => $d['personel_id'],
            'evrak_id' => $d['evrak_id'] ?? null,
            'madde_index' => $d['madde_index'] ?? 0,
            'madde_adi' => mb_substr((string) ($d['madde_adi'] ?? ''), 0, 255, 'UTF-8'),
            'ip' => $d['ip'],
            'sha' => $d['sha256'] ?? null,
            'boyut' => $d['boyut'] ?? 0,
            'tip' => $d['tip'] ?? null,
            'durum' => $d['durum'],
            'red' => isset($d['red_nedeni']) ? mb_substr((string) $d['red_nedeni'], 0, 255, 'UTF-8') : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function yuklemeSil(int $yuklemeId, int $karsilamaId): ?int
    {
        $stmt = $this->db->prepare("SELECT evrak_id FROM personel_karsilama_yuklemeleri
            WHERE id = :id AND karsilama_id = :k AND durum = 'basarili' AND is_active = 1");
        $stmt->execute(['id' => $yuklemeId, 'k' => $karsilamaId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        $up = $this->db->prepare("UPDATE personel_karsilama_yuklemeleri
            SET durum = 'silindi', is_active = 0, deleted_at = NOW() WHERE id = :id");
        $up->execute(['id' => $yuklemeId]);
        return (int) $r['evrak_id'];
    }

    public static function mesajOlustur(string $sablon, string $adSoyad, string $firmaAdi, string $link): string
    {
        $metin = strtr($sablon, [
            '{ad_soyad}' => $adSoyad,
            '{firma_adi}' => $firmaAdi,
            '{link}' => $link,
        ]);
        if (strpos($sablon, '{link}') === false) {
            $metin = rtrim($metin) . "\n\n" . $link;
        }
        return $metin;
    }
}
