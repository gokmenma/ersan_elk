<?php

namespace App\Model;

use PDO;

class EFaturaNotSablonModel extends Model
{
    protected $table = 'efatura_not_sablonlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firmaya ait tüm aktif alt bilgi / not şablonlarını getirir.
     */
    public function getAll(int $firmId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, firm_id, baslik, icerik, varsayilan_mi, sira, is_active, created_at, updated_at
            FROM {$this->table}
            WHERE firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY varsayilan_mi DESC, sira ASC, baslik ASC
        ");
        $stmt->execute(['firm_id' => $firmId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ID ve Firma ID ile tek bir şablonu getirir.
     */
    public function getById(int $id, int $firmId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, firm_id, baslik, icerik, varsayilan_mi, sira, is_active, created_at, updated_at
            FROM {$this->table}
            WHERE id = :id AND firm_id = :firm_id AND is_active = 1 AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Firmanın varsayılan alt bilgi / not şablonunu getirir.
     */
    public function getDefault(int $firmId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, firm_id, baslik, icerik, varsayilan_mi, sira, is_active, created_at, updated_at
            FROM {$this->table}
            WHERE firm_id = :firm_id AND varsayilan_mi = 1 AND is_active = 1 AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['firm_id' => $firmId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Şablon oluşturur veya günceller.
     */
    public function saveTemplate(int $firmId, ?int $id, string $baslik, string $icerik, int $varsayilanMi = 0, int $userId = 0): array
    {
        $baslik = trim($baslik);
        $icerik = trim($icerik);

        if ($baslik === '') {
            return ['success' => false, 'message' => 'Şablon başlığı boş bırakılamaz.'];
        }

        // Eğer varsayılan olarak işaretlendiyse, firmanın diğer şablonlarının varsayılanını kaldır
        if ($varsayilanMi === 1) {
            $resetStmt = $this->db->prepare("
                UPDATE {$this->table}
                SET varsayilan_mi = 0
                WHERE firm_id = :firm_id AND deleted_at IS NULL
            ");
            $resetStmt->execute(['firm_id' => $firmId]);
        }

        if ($id && $id > 0) {
            // Güncelleme
            $stmt = $this->db->prepare("
                UPDATE {$this->table}
                SET baslik = :baslik,
                    icerik = :icerik,
                    varsayilan_mi = :varsayilan_mi,
                    updated_at = NOW()
                WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
            ");
            $res = $stmt->execute([
                'baslik'        => $baslik,
                'icerik'        => $icerik,
                'varsayilan_mi' => $varsayilanMi,
                'id'            => $id,
                'firm_id'       => $firmId
            ]);

            if ($res) {
                return ['success' => true, 'message' => 'Şablon başarıyla güncellendi.', 'id' => $id];
            }
            return ['success' => false, 'message' => 'Şablon güncellenirken bir hata oluştu.'];
        } else {
            // Yeni Kayıt
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (
                    firm_id, baslik, icerik, varsayilan_mi, is_active, olusturan_user_id, created_at
                ) VALUES (
                    :firm_id, :baslik, :icerik, :varsayilan_mi, 1, :user_id, NOW()
                )
            ");
            $res = $stmt->execute([
                'firm_id'       => $firmId,
                'baslik'        => $baslik,
                'icerik'        => $icerik,
                'varsayilan_mi' => $varsayilanMi,
                'user_id'       => $userId > 0 ? $userId : null
            ]);

            if ($res) {
                $newId = (int)$this->db->lastInsertId();
                return ['success' => true, 'message' => 'Şablon başarıyla oluşturuldu.', 'id' => $newId];
            }
            return ['success' => false, 'message' => 'Şablon kaydedilirken bir hata oluştu.'];
        }
    }

    /**
     * Şablonu soft delete ile siler.
     */
    public function deleteTemplate(int $id, int $firmId, int $userId = 0): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_active = 0, deleted_at = NOW()
            WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
    }

    /**
     * Şablonu varsayılan yapar (diğerlerinin varsayılanını kaldırır).
     */
    public function setDefault(int $id, int $firmId): bool
    {
        $resetStmt = $this->db->prepare("
            UPDATE {$this->table}
            SET varsayilan_mi = 0
            WHERE firm_id = :firm_id AND deleted_at IS NULL
        ");
        $resetStmt->execute(['firm_id' => $firmId]);

        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET varsayilan_mi = 1, updated_at = NOW()
            WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL
        ");
        return $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
    }
}
