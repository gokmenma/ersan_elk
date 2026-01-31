<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Helper\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

$Tanimlamalar = new TanimlamalarModel();
$EndeksOkuma = new EndeksOkumaModel();
$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$activeTab = $_GET['tab'] ?? 'okuma';
$filterPersonelId = $_GET['personel_id'] ?? '';
$filterRegion = $_GET['region'] ?? '';

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$regions = $Tanimlamalar->getEkipBolgeleri();

if ($filterRegion) {
    $regions = array_filter($regions, function ($r) use ($filterRegion) {
        return $r == $filterRegion;
    });
}

$workTypes = [];
if ($activeTab === 'okuma') {
    $summary = $EndeksOkuma->getMonthlySummary($year, $month);
    $title = "Okuma Özet Raporu";
} elseif ($activeTab === 'kacakkontrol') {
    $summary = $Puantaj->getKacakSummary($year, $month);
    $title = "Kaçak Kontrol Özet Raporu";
} else {
    $summary = $Puantaj->getMonthlySummaryDetailed($year, $month);
    $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);

    // Fallback for sokme_takma if no records found
    if (empty($workTypes) && $activeTab === 'sokme_takma') {
        $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru('sokme');
    }

    // Fallback for kesme if no rapor_turu is set yet
    if (empty($workTypes) && $activeTab === 'kesme') {
        $workTypes = $Tanimlamalar->getUcretliIsTurleri();
    }

    $title = "İşlemleri Özet Raporu";
}

// Helper to generate short code
function getShortCode($text)
{
    $words = explode(' ', preg_replace('/[^A-ZÇĞİIÖŞÜ\s]/u', '', mb_strtoupper($text, 'UTF-8')));
    $code = '';
    foreach ($words as $word) {
        if (!empty($word))
            $code .= mb_substr($word, 0, 1, 'UTF-8');
    }
    return $code ?: mb_substr($text, 0, 2, 'UTF-8');
}

$workTypeCols = [];
if ($activeTab !== 'okuma' && !empty($workTypes)) {
    foreach ($workTypes as $ut) {
        $workTypeCols[] = [
            'id' => $ut->id,
            'name' => $ut->is_emri_sonucu,
            'code' => getShortCode($ut->is_emri_sonucu)
        ];
    }
}
$subColCount = count($workTypeCols) ?: 1;

$allPersonel = $Personel->all();
$personelMap = [];
foreach ($allPersonel as $p) {
    if ($p->ekip_no) {
        $personelMap[$p->ekip_no] = $p;
    }
}

$isUnmatchedReport = isset($_GET['unmatched']) && $_GET['unmatched'] == 1;

if ($isUnmatchedReport) {
    $unmatchedRecords = $Puantaj->getUnmatchedWorkResults($year, $month, $activeTab);
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Eşleşmeyen Kayıtlar');

    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F46A6A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];

    $headers = ['SIRA', 'TARİH', 'EKİP KODU', 'İSİM SOYİSİM', 'İŞ EMİR SONUCU (EŞLEŞMEYEN)'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $sheet->getColumnDimension($col)->setAutoSize(true);
        $col++;
    }
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    $row = 2;
    foreach ($unmatchedRecords as $idx => $rec) {
        $sheet->setCellValue('A' . $row, $idx + 1);
        $sheet->setCellValue('B' . $row, date('d.m.Y', strtotime($rec->tarih)));
        $sheet->setCellValue('C' . $row, $rec->ekip_kodu ?: '-');
        $sheet->setCellValue('D' . $row, $rec->personel_adi ?: '-');
        $sheet->setCellValue('E' . $row, $rec->is_emri_sonucu);
        $row++;
    }

    $sheet->getStyle('A2:E' . ($row - 1))->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ]);

    $filename = 'Eslesmeyen_Kayitlar_' . $activeTab . '_' . $year . '_' . $month . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rapor');

// Styles
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '556EE6']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$dataStyle = [
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$footerStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// Headers - Row 1
$headerRows = ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) ? 3 : 2;

$sheet->setCellValue('A1', 'SIRA');
$sheet->mergeCells('A1:A' . $headerRows);
$sheet->setCellValue('B1', 'EKİP KODU');
$sheet->mergeCells('B1:B' . $headerRows);

