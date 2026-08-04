<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\KesmeAcmaIslemModel;
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

$fotoId = (int) ($_GET['id'] ?? 0);
if ($fotoId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$Islem = new KesmeAcmaIslemModel();
$foto = $Islem->fotografGetir($fotoId);

if (!$foto) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$dosya = KesmeAcmaIslemModel::rootPath() . '/' . ltrim($foto['dosya_yolu'], '/');

if (!is_file($dosya)) {
    http_response_code(404);
    exit(!empty($foto['arsivlendi']) ? 'Bu dosya arşivlenmiş ve sunucudan silinmiştir.' : 'Dosya bulunamadı.');
}

$uzanti = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . ($mimeMap[$uzanti] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($dosya));
header('Content-Disposition: inline; filename="' . basename($dosya) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');

readfile($dosya);
exit;
