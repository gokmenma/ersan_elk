<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;
use App\Service\SayacDegisimService;

if (!isset($Tanimlamalar)) $Tanimlamalar = new TanimlamalarModel();
if (!isset($EndeksOkuma)) $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj)) $Puantaj = new PuantajModel();
if (!isset($Personel)) $Personel = new PersonelModel();
if (!isset($Firma)) $Firma = new FirmaModel();
if (!isset($Settings)) $Settings = new \App\Model\SettingsModel();

$firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'okuma';
$filterType = $_GET['filter_type'] ?? 'bugun'; // 'bugun' or 'buay'
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if ($filterType === 'buay') {
    $startDateStr = "$year-$month-01";
    $endDateStr = date('Y-m-t', strtotime($startDateStr));
} else {
    $startDateStr = date('Y-m-d');
    $endDateStr = date('Y-m-d');
}

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

if (!function_exists('getShortCode')) {
    function getShortCode($text) {
        $words = explode(' ', preg_replace('/[^A-ZÇĞİIÖŞÜ\s]/u', '', mb_strtoupper($text, 'UTF-8')));
        $code = '';
        foreach ($words as $word) {
            if (!empty($word)) $code .= mb_substr($word, 0, 1, 'UTF-8');
        }
        return $code ?: mb_substr($text, 0, 2, 'UTF-8');
    }
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

$allPersonel = $Personel->all(false, 'puantaj');
$personelById = [];
foreach ($allPersonel as $p) {
    // Profil fotoğrafını kontrol et, yoksa default_user.png
    if (empty($p->fotograf) || !file_exists(dirname(__DIR__, 2) . '/assets/images/users/' . $p->fotograf)) {
        $p->fotograf_url = 'assets/images/users/default_user.png';
    } else {
        $p->fotograf_url = 'assets/images/users/' . $p->fotograf;
    }
    $personelById[$p->id] = $p;
}

$activeAssignments = $Personel->getAllActiveAssignmentsInRange($startDateStr, $endDateStr);
$allTeams = $Tanimlamalar->getEkipKodlari();
$teamById = [];
foreach ($allTeams as $t) {
    $teamById[$t->id] = $t;
}

$kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

// --- DATA PRE-PROCESSING ---
$tableData = []; 
$validPairs = [];

if (!empty($summary)) {
    foreach ($summary as $pId => $teams) {
        if ($activeTab === 'kacakkontrol') {
            $teamName = $pId; 
            $matchingTeams = array_filter($allTeams, function ($t) use ($teamName) { return $t->tur_adi === $teamName; });
            $team = !empty($matchingTeams) ? reset($matchingTeams) : null;
            $tId = $team ? $team->id : 0;
            $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
            if ($team && $teamNo > 0) {
                if (!\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'kacakkontrol', $Settings)) continue;
            }
            $validPairs['kacak_' . $teamName] = [
                'pId' => 'kacak_' . $teamName,
                'tId' => $tId,
                'isKacak' => true,
                'teamName' => $teamName,
                'ekipKodu' => $teamName,
                'compositeKey' => $teamName
            ];
        } else {
            foreach ($teams as $compositeKey => $data) {
                $parts = explode('|', $compositeKey, 2);
                $tId = (int)$parts[0];
                $ekipKoduStr = $parts[1] ?? '';

                $hasRelevantData = true;
                if ($activeTab !== 'okuma') {
                    $hasRelevantData = false;
                    foreach ($data as $day => $workTypeCounts) {
                        foreach ($workTypeCounts as $workTypeName => $count) {
                            if ($activeTab === 'muhurleme' && $count > 0) {
                                $hasRelevantData = true;
                                break 2;
                            }
                            
                            foreach ($workTypeCols as $wtCol) {
                                if ($wtCol['name'] === $workTypeName && $count > 0) {
                                    $hasRelevantData = true;
                                    break 3;
                                }
                            }
                        }
                    }
                }

                $teamNo = 0;
                if ($tId > 0 && isset($teamById[$tId])) {
                    $team = $teamById[$tId];
                    $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
                } else if (!empty($ekipKoduStr)) {
                    $teamNo = \App\Helper\EkipHelper::extractTeamNo($ekipKoduStr);
                }

                if ($teamNo > 0) {
                    if (!\App\Helper\EkipHelper::isTeamInTabRange($teamNo, $activeTab, $Settings)) continue;
                } else if (!$hasRelevantData) {
                    continue;
                }

                $validPairs[$pId . '_' . $compositeKey] = [
                    'pId' => $pId, 
                    'tId' => $tId, 
                    'ekipKodu' => $ekipKoduStr,
                    'compositeKey' => $compositeKey
                ];
            }
        }
    }
}

