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
            <div class="col-md-6">
                <label class="form-label fw-bold small mb-1">
                    <i class="bx bx-calendar me-1"></i> Karşılaştırılacak Dönemleri Seçin
                </label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="bx bx-calendar"></i></span>
                    <select id="selectComparisonPeriodsPuantaj" class="form-select select2" multiple data-placeholder="Dönem(ler) seçiniz...">
                        <?php foreach ($periodsSelection as $p): ?>
                            <option value="<?= $p['val'] ?>" <?= ($p['val'] == date('Y-m')) ? 'selected' : '' ?>>
                                <?= $p['label'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" id="btnRefreshPuantajComparison">
                    <i class="bx bx-refresh me-1"></i> İstatistikleri Getir
                </button>
            </div>
            <div class="col-md-3 text-end">
                <div class="btn-group w-100" role="group">
                    <button type="button" class="btn btn-outline-primary active btn-puantaj-view-toggle" data-view="chart">
                        <i class="bx bx-bar-chart-alt-2"></i> Grafik
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-puantaj-view-toggle" data-view="table">
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
        <h6 class="text-primary mb-3"><i class="bx bx-bar-chart-alt-2 me-1"></i> Aylık İş Karşılaştırması (Ücretli Türler)</h6>
        
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

        $('#selectComparisonPeriodsPuantaj').select2({
            dropdownParent: $('#statsModal'),
            width: '100%'
        });

        let puantajComparisonChart = null;

        function loadPuantajComparison() {
            const selectedPeriods = $('#selectComparisonPeriodsPuantaj').val();
            const personelId = '<?= $personelId ?>';
            
            if (!selectedPeriods || selectedPeriods.length === 0) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
                return;
            }

            $('#puantajComparisonChart').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Veriler yükleniyor...</p></div>');

            $.get('views/puantaj/api.php', {
                action: 'get-puantaj-comparison',
                periods: selectedPeriods,
                personel_id: personelId
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
                    title: { text: 'İş Türü Bazlı Aylık Karşılaştırma', style: { fontSize: '14px', fontWeight: 'bold' } },
                    colors: ['#556ee6', '#34c38f', '#f1b44c', '#f46a6a', '#50a5f1', '#343a40', '#927fbf', '#e83e8c', '#2ab57d', '#4ba3ff']
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