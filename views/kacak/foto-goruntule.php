<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\KacakKontrolModel;
use App\Helper\Security;
use App\Service\Gate;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::allows('kacak_duzenle') && !Gate::allows('kacak_onay') && !Gate::allows('kacak_arsiv') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

$fotoId = isset($_GET['token'])
    ? (int) Security::decrypt((string) $_GET['token'])
    : (int) ($_GET['id'] ?? 0); // Masaüstü ekranıyla geriye dönük uyumluluk
if ($fotoId <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

$Kacak = new KacakKontrolModel();
$foto = $Kacak->getPhoto($fotoId);

if (!$foto) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$kucukIstendi = ($_GET['boyut'] ?? '') === 'kucuk';
$indirIstendi = isset($_GET['indir']) || isset($_GET['download']);
$secilenYol = ($kucukIstendi && !empty($foto['kucuk_yol'])) ? $foto['kucuk_yol'] : $foto['dosya_yolu'];

$dosya = KacakKontrolModel::rootPath() . '/' . ltrim($secilenYol, '/');

if (!is_file($dosya) && $secilenYol !== $foto['dosya_yolu']) {
    $dosya = KacakKontrolModel::rootPath() . '/' . ltrim($foto['dosya_yolu'], '/');
}

if (!is_file($dosya)) {
    http_response_code(404);
    exit($foto['arsivlendi'] ? 'Bu dosya arşivlenmiş ve sunucudan silinmiştir.' : 'Dosya bulunamadı.');
}

$ext = strtolower(pathinfo($dosya, PATHINFO_EXTENSION));
$isPdf = ($ext === 'pdf');
$isVideo = (($foto['medya_tipi'] ?? '') === 'video' || in_array($ext, ['mp4', 'mov', 'webm', '3gp'], true));

if ($isPdf || $isVideo) {
    \App\Helper\MedyaServis::gonder($dosya);
    exit;
}

// Görsel dosyaları: Uzantı ve format JPEG olarak sunulur
$orijinalAd = $foto['orijinal_ad'] ?? basename($dosya);
$orijinalBase = pathinfo($orijinalAd, PATHINFO_FILENAME);
$indirilecekAd = preg_replace('/[^\p{L}\p{N}_.-]+/u', '_', $orijinalBase) . '.jpeg';
$dispositionType = $indirIstendi ? 'attachment' : 'inline';

$jpegBinary = KacakKontrolModel::getAsJpegBinary($dosya);
if ($jpegBinary !== null) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: image/jpeg');
    header('Content-Disposition: ' . $dispositionType . '; filename="' . $indirilecekAd . '"; filename*=UTF-8\'\'' . rawurlencode($indirilecekAd));
    header('Content-Length: ' . strlen($jpegBinary));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=600');
    header('Accept-Ranges: bytes');
    echo $jpegBinary;
    exit;
}

\App\Helper\MedyaServis::gonder($dosya);
exit;
