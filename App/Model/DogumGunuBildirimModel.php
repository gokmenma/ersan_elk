<?php

namespace App\Model;

use App\Core\Db;
use App\Service\PushNotificationService;
use PDO;

class DogumGunuBildirimModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = (new Db())->db;
    }

    public function getBugunDogumGunuOlanlar(int $firmaId): array
    {
        $bugun = new \DateTimeImmutable('today');
        $gunler = [$bugun->format('m-d')];
        if ($bugun->format('m-d') === '02-28' && $bugun->format('L') === '0') {
            $gunler[] = '02-29';
        }
        $placeholders = [];
        $params = ['firma_id' => $firmaId, 'yil' => (int) $bugun->format('Y')];
        foreach ($gunler as $i => $gun) {
            $placeholders[] = ":gun$i";
            $params["gun$i"] = $gun;
        }

        $sql = "SELECT p.id, p.adi_soyadi
                FROM personel p
                WHERE p.firma_id = :firma_id
                  AND p.silinme_tarihi IS NULL
                  AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')
                  AND p.dogum_tarihi IS NOT NULL AND p.dogum_tarihi != '0000-00-00'
                  AND DATE_FORMAT(p.dogum_tarihi, '%m-%d') IN (" . implode(',', $placeholders) . ")
                  AND NOT EXISTS (
                      SELECT 1 FROM dogum_gunu_bildirim_loglari l
                      WHERE l.personel_id = p.id AND l.bildirim_yili = :yil
                  )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getTumDogumGunleri(int $firmaId): array
    {
        $sql = "SELECT p.adi_soyadi, p.departman, p.gorev, p.dogum_tarihi
                FROM personel p
                WHERE p.firma_id = :firma_id
                  AND p.silinme_tarihi IS NULL
                  AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00')
                  AND p.dogum_tarihi IS NOT NULL AND p.dogum_tarihi != '0000-00-00'
                ORDER BY p.adi_soyadi";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['firma_id' => $firmaId]);

        $liste = [];
        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $kayit) {
            $parcalar = explode('-', substr((string) $kayit->dogum_tarihi, 0, 10));
            if (count($parcalar) !== 3) {
                continue;
            }
            $liste[] = [
                'ad' => (string) $kayit->adi_soyadi,
                'bilgi' => (string) ($kayit->departman ?: $kayit->gorev),
                'yil' => (int) $parcalar[0],
                'ay' => (int) $parcalar[1],
                'gun' => (int) $parcalar[2],
            ];
        }

        return $liste;
    }

    public function sendDogumGunuBildirimleri(int $firmaId): int
    {
        $gonderilen = 0;
        $push = new PushNotificationService();
        $yil = (int) date('Y');

        foreach ($this->getBugunDogumGunuOlanlar($firmaId) as $personel) {
            try {
                $ad = trim((string) strtok((string) $personel->adi_soyadi, ' '));
                $payload = [
                    'title' => '🎂 Doğum Günün Kutlu Olsun!',
                    'body' => ($ad !== '' ? "Sevgili $ad, " : '') . 'yeni yaşında sağlık, mutluluk ve başarı dileriz. Ersan Elektrik Ailesi',
                    'url' => '?page=home'
                ];
                $basarili = $push->sendToPersonel($personel->id, $payload, true);
                $this->log($firmaId, (int) $personel->id, $yil, $payload, $basarili);
                if ($basarili) {
                    $gonderilen++;
                }
            } catch (\Throwable $e) {
                error_log('Doğum günü bildirimi hatası: ' . $e->getMessage());
            }
        }

        return $gonderilen;
    }

    private function log(int $firmaId, int $personelId, int $yil, array $payload, bool $basarili): void
    {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO dogum_gunu_bildirim_loglari
             (firma_id, personel_id, bildirim_yili, baslik, mesaj, gonderim_durumu, gonderim_tarihi)
             VALUES (:firma_id, :personel_id, :yil, :baslik, :mesaj, :durum, NOW())"
        );
        $stmt->execute([
            'firma_id' => $firmaId,
            'personel_id' => $personelId,
            'yil' => $yil,
            'baslik' => $payload['title'],
            'mesaj' => $payload['body'],
            'durum' => $basarili ? 'gonderildi' : 'basarisiz',
        ]);
    }
}
