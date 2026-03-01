<?php
use App\Helper\Date;
use App\Helper\Security;
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
if (!isset($Settings))
    $Settings = new \App\Model\SettingsModel();

$firma = $Firma->getFirma($_SESSION['firma_id'] ?? 0);
$firmaAdi = $firma->firma_adi ?? '';

$year = $year ?? $_GET['year'] ?? date('Y');
$month = $month ?? $_GET['month'] ?? date('m');
$activeTab = $activeTab ?? $_GET['tab'] ?? 'okuma';
$filterPersonelId = $_GET['personel_id'] ?? '';
$filterRegion = $_GET['region'] ?? '';

// Month name to number mapping safeguard
if (!is_numeric($month)) {
    $monthMapping = array_flip(Date::MONTHS);
    if (isset($monthMapping[$month])) {
        $month = $monthMapping[$month];
    }
}

$filterType = $_GET['filter_type'] ?? 'period';

// Date range logic
if ($filterType === 'range' && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $startDateStr = date('Y-m-d', strtotime($_GET['start_date']));
    $endDateStr = date('Y-m-d', strtotime($_GET['end_date']));
} else {
    $m_val = str_pad($month, 2, '0', STR_PAD_LEFT);
    $startDateStr = "$year-$m_val-01";
    $endDateStr = date('Y-m-t', strtotime($startDateStr));
}

// Generate array of dates
$reportDates = [];
$cPeriod = new DatePeriod(
    new DateTime($startDateStr),
    new DateInterval('P1D'),
    (new DateTime($endDateStr))->modify('+1 day')
);
foreach ($cPeriod as $date) {
    $reportDates[] = $date->format('Y-m-d');
}
$isCrossMonth = false;
if (count($reportDates) > 0) {
    $firstMonth = date('m', strtotime($reportDates[0]));
    $lastMonth = date('m', strtotime(end($reportDates)));
    if ($firstMonth != $lastMonth)
        $isCrossMonth = true;
}

$daysCount = count($reportDates);

// Fetch Manuel Düşüm totals for the range for all relevant tabs
$manuelDusumMap = [];
if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])) {
    $sqlDusum = "SELECT personel_id, ekip_kodu_id, SUM(ABS(sonuclanmis)) as total_dusum 
                 FROM yapilan_isler 
                 WHERE firma_id = ? 
                 AND is_emri_tipi = 'Manuel Düşüm' 
                 AND tarih BETWEEN ? AND ? 
                 AND silinme_tarihi IS NULL 
                 GROUP BY personel_id, ekip_kodu_id";
    $stmtDusum = $Puantaj->db->prepare($sqlDusum);
    $stmtDusum->execute([$_SESSION['firma_id'], $startDateStr, $endDateStr]);
    while ($row = $stmtDusum->fetch(PDO::FETCH_OBJ)) {
        $manuelDusumMap[$row->personel_id][$row->ekip_kodu_id] = $row->total_dusum;
    }
}

$regions = $Tanimlamalar->getEkipBolgeleri();

if ($filterRegion) {
    $regions = array_filter($regions, function ($r) use ($filterRegion) {
        return mb_strtoupper($r, 'UTF-8') == mb_strtoupper($filterRegion, 'UTF-8');
    });
}

