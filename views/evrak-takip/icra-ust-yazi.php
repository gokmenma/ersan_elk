<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\MenuModel;
use App\Model\SystemLogModel;
use App\Service\IcraUstYaziService;

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

$menuModel = new MenuModel();
if (!$menuModel->userCanAccessMenuLink($userId, 'evrak-takip/giden-evrak')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Bu işlem için yetkiniz yok.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new IcraUstYaziService();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'icra-listesi') {
        $personelId = (int) ($_POST['personel_id'] ?? 0);
        echo json_encode(['status' => 'success', 'data' => $service->personelIcralari($personelId)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'taslak') {
        $icraId = (int) Security::decrypt((string) ($_POST['icra_id'] ?? ''));
        $draft = $service->build($icraId);
        (new SystemLogModel())->logAction(
            $userId,
            'İcra Üst Yazısı Oluşturma',
            "İcra ID: {$icraId}, Personel: {$draft['personel_adi']}",
            SystemLogModel::LEVEL_INFO
        );
        echo json_encode(['status' => 'success', 'data' => $draft], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new InvalidArgumentException('Geçersiz işlem.');
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('İcra üst yazı hatası: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Üst yazı oluşturulamadı. Lütfen tekrar deneyiniz.'], JSON_UNESCAPED_UNICODE);
}
