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
    exit('Yetkisiz erişim.');
}

if (!Gate::allows('kacak_islemleri') && !Gate::allows('kacak/list') && !Gate::allows('kacak_duzenle') && !Gate::allows('kacak_onay') && !Gate::allows('kacak_arsiv') && !Gate::isSuperAdmin()) {
    http_response_code(403);
    exit('Yetkisiz erişim.');
}

$istek = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
$tip = $istek['tip'] ?? 'ozet';
$baslangic = Date::convertExcelDate($istek['start_date'] ?? '', 'Y-m-d') ?: date('Y-m-d', strtotime('monday this week'));
$bitis = Date::convertExcelDate($istek['end_date'] ?? '', 'Y-m-d') ?: date('Y-m-d');

$Kacak = new KacakKontrolModel();

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

@set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('pcre.backtrack_limit', '10000000');
ini_set('memory_limit', '1024M');

function getKacakFotoGorselYolu(string $kaynak, array &$geciciDosyalar): ?string
{
    if (!is_file($kaynak)) {
        return null;
    }
    $ext = strtolower(pathinfo($kaynak, PATHINFO_EXTENSION));
    // mPDF doğrudan jpg, jpeg, png, gif dosyalarını yerel disk yolundan okuyabilir
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
        return $kaynak;
    }
    // WebP veya diğer formatlar için geçici JPEG dosyası oluştur
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

    // Fotoğraf Grid Alanı (Sadece resimler yer alır, metin ve başlıklar kaldırılmıştır)
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
        if ($fotoAdet % 3 !== 0) {
            $kalan = 3 - ($fotoAdet % 3);
            for ($k = 0; $k < $kalan; $k++) {
                $html .= '<td class="photo-cell" style="width: 33.33%; border: none;"></td>';
            }
        }
        $html .= '</tr></table>';
    } elseif ($fotoAdet <= 8) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < $fotoAdet; $i++) {
            if ($i > 0 && $i % 4 === 0) {
                $html .= '</tr><tr>';
            }
            $f = $sahaFotolari[$i];
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 25%; height: 138mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 47mm; max-height: 134mm;" />
            </td>';
        }
        if ($fotoAdet % 4 !== 0) {
            $kalan = 4 - ($fotoAdet % 4);
            for ($k = 0; $k < $kalan; $k++) {
                $html .= '<td class="photo-cell" style="width: 25%; border: none;"></td>';
            }
        }
        $html .= '</tr></table>';
    } else {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < $fotoAdet; $i++) {
            if ($i > 0 && $i % 3 === 0) {
                $html .= '</tr><tr>';
            }
            $f = $sahaFotolari[$i];
            $gorselSrc = $esc($f['dosya_yolu_disk']);
            $html .= '<td class="photo-cell" style="width: 33.33%; height: 90mm;">
                <img src="' . $gorselSrc . '" class="photo-img" style="max-width: 63mm; max-height: 86mm;" />
            </td>';
        }
        if ($fotoAdet % 3 !== 0) {
            $kalan = 3 - ($fotoAdet % 3);
            for ($k = 0; $k < $kalan; $k++) {
                $html .= '<td class="photo-cell" style="width: 33.33%; border: none;"></td>';
            }
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
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .header-table td { padding: 3px 6px; font-size: 8.5pt; border: 1px solid #cbd5e1; }
        .header-title-box { background: #1e3a8a; color: #ffffff; text-align: center; font-weight: bold; font-size: 11pt; padding: 6px; letter-spacing: 0.5px; }
        .info-label { font-weight: bold; color: #475569; width: 18%; background: #f8fafc; }
        .info-value { color: #0f172a; width: 32%; font-weight: 600; }
        .photo-grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .photo-cell { text-align: center; vertical-align: middle; padding: 3px; border: 1px solid #e2e8f0; background: #ffffff; }
        .photo-img { vertical-align: middle; border: 1px solid #cbd5e1; border-radius: 2px; }
        .photo-caption { font-size: 7.5pt; color: #64748b; margin-top: 2px; text-align: center; }
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

if ($tip === 'teslim_foto_pdf') {
    $seciliListe = seciliTeslimKayitlari($Kacak, $baslangic, $bitis, (array) ($istek['tokens'] ?? []));
    // Sadece fotoğraf çıktısı gerekli olanları filtrele (Onikişubat / Dulkadiroğlu Kaçak kayıtları)
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
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .header-table td { padding: 3px 6px; font-size: 8.5pt; border: 1px solid #cbd5e1; }
        .header-title-box { background: #1e3a8a; color: #ffffff; text-align: center; font-weight: bold; font-size: 11pt; padding: 6px; letter-spacing: 0.5px; }
        .info-label { font-weight: bold; color: #475569; width: 18%; background: #f8fafc; }
        .info-value { color: #0f172a; width: 32%; font-weight: 600; }
        .photo-grid-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .photo-cell { text-align: center; vertical-align: middle; padding: 3px; border: 1px solid #e2e8f0; background: #ffffff; }
        .photo-img { vertical-align: middle; border: 1px solid #cbd5e1; border-radius: 2px; }
        .photo-caption { font-size: 7.5pt; color: #64748b; margin-top: 2px; text-align: center; }
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

            // Fotoğrafları toplu önbellekten al: Tutanak, İptal ve Video hariç, sadece saha tespit fotoğrafları
            $tumFotolar = $tumFotolarGrouped[$kacakId] ?? [];
            $sahaFotolari = [];

            foreach ($tumFotolar as $foto) {
                $fotoTur = strtolower($foto['tur'] ?? 'saha');
                $medyaTipi = strtolower($foto['medya_tipi'] ?? 'foto');
                $ext = strtolower(pathinfo($foto['dosya_yolu'] ?? '', PATHINFO_EXTENSION));

                if ($fotoTur === 'tutanak' || $fotoTur === 'iptal') {
                    continue;
                }
                if ($medyaTipi === 'video' || in_array($ext, ['mp4', 'mov', 'webm', '3gp', 'avi', 'mkv', 'pdf'], true)) {
                    continue;
                }

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

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $mpdf->Output($dosyaAdi, 'D');

        // Geçici dosyaları temizle
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
            if (!is_file($kaynak)) {
                continue;
            }

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

            // Dosyayı doğrudan disktan arşive akıt (Bellek ve CPU tasarrufu)
            $zip->addFile($kaynak, $recordPathInZip . '/' . $dosyaAdi, 0, 0, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);

            // Saha fotoğrafı ise tek sayfa PDF için biriktir
            if ($fotoTur === 'saha' && !$isVideo && !$isPdf) {
                $gorselDisk = getKacakFotoGorselYolu($kaynak, $geciciDosyalarZip);
                if ($gorselDisk !== null) {
                    $fotoCopy = $foto;
                    $fotoCopy['dosya_yolu_disk'] = $gorselDisk;
                    $sahaFotolariZip[] = $fotoCopy;
                }
            }

            $toplamDosyaSayisi++;
        }

        // Tek sayfa A4 saha fotoğrafları PDF'ini de ZIP içerisindeki klasöre ekle
        if (!empty($sahaFotolariZip)) {
            $pdfBinary = uretKacakTekSayfaPdfBinary($detay, $sahaFotolariZip, $geciciDosyalarZip);
            if ($pdfBinary !== null) {
                $pdfDosyaAdi = sprintf('foto_ciktisi_%s.pdf', $tutanakNo ?: ('kayit_' . $kacakId));
                $zip->addFromString($recordPathInZip . '/' . $pdfDosyaAdi, $pdfBinary, \ZipArchive::FL_OVERWRITE | \ZipArchive::FL_ENC_UTF_8);
                $toplamDosyaSayisi++;
            }
        }
    }

    // Geçici dosyaları temizle
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
    $encodedZipAdi = rawurlencode($zipDownloadName);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $zipDownloadName) . '"; filename*=UTF-8\'\'' . $encodedZipAdi);
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
