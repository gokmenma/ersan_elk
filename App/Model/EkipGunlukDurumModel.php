<?php

namespace App\Model;

use PDO;

class EkipGunlukDurumModel extends Model
{
    protected $table = 'ekip_gunluk_durum';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function kaydet(int $ekipId, string $tarih, int $kalanIs, ?int $girenId): bool
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, ekip_id, tarih, kalan_is, giren_id) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE kalan_is = VALUES(kalan_is), giren_id = VALUES(giren_id)");
        return $stmt->execute([$this->firmaId(), $ekipId, $tarih, $kalanIs, $girenId]);
    }

    public function gunlukDurumlar(string $baslangic, string $bitis): array
    {
        $stmt = $this->db->prepare("SELECT ekip_id, tarih, kalan_is FROM {$this->table}
            WHERE firma_id = ? AND tarih BETWEEN ? AND ?
            ORDER BY ekip_id ASC, tarih ASC");
        $stmt->execute([$this->firmaId(), $baslangic, $bitis]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_id']][$satir['tarih']] = (int) $satir['kalan_is'];
        }
        return $harita;
    }

    /**
     * Her ekibin en güncel kalan iş girişi: [ekip_id => ['tarih' => ..., 'kalan_is' => ...]]
     */
    public function sonDurumlar(): array
    {
        $stmt = $this->db->prepare("SELECT g.ekip_id, g.tarih, g.kalan_is
            FROM {$this->table} g
            INNER JOIN (
                SELECT ekip_id, MAX(tarih) AS son_tarih
                FROM {$this->table} WHERE firma_id = ? GROUP BY ekip_id
            ) s ON s.ekip_id = g.ekip_id AND s.son_tarih = g.tarih
            WHERE g.firma_id = ?");
        $stmt->execute([$this->firmaId(), $this->firmaId()]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_id']] = [
                'tarih' => $satir['tarih'],
                'kalan_is' => (int) $satir['kalan_is'],
            ];
        }
        return $harita;
    }
}
