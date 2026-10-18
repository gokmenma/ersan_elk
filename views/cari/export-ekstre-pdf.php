<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/yetki.php';

use App\Helper\Security;
use App\Model\CariHareketleriModel;
use App\Model\CariModel;
use App\Service\CariEkstreExportService;

cariExportYetkiKontrol('cari_hesap_hareketleri');

$cariId = (int) Security::decrypt((string) ($_GET['id'] ?? ''));
$cariModel = new CariModel();
$cari = $cariId > 0 ? $cariModel->find($cariId) : null;
if (!$cari) {
    http_response_code(404);
    exit('Cari bulunamadı.');
}

try {
    $hareketler = (new CariHareketleriModel())->getEkstreHareketleri($cariId);
    $cari->toplam_borc = array_sum(array_map(static fn($row) => (float) $row->borc, $hareketler));
    $cari->toplam_alacak = array_sum(array_map(static fn($row) => (float) $row->alacak, $hareketler));
    $cari->bakiye = $cari->toplam_alacak - $cari->toplam_borc;
    $exporter = new CariEkstreExportService();
    $pdf = $exporter->createPdf($cari, $hareketler);
    $fileName = $exporter->safeFileName((string) $cari->CariAdi, $cariId);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
} catch (Throwable $e) {
    error_log('Cari ekstre PDF hatası: ' . $e->getMessage());
    http_response_code(500);
    echo 'Ekstre oluşturulamadı. Lütfen daha sonra tekrar deneyin.';
}
