<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Helper\Helper;
use App\Helper\Security;
use App\Model\KacakKontrolModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

if ($userId <= 0 || empty($_SESSION['firma_id'])) {
    http_response_code(403);
    if (isset($_REQUEST['tip']) && in_array($_REQUEST['tip'], ['start_export_job', 'process_export_job', 'check_export_job'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
        exit;
    }
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::allows('kacak_duzenle') && !Gate::allows('kacak_onay') && !Gate::allows('kacak_arsiv') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    if (isset($_REQUEST['tip']) && in_array($_REQUEST['tip'], ['start_export_job', 'process_export_job', 'check_export_job'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
        exit;
    }
    exit('Yetkisiz erişim.');
}

@set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('pcre.backtrack_limit', '10000000');
ini_set('memory_limit', '1024M');

$istek = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
$tip = $istek['tip'] ?? 'ozet';
$baslangic = Date::convertExcelDate($istek['start_date'] ?? '', 'Y-m-d') ?: date('Y-m-d', strtotime('monday this week'));
$bitis = Date::convertExcelDate($istek['end_date'] ?? '', 'Y-m-d') ?: date('Y-m-d');

$Kacak = new KacakKontrolModel();
$exportStorageDir = dirname(__DIR__, 2) . '/storage/temp_exports';

if (!is_dir($exportStorageDir)) {
    @mkdir($exportStorageDir, 0775, true);
}

// 24 saatten eski geçici dosyaları temizleyen çöp toplayıcı (Garbage Collector)
function cleanupOldExportFiles(string $dir): void
{
    if (!is_dir($dir)) return;
    $files = glob($dir . '/*');
    if ($files === false) return;
    $now = time();
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== '.htaccess') {
            if ($now - filemtime($file) > 86400) {
                @unlink($file);
            }
        }
    }
}
cleanupOldExportFiles($exportStorageDir);

function getJobFilePath(string $dir, string $jobId, string $ext = 'json'): string
{
    $cleanId = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
    return $dir . '/job_' . $cleanId . '.' . $ext;
}

function updateJobStatus(string $dir, string $jobId, array $data): void
{
    $file = getJobFilePath($dir, $jobId, 'json');
    $existing = [];
    if (is_file($file)) {
        $json = @file_get_contents($file);
        if ($json) {
            $existing = json_decode($json, true) ?: [];
        }
    }
    $updated = array_merge($existing, $data, ['updated_at' => time()]);
    @file_put_contents($file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * HTTP Range destekli duraklatılabilir/devam ettirilebilir (Resumable) dosya indirme akışı
 */
function deliverFileWithRangeSupport(string $filePath, string $downloadFilename, string $contentType = 'application/octet-stream'): void
{
    if (!is_file($filePath)) {
        http_response_code(404);
        exit('İndirilecek dosya bulunamadı veya süresi dolmuş.');
    }

    $fileSize = (int) filesize($filePath);
    $offset = 0;
    $length = $fileSize;
    $statusCode = 200;

    $headers = [
        'Content-Type: ' . $contentType,
        'Accept-Ranges: bytes',
        'Cache-Control: public, must-revalidate, max-age=0',
        'Pragma: no-cache',
    ];

    $asciiName = Helper::toAscii($downloadFilename);
    $encodedName = rawurlencode($downloadFilename);
    $headers[] = 'Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . $encodedName;

    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
            $start = (int) $matches[1];
            $end = ($matches[2] !== '') ? (int) $matches[2] : ($fileSize - 1);
            if ($start <= $end && $end < $fileSize) {
                $offset = $start;
                $length = $end - $start + 1;
                $statusCode = 206;
                $headers[] = "Content-Range: bytes {$offset}-{$end}/{$fileSize}";
            }
        }
    }

    $headers[] = 'Content-Length: ' . $length;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    foreach ($headers as $h) {
        header($h);
    }

    $fp = @fopen($filePath, 'rb');
    if ($fp === false) {
        exit;
    }

    if ($offset > 0) {
        fseek($fp, $offset);
    }

    $chunkSize = 1024 * 1024; // 1 MB
    $bytesRemaining = $length;

    while (!feof($fp) && $bytesRemaining > 0 && connection_status() === CONNECTION_NORMAL) {
        $readSize = min($chunkSize, $bytesRemaining);
        $buffer = fread($fp, $readSize);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        flush();
        $bytesRemaining -= strlen($buffer);
    }

    fclose($fp);
    exit;
}

function seciliTeslimKayitlari(KacakKontrolModel $model, string $baslangic, string $bitis, array $tokenlar): array
{
    if (!empty($tokenlar)) {
        $ids = [];
        foreach ($tokenlar as $token) {
            $id = (int) Security::decrypt((string) $token);
            if ($id > 0) $ids[] = $id;
        }
        if (!empty($ids)) {
            return $model->getTeslimAlmaListesiByIds($ids);
        }
    }
    return $model->getTeslimAlmaListesi($baslangic, $bitis);
}

function getKacakFotoGorselYolu(string $kaynak, array &$geciciDosyalar): ?string
{
    if (!is_file($kaynak)) {
        return null;
    }
    $ext = strtolower(pathinfo($kaynak, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
        return $kaynak;
    }
    $jpegBinary = KacakKontrolModel::getAsJpegBinary($kaynak);
    if ($jpegBinary !== null) {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('kacak_pdf_img_', true) . '.jpg';
        if (@file_put_contents($tmpFile, $jpegBinary) !== false) {
            $geciciDosyalar[] = $tmpFile;
            return $tmpFile;
        }
    }
    return null;
}

function uretKacakFotoPdfHtml(array $detay, array $sahaFotolari): string
{
    $esc = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

    $html = '<div class="page-container">';
    $fotoAdet = count($sahaFotolari);

    if ($fotoAdet === 0) {
        $html .= '<div class="no-photo-box">
            <p style="margin: 0; font-weight: bold; font-size: 12pt; color: #475569;">Saha Tespit Fotoğrafı Bulunamadı</p>
        </div>';
    } elseif ($fotoAdet === 1) {
        $f = $sahaFotolari[0];
        $gorselSrc = $esc($f['dosya_yolu_disk']);
        $html .= '<div style="text-align: center; height: 280mm; line-height: 280mm;">
            <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 196mm; max-height: 278mm;" />
        </div>';
    } elseif ($fotoAdet === 2) {
        $html .= '<table class="photo-grid-table" style="height: 280mm;"><tr>';
        foreach ($sahaFotolari as $idx => $f) {
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 50%; height: 278mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 96mm; max-height: 272mm;" />
            </td>';
        }
        $html .= '</tr></table>';
    } elseif ($fotoAdet === 3) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < 2; $i++) {
            $f = $sahaFotolari[$i];
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 50%; height: 138mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 96mm; max-height: 134mm;" />
            </td>';
        }
        $html .= '</tr><tr>';
        $f = $sahaFotolari[2];
        $gorselSrc = $esc($f['dosya_yolu_disk']);
        $html .= '<td colspan="2" class="photo-cell" style="width: 100%; height: 138mm;">
            <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 96mm; max-height: 134mm;" />
        </td>';
        $html .= '</tr></table>';
    } elseif ($fotoAdet === 4) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < 4; $i++) {
            if ($i === 2) {
                $html .= '</tr><tr>';
            }
            $f = $sahaFotolari[$i];
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 50%; height: 138mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 96mm; max-height: 134mm;" />
            </td>';
        }
        $html .= '</tr></table>';
    } elseif ($fotoAdet <= 6) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < $fotoAdet; $i++) {
            if ($i > 0 && $i % 3 === 0) {
                $html .= '</tr><tr>';
            }
            $f = $sahaFotolari[$i];
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 33.33%; height: 138mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 63mm; max-height: 134mm;" />
            </td>';
        }
        if ($fotoAdet === 5) {
            $html .= '<td class="photo-cell" style="width: 33.33%; height: 138mm; background: #f8fafc;"></td>';
        }
        $html .= '</tr></table>';
    } else {
        $gosterilecekler = array_slice($sahaFotolari, 0, 8);
        $html .= '<table class="photo-grid-table"><tr>';
        foreach ($gosterilecekler as $i => $f) {
            if ($i > 0 && $i % 4 === 0) {
                $html .= '</tr><tr>';
            }
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 25%; height: 138mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 46mm; max-height: 134mm;" />
            </td>';
        }
        $kalan = 8 - count($gosterilecekler);
        for ($k = 0; $k < $kalan; $k++) {
            $html .= '<td class="photo-cell" style="width: 25%; height: 138mm; background: #f8fafc;"></td>';
        }
        $html .= '</tr></table>';
    }

    $html .= '</div>';
    return $html;
}

