<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Helper\Date;
use App\Model\KacakKontrolModel;
use App\Service\Gate;
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

$tip = $_GET['tip'] ?? 'ozet';
$baslangic = Date::convertExcelDate($_GET['start_date'] ?? '', 'Y-m-d') ?: date('Y-m-d', strtotime('monday this week'));
$bitis = Date::convertExcelDate($_GET['end_date'] ?? '', 'Y-m-d') ?: date('Y-m-d');

$Kacak = new KacakKontrolModel();

if ($tip === 'teslim_zip') {
    $liste = $Kacak->getTeslimAlmaListesi($baslangic, $bitis);
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

        foreach ($fotolar as $foto) {
            $kaynak = $rootDiskPath . '/' . ltrim($foto['dosya_yolu'], '/');
            if (!is_file($kaynak)) {
                continue;
            }

            $ext = pathinfo($foto['dosya_yolu'], PATHINFO_EXTENSION);
            if (empty($ext)) {
                $ext = 'jpg';
            }

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

            $dosyaAdi = sprintf('%s_%s_%d.%s', $prefix, $tutanakNo ?: ('kayit_' . $kacakId), $seq, $ext);
            $zip->addFile($kaynak, $recordPathInZip . '/' . $dosyaAdi);
            $toplamDosyaSayisi++;
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
    $basliklar = ['TARİH', 'TUTANAK NO', 'MÜKELLEF ADI', 'İLÇE', 'DURUM', 'EKİP', 'SEBEP', 'FOTO ÇIKTISI'];
    $sheet->fromArray($basliklar, null, 'A1');
    $sheet->getStyle('A1:H1')->applyFromArray($basligStili);

    $satir = 2;
    foreach ($Kacak->getTeslimAlmaListesi($baslangic, $bitis) as $kayit) {
        $sheet->fromArray([
            Date::dmY($kayit['tarih']),
            $kayit['tutanak_no'],
            $kayit['abone_adi'],
            mb_strtoupper((string) $kayit['ilce'], 'UTF-8'),
            mb_strtoupper((string) $kayit['tur'], 'UTF-8'),
            $kayit['ekip_adi'],
            $kayit['sebep'],
            $kayit['foto_cikti_gerekli'] ? 'GEREKLİ' : '-',
        ], null, 'A' . $satir);
        $satir++;
    }

    $sonSatir = max(1, $satir - 1);
    $sheet->getStyle('A1:H' . $sonSatir)->applyFromArray($kenarlik);
    foreach (range('A', 'H') as $sutun) {
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
    $basliklar = ['TARİH', 'TUTANAK NO', 'ABONE ADI', 'İLÇE', 'TÜR', 'SAYAÇ NO', 'SAYI', 'EKİP', 'KAYNAK', 'FOTO SAYISI', 'DURUM'];
    $sheet->fromArray($basliklar, null, 'A1');
    $sheet->getStyle('A1:K1')->applyFromArray($basligStili);

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

        $sheet->fromArray([
            Date::dmY($kayit['tarih']),
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
    $sheet->getStyle('A1:K' . $sonSatir)->applyFromArray($kenarlik);
    foreach (range('A', 'K') as $sutun) {
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
