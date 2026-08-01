<?php

namespace App\Model;

use App\Helper\Security;
use App\Model\Model;
use PDO;

/**
 * SgkSorguOnbellekModel
 * SGK Vizite web servisi sorgu cevaplarını kısa süreli saklar ve
 * dakikada 1 sorgu sınırı olan metotlar için son sorgu zamanını takip eder.
 */
class SgkSorguOnbellekModel extends Model
{
    protected $table = 'sgk_sorgu_onbellek';

    const VARSAYILAN_OMUR_SANIYE = 120;
    const SORGU_ARALIK_SANIYE = 60;

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function hesapAnahtari(string $kullaniciAdi, string $isyeriKodu): string
    {
        return hash('sha256', $kullaniciAdi . '|' . $isyeriKodu);
    }

    public function sorguAnahtari(string $metot, array $parametreler): string
    {
        return hash('sha256', $metot . '|' . json_encode($parametreler, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Önbellek kaydını döndürür.
     * Tazelik karşılaştırması, PHP ile veritabanı saat dilimleri farklı olabileceği için SQL tarafında yapılır.
     * @return array{cevap:?array, taze:bool, sorgudan_bu_yana:?int}|null
     */
    public function kayitGetir(string $hesapAnahtari, string $sorguAnahtari): ?array
    {
        $sql = "SELECT cevap,
                       (gecerlilik_bitis IS NOT NULL AND gecerlilik_bitis > NOW()) AS taze,
                       TIMESTAMPDIFF(SECOND, son_sorgu_zamani, NOW()) AS sorgudan_bu_yana
                FROM {$this->table}
                WHERE hesap_anahtari = ? AND sorgu_anahtari = ?
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hesapAnahtari, $sorguAnahtari]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'cevap' => $this->cevabiCoz($row['cevap']),
            'taze' => (int) $row['taze'] === 1,
            'sorgudan_bu_yana' => $row['sorgudan_bu_yana'] !== null ? (int) $row['sorgudan_bu_yana'] : null,
        ];
    }

    /**
     * Aynı metot için SGK'ya son istekten bu yana geçen saniyeyi döndürür.
     * Sorgu parametreleri farklı olsa bile sınır metot bazında uygulandığı için tüm kayıtlara bakılır.
     */
    public function sonSorgudanBuYana(string $hesapAnahtari, string $metot): ?int
    {
        $sql = "SELECT TIMESTAMPDIFF(SECOND, MAX(son_sorgu_zamani), NOW())
                FROM {$this->table}
                WHERE hesap_anahtari = ? AND metot = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hesapAnahtari, $metot]);
        $deger = $stmt->fetchColumn();

        return $deger === null || $deger === false ? null : (int) $deger;
    }

    public function sorguLimitiAktifMi(string $hesapAnahtari, string $metot, int $aralikSaniye = self::SORGU_ARALIK_SANIYE): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}
                WHERE hesap_anahtari = ? AND metot = ?
                AND son_sorgu_zamani IS NOT NULL
                AND son_sorgu_zamani > DATE_SUB(NOW(), INTERVAL ? SECOND)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hesapAnahtari, $metot, $aralikSaniye]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * SGK'ya istek gönderilmeden hemen önce çağrılır; başarısız denemeler de sınıra dahildir.
     */
    public function sorguZamaniniIsaretle(string $hesapAnahtari, string $sorguAnahtari, string $metot, ?int $firmaId = null): bool
    {
        $sql = "INSERT INTO {$this->table} (hesap_anahtari, sorgu_anahtari, metot, firma_id, son_sorgu_zamani)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE metot = VALUES(metot), firma_id = VALUES(firma_id), son_sorgu_zamani = NOW()";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$hesapAnahtari, $sorguAnahtari, $metot, $firmaId]);
    }

    public function cevapKaydet(
        string $hesapAnahtari,
        string $sorguAnahtari,
        string $metot,
        array $cevap,
        int $omurSaniye = self::VARSAYILAN_OMUR_SANIYE,
        ?int $firmaId = null
    ): bool {
        $sql = "INSERT INTO {$this->table} (hesap_anahtari, sorgu_anahtari, metot, firma_id, cevap, son_sorgu_zamani, gecerlilik_bitis)
                VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE
                    metot = VALUES(metot),
                    firma_id = VALUES(firma_id),
                    cevap = VALUES(cevap),
                    son_sorgu_zamani = NOW(),
                    gecerlilik_bitis = VALUES(gecerlilik_bitis)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $hesapAnahtari,
            $sorguAnahtari,
            $metot,
            $firmaId,
            Security::encrypt(json_encode($cevap, JSON_UNESCAPED_UNICODE)),
            $omurSaniye,
        ]);
    }

    /**
     * Veriyi değiştiren bir işlemden sonra cevabı bayatlatır.
     * Kayıt silinmez; sorgu sınırına takılan istekler bayat veriye düşebilsin diye korunur.
     */
    public function bayatlat(string $hesapAnahtari, string $metot): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET gecerlilik_bitis = NOW() WHERE hesap_anahtari = ? AND metot = ?");
        return $stmt->execute([$hesapAnahtari, $metot]);
    }

    public function suresiDolanlariTemizle(int $gunSayisi = 1): int
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE son_sorgu_zamani < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$gunSayisi]);
        return $stmt->rowCount();
    }

    private function cevabiCoz($ham): ?array
    {
        if (empty($ham)) {
            return null;
        }

        $json = Security::decrypt($ham);
        if (!is_string($json)) {
            return null;
        }

        $cozulen = json_decode($json, true);
        return is_array($cozulen) ? $cozulen : null;
    }
}
