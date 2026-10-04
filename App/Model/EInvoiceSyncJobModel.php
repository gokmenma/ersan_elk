<?php
namespace App\Model;

use PDO;

class EInvoiceSyncJobModel extends Model
{
    public function __construct(?PDO $db = null)
    {
        if ($db) $this->db = $db;
        else parent::__construct('efatura_sync_jobs');
    }

    public function lock(string $name): bool
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(:name, 0)');
        $stmt->execute(['name' => 'efatura-sync-' . $name]);
        return (int)$stmt->fetchColumn() === 1;
    }

    public function unlock(string $name): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(:name)');
        $stmt->execute(['name' => 'efatura-sync-' . $name]);
    }

    public function create(int $firmId, int $userId, array $state): array
    {
        $name = 'firm-' . $firmId;
        if (!$this->lock($name)) throw new \InvalidArgumentException('Başka bir aktarım başlatılıyor; tekrar deneyin.');
        try {
            $stmt = $this->db->prepare("SELECT * FROM efatura_sync_jobs WHERE firm_id = :firm AND status IN ('queued','running','paused') AND deleted_at IS NULL AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $stmt->execute(['firm' => $firmId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                if ((int)$existing['user_id'] !== $userId) throw new \InvalidArgumentException('Bu firma için başka bir kullanıcının açık aktarımı var.');
                $decoded = $this->decode($existing);
                if (($decoded['state']['list_type'] ?? 'all') !== ($state['list_type'] ?? 'all')) throw new \InvalidArgumentException('Bu firma için farklı türde bir aktarım açık. Önce mevcut aktarımı tamamlayın veya duraklayan aktarımı kapatın.');
                return $decoded;
            }
            $id = bin2hex(random_bytes(16));
            $stmt = $this->db->prepare('INSERT INTO efatura_sync_jobs (id,firm_id,user_id,state_json,status) VALUES (:id,:firm,:user,:state,:status)');
            $stmt->execute(['id' => $id, 'firm' => $firmId, 'user' => $userId, 'state' => json_encode($state, JSON_THROW_ON_ERROR), 'status' => 'queued']);
            return $this->findJob($id);
        } finally { $this->unlock($name); }
    }

    public function findJob(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM efatura_sync_jobs WHERE id = :id AND deleted_at IS NULL AND is_active = 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decode($row) : null;
    }

    public function latest(int $firmId, int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM efatura_sync_jobs WHERE firm_id = :firm AND user_id = :user AND deleted_at IS NULL AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute(['firm' => $firmId, 'user' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decode($row) : null;
    }

    public function latestForList(int $firmId, int $userId, string $listType): ?array
    {
        if (!in_array($listType, ['taslak', 'giden'], true)) throw new \InvalidArgumentException('Geçersiz aktarım türü.');
        $stmt = $this->db->prepare("SELECT * FROM efatura_sync_jobs WHERE firm_id = :firm AND user_id = :user AND JSON_UNQUOTE(JSON_EXTRACT(state_json, '$.list_type')) = :list_type AND deleted_at IS NULL AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1");
        $stmt->execute(['firm' => $firmId, 'user' => $userId, 'list_type' => $listType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decode($row) : null;
    }

    public function saveJob(array $job): void
    {
        $stmt = $this->db->prepare('UPDATE efatura_sync_jobs SET state_json = :state, status = :status, updated_at = NOW(6) WHERE id = :id AND firm_id = :firm AND deleted_at IS NULL AND is_active = 1');
        $stmt->execute(['state' => json_encode($job['state'], JSON_THROW_ON_ERROR), 'status' => $job['status'], 'id' => $job['id'], 'firm' => $job['firm_id']]);
    }

    private function decode(array $row): array
    {
        $row['state'] = json_decode($row['state_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['state_json']);
        return $row;
    }
}
