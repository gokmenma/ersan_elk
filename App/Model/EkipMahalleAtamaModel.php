<?php

namespace App\Model;

use PDO;

class EkipMahalleAtamaModel extends Model
{
    protected $table = 'ekip_mahalle_atama';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function gecmis(?int $ekipId = null, ?string $ilce = null): array
    {
        $sql = "SELECT a.id, a.ekip_id, a.mahalle_id, a.baslangic, a.bitis, a.durum,
                    m.ad AS mahalle_adi, m.ilce, m.kod_araligi, t.tur_adi AS ekip_adi
                FROM {$this->table} a
                INNER JOIN mahalle m ON m.id = a.mahalle_id
                LEFT JOIN tanimlamalar t ON t.id = a.ekip_id
                WHERE a.firma_id = ?";
        $parametreler = [$this->firmaId()];

        if ($ekipId) {
            $sql .= " AND a.ekip_id = ?";
            $parametreler[] = $ekipId;
        }
        if ($ilce) {
            $sql .= " AND m.ilce = ?";
            $parametreler[] = $ilce;
        }

        $sql .= " ORDER BY a.baslangic DESC, a.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aktifAtamalar(): array
    {
        $stmt = $this->db->prepare("SELECT a.id, a.ekip_id, a.mahalle_id, a.baslangic,
                    m.ad AS mahalle_adi, m.ilce
                FROM {$this->table} a
                INNER JOIN mahalle m ON m.id = a.mahalle_id
                WHERE a.firma_id = ? AND a.durum = 'aktif'");
        $stmt->execute([$this->firmaId()]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_id']] = $satir;
        }
        return $harita;
    }

    /**
     * Ekip başına son N atamanın ilçesi (eskiden yeniye). M3 döngüsü buradan okunur.
     */
    public function sonIlceler(int $adet = 3): array
    {
        $stmt = $this->db->prepare("SELECT a.ekip_id, m.ilce, m.ad AS mahalle_adi, a.baslangic
                FROM {$this->table} a
                INNER JOIN mahalle m ON m.id = a.mahalle_id
                WHERE a.firma_id = ?
                ORDER BY a.ekip_id ASC, a.baslangic ASC, a.id ASC");
        $stmt->execute([$this->firmaId()]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['ekip_id']][] = $satir;
        }
        foreach ($harita as $ekipId => $satirlar) {
            $harita[$ekipId] = array_slice($satirlar, -$adet);
        }
        return $harita;
    }

    public function sonZiyaretHaritasi(): array
    {
        $stmt = $this->db->prepare("SELECT a.mahalle_id, a.ekip_id, a.durum,
                    COALESCE(a.bitis, CURDATE()) AS ziyaret_tarihi, t.tur_adi AS ekip_adi
                FROM {$this->table} a
                LEFT JOIN tanimlamalar t ON t.id = a.ekip_id
                WHERE a.firma_id = ?
                ORDER BY COALESCE(a.bitis, CURDATE()) ASC, a.id ASC");
        $stmt->execute([$this->firmaId()]);

        $harita = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $satir) {
            $harita[(int) $satir['mahalle_id']] = $satir;
        }
        return $harita;
    }

    public function aktifAtama(int $ekipId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = ? AND ekip_id = ? AND durum = 'aktif' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$this->firmaId(), $ekipId]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);
        return $satir ?: null;
    }

    public function mahalleAktifMi(int $mahalleId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}
            WHERE firma_id = ? AND mahalle_id = ? AND durum = 'aktif'");
        $stmt->execute([$this->firmaId(), $mahalleId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * M10: yeni atama açılırken ekibin açık ataması aynı işlemde kapatılır.
     */
    public function ata(int $ekipId, int $mahalleId, string $baslangic, ?int $atayanId): int
    {
        $this->db->beginTransaction();
        try {
            $onceki = $this->aktifAtama($ekipId);
            if ($onceki) {
                $bitis = date('Y-m-d', strtotime($baslangic . ' -1 day'));
                if ($bitis < $onceki['baslangic']) {
                    $bitis = $onceki['baslangic'];
                }
                $this->kapat((int) $onceki['id'], $bitis);
            }

            $stmt = $this->db->prepare("INSERT INTO {$this->table}
                (firma_id, ekip_id, mahalle_id, baslangic, durum, atayan_id)
                VALUES (?, ?, ?, ?, 'aktif', ?)");
            $stmt->execute([$this->firmaId(), $ekipId, $mahalleId, $baslangic, $atayanId]);
            $yeniId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $yeniId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function kapat(int $atamaId, string $bitis): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET bitis = ?, durum = 'bitti' WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$bitis, $atamaId, $this->firmaId()]);
    }

    public function sil(int $atamaId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ? AND firma_id = ?");
        return $stmt->execute([$atamaId, $this->firmaId()]);
    }

    public function bul(int $atamaId): ?array
    {
        $stmt = $this->db->prepare("SELECT a.*, m.ad AS mahalle_adi, m.ilce
            FROM {$this->table} a
            INNER JOIN mahalle m ON m.id = a.mahalle_id
            WHERE a.id = ? AND a.firma_id = ?");
        $stmt->execute([$atamaId, $this->firmaId()]);
        $satir = $stmt->fetch(PDO::FETCH_ASSOC);
        return $satir ?: null;
    }
}