if ($activeTab !== 'kacakkontrol') {
    $sheet->setCellValue('C1', 'İSİM SOYİSİM');
    $sheet->mergeCells('C1:C' . $headerRows);
    $daysColStartIdx = 4;
} else {
    $daysColStartIdx = 3;
}

$sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($daysColStartIdx) . '1', 'GÜNLER');
$lastDayColIdx = ($daysColStartIdx - 1) + ($daysInMonth * $subColCount);
$lastDayCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastDayColIdx);
$sheet->mergeCells(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($daysColStartIdx) . '1:' . $lastDayCol . '1');

// Sadece okuma ve kacakkontrol için TOPLAM, BÖLGE TOPLAMI, BÖLGE ADI sütunlarını buradan tanımla
// Diğer detaylı raporlar için İŞLEM TOPLAMLARI bölümünde tanımlanacak
if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol' || empty($workTypeCols)) {
    $toplamColIdx = $lastDayColIdx + 1;
    $toplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($toplamColIdx);
    $sheet->setCellValue($toplamCol . '1', 'TOPLAM');
    $sheet->mergeCells($toplamCol . '1:' . $toplamCol . $headerRows);

    if ($activeTab !== 'kacakkontrol') {
        $bolgeToplamColIdx = $toplamColIdx + 1;
        $bolgeToplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($bolgeToplamColIdx);
        $sheet->setCellValue($bolgeToplamCol . '1', 'BÖLGE TOPLAMI');
        $sheet->mergeCells($bolgeToplamCol . '1:' . $bolgeToplamCol . $headerRows);

        $bolgeAdiColIdx = $bolgeToplamColIdx + 1;
        $bolgeAdiCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($bolgeAdiColIdx);
        $sheet->setCellValue($bolgeAdiCol . '1', 'BÖLGE ADI');
        $sheet->mergeCells($bolgeAdiCol . '1:' . $bolgeAdiCol . $headerRows);
        $lastCol = $bolgeAdiCol;
    } else {
        $lastCol = $toplamCol;
    }
}

// Headers - Row 2 (Days)
$colIndex = $daysColStartIdx;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $subColCount - 1);
    $sheet->setCellValue($startColLetter . '2', $d);
    if ($subColCount > 1) {
        $sheet->mergeCells($startColLetter . '2:' . $endColLetter . '2');
    }

    if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
        foreach ($workTypeCols as $wt) {
            $wtColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue($wtColLetter . '3', $wt['code']);
            // Dikey metin - yukarı doğru
            $sheet->getStyle($wtColLetter . '3')->getAlignment()->setTextRotation(90);
            $sheet->getColumnDimension($wtColLetter)->setWidth(4);
            $colIndex++;
        }
    } else {
        $colIndex++;
    }
}

// İŞLEM TOPLAMLARI için GENEL header'ı (Row 2 ve Row 3)
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    // İŞLEM TOPLAMLARI header'ı satır 1'de - colIndex şu an son gün kolonlarından sonra
    $islemToplamStartIdx = $colIndex;
    $islemToplamEndIdx = $colIndex + $subColCount - 1;
    
    $islemToplamStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($islemToplamStartIdx);
    $islemToplamEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($islemToplamEndIdx);
    
    // Row 1: İŞLEM TOPLAMLARI başlığı zaten toplamCol ile yönetiliyor, ancak bu bölüm ekstra
    $sheet->setCellValue($islemToplamStartCol . '1', 'İŞLEM TOPLAMLARI');
    if ($subColCount > 1) {
        $sheet->mergeCells($islemToplamStartCol . '1:' . $islemToplamEndCol . '1');
    }
    
    // Row 2: GENEL
    $sheet->setCellValue($islemToplamStartCol . '2', 'GENEL');
    if ($subColCount > 1) {
        $sheet->mergeCells($islemToplamStartCol . '2:' . $islemToplamEndCol . '2');
    }
    
    // Row 3: İş türü kodları
    $tempColIdx = $islemToplamStartIdx;
    foreach ($workTypeCols as $wt) {
        $wtColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($tempColIdx);
        $sheet->setCellValue($wtColLetter . '3', $wt['code']);
        // Dikey metin - yukarı doğru
        $sheet->getStyle($wtColLetter . '3')->getAlignment()->setTextRotation(90);
        $sheet->getColumnDimension($wtColLetter)->setWidth(4);
        $tempColIdx++;
    }
    
    // TOPLAM sütunu indexini güncelle
    $toplamColIdx = $islemToplamEndIdx + 1;
    $toplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($toplamColIdx);
    $sheet->setCellValue($toplamCol . '1', 'TOPLAM');
    $sheet->mergeCells($toplamCol . '1:' . $toplamCol . $headerRows);
    
    // BÖLGE TOPLAMI ve BÖLGE ADI sütunlarını güncelle
    $bolgeToplamColIdx = $toplamColIdx + 1;
    $bolgeToplamCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($bolgeToplamColIdx);
    $sheet->setCellValue($bolgeToplamCol . '1', 'BÖLGE TOPLAMI');
    $sheet->mergeCells($bolgeToplamCol . '1:' . $bolgeToplamCol . $headerRows);

    $bolgeAdiColIdx = $bolgeToplamColIdx + 1;
    $bolgeAdiCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($bolgeAdiColIdx);
    $sheet->setCellValue($bolgeAdiCol . '1', 'BÖLGE ADI');
    $sheet->mergeCells($bolgeAdiCol . '1:' . $bolgeAdiCol . $headerRows);
    $lastCol = $bolgeAdiCol;
}

