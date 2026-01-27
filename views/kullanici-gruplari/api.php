<?php
require_once "../../vendor/autoload.php";

use App\Model\MenuModel;
use App\Model\UserModel;
use App\Model\UserRolesModel;
use App\Model\PermissionsModel;
use App\Model\UserRolePermissionsModel;

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
    $submittedPermissions = json_decode($_POST['permissions']) ?? [];

    // Gelen izinlerin bir dizi olduğundan emin ol
    if (!is_array($submittedPermissions)) {
        $submittedPermissions = [];
    }
    // Gelen değerlerin integer olduğundan emin ol
    $submittedPermissions = array_map('intval', $submittedPermissions);

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

        $status = 'success';
        $message = 'Yetki Grubu izinleri başarıyla güncellendi.';

    } catch (Exception $e) {
        $status = "error";
        $message = 'Bir hata oluştu: ' . $e->getMessage();
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
        echo json_encode(['status' => 'success', 'message' => 'Yetki grubu başarıyla kaydedildi.', 'id' => $res]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Hata: ' . $e->getMessage()]);
    }
}

// Yetki Grubu Getir
if ($_POST['action'] == 'getGroup') {
    $id = Security::decrypt($_POST['id']);
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
    $result = $UserRoles->delete($id);

    if ($result === true) {
        echo json_encode(['status' => 'success', 'message' => 'Yetki grubu başarıyla silindi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hata: ' . $result->getMessage()]);
    }
}

// Yetkileri Kopyala
if ($_POST['action'] == 'copyPermissions') {
    $targetRoleID = Security::decrypt($_POST['target_role_id']);
    $sourceRoleID = Security::decrypt($_POST['source_role_id']);

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