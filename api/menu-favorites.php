<?php
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\MenuModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Lütfen giriş yapın.']);
    exit;
}

$menuId = (int) ($_POST['menu_id'] ?? 0);

if ($menuId === 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz menü ID.']);
    exit;
}

$menuModel = new MenuModel();
$success = $menuModel->toggleFavorite($userId, $menuId);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Favori durumu güncellendi.']);
} else {
    echo json_encode(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.']);
}
