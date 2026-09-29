<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\SayacDegisimModel;
use App\Model\FirmaModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) {
    die('Geçersiz istek veya yetkisiz erişim.');
}

$Firma = new FirmaModel();
$firma = $Firma->getFirma($firmaId);
$firmaAdi = $firma->firma_adi ?? 'ER-SAN';

$compareTab = $_GET['compare_tab'] ?? 'okuma';
$compareMode = $_GET['compare_mode'] ?? 'personel';
$periodsRaw = $_GET['periods'] ?? [];
$region = $_GET['region'] ?? '';

if (empty($periodsRaw) || !is_array($periodsRaw)) {
    die('Karşılaştırma yapmak için en az 2 dönem seçmelisiniz.');
}

// Türkçe ay isimleri
$monthNames = [
    '01' => 'Ocak',
    '02' => 'Şubat',
    '03' => 'Mart',
    '04' => 'Nisan',
    '05' => 'Mayıs',
    '06' => 'Haziran',
    '07' => 'Temmuz',
    '08' => 'Ağustos',
    '09' => 'Eylül',
    '10' => 'Ekim',
    '11' => 'Kasım',
    '12' => 'Aralık'
];

// Build periods array
$periods = [];
foreach ($periodsRaw as $p) {
    $parts = explode('-', $p); // YYYY-MM
    if (count($parts) !== 2)
        continue;
    $year = $parts[0];
    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    $label = ($monthNames[$month] ?? $month) . ' ' . $year;
    $periods[] = [
        'start' => $startDate,
        'end' => $endDate,
        'label' => $label,
        'key' => $p
    ];
}

if (count($periods) < 2) {
    die('Karşılaştırma yapmak için en az 2 dönem seçmelisiniz.');
}

$Tanimlamalar = new TanimlamalarModel();
$EndeksOkuma = new EndeksOkumaModel();
$Puantaj = new PuantajModel();

// Fetch data based on tab
if ($compareTab === 'okuma') {
    $data = $EndeksOkuma->getComparisonByPeriods($periods, $region);
} elseif ($compareTab === 'kacakkontrol') {
    $data = $Puantaj->getKacakComparisonByPeriods($periods, $region);
} elseif ($compareTab === 'sokme_takma') {
    $SayacDegisim = new SayacDegisimModel();
    $data = $SayacDegisim->getComparisonByPeriods($periods, $region);
} else {
    $data = $Puantaj->getComparisonByPeriods($periods, $compareTab, $region);
}

$periodLabels = array_column($periods, 'label');

$tabNames = [
    'okuma' => 'Endeks Okuma',
    'kesme' => 'Kesme/Açma',
    'sokme_takma' => 'Sayaç Sökme Takma',
    'muhurleme' => 'Mühürleme',
    'kacakkontrol' => 'Kaçak İşlemleri'
];
$currentTabName = $tabNames[$compareTab] ?? 'Karşılaştırma';
$valueLabel = ($compareTab === 'okuma') ? 'Okunan Abone' : (($compareTab === 'kacakkontrol') ? 'Kaçak Sayısı' : 'Sonuçlanan İşlem');

function calcChange($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

function formatTrendText($change)
{
    if ($change > 0) {
        return '+' . number_format($change, 1, ',', '.') . '%';
    } elseif ($change < 0) {
        return number_format($change, 1, ',', '.') . '%';
    }
    return '0.0%';
}

$spreadsheet = new Spreadsheet();

// Shared Styles
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5156BE']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B3F8C']]]
];

$dataBorder = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]];

$totalRowStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
    'borders' => [
        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '94A3B8']],
        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '475569']],
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]
    ],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
];

// Helper to auto-fit columns with safety margin
function autoFitSheetColumns($sheet, $startColIndex, $endColIndex) {
    for ($i = $startColIndex; $i <= $endColIndex; $i++) {
        $colLetter = Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }
}

// ==========================================
// 1. SHEET: PERSONEL BAZLI
// ==========================================
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Personel Bazlı');

// Title Block
$sheet1->setCellValue('A1', mb_strtoupper($firmaAdi, 'UTF-8') . ' - ' . mb_strtoupper($currentTabName, 'UTF-8') . ' KARŞILAŞTIRMA RAPORU (PERSONEL BAZLI)');
$sheet1->setCellValue('A2', 'Karşılaştırma Dönemleri: ' . implode(', ', $periodLabels) . ' | Oluşturulma: ' . date('d.m.Y H:i'));
$sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet1->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

