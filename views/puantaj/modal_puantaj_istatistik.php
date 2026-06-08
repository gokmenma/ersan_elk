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
                    <button type="button" class="btn btn-outline-success p-2 ms-1" id="btnExportPuantajStatsExcel" title="Excel'e Aktar">
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
                $('#puBodyRows').html(bodyHtml);

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

                if (puantajComparisonChart) puantajComparisonChart.destroy();
                
                // Dinamik yükseklik hesaplama (Kategori başına 35px + 100px padding)
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
            const table = document.getElementById('puantajComparisonTable');
            if (!table || !$('#puBodyRows').find('tr').length) {
                Swal.fire('Uyarı', 'Aktarılacak veri bulunamadı.', 'warning');
                return;
            }
            
            const periods = $('#selectComparisonPeriodsPuantaj').val() || [];
            const fileName = 'Kesme_Acma_Istatistikleri_' + periods.join('_') + '.xls';
            
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
                    <h2 style="text-align:center;">Kesme/Açma İşlemleri İstatistikleri</h2>
                    <p><b>Dönemler:</b> ${periods.join(', ')}</p>
                    <p><b>Personel:</b> ${$('#selectComparisonStaffPuantaj option:selected').text()}</p>
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