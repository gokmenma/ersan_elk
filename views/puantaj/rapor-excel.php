<?php
session_start();
require_once dirname(__DIR__, 2) . '/Autoloader.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;
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
$Firma = new FirmaModel();

$firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
$firmaAdi = $firma->firma_adi ?? '';

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

function shortenTeamName($teamName, $firmaAdi)
{
    if (empty($firmaAdi))
        return $teamName;

    // Şirket unvan eklerini temizle (LTD, ŞTİ, vb.)
    $firmaClean = preg_replace('/\s+(LTD|ŞTİ|LİMİTED|ŞİRKETİ|A\.Ş\.|ANONİM|TİCARET|SANAYİ).*$/ui', '', $firmaAdi);

    $short = $teamName;
    // 1. Temizlenmiş firma adı ile tam eşleşme kontrolü (vaka duyarsız)
    if (mb_stripos($teamName, $firmaClean) === 0) {
        $short = trim(mb_substr($teamName, mb_strlen($firmaClean)));
    } else {
        // 2. Normalleştirilmiş eşleşme (ER-SAN vs ERSAN gibi durumlar için)
        $firmaNormalized = preg_replace('/[^A-ZÇĞİIÖŞÜ]/u', '', mb_strtoupper($firmaClean, 'UTF-8'));
        $teamNormalized = preg_replace('/[^A-ZÇĞİIÖŞÜ]/u', '', mb_strtoupper($teamName, 'UTF-8'));

        if (mb_stripos($teamNormalized, $firmaNormalized) === 0) {
            $ekipPos = mb_stripos($teamName, 'EKİP');
            if ($ekipPos === false)
                $ekipPos = mb_stripos($teamName, 'EKIP');

            if ($ekipPos !== false) {
                $short = trim(mb_substr($teamName, $ekipPos));
            }
        }
    }

    // 3. Fallback: Eğer hala çok uzunsa ve içinde EKİP geçiyorsa direkt oradan al
    if (mb_strlen($short) > 15) {
        $ekipPos = mb_stripos($short, 'EKİP');
        if ($ekipPos === false)
            $ekipPos = mb_stripos($short, 'EKIP');
        if ($ekipPos !== false) {
            $short = trim(mb_substr($short, $ekipPos));
        }
    }

    return $short ?: $teamName;
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

// Personel mapping for easy access
$allPersonel = $Personel->all();
$personelById = [];
foreach ($allPersonel as $p) {
    $personelById[$p->id] = $p;
}

// Fetch all active assignments for this month range
$startDateStr = "$year-$month-01";
$endDateStr = date('Y-m-t', strtotime($startDateStr));
$activeAssignments = $Personel->getAllActiveAssignmentsInRange($startDateStr, $endDateStr);

// Pre-fetch all teams to have a lookup
$allTeams = $Tanimlamalar->getEkipKodlari();
$teamById = [];
foreach ($allTeams as $t) {
    $teamById[$t->id] = $t;
}

// Kacak Kontrol: Get mapping
$kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

$alreadySeenIds = [];
$allSummaryPersonels = ($activeTab !== 'kacakkontrol' && is_array($summary)) ? array_keys($summary) : [];

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
// Data Rendering
$row = $headerRows + 1;
$sira = 1;
$dailyTotals = array_fill(1, $daysInMonth, 0);
$dailyDetailedTotals = [];
$grandTotal = 0;

// 1. Build Valid Pairs
$validPairs = []; // key: [pId]_[tId] => ['pId' => X, 'tId' => Y]
if (!empty($summary)) {
    if ($activeTab === 'kacakkontrol') {
        foreach ($summary as $teamName => $days) {
            $matchingTeams = array_filter($allTeams, function ($t) use ($teamName) {
                return $t->tur_adi === $teamName;
            });
            foreach ($matchingTeams as $mt) {
                foreach ($activeAssignments as $assign) {
                    if ($assign->ekip_kodu_id == $mt->id) {
                        $validPairs[$assign->personel_id . '_' . $mt->id] = ['pId' => $assign->personel_id, 'tId' => $mt->id];
                    }
                }
            }
        }
    } else {
        foreach ($summary as $pId => $teams) {
            foreach ($teams as $tId => $data) {
                if ($filterPersonelId && $pId != $filterPersonelId)
                    continue;
                $validPairs[$pId . '_' . $tId] = ['pId' => $pId, 'tId' => $tId];
            }
        }
    }
}

// 2. Add from history (even if no data)
foreach ($activeAssignments as $assign) {
    if ($filterPersonelId && $assign->personel_id != $filterPersonelId)
        continue;

    $isValid = false;
    $personelDepts = !empty($assign->departman) ? array_map('trim', explode(',', $assign->departman)) : [];
    $gorev = $assign->gorev ?? '';

    if ($activeTab === 'okuma') {
        if (in_array('Endeks Okuma', $personelDepts) || in_array('Okuma', $personelDepts))
            $isValid = true;
    } elseif ($activeTab === 'kesme') {
        if (in_array('Kesme Açma', $personelDepts) || in_array('Kesme-Açma', $personelDepts))
            $isValid = true;
    } elseif ($activeTab === 'sokme_takma') {
        if (in_array('Sayaç Sökme Takma', $personelDepts) || in_array('Sökme Takma', $personelDepts))
            $isValid = true;
    } elseif ($activeTab === 'muhurleme') {
        if (in_array('Mühürleme', $personelDepts))
            $isValid = true;
    } elseif ($activeTab === 'kacakkontrol') {
        if (in_array('Kaçak Kontrol', $personelDepts) || in_array('Kaçak Su Tespiti', $personelDepts))
            $isValid = true;
    } else {
        $isValid = true;
    }

    if ($isValid) {
        $validPairs[$assign->personel_id . '_' . $assign->ekip_kodu_id] = [
            'pId' => $assign->personel_id,
            'tId' => $assign->ekip_kodu_id
        ];
    }
}

// 3. Group by region
$regionGrouped = [];
foreach ($validPairs as $pair) {
    $pId = $pair['pId'];
    $tId = $pair['tId'];
    $p = $personelById[$pId] ?? null;
    if (!$p)
        continue;
    $team = $teamById[$tId] ?? (object) ['id' => 0, 'tur_adi' => '-', 'ekip_bolge' => 'TANIMSIZ BÖLGE'];
    $regionName = $team->ekip_bolge ?: 'TANIMSIZ BÖLGE';
    if ($filterRegion && $regionName != $filterRegion)
        continue;

    $regionGrouped[$regionName][] = [
        'team' => $team,
        'personel' => $p,
        'pId' => $pId,
        'tId' => $tId
    ];
}

// 4. Render Data
foreach ($regions as $regionName) {
    if (empty($regionGrouped[$regionName]))
        continue;
    $teamsInRegion = $regionGrouped[$regionName];
    usort($teamsInRegion, function ($a, $b) {
        return strcoll($a['personel']->adi_soyadi, $b['personel']->adi_soyadi);
    });

    $regionStartRow = $row;
    $regionTotal = 0;

    foreach ($teamsInRegion as $tData) {
        $team = $tData['team'];
        $personel = $tData['personel'];
        $pId = $tData['pId'];
        $tId = $tData['tId'];
        $personelTotal = 0;

        $sheet->setCellValue('A' . $row, $sira++);
        $sheet->setCellValue('B' . $row, shortenTeamName($team->tur_adi, $firmaAdi));
        if ($activeTab !== 'kacakkontrol')
            $sheet->setCellValue('C' . $row, $personel->adi_soyadi);

        $colIdx = $daysColStartIdx;
        if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $val = ($activeTab === 'kacakkontrol') ? ($summary[$team->tur_adi][$d] ?? 0) : ($summary[$pId][$tId][$d] ?? 0);
                if ($val > 0) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($colLetter . $row, $val);
                    $dailyTotals[$d] += $val;
                    $personelTotal += $val;
                }
                $colIdx++;
            }
        } else {
            $personelWorkTypeTotals = [];
            foreach ($workTypeCols as $wt)
                $personelWorkTypeTotals[$wt['name']] = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                foreach ($workTypeCols as $wt) {
                    $val = $summary[$pId][$tId][$d][$wt['name']] ?? 0;
                    if ($val > 0) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                        $sheet->setCellValue($colLetter . $row, $val);
                        if (!isset($dailyDetailedTotals[$d][$wt['name']]))
                            $dailyDetailedTotals[$d][$wt['name']] = 0;
                        $dailyDetailedTotals[$d][$wt['name']] += $val;
                        $dailyTotals[$d] += $val;
                        $personelTotal += $val;
                        $personelWorkTypeTotals[$wt['name']] += $val;
                    }
                    $colIdx++;
                }
            }
            foreach ($workTypeCols as $wt) {
                if ($personelWorkTypeTotals[$wt['name']] > 0) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($colLetter . $row, $personelWorkTypeTotals[$wt['name']]);
                }
                $colIdx++;
            }
        }
        $sheet->setCellValue($toplamCol . $row, $personelTotal ?: '');
        $regionTotal += $personelTotal;
        $grandTotal += $personelTotal;
        $row++;
    }

    $regionEndRow = $row - 1;
    if ($activeTab !== 'kacakkontrol' && isset($bolgeToplamCol) && isset($bolgeAdiCol)) {
        if ($regionStartRow < $regionEndRow) {
            $sheet->mergeCells($bolgeToplamCol . $regionStartRow . ':' . $bolgeToplamCol . $regionEndRow);
            $sheet->mergeCells($bolgeAdiCol . $regionStartRow . ':' . $bolgeAdiCol . $regionEndRow);
        }
        $sheet->setCellValue($bolgeToplamCol . $regionStartRow, $regionTotal ?: '');
        $sheet->setCellValue($bolgeAdiCol . $regionStartRow, $regionName);
    }
    unset($regionGrouped[$regionName]);
}