function uretKacakTekSayfaPdfBinary(array $detay, array $sahaFotolari, array &$geciciDosyalar): ?string
{
    if (empty($sahaFotolari)) {
        return null;
    }
    $css = '
        body { font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif; font-size: 9pt; color: #1e293b; margin: 0; padding: 0; }
        .page-container { width: 100%; }
        .photo-grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .photo-cell { text-align: center; vertical-align: middle; padding: 3px; border: 1px solid #e2e8f0; background: #ffffff; }
        .photo-img { vertical-align: middle; border: 1px solid #cbd5e1; border-radius: 2px; }
        .no-photo-box { text-align: center; padding: 40mm 10mm; background: #f8fafc; border: 1px dashed #cbd5e1; color: #64748b; font-size: 11pt; border-radius: 4px; }
    ';

    try {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => sys_get_temp_dir(),
        ]);
        $mpdf->SetTitle('Fotoğraf Çıktısı - ' . ($detay['tutanak_no'] ?? ''));
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML(uretKacakFotoPdfHtml($detay, $sahaFotolari), \Mpdf\HTMLParserMode::HTML_BODY);
        return $mpdf->Output('', 'S');
    } catch (\Throwable $e) {
        error_log('Kacak tek sayfa PDF binary üretilemedi: ' . $e->getMessage());
        return null;
    }
}

// -------------------------------------------------------------
// ASENKRON GÖREV VE DURUM YÖNETİMİ ENDPOINT'LERİ (API)
// -------------------------------------------------------------

// 1. Görevi Başlat (start_export_job)
if ($tip === 'start_export_job') {
    header('Content-Type: application/json; charset=utf-8');
    $exportType = $istek['export_type'] ?? 'teslim_zip';
    $tokens = (array) ($istek['tokens'] ?? []);

    $liste = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, $tokens);
    if ($exportType === 'teslim_foto_pdf') {
        $liste = array_values(array_filter($liste, static fn(array $k): bool => !empty($k['foto_cikti_gerekli'])));
    }

    if (empty($liste)) {
        echo json_encode([
            'success' => false,
            'message' => ($exportType === 'teslim_foto_pdf')
                ? 'Seçilen kayıtlar arasında fotoğraf çıktısı gerekli olan kayıt bulunamadı.'
                : 'Dışa aktarma için seçili kayıt bulunamadı.'
        ]);
        exit;
    }

    $jobId = bin2hex(random_bytes(16));
    $initialData = [
        'job_id' => $jobId,
        'user_id' => $userId,
        'export_type' => $exportType,
        'start_date' => $baslangic,
        'end_date' => $bitis,
        'total_records' => count($liste),
        'processed_records' => 0,
        'progress' => 0,
        'status' => 'queued',
        'message' => 'Dışa aktarma sıraya alındı...',
        'file_path' => '',
        'filename' => '',
        'file_size' => 0,
        'file_size_fmt' => '',
        'created_at' => time(),
        'error' => null,
    ];

    updateJobStatus($exportStorageDir, $jobId, $initialData);

    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'total_records' => count($liste),
        'message' => 'Görev başlatıldı.'
    ]);
    exit;
}