foreach ($activeAssignments as $assign) {
    if (!isset($teamById[$assign->ekip_kodu_id])) continue;
    $team = $teamById[$assign->ekip_kodu_id];
    $teamNo = \App\Helper\EkipHelper::extractTeamNo(trim(($team->grup_adi ?? '') . ' ' . ($team->tur_adi ?? '')));
    if ($teamNo <= 0) continue; 
    $isValid = false;
    $personelDepts = !empty($assign->departman) ? array_map('trim', explode(',', $assign->departman)) : [];
    
    if ($activeTab === 'okuma') {
        if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'okuma', $Settings) && (in_array('Endeks Okuma', $personelDepts) || in_array('Okuma', $personelDepts))) $isValid = true;
    } elseif ($activeTab === 'kesme') {
        if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'kesme', $Settings) && (in_array('Kesme Açma', $personelDepts) || in_array('Kesme-Açma', $personelDepts))) $isValid = true;
    } elseif ($activeTab === 'sokme_takma') {
        if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'sokme_takma', $Settings) && (in_array('Sayaç Sökme Takma', $personelDepts) || in_array('Sökme Takma', $personelDepts))) $isValid = true;
    } elseif ($activeTab === 'muhurleme') {
        if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'muhurleme', $Settings)) $isValid = true;
    }

    if ($isValid) {
        $compositeKey = $assign->ekip_kodu_id . '|' . ($team->tur_adi ?? '');
        $validPairs[$assign->personel_id . '_' . $compositeKey] = [
            'pId' => $assign->personel_id,
            'tId' => $assign->ekip_kodu_id,
            'ekipKodu' => $team->tur_adi ?? '',
            'compositeKey' => $compositeKey
        ];
    }
}

// Convert pairs to flat list of cards
$cards = [];
foreach ($validPairs as $pair) {
    $pId = $pair['pId'];
    $tId = $pair['tId'];
    $compositeKey = $pair['compositeKey'];
    
    $rawTeamName = $teamById[$tId]->tur_adi ?? ($pair['ekipKodu'] ?? '-');
    $cleanTeamName = trim(str_ireplace(['ERSAN ELEKTRİK ', 'ERSAN ELEKTRIK ', 'ERSAN ELEKTRİK', 'ERSAN ELEKTRIK'], '', $rawTeamName));
    
    $team = (object) [
        'id' => $tId,
        'tur_adi' => $cleanTeamName,
        'ekip_bolge' => $teamById[$tId]->ekip_bolge ?? 'TANIMSIZ'
    ];

    if (isset($pair['isKacak'])) {
        $teamName = $pair['teamName'];
        // Also clean the teamName used for the card if it's kacak
        $cleanKacakTeamName = trim(str_ireplace(['ERSAN ELEKTRİK ', 'ERSAN ELEKTRIK ', 'ERSAN ELEKTRİK', 'ERSAN ELEKTRIK'], '', $teamName));
        
        $pIdsStr = $kacakPersonelMapping[$teamName] ?? '';
        if (empty($pIdsStr)) {
            $mappedIds = [];
            foreach ($activeAssignments as $assign) {
                if ($assign->ekip_kodu_id == $tId && $tId > 0) $mappedIds[] = $assign->personel_id;
            }
            $pIdsStr = implode(',', array_unique($mappedIds));
        }
        $names = [];
        foreach (explode(',', $pIdsStr) as $id) {
            if (isset($personelById[trim($id)])) $names[] = $personelById[trim($id)]->adi_soyadi;
        }
        
        $cards[] = [
            'pId' => 0,
            'name' => !empty($names) ? implode(', ', $names) : $cleanKacakTeamName,
            'team' => $cleanKacakTeamName,
            'region' => $team->ekip_bolge ?: '-',
            'totals' => [],
            'is_kacak' => true,
            'compositeKey' => $compositeKey
        ];
    } else {
        $p = $personelById[$pId] ?? null;
        if(!$p) continue;
        
        $cards[] = [
            'pId' => $pId,
            'name' => $p->adi_soyadi,
            'team' => $team->tur_adi,
            'region' => $team->ekip_bolge ?: '-',
            'totals' => [], // Will be filled
            'is_kacak' => false,
            'compositeKey' => $compositeKey
        ];
    }
}

