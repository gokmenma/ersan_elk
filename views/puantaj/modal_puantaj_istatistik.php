<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$workType = $_GET['work_type'] ?? '';
$workResult = $_GET['work_result'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$Puantaj = new \App\Model\PuantajModel();
$Tanimlamalar = new \App\Model\TanimlamalarModel();
$Personel = new \App\Model\PersonelModel();
$allPersonnelRaw = $Personel->all(true, 'puantaj');
$allPersonnel = array_merge([(object)['id' => '', 'adi_soyadi' => 'Tüm Personeller']], $allPersonnelRaw);

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

<?php 
// Sütun indekslerini Türkçe başlıklara eşle
$columnNames = [
    0 => 'Tarih',
    1 => 'Ekip Kodu',
    2 => 'Personel',
    3 => 'İş Emri Tipi',
    4 => 'İş Emri Sonucu',
    5 => 'Sonuçlanmış',
    6 => 'Açık Olanlar'
];

// Aktif sütun filtrelerini topla
$activeFilters = [];
for ($i = 0; $i <= 6; $i++) {
    if (!empty($_GET['col_' . $i])) {
        $activeFilters[] = $columnNames[$i] . ' = ' . htmlspecialchars($_GET['col_' . $i]);
    }
}
?>

<div class="card border-0 shadow-none mb-3">
    <div class="card-body p-0">
        <div class="row align-items-end g-2">
            <div class="col-md-5">
                <?= Form::FormMultipleSelect2('selectComparisonPeriodsPuantaj', $periodsSelection, [date('Y-m')], 'Karşılaştırılacak Dönemleri Seçin', 'calendar', 'key', '', 'form-select select2', false, 'selectComparisonPeriodsPuantaj', 'data-placeholder="Dönem(ler) seçiniz..."') ?>
            </div>
            <div class="col-md-4">
                <?= Form::FormSelect2('selectComparisonStaffPuantaj', $allPersonnel, '', 'Personel Filtresi', 'user', 'id', 'adi_soyadi', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Personeller"', 'selectComparisonStaffPuantaj') ?>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnRefreshPuantajComparison">
                    <i data-feather="refresh-cw" class="me-1 icon-sm"></i> Getir
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active btn-puantaj-view-toggle p-2" data-view="chart">
                        <i data-feather="bar-chart-2"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-puantaj-view-toggle p-2" data-view="table">
                        <i data-feather="list"></i>
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
        <div id="view-puantaj-chart" class="puantaj-view-container">
            <div id="puantajComparisonChart" style="min-height: 350px;"></div>
        </div>
        
        <!-- Comparison Table -->
        <div id="view-puantaj-table" class="puantaj-view-container d-none">
            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered table-striped" id="puantajComparisonTable">
                    <thead class="table-info text-dark">
                        <tr id="puHeaderRows">
                            <!-- JS ile dolacak -->
                        </tr>
                    </thead>
                    <tbody id="puBodyRows">
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

        $('#selectComparisonPeriodsPuantaj, #selectComparisonStaffPuantaj').select2({
            dropdownParent: $('#statsModal'),
            width: '100%',
            allowClear: true,
            placeholder: function() {
                return $(this).data('placeholder');
            }
        });

        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        let puantajComparisonChart = null;

        function loadPuantajComparison() {
            const selectedPeriods = (typeof $ === 'function' && $('#selectComparisonPeriodsPuantaj').length) ? $('#selectComparisonPeriodsPuantaj').val() : null;
            
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            const staffId = $('#selectComparisonStaffPuantaj').length ? $('#selectComparisonStaffPuantaj').val() : '';
            $('#puantajComparisonChart').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Veriler yükleniyor...</p></div>');

            $.get('views/puantaj/api.php', {
                action: 'get-puantaj-comparison',
                comparison_periods: selectedPeriods.join(','),
                personel_id: staffId,
                work_type: $('select[name="work_type"]').val(),
                work_result: $('select[name="work_result"]').val(),
                sorgu_turu: 'KESME_ACMA'
            }, function(res) {
                const data = typeof res === 'object' ? res : JSON.parse(res);
                
                if (!data || !data.periods || data.periods.length === 0) {
                    $('#puantajComparisonChart').html('<div class="text-center p-5 text-muted">Seçilen dönem(ler) için veri bulunamadı.</div>');
                    $('#puHeaderRows').html('');
                    $('#puBodyRows').html('');
                    return;
                }

                let headerHtml = '<th>İş Türü / Sonucu</th>';
                data.periods.forEach(p => {
                    headerHtml += `<th class="text-center">${p}</th>`;
                });
                headerHtml += '<th class="text-center fw-bold">Toplam</th>';
                $('#puHeaderRows').html(headerHtml);

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
                $('#puBodyRows').html(bodyHtml);

                if (puantajComparisonChart) puantajComparisonChart.destroy();
                
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
                    colors: ['#34c38f', '#556ee6', '#f1b44c', '#f46a6a', '#50a5f1', '#343a40', '#927fbf', '#e83e8c', '#2ab57d', '#4ba3ff']
                };

                $('#puantajComparisonChart').html('');
                puantajComparisonChart = new ApexCharts(document.querySelector("#puantajComparisonChart"), options);
                puantajComparisonChart.render();
            });
        }

        loadPuantajComparison();

        $('#btnRefreshPuantajComparison').on('click', loadPuantajComparison);

        // Toggle View Logic
        $('.btn-puantaj-view-toggle').on('click', function() {
            const view = $(this).data('view');
            $('.btn-puantaj-view-toggle').removeClass('active');
            $(this).addClass('active');
            
            $('.puantaj-view-container').addClass('d-none');
            $('#view-puantaj-' + view).removeClass('d-none');
        });

        $('#btnExportPuantajStatsExcel').on('click', function () {
            var content = document.getElementById('puantajStatsExportArea').innerHTML;
            if (!content) content = document.getElementById('puantajComparisonTable').outerHTML;
            var excelHtml = '<html><head><meta charset="utf-8"></head><body>' + content + '</body></html>';
            var blob = new Blob(['\ufeff', excelHtml], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.download = 'puantaj_istatistikleri.xls';
            link.href = url;
            link.click();
            setTimeout(function () { URL.revokeObjectURL(url); }, 100);
        });
    })();
</script>