// Headers
$headers1 = ['SIRA', 'PERSONEL', 'EKİP', 'BÖLGE'];
foreach ($periodLabels as $pl) {
    $headers1[] = mb_strtoupper($pl, 'UTF-8');
}
$headers1[] = 'ORTALAMA';
$headers1[] = 'TREND';

$headerRow1 = 4;
$sheet1->getRowDimension($headerRow1)->setRowHeight(26);

$colIdx = 1;
foreach ($headers1 as $h) {
    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
    $sheet1->setCellValue($colLetter . $headerRow1, $h);
    $colIdx++;
}
$lastColIdx1 = count($headers1);
$lastColLetter1 = Coordinate::stringFromColumnIndex($lastColIdx1);
$sheet1->getStyle('A' . $headerRow1 . ':' . $lastColLetter1 . $headerRow1)->applyFromArray($headerStyle);

// Title merge
$sheet1->mergeCells('A1:' . $lastColLetter1 . '1');
$sheet1->mergeCells('A2:' . $lastColLetter1 . '2');

// Data
$personelData = $data['personel'] ?? [];
uasort($personelData, function ($a, $b) use ($periodLabels) {
    $totalA = array_sum(array_column($a['periods'], 'toplam'));
    $totalB = array_sum(array_column($b['periods'], 'toplam'));
    return $totalB - $totalA;
});

$rowNum = 5;
$sira = 1;
$columnTotals1 = array_fill_keys($periodLabels, 0);

