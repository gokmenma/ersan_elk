<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class UetsSettingsModel extends Model
{
    protected $table = 'uets_ayarlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function formatUetsAddress(string $address): string
    {
        $clean = preg_replace('/[^0-9]/', '', $address);
        if (strlen($clean) === 15) {
            return substr($clean, 0, 5) . '-' . substr($clean, 5, 5) . '-' . substr($clean, 10, 5);
        }
        return trim($address);
    }

    /**
     * Firma UETS ayarlarını getirir (API anahtarını deşifre edilmiş olarak döner)
     */
    public function getSettings(int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM uets_ayarlar WHERE firm_id = :firm_id AND is_active = 1 LIMIT 1");
            $stmt->execute(['firm_id' => $firmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $row['uets_adresi'] = self::formatUetsAddress($row['uets_adresi'] ?? '');
                if (!empty($row['api_key'])) {
                    $row['api_key_decrypted'] = Security::decrypt($row['api_key']);
                } else {
                    $row['api_key_decrypted'] = '';
                }
                return $row;
            }
            return null;
        } catch (\PDOException $e) {
            error_log("UetsSettingsModel::getSettings Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Firma UETS ayarlarını kaydeder veya günceller
     */
    public function saveSettings(int $firmId, array $data): bool
    {
        try {
            $existing = $this->getSettings($firmId);
            $uetsAdresi = self::formatUetsAddress(trim((string) ($data['uets_adresi'] ?? '')));
            $apiKeyToStore = !empty($data['api_key'])
                ? Security::encrypt(trim((string) $data['api_key']))
                : ($existing['api_key'] ?? '');

            if ($existing) {
                $sql = "UPDATE uets_ayarlar SET 
                            uets_adresi = :uets_adresi,
                            api_key = :api_key,
                            api_environment = :api_environment,
                            otomatik_senkronizasyon = :otomatik_senkronizasyon,
                            updated_at = NOW()
                        WHERE firm_id = :firm_id";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'uets_adresi' => $uetsAdresi,
                    'api_key' => $apiKeyToStore,
                    'api_environment' => in_array($data['api_environment'] ?? 'prod', ['test', 'prod']) ? $data['api_environment'] : 'prod',
                    'otomatik_senkronizasyon' => !empty($data['otomatik_senkronizasyon']) ? 1 : 0,
                    'firm_id' => $firmId,
                ]);
            } else {
                $sql = "INSERT INTO uets_ayarlar 
                            (firm_id, uets_adresi, api_key, api_environment, otomatik_senkronizasyon, is_active, created_at)
                        VALUES 
                            (:firm_id, :uets_adresi, :api_key, :api_environment, :otomatik_senkronizasyon, 1, NOW())";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'firm_id' => $firmId,
                    'uets_adresi' => $uetsAdresi,
                    'api_key' => $apiKeyToStore,
                    'api_environment' => in_array($data['api_environment'] ?? 'prod', ['test', 'prod']) ? $data['api_environment'] : 'prod',
                    'otomatik_senkronizasyon' => !empty($data['otomatik_senkronizasyon']) ? 1 : 0,
                ]);
            }
        } catch (\PDOException $e) {
            error_log("UetsSettingsModel::saveSettings Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bakiye güncelleme
     */
    public function updateBalance(int $firmId, float $balance): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE uets_ayarlar SET bakiye = :bakiye, updated_at = NOW() WHERE firm_id = :firm_id");
            return $stmt->execute(['bakiye' => $balance, 'firm_id' => $firmId]);
        } catch (\PDOException $e) {
            error_log("UetsSettingsModel::updateBalance Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Senkronizasyon zamanı güncelleme
     */
    public function updateSyncTimes(int $firmId, ?string $incoming = null, ?string $outgoing = null): bool
    {
        try {
            $fields = [];
            $params = ['firm_id' => $firmId];
            if ($incoming !== null) {
                $fields[] = "son_gelen_sync_zamani = :incoming";
                $params['incoming'] = $incoming;
            }
            if ($outgoing !== null) {
                $fields[] = "son_giden_sync_zamani = :outgoing";
                $params['outgoing'] = $outgoing;
            }
            if (empty($fields)) {
                return true;
            }
            $sql = "UPDATE uets_ayarlar SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE firm_id = :firm_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("UetsSettingsModel::updateSyncTimes Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Otomatik senkronizasyonu aktif olan firmaları döner
     * @return int[]
     */
    public function getActiveFirmIds(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT firm_id 
                 FROM uets_ayarlar 
                 WHERE is_active = 1 
                   AND otomatik_senkronizasyon = 1
                   AND uets_adresi IS NOT NULL AND uets_adresi <> ''
                   AND api_key IS NOT NULL AND api_key <> ''
                 ORDER BY firm_id"
            );
            $stmt->execute();
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\PDOException $e) {
            error_log("UetsSettingsModel::getActiveFirmIds Error: " . $e->getMessage());
            return [];
        }
    }
}