// Custom sort by region, then team number, then name
usort($cards, function ($a, $b) {
    if ($a['region'] !== $b['region']) {
        return strcoll($a['region'], $b['region']);
    }
    preg_match('/(?:EK[İI\?]?P-?\s?)(\d+)/ui', $a['team'], $matchA);
    $numA = isset($matchA[1]) ? (int) $matchA[1] : 999999;
    preg_match('/(?:EK[İI\?]?P-?\s?)(\d+)/ui', $b['team'], $matchB);
    $numB = isset($matchB[1]) ? (int) $matchB[1] : 999999;
    if ($numA !== $numB) return $numA - $numB;
    return strcoll($a['name'], $b['name']);
});

// Calculate totals per card based on summary
foreach ($cards as &$card) {
    $pId = $card['is_kacak'] ? $card['compositeKey'] : $card['pId'];
    $compKey = $card['compositeKey'];
    
    // Totals accumulation
    if ($activeTab === 'okuma') {
        $cT = [ 'A' => 0 ];

        if (isset($summary[$pId][$compKey])) {
            $dataToSum = $summary[$pId][$compKey];
            if (is_array($dataToSum)) {
                foreach ($dataToSum as $dayData) {
                    $cT['A'] += (int) $dayData;
                }
            } else {
                $cT['A'] = (int) $dataToSum;
            }
        }
        $card['totals'] = $cT;
    } elseif ($activeTab === 'kacakkontrol') {
        $totalSayi = 0;
        if (isset($summary[$pId])) {
            foreach ($summary[$pId] as $dayStr => $sayiVal) {
                if (!is_array($sayiVal)) {
                    $totalSayi += (int)$sayiVal;
                }
            }
        }
        $card['totals'] = ['Sayı' => $totalSayi];
    } elseif ($activeTab === 'muhurleme') {
        $totalSayi = 0;
        if (isset($summary[$pId][$compKey])) {
            foreach ($summary[$pId][$compKey] as $dayData) {
                foreach($dayData as $vItem) {
                    $totalSayi += (int)$vItem;
                }
            }
        }
        $card['totals'] = ['Mühürleme' => $totalSayi];
    } else {
        $cT = [];
        foreach($workTypeCols as $wt) {
            $cT[$wt['name']] = 0;
        }
        if (isset($summary[$pId][$compKey])) {
            foreach ($summary[$pId][$compKey] as $dayData) {
                foreach($workTypeCols as $wt) {
                    $cT[$wt['name']] += $dayData[$wt['name']] ?? 0;
                }
            }
        }
        $card['totals'] = $cT;
    }
}