// 2. Görevi İşle (process_export_job)
if ($tip === 'process_export_job') {
    header('Content-Type: application/json; charset=utf-8');
    $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($istek['job_id'] ?? ''));
    $exportType = $istek['export_type'] ?? 'teslim_zip';
    $tokens = (array) ($istek['tokens'] ?? []);

    if ($jobId === '') {
        echo json_encode(['success' => false, 'message' => 'Geçersiz görev ID.']);
        exit;
    }

    $liste = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, $tokens);
    if ($exportType === 'teslim_foto_pdf') {
        $liste = array_values(array_filter($liste, static fn(array $k): bool => !empty($k['foto_cikti_gerekli'])));
    }

    $totalCount = count($liste);
    if ($totalCount === 0) {
        updateJobStatus($exportStorageDir, $jobId, ['status' => 'failed', 'error' => 'Kayıt bulunamadı.']);
        echo json_encode(['success' => false, 'message' => 'Kayıt bulunamadı.']);
        exit;
    }

    updateJobStatus($exportStorageDir, $jobId, [
        'status' => 'processing',
        'progress' => 5,
        'message' => 'Kayıtlar ve fotoğraflar taranıyor...'
    ]);

    $rootDiskPath = KacakKontrolModel::rootPath();
    $kacakIds = array_map(static fn(array $k): int => (int) $k['id'], $liste);
    $tumFotolarGrouped = $Kacak->getPhotosByKacakIds($kacakIds);

    $trMonths = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
    ];
    $sTime = strtotime($baslangic);
    $eTime = strtotime($bitis);
    $startDay = (int) date('j', $sTime);
    $startMonthName = $trMonths[(int) date('n', $sTime)] ?? date('F', $sTime);
    $endMonthName = $trMonths[(int) date('n', $eTime)] ?? date('F', $eTime);

    session_write_close();
    @ignore_user_abort(true);

    if ($exportType === 'teslim_foto_pdf') {
        $targetFile = $exportStorageDir . '/export_' . $jobId . '.pdf';
        $geciciDosyalar = [];
        $toplamFotoSayisi = 0;

        $css = '
            body { font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif; font-size: 9pt; color: #1e293b; margin: 0; padding: 0; }
            .page-container { width: 100%; }
            .photo-grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .photo-cell { text-align: center; vertical-align: middle; padding: 3px; border: 1px solid #e2e8f0; background: #ffffff; }
            .photo-img { vertical-align: middle; border: 1px solid #cbd5e1; border-radius: 2px; }
            .no-photo-box { text-align: center; padding: 40mm 10mm; background: #f8fafc; border: 1px dashed #cbd5e1; color: #64748b; font-size: 11pt; border-radius: 4px; }
        ';

        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-P',
                'margin_left' => 8,
                'margin_right' => 8,
                'margin_top' => 8,
                'margin_bottom' => 8,
                'tempDir' => sys_get_temp_dir(),
            ]);
            $mpdf->SetTitle('Kaçak Teslim Alma - Fotoğraf Çıktıları');
            $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

            foreach ($liste as $index => $kayit) {
                $kacakId = (int) $kayit['id'];
                $detay = $kayit;
                $tumFotolar = $tumFotolarGrouped[$kacakId] ?? [];
                $sahaFotolari = [];

                foreach ($tumFotolar as $foto) {
                    $fotoTur = strtolower($foto['tur'] ?? 'saha');
                    $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');
                    $ext = strtolower(pathinfo($foto['dosya_yolu'] ?? '', PATHINFO_EXTENSION));

                    if ($fotoTur === 'tutanak' || $fotoTur === 'iptal') continue;
                    if ($medyaTipi === 'video' || in_array($ext, ['mp4', 'mov', 'webm', '3gp', 'avi', 'mkv', 'pdf'], true)) continue;

                    $kaynak = $rootDiskPath . '/' . ltrim($foto['dosya_yolu'], '/');
                    $gorselYolu = getKacakFotoGorselYolu($kaynak, $geciciDosyalar);
                    if ($gorselYolu !== null) {
                        $foto['dosya_yolu_disk'] = $gorselYolu;
                        $sahaFotolari[] = $foto;
                        $toplamFotoSayisi++;
                    }
                }

                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $sayfaHtml = uretKacakFotoPdfHtml($detay, $sahaFotolari);
                $mpdf->WriteHTML($sayfaHtml, \Mpdf\HTMLParserMode::HTML_BODY);

                $processed = $index + 1;
                $pct = min(95, 5 + (int) round(($processed / $totalCount) * 90));
                if ($processed % 2 === 0 || $processed === $totalCount) {
                    updateJobStatus($exportStorageDir, $jobId, [
                        'processed_records' => $processed,
                        'progress' => $pct,
                        'message' => "Fotoğraflar PDF sayfalarına yerleştiriliyor... ({$processed} / {$totalCount})"
                    ]);
                }
            }

            $mpdf->Output($targetFile, 'F');

            foreach ($geciciDosyalar as $tmpF) {
                @unlink($tmpF);
            }

            $downloadName = ($totalCount === 1 && !empty($liste[0]['tutanak_no']))
                ? 'Kacak_Foto_Ciktisi_' . preg_replace('/[^\p{L}\p{N}_.-]+/u', '_', (string) $liste[0]['tutanak_no']) . '.pdf'
                : 'Kacak_Foto_Ciktilari_' . $baslangic . '_' . $bitis . '.pdf';

            $fileSize = (int) filesize($targetFile);
            $sizeFmt = formatBytes($fileSize);

            $logModel = new SystemLogModel();
            $logModel->logAction(
                $userId,
                'Teslim Alma Fotoğraf Çıktısı (PDF - Async)',
                "Aralık: $baslangic - $bitis, Kayıt Sayısı: $totalCount, Foto Sayısı: $toplamFotoSayisi, Boyut: $sizeFmt",
                SystemLogModel::LEVEL_INFO
            );

            updateJobStatus($exportStorageDir, $jobId, [
                'status' => 'completed',
                'progress' => 100,
                'processed_records' => $totalCount,
                'message' => 'PDF dosyası hazırlandı.',
                'file_path' => $targetFile,
                'filename' => $downloadName,
                'file_size' => $fileSize,
                'file_size_fmt' => $sizeFmt,
            ]);

            echo json_encode([
                'success' => true,
                'status' => 'completed',
                'job_id' => $jobId,
                'filename' => $downloadName,
                'file_size_fmt' => $sizeFmt
            ]);
            exit;
        } catch (\Throwable $e) {
            foreach ($geciciDosyalar as $tmpF) {
                @unlink($tmpF);
            }
            updateJobStatus($exportStorageDir, $jobId, ['status' => 'failed', 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'PDF oluşturulurken hata: ' . $e->getMessage()]);
            exit;
        }
    } else {
        // ZIP Arşivi Oluşturma
        try {
            $targetFile = $exportStorageDir . '/export_' . $jobId . '.zip';
            $rootFolder = sprintf('%d %s - %d %s Tarihleri Arasında Yapılan İşlemler', $startDay, $startMonthName, $endDay, $endMonthName);

            $zip = new \ZipArchive();
            if ($zip->open($targetFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                updateJobStatus($exportStorageDir, $jobId, ['status' => 'failed', 'error' => 'Arşiv dosyası açılamadı.']);
                echo json_encode(['success' => false, 'message' => 'Arşiv dosyası oluşturulamadı.']);
                exit;
            }

            $toplamDosyaSayisi = 0;
            $geciciDosyalarZip = [];

            foreach ($liste as $index => $kayit) {
                $kacakId = (int) $kayit['id'];
                $detay = $kayit;
                $ilceName = Helper::trUpper(trim((string) ($kayit['ilce'] ?? 'BELİRTİLMEMİŞ')));
                $tutanakNo = trim((string) ($kayit['tutanak_no'] ?? ''));
                $aboneAdi = Helper::trUpper(trim((string) ($kayit['abone_adi'] ?? '')));
                $tur = Helper::trUpper(trim((string) ($kayit['tur'] ?? 'KAÇAK')));

                $folderParts = [];
                if ($tutanakNo !== '') {
                    $folderParts[] = $tutanakNo;
                } else {
                    $folderParts[] = 'KAYIT_' . $kacakId;
                }
                if ($aboneAdi !== '') {
                    $folderParts[] = $aboneAdi;
                }

                $rawTutanakFolder = implode(' - ', $folderParts) . ' (' . $tur . ')';
                $tutanakFolder = preg_replace('/[\/\\\\:\*\?"<>\|]/u', '_', trim($rawTutanakFolder));

                $recordPathInZip = $rootFolder . '/' . $ilceName . '/' . $tutanakFolder;
                $zip->addEmptyDir($recordPathInZip);

                $fotolar = $tumFotolarGrouped[$kacakId] ?? [];
                $tutanakSeq = 1;
                $sahaSeq = 1;
                $iptalSeq = 1;
                $videoSeq = 1;

                foreach ($fotolar as $foto) {
                    $kaynak = $rootDiskPath . '/' . ltrim($foto['dosya_yolu'], '/');
                    if (!is_file($kaynak)) continue;

                    $origExt = strtolower(pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION));
                    $fotoTur = strtolower($foto['tur'] ?? 'saha');
                    $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');

                    if ($fotoTur === 'tutanak') {
                        $prefix = 'tutanak';
                        $seq = $tutanakSeq++;
                    } elseif ($fotoTur === 'iptal') {
                        $prefix = 'iptal';
                        $seq = $iptalSeq++;
                    } elseif ($medyaTipi === 'video') {
                        $prefix = 'video';
                        $seq = $videoSeq++;
                    } else {
                        $prefix = 'saha';
                        $seq = $sahaSeq++;
                    }

                    $isPdf = ($origExt === 'pdf');
                    $isVideo = ($medyaTipi === 'video' || in_array($origExt, ['mp4', 'mov', 'webm', '3gp'], true));
                    $ext = $origExt ?: ($isPdf ? 'pdf' : ($isVideo ? 'mp4' : 'jpeg'));
                    $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNo ?: ('kayit_' . $kacakId), $seq, $ext);
                    $entryName = $recordPathInZip . '/' . $dosyaAdi;
                    $zip->addFile($kaynak, $entryName, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
                    $zip->setCompressionName($entryName, \ZipArchive::CM_STORE);
                    $toplamDosyaSayisi++;
                }

                $processed = $index + 1;
                $pct = min(95, 5 + (int) round(($processed / $totalCount) * 90));
                if ($processed % 2 === 0 || $processed === $totalCount) {
                    updateJobStatus($exportStorageDir, $jobId, [
                        'processed_records' => $processed,
                        'progress' => $pct,
                        'message' => "Tutanaklar ve fotoğraflar arşive ekleniyor... ({$processed} / {$totalCount})"
                    ]);
                }
            }

            foreach ($geciciDosyalarZip as $tmpF) {
                @unlink($tmpF);
            }

            $zip->close();

            if (!is_file($targetFile)) {
                updateJobStatus($exportStorageDir, $jobId, ['status' => 'failed', 'error' => 'ZIP dosyası oluşturulamadı.']);
                echo json_encode(['success' => false, 'message' => 'ZIP dosyası oluşturulamadı.']);
                exit;
            }

            $zipDownloadName = $rootFolder . '.zip';
            $fileSize = (int) filesize($targetFile);
            $sizeFmt = formatBytes($fileSize);

            $logModel = new App\Model\SystemLogModel();
            $logModel->logAction(
                $userId,
                'Teslim Alma Listesi Toplu İndirme (ZIP - Async)',
                "Aralık: $baslangic - $bitis, Kayıt Sayısı: $totalCount, Dosya Sayısı: $toplamDosyaSayisi, Boyut: $sizeFmt, Klasör: $rootFolder",
                App\Model\SystemLogModel::LEVEL_INFO
            );

            updateJobStatus($exportStorageDir, $jobId, [
                'status' => 'completed',
                'progress' => 100,
                'processed_records' => $totalCount,
                'message' => 'ZIP arşivi hazırlandı.',
                'file_path' => $targetFile,
                'filename' => $zipDownloadName,
                'file_size' => $fileSize,
                'file_size_fmt' => $sizeFmt,
            ]);

            echo json_encode([
                'success' => true,
                'status' => 'completed',
                'job_id' => $jobId,
                'filename' => $zipDownloadName,
                'file_size_fmt' => $sizeFmt
            ]);
            exit;
        } catch (\Throwable $e) {
            error_log('process_export_job ZIP error: ' . $e->getMessage());
            updateJobStatus($exportStorageDir, $jobId, ['status' => 'failed', 'error' => $e->getMessage()]);
            echo json_encode(['success' => false, 'message' => 'ZIP oluşturulurken hata: ' . $e->getMessage()]);
            exit;
        }
    }
}