// 5. Handle TANIMSIZ regions
foreach ($regionGrouped as $regionName => $teamsInRegion) {
    $regionStartRow = $row;
    $regionTotal = 0;
    foreach ($teamsInRegion as $tData) {
        $team = $tData['team'];
        $personel = $tData['personel'];
        $pId = $tData['pId'];
        $tId = $tData['tId'];
        $personelTotal = 0;
        $sheet->setCellValue('A' . $row, $sira++);
        $sheet->setCellValue('B' . $row, shortenTeamName($team->tur_adi, $firmaAdi));
        if ($activeTab !== 'kacakkontrol')
            $sheet->setCellValue('C' . $row, $personel->adi_soyadi);
        $colIdx = $daysColStartIdx;
        if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $val = ($activeTab === 'kacakkontrol') ? ($summary[$team->tur_adi][$d] ?? 0) : ($summary[$pId][$tId][$d] ?? 0);
                if ($val > 0) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($colLetter . $row, $val);
                    $dailyTotals[$d] += $val;
                    $personelTotal += $val;
                }
                $colIdx++;
            }
        } else {
            $personelWorkTypeTotals = [];
            foreach ($workTypeCols as $wt)
                $personelWorkTypeTotals[$wt['name']] = 0;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                foreach ($workTypeCols as $wt) {
                    $val = $summary[$pId][$tId][$d][$wt['name']] ?? 0;
                    if ($val > 0) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                        $sheet->setCellValue($colLetter . $row, $val);
                        if (!isset($dailyDetailedTotals[$d][$wt['name']]))
                            $dailyDetailedTotals[$d][$wt['name']] = 0;
                        $dailyDetailedTotals[$d][$wt['name']] += $val;
                        $dailyTotals[$d] += $val;
                        $personelTotal += $val;
                        $personelWorkTypeTotals[$wt['name']] += $val;
                    }
                    $colIdx++;
                }
            }
            foreach ($workTypeCols as $wt) {
                if ($personelWorkTypeTotals[$wt['name']] > 0) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($colLetter . $row, $personelWorkTypeTotals[$wt['name']]);
                }
                $colIdx++;
            }
        }
        $sheet->setCellValue($toplamCol . $row, $personelTotal ?: '');
        $regionTotal += $personelTotal;
        $grandTotal += $personelTotal;
        $row++;
    }
    $regionEndRow = $row - 1;
    if ($activeTab !== 'kacakkontrol' && isset($bolgeToplamCol) && isset($bolgeAdiCol)) {
        if ($regionStartRow < $regionEndRow) {
            $sheet->mergeCells($bolgeToplamCol . $regionStartRow . ':' . $bolgeToplamCol . $regionEndRow);
            $sheet->mergeCells($bolgeAdiCol . $regionStartRow . ':' . $bolgeAdiCol . $regionEndRow);
        }
        $sheet->setCellValue($bolgeToplamCol . $regionStartRow, $regionTotal ?: '');
        $sheet->setCellValue($bolgeAdiCol . $regionStartRow, $regionName);
    }
}

