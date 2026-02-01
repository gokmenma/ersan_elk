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
$personelById = [];
foreach ($allPersonel as $p) {
    if ($p->ekip_no) {
        $ekipNoInt = intval($p->ekip_no);
        $personelMap[$ekipNoInt] = $p;
    }
    $personelById[$p->id] = $p;
}

// Kacak Kontrol: Get ekip_adi to personel_ids mapping from kacak_kontrol table
$kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

// Pazar günlerini belirle (0 = Pazar)
$sundayDays = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $timestamp = mktime(0, 0, 0, $month, $d, $year);
    if (date('w', $timestamp) == 0) {
        $sundayDays[] = $d;
    }
}
?>
<?php
// --- DATA PRE-PROCESSING ---
$allSummaryPersonels = ($activeTab !== 'kacakkontrol') ? array_keys($summary ?? []) : [];
$tableData = []; // Structure: [ [region_name, teams: [ [team_obj, personel_obj] ] ] ]
$alreadySeenIds = [];

if ($activeTab !== 'kacakkontrol') {
    // 1. Regions & Teams
    foreach ($regions as $regionName) {
        $teams = $Tanimlamalar->getEkipKodlariByBolgeAll($regionName);
        $regionTeams = [];
        foreach ($teams as $team) {
            $personel = $personelMap[$team->id] ?? null;

            $isValid = false;
            if ($personel) {
                if ($activeTab === 'okuma') {
                    // Görevi "Okuma" olanlar VEYA o dönemde endeks okuma verisi olanlar
                    if (mb_stripos($personel->gorev, 'Okuma') !== false || isset($summary[$personel->id]))
                        $isValid = true;
                } elseif ($activeTab === 'kesme') {
                    // Görevi "Kesme-Açma" olanlar VEYA o dönemde işlem verisi olanlar
                    if (mb_stripos($personel->gorev, 'Kesme-Açma') !== false || isset($summary[$personel->id]))
                        $isValid = true;
                } elseif ($activeTab === 'sokme_takma') {
                    // Görevi "Sayaç Sökme Takma" olanlar VEYA o dönemde işlem verisi olanlar
                    if (mb_stripos($personel->gorev, 'Sayaç Sökme Takma') !== false || isset($summary[$personel->id]))
                        $isValid = true;
                } elseif ($activeTab === 'muhurleme') {
                    // Görevi "Mühürleme" olanlar VEYA o dönemde işlem verisi olanlar
                    if (mb_stripos($personel->gorev, 'Mühürleme') !== false || isset($summary[$personel->id]))
                        $isValid = true;
                } else {
                    $isValid = true;
                }
            }

            if ($isValid) {
                // Apply personnel filter if set
                if ($filterPersonelId && $personel->id != $filterPersonelId) {
                    $isValid = false;
                }
            }

            if ($isValid) {
                $regionTeams[] = [
                    'team' => $team,
                    'personel' => $personel
                ];
                $alreadySeenIds[] = $personel->id;
            }
        }
        if (!empty($regionTeams)) {
            // Sort teams within region by personel name
            usort($regionTeams, function ($a, $b) {
                return strcoll($a['personel']->adi_soyadi, $b['personel']->adi_soyadi);
            });

            $tableData[] = [
                'region' => $regionName,
                'teams' => $regionTeams
            ];
        }
    }

    // 2. Unassigned Personnel (Tanımsız Bölge)
    $unseenIds = array_diff($allSummaryPersonels, $alreadySeenIds);
    $unassignedTeams = [];
    foreach ($unseenIds as $uid) {
        $p = $personelById[$uid] ?? null;
        if (!$p)
            continue;

        $isValid = false;
        if ($activeTab === 'okuma') {
            // Görevi "Okuma" olanlar VEYA o dönemde endeks okuma verisi olanlar
            if (mb_stripos($p->gorev, 'Okuma') !== false || isset($summary[$p->id]))
                $isValid = true;
        } elseif ($activeTab === 'kesme') {
            // Görevi "Kesme-Açma" olanlar VEYA o dönemde işlem verisi olanlar
            if (mb_stripos($p->gorev, 'Kesme-Açma') !== false || isset($summary[$p->id]))
                $isValid = true;
        } elseif ($activeTab === 'sokme_takma') {
            // Görevi "Sayaç Sökme Takma" olanlar VEYA o dönemde işlem verisi olanlar
            if (mb_stripos($p->gorev, 'Sayaç Sökme Takma') !== false || isset($summary[$p->id]))
                $isValid = true;
        } elseif ($activeTab === 'muhurleme') {
            // Görevi "Mühürleme" olanlar VEYA o dönemde işlem verisi olanlar
            if (mb_stripos($p->gorev, 'Mühürleme') !== false || isset($summary[$p->id]))
                $isValid = true;
        } else {
            $isValid = true;
        }

        if ($isValid) {
            // Apply personnel filter if set
            if ($filterPersonelId && $p->id != $filterPersonelId) {
                $isValid = false;
            }
        }

        if ($isValid) {
            $unassignedTeams[] = [
                'team' => (object) ['tur_adi' => '-'],
                'personel' => $p
            ];
        }
    }
    if (!empty($unassignedTeams)) {
        // Sort unassigned teams by personel name
        usort($unassignedTeams, function ($a, $b) {
            return strcoll($a['personel']->adi_soyadi, $b['personel']->adi_soyadi);
        });

        $tableData[] = [
            'region' => 'TANIMSIZ BÖLGE',
            'teams' => $unassignedTeams
        ];
    }
}

