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
            'evraklar' => "Kimlik Fotokopisi\nEhliyet Fotokopisi\nDiploma Fotokopisi\n2 Adet Biyometrik ya da Vesikalık Fotoğraf\n- Son 6 ayda çekilmiş olacak\nİkametgah Belgesi (E-Devlet)\nAdli Sicil Kaydı (E-Devlet)\nNüfus Kayıt Örneği (E-Devlet)\n- Evlilerde eş çocuk bilgileri dahil, bekarlar için tüm aile bireyleri dahil\nSağlık Raporu",
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
