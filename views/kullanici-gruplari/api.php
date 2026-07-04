<?php
require_once "../../vendor/autoload.php";

use App\Model\MenuModel;
use App\Model\UserModel;
use App\Model\UserRolesModel;
use App\Model\PermissionsModel;
use App\Model\UserRolePermissionsModel;
use App\Model\SystemLogModel;
use App\Helper\Security;

$Menus = new MenuModel();
$User = new UserModel();
$UserRoles = new UserRolesModel();
$Permissions = new PermissionsModel();
$UserPermissions = new UserRolePermissionsModel();


/**
 * Yetkileri ve Yetki Gruplarını döndürür.
 * * @return json
 * 
 */
if ($_POST['action'] == 'getPermissions') {

    $id = Security::decrypt($_POST['id']);
    
    $checkRole = $UserRoles->find($id);
    if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }

    // Tüm izinleri gruplanmış olarak al
    $permissionGroups = $Permissions->getGroupedPermissions();

    //Kullanıcı izinlerini al
    $userPermissions = $UserPermissions->getUserPermissions($id);

    // Sonucu bir API olarak döndürmek için:
    header('Content-Type: application/json; charset=utf-8');
    $res = [
        'status' => 'success',
        'id' => $id,
        'data' => [
            'permissions' => $permissionGroups,
            'user_permissions' => $userPermissions
        ]
    ];
    echo json_encode(
        $res,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );
}


// Yetkileri Kaydet
if ($_POST['action'] == 'savePermissions') {

    // Gelen verileri al
    $roleID = Security::decrypt($_POST['id']);
    
    $checkRole = $UserRoles->find($roleID);
    if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }
    $submittedPermissions = json_decode($_POST['permissions']) ?? [];

    // Gelen izinlerin bir dizi olduğundan emin ol
    if (!is_array($submittedPermissions)) {
        $submittedPermissions = [];
    }
    // Gelen değerlerin integer olduğundan emin ol
    $submittedPermissions = array_map('intval', $submittedPermissions);

    // Eğer giriş yapan kullanıcı superadmin değilse, superadmin yetkilerini temizle
    if (!$User->isSuperAdmin()) {
        $db = (new \App\Model\Model())->db;
        $stmt = $db->query("SELECT id FROM permissions WHERE superadmin = 1");
        $superadminPermIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $submittedPermissions = array_diff($submittedPermissions, $superadminPermIds);
    }

    try {
        if ($roleID === 0) {
            throw new Exception("Geçersiz veya eksik Yetki grubu ID'si.");
        }


        // 1. Adım: Düzenlenen kullanıcının bilgilerini ve rolünü al
        $role = $UserRoles->find($roleID);
        if (!$role) {
            throw new Exception("Yetki grubu bulunamadı (ID: {$roleID}).");
        }

        // 2. Adım (Güvenlik): Kullanıcının grubunun/rolünün izin verdiği yetkileri al
        $allowedGroupPermissions = $UserPermissions->getPermissionsForRole($roleID);

        // 3. Adım (Filtreleme): Gelen yetkileri, sadece kullanıcının grubunun izin verdikleriyle sınırla.
        // Bu, birinin formdan fazladan yetki göndermesini engeller.
        $validPermissionsToSync = array_intersect($submittedPermissions, $allowedGroupPermissions);

        // 4. Adım: Modeli çağırarak veritabanını senkronize et
        $UserPermissions->syncUserPermissions($roleID, $validPermissionsToSync);

        //Menu cache'yi temizle
        $Menus->clearMenuCacheForRole($roleID);

        $logModel = new SystemLogModel();
        $logModel->logAction(
            $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0,
            'Yetki Grubu İzin Değişimi',
            "Yetki grubu izinleri güncellendi. Grup: {$role->role_name} (ID: $roleID), " . count($validPermissionsToSync) . " izin atandı.",
            SystemLogModel::LEVEL_CRITICAL
        );

        $status = 'success';
        $message = 'Yetki Grubu izinleri başarıyla güncellendi.';

    } catch (Exception $e) {
        error_log('savePermissions hatası: ' . $e->getMessage());
        $status = "error";
        $message = 'İzin güncelleme sırasında bir hata oluştu.';
    }

    $res = [
        'status' => $status,
        'message' => $message,
        'data' => [
            'role_id' => $roleID,
            'permissions' => $_POST['permissions']
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($res);
}

// Yetki Grubu Kaydet/Güncelle
if ($_POST['action'] == 'saveGroup') {
    $id = $_POST['id'] != "0" ? Security::decrypt($_POST['id']) : 0;
    
    if ($id > 0) {
        $checkRole = $UserRoles->find($id);
        if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
            exit;
        }
    }

    $data = [
        'role_name' => $_POST['role_name'],
        'description' => $_POST['description'],
        'role_color' => $_POST['role_color'] ?? 'secondary',
        'owner_id' => $_SESSION['firma_id'],
        'kayit_tarihi' => date('Y-m-d H:i:s'),
        'kayit_yapan' => $_SESSION['user_id']
    ];

    if ($id > 0) {
        $data['id'] = $id;
    }

    try {
        $res = $UserRoles->saveWithAttr($data);
        $logModel = new SystemLogModel();
        $isNew = ($id == 0);
        $logModel->logAction(
            $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0,
            $isNew ? 'Yetki Grubu Eklendi' : 'Yetki Grubu Güncellendi',
            ($isNew ? 'Yeni yetki grubu eklendi' : 'Yetki grubu güncellendi') . ': ' . ($_POST['role_name'] ?? ''),
            SystemLogModel::LEVEL_CRITICAL
        );
        echo json_encode(['status' => 'success', 'message' => 'Yetki grubu başarıyla kaydedildi.', 'id' => $res]);
    } catch (Exception $e) {
        error_log('saveGroup hatası: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Kayıt sırasında bir hata oluştu.']);
    }
}