// Fetch summary based on active tab
$workTypes = [];
if ($activeTab === 'okuma') {
    $summary = $EndeksOkuma->getSummaryByRange($startDateStr, $endDateStr);
} elseif ($activeTab === 'kacakkontrol') {
    $summary = $Puantaj->getKacakSummaryByRange($startDateStr, $endDateStr);
} elseif ($activeTab === 'sokme_takma') {
    $SayacDegisim = new \App\Model\SayacDegisimModel();
    $summary = $SayacDegisim->getSummaryDetailedByRange($startDateStr, $endDateStr);
    $workTypes = $SayacDegisim->getDistinctWorkTypes();
} else {
    $summary = $Puantaj->getSummaryDetailedByRange($startDateStr, $endDateStr);
    // Fetch work types based on active tab from tanimlamalar
    $workTypes = $Tanimlamalar->getIsTurleriByRaporTuru($activeTab);

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

// Fetch all active assignments for this range
$activeAssignments = $Personel->getAllActiveAssignmentsInRange($startDateStr, $endDateStr);

// Pre-fetch all teams to have a lookup
$allTeams = $Tanimlamalar->getEkipKodlari();
$teamById = [];
foreach ($allTeams as $t) {
    $teamById[$t->id] = $t;
}

// Kacak Kontrol: Get ekip_adi to personel_ids mapping from kacak_kontrol table
$kacakPersonelMapping = $Puantaj->getKacakPersonelMapping();

// Pazar günlerini belirle (date'e göre)
$sundayDates = [];
foreach ($reportDates as $date) {
    if (date('w', strtotime($date)) == 0) {
        $sundayDates[] = $date;
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
        foreach ($summary as $pId => $teams) {
            if ($activeTab === 'kacakkontrol') {
                $teamName = $pId; // In kacak summary, pId is actually teamName
                $matchingTeams = array_filter($allTeams, function ($t) use ($teamName) {
                    return $t->tur_adi === $teamName;
                });
                $team = !empty($matchingTeams) ? reset($matchingTeams) : null;
                $tId = $team ? $team->id : 0;

                if ($team && preg_match('/EK[İI]P-?\s?(\d+)/ui', $team->tur_adi, $m)) {
                    $teamNo = (int) $m[1];
                    if (!\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'kacakkontrol', $Settings)) {
                        continue;
                    }
                }

                $validPairs['kacak_' . $teamName] = [
                    'pId' => 'kacak_' . $teamName,
                    'tId' => $tId,
                    'isKacak' => true,
                    'teamName' => $teamName
                ];
            } else {
                foreach ($teams as $tId => $data) {
                    if ($filterPersonelId && $pId != $filterPersonelId)
                        continue;

                    // Eğer okuma değilse, bu ekip/personel ikilisinin bu tabdaki iş türlerinden en az birine sahip olup olmadığını kontrol et
                    $hasRelevantData = true;
                    if ($activeTab !== 'okuma') {
                        $hasRelevantData = false;
                        foreach ($data as $day => $workTypeCounts) {
                            foreach ($workTypeCounts as $workTypeName => $count) {
                                foreach ($workTypeCols as $wtCol) {
                                    if ($wtCol['name'] === $workTypeName && $count > 0) {
                                        $hasRelevantData = true;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }

                    // Eğer ilgili verisi yoksa, en azından bu tabın belirlenen ekip aralığında mı diye bak (Eğer aralıktaysa boş da olsa gelsin diye)
                    if (!$hasRelevantData) {
                        $team = $teamById[$tId] ?? null;
                        if ($team && preg_match('/EK[İI]P-?\s?(\d+)/ui', $team->tur_adi, $m)) {
                            $teamNo = (int) $m[1];
                            if (!\App\Helper\EkipHelper::isTeamInTabRange($teamNo, $activeTab, $Settings)) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }

                    $validPairs[$pId . '_' . $tId] = ['pId' => $pId, 'tId' => $tId];
                }
            }
        }
    }

    // 2. From History Assignments (To show active personnel even without data)
    foreach ($activeAssignments as $assign) {
        if ($filterPersonelId && $assign->personel_id != $filterPersonelId)
            continue;

        // Ekip kodu aralık kontrolü (Dinamik) - Birincil koşul olarak kontrol et
        $team = $teamById[$assign->ekip_kodu_id] ?? null;
        if (!$team || !preg_match('/EK[İI]P-?\s?(\d+)/ui', $team->tur_adi, $m)) {
            continue; // Ekip bulunamadı veya ekip adı formatı uymuyor, atla
        }
        $teamNo = (int) $m[1];

        $isValid = false;
        $personelDepts = !empty($assign->departman) ? array_map('trim', explode(',', $assign->departman)) : [];
        $gorev = $assign->gorev ?? '';

        if ($activeTab === 'okuma') {
            if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'okuma', $Settings) && (in_array('Endeks Okuma', $personelDepts) || in_array('Okuma', $personelDepts))) {
                $isValid = true;
            }
        } elseif ($activeTab === 'kesme') {
            if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'kesme', $Settings) && (in_array('Kesme Açma', $personelDepts) || in_array('Kesme-Açma', $personelDepts))) {
                $isValid = true;
            }
        } elseif ($activeTab === 'sokme_takma') {
            if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'sokme_takma', $Settings) && (in_array('Sayaç Sökme Takma', $personelDepts) || in_array('Sökme Takma', $personelDepts))) {
                $isValid = true;
            }
        } elseif ($activeTab === 'muhurleme') {
            if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, 'muhurleme', $Settings) && in_array('Mühürleme', $personelDepts)) {
                $isValid = true;
            }
        } elseif ($activeTab === 'kacakkontrol') {
            // Kaçak Kontrol - Sadece verisi olanlar summary üzerinden eklensin isteniyor.
            // Bu nedenle assignments üzerinden otomatik ekleme yapmıyoruz.
            $isValid = false;
        } else {
            // Other unofficial tabs might show everyone, but still respect team range
            if (\App\Helper\EkipHelper::isTeamInTabRange($teamNo, $activeTab, $Settings)) {
                $isValid = true;
            }
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
            $team = $teamById[$tId] ?? (object) ['id' => 0, 'tur_adi' => '-', 'ekip_bolge' => 'TANIMSIZ BÖLGE'];

            if (!$p) {
                // Determine missing team
                $p = (object) [
                    'id' => 0,
                    'adi_soyadi' => '<span class="text-danger fw-bold"><i class="bx bx-error-circle"></i> Eşleşmeyen Ekip: ' . htmlspecialchars($team->tur_adi) . '</span>',
                    'ekip_no' => $team->id
                ];
            }

            $regionName = $team->ekip_bolge ?: 'TANIMSIZ BÖLGE';
        }

        if ($filterRegion !== '' && mb_strtoupper($regionName, 'UTF-8') !== mb_strtoupper($filterRegion, 'UTF-8'))
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

