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
            if (!in_array($data['environment'] ?? '', ['TEST','LIVE'], true) || trim($data['api_username'] ?? '') === '') throw new \InvalidArgumentException('Geçerli EDM ortamı ve kullanıcı adı gereklidir.');
            
            $efaturaSeri = strtoupper(substr(trim($data['efatura_seri'] ?? ($existing['efatura_seri'] ?? 'ERS')), 0, 3));
            $earsivSeri = strtoupper(substr(trim($data['earsiv_seri'] ?? ($existing['earsiv_seri'] ?? 'ERA')), 0, 3));
            if (!preg_match('/^[A-Z0-9]{3}$/D', $efaturaSeri) || !preg_match('/^[A-Z0-9]{3}$/D', $earsivSeri)) throw new \InvalidArgumentException('Fatura serileri üç harf/rakamdan oluşmalıdır.');
            if ($efaturaSeri === $earsivSeri) throw new \InvalidArgumentException('e-Fatura ve e-Arşiv serileri farklı olmalıdır.');

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
                        otomatik_gonder = :otomatik_gonder, kontor_esik = :kontor_esik,
                        updated_at = NOW()
                    WHERE firm_id = :firm_id
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO efatura_ayarlar 
                        (firm_id, entegrator, api_username, api_password, environment, test_wsdl_url, live_wsdl_url, efatura_seri, earsiv_seri, varsayilan_gonderici_alias, otomatik_gonder, kontor_esik, is_active, created_at)
                    VALUES 
                        (:firm_id, :entegrator, :api_username, :api_password, :environment, :test_wsdl_url, :live_wsdl_url, :efatura_seri, :earsiv_seri, :varsayilan_gonderici_alias, :otomatik_gonder, :kontor_esik, 1, NOW())
                ");
            }

            return $stmt->execute([
                'firm_id'                    => $firmId,
                'entegrator'                 => $data['entegrator'] ?? 'EDM',
                'api_username'               => $data['api_username'] ?? '',
                'api_password'               => $encryptedPassword,
                'environment'                => in_array($data['environment'] ?? '', ['TEST', 'LIVE']) ? $data['environment'] : 'TEST',
                'test_wsdl_url'              => $data['test_wsdl_url'] ?? $existing['test_wsdl_url'] ?? \App\Config\EdmConfig::TEST_WSDL_URL,
                'live_wsdl_url'              => !empty($data['live_wsdl_url']) && !str_contains($data['live_wsdl_url'], 'efatura.edmbilisim.com.tr') ? $data['live_wsdl_url'] : \App\Config\EdmConfig::LIVE_WSDL_URL,
                'efatura_seri'               => $efaturaSeri,
                'earsiv_seri'                => $earsivSeri,
                'varsayilan_gonderici_alias' => $data['varsayilan_gonderici_alias'] ?? '',
                'otomatik_gonder'            => !empty($data['otomatik_gonder']) ? 1 : 0,
                'kontor_esik' => max(0, (int)($data['kontor_esik'] ?? 100))
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
        $yil = $yil ?: (int)date('Y');
        $seri = strtoupper(trim($seri));
        if (!preg_match('/^[A-Z0-9]{3}$/D', $seri) || $yil < 2000 || $yil > 2099) throw new \InvalidArgumentException('Fatura serisi veya yılı geçersiz.');
        $this->db->beginTransaction();
        try {
            $params = ['firm' => $firmId, 'type' => $belgeTuru, 'year' => $yil, 'series' => $seri];
            $stmt = $this->db->prepare('INSERT INTO efatura_numarator (firm_id, belge_turu, yil, seri, son_numara) VALUES (:firm, :type, :year, :series, 0) ON DUPLICATE KEY UPDATE id = id');
            $stmt->execute($params);
            $stmt = $this->db->prepare('SELECT son_numara FROM efatura_numarator WHERE firm_id = :firm AND belge_turu = :type AND yil = :year AND seri = :series FOR UPDATE');
            $stmt->execute($params);
            $previous = (int)$stmt->fetchColumn();
            // Imported portal invoices may precede local counter creation.
            $stmt = $this->db->prepare("SELECT COALESCE(MAX(CAST(RIGHT(fatura_no, 9) AS UNSIGNED)), 0) FROM faturalar WHERE firm_id = :firm AND yon = 'GIDEN' AND fatura_no LIKE :prefix AND CHAR_LENGTH(fatura_no) = 16");
            $stmt->execute(['firm' => $firmId, 'prefix' => $seri . $yil . '%']);
            $number = max($previous, (int)$stmt->fetchColumn()) + 1;
            if ($number > 999999999) throw new \RuntimeException('Fatura serisi doldu.');
            $stmt = $this->db->prepare('UPDATE efatura_numarator SET son_numara = :number, updated_at = NOW() WHERE firm_id = :firm AND belge_turu = :type AND yil = :year AND seri = :series');
            $stmt->execute($params + ['number' => $number]);
            $this->db->commit();
            return sprintf('%s%04d%09d', $seri, $yil, $number);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function reconcileSerial(int $firmId, string $type, string $series, int $year, int $last): void
    {
        if (!preg_match('/^[A-Z0-9]{3}$/D', $series) || $last < 0 || $last > 999999999) throw new \InvalidArgumentException('EDM seri bilgisi geçersiz.');
        $stmt = $this->db->prepare('INSERT INTO efatura_numarator (firm_id, belge_turu, yil, seri, son_numara, updated_at) VALUES (:firm, :type, :year, :series, :last, NOW()) ON DUPLICATE KEY UPDATE son_numara = GREATEST(son_numara, VALUES(son_numara)), updated_at = NOW()');
        $stmt->execute(['firm' => $firmId, 'type' => $type, 'year' => $year, 'series' => $series, 'last' => $last]);
    }

    /**
     * Firmanın tüm kayıtlı sayaçlarını getirir
     */
    public function getNumarators(int $firmId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM efatura_numarator WHERE firm_id = :firm ORDER BY yil DESC, belge_turu ASC, seri ASC");
            $stmt->execute(['firm' => $firmId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            error_log("EInvoiceSettingsModel::getNumarators Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sayaç / Numaratör kaydını günceller veya ekler
     */
    public function saveNumarator(int $firmId, string $type, string $series, int $year, int $lastNumber): bool
    {
        $series = strtoupper(trim($series));
        if (!preg_match('/^[A-Z0-9]{3}$/D', $series) || $year < 2000 || $year > 2099 || $lastNumber < 0 || $lastNumber > 999999999) {
            throw new \InvalidArgumentException('Geçersiz seri, yıl veya numara.');
        }
        if (!in_array($type, ['EFATURA', 'EARSIV', 'EIRSALIYE', 'ESMM'], true)) {
            throw new \InvalidArgumentException('Geçersiz belge türü.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO efatura_numarator (firm_id, belge_turu, yil, seri, son_numara, updated_at)
            VALUES (:firm, :type, :year, :series, :last, NOW())
            ON DUPLICATE KEY UPDATE son_numara = :last_update, updated_at = NOW()
        ");
        return $stmt->execute([
            'firm' => $firmId,
            'type' => $type,
            'year' => $year,
            'series' => $series,
            'last' => $lastNumber,
            'last_update' => $lastNumber
        ]);
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
                'istek'       => \App\Helper\EInvoiceSecurity::redact($istek),
                'yanit'       => \App\Helper\EInvoiceSecurity::redact($yanit),
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