// Yetki Grubu Getir
if ($_POST['action'] == 'getGroup') {
    $id = Security::decrypt($_POST['id']);
    
    $checkRole = $UserRoles->find($id);
    if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }
    $group = $UserRoles->find($id);

    if ($group) {
        echo json_encode(['status' => 'success', 'data' => $group]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Grup bulunamadı.']);
    }
}

// Yetki Grubu Sil
if ($_POST['action'] == 'deleteGroup') {
    $id = $_POST['id'];
    
    $decryptedId = Security::decrypt($id);
    $checkRole = $UserRoles->find($decryptedId);
    if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }
    $result = $UserRoles->delete($id);

    if ($result === true) {
        $logModel = new SystemLogModel();
        $logModel->logAction(
            $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0,
            'Yetki Grubu Silindi',
            "Yetki grubu silindi. ID: $id",
            SystemLogModel::LEVEL_CRITICAL
        );
        echo json_encode(['status' => 'success', 'message' => 'Yetki grubu başarıyla silindi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Silme işlemi başarısız oldu.']);
    }
}

// Yetkileri Kopyala
if ($_POST['action'] == 'copyPermissions') {
    $targetRoleID = Security::decrypt($_POST['target_role_id']);
    $sourceRoleID = Security::decrypt($_POST['source_role_id']);
    
    $targetRole = $UserRoles->find($targetRoleID);
    if ($targetRole && $targetRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Hedef yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }
    
    $sourceRole = $UserRoles->find($sourceRoleID);
    if ($sourceRole && $sourceRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Kaynak yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }

    try {
        if (!$targetRoleID || !$sourceRoleID) {
            throw new Exception("Geçersiz rol ID'si.");
        }

        if ($targetRoleID == $sourceRoleID) {
            throw new Exception("Kaynak ve hedef grup aynı olamaz.");
        }

        // Kaynak rolün yetkilerini al
        $sourcePermissions = $UserPermissions->getUserPermissions($sourceRoleID);

        // Hedef role kopyala
        $UserPermissions->syncUserPermissions($targetRoleID, $sourcePermissions);

        // Menu cache temizle
        $Menus->clearMenuCacheForRole($targetRoleID);

        echo json_encode(['status' => 'success', 'message' => 'Yetkiler başarıyla kopyalandı.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Hata: ' . $e->getMessage()]);
    }
}

// Yetki Grubu İzin Özetini Getir
if ($_POST['action'] == 'getPermissionsSummary') {
    $id = Security::decrypt($_POST['id']);
    
    // Check if superadmin role and user is not superadmin
    $checkRole = $UserRoles->find($id);
    if ($checkRole && $checkRole->superadmin == 1 && !$User->isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Bu yetki grubu üzerinde işlem yapma yetkiniz yok.']);
        exit;
    }

    // Bu yetki grubunun sahip olduğu izinlerin id listesini al
    $userPermissions = $UserPermissions->getUserPermissions($id);

    if (empty($userPermissions)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // İzin id listesine göre izin detaylarını al (sadece active olanlar)
    $placeholders = implode(',', array_fill(0, count($userPermissions), '?'));
    
    // Güvenlik: Eğer giriş yapan kullanıcı superadmin değilse, sorguya 'AND superadmin = 0' ekle
    $superadminQuery = "";
    if (!$User->isSuperAdmin()) {
        $superadminQuery = " AND superadmin = 0";
    }

    $db = (new \App\Model\Model())->db;
    $stmt = $db->prepare("SELECT name, description, group_name FROM permissions WHERE is_active = 1 $superadminQuery AND id IN ($placeholders) ORDER BY group_name, name");
    $stmt->execute($userPermissions);
    $permissions = $stmt->fetchAll(PDO::FETCH_OBJ);

    // Gruplara göre eşleştir
    $grouped = [];
    foreach ($permissions as $p) {
        $grouped[$p->group_name][] = [
            'name' => $p->name,
            'description' => $p->description
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'role_name' => $checkRole->role_name,
        'description' => $checkRole->description,
        'data' => $grouped
    ]);
    exit;
}