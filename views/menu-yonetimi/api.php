<?php
require_once dirname(__DIR__, 2) . "/vendor/autoload.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Service\Gate;
use App\Model\MenuManagementModel;
use App\Helper\Security;
use App\Controllers\AuthController;

header('Content-Type: application/json; charset=utf-8');

// Strictly enforce SuperAdmin permission
if (!Gate::isSuperAdmin()) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bu işlemi gerçekleştirmek için Superadmin yetkisine sahip olmalısınız.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = AuthController::user();
$currentUserId = (int) ($currentUser->id ?? $_SESSION['user_id'] ?? 0);

$action = $_REQUEST['action'] ?? '';
$model = new MenuManagementModel();

try {
    switch ($action) {
        case 'fetch_list':
            $includeDeleted = !empty($_GET['include_deleted']) && $_GET['include_deleted'] == '1';
            $menus = $model->getAllMenus($includeDeleted);
            echo json_encode([
                'status' => 'success',
                'data' => $menus
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_parents':
            $excludeEncId = $_GET['exclude_id'] ?? '';
            $excludeId = !empty($excludeEncId) ? (int) Security::decrypt($excludeEncId) : null;
            $parents = $model->getParentMenus($excludeId);
            echo json_encode([
                'status' => 'success',
                'data' => $parents
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_groups':
            $groups = $model->getGroupNames();
            echo json_encode([
                'status' => 'success',
                'data' => $groups
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'get_detail':
            $encId = $_REQUEST['id'] ?? '';
            $id = (int) Security::decrypt($encId);
            if ($id <= 0) {
                throw new Exception("Geçersiz menü ID'si.");
            }
            $menu = $model->getMenuById($id);
            if (!$menu) {
                throw new Exception("Menü bulunamadı.");
            }
            echo json_encode([
                'status' => 'success',
                'data' => $menu
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'save':
            $encId = $_POST['id'] ?? '';
            $id = 0;
            if (!empty($encId)) {
                $id = (int) Security::decrypt($encId);
            }

            $menuName = trim($_POST['menu_name'] ?? '');
            if (empty($menuName)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Menü adı alanı doldurulmalıdır.'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $data = [
                'id' => $id,
                'menu_name' => $menuName,
                'page_description' => trim($_POST['page_description'] ?? ''),
                'parent_id' => (int) ($_POST['parent_id'] ?? 0),
                'group_name' => trim($_POST['group_name'] ?? 'Yönetim'),
                'group_order' => (int) ($_POST['group_order'] ?? 1),
                'menu_link' => trim($_POST['menu_link'] ?? ''),
                'menu_icon' => trim($_POST['menu_icon'] ?? ''),
                'menu_order' => (int) ($_POST['menu_order'] ?? 1),
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
                'is_menu' => isset($_POST['is_menu']) ? (int) $_POST['is_menu'] : 1,
                'is_authorized' => isset($_POST['is_authorized']) ? (int) $_POST['is_authorized'] : 1,
            ];

            $result = $model->saveMenuData($data, $currentUserId);
            echo json_encode([
                'status' => 'success',
                'message' => ($id > 0) ? 'Menü başarıyla güncellendi.' : 'Yeni menü başarıyla eklendi.',
                'data' => $result
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'soft_delete':
            $encId = $_POST['id'] ?? '';
            $id = (int) Security::decrypt($encId);
            if ($id <= 0) {
                throw new Exception("Geçersiz menü ID'si.");
            }

            $result = $model->softDeleteMenu($id, $currentUserId);
            echo json_encode([
                'status' => 'success',
                'message' => 'Menü başarıyla silindi (soft delete).'
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'restore':
            $encId = $_POST['id'] ?? '';
            $id = (int) Security::decrypt($encId);
            if ($id <= 0) {
                throw new Exception("Geçersiz menü ID'si.");
            }

            $result = $model->restoreMenu($id, $currentUserId);
            echo json_encode([
                'status' => 'success',
                'message' => 'Silinmiş menü başarıyla geri yüklendi.'
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Geçersiz işlem isteği.'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    error_log("views/menu-yonetimi/api.php Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