// Row 3 için satır yüksekliği (dikey text için)
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    $sheet->getRowDimension(3)->setRowHeight(60);
}

$sheet->getStyle('A1:' . $lastCol . $headerRows)->applyFromArray($headerStyle);

// Data
$row = $headerRows + 1;
$sira = 1;
$dailyTotals = array_fill(1, $daysInMonth, 0);
$dailyDetailedTotals = [];
$grandTotal = 0;
$seenTeams = [];
$allKacakTeams = ($activeTab === 'kacakkontrol') ? array_keys($summary) : [];

foreach ($regions as $regionName) {
    $teams = $Tanimlamalar->getEkipKodlariByBolgeAll($regionName);

    if (empty($teams))
        continue;

    // Filtreleme (Personel Seçimi & Görev Bazlı)
    $teams = array_filter($teams, function ($team) use ($activeTab, $personelMap, $filterPersonelId) {
        $personel = $personelMap[$team->id] ?? null;

        // Personel seçilmişse sadece onu getir
        if ($filterPersonelId && (!$personel || $personel->id != $filterPersonelId)) {
            return false;
        }

        // Görev bazlı filtreleme
        if ($activeTab === 'kesme') {
            return $personel && mb_stripos($personel->gorev, 'KESME-AÇMA') !== false;
        } elseif ($activeTab === 'okuma') {
            return $personel && mb_stripos($personel->gorev, 'OKUMA') !== false;
        } elseif ($activeTab === 'sokme_takma') {
            return $personel && mb_stripos($personel->gorev, 'SÖKME') !== false;
        } elseif ($activeTab === 'muhurleme') {
            return $personel && mb_stripos($personel->gorev, 'MÜHÜRLEME') !== false;
        }
        return true;
    });

    if (empty($teams))
        continue;

    $regionStartRow = $row;
    $regionTotal = 0;

    foreach ($teams as $team) {
        $personel = $personelMap[$team->id] ?? null;
        $personelTotal = 0;
        $lookupKey = ($activeTab === 'kacakkontrol') ? $team->tur_adi : ($personel ? $personel->id : null);

        if (!$lookupKey || !isset($summary[$lookupKey])) {
            if ($activeTab === 'kacakkontrol' || $activeTab === 'okuma')
                continue;
        }

        if ($activeTab === 'kacakkontrol')
            $seenTeams[] = $team->tur_adi;

        $sheet->setCellValue('A' . $row, $sira++);
        $sheet->setCellValue('B' . $row, $team->tur_adi);
        if ($activeTab !== 'kacakkontrol') {
            $sheet->setCellValue('C' . $row, $personel ? $personel->adi_soyadi : '-');
        }

        $colIndex = $daysColStartIdx;
        $personelWorkTypeTotals = []; // Her iş türü için personel toplamı
        foreach ($workTypeCols as $wt) {
            $personelWorkTypeTotals[$wt['name']] = 0;
        }
        
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $val = ($lookupKey && isset($summary[$lookupKey][$d])) ? $summary[$lookupKey][$d] : 0;
                if ($val > 0) {
                    $sheet->setCellValue($colLetter . $row, $val);
                    $dailyTotals[$d] += $val;
                    $personelTotal += $val;
                }
                $colIndex++;
            } else {
                foreach ($workTypeCols as $wt) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $val = ($personel && isset($summary[$personel->id][$d][$wt['name']])) ? $summary[$personel->id][$d][$wt['name']] : 0;
                    if ($val > 0) {
                        $sheet->setCellValue($colLetter . $row, $val);
                        if (!isset($dailyDetailedTotals[$d][$wt['name']]))
                            $dailyDetailedTotals[$d][$wt['name']] = 0;
                        $dailyDetailedTotals[$d][$wt['name']] += $val;
                        $dailyTotals[$d] += $val; // Günlük toplamları da güncelle
                        $personelTotal += $val;
                        $personelWorkTypeTotals[$wt['name']] += $val;
                    }
                    $colIndex++;
                }
            }
        }

        // İŞLEM TOPLAMLARI - GENEL sütunları (sadece detaylı raporlar için)
        if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
            foreach ($workTypeCols as $wt) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                if ($personelWorkTypeTotals[$wt['name']] > 0) {
                    $sheet->setCellValue($colLetter . $row, $personelWorkTypeTotals[$wt['name']]);
                }
                $colIndex++;
            }
        }

        $sheet->setCellValue($toplamCol . $row, $personelTotal ?: '');
        $regionTotal += $personelTotal;
        $grandTotal += $personelTotal;
        $row++;
    }

    // Region Total and Name (Merged)
    if ($activeTab !== 'kacakkontrol' && count($teams) > 0) {
        $regionEndRow = $row - 1;
        if ($regionStartRow < $regionEndRow) {
            $sheet->mergeCells($bolgeToplamCol . $regionStartRow . ':' . $bolgeToplamCol . $regionEndRow);
            $sheet->mergeCells($bolgeAdiCol . $regionStartRow . ':' . $bolgeAdiCol . $regionEndRow);
        }
        $sheet->setCellValue($bolgeToplamCol . $regionStartRow, $regionTotal ?: '');
        $sheet->setCellValue($bolgeAdiCol . $regionStartRow, $regionName);
    }
}

