<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\MenuModel;
use App\Model\SystemLogModel;
use App\Service\IcraEvrakAiService;

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
if (!(new MenuModel())->userCanAccessMenuLink($userId, 'personel/list')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sonuc = (new IcraEvrakAiService())->analiz($_FILES['icra_belgesi'] ?? []);
    (new SystemLogModel())->logAction(
        $userId,
        'İcra Belgesi Yapay Zekâ Analizi',
        'Personel ID: ' . (int) ($_POST['personel_id'] ?? 0) . ', Dosya No: ' . ($sonuc['dosya_no'] ?: '-'),
        SystemLogModel::LEVEL_INFO
    );
    echo json_encode(['status' => 'success', 'data' => $sonuc], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    error_log('İcra belgesi AI analiz hatası: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
