<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Security;
use App\Model\MenuModel;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$menuModel = new MenuModel();

if ($userId <= 0 || $firmaId <= 0 || !$menuModel->userCanAccessMenuLink($userId, 'bordro/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bordro işlemi için yetkiniz bulunmuyor.']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$allowedActions = ['get-detail', 'maas-hesapla', 'donem-kapat', 'donem-ac', 'odeme-dagit', 'odeme-reset', 'personel-gelir-ekle', 'personel-kesinti-ekle'];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Mobil bordro için geçersiz işlem.']);
    exit;
}

$decryptId = static function ($value): int {
    if (!is_string($value) || $value === '') {
        return 0;
    }
    try {
        return (int) Security::decrypt($value);
    } catch (Throwable $e) {
        return 0;
    }
};

if (isset($_POST['donem_token'])) {
    $_POST['donem_id'] = $decryptId($_POST['donem_token']);
}
if (isset($_POST['bordro_token'])) {
    $_POST['id'] = $decryptId($_POST['bordro_token']);
}
if (isset($_POST['personel_token'])) {
    $_POST['personel_id'] = $decryptId($_POST['personel_token']);
}
if (isset($_POST['personel_tokens']) && is_array($_POST['personel_tokens'])) {
    $_POST['personel_ids'] = array_values(array_filter(array_map($decryptId, $_POST['personel_tokens'])));
}

$db = (new \App\Core\Db())->getConnection();
if (!empty($_POST['donem_id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['donem_id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu bordro dönemine erişiminiz bulunmuyor.']);
        exit;
    }
}
if (!empty($_POST['id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM bordro_personel bp INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id WHERE bp.id = ? AND bd.firma_id = ? AND bp.silinme_tarihi IS NULL AND bd.silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu bordro kaydına erişiminiz bulunmuyor.']);
        exit;
    }
}
if (!empty($_POST['personel_ids'])) {
    $ids = array_map('intval', (array) $_POST['personel_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM bordro_personel bp INNER JOIN bordro_donemi bd ON bd.id = bp.donem_id WHERE bp.id IN ($placeholders) AND bp.donem_id = ? AND bd.firma_id = ? AND bp.silinme_tarihi IS NULL");
    $stmt->execute(array_merge($ids, [(int) ($_POST['donem_id'] ?? 0), $firmaId]));
    if ((int) $stmt->fetchColumn() !== count($ids)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Personel bordro seçimi geçersiz.']);
        exit;
    }
}
if (!empty($_POST['personel_id'])) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM personel WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
    $stmt->execute([(int) $_POST['personel_id'], $firmaId]);
    if (!(bool) $stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Bu personele erişiminiz bulunmuyor.']);
        exit;
    }
}

// Kapalı dönemlerde yalnız görüntüleme ve dönemi yeniden açma işlemi yapılabilir.
if (!in_array($action, ['get-detail', 'donem-ac', 'donem-kapat'], true)) {
    $targetPeriodId = (int) ($_POST['donem_id'] ?? 0);
    if ($targetPeriodId === 0 && !empty($_POST['id'])) {
        $stmt = $db->prepare('SELECT donem_id FROM bordro_personel WHERE id = ? AND silinme_tarihi IS NULL');
        $stmt->execute([(int) $_POST['id']]);
        $targetPeriodId = (int) $stmt->fetchColumn();
    }
    $stmt = $db->prepare('SELECT kapali_mi FROM bordro_donemi WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL');
    $stmt->execute([$targetPeriodId, $firmaId]);
    if ((int) $stmt->fetchColumn() === 1) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Kapalı bordro döneminde değişiklik yapılamaz.']);
        exit;
    }
}

require dirname(__DIR__, 2) . '/views/bordro/api.php';
