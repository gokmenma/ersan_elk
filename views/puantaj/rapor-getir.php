<?php
use App\Helper\Date;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\FirmaModel;

if (!isset($Tanimlamalar))
    $Tanimlamalar = new TanimlamalarModel();
if (!isset($EndeksOkuma))
    $EndeksOkuma = new EndeksOkumaModel();
if (!isset($Puantaj))
    $Puantaj = new PuantajModel();
if (!isset($Personel))
    $Personel = new PersonelModel();
if (!isset($Firma))
    $Firma = new FirmaModel();

$firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
$firmaAdi = $firma->firma_adi ?? '';

$year = $year ?? $_GET['year'] ?? date('Y');
$month = $month ?? $_GET['month'] ?? date('m');
$activeTab = $activeTab ?? $_GET['tab'] ?? 'okuma';
$filterPersonelId = $_GET['personel_id'] ?? '';
$filterRegion = $_GET['region'] ?? '';

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$regions = $Tanimlamalar->getEkipBolgeleri();

if ($filterRegion) {
    $regions = array_filter($regions, function ($r) use ($filterRegion) {
        return mb_strtoupper($r, 'UTF-8') == mb_strtoupper($filterRegion, 'UTF-8');
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

if (!function_exists('shortenTeamName')) {
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

// Kacak Kontrol: Get ekip_adi to personel_ids mapping from kacak_kontrol table
$kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

// Pazar günlerini belirle (0 = Pazar)
$sundayDays = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $timestamp = mktime(0, 0, 0, $month, $d, $year);
    if (date('w', $timestamp) == 0) {
        $sundayDays[] = $d;
    }
} ?>
<?php
// --- DATA PRE-PROCESSING ---
$allSummaryPersonels = ($activeTab !== 'kacakkontrol') ? array_keys($summary ?? []) : [];
$tableData = []; // Structure: [ [region_name, teams: [ [team_obj, personel_obj] ] ] ]
$alreadySeenIds = [];

if (true) { // Always use unified logic for all standard tabs
    $validPairs = []; // key: [pId]_[tId] => ['pId' => X, 'tId' => Y]

    // 1. From Summary (The primary source of rows)
    // For okuma/kesme/etc, summary is [pId][tId]
    // For kacak, summary is [teamName][day]
    if (!empty($summary)) {
        if ($activeTab === 'kacakkontrol') {
            foreach ($summary as $teamName => $days) {
                // Find teams with this name to get their IDs

                $matchingTeams = array_filter($allTeams, function ($t) use ($teamName) {
                    return $t->tur_adi === $teamName;
                });
                $tId = !empty($matchingTeams) ? reset($matchingTeams)->id : 0;

                $validPairs['kacak_' . $teamName] = [
                    'pId' => 'kacak_' . $teamName,
                    'tId' => $tId,
                    'isKacak' => true,
                    'teamName' => $teamName
                ];
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

    // 2. From History Assignments (To show active personnel even without data)
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
            // Kaçak Kontrol - Sadece verisi olanlar summary üzerinden eklensin isteniyor.
            // Bu nedenle assignments üzerinden otomatik ekleme yapmıyoruz.
            $isValid = false;
        } else {
            // Other unofficial tabs might show everyone
            $isValid = true;
        }

        if ($isValid) {
            $validPairs[$assign->personel_id . '_' . $assign->ekip_kodu_id] = [
                'pId' => $assign->personel_id,
                'tId' => $assign->ekip_kodu_id
            ];
        }
    }

    // Now organize pairs into regions
    $regionGrouped = []; // regionName => [teams => []]

    foreach ($validPairs as $pair) {
        $pId = $pair['pId'];
        $tId = $pair['tId'];

        if (isset($pair['isKacak'])) {
            $teamName = $pair['teamName'];
            $team = $teamById[$tId] ?? (object) ['id' => 0, 'tur_adi' => $teamName, 'ekip_bolge' => 'TANIMSIZ BÖLGE'];

            // Get combined personnel names
            $pIdsStr = $kacakPersonelMapping[$teamName] ?? '';
            if (empty($pIdsStr)) {
                $mappedIds = [];
                foreach ($activeAssignments as $assign) {
                    if ($assign->ekip_kodu_id == $tId && $tId > 0)
                        $mappedIds[] = $assign->personel_id;
                }
                $pIdsStr = implode(',', array_unique($mappedIds));
            }

            $names = [];
            foreach (explode(',', $pIdsStr) as $id) {
                if (isset($personelById[trim($id)])) {
                    $names[] = $personelById[trim($id)]->adi_soyadi;
                }
            }

            $p = (object) [
                'id' => 0,
                'adi_soyadi' => !empty($names) ? implode(', ', $names) : $teamName,
                'ekip_no' => 0
            ];
            $regionName = $team->ekip_bolge ?: 'TANIMSIZ BÖLGE';
        } else {
            $p = $personelById[$pId] ?? null;
            if (!$p)
                continue;

            $team = $teamById[$tId] ?? (object) ['id' => 0, 'tur_adi' => '-', 'ekip_bolge' => 'TANIMSIZ BÖLGE'];
            $regionName = $team->ekip_bolge ?: 'TANIMSIZ BÖLGE';
        }

        if ($filterRegion && mb_strtoupper($regionName, 'UTF-8') !== mb_strtoupper($filterRegion, 'UTF-8'))
            continue;

        $regionGrouped[$regionName][] = [
            'team' => $team,
            'personel' => $p,
            'pId' => $pId,
            'tId' => $tId
        ];
    }

    // Build final tableData from grouped regions
    foreach ($regions as $rName) {
        if (!empty($regionGrouped[$rName])) {
            $teams = $regionGrouped[$rName];
            usort($teams, function ($a, $b) {
                return strcoll((string) ($a['personel']->adi_soyadi ?? ''), (string) ($b['personel']->adi_soyadi ?? ''));
            });
            $tableData[] = [
                'region' => $rName,
                'teams' => $teams
            ];
            unset($regionGrouped[$rName]);
        }
    }

    // Remaining (Tanımsızlar)
    foreach ($regionGrouped as $rName => $teams) {
        usort($teams, function ($a, $b) {
            return strcoll((string) ($a['personel']->adi_soyadi ?? ''), (string) ($b['personel']->adi_soyadi ?? ''));
        });
        $tableData[] = [
            'region' => $rName,
            'teams' => $teams
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
                $pId = $tData['pId'];
                $tId = $tData['tId'];
                if (isset($summary[$pId][$tId])) {
                    foreach ($summary[$pId][$tId] as $dayData) {
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

    /* Pazar günü (tatil) arka plan rengi */
    .sunday-cell,
    .sunday-cell.day-bg-alt,
    td.sunday-cell,
    th.sunday-cell {
        background-color: rgba(244, 106, 106, 0.1) !important;
        color: #f46a6a !important;
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
        background-color: var(--bs-card-bg, #fff);
        color: var(--bs-body-color, #333);
    }

    #raporTable th,
    #raporTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid var(--bs-border-color, #eee) !important;
        border-bottom: 1px solid var(--bs-border-color, #e0e0e0) !important;
        padding: 6px 8px !important;
        line-height: normal !important;
        white-space: nowrap;
    }

    .day-separator {
        border-right: 2px solid var(--bs-border-color, #555) !important;
    }

    .day-bg-alt {
        background-color: rgba(0, 0, 0, 0.02) !important;
    }

    #raporTable thead th {
        background-color: var(--bs-card-bg, #f8f9fa) !important;
        font-weight: 600;
        font-size: 11px;
        color: var(--bs-heading-color, #333);
        position: sticky;
        z-index: 100;
    }

    #raporTable thead tr:nth-child(1) th {
        top: 0;
        z-index: 105 !important;
        height: 32px;
    }

    #raporTable thead tr:nth-child(2) th {
        top: 31px;
        z-index: 104 !important;
        height: 34px;
    }

    .column-search {
        height: 30px !important;
        font-size: 11px !important;
        padding: 4px 8px !important;
        background-color: #ffffff !important;
        border: 1px solid #e9ecef !important;
        border-radius: 6px !important;
        width: 100% !important;
        transition: all 0.2s ease;
        color: #495057;
        margin: 0 !important;
    }

    .column-search::placeholder {
        color: #adb5bd;
        text-transform: none;
        font-weight: 400;
    }

    .column-search:focus {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.15) !important;
        outline: none;
    }

    .search-row th {
        padding: 0 !important;
        background-color: #f8f9fa !important;
    }

    #raporTable tfoot td {
        position: sticky;
        z-index: 110;
        /* Headers and sticky columns use 100+, footer needs to be high */
        background-color: #f8f9fa !important;
        height: 40px;
        min-height: 40px;
        max-height: 40px;
        padding: 0 8px !important;
        border-top: 2px solid #dee2e6 !important;
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
        /* Subtle shadow to distinguish footer */
    }

    #raporTable tfoot tr.tfoot-general td {
        bottom: 0;
        z-index: 112;
    }

    #raporTable tfoot tr.tfoot-action td {
        bottom: 40px;
        z-index: 111;
    }

    /* First column in footer needs to be sticky on the left too */
    #raporTable tfoot td.sticky-col-1 {
        left: 0;
        z-index: 160;
        /* Higher than normal footer cells */
    }

    .sticky-col-1,
    .sticky-col-2,
    .sticky-col-3,
    .kacakkontrol-name-col {
        position: sticky;
        z-index: 80;
        background-color: var(--bs-card-bg, #fff) !important;
        color: var(--bs-body-color, #333) !important;
    }

    .sticky-col-1 {
        left: 0;
        border-left: 1px solid var(--bs-border-color, #ccc) !important;
    }

    .sticky-col-2 {
        left: 51px;
    }

    .sticky-col-3 {
        left: 172px;
    }

    .kacakkontrol-name-col {
        left: 51px;
        width: 340px !important;
        min-width: 340px !important;
        max-width: 340px !important;
    }

    /* Row 1 Sticky Header Columns */
    #raporTable thead tr:nth-child(1) .sticky-col-1,
    #raporTable thead tr:nth-child(1) .sticky-col-2,
    #raporTable thead tr:nth-child(1) .sticky-col-3,
    #raporTable thead tr:nth-child(1) .kacakkontrol-name-col {
        z-index: 160 !important;
        background-color: var(--bs-card-bg, #f8f9fa) !important;
    }

    /* Row 2 Sticky Header Columns (Search row) */
    #raporTable thead tr:nth-child(2) .sticky-col-1,
    #raporTable thead tr:nth-child(2) .sticky-col-2,
    #raporTable thead tr:nth-child(2) .sticky-col-3,
    #raporTable thead tr:nth-child(2) .kacakkontrol-name-col {
        z-index: 155 !important;
        background-color: var(--bs-card-bg, #f8f9fa) !important;
    }

    .table-responsive {
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 4px;
        overflow: auto;
        max-width: 100%;
        background: var(--bs-card-bg, #fff);
        /* Height is now managed dynamically by adjustTableHeight() in raporlar.php */
        min-height: 300px;
        position: relative;
    }

    /* Dark Mode Overrides - Aggressive Targeting */
    html[data-bs-theme="dark"] #raporTable,
    html[data-theme-mode="dark"] #raporTable,
    [data-bs-theme="dark"] #raporTable,
    [data-theme-mode="dark"] #raporTable {
        background-color: #191e22 !important;
        color: #eff2f7 !important;
    }

    html[data-bs-theme="dark"] #raporTable th,
    html[data-theme-mode="dark"] #raporTable th,
    html[data-bs-theme="dark"] #raporTable td,
    html[data-theme-mode="dark"] #raporTable td,
    [data-bs-theme="dark"] #raporTable th,
    [data-theme-mode="dark"] #raporTable th,
    [data-bs-theme="dark"] #raporTable td,
    [data-theme-mode="dark"] #raporTable td {
        border-color: #32394e !important;
    }

    html[data-bs-theme="dark"] #raporTable thead th,
    html[data-theme-mode="dark"] #raporTable thead th,
    html[data-bs-theme="dark"] #raporTable tfoot td,
    html[data-theme-mode="dark"] #raporTable tfoot td,
    [data-bs-theme="dark"] #raporTable thead th,
    [data-theme-mode="dark"] #raporTable thead th,
    [data-bs-theme="dark"] #raporTable tfoot td,
    [data-theme-mode="dark"] #raporTable tfoot td {
        background-color: #282f36 !important;
        color: #eff2f7 !important;
    }

    html[data-bs-theme="dark"] .sticky-col-1,
    html[data-theme-mode="dark"] .sticky-col-1,
    html[data-bs-theme="dark"] .sticky-col-2,
    html[data-theme-mode="dark"] .sticky-col-2,
    html[data-bs-theme="dark"] .sticky-col-3,
    html[data-theme-mode="dark"] .sticky-col-3,
    html[data-bs-theme="dark"] .kacakkontrol-name-col,
    html[data-theme-mode="dark"] .kacakkontrol-name-col,
    [data-bs-theme="dark"] .sticky-col-1,
    [data-theme-mode="dark"] .sticky-col-1,
    [data-bs-theme="dark"] .sticky-col-2,
    [data-theme-mode="dark"] .sticky-col-2,
    [data-bs-theme="dark"] .sticky-col-3,
    [data-theme-mode="dark"] .sticky-col-3,
    [data-bs-theme="dark"] .kacakkontrol-name-col,
    [data-theme-mode="dark"] .kacakkontrol-name-col {
        background-color: #282f36 !important;
        color: #eff2f7 !important;
    }

    html[data-bs-theme="dark"] #raporTable .sunday-cell,
    html[data-theme-mode="dark"] #raporTable .sunday-cell,
    [data-bs-theme="dark"] #raporTable .sunday-cell,
    [data-theme-mode="dark"] #raporTable .sunday-cell {
        background-color: rgba(244, 106, 106, 0.15) !important;
        color: #f46a6a !important;
    }

    html[data-bs-theme="dark"] .table-responsive,
    html[data-theme-mode="dark"] .table-responsive,
    [data-bs-theme="dark"] .table-responsive,
    [data-theme-mode="dark"] .table-responsive {
        border-color: #32394e !important;
        background: #191e22 !important;
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
                <th class="sticky-col-1" style="width: 50px; min-width: 50px; max-width: 50px;">SIRA</th>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th class="sticky-col-2" style="width: 120px; min-width: 120px; max-width: 120px;">EKİP / BÖLGE</th>
                <?php endif; ?>
                <th class="<?= ($activeTab === 'kacakkontrol') ? 'kacakkontrol-name-col' : 'sticky-col-3' ?>"
                    style="<?= ($activeTab === 'kacakkontrol') ? '' : 'width: 220px; min-width: 220px; max-width: 220px;' ?>">
                    İSİM SOYİSİM</th>

                <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'): ?>
                    <th colspan="<?= $daysInMonth * $subColCount ?>" id="mainGunlerHeader">GÜNLER</th>
                <?php else: ?>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isSunday = in_array($d, $sundayDays);
                        ?>
                        <th colspan="<?= $subColCount ?>"
                            class="day-num-header day-separator <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                            data-day="<?= $d ?>">
                            <?= $d ?>
                        </th><?php endfor; ?>
                <?php endif; ?>

                <?php if ($hasSubCols): ?>
                    <th colspan="<?= $subColCount ?>" id="actionTotalsHeader">İŞLEM TOPLAMLARI</th><?php endif; ?>
                <th>TOPLAM</th>
                <th>BÖLGE TOP.</th>
                <th>BÖLGE ADI</th>
            </tr>
            <tr class="search-row">
                <th class="sticky-col-1"><input type="text" class="column-search" placeholder="SIRA" data-col="sira">
                </th>
                <?php if ($activeTab !== 'kacakkontrol'): ?>
                    <th class="sticky-col-2"><input type="text" class="column-search" placeholder="EKİP / BÖLGE"
                            data-col="ekip"></th>
                <?php endif; ?>
                <th class="<?= ($activeTab === 'kacakkontrol') ? 'kacakkontrol-name-col' : 'sticky-col-3' ?>">
                    <input type="text" class="column-search" placeholder="İSİM SOYİSİM" data-col="isim">
                </th>

                <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'): ?>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isSunday = in_array($d, $sundayDays);
                        ?>
                        <th colspan="<?= $subColCount ?>"
                            class="day-num-header day-separator <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                            data-day="<?= $d ?>">
                            <?= $d ?>
                        </th><?php endfor; ?>
                <?php else: ?>
                    <?php if ($hasSubCols): ?>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $isSunday = in_array($d, $sundayDays);
                            $idx = 0;
                            foreach ($workTypeCols as $wt):
                                $idx++; ?>
                                <th class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= ($d % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                    data-day="<?= $d ?>" data-wt-code="<?= $wt['code'] ?>"><span
                                        class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?><?php endfor; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($hasSubCols): ?>
                    <?php $idx = 0;
                    foreach ($workTypeCols as $wt):
                        $idx++; ?>
                        <th class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info <?= ($idx === $subColCount) ? 'day-separator' : '' ?>"
                            data-day="genel-total" data-wt-code="<?= $wt['code'] ?>"><span
                                class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?>
                <?php endif; ?>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sira = 1;
            $dailyTotals = array_fill(1, $daysInMonth, 0);
            $dailyDetailedTotals = [];
            $grandTotal = 0;

            foreach ($tableData as $item):
                $regionTotal = 0;
                foreach ($item['teams'] as $tData) {
                    $pId = $tData['pId'];
                    $tId = $tData['tId'];
                    $team = $tData['team'];

                    if ($activeTab === 'kacakkontrol') {
                        if (isset($summary[$team->tur_adi])) {
                            $regionTotal += array_sum($summary[$team->tur_adi]);
                        }
                    } else {
                        if (isset($summary[$pId][$tId])) {
                            if ($activeTab === 'okuma') {
                                $regionTotal += array_sum($summary[$pId][$tId]);
                            } else {
                                // İŞLEM TOPLAMLARI kolonlarının toplamını hesapla
                                foreach ($workTypeCols as $wt) {
                                    $wtTotal = 0;
                                    foreach ($summary[$pId][$tId] as $dayData) {
                                        $wtTotal += $dayData[$wt['name']] ?? 0;
                                    }
                                    $regionTotal += $wtTotal;
                                }
                            }
                        }
                    }
                }

                $firstRow = true;
                foreach ($item['teams'] as $tData):
                    $team = $tData['team'];
                    $personel = $tData['personel'];
                    $pId = $tData['pId'];
                    $tId = $tData['tId'];
                    $personelTotal = 0;

                    if ($activeTab === 'kacakkontrol') {
                        if (isset($summary[$team->tur_adi])) {
                            // Personnel-based total in kacak is tricky; here we show the full team value 
                            // because kacak records don't have individual personnel links in the data rows themselves.
                            // However, to satisfy the report, we show the team's data on each person's row.
                            $personelTotal = array_sum($summary[$team->tur_adi]);
                            $grandTotal += $personelTotal;
                        }
                    } elseif ($activeTab === 'okuma' && isset($summary[$pId][$tId])) {
                        $personelTotal = array_sum($summary[$pId][$tId]);
                        $grandTotal += $personelTotal;
                    }
                    // Bölge ID'si olarak bölge adının hash'i kullanılıyor
                    $regionId = md5($item['region']);
                    ?>
                    <tr data-region-id="<?= $regionId ?>">
                        <td class="sticky-col-1"><?= $sira++ ?></td>
                        <?php if ($activeTab !== 'kacakkontrol'): ?>
                            <td class="sticky-col-2">
                                <?= shortenTeamName($team->tur_adi, $firmaAdi) ?>
                            </td>
                        <?php endif; ?>
                        <td
                            class="<?= ($activeTab === 'kacakkontrol') ? 'kacakkontrol-name-col' : 'sticky-col-3' ?> text-start">
                            <?php if ($activeTab === 'kacakkontrol'): ?>
                                <?php
                                $pIdsStr = $kacakPersonelMapping[$team->tur_adi] ?? '';
                                if (!empty($pIdsStr)) {
                                    $nameLinks = [];
                                    foreach (explode(',', $pIdsStr) as $pid) {
                                        $pid = trim($pid);
                                        if (isset($personelById[$pid])) {
                                            $pers = $personelById[$pid];
                                            $nameLinks[] = '<a class="fw-bold text-primary" target="_blank" href="index?p=personel/manage&id=' . $pid . '">' . htmlspecialchars($pers->adi_soyadi) . '</a>';
                                        }
                                    }
                                    echo !empty($nameLinks) ? implode(', ', $nameLinks) : htmlspecialchars($personel->adi_soyadi);
                                } else {
                                    echo htmlspecialchars($personel->adi_soyadi);
                                }
                                ?>
                            <?php else: ?>
                                <a class="fw-bold text-primary" target="_blank" href="index?p=personel/manage&id=<?= $pId ?>">
                                    <?= htmlspecialchars($personel->adi_soyadi) ?>
                                </a>
                            <?php endif; ?>
                        </td>

                        <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'): ?>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $val = 0;
                                $pIdsStr = '';
                                if ($activeTab === 'kacakkontrol') {
                                    $val = $summary[$team->tur_adi][$d] ?? 0;
                                    $pIdsStr = $kacakPersonelMapping[$team->tur_adi] ?? '';
                                } else {
                                    $val = $summary[$pId][$tId][$d] ?? 0;
                                }

                                $dailyTotals[$d] += $val;
                                $isSunday = in_array($d, $sundayDays);
                                $currentDate = str_pad($d, 2, '0', STR_PAD_LEFT) . "." . str_pad($month, 2, '0', STR_PAD_LEFT) . "." . $year;
                                ?>
                                <td class="<?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($d === $daysInMonth) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?> <?= ($activeTab === 'kacakkontrol') ? 'kacak-quick-cell' : '' ?>"
                                    <?php if ($activeTab === 'kacakkontrol'): ?> data-date="<?= $currentDate ?>"
                                        data-personel-ids="<?= $pIdsStr ?: $pId ?>" data-ekip-adi="<?= htmlspecialchars($team->tur_adi) ?>"
                                        style="cursor: cell;" title="Çift tıklayarak yeni kayıt ekle" <?php endif; ?>>
                                    <?= $val ?: '' ?>
                                </td>
                            <?php endfor; ?>
                        <?php else: ?>
                            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                                $isSunday = in_array($d, $sundayDays); ?>
                                <?php $idx = 0;
                                foreach ($workTypeCols as $wt):
                                    $idx++;
                                    $val = $summary[$pId][$tId][$d][$wt['name']] ?? 0;
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
                                foreach ($summary[$pId][$tId] ?? [] as $dayData) {
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
            ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <?php if ($hasSubCols): ?>
                <tr class="tfoot-action">
                    <td colspan="<?= ($activeTab === 'kacakkontrol') ? '2' : '3' ?>"
                        class="text-end text-muted sticky-col-1" style="font-size: 10px; left: 0; z-index: 165;">
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
                    <td class="table-warning fw-bold action-types-grand-total"><?= $allActionTypesGrandTotal ?: '' ?>
                    </td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            <tr class="tfoot-general">
                <td colspan="<?= ($activeTab === 'kacakkontrol') ? '2' : '3' ?>" class="text-end sticky-col-1"
                    style="left: 0; z-index: 166; width: 390px; min-width: 390px; max-width: 390px;">
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
                <td colspan="2"></td>
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