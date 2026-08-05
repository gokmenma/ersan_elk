<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\MenuModel;
use App\Service\EvrakAiTaslakService;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if (empty($_SESSION['loggedin']) || empty($_SESSION['firma_id']) || $userId < 1) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!(new MenuModel())->userCanAccessMenuLink($userId, 'evrak-takip/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $draft = (new EvrakAiTaslakService())->create($_FILES['gelen_evrak'] ?? [], (string) ($_POST['talimat'] ?? ''));
    echo json_encode(['status' => 'success', 'data' => $draft], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
