<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracModel;
use App\Model\SystemLogModel;
use App\Helper\Security;

$id = $_GET['id'] ?? '';
if (empty($id)) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

if (!is_numeric($id)) {
    $id = Security::decrypt($id);
}

if (empty($id) || !is_numeric($id)) {
    http_response_code(400);
    exit('Geçersiz araç ID.');
}

$Arac = new AracModel();
$arac = $Arac->getRuhsatSahibiArac((int) $id);

if (!$arac || empty($arac->ruhsat_dosya_adi)) {
    http_response_code(404);
    exit('Ruhsat görseli bulunamadı.');
}

$sifreliYol = dirname(__DIR__, 2) . '/files/arac_ruhsat/' . $arac->id . '/' . $arac->ruhsat_dosya_adi;

if (!is_file($sifreliYol)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

try {
    $sifreliVeri = file_get_contents($sifreliYol);
    $binaryData = Security::decryptFile($sifreliVeri);
} catch (\Throwable $e) {
    error_log('Ruhsat görseli çözülemedi: ' . $e->getMessage());
    http_response_code(500);
    exit('Görsel görüntülenemedi.');
}

$kullaniciId = $_SESSION['user_id'] ?? 0;
$SystemLog = new SystemLogModel();
$SystemLog->logAction($kullaniciId, 'Araç Ruhsat Görüntüleme', "{$arac->plaka} plakalı araca ait ruhsat görseli görüntülendi.", SystemLogModel::LEVEL_INFO);

header('Content-Type: ' . $arac->ruhsat_mime_tipi);
header('Content-Disposition: inline; filename="' . rawurlencode($arac->ruhsat_orijinal_ad ?? 'ruhsat') . '"');
header('Content-Length: ' . strlen($binaryData));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $binaryData;
exit;
