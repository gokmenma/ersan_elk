<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\PersonelIsRaporuModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$personelId = (int) ($_GET['personel_id'] ?? 0);

if ($firmaId <= 0 || $personelId <= 0) {
    die('Geçersiz istek veya yetkisiz erişim.');
}

$model = new PersonelIsRaporuModel();
$personelModel = new PersonelModel();
$firmaModel = new FirmaModel();

$firma = $firmaModel->find($firmaId);
$firmaAdi = $firma ? $firma->firma_adi : 'Firma';

$personel = $personelModel->find($personelId);
$personelAdi = $personel ? $personel->adi_soyadi : 'Personel';

$filterType = $_GET['filter_type'] ?? 'period';
$year = (int) ($_GET['year'] ?? date('Y'));
$month = str_pad((string) ($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$category = trim($_GET['category'] ?? '');

if ($filterType === 'period') {
    $calcStart = sprintf('%04d-%02d-01', $year, $month);
    $calcEnd = date('Y-m-t', strtotime($calcStart));
} else {
    $calcStart = !empty($startDate) ? date('Y-m-d', strtotime($startDate)) : date('Y-m-01');
    $calcEnd = !empty($endDate) ? date('Y-m-d', strtotime($endDate)) : date('Y-m-t');
}

$kpi = $model->getPersonelKpiSummary($firmaId, $personelId, $calcStart, $calcEnd);
$trend = $model->getDailyTrendData($firmaId, $personelId, $calcStart, $calcEnd);
$logs = $model->getDetailedWorkLogs($firmaId, $personelId, $calcStart, $calcEnd, $category ?: null, 5000);

$spreadsheet = new Spreadsheet();

// 1. Sayfa: Detaylı İşlem Listesi
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Detaylı İşlem Listesi');

// Başlık Alanı
$sheet->setCellValue('A1', mb_strtoupper($firmaAdi, 'UTF-8') . ' - PERSONEL İŞ TAKİP RAPORU');
$sheet->setCellValue('A2', 'Personel: ' . $personelAdi . ' | Dönem: ' . date('d.m.Y', strtotime($calcStart)) . ' - ' . date('d.m.Y', strtotime($calcEnd)));
$sheet->mergeCells('A1:H1');
$sheet->mergeCells('A2:H2');

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('555555'));

// Tablo Başlıkları
$headers = ['SIRA', 'TARİH', 'KATEGORİ', 'İŞ EMRİ TİPİ', 'İŞ EMRİ SONUCU', 'ABONE / SAYAÇ NO', 'BÖLGE / EKİP', 'ADET'];
$colIdx = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($colIdx . '4', $h);
    $colIdx++;
}

$sheet->getStyle('A4:H4')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3b82f6']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
]);

$rowNum = 5;
$sira = 1;
foreach ($logs as $log) {
    $sheet->setCellValue('A' . $rowNum, $sira++);
    $sheet->setCellValue('B' . $rowNum, date('d.m.Y', strtotime($log['tarih'])));
    $sheet->setCellValue('C' . $rowNum, $log['kategori_adi'] ?? $log['kategori']);
    $sheet->setCellValue('D' . $rowNum, $log['is_emri_tipi'] ?? '-');
    $sheet->setCellValue('E' . $rowNum, $log['is_emri_sonucu'] ?? '-');
    $sheet->setCellValue('F' . $rowNum, $log['abone_no'] ?? '-');
    $sheet->setCellValue('G' . $rowNum, trim(($log['bolge'] ?? '') . ' ' . ($log['ekip'] ?? '')));
    $sheet->setCellValue('H' . $rowNum, (int) ($log['adet'] ?? 1));

    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $rowNum++;
}

// Toplam Satırı
$sheet->setCellValue('A' . $rowNum, 'GENEL TOPLAM');
$sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
$sheet->setCellValue('H' . $rowNum, '=SUM(H5:H' . ($rowNum - 1) . ')');

$sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'f1f5f9']],
    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN]]
]);

// 2. Sayfa: Günlük Özet
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Günlük Özet');

$sheet2->setCellValue('A1', 'GÜNLÜK İŞ DAĞILIMI - ' . $personelAdi);
$sheet2->mergeCells('A1:H1');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);

$headers2 = ['TARİH', 'GÜN', 'KESME / AÇMA', 'ENDEKS OKUMA', 'SAYAÇ DEĞİŞİM', 'MÜHÜRLEME', 'KAÇAK İŞLEMLERİ', 'GÜNLÜK TOPLAM'];
$cIdx = 'A';
foreach ($headers2 as $h2) {
    $sheet2->setCellValue($cIdx . '3', $h2);
    $cIdx++;
}

$sheet2->getStyle('A3:H3')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '10b981']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

$rNum2 = 4;
foreach ($trend['daily_list'] ?? [] as $dRow) {
    $sheet2->setCellValue('A' . $rNum2, $dRow['tarih_tr']);
    $sheet2->setCellValue('B' . $rNum2, $dRow['gun_adi']);
    $sheet2->setCellValue('C' . $rNum2, $dRow['kesme_acma']);
    $sheet2->setCellValue('D' . $rNum2, $dRow['endeks_okuma']);
    $sheet2->setCellValue('E' . $rNum2, $dRow['sayac_degisim']);
    $sheet2->setCellValue('F' . $rNum2, $dRow['muhurleme']);
    $sheet2->setCellValue('G' . $rNum2, $dRow['kacak_kontrol']);
    $sheet2->setCellValue('H' . $rNum2, $dRow['toplam']);

    $sheet2->getStyle('A' . $rNum2 . ':B' . $rNum2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('C' . $rNum2 . ':H' . $rNum2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $rNum2++;
}

$sheet2->setCellValue('A' . $rNum2, 'TOPLAM');
$sheet2->mergeCells('A' . $rNum2 . ':B' . $rNum2);
$sheet2->setCellValue('C' . $rNum2, '=SUM(C4:C' . ($rNum2 - 1) . ')');
$sheet2->setCellValue('D' . $rNum2, '=SUM(D4:D' . ($rNum2 - 1) . ')');
$sheet2->setCellValue('E' . $rNum2, '=SUM(E4:E' . ($rNum2 - 1) . ')');
$sheet2->setCellValue('F' . $rNum2, '=SUM(F4:F' . ($rNum2 - 1) . ')');
$sheet2->setCellValue('G' . $rNum2, '=SUM(G4:G' . ($rNum2 - 1) . ')');
$sheet2->setCellValue('H' . $rNum2, '=SUM(H4:H' . ($rNum2 - 1) . ')');

$sheet2->getStyle('A' . $rNum2 . ':H' . $rNum2)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e2e8f0']]
]);

// Otomatik sütun genişlikleri
foreach ([$sheet, $sheet2] as $s) {
    foreach (range('A', 'H') as $col) {
        $s->getColumnDimension($col)->setAutoSize(true);
    }
}

$fileName = 'Personel_Is_Raporu_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $personelAdi) . '_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