// 3. Görev Durumunu Kontrol Et (check_export_job)
if ($tip === 'check_export_job') {
    header('Content-Type: application/json; charset=utf-8');
    $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($istek['job_id'] ?? ''));
    $statusFile = getJobFilePath($exportStorageDir, $jobId, 'json');

    if ($jobId === '' || !is_file($statusFile)) {
        echo json_encode(['success' => false, 'status' => 'not_found', 'message' => 'Görev bulunamadı.']);
        exit;
    }

    $json = @file_get_contents($statusFile);
    $data = json_decode($json, true) ?: [];
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// 4. Hazırlanan Dosyayı Resumable / HTTP Range ile İndir (download_job)
if ($tip === 'download_job') {
    $jobId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($istek['job_id'] ?? ''));
    $statusFile = getJobFilePath($exportStorageDir, $jobId, 'json');

    if ($jobId === '' || !is_file($statusFile)) {
        http_response_code(404);
        exit('İndirme oturumu bulunamadı veya süresi dolmuş.');
    }

    $json = @file_get_contents($statusFile);
    $data = json_decode($json, true) ?: [];

    if (($data['status'] ?? '') !== 'completed' || empty($data['file_path']) || !is_file($data['file_path'])) {
        http_response_code(404);
        exit('Dosya henüz hazırlanmamış veya silinmiş.');
    }

    $ext = strtolower(pathinfo($data['file_path'], PATHINFO_EXTENSION));
    $mime = ($ext === 'pdf') ? 'application/pdf' : 'application/zip';
    $downloadFilename = !empty($data['filename']) ? $data['filename'] : basename($data['file_path']);

    deliverFileWithRangeSupport($data['file_path'], $downloadFilename, $mime);
}

