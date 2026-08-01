<?php

namespace App\Model;

use App\Helper\Security;
use App\Model\Model;
use PDO;

/**
 * SgkTokenModel
 * SGK Vizite web servisinden alınan wsToken (GUID) kayıtlarını yönetir.
 */
class SgkTokenModel extends Model
{
    protected $table = 'sgk_ws_tokens';

    const TOKEN_OMUR_SANIYE = 1620;
    const KILIT_BEKLEME_SANIYE = 10;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function hesapAnahtari(string $kullaniciAdi, string $isyeriKodu): string
    {
        return hash('sha256', $kullaniciAdi . '|' . $isyeriKodu);
    }

    public function sifreOzeti(string $isyeriSifresi): string
    {
        return hash('sha256', $isyeriSifresi);
    }

    /**
     * Süresi dolmamış ve aynı şifre ile alınmış token'ı döndürür.
     * Kalan süre, PHP ile veritabanı saat dilimleri farklı olabileceği için SQL tarafında hesaplanır.
     * @return array{token:string, sunucu_adresi:?string, kalan_saniye:int}|null
     */
    public function gecerliTokenGetir(string $kullaniciAdi, string $isyeriKodu, string $isyeriSifresi): ?array
    {
        $sql = "SELECT ws_token, sunucu_adresi, TIMESTAMPDIFF(SECOND, NOW(), gecerlilik_bitis) AS kalan_saniye
                FROM {$this->table}
                WHERE hesap_anahtari = ? AND sifre_ozeti = ? AND gecerlilik_bitis > NOW()
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->hesapAnahtari($kullaniciAdi, $isyeriKodu),
            $this->sifreOzeti($isyeriSifresi),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $token = Security::decrypt($row['ws_token']);
        if (!is_string($token) || $token === '') {
            return null;
        }

        return [
            'token' => $token,
            'sunucu_adresi' => $row['sunucu_adresi'],
            'kalan_saniye' => max(0, (int) $row['kalan_saniye']),
        ];
    }

    public function tokenKaydet(
        string $kullaniciAdi,
        string $isyeriKodu,
        string $isyeriSifresi,
        string $token,
        int $omurSaniye = self::TOKEN_OMUR_SANIYE,
        ?string $sunucuAdresi = null,
        ?int $firmaId = null
    ): bool {
        $sql = "INSERT INTO {$this->table}
                    (hesap_anahtari, firma_id, kullanici_adi, isyeri_kodu, sifre_ozeti, ws_token, sunucu_adresi, gecerlilik_bitis)
                VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE
                    firma_id = VALUES(firma_id),
                    kullanici_adi = VALUES(kullanici_adi),
                    isyeri_kodu = VALUES(isyeri_kodu),
                    sifre_ozeti = VALUES(sifre_ozeti),
                    ws_token = VALUES(ws_token),
                    sunucu_adresi = VALUES(sunucu_adresi),
                    gecerlilik_bitis = VALUES(gecerlilik_bitis)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $this->hesapAnahtari($kullaniciAdi, $isyeriKodu),
            $firmaId,
            $kullaniciAdi,
            $isyeriKodu,
            $this->sifreOzeti($isyeriSifresi),
            Security::encrypt($token),
            $sunucuAdresi,
            $omurSaniye,
        ]);
    }

    public function tokenSil(string $kullaniciAdi, string $isyeriKodu): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE hesap_anahtari = ?");
        return $stmt->execute([$this->hesapAnahtari($kullaniciAdi, $isyeriKodu)]);
    }

    public function suresiDolanlariTemizle(int $gunSayisi = 7): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE gecerlilik_bitis < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$gunSayisi]);
        return $stmt->rowCount();
    }

    /**
     * Aynı anda birden fazla isteğin wsLogin çağırmasını engelleyen MySQL seviyesinde kilit alır.
     */
    public function kilitAl(string $kullaniciAdi, string $isyeriKodu, int $beklemeSaniye = self::KILIT_BEKLEME_SANIYE): bool
    {
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, ?)");
        $stmt->execute([$this->kilitAdi($kullaniciAdi, $isyeriKodu), $beklemeSaniye]);
        return (int) $stmt->fetchColumn() === 1;
    }

    public function kilidiBirak(string $kullaniciAdi, string $isyeriKodu): void
    {
        $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
        $stmt->execute([$this->kilitAdi($kullaniciAdi, $isyeriKodu)]);
    }

    private function kilitAdi(string $kullaniciAdi, string $isyeriKodu): string
    {
        return 'sgk_ws_login_' . substr($this->hesapAnahtari($kullaniciAdi, $isyeriKodu), 0, 32);
    }
}
