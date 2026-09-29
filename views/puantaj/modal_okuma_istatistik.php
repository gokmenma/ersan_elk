<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$EndeksOkuma = new \App\Model\EndeksOkumaModel();
$Personel = new \App\Model\PersonelModel();
$allPersonnelRaw = $Personel->getPersonnelWithActiveTeam('okuma', $endDate ?: null);
$allPersonnel = array_merge([(object)['id' => '', 'adi_soyadi' => 'Tüm Personeller']], $allPersonnelRaw);

$Tanimlar = new \App\Model\TanimlamalarModel();
$fromTanim = $Tanimlar->getFilteredEkipBolgeleri();

// Endeks tablosundaki bölgeleri de çekerek eksiksiz liste oluştur
$dbStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT bolge FROM endeks_okuma WHERE firma_id = ? AND silinme_tarihi IS NULL AND bolge IS NOT NULL AND bolge != '' ORDER BY bolge ASC");
$dbStmt->execute([$firmaId]);
$fromDb = $dbStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$allRegionsRaw = array_merge($fromTanim, $fromDb);
$regionMap = [];
foreach ($allRegionsRaw as $r) {
    $trimmed = trim($r);
    if (!$trimmed) continue;
    $key = mb_strtoupper($trimmed, 'UTF-8');
    if (!isset($regionMap[$key])) {
        // Güzel başlık görünümü için ilk karşılaşılan düzgün hali veya title case
        $regionMap[$key] = $trimmed;
    }
}
ksort($regionMap);

$regionOptions = ['' => 'Tüm Bölgeler (Genel Toplam)'];
foreach ($regionMap as $k => $displayVal) {
    $regionOptions[$displayVal] = $displayVal;
}

$defterList = $Tanimlar->getDefterKodlari();
$defterOptions = ['' => 'Tüm Defterler'];
foreach ($defterList as $d) {
    if ($d) {
        $defterOptions[$d] = $d;
    }
}

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

use App\Helper\Form;

$periodsSelection = [];
$currentDate = new DateTime();
$currentDate->modify('first day of this month');
// Son 24 ayı listele
for ($i = 0; $i < 24; $i++) {
    $val = $currentDate->format('Y-m');
    $label = \App\Helper\Date::monthName($currentDate->format('m')) . ' ' . $currentDate->format('Y');
    $periodsSelection[$val] = $label;
    $currentDate->modify('-1 month');
}

// Varsayılan seçili dönem belirleme
$defaultPeriod = [];
if (!empty($startDate)) {
    $parsedDate = \App\Helper\Date::convertExcelDate($startDate, 'Y-m') ?: date('Y-m', strtotime($startDate));
    if ($parsedDate && isset($periodsSelection[$parsedDate])) {
        $defaultPeriod = [$parsedDate];
    }
}
if (empty($defaultPeriod)) {
    $defaultPeriod = [date('Y-m')];
}
?>