// 3. Legend Totals Calculation (Only if workTypeCols exist)
$monthlyTotals = [];
if (!empty($workTypeCols)) {
    foreach ($workTypeCols as $wt) {
        $total = 0;
        foreach ($tableData as $item) {
            foreach ($item['teams'] as $tData) {
                $pId = $tData['personel']->id;
                if (isset($summary[$pId])) {
                    foreach ($summary[$pId] as $dayData) {
                        $total += $dayData[$wt['name']] ?? 0;
                    }
                }
            }
        }
        $monthlyTotals[$wt['name']] = $total;
    }
}
?>

<?php if ($activeTab !== 'okuma' && !empty($workTypeCols)): ?>

    <div class="report-legend" id="workTypeLegend">
        <?php foreach ($workTypeCols as $wt): ?>
            <div class="legend-item" data-wt-code="<?= $wt['code'] ?>" style="cursor: pointer; transition: all 0.2s;">
                <span class="legend-code"><?= $wt['code'] ?></span>
                <span class="legend-name"><?= $wt['name'] ?></span>
                <span class="badge bg-primary-subtle text-primary ms-1"><?= $monthlyTotals[$wt['name']] ?></span>
            </div>
        <?php endforeach; ?>
        <?php
        $unmatchedCount = 0;
        if ($activeTab === 'kesme') {
            // Tüm sekmelerdeki eşleşmeyenleri getir
            $unmatchedRecords = $Puantaj->getUnmatchedWorkResults($year, $month, 'all');
            $unmatchedCount = count($unmatchedRecords);
        }
        ?>
        <small class="text-muted ms-2 align-self-center">* Kodlara tıklayarak tabloyu filtreleyebilirsiniz.</small>

        <?php

        if ($unmatchedCount > 0): ?>
            <div class="legend-item ms-auto border-danger text-danger border-dashed" id="btnExportUnmatched"
                title="Tanımlı olmayan iş emri sonuçları var. Raporu indirerek kontrol edebilirsiniz."
                style="cursor: pointer; border: 1px dashed #f46a6a; padding: 2px 10px; border-radius: 6px; background-color: rgba(244, 106, 106, 0.05); font-weight: 500;">
                <i class="bx bx-error-circle me-1 animate-pulse"></i>
                Ücretsiz İşlemler: <span class="badge bg-danger ms-1"><?= $unmatchedCount ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
    .legend-item.active-filter {
        background-color: var(--bs-primary, #556ee6) !important;
        color: #fff !important;
        border-color: var(--bs-primary, #556ee6) !important;
        border-radius: 6px !important;
    }

    .legend-item.active-filter .legend-code {
        background-color: rgba(255, 255, 255, 0.2) !important;
        color: #fff !important;
    }

    .legend-item.active-filter .badge {
        background-color: #fff !important;
        color: var(--bs-primary, #556ee6) !important;
    }

    /* Pazar günü (tatil) arka plan rengi - zebra efektinden öncelikli */
    .sunday-cell,
    .sunday-cell.day-bg-alt,
    td.sunday-cell,
    th.sunday-cell {
        background-color: #EA7B7B !important;
    }

    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        font-size: 10px;
        padding: 0;
        margin: 0;
        display: inline-block;
        line-height: 1;
        height: 45px;
    }

    #raporTable {
        border-collapse: separate !important;
        border-spacing: 0 !important;
        font-size: 12px;
        width: 100%;
        table-layout: auto;
        background-color: #fff;
    }

    #raporTable th,
    #raporTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid #eee !important;
        border-bottom: 1px solid #e0e0e0 !important;
        padding: 6px 8px !important;
        line-height: normal !important;
        white-space: nowrap;
    }

    .day-separator {
        border-right: 2px solid #555 !important;
    }

    .day-bg-alt {
        background-color: #f9f9f9 !important;
    }

    #raporTable thead th {
        background-color: #f8f9fa !important;
        font-weight: 600;
        font-size: 11px;
        color: #333;
        position: sticky;
        z-index: 20;
    }

    #raporTable thead tr:nth-child(1) th {
        top: 0;
        z-index: 25;
        height: 40px;
    }

    #raporTable thead tr:nth-child(2) th {
        top: 40px;
        z-index: 24;
        height: 40px;
    }

    #raporTable thead tr:nth-child(3) th {
        top: 80px;
        z-index: 23;
        height: 65px;
    }

    #raporTable tfoot td {
        position: sticky;
        z-index: 20;
        background-color: #f8f9fa !important;
        height: 40px;
        padding: 0 8px !important;
        border-top: 1px solid #dee2e6 !important;
    }

    #raporTable tfoot tr.tfoot-general td {
        bottom: 0;
        z-index: 22;
    }

    #raporTable tfoot tr.tfoot-action td {
        bottom: 40px;
        z-index: 21;
    }

    .sticky-col-1 {
        position: sticky;
        left: 0;
        z-index: 10;
        background-color: #fff !important;
        border-left: 1px solid #ccc !important;
    }

    .sticky-col-2 {
        position: sticky;
        left: 51px;
        z-index: 10;
        background-color: #fff !important;
    }

    .sticky-col-3 {
        position: sticky;
        left: 172px;
        z-index: 10;
        background-color: #fff !important;
    }

    #raporTable thead .sticky-col-1,
    #raporTable thead .sticky-col-2,
    #raporTable thead .sticky-col-3 {
        z-index: 30;
    }

    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow: auto;
        max-width: 100%;
        background: #fff;
        max-height: calc(100vh - 350px);
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
            <tr>
                <th rowspan="<?= $headerRowspan ?>" class="sticky-col-1"
                    style="width: 50px; min-width: 50px; max-width: 50px;">SIRA</th>
                <th rowspan="<?= $headerRowspan ?>" class="sticky-col-2"
                    style="width: 120px; min-width: 120px; max-width: 120px;">EKİP / BÖLGE</th>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th rowspan="<?= $headerRowspan ?>" class="sticky-col-3"
                        style="width: 220px; min-width: 220px; max-width: 220px;">İSİM SOYİSİM</th><?php endif; ?>
                <th colspan="<?= $daysInMonth * $subColCount ?>" id="mainGunlerHeader">GÜNLER</th>
                <?php if ($hasSubCols): ?>
                    <th colspan="<?= $subColCount ?>" id="actionTotalsHeader">İŞLEM TOPLAMLARI</th><?php endif; ?>
                <th rowspan="<?= $headerRowspan ?>">TOPLAM</th><?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th rowspan="<?= $headerRowspan ?>">BÖLGE TOP.</th>
                    <th rowspan="<?= $headerRowspan ?>">BÖLGE ADI</th><?php endif; ?>
            </tr>
            <tr>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $isSunday = in_array($d, $sundayDays);
                    ?>
                    <th colspan="<?= $subColCount ?>"
                        class="day-num-header day-separator <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                        data-day="<?= $d ?>">
                        <?= $d ?>
                    </th><?php endfor; ?>
                <?php if ($hasSubCols): ?>
                    <th colspan="<?= $subColCount ?>" class="action-totals-day-header day-separator" data-day="genel-total">
                        GENEL</th><?php endif; ?>
            </tr>
            <?php if ($hasSubCols && $headerRowspan === 3): ?>
                <tr>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isSunday = in_array($d, $sundayDays);
                        ?>         <?php $idx = 0;
                                 foreach ($workTypeCols as $wt):
                                     $idx++; ?>
                            <th class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                data-day="<?= $d ?>" data-wt-code="<?= $wt['code'] ?>"><span
                                    class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?><?php endfor; ?>
                    <?php $idx = 0;
                    foreach ($workTypeCols as $wt):
                        $idx++; ?>
                        <th class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info <?= ($idx === $subColCount) ? 'day-separator' : '' ?>"
                            data-day="genel-total" data-wt-code="<?= $wt['code'] ?>"><span
                                class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php
            $sira = 1;
            $dailyTotals = array_fill(1, $daysInMonth, 0);
            $dailyDetailedTotals = [];
            $grandTotal = 0;

            if ($activeTab === 'kacakkontrol'):
                // Kaçak Kontrol uses its own special logic (team-based, not personel-based)
                $seenTeams = [];
                $allKacakTeams = array_keys($summary);
                foreach ($regions as $regionName):
                    $teams = $Tanimlamalar->getEkipKodlariByBolgeAll($regionName);
                    $visibleTeams = [];
                    foreach ($teams as $team) {
                        if (isset($summary[$team->tur_adi])) {
                            $visibleTeams[] = $team;
                        }
                    }
                    if (empty($visibleTeams))
                        continue;

                    $regionTotal = 0;
                    foreach ($visibleTeams as $team) {
                        $regionTotal += array_sum($summary[$team->tur_adi]);
                    }

                    $firstRow = true;
                    foreach ($visibleTeams as $team):
                        $teamTotal = array_sum($summary[$team->tur_adi]);
                        $grandTotal += $teamTotal;
                        $seenTeams[] = $team->tur_adi;
                        ?>
                        <tr>
                            <td class="sticky-col-1"><?= $sira++ ?></td>
                            <td class="sticky-col-2">
                                <?= $team->tur_adi ?>
                            </td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $val = $summary[$team->tur_adi][$d] ?? 0;
                                $dailyTotals[$d] += $val;
                                $currentDate = str_pad($d, 2, '0', STR_PAD_LEFT) . "." . str_pad($month, 2, '0', STR_PAD_LEFT) . "." . $year;
                                // Get personel_ids from kacak_kontrol table mapping
                                $pIdsStr = $kacakPersonelMapping[$team->tur_adi] ?? '';
                                $isSunday = in_array($d, $sundayDays);
                                ?>
                                <td class="<?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($d === $daysInMonth) ? 'day-separator' : '' ?> kacak-quick-cell <?= $isSunday ? 'sunday-cell' : '' ?>"
                                    data-date="<?= $currentDate ?>" data-personel-ids="<?= $pIdsStr ?>" style="cursor: cell;"
                                    title="Çift tıklayarak yeni kayıt ekle">
                                    <?= $val ?: '' ?>
                                </td>
                            <?php endfor; ?>
                            <td class="table-light fw-bold"><?= $teamTotal ?: '' ?></td>
                            <?php if ($firstRow): ?>
                                <td rowspan="<?= count($visibleTeams) ?>" class="fw-bold"><?= $regionTotal ?: '' ?></td>
                                <td rowspan="<?= count($visibleTeams) ?>" class="fw-bold text-uppercase" style="font-size: 9px;">
                                    <?= $regionName ?>
                                </td>
                                <?php $firstRow = false; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach;
                endforeach;

                // Unseen kacak teams
                $unseenKacakTeams = array_diff($allKacakTeams, $seenTeams);
                if (!empty($unseenKacakTeams)):
                    $regionTotal = 0;
                    $firstRow = true;
                    foreach ($unseenKacakTeams as $teamName)
                        $regionTotal += array_sum($summary[$teamName]);
                    foreach ($unseenKacakTeams as $teamName):
                        $teamTotal = array_sum($summary[$teamName]);
                        $grandTotal += $teamTotal;
                        ?>
                        <tr>
                            <td class="sticky-col-1"><?= $sira++ ?></td>
                            <td class="sticky-col-2">
                                <?= $teamName ?>
                            </td>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $val = $summary[$teamName][$d] ?? 0;
                                $dailyTotals[$d] += $val;
                                $currentDate = str_pad($d, 2, '0', STR_PAD_LEFT) . "." . str_pad($month, 2, '0', STR_PAD_LEFT) . "." . $year;
                                // Get personel_ids from kacak_kontrol table mapping
                                $pIdsStr = $kacakPersonelMapping[$teamName] ?? '';
                                $isSunday = in_array($d, $sundayDays);
                                ?>
                                <td class="<?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($d === $daysInMonth) ? 'day-separator' : '' ?> kacak-quick-cell <?= $isSunday ? 'sunday-cell' : '' ?>"
                                    data-date="<?= $currentDate ?>" data-personel-ids="<?= $pIdsStr ?>" style="cursor: cell;"
                                    title="Çift tıklayarak yeni kayıt ekle">
                                    <?= $val ?: '' ?>
                                </td>
                            <?php endfor; ?>
                            <td class="table-light fw-bold"><?= $teamTotal ?: '' ?></td>
                            <?php if ($firstRow): ?>
                                <td rowspan="<?= count($unseenKacakTeams) ?>" class="fw-bold"><?= $regionTotal ?: '' ?></td>
                                <td rowspan="<?= count($unseenKacakTeams) ?>" class="fw-bold text-uppercase" style="font-size: 9px;">
                                    TANIMSIZ</td>
                                <?php $firstRow = false; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach;
                endif;

            else:
                // Universal logic for Personnel-based reports (Okuma, Kesme, Sokme, Muhurleme)
                foreach ($tableData as $item):
                    $regionTotal = 0;
                    foreach ($item['teams'] as $tData) {
                        $pId = $tData['personel']->id;
                        if (isset($summary[$pId])) {
                            if ($activeTab === 'okuma') {
                                $regionTotal += array_sum($summary[$pId]);
                            } else {
                                // İŞLEM TOPLAMLARI kolonlarının toplamını hesapla
                                foreach ($workTypeCols as $wt) {
                                    $wtTotal = 0;
                                    foreach ($summary[$pId] as $dayData) {
                                        $wtTotal += $dayData[$wt['name']] ?? 0;
                                    }
                                    $regionTotal += $wtTotal;
                                }
                            }
                        }
                    }

                    $firstRow = true;
                    foreach ($item['teams'] as $tData):
                        $team = $tData['team'];
                        $personel = $tData['personel'];
                        $personelTotal = 0;
                        // Okuma sekmesi için toplam hesapla (diğer sekmeler için İŞLEM TOPLAMLARI bölümünde hesaplanacak)
                        if ($activeTab === 'okuma' && isset($summary[$personel->id])) {
                            $personelTotal = array_sum($summary[$personel->id]);
                            $grandTotal += $personelTotal;
                        }
                        // Bölge ID'si olarak bölge adının hash'i kullanılıyor
                        $regionId = md5($item['region']);
                        ?>
                        <tr data-region-id="<?= $regionId ?>">
                            <td class="sticky-col-1"><?= $sira++ ?></td>
                            <td class="sticky-col-2">
                                <?= $team->tur_adi ?>
                            </td>
                            <td class="sticky-col-3 text-start"><?= $personel->adi_soyadi ?></td>

                            <?php if ($activeTab === 'okuma'): ?>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                    $val = $summary[$personel->id][$d] ?? 0;
                                    $dailyTotals[$d] += $val;
                                    $isSunday = in_array($d, $sundayDays); ?>
                                    <td
                                        class="<?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($d === $daysInMonth) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>">
                                        <?= $val ?: '' ?>
                                    </td>
                                <?php endfor; ?>
                            <?php else: ?>
                                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                    $isSunday = in_array($d, $sundayDays); ?>
                                    <?php $idx = 0;
                                    foreach ($workTypeCols as $wt):
                                        $idx++;
                                        $val = $summary[$personel->id][$d][$wt['name']] ?? 0;
                                        if (!isset($dailyDetailedTotals[$d][$wt['name']]))
                                            $dailyDetailedTotals[$d][$wt['name']] = 0;
                                        $dailyDetailedTotals[$d][$wt['name']] += $val;
                                        $dailyTotals[$d] += $val; ?>
                                        <td
                                            class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>">
                                            <?= $val ?: '' ?>
                                        </td>
                                    <?php endforeach; ?>
                                <?php endfor; ?>
                                <?php
                                // İŞLEM TOPLAMLARI değerlerini hesapla ve sakla
                                $personelActTotals = [];
                                foreach ($workTypeCols as $wt) {
                                    $actTotal = 0;
                                    foreach ($summary[$personel->id] ?? [] as $dayData) {
                                        $actTotal += $dayData[$wt['name']] ?? 0;
                                    }
                                    $personelActTotals[$wt['name']] = $actTotal;
                                }
                                // TOPLAM = İŞLEM TOPLAMLARI kolonlarının toplamı
                                $personelTotal = array_sum($personelActTotals);
                                $grandTotal += $personelTotal;
                                ?>
                                <?php foreach ($workTypeCols as $wt): ?>
                                    <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info fw-bold row-action-total">
                                        <?= $personelActTotals[$wt['name']] ?: '' ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <td class="table-light fw-bold row-total-cell"><?= $personelTotal ?: '' ?></td>
                            <?php if ($firstRow): ?>
                                <td rowspan="<?= count($item['teams']) ?>" class="fw-bold region-total-cell"
                                    data-region-id="<?= $regionId ?>"><?= $regionTotal ?: '' ?></td>
                                <td rowspan="<?= count($item['teams']) ?>" class="fw-bold text-uppercase" style="font-size: 9px;">
                                    <?= $item['region'] ?>
                                </td>
                                <?php $firstRow = false; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach;
                endforeach;
            endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <?php if ($hasSubCols): ?>
                <tr class="tfoot-action">
                    <td colspan="3" class="text-end text-muted sticky-col-1" style="font-size: 10px; left: 0; z-index: 25;">
                        İŞLEM BAZINDA GÜNLÜK TOPLAMLAR</td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isSunday = in_array($d, $sundayDays); ?>
                        <?php $idx = 0;
                        foreach ($workTypeCols as $wt):
                            $idx++; ?>
                            <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                data-day="<?= $d ?>" data-wt-code="<?= $wt['code'] ?>">
                                <?= $dailyDetailedTotals[$d][$wt['name']] ?? '' ?>
                            </td>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                    <?php $idx = 0;
                    $allActionTypesGrandTotal = 0;
                    foreach ($workTypeCols as $wt):
                        $idx++;
                        $footActTotal = 0;
                        for ($d = 1; $d <= $daysInMonth; $d++) {
                            $footActTotal += $dailyDetailedTotals[$d][$wt['name']] ?? 0;
                        }
                        $allActionTypesGrandTotal += $footActTotal;
                        ?>
                        <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info action-grand-total-cell <?= ($idx === $subColCount) ? 'day-separator' : '' ?>"
                            data-wt-code="<?= $wt['code'] ?>" data-day="genel-total"><?= $footActTotal ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="table-warning fw-bold action-types-grand-total"><?= $allActionTypesGrandTotal ?: '' ?></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            <tr class="tfoot-general">
                <td colspan="<?= ($activeTab === 'kacakkontrol') ? '2' : '3' ?>" class="text-end sticky-col-1"
                    style="left: 0; z-index: 26; width: <?= ($activeTab === 'kacakkontrol') ? '170px' : '390px' ?>; min-width: <?= ($activeTab === 'kacakkontrol') ? '170px' : '390px' ?>; max-width: <?= ($activeTab === 'kacakkontrol') ? '170px' : '390px' ?>;">
                    GÜNLÜK TOPLAMLAR</td>
                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $isSunday = in_array($d, $sundayDays); ?>
                    <td colspan="<?= $subColCount ?>"
                        class="day-num-header-footer day-separator daily-total-cell <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                        data-day="<?= $d ?>"><?= $dailyTotals[$d] ?: '' ?></td>
                <?php endfor; ?>
                <?php if ($hasSubCols): ?>
                    <td colspan="<?= $subColCount ?>"
                        class="action-totals-day-header-footer day-separator action-grand-total-consolidated"
                        data-day="genel-total">
                        <?= $grandTotal ?: '' ?>
                    </td>
                <?php endif; ?>
                <td class="grand-total-cell"><?= $grandTotal ?: '' ?></td>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <td colspan="2"></td>
                <?php endif; ?>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    $(document).off('click', '#workTypeLegend .legend-item[data-wt-code]').on('click', '#workTypeLegend .legend-item[data-wt-code]', function () {
        $(this).toggleClass('active-filter');

        const activeFilters = $('#workTypeLegend .legend-item.active-filter');
        const totalDays = <?= $daysInMonth ?>;
        const defaultSubColCount = <?= $subColCount ?>;

        if (activeFilters.length === 0) {
            // Show everything
            $('#raporTable .wt-cell-sub').show();
            $('#raporTable .day-num-header').show();
            $('#raporTable .daily-total-cell').show();
            $('#mainGunlerHeader').attr('colspan', totalDays * defaultSubColCount);
            $('#raporTable .day-num-header').attr('colspan', defaultSubColCount);
            $('#actionTotalsHeader').attr('colspan', defaultSubColCount);
            $('#raporTable .action-totals-day-header').attr('colspan', defaultSubColCount);
            $('#raporTable .daily-total-cell').attr('colspan', defaultSubColCount);
            $('#raporTable .action-grand-total-consolidated').attr('colspan', defaultSubColCount);
        } else {
            // Hide all sub-cells first
            $('#raporTable .wt-cell-sub').hide();

            // Show only columns matching selected codes
            activeFilters.each(function () {
                const code = $(this).data('wt-code');
                $(`#raporTable .wt-code-${code}`).show();
            });

            // Dynamically calculate colspans based on visible columns per day
            for (let d = 1; d <= totalDays; d++) {
                const visibleInDay = $(`#raporTable thead tr:nth-child(3) th[data-day="${d}"]`).filter(':visible').length;
                if (visibleInDay > 0) {
                    $(`#raporTable .day-num-header[data-day="${d}"]`).show().attr('colspan', visibleInDay);
                    $(`#raporTable .daily-total-cell[data-day="${d}"]`).show().attr('colspan', visibleInDay);
                } else {
                    $(`#raporTable .day-num-header[data-day="${d}"]`).hide();
                    $(`#raporTable .daily-total-cell[data-day="${d}"]`).hide();
                }
            }

            // Calculate for GENERAL total column
            const visibleInGenel = $(`#raporTable thead tr:nth-child(3) th[data-day="genel-total"]`).filter(':visible').length;
            if (visibleInGenel > 0) {
                $('#actionTotalsHeader').show().attr('colspan', visibleInGenel);
                $('.action-totals-day-header').show().attr('colspan', visibleInGenel);
                $('.action-grand-total-consolidated').show().attr('colspan', visibleInGenel);
            } else {
                $('#actionTotalsHeader').hide();
                $('.action-totals-day-header').hide();
                $('.action-grand-total-consolidated').hide();
            }

            // Calculate main GÜNLER header colspan
            const totalVisible = $('#raporTable thead tr:nth-child(3) th').filter(':visible').length - visibleInGenel;
            $('#mainGunlerHeader').attr('colspan', totalVisible || 1);
        }

        // Update separators based on visibility
        $('#raporTable td, #raporTable th').css('border-right', ''); // Clear inline border-right
        $('#raporTable .day-separator').removeClass('day-separator');

        for (let d = 1; d <= totalDays; d++) {
            const lastVis = $(`#raporTable [data-day="${d}"]`).filter(':visible').last();
            if (lastVis.length) lastVis.addClass('day-separator');

            const dayHead = $(`#raporTable .day-num-header[data-day="${d}"]`);
            if (dayHead.is(':visible')) dayHead.addClass('day-separator');
        }

        const lastGenel = $(`#raporTable [data-day="genel-total"]`).filter(':visible').last();
        if (lastGenel.length) lastGenel.addClass('day-separator');

        const actionHead = $('.action-totals-day-header');
        if (actionHead.is(':visible')) actionHead.addClass('day-separator');

        // Recalculate totals
        updateDynamicTotals();
    });

    $(document).off('click', '#btnExportUnmatched').on('click', '#btnExportUnmatched', function () {
        const year = '<?= $year ?>';
        const month = '<?= $month ?>';
        // Tüm eşleşmeyenleri indirmek için tab=all gönderiyoruz
        const url = `views/puantaj/rapor-excel.php?tab=all&year=${year}&month=${month}&unmatched=1`;
        window.location.href = url;
    });

    function updateDynamicTotals() {
        const totalDays = <?= $daysInMonth ?>;
        let overallGrandSum = 0;
        let actionTypesGrandSum = 0;

        // Her satırın TOPLAM değerini güncelle
        $('#raporTable tbody tr').each(function () {
            let rowSum = 0;
            // Bu satırdaki görünür İŞLEM TOPLAMLARI (GENEL) hücrelerini topla
            $(this).find('td.row-action-total').filter(':visible').each(function () {
                const val = parseInt($(this).text()) || 0;
                rowSum += val;
            });
            // TOPLAM hücresini güncelle
            const totalCell = $(this).find('td.row-total-cell');
            if (totalCell.length) {
                totalCell.text(rowSum || '');
            }
        });

        // Bölge toplamlarını güncelle
        $('.region-total-cell').each(function () {
            const regionId = $(this).data('region-id');
            let regionSum = 0;

            // Bu bölgeye ait tüm satırların row-total-cell değerlerini topla
            $(`#raporTable tbody tr[data-region-id="${regionId}"]`).each(function () {
                const rowTotal = parseInt($(this).find('.row-total-cell').text()) || 0;
                regionSum += rowTotal;
            });

            $(this).text(regionSum || '');
        });

        for (let d = 1; d <= totalDays; d++) {
            let daySum = 0;
            $(`#raporTable tfoot .tfoot-action td.wt-cell-sub[data-day="${d}"]`).filter(':visible').each(function () {
                const val = parseInt($(this).text()) || 0;
                daySum += val;
            });
            const generalCell = $(`#raporTable tfoot .tfoot-general .daily-total-cell[data-day="${d}"]`);
            generalCell.text(daySum || '');
            overallGrandSum += daySum;
        }

        // Calculate action types grand total from visible GENEL columns
        $(`#raporTable tfoot .tfoot-action td.action-grand-total-cell[data-day="genel-total"]`).filter(':visible').each(function () {
            const val = parseInt($(this).text()) || 0;
            actionTypesGrandSum += val;
        });

        // Update action types grand total (İŞLEM BAZINDA GÜNLÜK TOPLAMLAR row)
        $('.action-types-grand-total').text(actionTypesGrandSum || '');

        // Update consolidated action total and grand total
        $('.action-grand-total-consolidated').text(overallGrandSum || '');
        $('.grand-total-cell').text(overallGrandSum || '');
    }
</script>