// 6. Footer (Action Totals)
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    $sheet->setCellValue('A' . $row, 'İŞLEM BAZINDA GÜNLÜK TOPLAMLAR');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->getStyle('A' . $row . ':C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $colIdx = $daysColStartIdx;
    $actionGrandTotals = [];
    foreach ($workTypeCols as $wt)
        $actionGrandTotals[$wt['name']] = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        foreach ($workTypeCols as $wt) {
            $val = $dailyDetailedTotals[$d][$wt['name']] ?? 0;
            if ($val > 0) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue($colLetter . $row, $val);
            }
            $actionGrandTotals[$wt['name']] += $val;
            $colIdx++;
        }
    }
    $actionTypesGrandTotal = 0;
    foreach ($workTypeCols as $wt) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
        if ($actionGrandTotals[$wt['name']] > 0)
            $sheet->setCellValue($colLetter . $row, $actionGrandTotals[$wt['name']]);
        $actionTypesGrandTotal += $actionGrandTotals[$wt['name']];
        $colIdx++;
    }
    $sheet->setCellValue($toplamCol . $row, $actionTypesGrandTotal ?: '');
    $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E3F2FD']]]);
    $row++;
}

// 7. Footer (Daily Totals)
$sheet->setCellValue('A' . $row, 'GÜNLÜK TOPLAMLAR');
$footerMergeEnd = ($activeTab === 'kacakkontrol') ? 'B' : 'C';
$sheet->mergeCells('A' . $row . ':' . $footerMergeEnd . $row);
$sheet->getStyle('A' . $row . ':' . $footerMergeEnd . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$colIdx = $daysColStartIdx;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    if ($dailyTotals[$d] > 0)
        $sheet->setCellValue($colLetter . $row, $dailyTotals[$d]);
    if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && $subColCount > 1) {
        $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + $subColCount - 1);
        $sheet->mergeCells($colLetter . $row . ':' . $endColLetter . $row);
        $colIdx += $subColCount;
    } else {
        $colIdx++;
    }
}
if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && !empty($workTypeCols)) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    $endColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + $subColCount - 1);
    $sheet->setCellValue($colLetter . $row, $grandTotal ?: '');
    if ($subColCount > 1)
        $sheet->mergeCells($colLetter . $row . ':' . $endColLetter . $row);
}
$sheet->setCellValue($toplamCol . $row, $grandTotal ?: '');
$sheet->getStyle('A' . $row . ':' . $lastCol . $row)->applyFromArray($footerStyle);

// 8. Styles
$sheet->getStyle('A' . ($headerRows + 1) . ':' . $lastCol . $row)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
$sheet->getStyle('A' . ($headerRows + 1) . ':B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
