<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$Model = new \App\Model\SayacDegisimModel();
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

<div class="card border-0 shadow-none mb-3">
    <div class="card-body p-0">
        <div class="row align-items-end g-2">
            <div class="col-md-5">
                <?= Form::FormMultipleSelect2('selectComparisonPeriodsSayac', $periodsSelection, [date('Y-m')], 'Karşılaştırılacak Dönemleri Seçin', 'calendar', 'key', '', 'form-select select2', false, 'selectComparisonPeriodsSayac', 'data-placeholder="Dönem(ler) seçiniz..."') ?>
            </div>
            <div class="col-md-4">
                <?= Form::FormSelect2('selectComparisonStaffSayac', $allPersonnel, '', 'Personel Filtresi', 'user', 'id', 'adi_soyadi', 'form-select select2', false, 'width:100%', 'data-placeholder="Tüm Personeller"', 'selectComparisonStaffSayac') ?>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnRefreshSayacComparison">
                    <i data-feather="refresh-cw" class="me-1 icon-sm"></i> Getir
                </button>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 text-end">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary active btn-sayac-view-toggle p-2" data-view="chart">
                        <i data-feather="bar-chart-2"></i>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sayac-view-toggle p-2" data-view="table">
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
        <div id="view-sayac-chart" class="sayac-view-container">
            <div id="sayacComparisonChart" style="min-height: 350px;"></div>
        </div>
        
        <!-- Comparison Table -->
        <div id="view-sayac-table" class="sayac-view-container d-none">
            <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered table-striped" id="sayacComparisonTable">
                    <thead class="table-info text-dark">
                        <tr id="sayacHeaderRows"></tr>
                    </thead>
                    <tbody id="sayacBodyRows"></tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</div>

<script>
    (function() {
        if (typeof $ === 'undefined') return;

        $('#selectComparisonPeriodsSayac, #selectComparisonStaffSayac').select2({
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

        let sayacComparisonChart = null;

        function loadSayacComparison() {
            const selectedPeriods = $('#selectComparisonPeriodsSayac').val();
            const personelId = '<?= $personelId ?>';
            
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            $('#sayacComparisonChart').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Veriler yükleniyor...</p></div>');

            const staffId = $('#selectComparisonStaffSayac').val() || '';

            $.get('views/puantaj/api.php', {
                action: 'get-sayac-comparison',
                periods: selectedPeriods.join(','),
                personel_id: staffId
            }, function(res) {
                const data = typeof res === 'object' ? res : JSON.parse(res);
                
                if (!data || !data.periods || data.periods.length === 0) {
                    $('#sayacComparisonChart').html('<div class="text-center p-5 text-muted">Seçilen dönem(ler) için veri bulunamadı.</div>');
                    $('#sayacHeaderRows').html('');
                    $('#sayacBodyRows').html('');
                    return;
                }

                let headerHtml = '<th>İş Emri Sonucu</th>';
                data.periods.forEach(p => {
                    headerHtml += `<th class="text-center">${p}</th>`;
                });
                headerHtml += '<th class="text-center fw-bold">Toplam</th>';
                $('#sayacHeaderRows').html(headerHtml);

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
                $('#sayacBodyRows').html(bodyHtml);

                if (sayacComparisonChart) sayacComparisonChart.destroy();
                
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

                $('#sayacComparisonChart').html('');
                sayacComparisonChart = new ApexCharts(document.querySelector("#sayacComparisonChart"), options);
                sayacComparisonChart.render();
            });
        }

        loadSayacComparison();

        $('#btnRefreshSayacComparison').on('click', loadSayacComparison);

        // Toggle View Logic
        $('.btn-sayac-view-toggle').on('click', function() {
            const view = $(this).data('view');
            $('.btn-sayac-view-toggle').removeClass('active');
            $(this).addClass('active');
            
            $('.sayac-view-container').addClass('d-none');
            $('#view-sayac-' + view).removeClass('d-none');
        });

        $('#btnExportSayacStatsExcel').on('click', function () {
            var table = document.getElementById('sayacComparisonTable');
            var excelHtml = '<html><head><meta charset="utf-8"></head><body>' + table.outerHTML + '</body></html>';
            var blob = new Blob(['\ufeff', excelHtml], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.download = 'sayac_istatistikleri.xls';
            link.href = url;
            link.click();
            setTimeout(function () { URL.revokeObjectURL(url); }, 100);
        });
    })();
</script>