<style>
    .stats-filter-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    [data-bs-theme="dark"] .stats-filter-card {
        background: #1e293b;
        border-color: #334155;
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.07);
    }
    [data-bs-theme="dark"] .kpi-card {
        background: #242b3d;
        border-color: #334155;
    }

    .btn-view-toggle.active {
        background-color: var(--vz-primary, #5156be) !important;
        color: #fff !important;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(81, 86, 190, 0.35);
    }
    .btn-view-toggle {
        border: none;
        color: #64748b;
        font-weight: 500;
        padding: 6px 14px;
        font-size: 0.85rem;
    }

    .stat-progress-bar {
        height: 6px;
        border-radius: 3px;
        background-color: #e2e8f0;
        overflow: hidden;
    }
    .stat-progress-bar-fill {
        height: 100%;
        background-color: #38bdf8;
        border-radius: 3px;
        transition: width 0.4s ease;
    }

    .stats-table th {
        font-weight: 600;
        font-size: 0.85rem;
        background-color: #f1f5f9;
        color: #334155;
        vertical-align: middle;
    }
    [data-bs-theme="dark"] .stats-table th {
        background-color: #1e293b;
        color: #cbd5e1;
    }
    .stats-table td {
        vertical-align: middle;
        font-size: 0.85rem;
    }
</style>

<!-- Filtre Alanı -->
<div class="stats-filter-card mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3 col-md-6">
            <?= Form::FormMultipleSelect2('selectComparisonPeriods', $periodsSelection, $defaultPeriod, 'Dönem(ler) Seçimi', 'calendar', 'key', '', 'form-select select2', false, 'selectComparisonPeriods', 'data-placeholder="Dönem(ler) seçiniz..."') ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <?= Form::FormSelect2('selectComparisonStaff', $allPersonnel, $personelId, 'Personel Filtresi', 'user', 'id', 'adi_soyadi', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Personeller"', 'selectComparisonStaff') ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <?= Form::FormSelect2('selectComparisonRegion', $regionOptions, '', 'Bölge / İlçe Filtresi', 'globe', 'key', '', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Bölgeler (Genel Toplam)"', 'selectComparisonRegion') ?>
        </div>
        <div class="col-lg-3 col-md-6">
            <?= Form::FormSelect2('selectComparisonDefter', $defterOptions, '', 'Defter Filtresi', 'book', 'key', '', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Defterler"', 'selectComparisonDefter') ?>
        </div>
    </div>

    <!-- Aksiyon ve Görünüm Çubuğu -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top border-light-subtle">
        <div class="d-flex gap-2 align-items-center mb-2 mb-md-0">
            <button type="button" class="btn btn-primary px-3 shadow-sm d-flex align-items-center" id="btnRefreshOkumaComparison">
                <i class="bx bx-search-alt me-1 fs-5"></i> <span>İstatistikleri Getir</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm px-2" id="btnClearOkumaFilters" title="Filtreleri Varsayılana Döndür">
                <i class="bx bx-reset me-1"></i> Sıfırla
            </button>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Görünüm Seçici (Grafik / Tablo) -->
            <div class="btn-group p-1 bg-white border rounded-pill shadow-xs" role="group">
                <button type="button" class="btn btn-view-toggle rounded-pill active" data-view="chart" title="Grafik Görünümü">
                    <i class="bx bx-bar-chart-alt-2 me-1 align-middle"></i> Grafik
                </button>
                <button type="button" class="btn btn-view-toggle rounded-pill" data-view="table" title="Tablo Görünümü">
                    <i class="bx bx-table me-1 align-middle"></i> Tablo
                </button>
            </div>

            <!-- Gelişmiş Excel Dışa Aktar -->
            <button type="button" class="btn btn-success shadow-sm d-flex align-items-center px-3" id="btnExportOkumaStatsExcel" title="Tüm bölgeleri ayrı sayfalarda ve genel toplamı içerecek şekilde Excel'e aktar">
                <i class="bx bxs-file-export me-1 fs-5"></i> <span>Excel'e Aktar</span>
            </button>
        </div>
    </div>
</div>

<!-- KPI Özet Kartları -->
<div class="row g-2 mb-3" id="statsKpiRow">
    <div class="col-6 col-md-3">
        <div class="kpi-card border-start border-primary border-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fs-11 text-uppercase fw-semibold">Toplam Okuma</div>
                    <div class="fs-18 fw-extrabold text-primary mt-1" id="kpiTotalRead">0</div>
                </div>
                <div class="avatar-sm rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center">
                    <i class="bx bx-check-double fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card border-start border-success border-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fs-11 text-uppercase fw-semibold">Sayaç Normal</div>
                    <div class="fs-18 fw-extrabold text-success mt-1" id="kpiNormalRead">0 <span class="fs-11 fw-medium text-muted" id="kpiNormalRatio">(%0)</span></div>
                </div>
                <div class="avatar-sm rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center">
                    <i class="bx bx-check-circle fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card border-start border-warning border-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fs-11 text-uppercase fw-semibold">Diğer / Sorunlu</div>
                    <div class="fs-18 fw-extrabold text-warning mt-1" id="kpiAbnormalRead">0 <span class="fs-11 fw-medium text-muted" id="kpiAbnormalRatio">(%0)</span></div>
                </div>
                <div class="avatar-sm rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center">
                    <i class="bx bx-error-alt fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card border-start border-info border-3">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted fs-11 text-uppercase fw-semibold">Seçili Kapsam</div>
                    <div class="fs-14 fw-bold text-info mt-1 text-truncate" id="kpiScopeText" style="max-width: 130px;">Tüm Bölgeler</div>
                </div>
                <div class="avatar-sm rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center">
                    <i class="bx bx-map-pin fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İçerik Alanı -->
<div class="row">
    <div class="col-12">
        <!-- Grafik Görünümü -->
        <div id="view-chart" class="view-container">
            <div class="card border border-light-subtle shadow-none rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="text-primary fw-bold mb-0">
                            <i class="bx bx-bar-chart-alt-2 me-1"></i> Sayaç Durumu Dağılım Grafiği
                        </h6>
                        <small class="text-muted" id="chartSubtitleText"></small>
                    </div>
                    <div id="okumaComparisonChart" style="min-height: 380px;">
                        <div class="text-center p-5 text-muted">
                            <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                            <p class="mt-2 mb-0">İstatistikler yükleniyor...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablo Görünümü -->
        <div id="view-table" class="view-container d-none">
            <div class="card border border-light-subtle shadow-none rounded-3">
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table table-sm table-hover table-striped table-bordered stats-table mb-0" id="okumaComparisonTable">
                            <thead class="sticky-top">
                                <tr id="compHeaderRows">
                                    <!-- JS ile dolacak -->
                                </tr>
                            </thead>
                            <tbody id="compBodyRows">
                                <!-- JS ile dolacak -->
                            </tbody>
                            <tfoot class="table-light fw-bold sticky-bottom" id="compFooterRows">
                                <!-- JS ile dolacak -->
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        if (typeof $ === 'undefined') return;

        // Select2 Başlatma
        $('#selectComparisonPeriods, #selectComparisonStaff, #selectComparisonRegion, #selectComparisonDefter').select2({
            dropdownParent: $('#statsModal'),
            width: '100%',
            allowClear: true,
            placeholder: function() {
                return $(this).data('placeholder');
            }
        });

        // Feather Icons (Varsa)
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        let comparisonChart = null;

        function loadComparison() {
            const selectedPeriods = $('#selectComparisonPeriods').val();
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            const staffId = $('#selectComparisonStaff').val() || '';
            const region = $('#selectComparisonRegion').val() || '';
            const defter = $('#selectComparisonDefter').val() || '';

            // Yükleniyor durumunu göster
            const $btn = $('#btnRefreshOkumaComparison');
            const originalBtnHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Yükleniyor...');

            $('#okumaComparisonChart').html('<div class="text-center p-5 text-muted"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">İstatistikler yükleniyor...</p></div>');
            $('#compBodyRows').html('<tr><td colspan="10" class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Veriler getiriliyor...</td></tr>');

            $.get('views/puantaj/api.php', {
                action: 'get-okuma-comparison',
                comparison_periods: selectedPeriods.join(','),
                personel_id: staffId,
                region: region,
                defter: defter
            }, function(res) {
                $btn.prop('disabled', false).html(originalBtnHtml);

                const data = typeof res === 'object' ? res : JSON.parse(res);

                if (!data || !data.periods || data.periods.length === 0 || !data.types || data.types.length === 0) {
                    $('#okumaComparisonChart').html('<div class="text-center p-5 text-muted"><i class="bx bx-info-circle fs-1 text-warning mb-2 d-block"></i>Seçilen kriterlere uygun veri bulunamadı.</div>');
                    $('#compHeaderRows').html('');
                    $('#compBodyRows').html('<tr><td class="text-center p-4 text-muted">Kayıt bulunamadı.</td></tr>');
                    $('#compFooterRows').html('');
                    
                    // KPI Reset
                    $('#kpiTotalRead').text('0');
                    $('#kpiNormalRead').html('0 <span class="fs-11 fw-medium text-muted">(%0)</span>');
                    $('#kpiAbnormalRead').html('0 <span class="fs-11 fw-medium text-muted">(%0)</span>');
                    $('#kpiScopeText').text(region || 'Tüm Bölgeler');
                    $('#chartSubtitleText').text('');
                    return;
                }

                // Toplam ve Normal / Anormal hesaplamaları
                let grandTotal = 0;
                let normalTotal = 0;
                const columnTotals = {};
                data.periods.forEach(p => { columnTotals[p] = 0; });

                const typeTotals = {};
                data.types.forEach(type => {
                    typeTotals[type] = 0;
                    data.periods.forEach(p => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? Number(data.matrix[type][p]) : 0;
                        columnTotals[p] += val;
                        typeTotals[type] += val;
                        grandTotal += val;

                        if (type.toUpperCase().indexOf('NORMAL') !== -1) {
                            normalTotal += val;
                        }
                    });
                });

                const abnormalTotal = Math.max(0, grandTotal - normalTotal);
                const normalRatio = grandTotal > 0 ? ((normalTotal / grandTotal) * 100).toFixed(1) : 0;
                const abnormalRatio = grandTotal > 0 ? ((abnormalTotal / grandTotal) * 100).toFixed(1) : 0;

                // KPI Kartlarını Güncelle
                $('#kpiTotalRead').text(grandTotal.toLocaleString('tr-TR'));
                $('#kpiNormalRead').html(`${normalTotal.toLocaleString('tr-TR')} <span class="fs-11 fw-medium text-success">(%${normalRatio})</span>`);
                $('#kpiAbnormalRead').html(`${abnormalTotal.toLocaleString('tr-TR')} <span class="fs-11 fw-medium text-warning">(%${abnormalRatio})</span>`);
                $('#kpiScopeText').text(region ? region : 'Tüm Bölgeler (Genel)');
                $('#chartSubtitleText').text(region ? `Bölge: ${region}` : 'Genel Toplam (Tüm İlçeler)');

                // TABLO OLUŞTURMA
                let headerHtml = '<th style="min-width: 200px;">Sayaç Durumu / İş Türü</th>';
                data.periods.forEach(p => {
                    headerHtml += `<th class="text-center" style="min-width: 100px;">${p}</th>`;
                });
                headerHtml += '<th class="text-center fw-bold bg-light" style="min-width: 110px;">Toplam</th>';
                headerHtml += '<th class="text-center" style="min-width: 130px;">Oran (%)</th>';
                $('#compHeaderRows').html(headerHtml);

                let bodyHtml = '';
                data.types.forEach(type => {
                    const rowSum = typeTotals[type] || 0;
                    const rowRatio = grandTotal > 0 ? ((rowSum / grandTotal) * 100).toFixed(1) : 0;

                    let rowHtml = `<td><span class="fw-semibold text-dark">${type}</span></td>`;
                    data.periods.forEach(p => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? Number(data.matrix[type][p]) : 0;
                        rowHtml += `<td class="text-end pe-3 font-monospace">${val > 0 ? val.toLocaleString('tr-TR') : '<span class="text-muted opacity-50">-</span>'}</td>`;
                    });

                    rowHtml += `<td class="text-end pe-3 fw-bold bg-light-subtle font-monospace">${rowSum.toLocaleString('tr-TR')}</td>`;
                    rowHtml += `<td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="stat-progress-bar flex-grow-1">
                                <div class="stat-progress-bar-fill" style="width: ${rowRatio}%"></div>
                            </div>
                            <span class="fs-11 fw-semibold text-muted font-monospace" style="min-width: 42px;">%${rowRatio}</span>
                        </div>
                    </td>`;

                    bodyHtml += `<tr>${rowHtml}</tr>`;
                });
                $('#compBodyRows').html(bodyHtml);

                // Tablo Footer
                let footerHtml = '<tr class="table-light fw-bold border-top border-dark-subtle"><td>GENEL TOPLAM</td>';
                data.periods.forEach(p => {
                    footerHtml += `<td class="text-end pe-3 font-monospace">${columnTotals[p].toLocaleString('tr-TR')}</td>`;
                });
                footerHtml += `<td class="text-end pe-3 font-monospace fs-13 text-primary bg-primary-subtle">${grandTotal.toLocaleString('tr-TR')}</td>`;
                footerHtml += `<td class="text-center font-monospace text-muted">%100.0</td></tr>`;
                $('#compFooterRows').html(footerHtml);

                // GRAFİK OLUŞTURMA
                const series = [];
                data.periods.forEach(p => {
                    const pData = [];
                    data.types.forEach(type => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? Number(data.matrix[type][p]) : 0;
                        pData.push(val);
                    });
                    series.push({
                        name: p,
                        data: pData
                    });
                });

                if (comparisonChart) {
                    comparisonChart.destroy();
                }

                const dynamicHeight = Math.max(380, data.types.length * 36 + 90);

                const chartOptions = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: { show: true },
                        fontFamily: 'Inter, system-ui, -apple-system, sans-serif'
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            barHeight: data.periods.length > 1 ? '75%' : '65%',
                            borderRadius: 4,
                            borderRadiusApplication: 'end',
                            dataLabels: { position: 'right' }
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        offsetX: 6,
                        style: {
                            fontSize: '11px',
                            fontWeight: 600,
                            colors: ["#334155"]
                        },
                        formatter: function(val) {
                            return val > 0 ? val.toLocaleString('tr-TR') : '';
                        }
                    },
                    xaxis: {
                        categories: data.types,
                        labels: {
                            style: { colors: '#64748b', fontSize: '11px' },
                            formatter: function(val) {
                                return Number(val).toLocaleString('tr-TR');
                            }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#334155', fontSize: '11.5px', fontWeight: 500 }
                        }
                    },
                    grid: {
                        borderColor: '#f1f5f9',
                        padding: { right: 40, left: 10 }
                    },
                    tooltip: {
                        theme: 'light',
                        y: {
                            formatter: function(val) {
                                const ratio = grandTotal > 0 ? ((val / grandTotal) * 100).toFixed(1) : 0;
                                return `${val.toLocaleString('tr-TR')} adet (%${ratio})`;
                            }
                        }
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'left',
                        fontSize: '12px',
                        fontWeight: 600,
                        itemMargin: { horizontal: 10, vertical: 5 }
                    },
                    colors: ['#38bdf8', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#fb7185', '#2dd4bf', '#818cf8', '#f472b6', '#a3e635']
                };

                $('#okumaComparisonChart').html('');
                comparisonChart = new ApexCharts(document.querySelector("#okumaComparisonChart"), chartOptions);
                comparisonChart.render();
            }).fail(function() {
                $btn.prop('disabled', false).html(originalBtnHtml);
                Swal.fire('Hata', 'İstatistik verileri alınırken sunucu hatası oluştu.', 'error');
            });
        }

        // İlk Yükleme
        loadComparison();

        // Buton Olayları
        $('#btnRefreshOkumaComparison').on('click', loadComparison);

        // Filtreleri Sıfırla
        $('#btnClearOkumaFilters').on('click', function() {
            $('#selectComparisonStaff').val('').trigger('change');
            $('#selectComparisonRegion').val('').trigger('change');
            $('#selectComparisonDefter').val('').trigger('change');
            loadComparison();
        });

        // Görünüm Değiştirme (Grafik / Tablo)
        $('.btn-view-toggle').on('click', function() {
            const view = $(this).data('view');
            $('.btn-view-toggle').removeClass('active');
            $(this).addClass('active');

            $('.view-container').addClass('d-none');
            $('#view-' + view).removeClass('d-none');
        });

        // Excel Dışa Aktarma (PhpSpreadsheet Çoklu Sayfa / Tek Sayfa Desteği)
        $('#btnExportOkumaStatsExcel').on('click', function () {
            const selectedPeriods = $('#selectComparisonPeriods').val();
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            const staffId = $('#selectComparisonStaff').val() || '';
            const region = $('#selectComparisonRegion').val() || '';
            const defter = $('#selectComparisonDefter').val() || '';

            const params = new URLSearchParams({
                action: 'export-okuma-comparison-excel',
                comparison_periods: selectedPeriods.join(','),
                personel_id: staffId,
                region: region,
                defter: defter
            });

            // Kullanıcıya bilgi ver
            const toastMsg = region ? 
                `'${region}' bölgesi Excel dosyası hazırlanıyor...` : 
                `Tüm bölgeler ve Genel Toplam sayfalarını içeren Excel hazırlanıyor...`;

            if (typeof Toast !== 'undefined') {
                Toast.fire({ icon: 'info', title: toastMsg });
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Excel İndiriliyor',
                    text: toastMsg,
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                });
            }

            // Dosya indirme isteği gönder
            window.location.href = 'views/puantaj/api.php?' + params.toString();
        });
    })();
</script>