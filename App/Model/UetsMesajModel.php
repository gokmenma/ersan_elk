<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class UetsMesajModel extends Model
{
    protected $table = 'uets_mesajlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * UETS mesaj ID ve firma bazında mesajı getirir
     */
    public function getByUetsId(int $firmId, string $messageId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM uets_mesajlar WHERE firm_id = :firm_id AND uets_message_id = :message_id AND deleted_at IS NULL LIMIT 1");
            $stmt->execute(['firm_id' => $firmId, 'message_id' => $messageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::getByUetsId Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Yerel ID ve firma bazında mesajı getirir
     */
    public function getById(int $id, int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM uets_mesajlar WHERE id = :id AND firm_id = :firm_id AND deleted_at IS NULL LIMIT 1");
            $stmt->execute(['id' => $id, 'firm_id' => $firmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::getById Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gelen tebligatı kaydeder veya durumunu günceller
     */
    public function upsertIncoming(int $firmId, array $data): int
    {
        try {
            $existing = $this->getByUetsId($firmId, $data['id']);
            
            // 7201 s. Kanun m. 7/a: Tebliğ tarihi teslimden 5 gün sonrasıdır
            $insertTime = !empty($data['inserttime']) ? date('Y-m-d H:i:s', (int) $data['inserttime']) : date('Y-m-d H:i:s');
            $hukukiTebligTarihi = date('Y-m-d 23:59:59', strtotime($insertTime . ' +5 days'));
            $updateTime = !empty($data['updatetime']) ? date('Y-m-d H:i:s', (int) $data['updatetime']) : date('Y-m-d H:i:s');

            if ($existing) {
                $sql = "UPDATE uets_mesajlar SET 
                            status = :status,
                            folders_id = :folders_id,
                            updatetime = :updatetime,
                            updated_at = NOW()
                        WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'status' => (string) ($data['status'] ?? 'delivered'),
                    'folders_id' => (int) ($data['folders_id'] ?? 1),
                    'updatetime' => $updateTime,
                    'id' => (int) $existing['id'],
                ]);
                return (int) $existing['id'];
            }

            $sql = "INSERT INTO uets_mesajlar 
                        (firm_id, uets_message_id, yon, sender_uets, sender_name, recipient_uets, recipient_name, 
                         subject, status, folders_id, size, etag, barcode, reference_id, inserttime, updatetime, 
                         hukuki_teblig_tarihi, created_at)
                    VALUES 
                        (:firm_id, :uets_message_id, 'gelen', :sender_uets, :sender_name, :recipient_uets, :recipient_name, 
                         :subject, :status, :folders_id, :size, :etag, :barcode, :reference_id, :inserttime, :updatetime, 
                         :hukuki_teblig_tarihi, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'firm_id' => $firmId,
                'uets_message_id' => (string) $data['id'],
                'sender_uets' => (string) ($data['sender'] ?? ''),
                'sender_name' => (string) ($data['sender_name'] ?? ''),
                'recipient_uets' => (string) ($data['recipient'] ?? ''),
                'recipient_name' => (string) ($data['recipient_name'] ?? ''),
                'subject' => (string) ($data['subject'] ?? 'Konusuz Tebligat'),
                'status' => (string) ($data['status'] ?? 'delivered'),
                'folders_id' => (int) ($data['folders_id'] ?? 1),
                'size' => (int) ($data['size'] ?? 0),
                'etag' => (string) ($data['etag'] ?? ''),
                'barcode' => (string) ($data['attributes']['barcode'] ?? ''),
                'reference_id' => (string) ($data['reference_id'] ?? ''),
                'inserttime' => $insertTime,
                'updatetime' => $updateTime,
                'hukuki_teblig_tarihi' => $hukukiTebligTarihi,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::upsertIncoming Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Giden tebligatı kaydeder
     */
    public function saveOutgoing(int $firmId, array $data): int
    {
        try {
            $insertTime = date('Y-m-d H:i:s');
            $sql = "INSERT INTO uets_mesajlar 
                        (firm_id, evrak_id, uets_message_id, yon, sender_uets, sender_name, recipient_uets, recipient_name, 
                         subject, status, folders_id, size, etag, barcode, reference_id, inserttime, updatetime, 
                         ham_eyp_yolu, created_at)
                    VALUES 
                        (:firm_id, :evrak_id, :uets_message_id, 'giden', :sender_uets, :sender_name, :recipient_uets, :recipient_name, 
                         :subject, :status, 2, :size, :etag, :barcode, :reference_id, :inserttime, :updatetime, 
                         :ham_eyp_yolu, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'firm_id' => $firmId,
                'evrak_id' => !empty($data['evrak_id']) ? (int) $data['evrak_id'] : null,
                'uets_message_id' => (string) ($data['id'] ?? $data['uets_message_id']),
                'sender_uets' => (string) ($data['sender'] ?? ''),
                'sender_name' => (string) ($data['sender_name'] ?? ''),
                'recipient_uets' => (string) ($data['recipient'] ?? ''),
                'recipient_name' => (string) ($data['recipient_name'] ?? ''),
                'subject' => (string) ($data['subject'] ?? ''),
                'status' => (string) ($data['status'] ?? 'submitted'),
                'size' => (int) ($data['size'] ?? 0),
                'etag' => (string) ($data['etag'] ?? ''),
                'barcode' => (string) ($data['barcode'] ?? ''),
                'reference_id' => (string) ($data['reference_id'] ?? ''),
                'inserttime' => $insertTime,
                'updatetime' => $insertTime,
                'ham_eyp_yolu' => (string) ($data['ham_eyp_yolu'] ?? ''),
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::saveOutgoing Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Durum güncelleme
     */
    public function updateStatus(int $id, string $status, ?string $okunmaTarihi = null): bool
    {
        try {
            $sql = "UPDATE uets_mesajlar SET status = :status, updatetime = NOW(), updated_at = NOW()";
            $params = ['status' => $status, 'id' => $id];
            if ($okunmaTarihi !== null) {
                $sql .= ", okunma_tarihi = :okunma_tarihi";
                $params['okunma_tarihi'] = $okunmaTarihi;
            }
            $sql .= " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::updateStatus Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Yerel dosya ve EYP yollarını güncelleme
     */
    public function updateLocalFiles(int $id, ?string $yerelDosyaYolu = null, ?string $hamEypYolu = null): bool
    {
        try {
            $fields = [];
            $params = ['id' => $id];
            if ($yerelDosyaYolu !== null) {
                $fields[] = "yerel_dosya_yolu = :yerel_dosya_yolu";
                $params['yerel_dosya_yolu'] = $yerelDosyaYolu;
            }
            if ($hamEypYolu !== null) {
                $fields[] = "ham_eyp_yolu = :ham_eyp_yolu";
                $params['ham_eyp_yolu'] = $hamEypYolu;
            }
            if (empty($fields)) {
                return true;
            }
            $sql = "UPDATE uets_mesajlar SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::updateLocalFiles Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * evrak_takip tablosuna bağlama
     */
    public function linkToEvrak(int $id, int $evrakId): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE uets_mesajlar SET evrak_id = :evrak_id, updated_at = NOW() WHERE id = :id");
            return $stmt->execute(['evrak_id' => $evrakId, 'id' => $id]);
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::linkToEvrak Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mesajları filtreli olarak listeleme
     */
    public function listMessages(int $firmId, array $filters = []): array
    {
        try {
            $conditions = ["firm_id = :firm_id", "deleted_at IS NULL"];
            $params = ['firm_id' => $firmId];

            if (!empty($filters['yon']) && in_array($filters['yon'], ['gelen', 'giden'])) {
                $conditions[] = "yon = :yon";
                $params['yon'] = $filters['yon'];
            }

            if (!empty($filters['status'])) {
                $conditions[] = "status = :status";
                $params['status'] = $filters['status'];
            }

            if (!empty($filters['q'])) {
                $conditions[] = "(subject LIKE :q OR sender_name LIKE :q OR recipient_name LIKE :q OR barcode LIKE :q)";
                $params['q'] = '%' . trim((string) $filters['q']) . '%';
            }

            $whereSql = implode(' AND ', $conditions);
            $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
            $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

            $sql = "SELECT m.*, e.evrak_no, e.onay_durumu 
                    FROM uets_mesajlar m
                    LEFT JOIN evrak_takip e ON e.id = m.evrak_id
                    WHERE {$whereSql}
                    ORDER BY m.inserttime DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::listMessages Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * İstatistik özetleri
     */
    public function getStats(int $firmId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as toplam_sayi,
                    SUM(CASE WHEN yon = 'gelen' THEN 1 ELSE 0 END) as gelen_sayisi,
                    SUM(CASE WHEN yon = 'giden' THEN 1 ELSE 0 END) as giden_sayisi,
                    SUM(CASE WHEN yon = 'gelen' AND status = 'delivered' THEN 1 ELSE 0 END) as okunmamis_gelen,
                    SUM(CASE WHEN yon = 'gelen' AND status = 'readtimeout' THEN 1 ELSE 0 END) as yasal_sure_asimi
                 FROM uets_mesajlar 
                 WHERE firm_id = :firm_id AND deleted_at IS NULL"
            );
            $stmt->execute(['firm_id' => $firmId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'toplam' => (int) ($res['toplam_sayi'] ?? 0),
                'gelen' => (int) ($res['gelen_sayisi'] ?? 0),
                'giden' => (int) ($res['giden_sayisi'] ?? 0),
                'okunmamis' => (int) ($res['okunmamis_gelen'] ?? 0),
                'yasal_sure_asimi' => (int) ($res['yasal_sure_asimi'] ?? 0),
            ];
        } catch (\PDOException $e) {
            error_log("UetsMesajModel::getStats Error: " . $e->getMessage());
            return ['toplam' => 0, 'gelen' => 0, 'giden' => 0, 'okunmamis' => 0, 'yasal_sure_asimi' => 0];
        }
    }
}
