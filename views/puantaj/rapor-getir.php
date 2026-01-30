<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

if (!isset($Tanimlamalar))
    $Tanimlamalar = new TanimlamalarModel();
if (!isset($EndeksOkuma))
    $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj))
    $Puantaj = new PuantajModel();
if (!isset($Personel))
    $Personel = new PersonelModel();

$year = $year ?? $_GET['year'] ?? date('Y');
$month = $month ?? $_GET['month'] ?? date('m');
$activeTab = $activeTab ?? $_GET['tab'] ?? 'okuma';
$filterPersonelId = $_GET['personel_id'] ?? '';
$filterRegion = $_GET['region'] ?? '';

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$regions = $Tanimlamalar->getEkipBolgeleri();

if ($filterRegion) {
    $regions = array_filter($regions, function ($r) use ($filterRegion) {
        return $r == $filterRegion;
    });
}

// Fetch summary based on active tab
$workTypes = [];
if ($activeTab === 'okuma') {
    $summary = $EndeksOkuma->getMonthlySummary($year, $month);
} elseif ($activeTab === 'kacakkontrol') {
    $summary = $Puantaj->getKacakSummary($year, $month);
} else {
    $summary = $Puantaj->getMonthlySummaryDetailed($year, $month);
    // Fetch work types based on active tab from tanimlamalar
    $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);

    // Fallback for sokme_takma if no records found
    if (empty($workTypes) && $activeTab === 'sokme_takma') {
        $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru('sokme');
    }

    // Fallback for kesme if no rapor_turu is set yet
    if (empty($workTypes) && $activeTab === 'kesme') {
        $workTypes = $Tanimlamalar->getUcretliIsTurleri();
    }
}

// Helper to generate short code
if (!function_exists('getShortCode')) {
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
$hasSubCols = !empty($workTypeCols);
$headerRowspan = ($activeTab !== 'okuma' && $activeTab !== 'kacakkontrol') && $hasSubCols ? 3 : 2;

// Personel mapping for easy access
$allPersonel = $Personel->all();
$personelMap = [];
foreach ($allPersonel as $p) {
    if ($p->ekip_no) {
        $personelMap[$p->ekip_no] = $p;
    }
}
?>

<?php if ($activeTab !== 'okuma' && !empty($workTypeCols)):
    // Calculate monthly totals for legend
    $monthlyTotals = [];
    foreach ($workTypeCols as $wt) {
        $total = 0;
        foreach ($summary as $personelId => $days) {
            foreach ($days as $day => $results) {
                if (isset($results[$wt['name']])) {
                    $total += $results[$wt['name']];
                }
            }
        }
        $monthlyTotals[$wt['name']] = $total;
    }
    ?>
    <div class="report-legend">
        <?php foreach ($workTypeCols as $wt): ?>
            <div class="legend-item">
                <span class="legend-code"><?= $wt['code'] ?></span>
                <span class="legend-name"><?= $wt['name'] ?></span>
                <span class="badge bg-primary-subtle text-primary ms-1"><?= $monthlyTotals[$wt['name']] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        font-size: 10px;
        padding: 0;
        margin: 0;
        display: inline-block;
        line-height: normal;
        height: 50px;
    }

    #raporTable {
        border-collapse: collapse !important;
        font-size: 12px;
        width: 100%;
        table-layout: auto;
        background-color: #fff;
    }

    #raporTable th,
    #raporTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid #dee2e6 !important;
        padding: 6px 8px !important;
        line-height: normal !important;
        white-space: nowrap;
    }

    #raporTable thead th {
        background-color: #f8f9fa !important;
        font-weight: 600;
        font-size: 11px;
        color: #333;
    }

    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow: auto;
        max-width: 100%;
        background: #fff;
    }
</style>

<?php
$totalColsInDays = $daysInMonth * $subColCount;
// Kesme tablosu sığmasın, scroll olsun. Diğerleri sığsın.
if ($activeTab === 'kesme' || $activeTab === 'sokme_takma' || $activeTab === 'muhurleme') {
    $tableMinWidth = 1500 + ($totalColsInDays * 25);
} else {
    $tableMinWidth = '100%';
}
?>

