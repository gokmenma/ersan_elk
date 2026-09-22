<?php 
namespace App\Model;

use App\Model\Model;
use PDO;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class UserRolesModel extends Model
{
    protected $table = 'user_roles';

    public function __construct()
    {
        parent::__construct($this->table);
    }


    /**
     * Veri Sahibine id'sine göre kullanıcı gruplarını listelemek için gerekli verileri getirir.
     * @param int $owner_id Verinin sahibi ID'si(Session ID'si gibi)
     * @return array
     */
    public function getUserGroups(): array
    {
        $ownerID = $_SESSION["owner_id"];
        
        $currentUser = \App\Controllers\AuthController::user();
        $currentUserRole = $currentUser->role ?? 'user';

        $roleTypeFilter = "";
        if ($currentUserRole === 'admin') {
            $roleTypeFilter = " AND role_type != 'superadmin'";
        } elseif ($currentUserRole === 'user') {
            $roleTypeFilter = " AND role_type = 'user'";
        }

        $sql = $this->db->prepare("SELECT * 
                                   FROM $this->table 
                                   WHERE owner_id = :owner_id $roleTypeFilter 
                                   ORDER BY id DESC");
        $sql->execute([
            'owner_id' => $ownerID
        ]);

        return $sql->fetchAll(PDO::FETCH_OBJ) ?? [];
    }

    /**
     * Kullanıcı gruplarını seçenekler olarak döndürür.
     * @return array
     */
    public function getGroupsOptions(): array
    {
        $groups = $this->getUserGroups();
        $options = [];
        $UserModel = new UserModel();

        foreach ($groups as $group) {
            /**Sessindaki kullanıcı süperadmin ise süperadmin kullanıcısı atla*/
            if($group->superadmin == 1 && !$UserModel->isSuperAdmin()) {
                continue;
            }
            $options[$group->id] = (object)[
                'id' => $group->id,
                'role_name' => $group->role_name
            ];
        }

        return $options;
    }

    /**
     * Rol ve Yetki Matrisi için tüm rolleri, atanan kullanıcıları ve izin dağılımlarını getirir.
     * @return array
     */
    public function getRolesWithDetails(): array
    {
        $ownerID = $_SESSION["owner_id"] ?? 1;
        $currentUser = \App\Controllers\AuthController::user();
        $currentUserRole = $currentUser->role ?? 'user';

        $roleTypeFilter = "";
        if ($currentUserRole === 'admin') {
            $roleTypeFilter = " AND ur.role_type != 'superadmin'";
        } elseif ($currentUserRole === 'user') {
            $roleTypeFilter = " AND ur.role_type = 'user'";
        }

        $UserModel = new UserModel();
        $superadminQuery = "";
        if (!$UserModel->isSuperAdmin()) {
            $superadminQuery = " AND p.superadmin = 0";
        }

        $totalPermQuery = $this->db->query("SELECT COUNT(*) FROM permissions p WHERE p.is_active = 1 $superadminQuery");
        $totalPermissions = (int) $totalPermQuery->fetchColumn();

        $sql = $this->db->prepare("SELECT ur.*,
            (SELECT COUNT(DISTINCT u.id) FROM users u WHERE FIND_IN_SET(CAST(ur.id AS CHAR), u.roles) > 0) as assigned_user_count,
            (SELECT COUNT(DISTINCT urp.permission_id) FROM user_role_permissions urp JOIN permissions p ON p.id = urp.permission_id WHERE urp.role_id = ur.id AND p.is_active = 1 $superadminQuery) as permission_count
            FROM {$this->table} ur
            WHERE ur.owner_id = :owner_id $roleTypeFilter
            ORDER BY ur.id DESC");
        $sql->execute(['owner_id' => $ownerID]);
        $roles = $sql->fetchAll(PDO::FETCH_OBJ) ?? [];

        foreach ($roles as &$role) {
            $role->total_permissions = $totalPermissions;
            $role->permission_percent = $totalPermissions > 0 ? round(($role->permission_count / $totalPermissions) * 100) : 0;

            // Örnek ilk 3 izin adı
            $sampleStmt = $this->db->prepare("SELECT p.name 
                FROM user_role_permissions urp 
                JOIN permissions p ON p.id = urp.permission_id 
                WHERE urp.role_id = ? AND p.is_active = 1 $superadminQuery 
                ORDER BY p.id ASC LIMIT 3");
            $sampleStmt->execute([$role->id]);
            $role->sample_permissions = $sampleStmt->fetchAll(PDO::FETCH_COLUMN) ?? [];

            // Atanan kullanıcıların detayları
            $usersStmt = $this->db->prepare("SELECT id, adi_soyadi, user_name, gorevi, durum 
                FROM users 
                WHERE FIND_IN_SET(?, roles) > 0 
                ORDER BY durum ASC, adi_soyadi ASC");
            $usersStmt->execute([(string)$role->id]);
            $role->assigned_users = $usersStmt->fetchAll(PDO::FETCH_OBJ) ?? [];
        }

        return $roles;
    }
}

?>