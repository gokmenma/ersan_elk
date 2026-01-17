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
    public function getSettings(string $set_name): ?string // getSettings -> getSetting olarak adını değiştirdim ve dönüş tipini belirttim
    {
        $stmt = $this->db->prepare("SELECT set_value FROM {$this->table} WHERE set_name = :set_name");
        $stmt->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result ? $result->set_value : null;
    }

    /**
     * Tüm ayarları 'set_name' => 'set_value' formatında bir dizi olarak döndürür.
     * @return array Ayarlar dizisi
     */
    public function getAllSettingsAsKeyValue(): array
    {
        $settings = [];
        $stmt = $this->db->query("SELECT set_name, set_value FROM {$this->table}");
        // PDO::FETCH_KEY_PAIR, ilk sütunu anahtar, ikinci sütunu değer olarak alır.
        // Eğer set_name'leriniz unique ise bu harika çalışır.
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); 
        
        if ($results === false) {
            // Hata durumu veya hiç ayar yoksa
            return [];
        }
        return $results; // fetchAll(PDO::FETCH_KEY_PAIR) zaten istediğimiz formatı verir
    }


    /**
     * Belirli bir ayarı günceller.
     * @param string $set_name Ayarın adı
     * @param string $set_value Ayarın yeni değeri
     * @return bool Başarılı olursa true, aksi halde false
     */
    public function updateSetting(string $set_name, string $set_value): bool // updateSettings -> updateSetting
    {
        
        $stmt = $this->db->prepare("UPDATE {$this->table} SET set_value = :set_value WHERE set_name = :set_name");
        $stmt->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $stmt->bindParam(':set_value', $set_value, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * set_name varsa UPDATE, yoksa INSERT yapar.
     * NOTE: settings tablosunda set_name unique değilse, insert duplikasyon üretebilir.
     */
    private function upsertSettingInternal(string $set_name, string $set_value): bool
    {
        $check = $this->db->prepare("SELECT id FROM {$this->table} WHERE set_name = :set_name ORDER BY id DESC LIMIT 1");
        $check->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $check->execute();

        $existingId = $check->fetchColumn();
        if ($existingId) {
            $update = $this->db->prepare("UPDATE {$this->table} SET set_value = :set_value WHERE id = :id");
            $update->bindParam(':set_value', $set_value, PDO::PARAM_STR);
            $update->bindParam(':id', $existingId, PDO::PARAM_INT);
            return $update->execute();
        }

        $insert = $this->db->prepare("INSERT INTO {$this->table} (set_name, set_value) VALUES (:set_name, :set_value)");
        $insert->bindParam(':set_name', $set_name, PDO::PARAM_STR);
        $insert->bindParam(':set_value', $set_value, PDO::PARAM_STR);
        return $insert->execute();
    }

    public function upsertSetting(string $set_name, string $set_value): bool
    {
        try {
            // Dışarıdan tekli çağrılar için transaction burada açılır.
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $ok = $this->upsertSettingInternal($set_name, $set_value);
                $this->db->commit();
                return $ok;
            }

            // Zaten transaction içindeysek sadece işlemi yap.
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
     * Gelen dizi ['set_name' => 'yeni_deger', ...] formatında olmalıdır.
     * @param array $settingsToUpdate Güncellenecek ayarlar
     * @return bool Tüm güncellemeler başarılı olursa true, en az biri başarısız olursa false (veya etkilenen satır sayısı)
     */
    public function updateMultipleSettings(array $settingsToUpdate): bool
    {
        if (empty($settingsToUpdate)) {
            return true; // Güncellenecek bir şey yoksa başarılı say
        }

        // Basit bir döngü ile her birini tek tek güncelleme (performans için iyileştirilebilir)
        // Daha performanslı bir yöntem için CASE WHEN THEN kullanılabilir veya her birini ayrı transaction'da yapmak yerine tek transaction.
        // Ancak bu genellikle yeterlidir ve daha okunaktır.
        $this->db->beginTransaction();
        try {
            foreach ($settingsToUpdate as $setName => $setValue) {
                if (!$this->updateSetting((string)$setName, (string)$setValue)) { // (string) ile tip dönüşümü
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
            // Hata loglama yapılabilir
            // error_log("Ayarlar güncellenirken PDO hatası: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Birden fazla ayarı upsert eder.
     * Gelen dizi ['set_name' => 'deger', ...] formatında olmalıdır.
     */
    public function upsertMultipleSettings(array $settingsToUpdate): bool
    {
        if (empty($settingsToUpdate)) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            foreach ($settingsToUpdate as $setName => $setValue) {
                if (!$this->upsertSettingInternal((string)$setName, (string)$setValue)) {
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