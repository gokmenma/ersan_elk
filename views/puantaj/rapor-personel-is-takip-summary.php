<?php
header('Content-Type: application/json');
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\SayacDegisimModel;

if (!isset($EndeksOkuma)) $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj)) $Puantaj = new PuantajModel();
if (!isset($SayacDegisim)) $SayacDegisim = new SayacDegisimModel();

$pId = $_GET['pId'] ?? 0;
$startDateStr = $_GET['start_date'] ?? null;
$endDateStr = $_GET['end_date'] ?? null;

if (!$startDateStr) {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    $startDateStr = "$year-$month-01";
    $endDateStr = date('Y-m-t', strtotime($startDateStr));
}

$summary = [
    'okuma' => 0,
    'kesme' => 0,
    'sokme_takma' => 0,
    'muhurleme' => 0,
    'kacakkontrol' => 0
];

// 1. Endeks Okuma
$okumaData = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
if (isset($okumaData[$pId])) {
    foreach ($okumaData[$pId] as $compKey => $dayData) {
        foreach ($dayData as $v) if ($v > 0) $summary['okuma'] += $v;
    }
}

// 2. Sayaç Değişim
$sayacData = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
if (isset($sayacData[$pId])) {
    foreach ($sayacData[$pId] as $compKey => $dayData) {
        foreach ($dayData as $vRow) {
            foreach ($vRow as $v) if ($v > 0) $summary['sokme_takma'] += $v;
        }
    }
}

// 3. Kesme / Mühürleme (yapilan_isler)
$yapilanIslerData = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
if (isset($yapilanIslerData[$pId])) {
    foreach ($yapilanIslerData[$pId] as $compKey => $dayData) {
        foreach ($dayData as $vRow) {
            foreach ($vRow as $workTypeName => $v) {
                if ($v > 0) {
                    if (stripos($workTypeName, 'mühür') !== false) {
                        $summary['muhurleme'] += $v;
                    } else {
                        $summary['kesme'] += $v;
                    }
                }
            }
        }
    }
}

// 4. Kaçak Kontrol
$kacakData = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
if (isset($kacakData[$pId])) {
    foreach ($kacakData[$pId] as $v) if ($v > 0) $summary['kacakkontrol'] += $v;
}

echo json_encode($summary);
