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

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::isSuperAdmin()) {
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
$secilenYol = ($kucukIstendi && !empty($foto['kucuk_yol'])) ? $foto['kucuk_yol'] : $foto['dosya_yolu'];

$dosya = KacakKontrolModel::rootPath() . '/' . ltrim($secilenYol, '/');

if (!is_file($dosya) && $secilenYol !== $foto['dosya_yolu']) {
    $dosya = KacakKontrolModel::rootPath() . '/' . ltrim($foto['dosya_yolu'], '/');
}

if (!is_file($dosya)) {
    http_response_code(404);
    exit($foto['arsivlendi'] ? 'Bu dosya arşivlenmiş ve sunucudan silinmiştir.' : 'Dosya bulunamadı.');
}

\App\Helper\MedyaServis::gonder($dosya);
exit;
