<?php
/**
 * Araç Performans Raporu
 * Araç bazlı yakıt, KM ve servis performans karşılaştırma sayfası
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;

$thisYear = date('Y');
$yearOptions = [];
for ($year = 2025; $year <= (int) $thisYear; $year++) {
    $yearOptions[(string) $year] = (string) $year;
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Araç Takip";
    $subtitle = "Performans";
    $title = "Araç Performans Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Filtre Barı -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <!-- Dönem Seçimi -->
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small fw-bold me-1"><i class="bx bx-calendar me-1"></i>Dönem:</span>
                            <div class="btn-group btn-group-sm" role="group" id="periodGroup">
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="gunluk">Günlük</button>
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="haftalik">Haftalık</button>
                                <button type="button" class="btn btn-primary period-btn active" data-period="aylik">Aylık</button>
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="yillik">Yıllık</button>
                            </div>
                        </div>

                        <!-- Tarih Seçici -->
                        <div class="d-flex flex-wrap align-items-end gap-2">
                            <div class="filter-field" id="singleDateWrapper">
                                <?php echo Form::FormFloatInput("text", "tarihSecici", "", "Tarih", "Seçiniz", "calendar", "form-control form-control-sm", false, null, "off"); ?>
                            </div>
                            <div class="filter-field" id="yearSelectWrapper" style="display:none;">
                                <?php echo Form::FormSelect2("yilSecici", $yearOptions, $thisYear, "Yıl", "calendar", "key", "", "form-select form-select-sm select2", false, "width:100%"); ?>
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
        <!-- En Çok Yakıt Yakan -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #e74a3b !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(231, 74, 59, 0.1);">
                            <i class="bx bx-gas-pump fs-5" style="color: #e74a3b;"></i>
                        </div>
                        <span class="badge bg-danger-subtle text-danger fw-bold" style="font-size:0.6rem;">EN ÇOK</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">EN ÇOK YAKIT YAKAN</p>
                    <h6 class="mb-0 fw-bold" style="color: #e74a3b; font-size: 0.95rem;" id="kpiEnCokYakit">-</h6>
                    <small class="text-muted" id="kpiEnCokYakitSub"></small>
                </div>
            </div>
        </div>

        <!-- En Az Yakıt Yakan -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #34c38f !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(52, 195, 143, 0.1);">
                            <i class="bx bx-leaf fs-5" style="color: #34c38f;"></i>
                        </div>
                        <span class="badge bg-success-subtle text-success fw-bold" style="font-size:0.6rem;">EN AZ</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">EN AZ YAKIT YAKAN</p>
                    <h6 class="mb-0 fw-bold" style="color: #34c38f; font-size: 0.95rem;" id="kpiEnAzYakit">-</h6>
                    <small class="text-muted" id="kpiEnAzYakitSub"></small>
                </div>
            </div>
        </div>

        <!-- En Çok KM Yapan -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #556ee6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(85, 110, 230, 0.1);">
                            <i class="bx bx-tachometer fs-5" style="color: #556ee6;"></i>
                        </div>
                        <span class="badge bg-primary-subtle text-primary fw-bold" style="font-size:0.6rem;">EN ÇOK</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">EN ÇOK KM YAPAN</p>
                    <h6 class="mb-0 fw-bold" style="color: #556ee6; font-size: 0.95rem;" id="kpiEnCokKm">-</h6>
                    <small class="text-muted" id="kpiEnCokKmSub"></small>
                </div>
            </div>
        </div>

        <!-- En Az KM Yapan -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #0ea5e9 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(14, 165, 233, 0.1);">
                            <i class="bx bx-walk fs-5" style="color: #0ea5e9;"></i>
                        </div>
                        <span class="badge bg-info-subtle text-info fw-bold" style="font-size:0.6rem;">EN AZ</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">EN AZ KM YAPAN</p>
                    <h6 class="mb-0 fw-bold" style="color: #0ea5e9; font-size: 0.95rem;" id="kpiEnAzKm">-</h6>
                    <small class="text-muted" id="kpiEnAzKmSub"></small>
                </div>
            </div>
        </div>

        <!-- En Çok Servise Giden -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #f1b44c !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(241, 180, 76, 0.1);">
                            <i class="bx bx-wrench fs-5" style="color: #f1b44c;"></i>
                        </div>
                        <span class="badge bg-warning-subtle text-warning fw-bold" style="font-size:0.6rem;">SERVİS</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">EN ÇOK SERVİSE GİDEN</p>
                    <h6 class="mb-0 fw-bold" style="color: #f1b44c; font-size: 0.95rem;" id="kpiEnCokServis">-</h6>
                    <small class="text-muted" id="kpiEnCokServisSub"></small>
                </div>
            </div>
        </div>

        <!-- Toplam Maliyet -->
        <div class="col-md-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 animate-card"
                style="border-radius: 12px; border-bottom: 3px solid #8b5cf6 !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kpi-icon-box" style="background: rgba(139, 92, 246, 0.1);">
                            <i class="bx bx-wallet fs-5" style="color: #8b5cf6;"></i>
                        </div>
                        <span class="badge bg-light text-muted fw-bold" style="font-size:0.6rem;">TOPLAM</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7; font-size:0.7rem;">TOPLAM MALİYET</p>
                    <h5 class="mb-0 fw-bold" style="color: #8b5cf6;" id="kpiToplamMaliyet">0 ₺</h5>
                    <small class="text-muted" id="kpiToplamMaliyetSub"></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafikler -->
    <div class="row g-3 mb-4">
        <!-- Yakıt Tüketim Trendi -->
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-line-chart me-1 text-muted"></i>Yakıt & KM Trendi
                        </h6>
                        <span class="badge bg-soft-primary text-primary" id="trendPeriodLabel">Aylık</span>
                    </div>
                    <div id="trendChart" style="min-height: 340px;"></div>
                </div>
            </div>
        </div>

        <!-- En Çok Yakıt Harcayan Top 10 -->
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-bar-chart me-1 text-muted"></i>En Çok Yakıt Yakan Top 10
                        </h6>
                        <span class="badge bg-soft-danger text-danger" id="barChartLabel">Litre</span>
                    </div>
                    <div id="barChart" style="min-height: 340px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Araç Performans Sıralama Tablosu -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                            <i class="bx bx-list-ol me-1 text-muted"></i>Araç Performans Detayları
                        </h6>
                        <button class="btn btn-sm btn-outline-success" id="btnExcelExport">
                            <i class="bx bx-file me-1"></i>Excel'e Aktar
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="performansTable" class="table table-hover table-bordered w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px;" class="text-center">#</th>
                                    <th>Plaka / Araç (Sürücü)</th>
                                    <th class="text-end">Yakıt (L)</th>
                                    <th class="text-end">Yakıt Maliyeti</th>
                                    <th class="text-end">Toplam KM</th>
                                    <th class="text-center">Servis Sayısı</th>
                                    <th class="text-end">Servis Maliyeti</th>
                                    <th class="text-end">L/100 KM</th>
                                    <th class="text-center" style="width:180px;">Yakıt Tüketimi</th>
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
    .kpi-icon-box {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .period-btn {
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.3rem 0.7rem;
        border-radius: 6px !important;
    }

    .filter-field {
        min-width: 180px;
    }

    .filter-field .form-floating-custom {
        margin-bottom: 0;
    }

    #btnFiltrele {
        height: 58px;
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
    .animate-card:nth-child(2) { animation-delay: 0.06s; }
    .animate-card:nth-child(3) { animation-delay: 0.12s; }
    .animate-card:nth-child(4) { animation-delay: 0.18s; }
    .animate-card:nth-child(5) { animation-delay: 0.24s; }
    .animate-card:nth-child(6) { animation-delay: 0.30s; }

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
        background-color: rgba(231, 74, 59, 0.04) !important;
    }

    #loadingOverlay {
        backdrop-filter: blur(2px);
    }
</style>

<script>
$(document).ready(function() {
    let currentPeriod = 'aylik';
    let currentYear = '<?= date("Y") ?>';
    let trendChart = null;
    let barChart = null;
    let dataTable = null;
    let fpSingle = null;

    const turkishMonths = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];

    // =============================================
    // FLATPICKR AYARLARI
    // =============================================
    function initSingleDatePicker() {
        if (fpSingle) fpSingle.destroy();

        let options = {
            locale: 'tr',
            allowInput: true,
            defaultDate: new Date(),
            plugins: []
        };

        if (currentPeriod === 'aylik') {
            options.plugins.push(new monthSelectPlugin({
                shorthand: false,
                dateFormat: "F Y",
                altFormat: "F Y",
                theme: "light"
            }));
        } else if (currentPeriod === 'haftalik') {
            options.plugins.push(new weekSelect({}));
            options.onChange = function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const firstDate = selectedDates[0];
                    const lastDate = new Date(firstDate.getTime() + (6 * 24 * 60 * 60 * 1000));
                    instance.input.value = instance.formatDate(firstDate, "d.m.Y") + " - " + instance.formatDate(lastDate, "d.m.Y");
                }
            };
        } else if (currentPeriod === 'gunluk') {
            options.dateFormat = "d.m.Y";
        }

        fpSingle = flatpickr('#tarihSecici', options);
    }

    function toggleDateMode() {
        const isYearly = currentPeriod === 'yillik';
        $('#singleDateWrapper').toggle(!isYearly);
        $('#yearSelectWrapper').toggle(isYearly);
    }

    // Select2
    if ($.fn.select2) {
        $('#yilSecici').select2({ width: '100%', minimumResultsForSearch: Infinity });
    }

    $('#yilSecici').on('change', function() {
        currentYear = String($(this).val() || new Date().getFullYear());
    });

    // İlk init
    initSingleDatePicker();
    toggleDateMode();

    // =============================================
    // DÖNEM DEĞİŞTİR
    // =============================================
    $('#periodGroup').on('click', '.period-btn', function() {
        $('.period-btn').removeClass('btn-primary active').addClass('btn-outline-primary');
        $(this).removeClass('btn-outline-primary').addClass('btn-primary active');
        currentPeriod = $(this).data('period');
        toggleDateMode();
        if (currentPeriod !== 'yillik') initSingleDatePicker();
        loadData();
    });

    // Filtrele
    $('#btnFiltrele').on('click', function() {
        loadData();
    });

    // =============================================
    // VERİ YÜKLEME
    // =============================================
    function getDateRange() {
        let baslangic, bitis;

        if (currentPeriod === 'yillik') {
            baslangic = currentYear + '-01-01';
            bitis = currentYear + '-12-31';
        } else if (fpSingle && fpSingle.selectedDates.length > 0) {
            const selDate = fpSingle.selectedDates[0];
            const y = selDate.getFullYear();
            const m = selDate.getMonth();
            const d = selDate.getDate();

            if (currentPeriod === 'aylik') {
                baslangic = `${y}-${String(m + 1).padStart(2, '0')}-01`;
                const lastDay = new Date(y, m + 1, 0).getDate();
                bitis = `${y}-${String(m + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
            } else if (currentPeriod === 'haftalik') {
                // Pazartesiye çek (Flatpickr weekSelect genelde pazar-cumartesi veya pazartesi-pazar)
                // Ama modelde BETWEEN kullandığımız için ilk seçilen (genelde haftanın başı) başlangıçtır
                const start = new Date(selDate.getTime());
                const end = new Date(selDate.getTime() + (6 * 24 * 60 * 60 * 1000));
                
                baslangic = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;
                bitis = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
            } else if (currentPeriod === 'gunluk') {
                baslangic = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                bitis = baslangic;
            }
        } else {
            const now = new Date();
            const y = now.getFullYear();
            const m = now.getMonth();
            baslangic = `${y}-${String(m + 1).padStart(2, '0')}-01`;
            const lastDay = new Date(y, m + 1, 0).getDate();
            bitis = `${y}-${String(m + 1).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        }

        return { baslangic, bitis };
    }

    function loadData() {
        const range = getDateRange();
        showLoading();

        $.ajax({
            url: 'views/arac-takip/api.php',
            type: 'GET',
            data: {
                action: 'arac-performans',
                baslangic: range.baslangic,
                bitis: range.bitis
            },
            dataType: 'json',
            success: function(res) {
                hideLoading();
                if (res.status === 'success') {
                    updateKPIs(res.summary);
                    updateTrendChart(res.yakit_trend, res.km_trend);
                    updateBarChart(res.araclar);
                    updateTable(res.araclar);
                    updateDonemBilgisi(res.baslangic, res.bitis);
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

    function showLoading() { $('#loadingOverlay').css('display', 'flex'); }
    function hideLoading() { $('#loadingOverlay').hide(); }

    // =============================================
    // KPI GÜNCELLEME
    // =============================================
    function updateKPIs(summary) {
        // En Çok Yakıt
        if (summary.en_cok_yakit) {
            $('#kpiEnCokYakit').text(summary.en_cok_yakit.plaka);
            $('#kpiEnCokYakitSub').text(formatNumber(summary.en_cok_yakit.toplam_litre) + ' Litre');
        } else {
            $('#kpiEnCokYakit').text('-');
            $('#kpiEnCokYakitSub').text('');
        }

        // En Az Yakıt
        if (summary.en_az_yakit) {
            $('#kpiEnAzYakit').text(summary.en_az_yakit.plaka);
            $('#kpiEnAzYakitSub').text(formatNumber(summary.en_az_yakit.toplam_litre) + ' Litre');
        } else {
            $('#kpiEnAzYakit').text('-');
            $('#kpiEnAzYakitSub').text('');
        }

        // En Çok KM
        if (summary.en_cok_km) {
            $('#kpiEnCokKm').text(summary.en_cok_km.plaka);
            $('#kpiEnCokKmSub').text(formatNumber(summary.en_cok_km.toplam_km) + ' KM');
        } else {
            $('#kpiEnCokKm').text('-');
            $('#kpiEnCokKmSub').text('');
        }

        // En Az KM
        if (summary.en_az_km) {
            $('#kpiEnAzKm').text(summary.en_az_km.plaka);
            $('#kpiEnAzKmSub').text(formatNumber(summary.en_az_km.toplam_km) + ' KM');
        } else {
            $('#kpiEnAzKm').text('-');
            $('#kpiEnAzKmSub').text('');
        }

        // En Çok Servis
        if (summary.en_cok_servis) {
            $('#kpiEnCokServis').text(summary.en_cok_servis.plaka);
            $('#kpiEnCokServisSub').text(summary.en_cok_servis.servis_sayisi + ' Sefer');
        } else {
            $('#kpiEnCokServis').text('-');
            $('#kpiEnCokServisSub').text('');
        }

        // Toplam Maliyet
        $('#kpiToplamMaliyet').text(formatMoney(summary.toplam_maliyet) + ' ₺');
        const yakitM = formatMoney(summary.toplam_yakit_maliyet);
        const servisM = formatMoney(summary.toplam_servis_maliyet);
        $('#kpiToplamMaliyetSub').html(`Yakıt: ${yakitM}₺ | Servis: ${servisM}₺`);
    }

    // =============================================
    // TREND GRAFİĞİ
    // =============================================
    function updateTrendChart(yakitTrend, kmTrend) {
        const yakitCats = yakitTrend.map(d => formatMonthLabel(d.ay));
        const yakitVals = yakitTrend.map(d => parseFloat(d.toplam_litre));
        const kmCats = kmTrend.map(d => formatMonthLabel(d.ay));
        const kmVals = kmTrend.map(d => parseFloat(d.toplam_km));

        // Tüm ayları birleştir
        const allMonths = [...new Set([...yakitTrend.map(d => d.ay), ...kmTrend.map(d => d.ay)])].sort();
        const categories = allMonths.map(m => formatMonthLabel(m));

        const yakitData = allMonths.map(m => {
            const item = yakitTrend.find(d => d.ay === m);
            return item ? parseFloat(item.toplam_litre) : 0;
        });
        const kmData = allMonths.map(m => {
            const item = kmTrend.find(d => d.ay === m);
            return item ? parseFloat(item.toplam_km) : 0;
        });

        if (trendChart) trendChart.destroy();

        trendChart = new ApexCharts(document.querySelector("#trendChart"), {
            series: [
                { name: 'Yakıt (L)', data: yakitData },
                { name: 'KM', data: kmData }
            ],
            chart: {
                type: 'area',
                height: 340,
                toolbar: { show: true, tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true } },
                fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 600 }
            },
            colors: ['#e74a3b', '#556ee6'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            stroke: { curve: 'smooth', width: 2.5 },
            dataLabels: { enabled: false },
            xaxis: {
                categories: categories,
                labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -45, rotateAlways: categories.length > 12 },
                axisBorder: { show: false },
                axisTicks: { show: false },
                tickAmount: categories.length > 20 ? 10 : undefined
            },
            yaxis: [
                {
                    title: { text: 'Yakıt (L)', style: { fontSize: '11px', color: '#e74a3b' } },
                    labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: val => formatNumber(Math.round(val)) }
                },
                {
                    opposite: true,
                    title: { text: 'KM', style: { fontSize: '11px', color: '#556ee6' } },
                    labels: { style: { fontSize: '11px', colors: '#94a3b8' }, formatter: val => formatNumber(Math.round(val)) }
                }
            ],
            tooltip: {
                shared: true,
                theme: 'light'
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '12px',
                fontWeight: 600
            },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            markers: { size: yakitData.length <= 12 ? 4 : 0, strokeWidth: 0 }
        });
        trendChart.render();
    }

    // =============================================
    // BAR CHART
    // =============================================
    function updateBarChart(araclar) {
        // Yakıta göre sırala, top 10
        const sorted = [...araclar].filter(a => a.toplam_litre > 0).sort((a, b) => b.toplam_litre - a.toplam_litre).slice(0, 10).reverse();
        const names = sorted.map(a => {
            if (a.surucu) return [a.plaka, a.surucu];
            return a.plaka;
        });
        const values = sorted.map(a => parseFloat(a.toplam_litre));
        const fullNames = sorted.map(a => {
            let n = `${a.plaka}`;
            if (a.surucu) n += ` (${a.surucu})`;
            n += ` - ${a.marka || ''} ${a.model || ''}`;
            return n;
        });

        if (barChart) barChart.destroy();

        barChart = new ApexCharts(document.querySelector("#barChart"), {
            series: [{ name: 'Litre', data: values }],
            chart: {
                type: 'bar',
                height: 340,
                toolbar: { show: false },
                fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    borderRadius: 4,
                    barHeight: '65%',
                    distributed: false,
                    dataLabels: { position: 'top' }
                }
            },
            colors: ['#e74a3b'],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.2,
                    gradientToColors: ['#f5a5a0'],
                    opacityFrom: 1,
                    opacityTo: 0.85,
                    stops: [0, 100]
                }
            },
            dataLabels: {
                enabled: true,
                textAnchor: 'start',
                formatter: val => formatNumber(val) + ' L',
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
                    maxWidth: 300,
                    formatter: function(val) {
                        if (Array.isArray(val)) {
                            // Plakayı büyük/bold, ismi small yapamaz mıyız? 
                            // ApexCharts SVG içinde formatlamada sınırlı. \n kullanıyor array'de.
                            return val;
                        }
                        return val;
                    }
                }
            },
            tooltip: {
                custom: function({ series, seriesIndex, dataPointIndex }) {
                    const val = series[seriesIndex][dataPointIndex];
                    return '<div class="p-2 shadow-sm" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px;">' +
                        '<div class="fw-bold text-dark mb-1">' + fullNames[dataPointIndex] + '</div>' +
                        '<div class="text-muted small">Yakıt: <span class="fw-bold text-danger">' + formatNumber(val) + ' L</span></div>' +
                        '</div>';
                }
            },
            grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } }
        });
        barChart.render();
    }

    // =============================================
    // TABLO GÜNCELLEME
    // =============================================
    function updateTable(araclar) {
        // Toplam yakıta göre sırala
        const sorted = [...araclar].sort((a, b) => b.toplam_litre - a.toplam_litre);
        const maxLitre = sorted.length > 0 ? Math.max(...sorted.map(a => a.toplam_litre)) : 1;

        if (dataTable) {
            dataTable.destroy();
            $('#performansTable tbody').empty();
        }

        let html = '';
        sorted.forEach((a, idx) => {
            const rank = idx + 1;
            const perc = maxLitre > 0 ? Math.round((a.toplam_litre / maxLitre) * 100) : 0;
            const medalClass = rank <= 3 ? 'rank-' + rank : 'rank-other';
            const aracLabel = `${a.marka || ''} ${a.model || ''}`.trim() || '-';
            const surucuLabel = a.surucu ? ` <span class="badge bg-soft-info text-info ms-1" style="font-size:0.7rem;">${escapeHtml(a.surucu)}</span>` : '';
            
            // L/100 KM Hesapla
            const l100km = (a.toplam_km > 0) ? (a.toplam_litre / a.toplam_km) * 100 : 0;
            const l100kmLabel = l100km > 0 ? formatNumber(l100km.toFixed(2)) : '0';

            html += `<tr>
                <td class="text-center">
                    <span class="rank-medal ${medalClass}">${rank}</span>
                </td>
                <td>
                    <div>
                        <div class="d-flex align-items-center">
                            <h6 class="mb-0 fw-bold" style="font-size:0.85rem;">${escapeHtml(a.plaka)}</h6>
                            ${surucuLabel}
                        </div>
                        <small class="text-muted">${escapeHtml(aracLabel)}</small>
                    </div>
                </td>
                <td class="text-end fw-bold" style="color: #e74a3b;">
                    ${formatNumber(a.toplam_litre)}
                </td>
                <td class="text-end">
                    ${formatMoney(a.yakit_maliyet)} ₺
                </td>
                <td class="text-end fw-bold" style="color: #556ee6;">
                    ${formatNumber(a.toplam_km)}
                </td>
                <td class="text-center">
                    <span class="badge ${a.servis_sayisi > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted'}">${a.servis_sayisi}</span>
                </td>
                <td class="text-end">
                    ${formatMoney(a.servis_maliyet)} ₺
                </td>
                <td class="text-end fw-bold">
                    ${l100kmLabel}
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="perf-bar-bg flex-grow-1">
                            <div class="perf-bar-fill" style="width: ${perc}%; background: linear-gradient(90deg, #e74a3b, #f5a5a0);"></div>
                        </div>
                        <span class="text-muted small fw-bold" style="min-width:35px; text-align:right;">%${perc}</span>
                    </div>
                </td>
            </tr>`;
        });

        $('#performansTableBody').html(html);

        // DataTable
        if ($.fn.DataTable.isDataTable('#performansTable')) {
            $('#performansTable').DataTable().destroy();
        }
        $('#performansTableBody').html(html);

        let options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        $.extend(true, options, {
            paging: true,
            pageLength: 25,
            ordering: true,
            order: [[2, 'desc']],
            searching: true,
            info: true,
            columnDefs: [
                { orderable: false, targets: [0, 7] }
            ]
        });

        dataTable = $('#performansTable').DataTable(options);
    }

    // =============================================
    // DÖNEM BİLGİSİ
    // =============================================
    function updateDonemBilgisi(baslangic, bitis) {
        const periodLabels = { gunluk: 'Günlük', haftalik: 'Haftalık', aylik: 'Aylık', yillik: 'Yıllık' };
        const start = formatDateFull(baslangic);
        const end = formatDateFull(bitis);
        
        let label = `<strong>Araç Performans</strong> | ${periodLabels[currentPeriod] || 'Aylık'} rapor | `;
        if (baslangic === bitis) {
            label += `<span class="fw-bold">${start}</span>`;
        } else {
            label += `<span class="fw-bold">${start}</span> — <span class="fw-bold">${end}</span>`;
        }
        
        $('#donemText').html(label);
        $('#trendPeriodLabel').text(periodLabels[currentPeriod] || 'Aylık');
    }

    // =============================================
    // YARDIMCI FONKSİYONLAR
    // =============================================
    function formatNumber(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('tr-TR');
    }

    function formatMoney(n) {
        if (n === null || n === undefined) return '0,00';
        return Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatMonthLabel(monthStr) {
        if (!monthStr) return '';
        const parts = monthStr.split('-');
        return turkishMonths[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    function formatDateFull(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        return parseInt(parts[2]) + ' ' + turkishMonths[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // =============================================
    // EXCEL EXPORT
    // =============================================
    $('#btnExcelExport').on('click', function() {
        if (!dataTable || !dataTable.data().any()) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Dışa aktarılacak veri bulunamadı.' });
            return;
        }

        let html = '<html><head><meta charset="utf-8"></head><body><table border="1">';
        html += '<tr><th>Sıra</th><th>Plaka</th><th>Sürücü</th><th>Yakıt (L)</th><th>Yakıt Maliyeti</th><th>Toplam KM</th><th>L/100 KM</th><th>Servis Sayısı</th><th>Servis Maliyeti</th></tr>';

        let sira = 1;
        dataTable.rows({ filter: 'applied' }).every(function() {
            const node = this.node();
            const plaka = $(node).find('td:eq(1) h6').text().trim();
            const surucu = $(node).find('td:eq(1) .badge').text().trim() || '-';
            const yakit = $(node).find('td:eq(2)').text().trim();
            const yakitMaliyet = $(node).find('td:eq(3)').text().trim();
            const km = $(node).find('td:eq(4)').text().trim();
            const l100km = $(node).find('td:eq(7)').text().trim();
            const servisSayi = $(node).find('td:eq(5)').text().trim();
            const servisMaliyet = $(node).find('td:eq(6)').text().trim();
            html += `<tr><td>${sira++}</td><td>${plaka}</td><td>${surucu}</td><td>${yakit}</td><td>${yakitMaliyet}</td><td>${km}</td><td>${l100km}</td><td>${servisSayi}</td><td>${servisMaliyet}</td></tr>`;
        });

        html += '</table></body></html>';
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const range = getDateRange();
        a.download = `Arac_Performans_${range.baslangic}_${range.bitis}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });

    // İlk yükleme
    loadData();
});
</script>