// -------------------------------------------------------------
// SENKRON / KLASİK AKIŞLAR (Geriye Dönük Uyumluluk)
// -------------------------------------------------------------

if ($tip === 'teslim_foto_pdf') {
    $seciliListe = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, (array) ($istek['tokens'] ?? []));
    $liste = array_values(array_filter($seciliListe, static fn(array $k): bool => !empty($k['foto_cikti_gerekli'])));

    if (empty($liste)) {
        http_response_code(422);
        exit('Seçilen kayıtlar arasında fotoğraf çıktısı gerekli olan kayıt bulunamadı.');
    }

    $rootDiskPath = KacakKontrolModel::rootPath();
    $geciciDosyalar = [];
    $kacakIds = array_map(static fn(array $k): int => (int) $k['id'], $liste);
    $tumFotolarGrouped = $Kacak->getPhotosByKacakIds($kacakIds);

    $css = '
        body { font-family: "DejaVu Sans", "Helvetica Neue", Arial, sans-serif; font-size: 9pt; color: #1e293b; margin: 0; padding: 0; }
        .page-container { width: 100%; }
        .photo-grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .photo-cell { text-align: center; vertical-align: middle; padding: 3px; border: 1px solid #e2e8f0; background: #ffffff; }
        .photo-img { vertical-align: middle; border: 1px solid #cbd5e1; border-radius: 2px; }
        .no-photo-box { text-align: center; padding: 40mm 10mm; background: #f8fafc; border: 1px dashed #cbd5e1; color: #64748b; font-size: 11pt; border-radius: 4px; }
    ';

    $toplamKayit = count($liste);
    $toplamFotoSayisi = 0;

    try {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Kaçak Teslim Alma - Fotoğraf Çıktıları');
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        foreach ($liste as $index => $kayit) {
            $kacakId = (int) $kayit['id'];
            $detay = $kayit;
            $tumFotolar = $tumFotolarGrouped[$kacakId] ?? [];
            $sahaFotolari = [];

            foreach ($tumFotolar as $foto) {
                $fotoTur = strtolower($foto['tur'] ?? 'saha');
                $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');
                $ext = strtolower(pathinfo($foto['dosya_yolu'] ?? '', PATHINFO_EXTENSION));

                if ($fotoTur === 'tutanak' || $fotoTur === 'iptal') continue;
                if ($medyaTipi === 'video' || in_array($ext, ['mp4', 'mov', 'webm', '3gp', 'avi', 'mkv', 'pdf'], true)) continue;

                $kaynak = $rootDiskPath . '/' . ltrim($foto['dosya_yolu'], '/');
                $gorselYolu = getKacakFotoGorselYolu($kaynak, $geciciDosyalar);
                if ($gorselYolu !== null) {
                    $foto['dosya_yolu_disk'] = $gorselYolu;
                    $sahaFotolari[] = $foto;
                    $toplamFotoSayisi++;
                }
            }

            if ($index > 0) {
                $mpdf->AddPage();
            }

            $sayfaHtml = uretKacakFotoPdfHtml($detay, $sahaFotolari);
            $mpdf->WriteHTML($sayfaHtml, \Mpdf\HTMLParserMode::HTML_BODY);
        }

        $logModel = new SystemLogModel();
        $logModel->logAction(
            $userId,
            'Teslim Alma Fotoğraf Çıktısı (PDF)',
            "Aralık: $baslangic - $bitis, Kayıt Sayısı: $toplamKayit, Foto Sayısı: $toplamFotoSayisi",
            SystemLogModel::LEVEL_INFO
        );

        $dosyaAdi = ($toplamKayit === 1 && !empty($liste[0]['tutanak_no']))
            ? 'Kacak_Foto_Ciktisi_' . preg_replace('/[^\p{L}\p{N}_.-]+/u', '_', (string) $liste[0]['tutanak_no']) . '.pdf'
            : 'Kacak_Foto_Ciktilari_' . $baslangic . '_' . $bitis . '.pdf';

        $asciiPdfAdi = Helper::toAscii($dosyaAdi);
        $encodedPdfAdi = rawurlencode($dosyaAdi);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $asciiPdfAdi . '"; filename*=UTF-8\'\'' . $encodedPdfAdi);
        echo $mpdf->Output('', 'S');

        foreach ($geciciDosyalar as $tmpF) {
            @unlink($tmpF);
        }
        exit;
    } catch (\Throwable $e) {
        foreach ($geciciDosyalar as $tmpF) {
            @unlink($tmpF);
        }
        error_log('Kaçak teslim foto PDF hatası: ' . $e->getMessage());
        http_response_code(500);
        exit('PDF oluşturulurken bir hata meydana geldi: ' . $e->getMessage());
    }
}

if ($tip === 'teslim_zip') {
    $liste = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, (array) ($istek['tokens'] ?? []));
    if (empty($liste)) {
        http_response_code(404);
        exit('Seçilen tarih aralığında teslim alma listesinde kayıt bulunamadı.');
    }

    $trMonths = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
    ];

    $sTime = strtotime($baslangic);
    $eTime = strtotime($bitis);
    $startDay = (int) date('j', $sTime);
    $startMonthName = $trMonths[(int) date('n', $sTime)] ?? date('F', $sTime);
    $endDay = (int) date('j', $eTime);
    $endMonthName = $trMonths[(int) date('n', $eTime)] ?? date('F', $eTime);

    $rootFolder = sprintf('%d %s - %d %s Tarihleri Arasında Yapılan İşlemler', $startDay, $startMonthName, $endDay, $endMonthName);
    $zipYolu = sys_get_temp_dir() . '/' . uniqid('kacak_teslim_zip_', true) . '.zip';
    $zip = new \ZipArchive();

    if ($zip->open($zipYolu, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('Arşiv dosyası oluşturulamadı.');
    }

    $rootDiskPath = KacakKontrolModel::rootPath();
    $toplamDosyaSayisi = 0;
    $geciciDosyalarZip = [];

    $kacakIds = array_map(static fn(array $k): int => (int) $k['id'], $liste);
    $tumFotolarGrouped = $Kacak->getPhotosByKacakIds($kacakIds);

    foreach ($liste as $kayit) {
        $kacakId = (int) $kayit['id'];
        $detay = $kayit;
        $ilceName = Helper::trUpper(trim((string) ($kayit['ilce'] ?? 'BELİRTİLMEMİŞ')));
        $tutanakNo = trim((string) ($kayit['tutanak_no'] ?? ''));
        $aboneAdi = Helper::trUpper(trim((string) ($kayit['abone_adi'] ?? '')));
        $tur = Helper::trUpper(trim((string) ($kayit['tur'] ?? 'KAÇAK')));

        $folderParts = [];
        if ($tutanakNo !== '') {
            $folderParts[] = $tutanakNo;
        } else {
            $folderParts[] = 'KAYIT_' . $kacakId;
        }
        if ($aboneAdi !== '') {
            $folderParts[] = $aboneAdi;
        }

        $rawTutanakFolder = implode(' - ', $folderParts) . ' (' . $tur . ')';
        $tutanakFolder = preg_replace('/[\/\\\\:\*\?"<>\|]/u', '_', trim($rawTutanakFolder));

        $recordPathInZip = $rootFolder . '/' . $ilceName . '/' . $tutanakFolder;
        $zip->addEmptyDir($recordPathInZip);

        $fotolar = $tumFotolarGrouped[$kacakId] ?? [];
        $tutanakSeq = 1;
        $sahaSeq = 1;
        $iptalSeq = 1;
        $videoSeq = 1;
        $sahaFotolariZip = [];

        foreach ($fotolar as $foto) {
            $kaynak = $rootDiskPath . '/' . ltrim($foto['dosya_yolu'], '/');
            if (!is_file($kaynak)) continue;

            $origExt = strtolower(pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION));
            $fotoTur = strtolower($foto['tur'] ?? 'saha');
            $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');

            if ($fotoTur === 'tutanak') {
                $prefix = 'tutanak';
                $seq = $tutanakSeq++;
            } elseif ($fotoTur === 'iptal') {
                $prefix = 'iptal';
                $seq = $iptalSeq++;
            } elseif ($medyaTipi === 'video') {
                $prefix = 'video';
                $seq = $videoSeq++;
            } else {
                $prefix = 'saha';
                $seq = $sahaSeq++;
            }

            $isPdf = ($origExt === 'pdf');
            $isVideo = ($medyaTipi === 'video' || in_array($origExt, ['mp4', 'mov', 'webm', '3gp'], true));
            $ext = $origExt ?: ($isPdf ? 'pdf' : ($isVideo ? 'mp4' : 'jpeg'));
            $entryName = $recordPathInZip . '/' . $dosyaAdi;
            $zip->addFile($kaynak, $entryName, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
            $zip->setCompressionName($entryName, \ZipArchive::CM_STORE);
            $toplamDosyaSayisi++;
        }
    }

    foreach ($geciciDosyalarZip as $tmpF) {
        @unlink($tmpF);
    }

    $zip->close();

    if (!is_file($zipYolu)) {
        http_response_code(500);
        exit('ZIP dosyası hazırlanamadı.');
    }

    $logModel = new App\Model\SystemLogModel();
    $logModel->logAction(
        $userId,
        'Teslim Alma Listesi Toplu İndirme (ZIP)',
        "Aralık: $baslangic - $bitis, Kayıt Sayısı: " . count($liste) . ", Dosya Sayısı: $toplamDosyaSayisi, Klasör: $rootFolder",
        App\Model\SystemLogModel::LEVEL_INFO
    );

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $zipDownloadName = $rootFolder . '.zip';
    $asciiZipAdi = Helper::toAscii($zipDownloadName);
    $encodedZipAdi = rawurlencode($zipDownloadName);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $asciiZipAdi . '"; filename*=UTF-8\'\'' . $encodedZipAdi);
    header('Content-Length: ' . filesize($zipYolu));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($zipYolu);
    @unlink($zipYolu);
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$basligStili = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F4B7C']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];

$kenarlik = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
];