<div class="table-responsive">
    <table class="table table-bordered table-sm mb-0" id="raporTable" style="min-width: <?= $tableMinWidth ?>;">
        <thead>
            <tr><?php /* Row 1 */ ?>
                <th rowspan="<?= $headerRowspan ?>" style="width: 50px;">SIRA</th>
                <th rowspan="<?= $headerRowspan ?>" style="width: 120px;">EKİP KODU</th>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th rowspan="<?= $headerRowspan ?>" style="width: 220px;">İSİM SOYİSİM</th>
                <?php endif; ?>
                <th colspan="<?= $daysInMonth * $subColCount ?>">GÜNLER</th>
                <th rowspan="<?= $headerRowspan ?>" style="width: 80px;">TOPLAM</th>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th rowspan="<?= $headerRowspan ?>" style="width: 90px;">BÖLGE TOP.</th>
                    <th rowspan="<?= $headerRowspan ?>" style="width: 140px;">BÖLGE ADI</th><?php endif; ?>
            </tr>
            <tr><?php /* Row 2 */ ?><?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th colspan="<?= $subColCount ?>" style="padding: 6px !important;"><?= $d ?></th><?php endfor; ?>
            </tr>
            <?php if ($hasSubCols && $headerRowspan === 3): ?>
                <tr><?php /* Row 3 */ ?><?php for ($d = 1; $d <= $daysInMonth; $d++): ?><?php foreach ($workTypeCols as $wt): ?>
                            <th style="padding: 0 !important;" title="<?= $wt['name'] ?>"><span
                                    class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?><?php endfor; ?>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php
            $sira = 1;
            $dailyTotals = array_fill(1, $daysInMonth, 0);
            $dailyDetailedTotals = []; // [day][work_result_name]
            $grandTotal = 0;
            $seenTeams = []; // Keep track of teams already displayed
            
            // For kacak_kontrol, we might have teams that are not in the standard region loop
            $allKacakTeams = ($activeTab === 'kacakkontrol') ? array_keys($summary) : [];
            foreach ($regions as $regionName):
                $teams = $Tanimlamalar->getEkipKodlariByBolgeAll($regionName);
                if ($filterPersonelId) {
                    $teams = array_filter($teams, function ($team) use ($filterPersonelId, $personelMap) {
                        $personel = $personelMap[$team->id] ?? null;
                        return $personel && $personel->id == $filterPersonelId;
                    });
                }
                if (empty($teams))
                    continue;

                // Pre-calculate visible teams to get accurate rowspan
                $visibleTeams = [];
                foreach ($teams as $team) {
                    $personel = $personelMap[$team->id] ?? null;

                    // Görev bazlı filtreleme: Tüm sekmelerde KESME-AÇMA personellerini getir (Okuma, Kesme, Sökme, Mühürleme)
                    // This filter is now applied consistently across all tabs.
                    if (!$personel || mb_stripos($personel->gorev, 'KESME-AÇMA') === false) {
                        continue;
                    }

                    $lookupKey = ($activeTab === 'kacakkontrol') ? $team->tur_adi : ($personel ? $personel->id : null);
                    if (($activeTab === 'kacakkontrol' || $activeTab === 'okuma') && (!$lookupKey || !isset($summary[$lookupKey]))) {
                        continue;
                    }
                    $visibleTeams[] = $team;
                }

                if (empty($visibleTeams))
                    continue;

                $regionTotal = 0;
                $firstRow = true;

                // Calculate region total
                foreach ($visibleTeams as $team) {
                    $lookupKey = ($activeTab === 'kacakkontrol') ? $team->tur_adi : (($personelMap[$team->id] ?? null) ? $personelMap[$team->id]->id : null);
                    if ($lookupKey && isset($summary[$lookupKey])) {
                        if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
                            $regionTotal += array_sum($summary[$lookupKey]);
                        } else {
                            foreach ($summary[$lookupKey] as $dayData) {
                                foreach ($workTypeCols as $wt) {
                                    $regionTotal += $dayData[$wt['name']] ?? 0;
                                }
                            }
                        }
                    }
                }

                foreach ($visibleTeams as $team):
                    $personel = $personelMap[$team->id] ?? null;
                    $personelTotal = 0;
                    $lookupKey = ($activeTab === 'kacakkontrol') ? $team->tur_adi : ($personel ? $personel->id : null);

                    if (!$lookupKey || !isset($summary[$lookupKey])) {
                        // Skip if no data for this team in this tab
                        if ($activeTab === 'kacakkontrol' || $activeTab === 'okuma')
                            continue;
                    }

                    if ($activeTab === 'kacakkontrol')
                        $seenTeams[] = $team->tur_adi;

                    if ($lookupKey && isset($summary[$lookupKey])) {
                        if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol') {
                            $personelTotal = array_sum($summary[$lookupKey]);
                        } else {
                            foreach ($summary[$lookupKey] as $dayData) {
                                foreach ($workTypeCols as $wt) {
                                    $personelTotal += $dayData[$wt['name']] ?? 0;
                                }
                            }
                        }
                    }
                    $grandTotal += $personelTotal;
                    ?>
                    <tr>
                        <td><?= $sira++ ?></td>
                        <td><?= $team->tur_adi ?></td><?php if ($activeTab !== 'kacakkontrol'): ?>
                            <td class="text-start"><?= $personel ? $personel->adi_soyadi : '-' ?></td>
                        <?php endif; ?>         <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>             <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'):
                                                             $val = ($lookupKey && isset($summary[$lookupKey][$d])) ? $summary[$lookupKey][$d] : 0;
                                                             $dailyTotals[$d] += $val; ?>
                                <td class="<?= $val ? 'fw-bold' : 'text-muted' ?>"><?= $val ?: '' ?></td>
                            <?php else: ?>                 <?php foreach ($workTypeCols as $wt):
                                                   $val = ($personel && isset($summary[$personel->id][$d][$wt['name']])) ? $summary[$personel->id][$d][$wt['name']] : 0;
                                                   if (!isset($dailyDetailedTotals[$d][$wt['name']]))
                                                       $dailyDetailedTotals[$d][$wt['name']] = 0;
                                                   $dailyDetailedTotals[$d][$wt['name']] += $val; ?>
                                    <td class="<?= $val ? 'fw-bold' : 'text-muted' ?>" style="font-size: 10px;"><?= $val ?: '' ?></td>
                                <?php endforeach; ?>             <?php endif; ?>         <?php endfor; ?>
                        <td class="table-light fw-bold"><?= $personelTotal ?: '' ?></td>
                        <?php if ($activeTab !== 'kacakkontrol' && $firstRow): ?>
                            <td rowspan="<?= count($visibleTeams) ?>" class="fw-bold"><?= $regionTotal ?: '' ?></td>
                            <td rowspan="<?= count($visibleTeams) ?>" class="fw-bold text-uppercase"
                                style="font-size: 9px; line-height: 1;"><?= $regionName ?></td>
                            <?php $firstRow = false; ?>         <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php
            // Show teams that were in kacak_kontrol but NOT in the region loop
            $unseenKacakTeams = array_diff($allKacakTeams, $seenTeams);
            if (!empty($unseenKacakTeams)):
                $regionTotal = 0;
                $firstRow = true;
                foreach ($unseenKacakTeams as $teamName) {
                    $regionTotal += array_sum($summary[$teamName]);
                }
                foreach ($unseenKacakTeams as $teamName):
                    $personelTotal = array_sum($summary[$teamName]);
                    $grandTotal += $personelTotal;
                    ?>
                    <tr>
                        <td><?= $sira++ ?></td>
                        <td><?= $teamName ?></td>
                        <?php if ($activeTab !== 'kacakkontrol'): ?>
                            <td class="text-start">-</td>
                        <?php endif; ?>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $val = $summary[$teamName][$d] ?? 0;
                            $dailyTotals[$d] += $val;
                            ?>
                            <td class="<?= $val ? 'fw-bold' : 'text-muted' ?>"><?= $val ?: '' ?></td>
                        <?php endfor; ?>
                        <td class="table-light fw-bold"><?= $personelTotal ?: '' ?></td>
                        <?php if ($activeTab !== 'kacakkontrol' && $firstRow): ?>
                            <td rowspan="<?= count($unseenKacakTeams) ?>" class="fw-bold"><?= $regionTotal ?: '' ?></td>
                            <td rowspan="<?= count($unseenKacakTeams) ?>" class="fw-bold text-uppercase"
                                style="font-size: 9px; line-height: 1;">TANIMSIZ</td>
                            <?php $firstRow = false; ?>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="<?= ($activeTab === 'kacakkontrol') ? '2' : '3' ?>" class="text-end">GÜNLÜK TOPLAMLAR</td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'): ?>
                        <td><?= $dailyTotals[$d] ?: '' ?></td>
                    <?php else: ?>
                        <?php foreach ($workTypeCols as $wt): ?>
                            <td><?= $dailyDetailedTotals[$d][$wt['name']] ?? '' ?></td>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endfor; ?>
                <td><?= $grandTotal ?: '' ?></td>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <td colspan="2"></td>
                <?php endif; ?>
            </tr>
        </tfoot>
    </table>
</div>