foreach ($personelData as $key => $pData) {
    $sheet1->setCellValue('A' . $rowNum, $sira++);
    
    $pName = $pData['personel_adi'] ?? '-';
    if ($pName === '-' || strpos($pName, 'Eşleşmeyen Ekip') !== false) {
        $pName = 'Eşleşmeyen Ekip: ' . ($pData['ekip_adi'] ?? '');
    }
    $sheet1->setCellValue('B' . $rowNum, $pName);
    $sheet1->setCellValue('C' . $rowNum, $pData['ekip_adi'] ?? '-');
    $sheet1->setCellValue('D' . $rowNum, $pData['bolge'] ?? '-');

    $values = [];
    $pColIdx = 5;
    foreach ($periodLabels as $label) {
        $v = (float) ($pData['periods'][$label]['toplam'] ?? 0);
        $values[] = $v;
        $columnTotals1[$label] += $v;

        $cLetter = Coordinate::stringFromColumnIndex($pColIdx);
        $sheet1->setCellValue($cLetter . $rowNum, $v);
        $sheet1->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
        $sheet1->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $pColIdx++;
    }

    $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
    $firstVal = $values[0] ?? 0;
    $lastVal = end($values);
    $change = calcChange($lastVal, $firstVal);

    // Ortalama
    $avgColLetter = Coordinate::stringFromColumnIndex($pColIdx);
    $sheet1->setCellValue($avgColLetter . $rowNum, $avg);
    $sheet1->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet1->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet1->getStyle($avgColLetter . $rowNum)->getFont()->setBold(true);
    $pColIdx++;

    // Trend
    $trendColLetter = Coordinate::stringFromColumnIndex($pColIdx);
    $sheet1->setCellValue($trendColLetter . $rowNum, formatTrendText($change));
    $sheet1->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    if ($change > 0) {
        $sheet1->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('00875A'))->setBold(true);
    } elseif ($change < 0) {
        $sheet1->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DE350B'))->setBold(true);
    }

    // Alignments
    $sheet1->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet1->getStyle('C' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sheet1->getStyle('A' . $rowNum . ':' . $lastColLetter1 . $rowNum)->applyFromArray([
        'borders' => $dataBorder,
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);

    $rowNum++;
}

// Summary (Toplam) Row
$sheet1->setCellValue('A' . $rowNum, '');
$sheet1->setCellValue('B' . $rowNum, 'GENEL TOPLAM');
$sheet1->setCellValue('C' . $rowNum, '');
$sheet1->setCellValue('D' . $rowNum, '');

$footerValues1 = [];
$pColIdx = 5;
foreach ($periodLabels as $label) {
    $v = (float) ($columnTotals1[$label] ?? 0);
    $footerValues1[] = $v;

    $cLetter = Coordinate::stringFromColumnIndex($pColIdx);
    $sheet1->setCellValue($cLetter . $rowNum, $v);
    $sheet1->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet1->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $pColIdx++;
}
$footerAvg1 = count($footerValues1) > 0 ? round(array_sum($footerValues1) / count($footerValues1)) : 0;
$footerTrend1 = calcChange(end($footerValues1), reset($footerValues1));

// Footer Ortalama & Trend
$avgColLetter = Coordinate::stringFromColumnIndex($pColIdx);
$sheet1->setCellValue($avgColLetter . $rowNum, $footerAvg1);
$sheet1->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
$sheet1->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$pColIdx++;

$trendColLetter = Coordinate::stringFromColumnIndex($pColIdx);
$sheet1->setCellValue($trendColLetter . $rowNum, formatTrendText($footerTrend1));
$sheet1->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet1->getStyle('A' . $rowNum . ':' . $lastColLetter1 . $rowNum)->applyFromArray($totalRowStyle);
$sheet1->getRowDimension($rowNum)->setRowHeight(22);

autoFitSheetColumns($sheet1, 1, $lastColIdx1);
$sheet1->freezePane('E5');


// ==========================================
// 2. SHEET: BÖLGE BAZLI
// ==========================================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Bölge Bazlı');

$sheet2->setCellValue('A1', mb_strtoupper($firmaAdi, 'UTF-8') . ' - ' . mb_strtoupper($currentTabName, 'UTF-8') . ' KARŞILAŞTIRMA RAPORU (BÖLGE BAZLI)');
$sheet2->setCellValue('A2', 'Karşılaştırma Dönemleri: ' . implode(', ', $periodLabels) . ' | Oluşturulma: ' . date('d.m.Y H:i'));
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet2->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

$headers2 = ['SIRA', 'BÖLGE'];
foreach ($periodLabels as $pl) {
    $headers2[] = mb_strtoupper($pl, 'UTF-8');
}
$headers2[] = 'ORTALAMA';
$headers2[] = 'TREND';

$headerRow2 = 4;
$sheet2->getRowDimension($headerRow2)->setRowHeight(26);

$colIdx = 1;
foreach ($headers2 as $h) {
    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
    $sheet2->setCellValue($colLetter . $headerRow2, $h);
    $colIdx++;
}
$lastColIdx2 = count($headers2);
$lastColLetter2 = Coordinate::stringFromColumnIndex($lastColIdx2);
$sheet2->getStyle('A' . $headerRow2 . ':' . $lastColLetter2 . $headerRow2)->applyFromArray($headerStyle);

$sheet2->mergeCells('A1:' . $lastColLetter2 . '1');
$sheet2->mergeCells('A2:' . $lastColLetter2 . '2');

// Data
$bolgeData = $data['bolge'] ?? [];
ksort($bolgeData);

$rowNum = 5;
$sira = 1;
$columnTotals2 = array_fill_keys($periodLabels, 0);

foreach ($bolgeData as $bolgeName => $bData) {
    $sheet2->setCellValue('A' . $rowNum, $sira++);
    $sheet2->setCellValue('B' . $rowNum, $bolgeName ?: 'TANIMSIZ BÖLGE');

    $values = [];
    $bColIdx = 3;
    foreach ($periodLabels as $label) {
        $v = (float) ($bData['periods'][$label]['toplam'] ?? 0);
        $values[] = $v;
        $columnTotals2[$label] += $v;

        $cLetter = Coordinate::stringFromColumnIndex($bColIdx);
        $sheet2->setCellValue($cLetter . $rowNum, $v);
        $sheet2->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
        $sheet2->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $bColIdx++;
    }

    $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
    $firstVal = $values[0] ?? 0;
    $lastVal = end($values);
    $change = calcChange($lastVal, $firstVal);

    // Ortalama
    $avgColLetter = Coordinate::stringFromColumnIndex($bColIdx);
    $sheet2->setCellValue($avgColLetter . $rowNum, $avg);
    $sheet2->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet2->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet2->getStyle($avgColLetter . $rowNum)->getFont()->setBold(true);
    $bColIdx++;

    // Trend
    $trendColLetter = Coordinate::stringFromColumnIndex($bColIdx);
    $sheet2->setCellValue($trendColLetter . $rowNum, formatTrendText($change));
    $sheet2->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    if ($change > 0) {
        $sheet2->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('00875A'))->setBold(true);
    } elseif ($change < 0) {
        $sheet2->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DE350B'))->setBold(true);
    }

    $sheet2->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sheet2->getStyle('A' . $rowNum . ':' . $lastColLetter2 . $rowNum)->applyFromArray([
        'borders' => $dataBorder,
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);

    $rowNum++;
}

// Summary Row
$sheet2->setCellValue('A' . $rowNum, '');
$sheet2->setCellValue('B' . $rowNum, 'GENEL TOPLAM');

$footerValues2 = [];
$bColIdx = 3;
foreach ($periodLabels as $label) {
    $v = (float) ($columnTotals2[$label] ?? 0);
    $footerValues2[] = $v;

    $cLetter = Coordinate::stringFromColumnIndex($bColIdx);
    $sheet2->setCellValue($cLetter . $rowNum, $v);
    $sheet2->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet2->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $bColIdx++;
}
$footerAvg2 = count($footerValues2) > 0 ? round(array_sum($footerValues2) / count($footerValues2)) : 0;
$footerTrend2 = calcChange(end($footerValues2), reset($footerValues2));

$avgColLetter = Coordinate::stringFromColumnIndex($bColIdx);
$sheet2->setCellValue($avgColLetter . $rowNum, $footerAvg2);
$sheet2->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
$sheet2->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$bColIdx++;

$trendColLetter = Coordinate::stringFromColumnIndex($bColIdx);
$sheet2->setCellValue($trendColLetter . $rowNum, formatTrendText($footerTrend2));
$sheet2->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet2->getStyle('A' . $rowNum . ':' . $lastColLetter2 . $rowNum)->applyFromArray($totalRowStyle);
$sheet2->getRowDimension($rowNum)->setRowHeight(22);

autoFitSheetColumns($sheet2, 1, $lastColIdx2);
$sheet2->freezePane('C5');


// ==========================================
// 3. SHEET: FİRMA TOPLAM
// ==========================================
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Firma Toplam');

$sheet3->setCellValue('A1', mb_strtoupper($firmaAdi, 'UTF-8') . ' - ' . mb_strtoupper($currentTabName, 'UTF-8') . ' KARŞILAŞTIRMA RAPORU (FİRMA TOPLAM)');
$sheet3->setCellValue('A2', 'Karşılaştırma Dönemleri: ' . implode(', ', $periodLabels) . ' | Oluşturulma: ' . date('d.m.Y H:i'));
$sheet3->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet3->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

$headers3 = ['SIRA', 'METRİK / GÖSTERGE'];
foreach ($periodLabels as $pl) {
    $headers3[] = mb_strtoupper($pl, 'UTF-8');
}
$headers3[] = 'ORTALAMA';
$headers3[] = 'TREND';

$headerRow3 = 4;
$sheet3->getRowDimension($headerRow3)->setRowHeight(26);

$colIdx = 1;
foreach ($headers3 as $h) {
    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
    $sheet3->setCellValue($colLetter . $headerRow3, $h);
    $colIdx++;
}
$lastColIdx3 = count($headers3);
$lastColLetter3 = Coordinate::stringFromColumnIndex($lastColIdx3);
$sheet3->getStyle('A' . $headerRow3 . ':' . $lastColLetter3 . $headerRow3)->applyFromArray($headerStyle);

$sheet3->mergeCells('A1:' . $lastColLetter3 . '1');
$sheet3->mergeCells('A2:' . $lastColLetter3 . '2');

// Metrics Rows
$firmaData = $data['firma'] ?? [];
$metrics = [
    ['label' => 'Toplam ' . $valueLabel, 'key' => 'toplam'],
    ['label' => 'Aktif Personel Sayısı', 'key' => 'personel_sayisi']
];

$rowNum = 5;
$sira = 1;

foreach ($metrics as $metric) {
    $sheet3->setCellValue('A' . $rowNum, $sira++);
    $sheet3->setCellValue('B' . $rowNum, $metric['label']);

    $values = [];
    $mColIdx = 3;
    foreach ($periodLabels as $label) {
        $v = (float) ($firmaData[$label][$metric['key']] ?? 0);
        $values[] = $v;

        $cLetter = Coordinate::stringFromColumnIndex($mColIdx);
        $sheet3->setCellValue($cLetter . $rowNum, $v);
        $sheet3->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
        $sheet3->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $mColIdx++;
    }

    $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
    $firstVal = $values[0] ?? 0;
    $lastVal = end($values);
    $change = calcChange($lastVal, $firstVal);

    // Ortalama
    $avgColLetter = Coordinate::stringFromColumnIndex($mColIdx);
    $sheet3->setCellValue($avgColLetter . $rowNum, $avg);
    $sheet3->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet3->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet3->getStyle($avgColLetter . $rowNum)->getFont()->setBold(true);
    $mColIdx++;

    // Trend
    $trendColLetter = Coordinate::stringFromColumnIndex($mColIdx);
    $sheet3->setCellValue($trendColLetter . $rowNum, formatTrendText($change));
    $sheet3->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    if ($change > 0) {
        $sheet3->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('00875A'))->setBold(true);
    } elseif ($change < 0) {
        $sheet3->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DE350B'))->setBold(true);
    }

    $sheet3->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet3->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $sheet3->getStyle('A' . $rowNum . ':' . $lastColLetter3 . $rowNum)->applyFromArray([
        'borders' => $dataBorder,
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
    ]);

    $rowNum++;
}