<?php
$tabNames = [
    'okuma' => 'Endeks Okuma',
    'kesme' => 'Kesme/Açma',
    'sokme_takma' => 'Sayaç Sökme Takma',
    'muhurleme' => 'Mühürleme',
    'kacakkontrol' => 'Kaçak Kontrol'
];
$currentTabName = $tabNames[$activeTab] ?? 'Rapor';
?>

<div class="report-legend d-flex align-items-center" id="workTypeLegend">
    <?php if ($activeTab !== 'okuma' && !empty($workTypeCols)): ?>
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

        <?php if ($unmatchedCount > 0): ?>
            <div class="legend-item ms-auto border-danger text-danger border-dashed me-2" id="btnExportUnmatched"
                title="Tanımlı olmayan iş emri sonuçları var. Raporu indirerek kontrol edebilirsiniz."
                style="cursor: pointer; border: 1px dashed #f46a6a; padding: 2px 10px; border-radius: 6px; background-color: rgba(244, 106, 106, 0.05); font-weight: 500;">
                <i class="bx bx-error-circle me-1 animate-pulse"></i>
                Ücretsiz İşlemler: <span class="badge bg-danger ms-1"><?= $unmatchedCount ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="ms-auto d-flex gap-2">
        <?php if (in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])): ?>
            <button type="button" class="btn btn-outline-info btn-sm d-flex align-items-center gap-1"
                id="btnToggleDailyTotals">
                <i class="bx bx-show me-1"></i> Günlük Topl. Göster
            </button>
        <?php endif; ?>
        <button type="button"
            class="btn btn-outline-secondary btn-sm btn-tab-settings d-flex align-items-center justify-content-center"
            style="width: 32px; height: 32px; padding: 0;" data-tab="<?= $activeTab ?>"
            data-tab-name="<?= $currentTabName ?>" title="<?= $currentTabName ?> Ayarları">
            <i class="bx bx-cog fs-4"></i>
        </button>
    </div>