// Extra section for unseen teams in kacak_kontrol
$unseenKacakTeams = array_diff($allKacakTeams, $seenTeams);
if (!empty($unseenKacakTeams)) {
    $regionStartRow = $row;
    $regionTotal = 0;
    foreach ($unseenKacakTeams as $teamName) {
        $personelTotal = array_sum($summary[$teamName]);
        $sheet->setCellValue('A' . $row, $sira++);
        $sheet->setCellValue('B' . $row, $teamName);
        if ($activeTab !== 'kacakkontrol') {
            $sheet->setCellValue('C' . $row, '-');
        }

        $colIndex = $daysColStartIdx;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $val = $summary[$teamName][$d] ?? 0;
            if ($val > 0) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue($colLetter . $row, $val);
                $dailyTotals[$d] += $val;
            }
            $colIndex++;
        }
        $sheet->setCellValue($toplamCol . $row, $personelTotal ?: '');
        $regionTotal += $personelTotal;
        $grandTotal += $personelTotal;
        $row++;
    }

    if ($activeTab !== 'kacakkontrol') {
        $regionEndRow = $row - 1;
        if ($regionStartRow < $regionEndRow) {
            $sheet->mergeCells($bolgeToplamCol . $regionStartRow . ':' . $bolgeToplamCol . $regionEndRow);
            $sheet->mergeCells($bolgeAdiCol . $regionStartRow . ':' . $bolgeAdiCol . $regionEndRow);
        }
        $sheet->setCellValue($bolgeToplamCol . $regionStartRow, $regionTotal ?: '');
        $sheet->setCellValue($bolgeAdiCol . $regionStartRow, 'TANIMSIZ');
    }
}

