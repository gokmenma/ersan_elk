<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Service\SayacDegisimService;

if (!isset($Tanimlamalar)) $Tanimlamalar = new TanimlamalarModel();
if (!isset($EndeksOkuma)) $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj)) $Puantaj = new PuantajModel();
if (!isset($Personel)) $Personel = new PersonelModel();

$pId = $_GET['pId'] ?? 0;
$activeTab = $_GET['tab'] ?? 'okuma';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$month = str_pad($month, 2, '0', STR_PAD_LEFT);
$startDateStr = "$year-$month-01";
$endDateStr = date('Y-m-t', strtotime($startDateStr));

// Get work types definitions
$workTypes = [];
if ($activeTab === 'okuma') {
    $summary = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
} elseif ($activeTab === 'kacakkontrol') {
    $summary = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
} elseif ($activeTab === 'sokme_takma') {
    $SayacDegisim = new SayacDegisimService();
    $summary = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
    $workTypes = $SayacDegisim->getDistinctWorkTypes();
} else {
    $summary = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
    $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);
    if (empty($workTypes) && $activeTab === 'kesme') {
        $workTypes = $Tanimlamalar->getUcretliIsTurleri();
    }
}

$workTypeCols = [];
if ($activeTab !== 'okuma' && !empty($workTypes)) {
    foreach ($workTypes as $ut) {
        $workTypeCols[] = [
            'name' => $ut->is_emri_sonucu,
        ];
    }
}

$personelData = [];
// Find if the person exists in summary
if (isset($summary[$pId])) {
    if ($activeTab === 'okuma') {
        // Okuma summary is [pId][compKey][day]
        foreach($summary[$pId] as $compKey => $dayDataArray) {
            foreach($dayDataArray as $day => $val) {
                if(!isset($personelData[$day])) $personelData[$day] = [];
                $personelData[$day]['Okunan Abone'] = ($personelData[$day]['Okunan Abone'] ?? 0) + (int)$val;
            }
        }
    } elseif ($activeTab === 'kacakkontrol') {
        // For kacakkontrol, summary[$pId] is [day => scalar]
        foreach($summary[$pId] as $day => $val) {
            if(!isset($personelData[$day])) $personelData[$day] = [];
            $personelData[$day]['İşlem Sayısı'] = ($personelData[$day]['İşlem Sayısı'] ?? 0) + (int)$val;
        }
    } else {
        // Determine the compositeKey, we might have multiple teams for the same person
        foreach($summary[$pId] as $compKey => $dayDataArray) {
            // Collect by day
            foreach($dayDataArray as $day => $vals) {
                if(!isset($personelData[$day])) $personelData[$day] = [];
                foreach($workTypeCols as $wt) {
                    $personelData[$day][$wt['name']] = ($personelData[$day][$wt['name']] ?? 0) + ($vals[$wt['name']] ?? 0);
                }
            }
        }
    }
}

krsort($personelData); // order descending date

$monthName = \App\Helper\Date::MONTHS[(int)$month] ?? '';
$titleStr = $monthName . ' ' . $year . ' İşlem Dökümü';
?>

<div>
    <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4 px-1 pb-2 border-b border-slate-100 dark:border-slate-800"><?= htmlspecialchars($titleStr) ?></h3>
    
    <?php if(empty($personelData)): ?>
        <div class="text-center py-8">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">event_busy</span>
            <p class="text-sm font-semibold text-slate-500">Bu dönem için henüz bir işlem kaydı bulunamadı.</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach($personelData as $day => $metrics): 
                $hasSomeValue = false;
                foreach($metrics as $k => $v) { if($v > 0) $hasSomeValue = true; }
                if(!$hasSomeValue) continue; 
                
                if (empty($day) || strpos($day, '-') === false) continue;
                
                $dayArr = explode('-', $day);
                if (count($dayArr) < 3) continue;
                
                $mIndex = (int)$dayArr[1];
                $mName = Date::MONTHS[$mIndex] ?? '';
                $dayFormatted = $dayArr[2] . ' ' . $mName . ' ' . $dayArr[0];
                $dayName = Date::gunAdi($day);
            ?>
            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-3 border border-slate-100 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold text-sm text-slate-800 dark:text-slate-200"><?= $dayFormatted ?></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-full shadow-sm"><?= $dayName ?></span>
                </div>
                
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <?php foreach($metrics as $key => $val): if($val <= 0) continue; ?>
                        <div class="bg-white dark:bg-slate-800 p-2 text-center rounded-md border border-slate-100 dark:border-slate-700">
                            <span class="block text-[10px] text-slate-500 font-bold uppercase mb-0.5"><?= htmlspecialchars($key) ?></span>
                            <span class="block text-sm font-black text-primary">
                                <?= $val ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