</div>

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

    #raporTable.summary-mode .wt-cell-sub {
        display: none !important;
    }

    #raporTable.summary-mode .day-total-col {
        display: table-cell !important;
    }

    #raporTable.summary-mode #actionTotalsHeader,
    #raporTable.summary-mode .tfoot-action,
    #raporTable.summary-mode .action-grand-total-consolidated,
    #raporTable.summary-mode .action-types-grand-total,
    #raporTable.summary-mode .row-action-total {
        display: none !important;
    }

    .legend-hidden {
        display: none !important;
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
$totalColsInDays = count($reportDates) * $subColCount;
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
                    <th colspan="<?= count($reportDates) * $subColCount ?>" id="mainGunlerHeader">GÜNLER</th>
                <?php else: ?>
                    <?php
                    $dateIdx = 0;
                    foreach ($reportDates as $date):
                        $dateIdx++;
                        $isSunday = in_array($date, $sundayDates);
                        $headerLabel = $isCrossMonth ? date('d.m', strtotime($date)) : date('j', strtotime($date));
                        ?>
                        <th colspan="<?= $subColCount ?>"
                            class="day-num-header day-separator <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                            data-date="<?= $date ?>" data-base-colspan="<?= $subColCount ?>">
                            <?= $headerLabel ?>
                        </th><?php endforeach; ?>
                <?php endif; ?>

                <?php if ($hasSubCols): ?>
                    <th colspan="<?= $subColCount ?>" id="actionTotalsHeader">İŞLEM TOPLAMLARI</th><?php endif; ?>
                <th>TOPLAM</th>
                <?php if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])): ?>
                    <th>(-) Sayı</th>
                    <th>Kalan</th>
                <?php endif; ?>
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
                    <?php
                    $dateIdx = 0;
                    foreach ($reportDates as $date):
                        $dateIdx++;
                        $isSunday = in_array($date, $sundayDates);
                        $headerLabel = $isCrossMonth ? date('d.m', strtotime($date)) : date('j', strtotime($date));
                        ?>
                        <th colspan="<?= $subColCount ?>"
                            class="day-num-header day-separator <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                            data-date="<?= $date ?>">
                            <?= $headerLabel ?>
                        </th><?php endforeach; ?>
                <?php else: ?>
                    <?php if ($hasSubCols): ?>
                        <?php
                        $dateIdx = 0;
                        foreach ($reportDates as $date):
                            $dateIdx++;
                            $isSunday = in_array($date, $sundayDates);
                            $idx = 0;
                            foreach ($workTypeCols as $wt):
                                $idx++; ?>
                                <th class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount && !in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                    data-date="<?= $date ?>" data-wt-code="<?= $wt['code'] ?>"><span
                                        class="vertical-text"><?= $wt['code'] ?></span></th><?php endforeach; ?>
                            <?php if (in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])): ?>
                                <th class="day-total-col table-light day-separator <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                    data-date="<?= $date ?>" style="display: none;">
                                    <span class="vertical-text">TOPLAM</span>
                                </th>
                            <?php endif; ?>
                        <?php endforeach; ?>
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
                <?php if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])): ?>
                    <th></th>
                    <th></th>
                <?php endif; ?>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sira = 1;
            $dailyTotals = [];
            foreach ($reportDates as $date) {
                $dailyTotals[$date] = 0;
            }
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
                                            $nameLinks[] = '<a class="fw-bold text-primary" target="_blank" href="index?p=personel/manage&id=' . Security::encrypt($pid) . '">' . htmlspecialchars($pers->adi_soyadi) . '</a>';
                                        }
                                    }
                                    echo !empty($nameLinks) ? implode(', ', $nameLinks) : htmlspecialchars($personel->adi_soyadi);
                                } else {
                                    echo htmlspecialchars($personel->adi_soyadi);
                                }
                                ?>
                            <?php else: ?>
                                <?php if (empty($personel->id)): ?>
                                    <?= $personel->adi_soyadi ?>
                                <?php else: ?>
                                    <a class="fw-bold text-primary" target="_blank"
                                        href="index?p=personel/manage&id=<?= Security::encrypt($personel->id) ?>">
                                        <?= htmlspecialchars($personel->adi_soyadi) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>

                        <?php if ($activeTab === 'okuma' || $activeTab === 'kacakkontrol'): ?>
                            <?php foreach ($reportDates as $date):
                                $val = 0;
                                $pIdsStr = '';
                                if ($activeTab === 'kacakkontrol') {
                                    $val = $summary[$team->tur_adi][$date] ?? 0;
                                    $pIdsStr = $kacakPersonelMapping[$team->tur_adi] ?? '';
                                } else {
                                    $val = $summary[$pId][$tId][$date] ?? 0;
                                }

                                $dailyTotals[$date] += $val;
                                $isSunday = in_array($date, $sundayDates);
                                $currentDateFormatted = date('d.m.Y', strtotime($date));
                                ?>
                                <td class="<?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($date === end($reportDates)) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?> <?= ($activeTab === 'kacakkontrol') ? 'kacak-quick-cell' : '' ?>"
                                    data-date="<?= $date ?>" <?php if ($activeTab === 'kacakkontrol'): ?>
                                        data-personel-ids="<?= $pIdsStr ?: $pId ?>" data-ekip-adi="<?= htmlspecialchars($team->tur_adi) ?>"
                                        style="cursor: cell;" title="Çift tıklayarak yeni kayıt ekle" <?php endif; ?>>
                                    <?= $val ?: '' ?>
                                </td>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php
                            $dateIdx = 0;
                            foreach ($reportDates as $date):
                                $dateIdx++;
                                $isSunday = in_array($date, $sundayDates); ?>
                                <?php $idx = 0;
                                foreach ($workTypeCols as $wt):
                                    $idx++;
                                    $val = $summary[$pId][$tId][$date][$wt['name']] ?? 0;
                                    if (!isset($dailyDetailedTotals[$date][$wt['name']]))
                                        $dailyDetailedTotals[$date][$wt['name']] = 0;
                                    $dailyDetailedTotals[$date][$wt['name']] += $val;
                                    $dailyTotals[$date] += $val; ?>
                                    <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= $val ? 'fw-bold' : 'text-muted' ?> <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount && !in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                        data-date="<?= $date ?>" data-wt-code="<?= $wt['code'] ?>">
                                        <?= $val ?: '' ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if (in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])):
                                    $daySum = 0;
                                    foreach ($workTypeCols as $wt) {
                                        $daySum += $summary[$pId][$tId][$date][$wt['name']] ?? 0;
                                    }
                                    ?>
                                    <td class="day-total-col table-light fw-bold day-separator <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                        data-date="<?= $date ?>" style="display: none;">
                                        <?= $daySum ?: '' ?>
                                    </td>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
                                <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info fw-bold row-action-total"
                                    data-wt-code="<?= $wt['code'] ?>">
                                    <?= $personelActTotals[$wt['name']] ?: '' ?>
                                </td>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <td class="table-light fw-bold row-total-cell"><?= $personelTotal ?: '' ?></td>
                        <?php if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])): ?>
                            <?php
                            $dusum = $manuelDusumMap[$pId][$tId] ?? 0;
                            ?>
                            <td class="table-danger" style="width: 80px;">
                                <input type="number" class="form-control form-control-sm text-center fw-bold manual-dusum-input"
                                    data-pid="<?= $pId ?>" data-tid="<?= $tId ?>" value="<?= $dusum ?: '' ?>" min="0"
                                    style="width: 70px; display:inline-block; padding: 2px;">
                            </td>
                            <td class="table-success fw-bold kalan-toplam-cell">
                                <?= $personelTotal - $dusum ?>
                            </td>
                        <?php endif; ?>
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
                    <?php
                    $dateIdx = 0;
                    foreach ($reportDates as $date):
                        $dateIdx++;
                        $isSunday = in_array($date, $sundayDates); ?>
                        <?php $idx = 0;
                        foreach ($workTypeCols as $wt):
                            $idx++; ?>
                            <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= ($idx === $subColCount && !in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])) ? 'day-separator' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                data-date="<?= $date ?>" data-wt-code="<?= $wt['code'] ?>">
                                <?= $dailyDetailedTotals[$date][$wt['name']] ?? '' ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if (in_array($activeTab, ['kesme', 'sokme_takma', 'muhurleme'])):
                            $dayTotalAll = $dailyTotals[$date] ?? 0;
                            ?>
                            <td class="day-total-col table-light fw-bold day-separator <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                                data-date="<?= $date ?>" style="display: none;">
                                <?= $dayTotalAll ?: '' ?>
                            </td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php $idx = 0;
                    $allActionTypesGrandTotal = 0;
                    foreach ($workTypeCols as $wt):
                        $idx++;
                        $footActTotal = 0;
                        foreach ($reportDates as $date) {
                            $footActTotal += $dailyDetailedTotals[$date][$wt['name']] ?? 0;
                        }
                        $allActionTypesGrandTotal += $footActTotal;
                        ?>
                        <td class="wt-cell-sub wt-code-<?= $wt['code'] ?> table-info action-grand-total-cell <?= ($idx === $subColCount) ? 'day-separator' : '' ?>"
                            data-wt-code="<?= $wt['code'] ?>" data-day="genel-total"><?= $footActTotal ?: '' ?></td>
                    <?php endforeach; ?>
                    <td class="table-warning fw-bold action-types-grand-total"><?= $allActionTypesGrandTotal ?: '' ?>
                    </td>
                    <?php if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])): ?>
                        <td colspan="2"></td>
                    <?php endif; ?>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
            <tr class="tfoot-general">
                <td colspan="<?= ($activeTab === 'kacakkontrol') ? '2' : '3' ?>" class="text-end sticky-col-1"
                    style="left: 0; z-index: 166; width: 390px; min-width: 390px; max-width: 390px;">
                    GÜNLÜK TOPLAMLAR</td>
                <?php
                $dateIdx = 0;
                foreach ($reportDates as $date):
                    $dateIdx++;
                    $isSunday = in_array($date, $sundayDates); ?>
                    <td colspan="<?= $subColCount ?>"
                        class="day-num-header-footer day-separator daily-total-cell <?= ($dateIdx % 2 == 0) ? 'day-bg-alt' : '' ?> <?= $isSunday ? 'sunday-cell' : '' ?>"
                        data-date="<?= $date ?>" data-base-colspan="<?= $subColCount ?>"><?= $dailyTotals[$date] ?: '' ?>
                    </td>
                <?php endforeach; ?>
                <?php if ($hasSubCols): ?>
                    <td colspan="<?= $subColCount ?>"
                        class="action-totals-day-header-footer day-separator action-grand-total-consolidated"
                        data-day="genel-total">
                        <?= $grandTotal ?: '' ?>
                    </td>
                <?php endif; ?>
                <td class="grand-total-cell"><?= number_format($grandTotal, 0, '', '') ?></td>
                <?php if (in_array($activeTab, ['kesme', 'okuma', 'sokme_takma'])): ?>
                    <?php 
                    $grandDusumVal = 0;
                    if (isset($manuelDusumMap)) {
                        foreach ($manuelDusumMap as $pId => $teams) {
                            foreach ($teams as $tId => $val) {
                                $grandDusumVal += $val;
                            }
                        }
                    }
                    ?>
                    <td class="grand-dusum-cell table-danger"><?= $grandDusumVal ?: '0' ?></td>
                    <td class="grand-kalan-cell table-success"><?= ($grandTotal - $grandDusumVal) ?: '0' ?></td>
                <?php endif; ?>
                <td class="grand-region-total-cell table-light fw-bold"><?= number_format($grandTotal, 0, '', '') ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
    function refreshLayoutAndTotals() {
        const table = document.getElementById('raporTable');
        if (!table) return;
        const isSummaryMode = table.classList.contains('summary-mode');
        const activeFilters = document.querySelectorAll('#workTypeLegend .legend-item.active-filter');
        const defaultSubColCount = <?= $subColCount ?>;
        const wtSubCells = table.querySelectorAll('.wt-cell-sub');

        if (activeFilters.length === 0) {
            for (let i = 0; i < wtSubCells.length; i++) wtSubCells[i].classList.remove('legend-hidden');
            document.querySelectorAll('.day-num-header, .daily-total-cell').forEach(h => {
                h.style.display = '';
                h.setAttribute('colspan', isSummaryMode ? 1 : defaultSubColCount);
            });
            const totalCols = <?= count($reportDates) * $subColCount ?>;
            const mainGunlerHeader = document.getElementById('mainGunlerHeader');
            if (mainGunlerHeader) mainGunlerHeader.setAttribute('colspan', totalCols);
            const actionTotalsHeader = document.getElementById('actionTotalsHeader');
            if (actionTotalsHeader) {
                actionTotalsHeader.style.display = isSummaryMode ? 'none' : '';
                actionTotalsHeader.setAttribute('colspan', defaultSubColCount);
            }
            document.querySelectorAll('.action-grand-total-consolidated').forEach(c => {
                c.style.display = isSummaryMode ? 'none' : '';
                c.setAttribute('colspan', defaultSubColCount);
            });
        } else {
            const activeCodes = Array.from(activeFilters).map(f => f.dataset.wtCode);
            for (let i = 0; i < wtSubCells.length; i++) {
                if (activeCodes.indexOf(wtSubCells[i].dataset.wtCode) > -1) wtSubCells[i].classList.remove('legend-hidden');
                else wtSubCells[i].classList.add('legend-hidden');
            }

            const row2SubHeaders = table.querySelectorAll('thead tr:nth-child(2) th.wt-cell-sub');
            document.querySelectorAll('.day-num-header[data-date]').forEach(h => {
                const date = h.dataset.date;
                let visCount = 0;
                for (let i = 0; i < row2SubHeaders.length; i++) {
                    if (row2SubHeaders[i].dataset.date === date && !row2SubHeaders[i].classList.contains('legend-hidden')) visCount++;
                }
                const fCell = table.querySelector(`.daily-total-cell[data-date="${date}"]`);
                if (visCount > 0) {
                    h.style.display = '';
                    h.setAttribute('colspan', isSummaryMode ? 1 : visCount);
                    if (fCell) { fCell.style.display = ''; fCell.setAttribute('colspan', isSummaryMode ? 1 : visCount); }
                } else {
                    h.style.display = 'none';
                    if (fCell) fCell.style.display = 'none';
                }
            });

            const actH = document.getElementById('actionTotalsHeader');
            const actC = document.querySelectorAll('.action-grand-total-consolidated');
            let genVis = 0;
            for (let i = 0; i < row2SubHeaders.length; i++) {
                if (row2SubHeaders[i].dataset.day === 'genel-total' && !row2SubHeaders[i].classList.contains('legend-hidden')) genVis++;
            }
            if (genVis > 0) {
                if (actH) { actH.style.display = ''; actH.setAttribute('colspan', genVis); }
                actC.forEach(c => { c.style.display = ''; c.setAttribute('colspan', genVis); });
            } else {
                if (actH) actH.style.display = 'none';
                actC.forEach(c => c.style.display = 'none');
            }

            let mVis = 0;
            for (let i = 0; i < row2SubHeaders.length; i++) {
                if (row2SubHeaders[i].dataset.date && !row2SubHeaders[i].classList.contains('legend-hidden')) mVis++;
            }
            const mainGunlerHeader2 = document.getElementById('mainGunlerHeader');
            if (mainGunlerHeader2) mainGunlerHeader2.setAttribute('colspan', mVis || 1);
        }

        table.querySelectorAll('.day-separator').forEach(s => s.classList.remove('day-separator'));
        const dates = <?= json_encode($reportDates) ?>;
        dates.forEach(date => {
            if (isSummaryMode) {
                table.querySelectorAll(`[data-date="${date}"].day-total-col`).forEach(c => c.classList.add('day-separator'));
                table.querySelectorAll(`.day-num-header[data-date="${date}"]`).forEach(c => c.classList.add('day-separator'));
            } else {
                const daySubs = table.querySelectorAll(`thead tr:nth-child(2) [data-date="${date}"]:not(.legend-hidden)`);
                if (daySubs.length) daySubs[daySubs.length - 1].classList.add('day-separator');
                table.querySelectorAll(`.day-num-header[data-date="${date}"], .daily-total-cell[data-date="${date}"]`).forEach(c => c.classList.add('day-separator'));
            }
        });
        updateDynamicTotals();
    }

    $(document).off('click', '#workTypeLegend .legend-item[data-wt-code]').on('click', '#workTypeLegend .legend-item[data-wt-code]', function () {
        $(this).toggleClass('active-filter');
        refreshLayoutAndTotals();
    });

    $(document).off('click', '#btnExportUnmatched').on('click', '#btnExportUnmatched', function () {
        const year = '<?= $year ?>';
        const month = '<?= $month ?>';
        // Tüm eşleşmeyenleri indirmek için tab=all gönderiyoruz
        const url = `views/puantaj/rapor-excel.php?tab=all&year=${year}&month=${month}&unmatched=1`;
        window.location.href = url;
    });

    $(document).off('change', '.manual-dusum-input').on('change', '.manual-dusum-input', function () {
        const $input = $(this);
        const pId = $input.data('pid');
        const tId = $input.data('tid');
        const dusumValue = parseInt($input.val()) || 0;

        updateDynamicTotals();

        $.ajax({
            url: 'views/puantaj/api.php',
            type: 'POST',
            data: {
                action: 'save-manuel-dusum',
                personel_id: pId,
                ekip_kodu_id: tId,
                dusum_value: dusumValue,
                year: '<?= $year ?>',
                month: '<?= $month ?>'
            },
            success: function (res) { }
        });
    });

    function updateDynamicTotals() {
        const table = document.getElementById('raporTable');
        if (!table) return;
        const reportDates = <?= json_encode($reportDates) ?>;
        const workTypeLegendItems = document.querySelectorAll('#workTypeLegend .legend-item');
        const workTypeTotals = {};
        const dateTypeTotals = {};
        const dailyGrandTotals = {};
        let overallGrandTotal = 0;
        let overallGrandDusum = 0;
        let overallGrandKalan = 0;
        const hasSubCols = <?= $hasSubCols ? 'true' : 'false' ?>;
        const activeTab = '<?= $activeTab ?>';

        workTypeLegendItems.forEach(item => {
            workTypeTotals[item.dataset.wtCode] = 0;
        });

        reportDates.forEach(date => {
            dailyGrandTotals[date] = 0;
            dateTypeTotals[date] = {};
            for (let code in workTypeTotals) dateTypeTotals[date][code] = 0;
        });

        // Use native tr array for maximum speed
        const rows = table.tBodies[0].rows;
        for (let r = 0; r < rows.length; r++) {
            const row = rows[r];
            if (row.style.display === 'none') continue;

            let rowTotal = 0;
            const rowActTotals = {};
            const rowDayTotals = {};
            for (let code in workTypeTotals) rowActTotals[code] = 0;

            if (hasSubCols) {
                const subCells = row.querySelectorAll('.wt-cell-sub[data-date]');
                for (let c = 0; c < subCells.length; c++) {
                    const cell = subCells[c];
                    const date = cell.dataset.date;
                    const code = cell.dataset.wtCode;

                    if (typeof rowActTotals[code] === 'undefined') rowActTotals[code] = 0;

                    const val = parseInt(cell.textContent) || 0;

                    rowActTotals[code] += val;
                    rowDayTotals[date] = (rowDayTotals[date] || 0) + val;

                    if (!cell.classList.contains('legend-hidden')) {
                        workTypeTotals[code] += val;
                        dateTypeTotals[date][code] += val;
                        dailyGrandTotals[date] += val;
                        rowTotal += val;
                    }
                }

                row.querySelectorAll('.day-total-col[data-date]').forEach(col => {
                    col.textContent = rowDayTotals[col.dataset.date] || '';
                });

                row.querySelectorAll('.row-action-total').forEach(col => {
                    const wtCode = col.dataset.wtCode;
                    col.textContent = (rowActTotals[wtCode] !== undefined && rowActTotals[wtCode] !== 0) ? rowActTotals[wtCode] : '';
                });
            } else {
                const dayCells = row.querySelectorAll('[data-date]');
                for (let c = 0; c < dayCells.length; c++) {
                    const cell = dayCells[c];
                    const val = parseInt(cell.textContent) || 0;
                    if (!cell.classList.contains('legend-hidden') && cell.style.display !== 'none') {
                        dailyGrandTotals[cell.dataset.date] += val;
                        rowTotal += val;
                    }
                }
            }

            const totalCell = row.querySelector('.row-total-cell');
            if (totalCell) totalCell.textContent = rowTotal || '';
            overallGrandTotal += rowTotal;

            if (['kesme', 'okuma', 'sokme_takma'].includes(activeTab)) {
                // Manuel düşüm ve Kalan sütunları için toplam hesaplama
                const dusumInput = row.querySelector('.manual-dusum-input');
                let dusumVal = 0;
                if (dusumInput) {
                    dusumVal = parseInt(dusumInput.value) || 0;
                    overallGrandDusum += dusumVal;

                    const kalanCell = row.querySelector('.kalan-toplam-cell');
                    if (kalanCell) {
                        kalanCell.textContent = (rowTotal - dusumVal) || '0';
                    }
                } else {
                    // Input yoksa (bazı satır tipleri için), kalan hücresindeki değeri almayı dene veya rowTotal kullan
                    const kalanCell = row.querySelector('.kalan-toplam-cell');
                    if (kalanCell) {
                        dusumVal = 0; // Manuel düşüm yoksa 0'dır
                        kalanCell.textContent = rowTotal || '0';
                    }
                }
                overallGrandKalan += (rowTotal - dusumVal);
            }
        }

        // Global updates
        workTypeLegendItems.forEach(item => {
            const badge = item.querySelector('.badge');
            if (badge) badge.textContent = workTypeTotals[item.dataset.wtCode];
        });

        if (hasSubCols) {
            let actionGrandSum = 0;
            const footAction = table.querySelector('tfoot .tfoot-action');
            if (footAction) {
                reportDates.forEach(date => {
                    const codes = dateTypeTotals[date];
                    for (let code in codes) {
                        const cell = footAction.querySelector(`.wt-cell-sub[data-date="${date}"][data-wt-code="${code}"]`);
                        if (cell) cell.textContent = codes[code] || '';
                    }
                    const dayTotCell = footAction.querySelector(`.day-total-col[data-date="${date}"]`);
                    if (dayTotCell) dayTotCell.textContent = dailyGrandTotals[date] || '';
                });

                for (let code in workTypeTotals) {
                    const cell = footAction.querySelector(`.action-grand-total-cell[data-wt-code="${code}"]`);
                    if (cell) cell.textContent = workTypeTotals[code] || '';
                    actionGrandSum += workTypeTotals[code];
                }
            }
            const actGrandTotalCell = document.querySelector('.action-types-grand-total');
            if (actGrandTotalCell) actGrandTotalCell.textContent = actionGrandSum || '';
            const actConsolidated = document.querySelector('.action-grand-total-consolidated');
            if (actConsolidated) actConsolidated.textContent = overallGrandTotal || '';
        }

        reportDates.forEach(date => {
            const cell = document.querySelector(`.tfoot-general .daily-total-cell[data-date="${date}"]`);
            if (cell) cell.textContent = dailyGrandTotals[date] || '';
        });

        const grandCell = table.querySelector('.grand-total-cell');
        if (grandCell) grandCell.textContent = overallGrandTotal || '';

        const grandRegionCell = table.querySelector('.grand-region-total-cell');
        if (grandRegionCell) grandRegionCell.textContent = overallGrandTotal || '';

        if (['kesme', 'okuma', 'sokme_takma'].includes(activeTab)) {
            const gdCell = table.querySelector('.grand-dusum-cell');
            if (gdCell) gdCell.textContent = overallGrandDusum || '0';
            const gkCell = table.querySelector('.grand-kalan-cell');
            if (gkCell) gkCell.textContent = overallGrandKalan || '0';
        }

        document.querySelectorAll('.region-total-cell').forEach(cell => {
            const rid = cell.dataset.regionId;
            let rSum = 0;
            table.querySelectorAll(`tbody tr[data-region-id="${rid}"]`).forEach(tr => {
                if (tr.style.display !== 'none') {
                    const rtc = tr.querySelector('.row-total-cell');
                    rSum += parseInt(rtc ? rtc.textContent : 0) || 0;
                }
            });
            cell.textContent = rSum || '';
        });
    }

    $(document).off('click', '#btnToggleDailyTotals').on('click', '#btnToggleDailyTotals', function () {
        const $btn = $(this);
        const activeTab = '<?= $activeTab ?>';
        const $table = $('#raporTable');
        const isTurningOn = !$btn.hasClass('active');

        if (isTurningOn) {
            $btn.addClass('active btn-info').removeClass('btn-outline-info').html('<i class="bx bx-hide me-1"></i> Günlük Topl. Gizle');
            $table.addClass('summary-mode');
            localStorage.setItem('show_daily_totals_' + activeTab, '1');
        } else {
            $btn.removeClass('active btn-info').addClass('btn-outline-info').html('<i class="bx bx-show me-1"></i> Günlük Topl. Göster');
            $table.removeClass('summary-mode');
            localStorage.setItem('show_daily_totals_' + activeTab, '0');
        }

        refreshLayoutAndTotals();
    });

    // Restore state on load
    $(function () {
        const activeTab = '<?= $activeTab ?>';
        if (localStorage.getItem('show_daily_totals_' + activeTab) === '1') {
            $('#btnToggleDailyTotals').trigger('click');
        }
    });
</script>