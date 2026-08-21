<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
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

if (!Gate::allows('kacak_islemleri') && !Gate::isSuperAdmin()) {
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
    $tumListe = $model->getTeslimAlmaListesi($baslangic, $bitis);
    if (empty($tokenlar)) {
        return $tumListe;
    }
    $ids = [];
    foreach ($tokenlar as $token) {
        $id = (int) Security::decrypt((string) $token);
        if ($id > 0) $ids[$id] = true;
    }
    if (empty($ids)) {
        return $tumListe;
    }
    return array_values(array_filter(
        $tumListe,
        static fn(array $kayit): bool => isset($ids[(int) $kayit['id']])
    ));
}

function uretKacakFotoPdfHtml(array $detay, array $sahaFotolari): string
{
    $esc = static fn($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

    $tutanakNo = $esc($detay['tutanak_no'] ?? '-');
    $tarihFmt = Date::dmY($detay['tarih'] ?? '');
    $aboneAdi = $esc(mb_strtoupper(trim((string) ($detay['abone_adi'] ?? '-')), 'UTF-8'));
    $ilce = $esc(mb_strtoupper(trim((string) ($detay['ilce'] ?? '-')), 'UTF-8'));
    $tur = $esc(mb_strtoupper(trim((string) ($detay['tur'] ?? 'KAÇAK')), 'UTF-8'));
    $ekip = $esc($detay['ekip_adi'] ?? '-');
    $sayacNo = $esc($detay['sayac_no'] ?? '-');
    $tesisatNo = $esc($detay['tesisat_no'] ?? '-');

    $html = '<div class="page-container">';
    
    // Üst Bilgi Başlık Tablosu
    $html .= '<table class="header-table">
        <tr>
            <td colspan="4" class="header-title-box">KAÇAK ELEKTRİK KONTROLÜ — SAHA TESPİT FOTOĞRAFLARI</td>
        </tr>
        <tr>
            <td class="info-label">TUTANAK NO:</td>
            <td class="info-value" style="font-size: 10pt; color: #1e3a8a;">' . $tutanakNo . '</td>
            <td class="info-label">KONTROL TARİHİ:</td>
            <td class="info-value">' . $tarihFmt . '</td>
        </tr>
        <tr>
            <td class="info-label">MÜKELLEF / ABONE:</td>
            <td class="info-value">' . $aboneAdi . '</td>
            <td class="info-label">İLÇE / BÖLGE:</td>
            <td class="info-value">' . $ilce . ' (' . $tur . ')</td>
        </tr>
        <tr>
            <td class="info-label">KONTROL EKİBİ:</td>
            <td class="info-value">' . $ekip . '</td>
            <td class="info-label">SAYAÇ / TESİSAT:</td>
            <td class="info-value">' . $sayacNo . ' / ' . $tesisatNo . '</td>
        </tr>
    </table>';

    // Fotoğraf Grid Alanı (Tek A4 sayfasına sığdırılır)
    $fotoAdet = count($sahaFotolari);

    if ($fotoAdet === 0) {
        $html .= '<div class="no-photo-box">
            <p style="margin: 0; font-weight: bold; font-size: 12pt; color: #475569;">Saha Tespit Fotoğrafı Bulunamadı</p>
            <p style="margin: 4px 0 0 0; font-size: 9pt;">Bu tutanağa ait sisteme yüklenmiş saha tespit fotoğrafı bulunmamaktadır.</p>
        </div>';
    } elseif ($fotoAdet === 1) {
        $f = $sahaFotolari[0];
        $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Saha Fotoğrafı 1';
        $html .= '<div style="text-align: center; height: 228mm; line-height: 228mm;">
            <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 190mm; max-height: 220mm;" />
            <div class="photo-caption">' . $esc($caption) . '</div>
        </div>';
    } elseif ($fotoAdet === 2) {
        $html .= '<table class="photo-grid-table" style="height: 228mm;"><tr>';
        foreach ($sahaFotolari as $idx => $f) {
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($idx + 1);
            $html .= '<td class="photo-cell" style="width: 50%; height: 224mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 92mm; max-height: 215mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
            </td>';
        }
        $html .= '</tr></table>';
    } elseif ($fotoAdet === 3) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < 2; $i++) {
            $f = $sahaFotolari[$i];
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($i + 1);
            $html .= '<td class="photo-cell" style="width: 50%; height: 110mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 92mm; max-height: 104mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
            </td>';
        }
        $html .= '</tr><tr>';
        $f = $sahaFotolari[2];
        $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf 3';
        $html .= '<td colspan="2" class="photo-cell" style="width: 100%; height: 110mm;">
            <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 92mm; max-height: 104mm;" />
            <div class="photo-caption">' . $esc($caption) . '</div>
        </td>';
        $html .= '</tr></table>';
    } elseif ($fotoAdet === 4) {
        $html .= '<table class="photo-grid-table"><tr>';
        for ($i = 0; $i < 4; $i++) {
            if ($i === 2) {
                $html .= '</tr><tr>';
            }
            $f = $sahaFotolari[$i];
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($i + 1);
            $html .= '<td class="photo-cell" style="width: 50%; height: 110mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 92mm; max-height: 104mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
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
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($i + 1);
            $html .= '<td class="photo-cell" style="width: 33.33%; height: 110mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 60mm; max-height: 104mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
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
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($i + 1);
            $html .= '<td class="photo-cell" style="width: 25%; height: 110mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 44mm; max-height: 104mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
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
            $caption = !empty($f['cekim_tarihi']) ? 'Çekim: ' . date('d.m.Y H:i', strtotime($f['cekim_tarihi'])) : 'Fotoğraf ' . ($i + 1);
            $html .= '<td class="photo-cell" style="width: 33.33%; height: 72mm;">
                <img src="' . $f['data_uri'] . '" class="photo-img" style="max-width: 60mm; max-height: 66mm;" />
                <div class="photo-caption">' . $esc($caption) . '</div>
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

function uretKacakTekSayfaPdfBinary(array $detay, array $sahaFotolari): ?string
{
    if (empty($sahaFotolari)) {
        return null;
    }
    $css = '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
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
</style>
</head>
<body>' . uretKacakFotoPdfHtml($detay, $sahaFotolari) . '</body></html>';

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
        $mpdf->WriteHTML($css);
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

    $html = '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
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
</style>
</head>
<body>';

    $toplamKayit = count($liste);
    $toplamFotoSayisi = 0;

    foreach ($liste as $index => $kayit) {
        $kacakId = (int) $kayit['id'];
        $detay = $Kacak->getRecord($kacakId) ?? $kayit;

        // Fotoğrafları al: Tutanak, İptal ve Video hariç, sadece saha tespit fotoğrafları
        $tumFotolar = $Kacak->getPhotos($kacakId);
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
            if (!is_file($kaynak)) {
                continue;
            }

            $jpegBinary = KacakKontrolModel::getAsJpegBinary($kaynak);
            if ($jpegBinary !== null) {
                $foto['data_uri'] = 'data:image/jpeg;base64,' . base64_encode($jpegBinary);
                $sahaFotolari[] = $foto;
                $toplamFotoSayisi++;
            }
        }

        $html .= uretKacakFotoPdfHtml($detay, $sahaFotolari);

        if ($index < ($toplamKayit - 1)) {
            $html .= '<pagebreak />';
        }
    }

    $html .= '</body></html>';

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
        $mpdf->WriteHTML($html);

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
        exit;
    } catch (\Throwable $e) {
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

    foreach ($liste as $kayit) {
        $kacakId = (int) $kayit['id'];
        $detay = $Kacak->getRecord($kacakId) ?? $kayit;
        $ilceName = mb_strtoupper(trim((string) ($kayit['ilce'] ?? 'BELİRTİLMEMİŞ')), 'UTF-8');

        $tutanakNo = trim((string) ($kayit['tutanak_no'] ?? ''));
        $aboneAdi = mb_strtoupper(trim((string) ($kayit['abone_adi'] ?? '')), 'UTF-8');
        $tur = mb_strtoupper(trim((string) ($kayit['tur'] ?? 'KAÇAK')), 'UTF-8');

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

        $fotolar = $Kacak->getPhotos($kacakId);
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

            if ($isPdf) {
                $ext = 'pdf';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNo ?: ('kayit_' . $kacakId), $seq, $ext);
                $zip->addFile($kaynak, $recordPathInZip . '/' . $dosyaAdi);
            } elseif ($isVideo) {
                $ext = $origExt ?: 'mp4';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNo ?: ('kayit_' . $kacakId), $seq, $ext);
                $zip->addFile($kaynak, $recordPathInZip . '/' . $dosyaAdi);
            } else {
                $ext = 'jpeg';
                $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNo ?: ('kayit_' . $kacakId), $seq, $ext);
                $jpegData = KacakKontrolModel::getAsJpegBinary($kaynak);
                if ($jpegData !== null) {
                    $zip->addFromString($recordPathInZip . '/' . $dosyaAdi, $jpegData);
                    // Saha fotoğrafı ise tek sayfa PDF için biriktir
                    if ($fotoTur === 'saha' && $medyaTipi !== 'video') {
                        $fotoCopy = $foto;
                        $fotoCopy['data_uri'] = 'data:image/jpeg;base64,' . base64_encode($jpegData);
                        $sahaFotolariZip[] = $fotoCopy;
                    }
                } else {
                    $zip->addFile($kaynak, $recordPathInZip . '/' . $dosyaAdi);
                }
            }
            $toplamDosyaSayisi++;
        }

        // Tek sayfa A4 saha fotoğrafları PDF'ini de ZIP içerisindeki klasöre ekle
        if (!empty($sahaFotolariZip)) {
            $pdfBinary = uretKacakTekSayfaPdfBinary($detay, $sahaFotolariZip);
            if ($pdfBinary !== null) {
                $pdfDosyaAdi = sprintf('foto_ciktisi_%s.pdf', $tutanakNo ?: ('kayit_' . $kacakId));
                $zip->addFromString($recordPathInZip . '/' . $pdfDosyaAdi, $pdfBinary);
                $toplamDosyaSayisi++;
            }
        }
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
            mb_strtoupper((string) $kayit['ilce'], 'UTF-8'),
            mb_strtoupper((string) $kayit['tur'], 'UTF-8'),
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
            mb_strtoupper((string) $kayit['ilce'], 'UTF-8'),
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
