<?php
/**
 * Karşılaştırma Raporu - Çoklu Dönem Karşılaştırma
 * 
 * GET parameters:
 * - compare_tab: okuma, kesme, sokme_takma, muhurleme, kacakkontrol
 * - compare_mode: personel, bolge, firma
 * - periods[]: Array of "YYYY-MM" strings
 */

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

$compareTab = $_GET['compare_tab'] ?? 'okuma';
$compareMode = $_GET['compare_mode'] ?? 'personel';
$periodsRaw = $_GET['periods'] ?? [];

if (empty($periodsRaw) || !is_array($periodsRaw)) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2 m-3">
        <i class="bx bx-info-circle fs-4"></i>
        <div>Karşılaştırma yapmak için en az <strong>2 dönem</strong> seçmelisiniz.</div>
    </div>';
    return;
}

// Türkçe ay isimleri
$monthNames = [
    '01' => 'Ocak',
    '02' => 'Şubat',
    '03' => 'Mart',
    '04' => 'Nisan',
    '05' => 'Mayıs',
    '06' => 'Haziran',
    '07' => 'Temmuz',
    '08' => 'Ağustos',
    '09' => 'Eylül',
    '10' => 'Ekim',
    '11' => 'Kasım',
    '12' => 'Aralık'
];

// Build periods array
$periods = [];
foreach ($periodsRaw as $p) {
    $parts = explode('-', $p); // YYYY-MM
    if (count($parts) !== 2)
        continue;
    $year = $parts[0];
    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
    $startDate = "$year-$month-01";
    $endDate = date('Y-m-t', strtotime($startDate));
    $label = ($monthNames[$month] ?? $month) . ' ' . $year;
    $periods[] = [
        'start' => $startDate,
        'end' => $endDate,
        'label' => $label,
        'key' => $p
    ];
}

if (count($periods) < 2) {
    echo '<div class="alert alert-warning d-flex align-items-center gap-2 m-3">
        <i class="bx bx-info-circle fs-4"></i>
        <div>Karşılaştırma yapmak için en az <strong>2 dönem</strong> seçmelisiniz.</div>
    </div>';
    return;
}

// Fetch data based on tab
if ($compareTab === 'okuma') {
    $data = $EndeksOkuma->getComparisonByPeriods($periods);
} elseif ($compareTab === 'kacakkontrol') {
    $data = $Puantaj->getKacakComparisonByPeriods($periods);
} else {
    $data = $Puantaj->getComparisonByPeriods($periods, $compareTab);
}

$periodLabels = array_column($periods, 'label');

$tabNames = [
    'okuma' => 'Endeks Okuma',
    'kesme' => 'Kesme/Açma',
    'sokme_takma' => 'Sayaç Sökme Takma',
    'muhurleme' => 'Mühürleme',
    'kacakkontrol' => 'Kaçak Kontrol'
];
$currentTabName = $tabNames[$compareTab] ?? 'Rapor';

$valueLabel = ($compareTab === 'okuma') ? 'Okunan Abone' : (($compareTab === 'kacakkontrol') ? 'Kaçak Sayısı' : 'Sonuçlanan');