$groupedCards = [];
$hasData = false;
foreach ($cards as $c) {
    // Only show card if it has non-zero values for something, or if today is empty
    $foundActivity = false;
    foreach($c['totals'] as $v) {
        if($v > 0) { $foundActivity = true; break; }
    }
    if($foundActivity || $filterType == 'bugun') { // For today, show them even if 0 to show default list
        $hasData = true;
        $r = $c['region'];
        if(!isset($groupedCards[$r])) $groupedCards[$r] = [];
        $groupedCards[$r][] = $c;
    }
}
?>

<div class="px-2 pt-2 pb-2">
<?php if (count($groupedCards) === 0): ?>
    <div class="text-center py-12 px-6">
        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">inbox</span>
        <h3 class="text-sm font-bold text-slate-700">Veri Bulunamadı</h3>
        <p class="text-xs text-slate-500 mt-1">Bu dönem için personel/ekip verisi kaydı görünmüyor.</p>
    </div>
<?php else: 
    $chartLabels = [];
    $chartData = [];
    $chartColors = [];
    
    // Some bright pastel hex colors for the chart
    $chartHexColors = ['#3b82f6', '#10b981', '#a855f7', '#f59e0b', '#ef4444', '#06b6d4', '#8b5cf6'];
    // Corresponding Tailwind background classes for the headers
    $regionSoftBgClasses = [
        'bg-blue-50/80 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
        'bg-emerald-50/80 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'bg-purple-50/80 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
        'bg-amber-50/80 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        'bg-rose-50/80 dark:bg-rose-900/20 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        'bg-cyan-50/80 dark:bg-cyan-900/20 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
        'bg-violet-50/80 dark:bg-violet-900/20 text-violet-700 dark:text-violet-300 border-violet-200 dark:border-violet-800',
    ];
    
    $idx = 0;
    $regionTotals = [];
    foreach ($groupedCards as $rName => $rCards) {
        $regTotal = 0;
        foreach($rCards as $cData) {
            if ($activeTab === 'okuma') {
                $regTotal += $cData['totals']['A'] ?? 0;
            } elseif ($activeTab === 'kacakkontrol') {
                $regTotal += $cData['totals']['Sayı'] ?? 0;
            } elseif ($activeTab === 'muhurleme') {
                $regTotal += $cData['totals']['Mühürleme'] ?? 0;
            } else {
                $regTotal += array_sum($cData['totals']);
            }
        }
        $regionTotals[$rName] = $regTotal;
        $chartLabels[] = mb_substr($rName, 0, 10, 'UTF-8') . (mb_strlen($rName, 'UTF-8') > 10 ? '..' : '');
        $chartData[] = $regTotal;
        $chartColors[] = $chartHexColors[$idx % count($chartHexColors)];
        $idx++;
    }
