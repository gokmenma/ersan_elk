<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Security;
use App\Service\BordroMuhasebeExcelKarsilastirmaService;
use App\Service\Gate;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new DomainException('Yalnızca POST isteği kabul edilir.');
    }
    if (empty($_SESSION['user']->id) || empty($_SESSION['firma_id']) || !Gate::allows('bordro/raporlar')) {
        http_response_code(403);
        throw new DomainException('Muhasebe karşılaştırması için yetkiniz bulunmamaktadır.');
    }
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        http_response_code(419);
        throw new DomainException('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
    }
    $donemId = (int) Security::decrypt((string) ($_POST['donem'] ?? ''));
    if ($donemId <= 0) throw new DomainException('Geçersiz dönem seçimi.');
    $result = (new BordroMuhasebeExcelKarsilastirmaService())->karsilastir(
        $_FILES['muhasebe_excel'] ?? [],
        (int) $_SESSION['firma_id'],
        $donemId
    );
    echo json_encode(['status' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Bordro muhasebe Excel karşılaştırma: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Excel karşılaştırması tamamlanamadı.'], JSON_UNESCAPED_UNICODE);
}
