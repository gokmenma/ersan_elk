<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\SayacDegisimModel;

if (!isset($Tanimlamalar)) $Tanimlamalar = new TanimlamalarModel();
if (!isset($EndeksOkuma)) $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj)) $Puantaj = new PuantajModel();
if (!isset($SayacDegisim)) $SayacDegisim = new SayacDegisimModel();

$pId = $_GET['pId'] ?? 0;
$activeTab = $_GET['tab'] ?? 'okuma';
$startDateStr = $_GET['start_date'] ?? null;
$endDateStr = $_GET['end_date'] ?? null;

if (!$startDateStr) {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    $startDateStr = "$year-$month-01";
    $endDateStr = date('Y-m-t', strtotime($startDateStr));
}

// Fetch only requested type of work
$allWorkData = [];

// 1. Kesme / Açma / Diğerleri (yapilan_isler -> PuantajModel)
if (in_array($activeTab, ['kesme', 'muhurleme'])) {
    $sum = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
    
    // Filter work types based on tab context if needed (though getSummaryDetailedByRange usually separates them)
    // For now, if user selected 'muhurleme', we might want to filter only mühürleme work types.
    // However, the user request says "mühürleme, veya kaçak kontrol olarak sekmeli yap, iş hangi sekemede ise"
    // Let's check common work types.
    
    if (isset($sum[$pId])) {
        foreach ($sum[$pId] as $compKey => $dayDataArray) {
            foreach ($dayDataArray as $day => $vals) {
                foreach ($vals as $workTypeName => $count) {
                    if ($count > 0) {
                        // Logic to decide if this workType belongs to this tab
                        $isMatch = false;
                        if ($activeTab === 'muhurleme' && stripos($workTypeName, 'mühür') !== false) $isMatch = true;
                        if ($activeTab === 'kesme' && stripos($workTypeName, 'mühür') === false) $isMatch = true;
                        
                        if ($isMatch) {
                            $allWorkData[$day][$workTypeName] = ($allWorkData[$day][$workTypeName] ?? 0) + $count;
                        }
                    }
                }
            }
        }
    }
}

// 2. Endeks Okuma
if ($activeTab === 'okuma') {
    $okumaSummary = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
    if (isset($okumaSummary[$pId])) {
        foreach ($okumaSummary[$pId] as $compKey => $dayDataArray) {
            foreach ($dayDataArray as $day => $val) {
                if ($val > 0) {
                    $allWorkData[$day]['Endeks Okuma'] = ($allWorkData[$day]['Endeks Okuma'] ?? 0) + (int)$val;
                }
            }
        }
    }
}

// 3. Sayaç Değişim
if ($activeTab === 'sokme_takma') {
    $sayacSummary = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
    if (isset($sayacSummary[$pId])) {
        foreach ($sayacSummary[$pId] as $compKey => $dayDataArray) {
            foreach ($dayDataArray as $day => $vals) {
                foreach ($vals as $workTypeName => $count) {
                    if ($count > 0) {
                        $allWorkData[$day][$workTypeName] = ($allWorkData[$day][$workTypeName] ?? 0) + $count;
                    }
                }
            }
        }
    }
}

// 4. Kaçak Kontrol
if ($activeTab === 'kacakkontrol') {
    $kacakSummary = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
    if (isset($kacakSummary[$pId])) {
        // Kacak summary normally returns [day => count] or [team => [day => count]]
        // Assuming [day => count] for person based on raporlar.php logic
        foreach ($kacakSummary[$pId] as $day => $val) {
            if ($val > 0) {
                $allWorkData[$day]['Kaçak Kontrol'] = ($allWorkData[$day]['Kaçak Kontrol'] ?? 0) + (int)$val;
            }
        }
    }
}

krsort($allWorkData);

if ($startDateStr === $endDateStr) {
    $titleStr = Date::dmY($startDateStr) . ' İş Takip';
} else {
    $titleStr = Date::dmY($startDateStr) . ' - ' . Date::dmY($endDateStr) . ' İş Takip';
}
?>

<div class="space-y-4">
    <?php if(empty($allWorkData)): ?>
        <div class="flex flex-col items-center justify-center py-12 px-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
            <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">history</span>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Kayıt Bulunamadı</p>
            <p class="text-[10px] text-slate-400 mt-1">Seçilen dönemde yapılan iş kaydı bulunmuyor.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach($allWorkData as $day => $metrics): 
                $dayArr = explode('-', $day);
                $mIndex = (int)$dayArr[1];
                $mName = Date::MONTHS[$mIndex] ?? '';
                $dayFormatted = $dayArr[2] . ' ' . $mName . ' ' . $dayArr[0];
                $dayName = Date::gunAdi($day);
            ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <!-- Day Header -->
                <div class="flex items-center justify-between mb-4 border-b border-slate-50 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <span class="text-sm font-black"><?= $dayArr[2] ?></span>
                        </div>
                        <div>
                            <h5 class="text-[13px] font-bold text-slate-800 dark:text-white leading-tight"><?= $mName . ' ' . $dayArr[0] ?></h5>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?= $dayName ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Metrics Grid -->
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach($metrics as $label => $value): ?>
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-700/50">
                            <span class="block text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider mb-1 opacity-70"><?= htmlspecialchars($label) ?></span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-black text-indigo-600 dark:text-indigo-400 leading-none"><?= $value ?></span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">adet</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
