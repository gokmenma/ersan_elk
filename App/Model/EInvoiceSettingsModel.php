<?php
namespace App\Model;

use App\Model\Model;
use App\Helper\Security;
use PDO;

class EInvoiceSettingsModel extends Model
{
    protected $table = 'efatura_ayarlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Firma e-fatura ayarlarını getirir
     */
    public function getSettings(int $firmId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM efatura_ayarlar WHERE firm_id = :firm_id AND is_active = 1 LIMIT 1");
            $stmt->execute(['firm_id' => $firmId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                if (!empty($row['api_password'])) {
                    $row['api_password_decrypted'] = Security::decrypt($row['api_password']);
                } else {
                    $row['api_password_decrypted'] = '';
                }
                return $row;
            }
            return null;
        } catch (\PDOException $e) {
            error_log("EInvoiceSettingsModel::getSettings Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Firma e-fatura ayarlarını kaydeder veya günceller
     */
    public function saveSettings(int $firmId, array $data): bool
    {
        try {
            $existing = $this->getSettings($firmId);
            $encryptedPassword = !empty($data['api_password']) ? Security::encrypt($data['api_password']) : ($existing['api_password'] ?? '');

            if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE efatura_ayarlar SET 
                        entegrator = :entegrator,
                        api_username = :api_username,
                        api_password = :api_password,
                        environment = :environment,
                        test_wsdl_url = :test_wsdl_url,
                        live_wsdl_url = :live_wsdl_url,
                        efatura_seri = :efatura_seri,
                        earsiv_seri = :earsiv_seri,
                        varsayilan_gonderici_alias = :varsayilan_gonderici_alias,
                        otomatik_gonder = :otomatik_gonder,
                        updated_at = NOW()
                    WHERE firm_id = :firm_id
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO efatura_ayarlar 
                        (firm_id, entegrator, api_username, api_password, environment, test_wsdl_url, live_wsdl_url, efatura_seri, earsiv_seri, varsayilan_gonderici_alias, otomatik_gonder, is_active, created_at)
                    VALUES 
                        (:firm_id, :entegrator, :api_username, :api_password, :environment, :test_wsdl_url, :live_wsdl_url, :efatura_seri, :earsiv_seri, :varsayilan_gonderici_alias, :otomatik_gonder, 1, NOW())
                ");
            }

            return $stmt->execute([
                'firm_id'                    => $firmId,
                'entegrator'                 => $data['entegrator'] ?? 'EDM',
                'api_username'               => $data['api_username'] ?? '',
                'api_password'               => $encryptedPassword,
                'environment'                => in_array($data['environment'] ?? '', ['TEST', 'LIVE']) ? $data['environment'] : 'TEST',
                'test_wsdl_url'              => $data['test_wsdl_url'] ?? 'https://efaturatest.edmbilisim.com.tr/EFaturaEDM21/EFaturaEDM.svc?wsdl',
                'live_wsdl_url'              => $data['live_wsdl_url'] ?? 'https://efatura.edmbilisim.com.tr/EFaturaEDM/EFaturaEDM.svc?wsdl',
                'efatura_seri'               => strtoupper(substr(trim($data['efatura_seri'] ?? 'ERS'), 0, 3)),
                'earsiv_seri'                => strtoupper(substr(trim($data['earsiv_seri'] ?? 'ERA'), 0, 3)),
                'varsayilan_gonderici_alias' => $data['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb',
                'otomatik_gonder'            => !empty($data['otomatik_gonder']) ? 1 : 0
            ]);
        } catch (\PDOException $e) {
            error_log("EInvoiceSettingsModel::saveSettings Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sıradaki fatura numarasını üretir (Örn: ERS2026000000001)
     * Atomik transaction ile kilitler ve mükerrer oluşumunu önler
     */
    public function generateNextInvoiceNumber(int $firmId, string $belgeTuru, string $seri, ?int $yil = null): string
    {
        $yil = $yil ?: (int) date('Y');
        $seri = strtoupper(substr(trim($seri), 0, 3));

        $stmt = $this->db->prepare("
            INSERT INTO efatura_numarator (firm_id, belge_turu, yil, seri, son_numara, updated_at)
            VALUES (:firm_id, :belge_turu, :yil, :seri, 1, NOW())
            ON DUPLICATE KEY UPDATE son_numara = son_numara + 1, updated_at = NOW()
        ");
        $stmt->execute([
            'firm_id'    => $firmId,
            'belge_turu' => $belgeTuru,
            'yil'        => $yil,
            'seri'       => $seri
        ]);

        $fetchStmt = $this->db->prepare("
            SELECT son_numara FROM efatura_numarator 
            WHERE firm_id = :firm_id AND belge_turu = :belge_turu AND yil = :yil AND seri = :seri
        ");
        $fetchStmt->execute([
            'firm_id'    => $firmId,
            'belge_turu' => $belgeTuru,
            'yil'        => $yil,
            'seri'       => $seri
        ]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        $num = $row ? (int)$row['son_numara'] : 1;

        // 3 hane Seri + 4 hane Yıl + 9 hane Sıra No (Toplam 16 Karakter)
        return sprintf('%s%04d%09d', $seri, $yil, $num);
    }

    /**
     * SOAP işlem logunu kaydeder
     */
    public function logSoapAction(int $firmId, ?int $faturaId, string $islemTuru, ?string $istek, ?string $yanit, string $durum, ?string $hataKodu = null, ?string $hataMesaji = null, ?int $userId = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO efatura_loglari 
                    (firm_id, fatura_id, islem_turu, istek_payload, yanit_payload, durum, hata_kodu, hata_mesaji, ip_adresi, user_id, created_at)
                VALUES 
                    (:firm_id, :fatura_id, :islem_turu, :istek, :yanit, :durum, :hata_kodu, :hata_mesaji, :ip_adresi, :user_id, NOW())
            ");
            return $stmt->execute([
                'firm_id'     => $firmId,
                'fatura_id'   => $faturaId,
                'islem_turu'  => $islemTuru,
                'istek'       => $istek,
                'yanit'       => $yanit,
                'durum'       => in_array($durum, ['BASARILI', 'BASARISIZ']) ? $durum : 'BASARILI',
                'hata_kodu'   => $hataKodu,
                'hata_mesaji' => $hataMesaji,
                'ip_adresi'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_id'     => $userId ?: ($_SESSION['user_id'] ?? null)
            ]);
        } catch (\PDOException $e) {
            error_log("EInvoiceSettingsModel::logSoapAction Error: " . $e->getMessage());
            return false;
        }
    }
}