?>
    <!-- CHART CONTAINER -->
    <?php if ($hasData): ?>
    <div class="mb-4 bg-white dark:bg-card-dark rounded-xl p-3 border border-slate-100 dark:border-slate-800 shadow-sm relative">
        <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 px-1">Bölge Performansı</h3>
        <div class="w-full relative" style="height: 160px;">
            <canvas id="regionChartCanvas"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col gap-4">
    <?php $i = 0; foreach ($groupedCards as $regionName => $regionCards): 
        $hClass = $regionSoftBgClasses[$i % count($regionSoftBgClasses)];
        $i++;
    ?>
        <div class="border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden bg-white dark:bg-slate-900 shadow-sm relative">
            <div class="<?= $hClass ?> px-4 py-2 border-b backdrop-blur-sm sticky top-0 z-10">
                <h3 class="text-[11px] font-black uppercase tracking-widest flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">pin_drop</span> 
                    <?= htmlspecialchars($regionName) ?> 
                    <div class="ml-auto flex items-center gap-2">
                        <span class="bg-primary/20 dark:bg-primary/30 px-2 py-0.5 rounded text-[10px] font-black text-primary dark:text-primary-400 border border-primary/20">
                            <?= number_format($regionTotals[$regionName], 0, ',', '.') ?> <?= ($activeTab === 'okuma' ? 'İS' : 'İŞLEM') ?>
                        </span>
                        <span class="bg-white/50 dark:bg-black/20 px-1.5 py-0.5 rounded text-[9px]"><?= count($regionCards) ?> KİŞİ</span>
                    </div>
                </h3>
            </div>
            <div class="p-2 grid grid-cols-1 gap-2">
                <?php foreach ($regionCards as $c): 
                    $totalValForColors = array_sum($c['totals']);
                    
                    // Calculate operations count
                    $operationCount = 0;
                    if ($activeTab === 'okuma') {
                        $operationCount = $c['totals']['A'] ?? 0;
                    } elseif ($activeTab === 'kacakkontrol') {
                        $operationCount = $c['totals']['Sayı'] ?? 0;
                    } elseif ($activeTab === 'muhurleme') {
                        $operationCount = $c['totals']['Mühürleme'] ?? 0;
                    } else {
                        $operationCount = $totalValForColors;
                    }
                    
                    $clickId = $c['is_kacak'] ? $c['compositeKey'] : $c['pId'];
                ?>
                    <div class="bg-slate-50 dark:bg-card-dark rounded-xl p-3 flex flex-col active:scale-[0.98] transition-all cursor-pointer border border-transparent hover:border-slate-200 dark:hover:border-slate-700 group" onclick="window.openPersonelMonthlyDetails('<?= htmlspecialchars($clickId, ENT_QUOTES) ?>', '<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>', '<?= $activeTab ?>')">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 shrink-0 bg-primary/10 rounded-xl flex flex-col items-center justify-center text-primary font-black shadow-inner shadow-primary/10">
                                <span class="text-[18px] leading-none mb-0.5"><?= $operationCount ?></span>
                            </div>
                            <div class="flex-grow min-w-0 pr-2">
                                <h4 class="font-bold text-slate-900 dark:text-white text-[13px] truncate"><?= htmlspecialchars($c['name']) ?></h4>
                                <p class="text-[11px] text-slate-500 font-semibold flex items-center mt-0.5"><span class="bg-white dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 shadow-sm border-b-2 font-bold"><?= htmlspecialchars($c['team']) ?></span></p>
                            </div>
                            <div class="shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-white dark:bg-slate-800 text-slate-400 shadow-sm border border-slate-100 dark:border-slate-700 group-active:bg-primary group-active:text-white transition-colors">
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </div>
                        </div>
                        
                        <?php if ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol' && $totalValForColors > 0): ?>
                        <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700/50 flex flex-wrap gap-2">
                            <?php foreach($c['totals'] as $key => $val): if($val <= 0) continue; ?>
                                <div class="flex items-center gap-1.5 px-2 py-1 bg-white dark:bg-slate-800/80 rounded-lg shadow-sm border border-slate-100 dark:border-slate-700/80">
                                    <span class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest inline-block truncate max-w-[90px] mt-0.5"><?= $key ?></span>
                                    <span class="text-xs font-black text-slate-700 dark:text-slate-300"><?= $val ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<!-- SCRIPTS for Rendering the Chart -->
<?php if (!empty($chartData)): ?>
<script>
    if(window.regionChartInst) {
        window.regionChartInst.destroy();
    }
    
    // We expect Chart.js to be available from raporlar.php header setup. 
    // Wait until it's loaded to draw chart.
    setTimeout(() => {
        if(typeof Chart === 'undefined') return;
        
        const ctx = document.getElementById('regionChartCanvas').getContext('2d');
        window.regionChartInst = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'İşlem',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: <?= json_encode($chartColors) ?>.map(c => c + 'CC'),
                    borderColor: <?= json_encode($chartColors) ?>,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (item) => ' ' + item.formattedValue + ' İşlem'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9, weight: 'bold' } }
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 9 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }, 100);
</script>
<?php endif; ?>
