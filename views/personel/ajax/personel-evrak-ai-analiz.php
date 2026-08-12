<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Model\SystemLogModel;
use App\Helper\Security;
use App\Service\Gate;
use App\Service\PersonelEvrakAiService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

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

$csrf = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $csrf === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyiniz.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$forwardedProto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
$httpsAktif = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$yerelIstek = str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1') || str_starts_with($host, '[::1]');
$httpsZorunlu = filter_var($_ENV['OCR_REQUIRE_HTTPS'] ?? false, FILTER_VALIDATE_BOOL);
if ($httpsZorunlu && !$httpsAktif && !$yerelIstek) {
    http_response_code(426);
    echo json_encode(['status' => 'error', 'message' => 'Belge aktarımı yalnızca güvenli HTTPS bağlantısı üzerinden yapılabilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Oturum ve kullanıcı bazında dakikada en fazla 5 OCR isteği.
$simdi = time();
$ocrIstekleri = array_values(array_filter(
    is_array($_SESSION['personel_ocr_rate'] ?? null) ? $_SESSION['personel_ocr_rate'] : [],
    static fn($zaman) => is_int($zaman) && $zaman > $simdi - 60
));
if (count($ocrIstekleri) >= 5) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Çok fazla OCR isteği gönderildi. Lütfen bir dakika bekleyiniz.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$ocrIstekleri[] = $simdi;
$_SESSION['personel_ocr_rate'] = $ocrIstekleri;

try {
    $sonuc = (new PersonelEvrakAiService())->analiz($_FILES['personel_belgeleri'] ?? []);
    (new SystemLogModel())->logAction(
        $userId,
        'Personel Evrakları Yerel OCR İşlemi',
        'Personel ID: ' . (int) ($_POST['personel_id'] ?? 0) . ', Belge sayısı: ' . count($_FILES['personel_belgeleri']['name'] ?? []),
        SystemLogModel::LEVEL_INFO
    );
    echo json_encode(['status' => 'success', 'data' => $sonuc], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    error_log('Personel evrakları yerel OCR hatası: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
