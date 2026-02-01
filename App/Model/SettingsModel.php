<?php
namespace App\Model;

use App\Model\Model;
use PDO;

class SettingsModel extends Model
{
    protected $table = 'settings';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirli bir ayarın değerini döndürür.
     * @param string $set_name Ayarın adı (set_name)
     * @return string|null Ayarın değeri veya bulunamazsa null
     */
    public function getSettings(string $set_name): ?string
    {
        $stmt = $this->db->prepare("SELECT set_value FROM {$this->table} WHERE set_name = :set_name");
        $stmt->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? $result->set_value : null;
    }

    /**
     * Tüm ayarları 'set_name' => 'set_value' formatında bir dizi olarak döndürür.
     * @param int|null $firma_id Firma bazlı ayarları almak için opsiyonel firma ID
     * @return array Ayarlar dizisi
     */
    public function getAllSettingsAsKeyValue(?int $firma_id = null): array
    {
        // Önce global ayarları al
        $stmt = $this->db->prepare("SELECT set_name, set_value FROM {$this->table} WHERE firma_id IS NULL AND user_id IS NULL");
        $stmt->execute();
        $globalSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        if ($firma_id !== null) {
            // Firma bazlı ayarları al ve global olanlarla birleştir (firma ayarları globali ezer)
            $stmt = $this->db->prepare("SELECT set_name, set_value FROM {$this->table} WHERE firma_id = :firma_id");
            $stmt->execute(['firma_id' => $firma_id]);
            $firmaSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            return array_merge($globalSettings, $firmaSettings);
        }

        return $globalSettings;
    }


    /**
     * Belirli bir ayarı günceller.
     * @param string $set_name Ayarın adı
     * @param string $set_value Ayarın yeni değeri
     * @return bool Başarılı olursa true, aksi halde false
     */
    public function updateSetting(string $set_name, string $set_value): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET set_value = :set_value WHERE set_name = :set_name");
        $stmt->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $stmt->bindParam(':set_value', $set_value, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Kullanıcı / Firma / Global ayar kaydeder.
     * Öncelik:
     * - user_id doluysa → kullanıcı ayarı
     * - user_id NULL, firma_id doluysa → firma ayarı
     * - ikisi de NULL → global ayar
     */
    private function upsertSettingInternal(
        string $set_name,
        string $set_value,
        ?int $firma_id = null,
        ?int $user_id = null
    ): bool {
        // Mevcut kaydı kontrol et
        $sqlCheck = "SELECT id FROM {$this->table} WHERE set_name = :set_name ";
        $params = ['set_name' => $set_name];

        if ($firma_id === null) {
            $sqlCheck .= " AND firma_id IS NULL";
        } else {
            $sqlCheck .= " AND firma_id = :firma_id";
            $params['firma_id'] = $firma_id;
        }

        if ($user_id === null) {
            $sqlCheck .= " AND user_id IS NULL";
        } else {
            $sqlCheck .= " AND user_id = :user_id";
            $params['user_id'] = $user_id;
        }

        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute($params);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            // Güncelle
            $sqlUpdate = "UPDATE {$this->table} SET set_value = :set_value WHERE id = :id";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            return $stmtUpdate->execute([
                'set_value' => $set_value,
                'id' => $existingId
            ]);
        } else {
            // Ekle
            $sqlInsert = "INSERT INTO {$this->table} (set_name, set_value, firma_id, user_id) VALUES (:set_name, :set_value, :firma_id, :user_id)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            return $stmtInsert->execute([
                'set_name' => $set_name,
                'set_value' => $set_value,
                'firma_id' => $firma_id,
                'user_id' => $user_id
            ]);
        }
    }

    public function upsertSetting(string $set_name, string $set_value): bool
    {
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $ok = $this->upsertSettingInternal($set_name, $set_value);
                $this->db->commit();
                return $ok;
            }
            return $this->upsertSettingInternal($set_name, $set_value);
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    /**
     * Birden fazla ayarı tek seferde günceller.
     */
    public function updateMultipleSettings(array $settingsToUpdate): bool
    {
        if (empty($settingsToUpdate)) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            foreach ($settingsToUpdate as $setName => $setValue) {
                if (!$this->updateSetting((string) $setName, (string) $setValue)) {
                    $this->db->rollBack();
                    return false;
                }
            }
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    /**
     * Birden fazla ayarı upsert eder.
     */
    public function upsertMultipleSettings(array $settingsToUpdate, ?int $firma_id = null, ?int $user_id = null): bool
    {
        if (empty($settingsToUpdate)) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            foreach ($settingsToUpdate as $setName => $setValue) {
                if (!$this->upsertSettingInternal((string) $setName, (string) $setValue, $firma_id, $user_id)) {
                    $this->db->rollBack();
                    return false;
                }
            }
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }
}