// Kişi Başı Ortalama Row
$sheet3->setCellValue('A' . $rowNum, $sira++);
$sheet3->setCellValue('B' . $rowNum, 'Kişi Başı Ortalama');

$kisiBasiValues = [];
$mColIdx = 3;
foreach ($periodLabels as $label) {
    $toplam = (float) ($firmaData[$label]['toplam'] ?? 0);
    $pSayisi = (int) ($firmaData[$label]['personel_sayisi'] ?? 1);
    $kbVal = $pSayisi > 0 ? round($toplam / $pSayisi) : 0;
    $kisiBasiValues[] = $kbVal;

    $cLetter = Coordinate::stringFromColumnIndex($mColIdx);
    $sheet3->setCellValue($cLetter . $rowNum, $kbVal);
    $sheet3->getStyle($cLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet3->getStyle($cLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $mColIdx++;
}

$kbAvg = count($kisiBasiValues) > 0 ? round(array_sum($kisiBasiValues) / count($kisiBasiValues)) : 0;
$kbChange = calcChange(end($kisiBasiValues), reset($kisiBasiValues));

$avgColLetter = Coordinate::stringFromColumnIndex($mColIdx);
$sheet3->setCellValue($avgColLetter . $rowNum, $kbAvg);
$sheet3->getStyle($avgColLetter . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
$sheet3->getStyle($avgColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet3->getStyle($avgColLetter . $rowNum)->getFont()->setBold(true);
$mColIdx++;

$trendColLetter = Coordinate::stringFromColumnIndex($mColIdx);
$sheet3->setCellValue($trendColLetter . $rowNum, formatTrendText($kbChange));
$sheet3->getStyle($trendColLetter . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
if ($kbChange > 0) {
    $sheet3->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('00875A'))->setBold(true);
} elseif ($kbChange < 0) {
    $sheet3->getStyle($trendColLetter . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DE350B'))->setBold(true);
}

$sheet3->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet3->getStyle('B' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$sheet3->getStyle('A' . $rowNum . ':' . $lastColLetter3 . $rowNum)->applyFromArray([
    'borders' => $dataBorder,
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
]);

autoFitSheetColumns($sheet3, 1, $lastColIdx3);
$sheet3->freezePane('C5');


// ==========================================
// ACTIVE SHEET & OUTPUT
// ==========================================
if ($compareMode === 'bolge') {
    $spreadsheet->setActiveSheetIndex(1);
} elseif ($compareMode === 'firma') {
    $spreadsheet->setActiveSheetIndex(2);
} else {
    $spreadsheet->setActiveSheetIndex(0);
}

$cleanTabName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $compareTab);
$firstP = reset($periodsRaw);
$lastP = end($periodsRaw);
$filename = 'Karsilastirma_Raporu_' . $cleanTabName . '_' . $firstP . '_' . $lastP . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
