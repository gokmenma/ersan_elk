<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class KepSettingsModel extends Model
{
    protected $table = 'kep_ayarlar';
    private const USER_TABLE = 'kep_kullanici_hesaplari';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma KEP ayarlarını getirir (şifreli alanları deşifre eder)
     */
    public function getSettings(int $firmId, ?int $userId = null): ?array
    {
        try {
            $userId = $this->resolveUserId($userId);
            $this->claimLegacyCredentials($firmId, $userId);

            $stmt = $this->db->prepare("SELECT ka.*,
                        kh.id AS kullanici_hesap_id,
                        kh.yetkili_tcno AS kullanici_yetkili_tcno,
                        kh.parola AS kullanici_parola,
                        kh.sifre AS kullanici_sifre
                    FROM {$this->table} ka
                    LEFT JOIN " . self::USER_TABLE . " kh
                        ON kh.kep_ayar_id = ka.id
                       AND kh.user_id = :user_id
                       AND kh.is_active = 1
                       AND kh.deleted_at IS NULL
                    WHERE ka.firm_id = :firm_id AND ka.is_active = 1
                    LIMIT 1");
            $stmt->execute(['firm_id' => $firmId, 'user_id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $row['yetkili_tcno_decrypted'] = !empty($row['kullanici_yetkili_tcno']) ? Security::decrypt($row['kullanici_yetkili_tcno']) : '';
                $row['parola_decrypted'] = !empty($row['kullanici_parola']) ? Security::decrypt($row['kullanici_parola']) : '';
                $row['sifre_decrypted'] = !empty($row['kullanici_sifre']) ? Security::decrypt($row['kullanici_sifre']) : '';
                $row['has_user_credentials'] = !empty($row['kullanici_hesap_id']);
                return $row;
            }

            return null;
        } catch (\PDOException $e) {
            error_log("KepSettingsModel::getSettings Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Firma KEP ayarlarını kaydeder veya günceller
     */
    public function saveSettings(int $firmId, array $data, ?int $userId = null): bool
    {
        try {
            $userId = $this->resolveUserId($userId);
            if ($userId <= 0) {
                return false;
            }
            $existing = $this->getSettings($firmId, $userId);
            $kepAdresi = trim((string) ($data['kep_adresi'] ?? ''));

            $wsdlUrl = !empty($data['wsdl_url']) ? trim((string) $data['wsdl_url']) : 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl';
            $ortam = in_array(($data['ortam'] ?? 'prod'), ['prod', 'test'], true) ? $data['ortam'] : 'prod';

            $this->db->beginTransaction();
            if ($existing) {
                $sql = "UPDATE {$this->table} SET 
                            kep_adresi = :kep_adresi,
                            wsdl_url = :wsdl_url,
                            ortam = :ortam,
                            updated_at = NOW()
                        WHERE firm_id = :firm_id";
            } else {
                $sql = "INSERT INTO {$this->table} (
                            firm_id, kep_adresi, yetkili_tcno, parola, sifre, wsdl_url, ortam, is_active, created_at
                        ) VALUES (
                            :firm_id, :kep_adresi, '', '', '', :wsdl_url, :ortam, 1, NOW()
                        )";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'firm_id' => $firmId,
                'kep_adresi' => $kepAdresi,
                'wsdl_url' => $wsdlUrl,
                'ortam' => $ortam,
            ]);
            $settingsId = $existing ? (int) $existing['id'] : (int) $this->db->lastInsertId();

            $credentials = $this->getUserCredentialRow($settingsId, $userId);
            $tcnoToStore = !empty($data['yetkili_tcno']) ? Security::encrypt(trim((string) $data['yetkili_tcno'])) : ($credentials['yetkili_tcno'] ?? '');
            $parolaToStore = !empty($data['parola']) ? Security::encrypt(trim((string) $data['parola'])) : ($credentials['parola'] ?? '');
            $sifreToStore = !empty($data['sifre']) ? Security::encrypt(trim((string) $data['sifre'])) : ($credentials['sifre'] ?? '');
            if ($tcnoToStore === '' || $parolaToStore === '' || $sifreToStore === '') {
                throw new \RuntimeException('Bu kullanıcı için T.C. No, parola ve web servis şifresi eksiksiz girilmelidir.');
            }

            $credentialSql = "INSERT INTO " . self::USER_TABLE . "
                    (kep_ayar_id, firm_id, user_id, yetkili_tcno, parola, sifre, is_active, created_at)
                VALUES (:kep_ayar_id, :firm_id, :user_id, :yetkili_tcno, :parola, :sifre, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    yetkili_tcno = VALUES(yetkili_tcno), parola = VALUES(parola), sifre = VALUES(sifre),
                    is_active = 1, deleted_at = NULL, updated_at = NOW()";
            $credentialStmt = $this->db->prepare($credentialSql);
            $credentialStmt->execute([
                'kep_ayar_id' => $settingsId, 'firm_id' => $firmId, 'user_id' => $userId,
                'yetkili_tcno' => $tcnoToStore, 'parola' => $parolaToStore, 'sifre' => $sifreToStore,
            ]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("KepSettingsModel::saveSettings Error: " . $e->getMessage());
            return false;
        }
    }

    private function resolveUserId(?int $userId): int
    {
        return $userId ?? (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    }

    private function getUserCredentialRow(int $settingsId, int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT yetkili_tcno, parola, sifre FROM " . self::USER_TABLE . "
            WHERE kep_ayar_id = :kep_ayar_id AND user_id = :user_id AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['kep_ayar_id' => $settingsId, 'user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** İlk geçişte eski tekil hesabı, KEP ekranını ilk kullanan yetkili kullanıcıya taşır. */
    private function claimLegacyCredentials(int $firmId, int $userId): void
    {
        if ($userId <= 0) return;
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $legacyStmt = $this->db->prepare("SELECT id, yetkili_tcno, parola, sifre FROM {$this->table}
                WHERE firm_id = :firm_id AND is_active = 1 LIMIT 1 FOR UPDATE");
            $legacyStmt->execute(['firm_id' => $firmId]);
            $legacy = $legacyStmt->fetch(PDO::FETCH_ASSOC);
            if ($legacy && $legacy['yetkili_tcno'] !== '' && $legacy['parola'] !== '' && $legacy['sifre'] !== '') {
                $countStmt = $this->db->prepare("SELECT COUNT(*) FROM " . self::USER_TABLE . " WHERE kep_ayar_id = :kep_ayar_id");
                $countStmt->execute(['kep_ayar_id' => (int) $legacy['id']]);
                if ((int) $countStmt->fetchColumn() === 0) {
                    $insertStmt = $this->db->prepare("INSERT INTO " . self::USER_TABLE . "
                        (kep_ayar_id, firm_id, user_id, yetkili_tcno, parola, sifre, is_active, created_at)
                        VALUES (:kep_ayar_id, :firm_id, :user_id, :yetkili_tcno, :parola, :sifre, 1, NOW())");
                    $insertStmt->execute([
                        'kep_ayar_id' => (int) $legacy['id'], 'firm_id' => $firmId, 'user_id' => $userId,
                        'yetkili_tcno' => $legacy['yetkili_tcno'], 'parola' => $legacy['parola'], 'sifre' => $legacy['sifre'],
                    ]);
                }
            }
            if ($ownsTransaction) $this->db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Bakiye ve son sorgu zamanını günceller
     */
    public function updateBalance(int $firmId, float $balance, string $cins = 'Adet'): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE {$this->table} SET bakiye_kontor = :bakiye, bakiye_cins = :cins, son_bakiye_sorgu = NOW() WHERE firm_id = :firm_id");
            return $stmt->execute([
                'firm_id' => $firmId,
                'bakiye' => $balance,
                'cins' => $cins,
            ]);
        } catch (\PDOException $e) {
            error_log("KepSettingsModel::updateBalance Error: " . $e->getMessage());
            return false;
        }
    }
}
