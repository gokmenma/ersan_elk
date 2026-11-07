<?php

namespace App\Model;

use PDO;

class SiteAyarModel extends SiteModel
{
    public const SEMA = [
        'site_adi' => ['tip' => 'metin', 'max' => 100],
        'seo_baslik' => ['tip' => 'metin', 'max' => 70],
        'seo_aciklama' => ['tip' => 'metin', 'max' => 160],
        'telefon' => ['tip' => 'telefon', 'max' => 30],
        'eposta' => ['tip' => 'eposta', 'max' => 190],
        'adres' => ['tip' => 'metin', 'max' => 300],
        'calisma_saatleri' => ['tip' => 'metin', 'max' => 150],
        'hakkimizda_baslik' => ['tip' => 'metin', 'max' => 150],
        'hakkimizda_metin' => ['tip' => 'metin', 'max' => 2000],
        'hakkimizda_maddeler' => ['tip' => 'satirlar', 'max' => 150, 'adet' => 8],
        'misyon' => ['tip' => 'metin', 'max' => 500],
        'vizyon' => ['tip' => 'metin', 'max' => 500],
        'surec_adimlari' => ['tip' => 'satirlar', 'max' => 220, 'adet' => 6],
        'slayt1_kicker' => ['tip' => 'metin', 'max' => 60],
        'slayt1_baslik' => ['tip' => 'metin', 'max' => 120],
        'slayt1_metin' => ['tip' => 'metin', 'max' => 300],
        'slayt2_kicker' => ['tip' => 'metin', 'max' => 60],
        'slayt2_baslik' => ['tip' => 'metin', 'max' => 120],
        'slayt2_metin' => ['tip' => 'metin', 'max' => 300],
        'slayt3_kicker' => ['tip' => 'metin', 'max' => 60],
        'slayt3_baslik' => ['tip' => 'metin', 'max' => 120],
        'slayt3_metin' => ['tip' => 'metin', 'max' => 300],
        'sosyal_facebook' => ['tip' => 'url', 'max' => 255],
        'sosyal_instagram' => ['tip' => 'url', 'max' => 255],
        'sosyal_linkedin' => ['tip' => 'url', 'max' => 255],
        'sosyal_youtube' => ['tip' => 'url', 'max' => 255],
        'sosyal_whatsapp' => ['tip' => 'telefon', 'max' => 30],
    ];

    public function __construct()
    {
        parent::__construct('site_ayarlar');
    }

    public static function temizle(string $anahtar, string $deger): array
    {
        $kural = self::SEMA[$anahtar] ?? null;
        if ($kural === null) {
            return [false, '', 'Tanımsız ayar.'];
        }

        $deger = trim(str_replace("\r", '', $deger));
        if ($deger === '') {
            return [true, '', null];
        }

        if ($kural['tip'] === 'satirlar') {
            $satirlar = [];
            foreach (explode("\n", $deger) as $satir) {
                $satir = trim($satir);
                if ($satir !== '') {
                    $satirlar[] = mb_substr($satir, 0, $kural['max'], 'UTF-8');
                }
                if (count($satirlar) >= $kural['adet']) {
                    break;
                }
            }
            return [true, implode("\n", $satirlar), null];
        }

        $deger = mb_substr($deger, 0, $kural['max'], 'UTF-8');

        if ($kural['tip'] === 'eposta' && !filter_var($deger, FILTER_VALIDATE_EMAIL)) {
            return [false, '', 'Geçerli bir e-posta adresi yazın.'];
        }
        if ($kural['tip'] === 'url') {
            $parcalar = parse_url($deger);
            if (!filter_var($deger, FILTER_VALIDATE_URL) || strtolower((string) ($parcalar['scheme'] ?? '')) !== 'https') {
                return [false, '', 'Bağlantı https:// ile başlayan geçerli bir adres olmalıdır.'];
            }
        }
        if ($kural['tip'] === 'telefon') {
            $deger = (string) preg_replace('/[^0-9+\s()-]/', '', $deger);
        }

        return [true, $deger, null];
    }

    public function tumu(): array
    {
        $stmt = $this->db->prepare("SELECT anahtar, deger FROM {$this->table}");
        $stmt->execute();
        $sonuc = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $satir) {
            if (isset(self::SEMA[$satir->anahtar])) {
                $sonuc[$satir->anahtar] = (string) $satir->deger;
            }
        }
        return $sonuc;
    }

    public function kaydet(array $degerler, int $kullaniciId): void
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (anahtar, deger, guncelleyen_id)
            VALUES (:anahtar, :deger, :kullanici)
            ON DUPLICATE KEY UPDATE deger = VALUES(deger), guncelleyen_id = VALUES(guncelleyen_id)");
        $this->db->beginTransaction();
        try {
            foreach ($degerler as $anahtar => $deger) {
                if (!isset(self::SEMA[$anahtar])) {
                    continue;
                }
                $stmt->execute([
                    'anahtar' => $anahtar,
                    'deger' => $deger !== '' ? $deger : null,
                    'kullanici' => $kullaniciId > 0 ? $kullaniciId : null,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
