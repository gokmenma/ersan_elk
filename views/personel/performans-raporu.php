<?php
/**
 * Personel Performans Raporu
 * Departman bazlı personel performans karşılaştırma sayfası
 * Kesme Açma / Endeks Okuma / Sayaç Sökme Takma
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\Gate;

?>

<div class="container-fluid">
    <?php
    $maintitle = "Personel";
    $subtitle = "Performans";
    $title = "Personel Performans Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Filtre Barı -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <!-- Departman Seçimi -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-bold me-1"><i class="bx bx-buildings me-1"></i>Departman:</span>
                            <div class="btn-group" role="group" id="departmanGroup">
                                <button type="button" class="btn btn-sm dept-btn active" data-dept="kesme_acma"
                                    style="background: #e74a3b; color: #fff; border-color: #e74a3b;">
                                    <i class="bx bx-cut me-1"></i>Kesme Açma
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary dept-btn" data-dept="endeks_okuma"
                                    data-color="#36b9cc">
                                    <i class="bx bx-tachometer me-1"></i>Endeks Okuma
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary dept-btn" data-dept="sayac_degisim"
                                    data-color="#f6c23e">
                                    <i class="bx bx-reset me-1"></i>Sayaç Sökme Takma
                                </button>
                            </div>
                        </div>

                        <!-- Dönem Seçimi -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-bold me-1"><i class="bx bx-calendar me-1"></i>Dönem:</span>
                            <div class="btn-group btn-group-sm" role="group" id="periodGroup">
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="gunluk">Günlük</button>
                                <button type="button" class="btn btn-primary period-btn active" data-period="aylik">Aylık</button>
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="yillik">Yıllık</button>
                            </div>
                        </div>

                        <!-- Tarih Seçici -->
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group input-group-sm" style="width: 180px;">
                                <span class="input-group-text"><i class="bx bx-calendar-event"></i></span>
                                <input type="text" class="form-control" id="tarihSecici" placeholder="Tarih Seçin">
                            </div>
                            <button class="btn btn-sm btn-primary" id="btnFiltrele">
                                <i class="bx bx-search-alt me-1"></i>Filtrele
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dönem Bilgisi -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex align-items-center">
                <h6 class="mb-0 text-muted" id="donemBilgisi" style="font-size: 0.85rem;">
                    <i class="bx bx-info-circle me-1"></i>
                    <span id="donemText">Yükleniyor...</span>
                </h6>
            </div>
        </div>
    </div>

    <!-- KPI Kartları -->
    <div class="row g-3 mb-4" id="kpiCards">
        <!-- Toplam İş -->
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid var(--dept-color, #e74a3b) !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="icon-box" style="background: rgba(var(--dept-rgb, 231, 74, 59), 0.1); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <i class="bx bx-bar-chart-alt-2 fs-5" id="kpiIcon1" style="color: var(--dept-color, #e74a3b);"></i>
                        </div>
                        <span class="badge bg-light text-muted fw-bold" style="font-size:0.65rem;" id="kpiLabel">TOPLAM</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;" id="kpiTitle1">TOPLAM İŞ</p>
                    <h3 class="mb-0 fw-bold" id="kpiValue1" style="color: var(--dept-color, #e74a3b);">0</h3>
                </div>
            </div>
        </div>

        <!-- Personel Sayısı -->
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #556ee6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="icon-box" style="background: rgba(85, 110, 230, 0.1); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <i class="bx bx-group fs-5" style="color: #556ee6;"></i>
                        </div>
                        <span class="badge bg-light text-muted fw-bold" style="font-size:0.65rem;">PERSONEL</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">PERSONEL SAYISI</p>
                    <h3 class="mb-0 fw-bold text-primary" id="kpiValue2">0</h3>
                </div>
            </div>
        </div>

        <!-- Kişi Başı Ortalama -->
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #34c38f !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="icon-box" style="background: rgba(52, 195, 143, 0.1); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <i class="bx bx-trending-up fs-5" style="color: #34c38f;"></i>
                        </div>
                        <span class="badge bg-light text-muted fw-bold" style="font-size:0.65rem;">ORTALAMA</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">KİŞİ BAŞI ORTALAMA</p>
                    <h3 class="mb-0 fw-bold text-success" id="kpiValue3">0</h3>
                </div>
            </div>
        </div>

        <!-- En İyi Performans -->
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #f1b44c !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="icon-box" style="background: rgba(241, 180, 76, 0.1); width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center;">
                            <i class="bx bx-trophy fs-5" style="color: #f1b44c;"></i>
                        </div>
                        <span class="badge bg-light text-muted fw-bold" style="font-size:0.65rem;">EN İYİ</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">EN İYİ PERFORMANS</p>
                    <h4 class="mb-0 fw-bold" style="color: #f1b44c; font-size: 1.1rem;" id="kpiValue4">-</h4>
                    <small class="text-muted" id="kpiValue4Sub"></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafikler -->
    <div class="row g-3 mb-4">
        <!-- Trend Grafiği -->
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-line-chart me-1 text-muted"></i>Performans Trendi
                        </h6>
                        <span class="badge bg-soft-primary text-primary" id="trendPeriodLabel">Aylık</span>
                    </div>
                    <div id="trendChart" style="min-height: 320px;"></div>
                </div>
            </div>
        </div>

        <!-- Personel Karşılaştırma -->
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-bar-chart me-1 text-muted"></i>En İyi 15 Personel
                        </h6>
                        <span class="badge bg-soft-success text-success" id="barChartLabel">Sıralama</span>
                    </div>
                    <div id="barChart" style="min-height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personel Sıralama Tablosu -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-list-ol me-1 text-muted"></i>Personel Performans Sıralaması
                        </h6>
                        <button class="btn btn-sm btn-outline-success" id="btnExcelExport">
                            <i class="bx bx-file me-1"></i>Excel'e Aktar
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="performansTable" class="table table-hover table-bordered w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>Personel</th>
                                    <th>Departman</th>
                                    <th class="text-end">Toplam</th>
                                    <th class="text-center" style="width:200px;">Performans</th>
                                </tr>
                            </thead>
                            <tbody id="performansTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.7); z-index:9999; display:flex; align-items:center; justify-content:center;">
    <div class="text-center">
        <div class="spinner-border text-primary mb-2" role="status" style="width:3rem; height:3rem;">
            <span class="visually-hidden">Yükleniyor...</span>
        </div>
        <p class="text-muted fw-bold">Veriler yükleniyor...</p>
    </div>
</div>

<style>
    :root {
        --dept-color: #e74a3b;
        --dept-rgb: 231, 74, 59;
    }

    .dept-btn {
        transition: all 0.25s ease;
        border-radius: 6px !important;
        font-size: 0.8rem;
        padding: 0.35rem 0.85rem;
        font-weight: 600;
    }

    .dept-btn:not(.active):hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
    }

    .period-btn {
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.3rem 0.7rem;
        border-radius: 6px !important;
    }

    .animate-card {
        animation: fadeInUp 0.4s ease forwards;
        opacity: 0;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-card:nth-child(1) { animation-delay: 0s; }
    .animate-card:nth-child(2) { animation-delay: 0.08s; }
    .animate-card:nth-child(3) { animation-delay: 0.16s; }
    .animate-card:nth-child(4) { animation-delay: 0.24s; }

    .rank-medal {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.72rem;
        color: #fff;
    }

    .rank-1 { background: linear-gradient(135deg, #f1b44c, #f5c06d); }
    .rank-2 { background: linear-gradient(135deg, #a0aec0, #bfc8d6); }
    .rank-3 { background: linear-gradient(135deg, #cd7f32, #d9a066); }
    .rank-other { background: #e2e8f0; color: #64748b; }

    .perf-bar-bg {
        height: 8px;
        background: #f1f5f9;
        border-radius: 4px;
        overflow: hidden;
    }

    .perf-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.6s ease;
    }

    #performansTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #performansTable tbody tr:hover {
        background-color: rgba(var(--dept-rgb, 231, 74, 59), 0.04) !important;
    }

    #loadingOverlay {
        backdrop-filter: blur(2px);
    }
</style>

<script>
$(document).ready(function() {
    // Renk tanımları
    const deptColors = {
        kesme_acma: { color: '#e74a3b', rgb: '231, 74, 59', icon: 'bx-cut', label: 'Kesme Açma', unit: 'İş' },
        endeks_okuma: { color: '#36b9cc', rgb: '54, 185, 204', icon: 'bx-tachometer', label: 'Endeks Okuma', unit: 'Abone' },
        sayac_degisim: { color: '#f6c23e', rgb: '246, 194, 62', icon: 'bx-reset', label: 'Sayaç Sökme Takma', unit: 'İşlem' }
    };

    let currentDept = 'kesme_acma';
    let currentPeriod = 'aylik';
    let currentDate = '<?= date("Y-m-d") ?>';
    let trendChart = null;
    let barChart = null;
    let dataTable = null;

    // Flatpickr
    let fp = null;

    function initFlatpickr(mode) {
        if (fp) {
            fp.destroy();
        }

        let options = {
            locale: 'tr',
            defaultDate: currentDate,
            onChange: function(selectedDates, dateStr) {
                if (selectedDates.length > 0) {
                    const d = selectedDates[0];
                    if (mode === 'yillik') {
                        currentDate = d.getFullYear() + '-01-01';
                    } else if (mode === 'aylik') {
                        currentDate = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-01';
                    } else {
                        currentDate = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                    }
                }
            }
        };

        if (mode === 'aylik') {
            options.plugins = [
                new monthSelectPlugin({
                    shorthand: true, // "Oca", "Şub" vb.
                    dateFormat: "m.Y",
                    altFormat: "F Y", // "Ocak 2026"
                    theme: "light"
                })
            ];
        } else if (mode === 'yillik') {
            options.dateFormat = "Y";
            // Normal datepicker'ı gizle, sadece yıl seçimi gibi davranması için
            // Ancak flatpickr'da yerleşik salt yıl seçimi plugin'i yok,
            // bu yüzden günü/ayı kısıtlayarak veya onChange'de sadece yılı alarak çözüyoruz.
        } else {
            options.dateFormat = 'd.m.Y';
        }

        fp = flatpickr('#tarihSecici', options);
    }
    
    initFlatpickr(currentPeriod);

    // Departman değiştir
    $('#departmanGroup').on('click', '.dept-btn', function() {
        const dept = $(this).data('dept');
        const info = deptColors[dept];

        // Active state güncelle
        $('.dept-btn').removeClass('active').addClass('btn-outline-secondary').css({background: '', color: '', borderColor: ''});
        $(this).removeClass('btn-outline-secondary').addClass('active').css({
            background: info.color,
            color: '#fff',
            borderColor: info.color
        });

        // CSS değişkenleri
        document.documentElement.style.setProperty('--dept-color', info.color);
        document.documentElement.style.setProperty('--dept-rgb', info.rgb);

        // KPI renkleri güncelle
        $('#kpiIcon1').css('color', info.color);
        $('#kpiCards .col-md-6:first-child .card').css('border-bottom-color', info.color + ' !important');

        currentDept = dept;
        loadData();
    });

    // Dönem değiştir
    $('#periodGroup').on('click', '.period-btn', function() {
        $('.period-btn').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        currentPeriod = $(this).data('period');

        // Flatpickr modunu güncelle
        updateFlatpickrMode();
        loadData();
    });

    function updateFlatpickrMode() {
        initFlatpickr(currentPeriod);
    }

    // Filtrele butonu
    $('#btnFiltrele').on('click', function() {
        loadData();
    });

    // Veri yükle
    function loadData() {
        showLoading();

        $.ajax({
            url: 'views/personel/api/performans-raporu-api.php',
            type: 'GET',
            data: {
                action: 'get-performans',
                departman: currentDept,
                period: currentPeriod,
                tarih: currentDate
            },
            dataType: 'json',
            success: function(res) {
                hideLoading();
                if (res.status === 'success') {
                    updateKPIs(res.summary);
                    updateTrendChart(res.gunluk_trend);
                    updateBarChart(res.personeller);
                    updateTable(res.personeller);
                    updateDonemBilgisi(res);
                } else {
                    Swal.fire('Hata', res.message || 'Veri yüklenemedi.', 'error');
                }
            },
            error: function() {
                hideLoading();
                Swal.fire('Hata', 'Sunucu ile bağlantı kurulamadı.', 'error');
            }
        });
    }

    function showLoading() {
        $('#loadingOverlay').css('display', 'flex');
    }

    function hideLoading() {
        $('#loadingOverlay').hide();
    }

    // KPI güncelle
    function updateKPIs(summary) {
        const info = deptColors[currentDept];
        $('#kpiTitle1').text('TOPLAM ' + info.unit.toUpperCase());
        $('#kpiValue1').text(formatNumber(summary.toplam));
        $('#kpiValue2').text(formatNumber(summary.personel_sayisi));
        $('#kpiValue3').text(formatNumber(summary.ortalama));

        if (summary.en_iyi) {
            $('#kpiValue4').text(summary.en_iyi.adi_soyadi || '-');
            $('#kpiValue4Sub').text(formatNumber(summary.en_iyi.toplam) + ' ' + info.unit.toLowerCase());
        } else {
            $('#kpiValue4').text('-');
            $('#kpiValue4Sub').text('');
        }
    }

    // Trend grafiği
    function updateTrendChart(trendData) {
        const info = deptColors[currentDept];
        const categories = trendData.map(d => formatDateLabel(d.tarih));
        const values = trendData.map(d => parseInt(d.toplam));

        const periodLabels = { gunluk: 'Günlük', aylik: 'Aylık', yillik: 'Yıllık' };
        $('#trendPeriodLabel').text(periodLabels[currentPeriod] || 'Aylık');

        if (trendChart) trendChart.destroy();

        trendChart = new ApexCharts(document.querySelector("#trendChart"), {
            series: [{
                name: info.label,
                data: values
            }],
            chart: {
                type: 'area',
                height: 320,
                toolbar: { show: true, tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true } },
                fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 600 }
            },
            colors: [info.color],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.4,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            stroke: { curve: 'smooth', width: 2.5 },
            dataLabels: { enabled: false },
            xaxis: {
                categories: categories,
                labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -45, rotateAlways: categories.length > 15 },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: val => formatNumber(Math.round(val)) }
            },
            tooltip: {
                y: { formatter: val => formatNumber(val) + ' ' + info.unit.toLowerCase() },
                theme: 'light'
            },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            markers: { size: values.length <= 31 ? 3 : 0, strokeWidth: 0 }
        });
        trendChart.render();
    }

    // Bar chart
    function updateBarChart(personeller) {
        const info = deptColors[currentDept];
        const top15 = personeller.slice(0, 15).reverse();
        const names = top15.map(p => truncateName(p.adi_soyadi));
        const fullNames = top15.map(p => p.adi_soyadi);
        const values = top15.map(p => parseInt(p.toplam));

        if (barChart) barChart.destroy();

        barChart = new ApexCharts(document.querySelector("#barChart"), {
            series: [{
                name: info.unit,
                data: values
            }],
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false },
                fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '70%',
                    distributed: false,
                    dataLabels: { position: 'top' }
                }
            },
            colors: [info.color],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.2,
                    gradientToColors: [lightenColor(info.color, 30)],
                    opacityFrom: 1,
                    opacityTo: 0.85,
                    stops: [0, 100]
                }
            },
            dataLabels: {
                enabled: true,
                textAnchor: 'start',
                formatter: val => formatNumber(val),
                offsetX: 5,
                style: { fontSize: '11px', fontWeight: 700, colors: ['#475569'] }
            },
            xaxis: {
                categories: names,
                labels: { style: { fontSize: '10px', colors: '#94a3b8' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: { 
                    style: { fontSize: '11px', colors: '#334155', fontWeight: 600 }, 
                    maxWidth: 120
                }
            },
            tooltip: {
                custom: function({ series, seriesIndex, dataPointIndex, w }) {
                    const val = series[seriesIndex][dataPointIndex];
                    return '<div class="p-2 shadow-sm" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;">' +
                        '<div class="fw-bold text-dark mb-1">' + fullNames[dataPointIndex] + '</div>' +
                        '<div class="text-muted small">' + info.unit + ': <span class="fw-bold text-primary">' + formatNumber(val) + '</span></div>' +
                        '</div>';
                }
            },
            grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } }
        });
        barChart.render();
    }

    // Tablo güncelle
    function updateTable(personeller) {
        const info = deptColors[currentDept];
        const maxVal = personeller.length > 0 ? Math.max(...personeller.map(p => parseInt(p.toplam))) : 1;

        if (dataTable) {
            dataTable.destroy();
            $('#performansTable tbody').empty();
        }

        let html = '';
        personeller.forEach((p, idx) => {
            const rank = idx + 1;
            const perc = maxVal > 0 ? Math.round((parseInt(p.toplam) / maxVal) * 100) : 0;
            const medalClass = rank <= 3 ? 'rank-' + rank : 'rank-other';
            const img = p.resim_yolu || 'assets/images/users/user-dummy-img.jpg';

            html += `<tr>
                <td class="text-center">
                    <span class="rank-medal ${medalClass}">${rank}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${img}" class="rounded-circle me-2" style="width:32px; height:32px; object-fit:cover;" 
                             onerror="this.src='assets/images/users/user-dummy-img.jpg'">
                        <div>
                            <h6 class="mb-0 fw-bold" style="font-size:0.85rem;">${escapeHtml(p.adi_soyadi || 'Bilinmeyen')}</h6>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="text-muted small">${escapeHtml(p.departman || '-')}</span>
                </td>
                <td class="text-end fw-bold" style="color: ${info.color}; font-size: 1rem;">
                    ${formatNumber(p.toplam)}
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="perf-bar-bg flex-grow-1">
                            <div class="perf-bar-fill" style="width: ${perc}%; background: ${info.color};"></div>
                        </div>
                        <span class="text-muted small fw-bold" style="min-width:35px; text-align:right;">%${perc}</span>
                    </div>
                </td>
            </tr>`;
        });

        $('#performansTableBody').html(html);

        // DataTable manual init
        if ($.fn.DataTable.isDataTable('#performansTable')) {
            $('#performansTable').DataTable().destroy();
        }

        $('#performansTableBody').html(html);

        let options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        $.extend(true, options, {
            paging: true,
            pageLength: 25,
            ordering: true,
            order: [[3, 'desc']],
            searching: true,
            info: true,
            columnDefs: [
                { orderable: false, targets: [0, 4] }
            ]
        });

        dataTable = $('#performansTable').DataTable(options);
    }

    function updateDonemBilgisi(res) {
        const periodLabels = { gunluk: 'Günlük', aylik: 'Aylık', yillik: 'Yıllık' };
        const info = deptColors[currentDept];
        const start = formatDateFull(res.start_date);
        const end = formatDateFull(res.end_date);
        $('#donemText').html(
            `<strong>${info.label}</strong> departmanı | ${periodLabels[currentPeriod]} rapor | ` +
            `<span class="fw-bold">${start}</span> — <span class="fw-bold">${end}</span>`
        );
    }

    // Yardımcı fonksiyonlar
    function formatNumber(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('tr-TR');
    }

    function formatDateLabel(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        return parts[2] + '.' + parts[1];
    }

    function formatDateFull(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        const months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
        return parseInt(parts[2]) + ' ' + months[parseInt(parts[1])-1] + ' ' + parts[0];
    }

    function truncateName(name) {
        if (!name) return '';
        return name.length > 18 ? name.substring(0, 16) + '...' : name;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function lightenColor(color, percent) {
        const num = parseInt(color.replace('#', ''), 16);
        const amt = Math.round(2.55 * percent);
        const R = Math.min(255, (num >> 16) + amt);
        const G = Math.min(255, ((num >> 8) & 0x00FF) + amt);
        const B = Math.min(255, (num & 0x0000FF) + amt);
        return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
    }

    // Excel export
    $('#btnExcelExport').on('click', function() {
        if (!dataTable || !dataTable.data().any()) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Dışa aktarılacak veri bulunamadı.' });
            return;
        }

        const info = deptColors[currentDept];
        let html = '<html><head><meta charset="utf-8"></head><body><table border="1">';
        html += '<tr><th>Sıra</th><th>Personel</th><th>Departman</th><th>Toplam</th><th>Yüzde</th></tr>';

        let sira = 1;
        dataTable.rows({ filter: 'applied' }).every(function() {
            const node = this.node();
            const ad = $(node).find('td:eq(1) h6').text().trim();
            const dept = $(node).find('td:eq(2)').text().trim();
            const toplam = $(node).find('td:eq(3)').text().trim();
            const yuzde = $(node).find('td:eq(4) .small').text().trim();
            html += `<tr><td>${sira++}</td><td>${ad}</td><td>${dept}</td><td>${toplam}</td><td>${yuzde}</td></tr>`;
        });

        html += '</table></body></html>';
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Performans_Raporu_${info.label.replace(/ /g, '_')}_${currentDate}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });

    // İlk yükleme
    loadData();
});
</script>
