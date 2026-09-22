<?php
require_once dirname(__DIR__) . '/Autoloader.php';

use App\Model\MenuModel;

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId === 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Oturum süreniz dolmuş veya giriş yapılmamış.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$menuModel = new MenuModel();
$action = $_REQUEST['action'] ?? 'save';

if ($action === 'reset') {
    $result = $menuModel->resetUserMenuOrder($userId);
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Menü sırası başarıyla varsayılan haline döndürüldü.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Menü sırası sıfırlanırken bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'save') {
    // POST üzerinden gelen raw json veya form datayı oku
    $rawInput = file_get_contents('php://input');
    $payload = [];

    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if (empty($payload)) {
        $payload = $_POST;
    }

    $groups = $payload['groups'] ?? [];
    $menus = $payload['menus'] ?? [];
    $submenus = $payload['submenus'] ?? [];

    if (!is_array($groups) || !is_array($menus) || !is_array($submenus)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Geçersiz veri formatı.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Grupları ve menü ID'lerini temizle/sanitize et
    $cleanGroups = [];
    foreach ($groups as $g) {
        $g = trim((string)$g);
        if ($g !== '') {
            $cleanGroups[] = $g;
        }
    }

    $cleanMenus = [];
    foreach ($menus as $grp => $menuIds) {
        $grpKey = trim((string)$grp);
        if ($grpKey !== '' && is_array($menuIds)) {
            $cleanMenus[$grpKey] = array_values(array_filter(array_map('intval', $menuIds), function($id) {
                return $id > 0;
            }));
        }
    }

    $cleanSubmenus = [];
    foreach ($submenus as $parentId => $subIds) {
        $pId = (int)$parentId;
        if ($pId > 0 && is_array($subIds)) {
            $cleanSubmenus[$pId] = array_values(array_filter(array_map('intval', $subIds), function($id) {
                return $id > 0;
            }));
        }
    }

    $orderData = [
        'groups' => $cleanGroups,
        'menus' => $cleanMenus,
        'submenus' => $cleanSubmenus
    ];

    $result = $menuModel->saveUserMenuOrder($userId, $orderData);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Menü sırası kaydedildi.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Menü sırası kaydedilirken bir hata oluştu.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Geçersiz işlem.'
], JSON_UNESCAPED_UNICODE);
