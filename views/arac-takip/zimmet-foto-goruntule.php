<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\AracZimmetFotoModel;
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
    exit('Geçersiz fotoğraf ID.');
}

$FotoModel = new AracZimmetFotoModel();
$foto = $FotoModel->getById((int) $id);

if (!$foto) {
    http_response_code(404);
    exit('Fotoğraf bulunamadı.');
}

$sifreliYol = dirname(__DIR__, 2) . '/files/arac_zimmet_foto/' . $foto->zimmet_id . '/' . $foto->dosya_adi;

if (!is_file($sifreliYol)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

try {
    $sifreliVeri = file_get_contents($sifreliYol);
    $binaryData = Security::decryptFile($sifreliVeri);
} catch (\Throwable $e) {
    error_log('Zimmet fotoğrafı çözülemedi: ' . $e->getMessage());
    http_response_code(500);
    exit('Görsel görüntülenemedi.');
}

$SystemLog = new SystemLogModel();
$SystemLog->logAction($_SESSION['user_id'] ?? 0, 'Zimmet Fotoğraf Görüntüleme', "Zimmet (#{$foto->zimmet_id}) için bir {$foto->foto_turu} fotoğrafı görüntülendi.", SystemLogModel::LEVEL_INFO);

header('Content-Type: ' . $foto->mime_tipi);
header('Content-Disposition: inline; filename="' . rawurlencode($foto->orijinal_ad ?? 'foto') . '"');
header('Content-Length: ' . strlen($binaryData));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $binaryData;
exit;
