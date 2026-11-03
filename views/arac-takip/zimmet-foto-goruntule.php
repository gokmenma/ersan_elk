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

$kullaniciId = $_SESSION['user_id'] ?? 0;
session_write_close();

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

$kucukResim = !empty($_GET['thumb']) && strpos($foto->mime_tipi, 'image/') === 0;
$mimeTipi = $foto->mime_tipi;

if ($kucukResim) {
    $kaynak = @imagecreatefromstring($binaryData);
    if ($kaynak !== false) {
        $yon = 1;
        if (function_exists('exif_read_data') && $foto->mime_tipi === 'image/jpeg') {
            $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($binaryData));
            $yon = (int) ($exif['Orientation'] ?? 1);
        }
        $aci = [3 => 180, 6 => -90, 8 => 90][$yon] ?? 0;
        if ($aci !== 0) {
            $dondurulmus = imagerotate($kaynak, $aci, 0);
            if ($dondurulmus !== false) {
                $kaynak = $dondurulmus;
            }
        }
        $en = imagesx($kaynak);
        $boy = imagesy($kaynak);
        $oran = min(1, 320 / max($en, $boy));
        $yeniEn = max(1, (int) round($en * $oran));
        $yeniBoy = max(1, (int) round($boy * $oran));
        $kucuk = imagecreatetruecolor($yeniEn, $yeniBoy);
        imagefill($kucuk, 0, 0, imagecolorallocate($kucuk, 255, 255, 255));
        imagecopyresampled($kucuk, $kaynak, 0, 0, 0, 0, $yeniEn, $yeniBoy, $en, $boy);
        ob_start();
        imagejpeg($kucuk, null, 78);
        $binaryData = ob_get_clean();
        $mimeTipi = 'image/jpeg';
    }
} else {
    $SystemLog = new SystemLogModel();
    $SystemLog->logAction($kullaniciId, 'Zimmet Fotoğraf Görüntüleme', "Zimmet (#{$foto->zimmet_id}) için bir {$foto->foto_turu} fotoğrafı görüntülendi.", SystemLogModel::LEVEL_INFO);
}

header('Content-Type: ' . $mimeTipi);
header('Content-Disposition: inline; filename="' . rawurlencode($foto->orijinal_ad ?? 'foto') . '"');
header('Content-Length: ' . strlen($binaryData));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');

echo $binaryData;
exit;
