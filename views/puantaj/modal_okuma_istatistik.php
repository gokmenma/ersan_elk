<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';
session_start();

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$personelId = $_GET['personel_id'] ?? '';
$firmaId = $_SESSION['firma_id'] ?? 0;

$EndeksOkuma = new \App\Model\EndeksOkumaModel();

// Convert dates for SQL
$sqlStart = \App\Helper\Date::convertExcelDate($startDate, 'Y-m-d') ?: $startDate;
$sqlEnd = \App\Helper\Date::convertExcelDate($endDate, 'Y-m-d') ?: $endDate;

// Query for statistics by region
$periodsSelection = [];
$currentDate = new DateTime();
$currentDate->modify('first day of this month');
// Son 24 ayı listele
for ($i = 0; $i < 24; $i++) {
    $val = $currentDate->format('Y-m');
    $label = \App\Helper\Date::monthName($currentDate->format('m')) . ' ' . $currentDate->format('Y');
    $periodsSelection[] = ['val' => $val, 'label' => $label];
    $currentDate->modify('-1 month');
}
?>

<div class="card border-0 shadow-none mb-3">
    <div class="card-body p-0">
        <div class="row align-items-end g-2">
            <div class="col-md-6">
                <label class="form-label fw-bold small mb-1">
                    <i class="bx bx-calendar me-1"></i> Karşılaştırılacak Dönemleri Seçin
                </label>
                <select id="selectComparisonPeriods" class="form-select select2" multiple data-placeholder="Dönem(ler) seçiniz...">
                    <?php foreach ($periodsSelection as $p): ?>
                        <option value="<?= $p['val'] ?>" <?= ($p['val'] == date('Y-m')) ? 'selected' : '' ?>>
                            <?= $p['label'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnRefreshOkumaComparison">
                    <i class="bx bx-refresh me-1"></i> İstatistikleri Getir
                </button>
            </div>
            <div class="col-md-3 text-end">
                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-outline-primary active btn-view-toggle" data-view="chart">
                        <i class="bx bx-bar-chart-alt-2"></i> Grafik
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-view-toggle" data-view="table">
                        <i class="bx bx-table"></i> Liste
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<hr class="my-4">

<div class="row">
    <div class="col-12">
        <h6 class="text-primary mb-3"><i class="bx bx-bar-chart-alt-2 me-1"></i> Aylık İş Türü Karşılaştırması</h6>
        
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
        $('#selectComparisonPeriods').select2({
            dropdownParent: $('#statsModal'),
            width: '100%'
        });

        let comparisonChart = null;

        function loadComparison() {
            const selectedPeriods = $('#selectComparisonPeriods').val();
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            const staffId = $('#filterStaffId').val() || '';

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
    })();
</script>