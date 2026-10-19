<?php

namespace App\Model;

use PDO;

class KepAdresDefteriModel extends Model
{
    protected $table = 'kep_adres_defteri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function listActive(int $firmId): array
    {
        $stmt = $this->db->prepare(
            "SELECT kep_adresi, son_dogrulama_tarihi, son_kullanim_tarihi
             FROM {$this->table}
             WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
             ORDER BY COALESCE(son_kullanim_tarihi, son_dogrulama_tarihi) DESC, kep_adresi ASC"
        );
        $stmt->execute(['firm_id' => $firmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveVerified(int $firmId, string $address): bool
    {
        $address = mb_strtolower(trim($address), 'UTF-8');
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table}
                (firm_id, kep_adresi, son_dogrulama_tarihi, is_active, created_at, updated_at)
             VALUES
                (:firm_id, :kep_adresi, NOW(), 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                son_dogrulama_tarihi = NOW(), is_active = 1, deleted_at = NULL, updated_at = NOW()"
        );
        return $stmt->execute(['firm_id' => $firmId, 'kep_adresi' => $address]);
    }

    public function markUsed(int $firmId, array $addresses): void
    {
        $addresses = array_values(array_unique(array_filter(array_map(
            static fn($address) => mb_strtolower(trim((string) $address), 'UTF-8'),
            $addresses
        ))));
        if ($addresses === []) return;

        $placeholders = implode(',', array_fill(0, count($addresses), '?'));
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET son_kullanim_tarihi = NOW(), updated_at = NOW()
             WHERE firm_id = ? AND kep_adresi IN ({$placeholders}) AND is_active = 1"
        );
        $stmt->execute(array_merge([$firmId], $addresses));
    }
}
