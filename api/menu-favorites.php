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

$menuModel = new MenuModel();
$action = $_REQUEST['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : 'toggle');

function formatFavoritesArray(array $favorites): array {
    return array_map(function($fav) {
        $title = $fav->menu_name ?? $fav->title ?? '';
        $link = $fav->menu_link ?? $fav->link ?? '#';
        $icon = $fav->menu_icon ?? $fav->icon ?? 'circle';
        
        return [
            'id' => (int) $fav->id,
            'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            'link' => htmlspecialchars($link, ENT_QUOTES, 'UTF-8'),
            'icon' => htmlspecialchars(!empty($icon) ? $icon : 'circle', ENT_QUOTES, 'UTF-8')
        ];
    }, $favorites);
}

if ($action === 'list') {
    $favorites = $menuModel->getFavoriteMenus($userId);
    $favoriteIds = $menuModel->getFavoriteMenuIds($userId);

    echo json_encode([
        'success' => true,
        'favorites' => formatFavoritesArray($favorites),
        'favorite_ids' => $favoriteIds
    ]);
    exit;
}

$menuId = (int) ($_POST['menu_id'] ?? $_GET['menu_id'] ?? 0);

if ($menuId === 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz menü ID.']);
    exit;
}

$success = $menuModel->toggleFavorite($userId, $menuId);

if ($success) {
    $favorites = $menuModel->getFavoriteMenus($userId);
    $favoriteIds = $menuModel->getFavoriteMenuIds($userId);
    $isFavorited = in_array($menuId, $favoriteIds, true);

    echo json_encode([
        'success' => true,
        'is_favorited' => $isFavorited,
        'message' => $isFavorited ? 'Sık kullanılanlara eklendi.' : 'Sık kullanılanlardan çıkarıldı.',
        'favorites' => formatFavoritesArray($favorites),
        'favorite_ids' => $favoriteIds
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'İşlem sırasında bir hata oluştu.']);
}
