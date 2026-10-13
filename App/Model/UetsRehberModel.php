<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class UetsRehberModel extends Model
{
    protected $table = 'uets_rehber';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Yerel önbellekten alıcı arama (UETS adresi, TCKN/VKN veya unvan)
     */
    public function searchLocal(int $firmId, string $query): array
    {
        try {
            $q = '%' . trim($query) . '%';
            $stmt = $this->db->prepare(
                "SELECT * FROM uets_rehber 
                 WHERE firm_id = :firm_id 
                   AND (uets_adresi LIKE :q OR unvan LIKE :q OR tckn_vkn LIKE :q)
                 ORDER BY son_sorgu_tarihi DESC LIMIT 20"
            );
            $stmt->execute(['firm_id' => $firmId, 'q' => $q]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("UetsRehberModel::searchLocal Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Alıcıyı önbelleğe kaydetme veya güncelleme
     */
    public function saveOrUpdate(int $firmId, array $recipient): int
    {
        try {
            $uetsAdresi = trim((string) ($recipient['address'] ?? $recipient['uets_adresi'] ?? ''));
            if ($uetsAdresi === '') {
                return 0;
            }

            $unvan = trim((string) ($recipient['name'] ?? $recipient['unvan'] ?? ''));
            if (!empty($recipient['lastname'])) {
                $unvan .= ' ' . trim((string) $recipient['lastname']);
            }
            $tcknVkn = trim((string) ($recipient['tcn'] ?? $recipient['vgn'] ?? $recipient['tckn_vkn'] ?? ''));

            $stmtCheck = $this->db->prepare("SELECT id FROM uets_rehber WHERE firm_id = :firm_id AND uets_adresi = :addr LIMIT 1");
            $stmtCheck->execute(['firm_id' => $firmId, 'addr' => $uetsAdresi]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmtUpdate = $this->db->prepare(
                    "UPDATE uets_rehber SET unvan = :unvan, tckn_vkn = :tckn_vkn, son_sorgu_tarihi = NOW() WHERE id = :id"
                );
                $stmtUpdate->execute(['unvan' => $unvan, 'tckn_vkn' => $tcknVkn, 'id' => (int) $existingId]);
                return (int) $existingId;
            }

            $stmtInsert = $this->db->prepare(
                "INSERT INTO uets_rehber (firm_id, uets_adresi, tckn_vkn, unvan, son_sorgu_tarihi)
                 VALUES (:firm_id, :uets_adresi, :tckn_vkn, :unvan, NOW())"
            );
            $stmtInsert->execute([
                'firm_id' => $firmId,
                'uets_adresi' => $uetsAdresi,
                'tckn_vkn' => $tcknVkn,
                'unvan' => $unvan,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("UetsRehberModel::saveOrUpdate Error: " . $e->getMessage());
            return 0;
        }
    }
}