// Footer - İŞLEM BAZINDA GÜNLÜK TOPLAMLAR (only for detailed reports with workTypeCols)
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    $sheet->setCellValue('A' . $row, 'İŞLEM BAZINDA GÜNLÜK TOPLAMLAR');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->getStyle('A' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    $colIndex = $daysColStartIdx;
    $actionGrandTotals = []; // Track grand totals per work type
    foreach ($workTypeCols as $wt) {
        $actionGrandTotals[$wt['name']] = 0;
    }
    
    for ($d = 1; $d <= $daysInMonth; $d++) {
        foreach ($workTypeCols as $wt) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $val = $dailyDetailedTotals[$d][$wt['name']] ?? 0;
            if ($val > 0) {
                $sheet->setCellValue($colLetter . $row, $val);
            }
            $actionGrandTotals[$wt['name']] += $val;
            $colIndex++;
        }
    }
    
    // İŞLEM TOPLAMLARI - GENEL sütunları
    $actionTypesGrandTotal = 0;
    foreach ($workTypeCols as $wt) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        if ($actionGrandTotals[$wt['name']] > 0) {
            $sheet->setCellValue($colLetter . $row, $actionGrandTotals[$wt['name']]);
        }
        $actionTypesGrandTotal += $actionGrandTotals[$wt['name']];
        $colIndex++;
    }
    
    // Action types grand total cell
    $sheet->setCellValue($toplamCol . $row, $actionTypesGrandTotal ?: '');
    
    // İşlem bazında satır için stil
    $actionFooterStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($actionFooterStyle);
    $row++;
}

// Footer (Daily Totals - GÜNLÜK TOPLAMLAR)
$sheet->setCellValue('A' . $row, 'GÜNLÜK TOPLAMLAR');
$footerMergeEnd = ($activeTab === 'kacakkontrol') ? 'B' : 'C';
$sheet->mergeCells('A' . $row . ':' . $footerMergeEnd . $row);
$sheet->getStyle('A' . $row . ':' . $footerMergeEnd . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$colIndex = $daysColStartIdx;
for ($d = 1; $d <= $daysInMonth; $d++) {
    if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        if ($dailyTotals[$d] > 0) {
            $sheet->setCellValue($colLetter . $row, $dailyTotals[$d]);
        }
        $colIndex++;
    } else {
        // Detaylı raporlarda tüm sub-colonları kapsayan tek hücre
        $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
        $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $subColCount - 1);
        if ($dailyTotals[$d] > 0) {
            $sheet->setCellValue($startColLetter . $row, $dailyTotals[$d]);
        }
        if ($subColCount > 1) {
            $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
        }
        $colIndex += $subColCount;
    }
}

// İŞLEM TOPLAMLARI - GENEL sütunları için GÜNLÜK TOPLAMLAR satırında merge
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    $startColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
    $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + $subColCount - 1);
    $sheet->setCellValue($startColLetter . $row, $grandTotal ?: '');
    if ($subColCount > 1) {
        $sheet->mergeCells($startColLetter . $row . ':' . $endColLetter . $row);
    }
}

$sheet->setCellValue($toplamCol . $row, $grandTotal ?: '');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($footerStyle);

// Apply data style to all data rows
$sheet->getStyle('A' . ($headerRows + 1) . ':' . $lastCol . $row)->applyFromArray([
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
]);
$sheet->getStyle('A' . ($headerRows + 1) . ':A' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('B' . ($headerRows + 1) . ':B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($daysColStartIdx) . ($headerRows + 1) . ':' . $toplamCol . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
if ($activeTab !== 'kacakkontrol') {
    $sheet->getStyle($bolgeToplamCol . ($headerRows + 1) . ':' . $bolgeAdiCol . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Sütun genişlikleri - tablodaki gibi sabit değerler
$sheet->getColumnDimension('A')->setWidth(6); // SIRA - dar
$sheet->getColumnDimension('B')->setWidth(22); // EKİP KODU - orta

if ($activeTab !== 'kacakkontrol') {
    $sheet->getColumnDimension('C')->setWidth(28); // İSİM SOYİSİM - geniş
}

// Günlük sütunlar - zaten header kısmında ayarlandı (4 genişlik)
// Okuma ve Kaçak Kontrol için günlük sütunlar daha geniş olabilir
if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
    for ($i = $daysColStartIdx; $i <= $lastDayColIdx; $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($colLetter)->setWidth(5);
    }
}

// TOPLAM sütunu
$sheet->getColumnDimension($toplamCol)->setWidth(9);

if ($activeTab !== 'kacakkontrol') {
    $sheet->getColumnDimension($bolgeToplamCol)->setWidth(11); // BÖLGE TOP.
    $sheet->getColumnDimension($bolgeAdiCol)->setWidth(15); // BÖLGE ADI
}

// Download
$filename = str_replace(' ', '_', $title) . '_' . $year . '_' . $month . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
