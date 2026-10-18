<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/yetki.php';

use App\Model\CariHareketleriModel;
use App\Model\CariModel;
use App\Service\CariEkstreExportService;

cariExportYetkiKontrol('cari_hesap_hareketleri');
set_time_limit(0);
$tempFile = tempnam(sys_get_temp_dir(), 'cari-ekstre-');
if ($tempFile === false) { http_response_code(500); exit('Geçici dışa aktarım dosyası oluşturulamadı.'); }

try {
    $zip = new ZipArchive();
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('ZIP arşivi oluşturulamadı.'); }
    $cariModel = new CariModel();
    $hareketModel = new CariHareketleriModel();
    $exporter = new CariEkstreExportService();
    $cariler = $cariModel->getEkstreExportList();
    $csv = fopen('php://temp', 'w+');
    $csvSafe = static function ($value): string {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/u', $value) ? "'" . $value : $value;
    };
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['Cari ID', 'Cari Adı', 'VKN/TCKN', 'Telefon', 'Hareket Sayısı', 'Toplam Borç', 'Toplam Alacak', 'Bakiye'], ';');
    foreach ($cariler as $cari) {
        $hareketler = $hareketModel->getEkstreHareketleri((int) $cari->id);
        $zip->addFromString('ekstreler/' . $exporter->safeFileName((string) $cari->CariAdi, (int) $cari->id), $exporter->createPdf($cari, $hareketler));
        fputcsv($csv, [$cari->id, $csvSafe($cari->CariAdi), $csvSafe($cari->vkn_tckn ?? ''), $csvSafe($cari->Telefon ?? ''), (int) $cari->hareket_sayisi, number_format((float) $cari->toplam_borc, 2, ',', ''), number_format((float) $cari->toplam_alacak, 2, ',', ''), number_format((float) $cari->bakiye, 2, ',', '')], ';');
    }
    rewind($csv);
    $zip->addFromString('cari-ozet.csv', stream_get_contents($csv));
    fclose($csv);
    $zip->addFromString('BILGI.txt', count($cariler) . " aktif carinin hesap ekstresi ve özet tablosu içerir.\r\nOluşturma: " . date('d.m.Y H:i:s'));
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Cari_Ekstre_Yedegi_' . date('Y-m-d_H-i') . '.zip"');
    header('Content-Length: ' . filesize($tempFile));
    header('X-Content-Type-Options: nosniff');
    readfile($tempFile);
} catch (Throwable $e) {
    error_log('Toplu cari ekstre dışa aktarım hatası: ' . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo 'Toplu ekstre yedeği oluşturulamadı. Lütfen daha sonra tekrar deneyin.';
} finally {
    if (is_file($tempFile)) { unlink($tempFile); }
}
