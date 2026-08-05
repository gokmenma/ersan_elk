<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\AparatTipiModel;
use App\Helper\Security;
use App\Service\Gate;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('aparat_takip') && !Gate::allows('aparat-takip/list') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

$idInput = $_GET['id'] ?? 0;
$tipId = is_numeric($idInput) ? (int) $idInput : (int) Security::decrypt($idInput);

if ($tipId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$TipModel = new AparatTipiModel();
$tip = $TipModel->getir($tipId);

if (!$tip || empty($tip['resim'])) {
    http_response_code(404);
    exit('Görsel bulunamadı.');
}

$dosyaYolu = dirname(__DIR__, 2) . '/files/aparat_tipleri/' . $tip['resim'];

if (!is_file($dosyaYolu)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

try {
    $sifreliVeri = file_get_contents($dosyaYolu);
    $binaryData = Security::decryptFile($sifreliVeri);
} catch (\Throwable $e) {
    error_log('Aparat tipi resmi deşifre edilemedi: ' . $e->getMessage());
    http_response_code(500);
    exit('Görsel görüntülenemedi.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($binaryData) ?: 'image/jpeg';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($binaryData));
header('Content-Disposition: inline; filename="aparat_tip_' . $tipId . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

echo $binaryData;
exit;
