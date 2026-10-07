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
            0 as assigned_user_count,
            (SELECT COUNT(DISTINCT urp.permission_id) FROM user_role_permissions urp JOIN permissions p ON p.id = urp.permission_id WHERE urp.role_id = ur.id AND p.is_active = 1 $superadminQuery) as permission_count
            FROM {$this->table} ur
            WHERE ur.owner_id = :owner_id $roleTypeFilter
            ORDER BY ur.id DESC");
        $sql->execute(['owner_id' => $ownerID]);
        $roles = $sql->fetchAll(PDO::FETCH_OBJ) ?? [];

        $assignmentModel = new UserRoleAssignmentModel();
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
            $role->assigned_users = $assignmentModel->activeUsersForRole((int) $role->id);
            $role->assigned_user_count = count($role->assigned_users);
        }

        return $roles;
    }

    /**
     * Yetki adı, açıklaması, kodu veya modülüne göre eşleşen yetkilerin
     * erişilebilir rol gruplarındaki açık/kapalı durumunu döndürür.
     */
    public function searchPermissionRoleMatrix(string $search): array
    {
        $search = trim($search);
        if (mb_strlen($search, 'UTF-8') < 2) {
            return [];
        }

        $ownerID = (int) ($_SESSION['owner_id'] ?? 1);
        $currentUser = \App\Controllers\AuthController::user();
        $currentUserRole = $currentUser->role ?? 'user';

        $roleTypeFilter = '';
        if ($currentUserRole === 'admin') {
            $roleTypeFilter = " AND ur.role_type != 'superadmin'";
        } elseif ($currentUserRole === 'user') {
            $roleTypeFilter = " AND ur.role_type = 'user'";
        }

        $UserModel = new UserModel();
        $superadminFilter = $UserModel->isSuperAdmin() ? '' : ' AND p.superadmin = 0';
        $like = '%' . $search . '%';

        $sql = "SELECT DISTINCT p.id AS permission_id, p.name AS permission_name,
                       p.auth_name, p.description AS permission_description,
                       p.group_name, p.is_required,
                       m.menu_name, m.menu_link, pm.menu_name AS parent_menu_name,
                       ur.id AS role_id, ur.role_name, ur.role_color,
                       CASE WHEN urp.permission_id IS NULL THEN 0 ELSE 1 END AS is_enabled
                FROM permissions p
                LEFT JOIN menus m ON (
                    p.auth_name = m.menu_link
                    OR p.name = m.menu_link
                    OR (p.id = m.id AND m.id <= 944)
                ) AND m.is_active = 1
                LEFT JOIN menus pm ON m.parent_id = pm.id
                INNER JOIN user_roles ur
                    ON ur.owner_id = :owner_id {$roleTypeFilter}
                LEFT JOIN user_role_permissions urp
                    ON urp.permission_id = p.id AND urp.role_id = ur.id
                WHERE p.is_active = 1 {$superadminFilter}
                  AND (p.name LIKE :name_search
                       OR p.auth_name LIKE :auth_search
                       OR p.description LIKE :description_search
                       OR p.group_name LIKE :group_search
                       OR m.menu_name LIKE :menu_search
                       OR m.menu_link LIKE :menulink_search)
                ORDER BY p.group_name, p.name, ur.role_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'owner_id' => $ownerID,
            'name_search' => $like,
            'auth_search' => $like,
            'description_search' => $like,
            'group_search' => $like,
            'menu_search' => $like,
            'menulink_search' => $like,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        $permissions = [];
        foreach ($rows as $row) {
            $permissionId = (int) $row->permission_id;
            if (!isset($permissions[$permissionId])) {
                $menuInfo = null;
                if (!empty($row->menu_name)) {
                    $menuInfo = (!empty($row->parent_menu_name) ? $row->parent_menu_name . ' > ' : '') . $row->menu_name;
                    if (!empty($row->menu_link)) {
                        $menuInfo .= ' (' . $row->menu_link . ')';
                    }
                }

                $permissions[$permissionId] = [
                    'id' => $permissionId,
                    'name' => $row->permission_name,
                    'auth_name' => $row->auth_name,
                    'menu_info' => $menuInfo,
                    'description' => $row->permission_description,
                    'group_name' => $row->group_name,
                    'required' => (bool) $row->is_required,
                    'roles' => [],
                ];
            }

            $permissions[$permissionId]['roles'][] = [
                'id' => (int) $row->role_id,
                'name' => $row->role_name,
                'color' => $row->role_color ?: 'secondary',
                'enabled' => (bool) $row->is_enabled,
            ];
        }

        return array_values($permissions);
    }
}

?>
