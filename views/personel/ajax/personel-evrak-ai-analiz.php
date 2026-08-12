<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\SystemLogModel;
use App\Service\Gate;
use App\Service\PersonelEvrakAiService;

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
if (!Gate::allows('personel_duzenle')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sonuc = (new PersonelEvrakAiService())->analiz($_FILES['personel_belgeleri'] ?? []);
    (new SystemLogModel())->logAction(
        $userId,
        'Personel Evrakları Yapay Zekâ Analizi',
        'Personel ID: ' . (int) ($_POST['personel_id'] ?? 0) . ', Belge sayısı: ' . count($_FILES['personel_belgeleri']['name'] ?? []),
        SystemLogModel::LEVEL_INFO
    );
    echo json_encode(['status' => 'success', 'data' => $sonuc], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    error_log('Personel evrakları AI analiz hatası: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
