<?php

require_once dirname(__DIR__, 2) . '/layouts/session.php';
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Model\DemirbasServisModel;

$id = Security::decrypt($_GET['id'] ?? '');
if (empty($id) || !is_numeric($id)) {
    http_response_code(400);
    exit('Geçersiz servis kaydı.');
}

$Servis = new DemirbasServisModel();
$kayit = $Servis->findForCompany((int) $id);
if (!$kayit || empty($kayit->fatura_dosya_adi)) {
    http_response_code(404);
    exit('Servis faturası bulunamadı.');
}

$dosya = dirname(__DIR__, 2) . '/files/demirbas_servis_fatura/' . (int) $kayit->id . '/' . basename($kayit->fatura_dosya_adi);
if (!is_file($dosya)) {
    http_response_code(404);
    exit('Servis faturası bulunamadı.');
}

try {
    $binaryData = Security::decryptFile(file_get_contents($dosya));
} catch (Throwable $e) {
    error_log('Demirbaş servis faturası çözülemedi: ' . $e->getMessage());
    http_response_code(500);
    exit('Servis faturası görüntülenemedi.');
}

$mimeType = in_array($kayit->fatura_mime_tipi, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)
    ? $kayit->fatura_mime_tipi
    : 'application/octet-stream';
$orijinalAd = str_replace(["\r", "\n", '"'], '', basename($kayit->fatura_orijinal_adi ?: 'servis-faturasi'));

header('Content-Type: ' . $mimeType);
header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($orijinalAd));
header('Content-Length: ' . strlen($binaryData));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

echo $binaryData;
exit;
