<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$EndeksOkuma = new \App\Model\EndeksOkumaModel();
$Personel = new \App\Model\PersonelModel();
$allPersonnelRaw = $Personel->all(true, 'puantaj');
$allPersonnel = array_merge([(object)['id' => '', 'adi_soyadi' => 'Tüm Personeller']], $allPersonnelRaw);

$Tanimlar = new \App\Model\TanimlamalarModel();
$regionList = $Tanimlar->getFilteredEkipBolgeleri();
$regionOptions = ['' => 'Tüm Bölgeler'];
foreach ($regionList as $r) {
    $regionOptions[$r] = $r;
}

$defterList = $Tanimlar->getDefterKodlari();
$defterOptions = ['' => 'Tüm Defterler'];
foreach ($defterList as $d) {
    if ($d)
        $defterOptions[$d] = $d;
}

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Query for statistics by region
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
?>

<div class="card border-0 shadow-none mb-3">
    <div class="card-body p-0">
        <div class="row align-items-end g-2">
            <div class="col-md-6">
                <?= Form::FormMultipleSelect2('selectComparisonPeriods', $periodsSelection, [date('Y-m')], 'Karşılaştırılacak Dönemleri Seçin', 'calendar', 'key', '', 'form-select select2', false, 'selectComparisonPeriods', 'data-placeholder="Dönem(ler) seçiniz..."') ?>
            </div>
            <div class="col-md-6">
                <?= Form::FormSelect2('selectComparisonStaff', $allPersonnel, '', 'Personel Filtresi', 'user', 'id', 'adi_soyadi', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Personeller"', 'selectComparisonStaff') ?>
            </div>
        </div>
        <div class="row align-items-end g-2 mt-1">
            <div class="col-md-5">
                <?= Form::FormSelect2('selectComparisonRegion', $regionOptions, '', 'Bölge Filtresi', 'globe', 'key', '', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Bölgeler"', 'selectComparisonRegion') ?>
            </div>
            <div class="col-md-4">
                <?= Form::FormSelect2('selectComparisonDefter', $defterOptions, '', 'Defter Filtresi', 'book', 'key', '', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Defterler"', 'selectComparisonDefter') ?>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnRefreshOkumaComparison">
                    <i data-feather="refresh-cw" class="me-1 icon-sm"></i> Getir
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active btn-view-toggle p-2" data-view="chart">
                        <i data-feather="bar-chart-2"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-view-toggle p-2" data-view="table">
                        <i data-feather="list"></i>
                    </button>
                    <button type="button" class="btn btn-outline-success p-2 ms-1" id="btnExportOkumaStatsExcel" title="Excel'e Aktar">
                        <i data-feather="file-text"></i>
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>


<hr class="my-4">

<div class="row">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="text-primary mb-0"><i data-feather="bar-chart-2" class="me-1"></i> Aylık Karşılaştırma</h6>
            <div id="chartTotalBadge" class="badge bg-primary-subtle text-primary fs-12 py-2 px-3 rounded-pill fw-bold shadow-sm d-none">
                <i class="bx bx-check-double me-1 align-middle fs-14"></i> Toplam Okuma: <span id="chartTotalVal" class="fs-13 fw-extrabold ms-1">0</span>
            </div>
        </div>
        
        <!-- Chart Container -->
        <div id="view-chart" class="view-container">
            <div id="okumaComparisonChart" style="min-height: 350px;"></div>
        </div>
        
        <!-- Comparison Table -->
        <div id="view-table" class="view-container d-none">
            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered table-striped" id="okumaComparisonTable">
                    <thead class="table-info text-dark">
                        <tr id="compHeaderRows">
                            <!-- JS ile dolacak -->
                        </tr>
                    </thead>
                    <tbody id="compBodyRows">
                        <!-- JS ile dolacak -->
                    </tbody>
                    <tfoot class="table-light fw-bold" id="compFooterRows">
                        <!-- JS ile dolacak -->
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        if (typeof $ === 'undefined') return;
        
        // Initialize Select2 in modal
        $('#selectComparisonPeriods, #selectComparisonStaff, #selectComparisonRegion, #selectComparisonDefter').select2({
            dropdownParent: $('#statsModal'),
            width: '100%',
            allowClear: true,
            placeholder: function() {
                return $(this).data('placeholder');
            }
        });

        // Initialize Feather Icons
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

            $.get('views/puantaj/api.php', {
                action: 'get-okuma-comparison',
                comparison_periods: selectedPeriods.join(','),
                personel_id: staffId,
                region: region,
                defter: defter
            }, function(res) {
                const data = typeof res === 'object' ? res : JSON.parse(res);
                
                if (!data || !data.periods || data.periods.length === 0) {
                    $('#okumaComparisonChart').html('<div class="text-center p-5 text-muted">Seçilen dönem(ler) için veri bulunamadı.</div>');
                    $('#compHeaderRows').html('');
                    $('#compBodyRows').html('');
                    $('#compFooterRows').html('');
                    $('#chartTotalBadge').addClass('d-none');
                    return;
                }

                // Tabloyu Oluştur
                let headerHtml = '<th>İş Türü (Sayaç Durumu)</th>';
                data.periods.forEach(p => {
                    headerHtml += `<th class="text-center">${p}</th>`;
                });
                headerHtml += '<th class="text-center fw-bold">Toplam</th>';
                $('#compHeaderRows').html(headerHtml);

                let bodyHtml = '';
                const series = [];
                
                // Tablo satırlarını doldur
                data.types.forEach(type => {
                    let rowHtml = `<td class="fw-medium text-nowrap">${type}</td>`;
                    let typeTotal = 0;
                    
                    data.periods.forEach(p => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? data.matrix[type][p] : 0;
                        rowHtml += `<td class="text-center">${val.toLocaleString('tr-TR')}</td>`;
                        typeTotal += val;
                    });
                    
                    rowHtml += `<td class="text-center fw-bold bg-light">${typeTotal.toLocaleString('tr-TR')}</td>`;
                    bodyHtml += `<tr>${rowHtml}</tr>`;
                });
                $('#compBodyRows').html(bodyHtml);

                // Calculate and build footer totals
                let footerHtml = '<tr class="table-light fw-bold"><td>Toplam</td>';
                let grandTotal = 0;
                const columnTotals = {};
                
                data.periods.forEach(p => {
                    columnTotals[p] = 0;
                });

                data.types.forEach(type => {
                    data.periods.forEach(p => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? data.matrix[type][p] : 0;
                        columnTotals[p] += val;
                        grandTotal += val;
                    });
                });

                data.periods.forEach(p => {
                    footerHtml += `<td class="text-center">${columnTotals[p].toLocaleString('tr-TR')}</td>`;
                });
                footerHtml += `<td class="text-center bg-light fw-bold">${grandTotal.toLocaleString('tr-TR')}</td></tr>`;
                $('#compFooterRows').html(footerHtml);

                // Toplam Badge Güncelle
                $('#chartTotalVal').text(grandTotal.toLocaleString('tr-TR'));
                $('#chartTotalBadge').removeClass('d-none').hide().fadeIn(300);

                // Grafik Serilerini Transpoze Et (Dönemler seri, iş türleri kategori)
                data.periods.forEach(p => {
                    const pData = [];
                    data.types.forEach(type => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? data.matrix[type][p] : 0;
                        pData.push(val);
                    });
                    series.push({
                        name: p,
                        data: pData
                    });
                });

                // Grafiği Oluştur
                if (comparisonChart) comparisonChart.destroy();
                
                const dynamicHeight = Math.max(450, data.types.length * 35 + 100);

                const options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: { show: true },
                        fontFamily: 'Inter, sans-serif'
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            barHeight: '75%',
                            borderRadius: 5,
                            borderRadiusApplication: 'end',
                            dataLabels: { position: 'right' }
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        offsetX: 8,
                        style: { 
                            fontSize: '11px', 
                            fontWeight: 600,
                            colors: ["#475569"] 
                        },
                        formatter: function(val) {
                            return val > 0 ? val.toLocaleString('tr-TR') : '';
                        }
                    },
                    xaxis: { 
                        categories: data.types,
                        labels: {
                            style: { colors: '#94a3b8', fontSize: '11px' }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#475569', fontSize: '11px', fontWeight: 500 }
                        }
                    },
                    grid: {
                        borderColor: '#f1f5f9',
                        padding: { right: 40 }
                    },
                    legend: { 
                        position: 'top',
                        horizontalAlign: 'left',
                        fontSize: '12px',
                        fontWeight: 500,
                        itemMargin: { horizontal: 10, vertical: 5 }
                    },
                    colors: ['#38bdf8', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#fb7185', '#2dd4bf', '#818cf8', '#f472b6', '#a3e635']
                };

                $('#okumaComparisonChart').html('');
                comparisonChart = new ApexCharts(document.querySelector("#okumaComparisonChart"), options);
                comparisonChart.render();
            });
        }

        // Initial Load
        loadComparison();

        $('#btnRefreshOkumaComparison').on('click', loadComparison);

        // Toggle View Logic
        $('.btn-view-toggle').on('click', function() {
            const view = $(this).data('view');
            $('.btn-view-toggle').removeClass('active');
            $(this).addClass('active');
            
            $('.view-container').addClass('d-none');
            $('#view-' + view).removeClass('d-none');
        });

        $('#btnExportOkumaStatsExcel').on('click', function () {
            const table = document.getElementById('okumaComparisonTable');
            if (!table || !$('#compBodyRows').find('tr').length) {
                Swal.fire('Uyarı', 'Aktarılacak veri bulunamadı.', 'warning');
                return;
            }
            
            const periods = $('#selectComparisonPeriods').val() || [];
            const fileName = 'Okuma_Istatistikleri_' + periods.join('_') + '.xls';
            
            let excelHtml = `
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        table { border-collapse: collapse; width: 100%; }
                        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        .text-left { text-align: left; }
                    </style>
                </head>
                <body>
                    <h2 style="text-align:center;">Bölge Bazlı Okuma İstatistikleri</h2>
                    <p><b>Dönemler:</b> ${periods.join(', ')}</p>
                    <p><b>Personel:</b> ${$('#selectComparisonStaff option:selected').text()}</p>
                    <br>
                    ${table.outerHTML}
                </body>
                </html>
            `;
            
            const blob = new Blob(['\ufeff', excelHtml], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = fileName;
            link.href = url;
            link.click();
            setTimeout(function () { URL.revokeObjectURL(url); }, 100);
        });
    })();
</script>