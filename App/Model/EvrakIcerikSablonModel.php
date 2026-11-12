<?php

namespace App\Model;

use PDO;

class EvrakIcerikSablonModel extends Model
{
    protected $table = 'evrak_icerik_sablonlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAllActive(): array
    {
        $stmt = $this->db->prepare("SELECT id, adi, icerik, updated_at
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY adi ASC");
        $stmt->execute(['firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findByName(string $adi): object|false
    {
        $stmt = $this->db->prepare("SELECT id, adi FROM {$this->table}
            WHERE firma_id = :firma_id AND adi = :adi AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['firma_id' => (int) ($_SESSION['firma_id'] ?? 0), 'adi' => $adi]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function saveByName(string $adi, string $icerik, int $userId): array
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $mevcut = $this->findByName($adi);
        if ($mevcut) {
            $stmt = $this->db->prepare("UPDATE {$this->table}
                SET icerik = :icerik, guncelleyen_kullanici_id = :kullanici_id, updated_at = NOW()
                WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute(['icerik' => $icerik, 'kullanici_id' => $userId, 'id' => (int) $mevcut->id, 'firma_id' => $firmaId]);
            return ['id' => (int) $mevcut->id, 'guncellendi' => true];
        }
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, adi, icerik, olusturan_kullanici_id, guncelleyen_kullanici_id)
            VALUES (:firma_id, :adi, :icerik, :kullanici_id, :kullanici_id)");
        $stmt->execute(['firma_id' => $firmaId, 'adi' => $adi, 'icerik' => $icerik, 'kullanici_id' => $userId]);
        return ['id' => (int) $this->db->lastInsertId(), 'guncellendi' => false];
    }

    public function softDeleteTemplate(int $id, int $userId): ?string
    {
        $stmt = $this->db->prepare("SELECT adi FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $stmt->execute(['id' => $id, 'firma_id' => $firmaId]);
        $adi = $stmt->fetchColumn();
        if ($adi === false) {
            return null;
        }
        $upd = $this->db->prepare("UPDATE {$this->table}
            SET is_active = 0, deleted_at = NOW(), guncelleyen_kullanici_id = :kullanici_id, updated_at = NOW()
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
        $upd->execute(['kullanici_id' => $userId, 'id' => $id, 'firma_id' => $firmaId]);
        return $upd->rowCount() === 1 ? (string) $adi : null;
    }
}