// Calculate trend
function calcChange($current, $previous)
{
    if ($previous == 0)
        return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

function trendBadge($change)
{
    if ($change > 0) {
        return '<span class="badge bg-success-subtle text-success"><i class="bx bx-trending-up me-1"></i>+' . $change . '%</span>';
    } elseif ($change < 0) {
        return '<span class="badge bg-danger-subtle text-danger"><i class="bx bx-trending-down me-1"></i>' . $change . '%</span>';
    }
    return '<span class="badge bg-secondary-subtle text-secondary"><i class="bx bx-minus me-1"></i>0%</span>';
}

// Color palette for charts
$chartColors = [
    'rgba(81, 86, 190, 0.85)',
    'rgba(52, 195, 143, 0.85)',
    'rgba(244, 106, 106, 0.85)',
    'rgba(241, 180, 76, 0.85)',
    'rgba(80, 165, 241, 0.85)',
    'rgba(166, 104, 217, 0.85)',
    'rgba(255, 135, 100, 0.85)',
    'rgba(39, 174, 96, 0.85)',
    'rgba(255, 99, 132, 0.85)',
    'rgba(54, 162, 235, 0.85)',
    'rgba(255, 206, 86, 0.85)',
    'rgba(75, 192, 192, 0.85)',
];
$chartBorderColors = array_map(function ($c) {
    return str_replace('0.85', '1', $c);
}, $chartColors);
?>

<style>
    .compare-summary-card {
        border-radius: 10px;
        padding: 16px 20px;
        border: 1px solid var(--bs-border-color, #e9ecef);
        background: linear-gradient(135deg, var(--bs-card-bg, #fff) 0%, rgba(81, 86, 190, 0.03) 100%);
        transition: all 0.3s ease;
    }

    .compare-summary-card:hover {
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .compare-summary-card .card-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .compare-summary-card .card-value {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
    }

    .compare-summary-card .card-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.7;
    }

    #compareTable {
        font-size: 12.5px;
        border-collapse: separate !important;
        border-spacing: 0 !important;
    }

    #compareTable th {
        background-color: var(--bs-card-bg, #f8f9fa) !important;
        font-weight: 600;
        font-size: 11px;
        position: sticky;
        top: 0;
        z-index: 10;
        border: 1px solid var(--bs-border-color, #e9ecef) !important;
        padding: 10px 12px !important;
        white-space: nowrap;
    }

    #compareTable td {
        padding: 8px 12px !important;
        border: 1px solid var(--bs-border-color, #eee) !important;
        vertical-align: middle !important;
    }

    #compareTable tbody tr {
        transition: background-color 0.15s ease;
    }

    #compareTable tbody tr:hover {
        background-color: rgba(81, 86, 190, 0.04) !important;
    }

    #compareTable .period-col {
        text-align: right !important;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    #compareTable .trend-col {
        text-align: center !important;
    }

    #compareTable tfoot td {
        background-color: var(--bs-card-bg, #f8f9fa) !important;
        font-weight: 700 !important;
        border-top: 2px solid var(--bs-border-color, #dee2e6) !important;
        position: sticky;
        bottom: 0;
        z-index: 9;
        box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.06);
    }

    .compare-mode-btn {
        padding: 6px 16px;
        border-radius: 8px;
        border: 1.5px solid var(--bs-border-color, #e2e5e9);
        background: var(--bs-card-bg, #fff);
        color: var(--bs-body-color, #495057);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .compare-mode-btn:hover {
        border-color: #5156be;
        color: #5156be;
    }

    .compare-mode-btn.active {
        background: #5156be;
        border-color: #5156be;
        color: #fff;
        box-shadow: 0 2px 8px rgba(81, 86, 190, 0.3);
    }

    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .highlight-best {
        background-color: rgba(52, 195, 143, 0.08) !important;
    }

    .highlight-worst {
        background-color: rgba(244, 106, 106, 0.06) !important;
    }

    /* Accordion badge visibility */
    .accordion-button:not(.collapsed)~.compare-summary-badges {
        display: none !important;
    }

    .accordion-button.collapsed~.compare-summary-badges {
        display: flex !important;
    }
</style>

<?php
// ===================== SUMMARY CARDS =====================
$firmaData = $data['firma'];
$lastPeriod = end($periodLabels);
$firstPeriod = reset($periodLabels);
$totalChange = calcChange($firmaData[$lastPeriod]['toplam'] ?? 0, $firmaData[$firstPeriod]['toplam'] ?? 0);

// Find best/worst period
$bestPeriod = $firstPeriod;
$worstPeriod = $firstPeriod;
$bestVal = 0;
$worstVal = PHP_INT_MAX;
$grandTotal = 0;
foreach ($firmaData as $label => $fd) {
    $val = $fd['toplam'] ?? 0;
    $grandTotal += $val;
    if ($val > $bestVal) {
        $bestVal = $val;
        $bestPeriod = $label;
    }
    if ($val < $worstVal) {
        $worstVal = $val;
        $worstPeriod = $label;
    }
}
$avgPerPeriod = count($firmaData) > 0 ? round($grandTotal / count($firmaData)) : 0;

$modeNames = ['personel' => 'Personel Bazlı', 'bolge' => 'Bölge Bazlı', 'firma' => 'Firma Toplam'];
$currentModeName = $modeNames[$compareMode] ?? 'Personel Bazlı';
$changeBadgeClass = $totalChange > 0 ? 'bg-success-subtle text-success' : ($totalChange < 0 ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary');
$changeIcon = $totalChange > 0 ? 'bx-trending-up' : ($totalChange < 0 ? 'bx-trending-down' : 'bx-minus');
?>

<!-- Karşılaştırma Detay Accordion -->
<div class="card mb-3">
    <div class="card-body p-2">
        <div class="accordion" id="compareDetailAccordion">
            <div class="accordion-item border-0">
                <h2 class="accordion-header position-relative" id="compareDetailHeading">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse"
                        data-bs-target="#compareDetailCollapse" aria-expanded="false"
                        aria-controls="compareDetailCollapse">
                        <i class="bx bx-bar-chart-alt-2 me-2 text-primary"></i> Özet & Görünüm Ayarları
                    </button>

                    <!-- Collapsed state badges (sağda) -->
                    <div class="compare-summary-badges d-none d-md-flex gap-2 position-absolute"
                        style="right: 60px; top: 50%; transform: translateY(-50%); z-index: 5;">
                        <div class="filter-summary-badge">
                            <span class="badge-label">Görünüm:</span>
                            <span class="badge-value"><?= $currentModeName ?></span>
                        </div>
                        <div class="filter-summary-badge">
                            <span class="badge-label">Değişim:</span>
                            <span class="badge-value"><i
                                    class="bx <?= $changeIcon ?> me-1"></i><?= ($totalChange > 0 ? '+' : '') . $totalChange ?>%</span>
                        </div>
                        <div class="filter-summary-badge">
                            <span class="badge-label">Toplam:</span>
                            <span class="badge-value"><?= number_format($grandTotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="filter-summary-badge">
                            <span class="badge-label">Ort:</span>
                            <span class="badge-value"><?= number_format($avgPerPeriod, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </h2>

                <div id="compareDetailCollapse" class="accordion-collapse collapse"
                    aria-labelledby="compareDetailHeading" data-bs-parent="#compareDetailAccordion">
                    <div class="accordion-body pt-3 pb-2">
                        <!-- Mode Switcher -->
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted fw-semibold" style="font-size: 12px;"><i
                                        class="bx bx-bar-chart-alt-2 me-1"></i>Görünüm:</span>
                                <button class="compare-mode-btn <?= $compareMode === 'personel' ? 'active' : '' ?>"
                                    onclick="switchCompareMode('personel')">
                                    <i class="bx bx-user"></i> Personel Bazlı
                                </button>
                                <button class="compare-mode-btn <?= $compareMode === 'bolge' ? 'active' : '' ?>"
                                    onclick="switchCompareMode('bolge')">
                                    <i class="bx bx-map"></i> Bölge Bazlı
                                </button>
                                <button class="compare-mode-btn <?= $compareMode === 'firma' ? 'active' : '' ?>"
                                    onclick="switchCompareMode('firma')">
                                    <i class="bx bx-buildings"></i> Firma Toplam
                                </button>
                            </div>
                            <!-- Chart + Table Toggle -->
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" id="btnShowTable"
                                    onclick="toggleView('table')">
                                    <i class="bx bx-table me-1"></i>Tablo
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btnShowChart"
                                    onclick="toggleView('chart')">
                                    <i class="bx bx-bar-chart me-1"></i>Grafik
                                </button>
                                <button type="button" class="btn btn-outline-primary" id="btnShowBoth"
                                    onclick="toggleView('both')">
                                    <i class="bx bx-columns me-1"></i>İkisi
                                </button>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="compare-summary-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="card-icon bg-primary-subtle text-primary"><i
                                                class="bx bx-line-chart"></i></div>
                                        <div>
                                            <div class="card-label text-muted">Toplam Değişim</div>
                                            <div class="card-value"><?= trendBadge($totalChange) ?></div>
                                            <div class="text-muted" style="font-size: 10px;"><?= $firstPeriod ?> →
                                                <?= $lastPeriod ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="compare-summary-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="card-icon bg-success-subtle text-success"><i
                                                class="bx bx-trophy"></i></div>
                                        <div>
                                            <div class="card-label text-muted">En İyi Dönem</div>
                                            <div class="card-value text-success">
                                                <?= number_format($bestVal, 0, ',', '.') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 10px;"><?= $bestPeriod ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="compare-summary-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="card-icon bg-warning-subtle text-warning"><i
                                                class="bx bx-calculator"></i></div>
                                        <div>
                                            <div class="card-label text-muted">Dönem Ortalaması</div>
                                            <div class="card-value"><?= number_format($avgPerPeriod, 0, ',', '.') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 10px;"><?= count($periods) ?>
                                                dönem</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="compare-summary-card">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="card-icon bg-info-subtle text-info"><i
                                                class="bx bx-bar-chart-square"></i></div>
                                        <div>
                                            <div class="card-label text-muted">Genel Toplam</div>
                                            <div class="card-value"><?= number_format($grandTotal, 0, ',', '.') ?></div>
                                            <div class="text-muted" style="font-size: 10px;">Tüm dönemler</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row" id="compareContentRow">
    <!-- TABLE -->
    <div class="col-12" id="tableContainer">
        <div class="table-responsive" style="max-height: calc(100vh - 300px); overflow: auto;">
            <table class="table table-bordered table-sm mb-0" id="compareTable">
                <thead>
                    <tr>
                        <th style="min-width: 50px;">#</th>
                        <?php if ($compareMode === 'personel'): ?>
                            <th style="min-width: 180px;">Personel</th>
                            <th style="min-width: 120px;">Ekip</th>
                            <th style="min-width: 100px;">Bölge</th>
                        <?php elseif ($compareMode === 'bolge'): ?>
                            <th style="min-width: 180px;">Bölge</th>
                        <?php else: ?>
                            <th style="min-width: 180px;">Metrik</th>
                        <?php endif; ?>

                        <?php foreach ($periodLabels as $label): ?>
                            <th class="period-col" style="min-width: 110px;">
                                <?= $label ?>
                            </th>
                        <?php endforeach; ?>

                        <th class="period-col" style="min-width: 90px;">Ortalama</th>
                        <th class="trend-col" style="min-width: 90px;">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sira = 0;

                    if ($compareMode === 'personel'):
                        // Sort by total descending
                        $personelData = $data['personel'];
                        uasort($personelData, function ($a, $b) use ($periodLabels) {
                            $totalA = array_sum(array_column($a['periods'], 'toplam'));
                            $totalB = array_sum(array_column($b['periods'], 'toplam'));
                            return $totalB - $totalA;
                        });

                        $columnTotals = array_fill_keys($periodLabels, 0);

                        foreach ($personelData as $key => $pData):
                            $sira++;
                            $values = [];
                            foreach ($periodLabels as $label) {
                                $v = $pData['periods'][$label]['toplam'] ?? 0;
                                $values[] = $v;
                                $columnTotals[$label] += $v;
                            }
                            $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
                            $firstVal = $values[0] ?? 0;
                            $lastVal = end($values);
                            $change = calcChange($lastVal, $firstVal);

                            // Find max/min in this row
                            $maxVal = max($values);
                            $minVal = min($values);
                            ?>
                            <tr>
                                <td class="text-center text-muted">
                                    <?= $sira ?>
                                </td>
                                <td class="fw-semibold">
                                    <?php
                                    if ($pData['personel_adi'] === '-' || strpos($pData['personel_adi'], 'Eşleşmeyen Ekip') !== false) {
                                        echo '<span class="text-danger fw-bold"><i class="bx bx-error-circle"></i> Eşleşmeyen Ekip: ' . htmlspecialchars($pData['ekip_adi']) . '</span>';
                                    } else {
                                        echo htmlspecialchars($pData['personel_adi']);
                                    }
                                    ?>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($pData['ekip_adi']) ?>
                                    </span></td>
                                <td class="text-muted">
                                    <?= htmlspecialchars($pData['bolge']) ?>
                                </td>
                                <?php foreach ($values as $i => $v):
                                    $cellClass = '';
                                    if (count(array_unique($values)) > 1) {
                                        if ($v === $maxVal)
                                            $cellClass = 'highlight-best';
                                        elseif ($v === $minVal && $v > 0)
                                            $cellClass = 'highlight-worst';
                                    }
                                    ?>
                                    <td class="period-col <?= $cellClass ?>">
                                        <?= number_format($v, 0, ',', '.') ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="period-col fw-bold">
                                    <?= number_format($avg, 0, ',', '.') ?>
                                </td>
                                <td class="trend-col">
                                    <?= trendBadge($change) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td class="fw-bold">TOPLAM</td>
                            <td></td>
                            <td></td>
                            <?php
                            $footerValues = [];
                            foreach ($periodLabels as $label):
                                $v = $columnTotals[$label] ?? 0;
                                $footerValues[] = $v;
                                ?>
                                <td class="period-col">
                                    <?= number_format($v, 0, ',', '.') ?>
                                </td>
                            <?php endforeach; ?>
                            <?php $footerAvg = count($footerValues) > 0 ? round(array_sum($footerValues) / count($footerValues)) : 0; ?>
                            <td class="period-col">
                                <?= number_format($footerAvg, 0, ',', '.') ?>
                            </td>
                            <td class="trend-col">
                                <?= trendBadge(calcChange(end($footerValues), reset($footerValues))) ?>
                            </td>
                        </tr>
                    </tfoot>

                <?php elseif ($compareMode === 'bolge'):
                        $bolgeData = $data['bolge'];
                        ksort($bolgeData);
                        $columnTotals = array_fill_keys($periodLabels, 0);

                        foreach ($bolgeData as $bolgeName => $bData):
                            $sira++;
                            $values = [];
                            foreach ($periodLabels as $label) {
                                $v = $bData['periods'][$label]['toplam'] ?? 0;
                                $values[] = $v;
                                $columnTotals[$label] += $v;
                            }
                            $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
                            $firstVal = $values[0] ?? 0;
                            $lastVal = end($values);
                            $change = calcChange($lastVal, $firstVal);
                            $maxVal = max($values);
                            $minVal = min($values);
                            ?>
                        <tr>
                            <td class="text-center text-muted">
                                <?= $sira ?>
                            </td>
                            <td class="fw-semibold">
                                <?= htmlspecialchars($bolgeName) ?>
                            </td>
                            <?php foreach ($values as $v):
                                $cellClass = '';
                                if (count(array_unique($values)) > 1) {
                                    if ($v === $maxVal)
                                        $cellClass = 'highlight-best';
                                    elseif ($v === $minVal && $v > 0)
                                        $cellClass = 'highlight-worst';
                                }
                                ?>
                                <td class="period-col <?= $cellClass ?>">
                                    <?= number_format($v, 0, ',', '.') ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="period-col fw-bold">
                                <?= number_format($avg, 0, ',', '.') ?>
                            </td>
                            <td class="trend-col">
                                <?= trendBadge($change) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td></td>
                            <td class="fw-bold">TOPLAM</td>
                            <?php
                            $footerValues = [];
                            foreach ($periodLabels as $label):
                                $v = $columnTotals[$label] ?? 0;
                                $footerValues[] = $v;
                                ?>
                                <td class="period-col">
                                    <?= number_format($v, 0, ',', '.') ?>
                                </td>
                            <?php endforeach; ?>
                            <?php $footerAvg = count($footerValues) > 0 ? round(array_sum($footerValues) / count($footerValues)) : 0; ?>
                            <td class="period-col">
                                <?= number_format($footerAvg, 0, ',', '.') ?>
                            </td>
                            <td class="trend-col">
                                <?= trendBadge(calcChange(end($footerValues), reset($footerValues))) ?>
                            </td>
                        </tr>
                    </tfoot>

                <?php else: // firma mode
                        // Show different metrics as rows
                        $metrics = [
                            ['label' => 'Toplam ' . $valueLabel, 'key' => 'toplam', 'icon' => 'bx-bar-chart'],
                            ['label' => 'Personel Sayısı', 'key' => 'personel_sayisi', 'icon' => 'bx-group'],
                        ];

                        foreach ($metrics as $metric):
                            $sira++;
                            $values = [];
                            foreach ($periodLabels as $label) {
                                $v = $firmaData[$label][$metric['key']] ?? 0;
                                $values[] = $v;
                            }
                            $avg = count($values) > 0 ? round(array_sum($values) / count($values)) : 0;
                            $firstVal = $values[0] ?? 0;
                            $lastVal = end($values);
                            $change = calcChange($lastVal, $firstVal);
                            $maxVal = max($values);
                            $minVal = min($values);
                            ?>
                        <tr>
                            <td class="text-center text-muted">
                                <?= $sira ?>
                            </td>
                            <td class="fw-semibold"><i class="bx <?= $metric['icon'] ?> me-1 text-primary"></i>
                                <?= $metric['label'] ?>
                            </td>
                            <?php foreach ($values as $v):
                                $cellClass = '';
                                if (count(array_unique($values)) > 1) {
                                    if ($v === $maxVal)
                                        $cellClass = 'highlight-best';
                                    elseif ($v === $minVal && $v > 0)
                                        $cellClass = 'highlight-worst';
                                }
                                ?>
                                <td class="period-col <?= $cellClass ?>">
                                    <?= number_format($v, 0, ',', '.') ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="period-col fw-bold">
                                <?= number_format($avg, 0, ',', '.') ?>
                            </td>
                            <td class="trend-col">
                                <?= trendBadge($change) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php
                    // Kişi başı ortalama row
                    $sira++;
                    $kisiBasiValues = [];
                    foreach ($periodLabels as $label) {
                        $toplam = $firmaData[$label]['toplam'] ?? 0;
                        $pSayisi = $firmaData[$label]['personel_sayisi'] ?? 1;
                        $kisiBasiValues[] = $pSayisi > 0 ? round($toplam / $pSayisi) : 0;
                    }
                    $kbAvg = count($kisiBasiValues) > 0 ? round(array_sum($kisiBasiValues) / count($kisiBasiValues)) : 0;
                    $kbChange = calcChange(end($kisiBasiValues), reset($kisiBasiValues));
                    $kbMax = max($kisiBasiValues);
                    $kbMin = min($kisiBasiValues);
                    ?>
                    <tr>
                        <td class="text-center text-muted">
                            <?= $sira ?>
                        </td>
                        <td class="fw-semibold"><i class="bx bx-user-check me-1 text-primary"></i>Kişi Başı Ortalama</td>
                        <?php foreach ($kisiBasiValues as $v):
                            $cellClass = '';
                            if (count(array_unique($kisiBasiValues)) > 1) {
                                if ($v === $kbMax)
                                    $cellClass = 'highlight-best';
                                elseif ($v === $kbMin && $v > 0)
                                    $cellClass = 'highlight-worst';
                            }
                            ?>
                            <td class="period-col <?= $cellClass ?>">
                                <?= number_format($v, 0, ',', '.') ?>
                            </td>
                        <?php endforeach; ?>
                        <td class="period-col fw-bold">
                            <?= number_format($kbAvg, 0, ',', '.') ?>
                        </td>
                        <td class="trend-col">
                            <?= trendBadge($kbChange) ?>
                        </td>
                    </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="<?= count($periodLabels) + 4 ?>"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- CHART -->
    <div class="col-12 d-none" id="chartContainer">
        <div class="card">
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="compareChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Load Chart.js if not already loaded
    if (typeof Chart === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
        s.onload = function () { window.dispatchEvent(new Event('chartjs-loaded')); };
        document.head.appendChild(s);
    }
</script>
<script>
    (function () {
        // Chart data preparation
        const periodLabels = <?= json_encode($periodLabels) ?>;
        const compareMode = '<?= $compareMode ?>';
        const chartColors = <?= json_encode($chartColors) ?>;
        const chartBorderColors = <?= json_encode($chartBorderColors) ?>;

        let chartLabels = []; // X-axis labels (personel/bölge names)
        let chartDatasets = [];

        <?php if ($compareMode === 'personel'): ?>
            <?php
            // Sort by total descending and take top 10
            $chartPersonel = $data['personel'];
            uasort($chartPersonel, function ($a, $b) use ($periodLabels) {
                $totalA = array_sum(array_column($a['periods'], 'toplam'));
                $totalB = array_sum(array_column($b['periods'], 'toplam'));
                return $totalB - $totalA;
            });
            $chartPersonel = array_slice($chartPersonel, 0, 10, true);

            // Collect person names for X-axis
            $personNames = [];
            foreach ($chartPersonel as $pData) {
                $personNames[] = $pData['personel_adi'];
            }
            ?>
            chartLabels = <?= json_encode(array_values($personNames)) ?>;

            <?php
            // Each dataset = a period
            $pIdx = 0;
            foreach ($periodLabels as $label):
                $periodValues = [];
                foreach ($chartPersonel as $pData) {
                    $periodValues[] = $pData['periods'][$label]['toplam'] ?? 0;
                }
                ?>
                chartDatasets.push({
                    label: '<?= addslashes($label) ?>',
                    data: <?= json_encode($periodValues) ?>,
                    backgroundColor: chartColors[<?= $pIdx % count($chartColors) ?>],
                    borderColor: chartBorderColors[<?= $pIdx % count($chartColors) ?>],
                    borderWidth: 1,
                    borderRadius: 4
                });
                <?php $pIdx++; endforeach; ?>

        <?php elseif ($compareMode === 'bolge'): ?>
            <?php
            $bolgeNames = array_keys($data['bolge']);
            sort($bolgeNames);
            ?>
            chartLabels = <?= json_encode($bolgeNames) ?>;

            <?php
            $pIdx = 0;
            foreach ($periodLabels as $label):
                $periodValues = [];
                foreach ($bolgeNames as $bName) {
                    $periodValues[] = $data['bolge'][$bName]['periods'][$label]['toplam'] ?? 0;
                }
                ?>
                chartDatasets.push({
                    label: '<?= addslashes($label) ?>',
                    data: <?= json_encode($periodValues) ?>,
                    backgroundColor: chartColors[<?= $pIdx % count($chartColors) ?>],
                    borderColor: chartBorderColors[<?= $pIdx % count($chartColors) ?>],
                    borderWidth: 1,
                    borderRadius: 4
                });
                <?php $pIdx++; endforeach; ?>

        <?php else: // firma ?>
            chartLabels = periodLabels;
            chartDatasets.push({
                label: 'Toplam <?= $valueLabel ?>',
                data: [<?php
                $vals = [];
                foreach ($periodLabels as $l) {
                    $vals[] = $firmaData[$l]['toplam'] ?? 0;
                }
                echo implode(',', $vals);
                ?>],
                backgroundColor: chartColors[0],
                borderColor: chartBorderColors[0],
                borderWidth: 2,
                borderRadius: 6,
                type: 'bar'
            });
            chartDatasets.push({
                label: 'Personel Sayısı',
                data: [<?php
                $vals = [];
                foreach ($periodLabels as $l) {
                    $vals[] = $firmaData[$l]['personel_sayisi'] ?? 0;
                }
                echo implode(',', $vals);
                ?>],
                backgroundColor: 'transparent',
                borderColor: chartBorderColors[1],
                borderWidth: 2.5,
                type: 'line',
                yAxisID: 'y1',
                tension: 0.3,
                pointBackgroundColor: chartBorderColors[1],
                pointRadius: 5
            });
        <?php endif; ?>

        // Initialize chart
        let compareChartInstance = null;

        function initChart() {
            const ctx = document.getElementById('compareChart');
            if (!ctx) return;

            if (compareChartInstance) {
                compareChartInstance.destroy();
            }

            const config = {
                type: 'bar',
                data: { labels: chartLabels, datasets: chartDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: true, mode: 'nearest' },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 16,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                                font: { size: 11, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.85)',
                            titleFont: { size: 13, weight: '700' },
                            bodyFont: { size: 12 },
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': ' + new Intl.NumberFormat('tr-TR').format(ctx.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11, weight: '600' }, maxRotation: 45, minRotation: 0 }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: {
                                font: { size: 11 },
                                callback: function (v) { return new Intl.NumberFormat('tr-TR').format(v); }
                            }
                        }
                    }
                }
            };

            if (compareMode === 'firma') {
                config.options.scales.y1 = {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { size: 11 },
                        callback: function (v) { return v + ' kişi'; }
                    }
                };
            }

            compareChartInstance = new Chart(ctx, config);
        }

        // View toggle
        window.toggleView = function (mode) {
            const table = document.getElementById('tableContainer');
            const chart = document.getElementById('chartContainer');
            const btnTable = document.getElementById('btnShowTable');
            const btnChart = document.getElementById('btnShowChart');
            const btnBoth = document.getElementById('btnShowBoth');

            [btnTable, btnChart, btnBoth].forEach(b => b.classList.remove('active'));

            if (mode === 'table') {
                table.className = 'col-12';
                table.classList.remove('d-none');
                chart.classList.add('d-none');
                btnTable.classList.add('active');
            } else if (mode === 'chart') {
                table.classList.add('d-none');
                chart.className = 'col-12';
                chart.classList.remove('d-none');
                btnChart.classList.add('active');
                setTimeout(initChart, 100);
            } else {
                table.className = 'col-lg-7';
                table.classList.remove('d-none');
                chart.className = 'col-lg-5';
                chart.classList.remove('d-none');
                btnBoth.classList.add('active');
                setTimeout(initChart, 100);
            }
        };

        // Mode switcher
        window.switchCompareMode = function (mode) {
            if (typeof window.loadComparisonReport === 'function') {
                window.loadComparisonReport(mode);
            }
        };

    })();
</script>