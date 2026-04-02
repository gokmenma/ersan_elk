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
            <div class="col-md-5">
                <?= Form::FormMultipleSelect2('selectComparisonPeriods', $periodsSelection, [date('Y-m')], 'Karşılaştırılacak Dönemleri Seçin', 'calendar', 'key', '', 'form-select select2', false, 'selectComparisonPeriods', 'data-placeholder="Dönem(ler) seçiniz..."') ?>
            </div>
            <div class="col-md-4">
                <?= Form::FormSelect2('selectComparisonStaff', $allPersonnel, '', 'Personel Filtresi', 'user', 'id', 'adi_soyadi', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Personeller"', 'selectComparisonStaff') ?>
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
        <h6 class="text-primary mb-3"><i data-feather="bar-chart-2" class="me-1"></i> Aylık Karşılaştırma</h6>
        
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
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        if (typeof $ === 'undefined') return;
        
        // Initialize Select2 in modal
        $('#selectComparisonPeriods, #selectComparisonStaff').select2({
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

            $.get('views/puantaj/api.php', {
                action: 'get-okuma-comparison',
                comparison_periods: selectedPeriods.join(','),
                personel_id: staffId
            }, function(res) {
                const data = typeof res === 'object' ? res : JSON.parse(res);
                
                if (!data || !data.periods || data.periods.length === 0) {
                    $('#okumaComparisonChart').html('<div class="text-center p-5 text-muted">Seçilen dönem(ler) için veri bulunamadı.</div>');
                    $('#compHeaderRows').html('');
                    $('#compBodyRows').html('');
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
                
                data.types.forEach(type => {
                    let rowHtml = `<td class="fw-medium text-nowrap">${type}</td>`;
                    const typeData = [];
                    let typeTotal = 0;
                    
                    data.periods.forEach(p => {
                        const val = (data.matrix[type] && data.matrix[type][p]) ? data.matrix[type][p] : 0;
                        rowHtml += `<td class="text-center">${val.toLocaleString('tr-TR')}</td>`;
                        typeData.push(val);
                        typeTotal += val;
                    });
                    
                    rowHtml += `<td class="text-center fw-bold bg-light">${typeTotal.toLocaleString('tr-TR')}</td>`;
                    bodyHtml += `<tr>${rowHtml}</tr>`;
                    
                    series.push({
                        name: type,
                        data: typeData
                    });
                });
                $('#compBodyRows').html(bodyHtml);

                // Grafiği Oluştur
                if (comparisonChart) comparisonChart.destroy();
                
                const options = {
                    series: series,
                    chart: {
                        type: 'bar',
                        height: 400,
                        stacked: false,
                        toolbar: { show: true }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            columnWidth: '55%',
                            endingShape: 'rounded',
                            dataLabels: { position: 'top' }
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        offsetY: -20,
                        style: { fontSize: '12px', colors: ["#304758"] }
                    },
                    xaxis: { categories: data.periods },
                    legend: { position: 'bottom' },
                    fill: { opacity: 1 },
                    colors: ['#34c38f', '#556ee6', '#f1b44c', '#f46a6a', '#50a5f1', '#74788d', '#343a40', '#927fbf', '#e83e8c', '#2ab57d']
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