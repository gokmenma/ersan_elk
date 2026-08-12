<?php
require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\PersonelEvrakModel;
use App\Helper\Security;
use App\Service\Gate;

if (!Gate::allows('personel_duzenle')) {
    http_response_code(403);
    exit('Bu evrakı görüntüleme yetkiniz yok.');
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
    exit('Geçersiz evrak ID.');
}

$EvrakModel = new PersonelEvrakModel();
$evrak = $EvrakModel->getById((int) $id);

if (!$evrak) {
    http_response_code(404);
    exit('Evrak bulunamadı.');
}

$filePath = dirname(__DIR__, 2) . '/uploads/personel_evraklar/' . $evrak->personel_id . '/' . $evrak->dosya_adi;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

$inline = isset($_GET['inline']) && $_GET['inline'] === '1';
$disposition = $inline ? 'inline' : 'attachment';
$filename = $evrak->orijinal_dosya_adi ?? $evrak->dosya_adi;

$binaryData = null;
if (str_ends_with((string) $evrak->dosya_adi, '.enc')) {
    try {
        $encryptedData = file_get_contents($filePath);
        if ($encryptedData === false) {
            throw new RuntimeException('Şifreli dosya okunamadı.');
        }
        $binaryData = Security::decryptFile($encryptedData);
    } catch (Throwable $e) {
        error_log('Personel evrakı çözme hatası: Evrak ID ' . (int) $evrak->id);
        http_response_code(500);
        exit('Dosya güvenli biçimde açılamadı.');
    }
}

header('Content-Type: ' . $evrak->dosya_tipi);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . ($binaryData !== null ? strlen($binaryData) : filesize($filePath)));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

if ($binaryData !== null) {
    echo $binaryData;
} else {
    readfile($filePath);
}
exit;
