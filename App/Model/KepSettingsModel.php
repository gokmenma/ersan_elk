<?php

namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class KepSettingsModel extends Model
{
    protected $table = 'kep_ayarlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma KEP ayarlarını getirir (şifreli alanları deşifre eder)
     */
    public function getSettings(int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE firm_id = :firm_id AND is_active = 1 LIMIT 1");
            $stmt->execute(['firm_id' => $firmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $row['yetkili_tcno_decrypted'] = !empty($row['yetkili_tcno']) ? Security::decrypt($row['yetkili_tcno']) : '';
                $row['parola_decrypted'] = !empty($row['parola']) ? Security::decrypt($row['parola']) : '';
                $row['sifre_decrypted'] = !empty($row['sifre']) ? Security::decrypt($row['sifre']) : '';
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
    public function saveSettings(int $firmId, array $data): bool
    {
        try {
            $existing = $this->getSettings($firmId);
            $kepAdresi = trim((string) ($data['kep_adresi'] ?? ''));

            // TCKN, Parola ve Şifre güncelleniyorsa şifrele, boş bırakıldıysa mevcut olanı koru
            $tcnoToStore = !empty($data['yetkili_tcno'])
                ? Security::encrypt(trim((string) $data['yetkili_tcno']))
                : ($existing['yetkili_tcno'] ?? '');

            $parolaToStore = !empty($data['parola'])
                ? Security::encrypt(trim((string) $data['parola']))
                : ($existing['parola'] ?? '');

            $sifreToStore = !empty($data['sifre'])
                ? Security::encrypt(trim((string) $data['sifre']))
                : ($existing['sifre'] ?? '');

            $wsdlUrl = !empty($data['wsdl_url']) ? trim((string) $data['wsdl_url']) : 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl';
            $ortam = in_array(($data['ortam'] ?? 'prod'), ['prod', 'test'], true) ? $data['ortam'] : 'prod';

            if ($existing) {
                $sql = "UPDATE {$this->table} SET 
                            kep_adresi = :kep_adresi,
                            yetkili_tcno = :yetkili_tcno,
                            parola = :parola,
                            sifre = :sifre,
                            wsdl_url = :wsdl_url,
                            ortam = :ortam,
                            updated_at = NOW()
                        WHERE firm_id = :firm_id";
            } else {
                $sql = "INSERT INTO {$this->table} (
                            firm_id, kep_adresi, yetkili_tcno, parola, sifre, wsdl_url, ortam, is_active, created_at
                        ) VALUES (
                            :firm_id, :kep_adresi, :yetkili_tcno, :parola, :sifre, :wsdl_url, :ortam, 1, NOW()
                        )";
            }

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'firm_id' => $firmId,
                'kep_adresi' => $kepAdresi,
                'yetkili_tcno' => $tcnoToStore,
                'parola' => $parolaToStore,
                'sifre' => $sifreToStore,
                'wsdl_url' => $wsdlUrl,
                'ortam' => $ortam,
            ]);
        } catch (\PDOException $e) {
            error_log("KepSettingsModel::saveSettings Error: " . $e->getMessage());
            return false;
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
