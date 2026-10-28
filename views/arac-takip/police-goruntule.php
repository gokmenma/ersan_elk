<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracPoliceModel;
use App\Model\SystemLogModel;
use App\Helper\Security;
use App\Service\Gate;

if (!Gate::allows('arac_takip_yonetim')) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz bulunmamaktadır.');
}

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
    exit('Geçersiz poliçe ID.');
}

$Police = new AracPoliceModel();
$police = $Police->getById((int) $id);

if (!$police || empty($police->dosya_adi)) {
    http_response_code(404);
    exit('Poliçe dosyası bulunamadı.');
}

$sifreliYol = dirname(__DIR__, 2) . '/files/arac_police/' . (int) $police->arac_id . '/' . basename($police->dosya_adi);

if (!is_file($sifreliYol)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

try {
    $binaryData = Security::decryptFile(file_get_contents($sifreliYol));
} catch (\Throwable $e) {
    error_log('Poliçe dosyası çözülemedi: ' . $e->getMessage());
    http_response_code(500);
    exit('Dosya görüntülenemedi.');
}

$SystemLog = new SystemLogModel();
$SystemLog->logAction($_SESSION['user_id'] ?? 0, 'Araç Poliçe Görüntüleme', "Araç (#{$police->arac_id}) {$police->police_turu} poliçe dosyası görüntülendi.", SystemLogModel::LEVEL_INFO);

header('Content-Type: ' . $police->mime_tipi);
header('Content-Disposition: inline; filename="' . rawurlencode($police->orijinal_ad ?? 'police') . '"');
header('Content-Length: ' . strlen($binaryData));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $binaryData;
exit;
