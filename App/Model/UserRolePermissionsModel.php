<?php 


namespace App\Model;

use App\Model\Model;
use PDO;

class UserRolePermissionsModel extends Model
{
    protected $table = 'user_role_permissions'; // Kullanıcıların izinlerini tutan tablo (user_id, permission_id)

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirtilen kullanıcının sahip olduğu tüm izinlerin ID'lerini bir dizi olarak döndürür.
     * @param int $userId
     * @return array [101, 102, 203] gibi bir dizi.
     */
    public function getPermissionsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT permission_id FROM {$this->table} WHERE role_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Belirtilen rolün/grubun sahip olabileceği tüm izinlerin ID'lerini bir dizi olarak döndürür.
     * @param int $roleId
     * @return array
     */
    public function getPermissionsForRole(int $roleId): array
    {
        // 'role_permissions' adında bir bağlantı tablonuz olduğunu varsayıyoruz.
        $stmt = $this->db->prepare("SELECT id FROM permissions");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Bir kullanıcının izinlerini, verilen yeni listeyle senkronize eder.
     * Olmayanları ekler, artık listede olmayanları siler.
     * @param int $userId
     * @param array $finalPermissionIds Senkronize edilecek son izin ID'leri listesi.
     * @return bool
     */
    public function syncUserPermissions(int $userId, array $finalPermissionIds): bool
    {
        $currentPermissions = $this->getPermissionsForUser($userId);

        // Set (küme) operasyonları için array_diff kullanmak en verimli yoldur.
        $permissionsToAdd = array_diff($finalPermissionIds, $currentPermissions);
        $permissionsToDelete = array_diff($currentPermissions, $finalPermissionIds);

        try {
            // Veri tutarlılığı için transaction başlat.
            $this->db->beginTransaction();

            // 1. Adım: Silinecek izinleri sil
            if (!empty($permissionsToDelete)) {
                $placeholders = implode(',', array_fill(0, count($permissionsToDelete), '?'));
                $stmt = $this->db->prepare(
                    "DELETE FROM {$this->table} WHERE role_id = ? AND permission_id IN ({$placeholders})"
                );
                // execute() için parametreleri birleştir: [userId, perm1, perm2, ...]
                $params = array_merge([$userId], array_values($permissionsToDelete));
                $stmt->execute($params);
            }

            // 2. Adım: Eklenecek izinleri ekle
            if (!empty($permissionsToAdd)) {
                $stmt = $this->db->prepare(
                    "INSERT INTO {$this->table} (role_id, permission_id) VALUES (?, ?)"
                );
                foreach ($permissionsToAdd as $permissionId) {
                    $stmt->execute([$userId, $permissionId]);
                }
            }

            // Her şey yolundaysa, değişiklikleri onayla.
            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            // Bir hata olursa, tüm değişiklikleri geri al.
            $this->db->rollBack();
            // Hatayı log'layabilir veya yeniden fırlatabilirsiniz.
            throw $e; 
        }
    }

    /** Kullanıcı Grubunun yetkilerini getirir
     * @param int $userId Kullanıcı ID'si
     * @return array
     */

    public function getUserPermissions(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT permission_id FROM {$this->table} WHERE role_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>