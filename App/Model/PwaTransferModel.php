<?php
namespace App\Model;

use PDO;
use RuntimeException;

/** Transactional receipts shared by the record and attachment endpoints. */
class PwaTransferModel extends Model
{
    private int $firma;
    private int $personel;
    private ?string $operation = null;

    public function __construct(int $firma, int $personel)
    {
        parent::__construct('pwa_transfer_receipts');
        $this->firma = $firma;
        $this->personel = $personel;
    }

    public static function key(string $key): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $key)) {
            throw new RuntimeException('Geçersiz işlem anahtarı.');
        }
        return $key;
    }

    public function begin(string $operation, string $action): ?array
    {
        $this->operation = self::key($operation);
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('INSERT INTO pwa_transfer_receipts (firma_id, personel_id, operation_key, action_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE operation_key = VALUES(operation_key)');
        $stmt->execute([$this->firma, $this->personel, $operation, $action]);
        $stmt = $this->db->prepare('SELECT action_name, response_json FROM pwa_transfer_receipts WHERE firma_id = ? AND personel_id = ? AND operation_key = ? FOR UPDATE');
        $stmt->execute([$this->firma, $this->personel, $operation]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row['action_name'] !== $action) {
            throw new RuntimeException('İşlem anahtarı başka bir işlemde kullanılmış.');
        }
        return $row['response_json'] ? json_decode($row['response_json'], true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function finish(array $response): void
    {
        if (!$this->operation || !$this->db->inTransaction()) return;
        if ($response['success']) {
            $stmt = $this->db->prepare('UPDATE pwa_transfer_receipts SET response_json = ?, completed_at = NOW() WHERE firma_id = ? AND personel_id = ? AND operation_key = ?');
            $stmt->execute([json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $this->firma, $this->personel, $this->operation]);
            $this->db->commit();
        } else {
            $this->db->rollBack();
        }
        $this->operation = null;
    }

    public function receipt(string $operation, string $action): ?array
    {
        self::key($operation);
        $stmt = $this->db->prepare('SELECT response_json FROM pwa_transfer_receipts WHERE firma_id = ? AND personel_id = ? AND operation_key = ? AND action_name = ?');
        $stmt->execute([$this->firma, $this->personel, $operation, $action]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function lockRecord(string $kind, int $id): void
    {
        $table = $kind === 'ihbar' ? 'ihbarlar' : 'kacak_kontrol';
        $stmt = $this->db->prepare("SELECT id FROM {$table} WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL FOR UPDATE");
        $stmt->execute([$id, $this->firma]);
        if (!$stmt->fetchColumn()) throw new RuntimeException('Kayıt bulunamadı.');
    }
}