if ($tip === 'teslim') {
    $sheet->setTitle('Teslim Alma Listesi');
    $basliklar = ['TARİH', 'TUTANAK NO', 'MÜKELLEF ADI', 'İLÇE', 'TÜR', 'EKİP', 'SEBEP', 'FOTO ÇIKTISI', 'TESLİM DURUMU'];
    $sheet->fromArray($basliklar, null, 'A1');
    $sheet->getStyle('A1:I1')->applyFromArray($basligStili);

    $satir = 2;
    $teslimListe = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, (array) ($istek['tokens'] ?? []));
    if (empty($teslimListe)) {
        http_response_code(422);
        exit('İndirmek için en az bir kayıt seçmelisiniz.');
    }
    foreach ($teslimListe as $kayit) {
        $sheet->fromArray([
            Date::dmY($kayit['tarih']),
            $kayit['tutanak_no'],
            $kayit['abone_adi'],
            Helper::trUpper((string) $kayit['ilce']),
            Helper::trUpper((string) $kayit['tur']),
            $kayit['ekip_adi'],
            $kayit['sebep'],
            $kayit['foto_cikti_gerekli'] ? 'GEREKLİ' : '-',
            $kayit['teslim_durumu'],
        ], null, 'A' . $satir);
        $satir++;
    }

    $sonSatir = max(1, $satir - 1);
    $sheet->getStyle('A1:I' . $sonSatir)->applyFromArray($kenarlik);
    foreach (range('A', 'I') as $sutun) {
        $sheet->getColumnDimension($sutun)->setAutoSize(true);
    }

    $dosyaAdi = 'Kacak_Teslim_Alma_Listesi_' . $baslangic . '_' . $bitis . '.xlsx';
} elseif ($tip === 'kayitlar') {
    $filters = [
        'tarih_baslangic' => $baslangic,
        'tarih_bitis' => $bitis,
        'ilce' => $_GET['ilce'] ?? '',
        'tur' => $_GET['tur'] ?? '',
        'arama' => $_GET['arama'] ?? '',
        'durum' => $_GET['durum'] ?? 'aktif',
        'onay_durumu' => $_GET['onay_durumu'] ?? 'onaylandi',
    ];

    $sheet->setTitle('Kaçak Kontrol Kayıtları');
    $basliklar = ['TARİH', 'BİLDİRİM TARİHİ', 'TUTANAK NO', 'ABONE ADI', 'İLÇE', 'TÜR', 'SAYAÇ NO', 'SAYI', 'EKİP', 'KAYNAK', 'FOTO SAYISI', 'DURUM'];
    $sheet->fromArray($basliklar, null, 'A1');
    $sheet->getStyle('A1:L1')->applyFromArray($basligStili);

    $records = $Kacak->getRecords($filters);
    $satir = 2;
    foreach ($records as $kayit) {
        $kaynakMap = ['pwa' => 'Mobil', 'masaustu' => 'Masaüstü', 'excel' => 'Excel'];
        $kaynakLabel = $kaynakMap[$kayit['kaynak']] ?? ($kayit['kaynak'] ?? '-');

        $durumLabel = 'Aktif';
        if ($kayit['onay_durumu'] === 'beklemede') {
            $durumLabel = 'Onay Bekliyor';
        } elseif ($kayit['onay_durumu'] === 'reddedildi') {
            $durumLabel = 'Reddedildi';
        } elseif ($kayit['durum'] === 'iptal') {
            $durumLabel = ($kayit['hakedisten_dus'] == 1) ? 'İptal (Düşüldü)' : 'İptal';
        }

        $bildirimTarihiFmt = !empty($kayit['olusturma_tarihi']) && $kayit['olusturma_tarihi'] !== '0000-00-00 00:00:00'
            ? date('d.m.Y H:i', strtotime($kayit['olusturma_tarihi']))
            : '-';

        $sheet->fromArray([
            Date::dmY($kayit['tarih']),
            $bildirimTarihiFmt,
            $kayit['tutanak_no'],
            $kayit['abone_adi'],
            $kayit['ilce'],
            $kayit['tur'],
            $kayit['sayac_no'],
            (int) $kayit['sayi'],
            $kayit['ekip_adi'],
            $kaynakLabel,
            (int) ($kayit['foto_sayisi'] ?? 0),
            $durumLabel
        ], null, 'A' . $satir);
        $satir++;
    }

    $sonSatir = max(1, $satir - 1);
    $sheet->getStyle('A1:L' . $sonSatir)->applyFromArray($kenarlik);
    foreach (range('A', 'L') as $sutun) {
        $sheet->getColumnDimension($sutun)->setAutoSize(true);
    }

    $dosyaAdi = 'Kacak_Kontrol_Kayitlari_' . $baslangic . '_' . $bitis . '.xlsx';
} else {
    $sheet->setTitle('Bölge Bazlı Özet');
    $sheet->setCellValue('A1', 'BÖLGE (İLÇE) BAZLI ABONESİZ / KAÇAK / USÜLSÜZ ÖZETİ');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->applyFromArray($basligStili);

    $sheet->setCellValue('A2', Date::dmY($baslangic) . ' - ' . Date::dmY($bitis));
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->fromArray(['İLÇE', 'ABONESİZ', 'KAÇAK', 'USÜLSÜZ', 'TOPLAM'], null, 'A3');
    $sheet->getStyle('A3:E3')->applyFromArray($basligStili);

    $satir = 4;
    $toplamAbonesiz = $toplamKacak = $toplamUsulsuz = 0;
    foreach ($Kacak->getBolgeBazliOzet($baslangic, $bitis) as $kayit) {
        $sheet->fromArray([
            Helper::trUpper((string) $kayit['ilce']),
            (int) $kayit['abonesiz'],
            (int) $kayit['kacak'],
            (int) $kayit['usulsuz'],
            (int) $kayit['toplam'],
        ], null, 'A' . $satir);
        $toplamAbonesiz += (int) $kayit['abonesiz'];
        $toplamKacak += (int) $kayit['kacak'];
        $toplamUsulsuz += (int) $kayit['usulsuz'];
        $satir++;
    }

    $sheet->fromArray([
        'GENEL TOPLAM',
        $toplamAbonesiz,
        $toplamKacak,
        $toplamUsulsuz,
        $toplamAbonesiz + $toplamKacak + $toplamUsulsuz,
    ], null, 'A' . $satir);
    $sheet->getStyle('A' . $satir . ':E' . $satir)->getFont()->setBold(true);

    $sheet->getStyle('A3:E' . $satir)->applyFromArray($kenarlik);
    $sheet->getStyle('B4:E' . $satir)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    foreach (range('A', 'E') as $sutun) {
        $sheet->getColumnDimension($sutun)->setAutoSize(true);
    }

    $dosyaAdi = 'Kacak_Bolge_Bazli_Ozet_' . $baslangic . '_' . $bitis . '.xlsx';
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $dosyaAdi . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;
