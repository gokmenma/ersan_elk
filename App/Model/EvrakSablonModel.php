<?php

namespace App\Model;

use PDO;

class EvrakSablonModel extends Model
{
    protected $table = 'evrak_sablonlari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getAllActive(): array
    {
        $stmt = $this->db->prepare("SELECT id, adi, sablon_verisi, updated_at
            FROM {$this->table}
            WHERE firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL
            ORDER BY adi ASC");
        $stmt->execute(['firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAttachments(int $templateId): array
    {
        $stmt = $this->db->prepare("SELECT id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu, sira
            FROM evrak_sablon_ekleri WHERE sablon_id = :sablon_id AND firma_id = :firma_id
            AND is_active = 1 AND deleted_at IS NULL ORDER BY sira ASC, id ASC");
        $stmt->execute(['sablon_id' => $templateId, 'firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAttachment(int $id): object|false
    {
        $stmt = $this->db->prepare("SELECT e.* FROM evrak_sablon_ekleri e
            INNER JOIN evrak_sablonlari s ON s.id = e.sablon_id AND s.firma_id = e.firma_id
            WHERE e.id = :id AND e.firma_id = :firma_id AND e.is_active = 1 AND e.deleted_at IS NULL
              AND s.is_active = 1 AND s.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id, 'firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function replaceAttachments(int $templateId, array $attachments): void
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $stmt = $this->db->prepare("UPDATE evrak_sablon_ekleri SET is_active = 0, deleted_at = NOW()
            WHERE sablon_id = :sablon_id AND firma_id = :firma_id AND deleted_at IS NULL");
        $stmt->execute(['sablon_id' => $templateId, 'firma_id' => $firmaId]);
        $insert = $this->db->prepare("INSERT INTO evrak_sablon_ekleri
            (sablon_id, firma_id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu, sira)
            VALUES (:sablon_id, :firma_id, :dosya_adi, :dosya_yolu, :mime_tipi, :dosya_boyutu, :sira)");
        foreach ($attachments as $index => $attachment) {
            $insert->execute([
                'sablon_id' => $templateId, 'firma_id' => $firmaId,
                'dosya_adi' => $attachment['dosya_adi'], 'dosya_yolu' => $attachment['dosya_yolu'],
                'mime_tipi' => $attachment['mime_tipi'] ?? null, 'dosya_boyutu' => (int) ($attachment['dosya_boyutu'] ?? 0),
                'sira' => $index + 1,
            ]);
        }
    }

    public function getById(int $id): object|false
    {
        $stmt = $this->db->prepare("SELECT id, adi, sablon_verisi, updated_at
            FROM {$this->table}
            WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL
            LIMIT 1");
        $stmt->execute(['id' => $id, 'firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function saveTemplate(int $id, string $adi, array $data, int $userId): int
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($id > 0) {
            if (!$this->getById($id)) {
                throw new \RuntimeException('Şablon bulunamadı.');
            }
            $stmt = $this->db->prepare("UPDATE {$this->table}
                SET adi = :adi, sablon_verisi = :sablon_verisi, guncelleyen_kullanici_id = :kullanici_id, updated_at = NOW()
                WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute(['adi' => $adi, 'sablon_verisi' => $json, 'kullanici_id' => $userId, 'id' => $id, 'firma_id' => $firmaId]);
            return $id;
        }

        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, adi, sablon_verisi, olusturan_kullanici_id, guncelleyen_kullanici_id)
            VALUES (:firma_id, :adi, :sablon_verisi, :kullanici_id, :kullanici_id)");
        $stmt->execute(['firma_id' => $firmaId, 'adi' => $adi, 'sablon_verisi' => $json, 'kullanici_id' => $userId]);
        return (int) $this->db->lastInsertId();
    }

    public function softDeleteTemplate(int $id, int $userId): bool
    {
        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table}
                SET is_active = 0, deleted_at = NOW(), guncelleyen_kullanici_id = :kullanici_id, updated_at = NOW()
                WHERE id = :id AND firma_id = :firma_id AND is_active = 1 AND deleted_at IS NULL");
            $stmt->execute(['kullanici_id' => $userId, 'id' => $id, 'firma_id' => $firmaId]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }
            $attachments = $this->db->prepare("UPDATE evrak_sablon_ekleri SET is_active = 0, deleted_at = NOW()
                WHERE sablon_id = :sablon_id AND firma_id = :firma_id AND deleted_at IS NULL");
            $attachments->execute(['sablon_id' => $id, 'firma_id' => $firmaId]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
