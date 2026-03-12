<?php
/**
 * Araç Performans Raporu
 * Araç bazlı yakıt, KM ve servis performans karşılaştırma sayfası
 * Versiyon: 1.0.6 (Diagnostic)
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;

$thisYear = date('Y');
$yearOptions = [];
for ($year = 2025; $year <= (int) $thisYear; $year++) {
    $yearOptions[(string) $year] = (string) $year;
}

$activeTab = $_GET['tab'] ?? 'genel-bakis';
?>

<div class="container-fluid">
    <div class="alert alert-info py-1 px-2 mb-2 d-flex justify-content-between align-items-center" style="font-size: 0.7rem; border-radius: 8px;">
        <span><i class="bx bx-bug-alt me-1"></i>Diagnostic: Araç Performans v1.0.6 | Aktif Sekme: <strong><?= $activeTab ?></strong></span>
        <span class="badge bg-info text-white"><?= date('H:i:s') ?></span>
    </div>
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
                             <!-- Araç Seçimi -->
                            <div id="topAracFilterWrapper" style="display:none;">
                                <div class="filter-field" style="min-width: 220px;">
                                    <?php echo Form::FormSelect2("aracSecici", ["" => "Araç Seçiniz..."], "", "Araç", "truck", "key", "", "form-select form-select-sm select2", false, "width:100%", 'data-placeholder="Araç Seçiniz..."'); ?>
                                </div>
                            </div>
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

    <!-- Tab Navigasyonu -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills nav-justified bg-white p-1 shadow-sm mb-3" role="tablist" style="border-radius: 12px;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'genel-bakis' ? 'active' : '' ?> fw-bold py-2" id="genel-tab" data-bs-toggle="tab" data-bs-target="#genel-bakis" type="button" role="tab">
                        <i class="bx bx-pie-chart-alt-2 me-2"></i>Genel Performans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'arac-analiz' ? 'active' : '' ?> fw-bold py-2" id="arac-tab" data-bs-toggle="tab" data-bs-target="#arac-analiz" type="button" role="tab">
                        <i class="bx bx-truck me-2"></i>Araç Analizi
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content">
        <!-- TAB 1: GENEL BAKIS -->
        <div class="tab-pane <?= $activeTab === 'genel-bakis' ? 'show active' : '' ?>" id="genel-bakis" role="tabpanel" style="position: relative;">
            <span class="diagnostic-anchor badge bg-dark text-white p-1" data-tab="genel-bakis" style="position: absolute; top: 2px; right: 2px; font-size: 0.5rem; z-index: 1000; opacity: 0.4;">Anchor: Genel</span>

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
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold text-dark mb-0" style="font-size: 0.95rem;">
                                <i class="bx bx-bar-chart me-1 text-muted"></i>
                                <span id="barChartTitle">En Yüksek Yakıt Ortalaması</span>
                            </h6>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="chartMetricSelector" style="width: 150px;">
                                <option value="avg">Yakıt Ortalaması</option>
                                <option value="km">En Çok KM</option>
                                <option value="fuel">En Çok Yakıt</option>
                            </select>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-danger active" id="btnSortHigh">En Yüksek</button>
                                <button type="button" class="btn btn-outline-success" id="btnSortLow">En Düşük</button>
                            </div>
                            <span class="badge bg-soft-secondary" id="barChartLabel">L/100 KM</span>
                        </div>
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
                                    <th class="text-end">L/100 KM</th>
                                    <th class="text-center">Servis Sayısı</th>
                                    <th class="text-end">Servis Maliyeti</th>
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

    <!-- TAB 2: ARAÇ ANALİZ -->
    <div class="tab-pane <?= $activeTab === 'arac-analiz' ? 'show active' : '' ?>" id="arac-analiz" role="tabpanel" style="position: relative;">
        <span class="diagnostic-anchor badge bg-dark text-white p-1" data-tab="arac-analiz" style="position: absolute; top: 2px; right: 2px; font-size: 0.5rem; z-index: 1000; opacity: 0.4;">Anchor: Analiz</span>
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px; border-top: 3px solid #556ee6;">
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.95rem;">
                                <i class="bx bx-bar-chart-alt-2 me-2 text-muted fs-5"></i>Araç Detay Analizi
                            </h6>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Araç KPI Kartları -->
            <div id="aracKPIs" class="row g-3 mb-4" style="display:none;">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 3px solid #e74a3b;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">YAKIT TÜKETİMİ</p>
                            <h4 class="mb-0 fw-bold" id="akp_yakit">0 L</h4>
                            <small id="akp_yakit_diff"></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 3px solid #556ee6;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">YAPILAN KM</p>
                            <h4 class="mb-0 fw-bold" id="akp_km">0 KM</h4>
                            <small id="akp_km_diff"></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 3px solid #f1b44c;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">SERVİS SAYISI</p>
                            <h4 class="mb-0 fw-bold" id="akp_servis">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 3px solid #8b5cf6;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">TOPLAM MALİYET</p>
                            <h4 class="mb-0 fw-bold" id="akp_maliyet">0 ₺</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Araç Grafikleri -->
            <div class="row g-3">
                <div class="col-xl-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="card-body p-3">
                            <div id="aracChartContainer" style="display:none;">
                                <div id="aracDetailChart" style="min-height: 400px;"></div>
                            </div>
                            <div id="aracEmptyState" class="text-center py-5">
                                <div class="mb-3">
                                    <i class="bx bx-truck text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
                                </div>
                                <h5 class="text-muted fw-bold">Analiz için araç seçiniz</h5>
                                <p class="text-muted small">Araç seçerek detaylı performans verilerini görüntüleyebilirsiniz.</p>
                            </div>
                        </div>
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
    console.log("Araç Performans JS Yüklendi. Versiyon: 1.0.6");
    let currentTab = '<?= $activeTab ?>';
    let currentPeriod = 'aylik';
    let currentYear = '<?= date("Y") ?>';
    let trendChart = null;
    let barChart = null;
    let aracDetailChart = null; // Eksik bildirim eklendi
    let dataTable = null;
    let fpSingle = null;
    let barChartSort = 'desc'; // 'desc' or 'asc'
    let currentMetric = 'avg'; // 'avg', 'km', 'fuel'
    let lastAraclar = [];


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
            options.mode = "range";
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

    $('#btnSortHigh').on('click', function() {
        barChartSort = 'desc';
        $(this).addClass('active').siblings().removeClass('active');
        updateBarChartTitle();
        updateBarChart(lastAraclar);
    });

    $('#btnSortLow').on('click', function() {
        barChartSort = 'asc';
        $(this).addClass('active').siblings().removeClass('active');
        updateBarChartTitle();
        updateBarChart(lastAraclar);
    });

    $('#chartMetricSelector').on('change', function() {
        currentMetric = $(this).val();
        updateBarChartTitle();
        updateBarChart(lastAraclar);
    });

    function updateBarChartTitle() {
        const sortText = barChartSort === 'desc' ? 'En Yüksek' : 'En Düşük';
        let metricText = '';
        let labelText = '';

        if (currentMetric === 'avg') {
            metricText = 'Yakıt Ortalaması';
            labelText = 'L/100 KM';
        } else if (currentMetric === 'km') {
            metricText = 'Yapılan KM';
            labelText = 'KM';
        } else if (currentMetric === 'fuel') {
            metricText = 'Harcanan Yakıt';
            labelText = 'Litre';
        }

        $('#barChartTitle').text(`${sortText} ${metricText}`);
        $('#barChartLabel').text(labelText);
    }

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
                if (fpSingle.selectedDates.length === 2) {
                    const start = fpSingle.selectedDates[0];
                    const end = fpSingle.selectedDates[1];
                    baslangic = `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}-${String(start.getDate()).padStart(2, '0')}`;
                    bitis = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
                } else {
                    baslangic = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    bitis = baslangic;
                }
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

    // =============================================
    // SEKME YÖNETİMİ
    // =============================================
    
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        currentTab = $(e.target).attr('data-bs-target').replace('#', '');
        console.log("Sekme değiştirildi:", currentTab);
        
        // Üst araç filtresini göster/gizle
        if (currentTab === 'arac-analiz') {
            $('#topAracFilterWrapper').fadeIn();
        } else {
            $('#topAracFilterWrapper').fadeOut();
        }

        loadData();
    });

    // Araç listesini doldur (Filtreleme için)
    function populateAracSelect(araclar) {
        const $select = $('#aracSecici');
        if ($select.children('option').length > 1) return;
        
        araclar.forEach(a => {
            $select.append(`<option value="${a.arac_id}">${a.plaka} - ${a.marka} ${a.model}</option>`);
        });

        // Eğer araç analiz sekmesindeysek ve henüz araç seçilmediyse ilk aracı otomatik seç
        if (currentTab === 'arac-analiz' && !$select.val() && araclar.length > 0) {
            console.log("İlk araç otomatik seçiliyor...");
            $select.val(araclar[0].arac_id).trigger('change');
        }
    }

    // =============================================
    // VERİ YÜKLEME (ANA FONKSİYON)
    // =============================================
    function loadData() {
        console.log("Veri yükleniyor. Mevcut sekme:", currentTab);
        
        // Araç analizi sekmesindeysek ve araç listesi henüz gelmediyse, listeyi çekmek için genel bakışı tetikleyici ile çalıştır
        if (currentTab === 'arac-analiz' && lastAraclar.length === 0) {
            console.log("Araç listesi eksik, genel bakış üzerinden liste çekiliyor...");
            loadGenelBakis(true); // true = liste geldikten sonra analiz yükle
            return;
        }

        if (currentTab === 'genel-bakis') {
            loadGenelBakis(false);
        } else if (currentTab === 'arac-analiz') {
            loadAracAnaliz();
        }
    }

    function loadGenelBakis(triggerAracAnalizAfter = false) {
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
                    lastAraclar = res.araclar;
                    updateKPIs(res.summary);
                    updateTrendChart(res.yakit_trend, res.km_trend);
                    updateBarChart(res.araclar);
                    updateTable(res.araclar);
                    updateDonemBilgisi(res.baslangic, res.bitis);
                    populateAracSelect(res.araclar);

                    if (triggerAracAnalizAfter && currentTab === 'arac-analiz') {
                        loadAracAnaliz();
                    }
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

    function loadAracAnaliz() {
        const arac_id = $('#aracSecici').val();
        if (!arac_id) {
            $('#aracKPIs, #aracChartContainer').hide();
            $('#aracEmptyState').show();
            return;
        }

        const range = getDateRange();
        showLoading();

        $.ajax({
            url: 'views/arac-takip/api.php',
            type: 'GET',
            data: {
                action: 'get-arac-analiz',
                arac_id: arac_id,
                baslangic: range.baslangic,
                bitis: range.bitis
            },
            dataType: 'json',
            success: function(res) {
                console.log("Araç Analiz Yanıtı Geldi:", res.status);
                hideLoading();
                if (res.status === 'success') {
                    $('#aracEmptyState').hide();
                    $('#aracKPIs, #aracChartContainer').fadeIn();
                    updateAracDetailUI(res);
                } else {
                    Swal.fire('Hata', res.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("Araç Analiz AJAX Hatası:", status, error);
                hideLoading();
                Swal.fire('Hata', 'Veri yükleme hatası.', 'error');
            }
        });
    }

    function showLoading() { $('#loadingOverlay').css('display', 'flex'); }
    function hideLoading() { $('#loadingOverlay').hide(); }

    // =============================================
    // KPI GÜNCELLEME
    // =============================================
    function updateKPIs(summary) {
        if (!summary) {
            console.error("Summary verisi eksik!");
            return;
        }
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
        if (!araclar || araclar.length === 0) return;

        // Metrik bazlı değer hazırla
        let mapped = araclar.map(a => {
            let val = 0;
            if (currentMetric === 'avg') {
                val = a.toplam_km > 0 ? (a.toplam_litre / a.toplam_km) * 100 : 0;
            } else if (currentMetric === 'km') {
                val = parseFloat(a.toplam_km) || 0;
            } else if (currentMetric === 'fuel') {
                val = parseFloat(a.toplam_litre) || 0;
            }
            return { ...a, displayVal: val };
        }).filter(a => a.displayVal > 0);

        if (barChartSort === 'desc') {
            mapped.sort((a, b) => b.displayVal - a.displayVal);
        } else {
            mapped.sort((a, b) => a.displayVal - b.displayVal);
        }

        const sorted = mapped.slice(0, 10);

        const names = sorted.map(a => {
            if (a.surucu) return [a.plaka, a.surucu];
            return a.plaka;
        });
        const values = sorted.map(a => parseFloat(a.displayVal.toFixed(2)));
        const fullNames = sorted.map(a => {
            let n = `${a.plaka}`;
            if (a.surucu) n += ` (${a.surucu})`;
            n += ` - ${a.marka || ''} ${a.model || ''}`;
            return n;
        });

        if (barChart) barChart.destroy();

        const chartColor = barChartSort === 'desc' ? (currentMetric === 'km' ? '#556ee6' : '#e74a3b') : '#1cc88a';
        const gradientColor = barChartSort === 'desc' ? (currentMetric === 'km' ? '#a5b4fc' : '#f5a5a0') : '#87e0be';

        let unitLabel = 'L/100 KM';
        if (currentMetric === 'km') unitLabel = 'KM';
        else if (currentMetric === 'fuel') unitLabel = 'Litre';

        barChart = new ApexCharts(document.querySelector("#barChart"), {
            series: [{ name: 'L/100 KM', data: values }],
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
            colors: [chartColor],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'horizontal',
                    shadeIntensity: 0.2,
                    gradientToColors: [gradientColor],
                    opacityFrom: 1,
                    opacityTo: 0.85,
                    stops: [0, 100]
                }
            },

            dataLabels: {
                enabled: true,
                textAnchor: 'start',
                formatter: val => formatNumber(val) + ' ' + unitLabel,
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
                        '<div class="text-muted small">Değer: <span class="fw-bold" style="color:'+chartColor+'">' + formatNumber(val) + ' ' + unitLabel + '</span></div>' +
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
        // Calculate L/100km first and add it to the array objects
        const araclarWithL100 = araclar.map(a => {
            const l100km = (a.toplam_km > 0) ? (a.toplam_litre / a.toplam_km) * 100 : 0;
            return { ...a, l100km: l100km };
        });

        // Sort by L/100 KM descending
        const sorted = [...araclarWithL100].sort((a, b) => b.l100km - a.l100km);
        
        // Find max L/100 KM for the percentage bar, only considering those with > 0 so the bar scale is meaningful
        const maxL100 = sorted.length > 0 ? Math.max(...sorted.map(a => a.l100km)) : 1;

        if (dataTable) {
            dataTable.destroy();
            $('#performansTable tbody').empty();
        }

        let html = '';
        sorted.forEach((a, idx) => {
            const rank = idx + 1;
            const perc = maxL100 > 0 ? Math.round((a.l100km / maxL100) * 100) : 0;
            const medalClass = rank <= 3 ? 'rank-' + rank : 'rank-other';
            const aracLabel = `${a.marka || ''} ${a.model || ''}`.trim() || '-';
            const surucuLabel = a.surucu ? ` <span class="badge bg-soft-info text-info ms-1" style="font-size:0.7rem;">${escapeHtml(a.surucu)}</span>` : '';
            
            const l100kmLabel = a.l100km > 0 ? formatNumber(a.l100km.toFixed(2)) : '0';

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
                <td class="text-end fw-bold" style="color: #e74a3b;" data-sort="${a.toplam_litre}">
                    ${formatNumber(a.toplam_litre)}
                </td>
                <td class="text-end" data-sort="${a.yakit_maliyet}">
                    ${formatMoney(a.yakit_maliyet)} ₺
                </td>
                <td class="text-end fw-bold" style="color: #556ee6;" data-sort="${a.toplam_km}">
                    ${formatNumber(a.toplam_km)}
                </td>
                <td class="text-end fw-bold" data-sort="${a.l100km}">
                    ${l100kmLabel}
                </td>
                <td class="text-center" data-sort="${a.servis_sayisi}">
                    <span class="badge ${a.servis_sayisi > 0 ? 'bg-warning-subtle text-warning' : 'bg-light text-muted'}">${a.servis_sayisi}</span>
                </td>
                <td class="text-end" data-sort="${a.servis_maliyet}">
                    ${formatMoney(a.servis_maliyet)} ₺
                </td>
                <td data-sort="${perc}">
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
            order: [[5, 'desc']], // Column index 5 is L/100 KM
            searching: true,
            info: true,
            columnDefs: [
                { type: 'num', targets: [2, 3, 4, 5, 6, 7] }
            ]
        });

        dataTable = $('#performansTable').DataTable(options);
    }

    $('#aracSecici').on('change', function() {
        if (currentTab === 'arac-analiz') loadData();
    });

    // =============================================
    // ARAÇ DETAY UI GÜNCELLEME
    // =============================================
    function updateAracDetailUI(res) {
        const cur = res.current;
        const prev = res.prev;

        $('#akp_yakit').text(formatNumber(cur.yakit) + ' L');
        $('#akp_km').text(formatNumber(cur.km) + ' KM');
        $('#akp_servis').text(cur.servis_sayisi);
        $('#akp_maliyet').text(formatMoney(cur.yakit_maliyet + cur.servis_maliyet) + ' ₺');

        // Karşılaştırma Badge'leri
        updateDiffBadge($('#akp_yakit_diff'), cur.yakit, prev.yakit);
        updateDiffBadge($('#akp_km_diff'), cur.km, prev.km);

        if (res.charts && res.charts.yakit && res.charts.km) {
            renderAracDetailChart(res.charts.yakit, res.charts.km);
        } else {
            console.warn("Chart verisi eksik veya hatalı:", res.charts);
            $('#aracChartContainer').hide();
        }
    }

    function updateDiffBadge($el, cur, prev) {
        if (prev <= 0) {
            $el.hide();
            return;
        }
        const diff = ((cur - prev) / prev) * 100;
        const isUp = diff > 0;
        
        // Yakıt artışı kötü (red), KM artışı iyi (green) - Basit mantık
        let colorClass = 'text-success';
        if (isUp && $el.attr('id') === 'akp_yakit_diff') colorClass = 'text-danger';
        if (!isUp && $el.attr('id') === 'akp_km_diff') colorClass = 'text-danger';

        const icon = isUp ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';
        
        $el.html(`<span class="${colorClass} small fw-bold">
            <i class="bx ${icon}"></i> %${Math.abs(diff).toFixed(1)}
        </span> <span class="text-muted" style="font-size:0.65rem;">önceki döneme göre</span>`).show();
    }

    function renderAracDetailChart(yakitData, kmData) {
        if (!yakitData || !kmData) return;
        
        const dates = [...new Set([...yakitData.map(d => d.tarih), ...kmData.map(d => d.tarih)])].sort();
        if (dates.length === 0) {
            $('#aracChartContainer').hide();
            $('#aracEmptyState').show().html('<div class="py-5 text-center"><i class="bx bx-info-circle fs-1 text-muted opacity-50 mb-3"></i><h5 class="text-muted">Bu dönem için veri bulunamadı</h5><p class="text-muted small">Seçilen tarihler arasında yakıt veya KM kaydı mevcut değil.</p></div>');
            return;
        }
        
        const yakitSeries = dates.map(d => {
            const row = yakitData.find(y => y.tarih === d);
            return row ? parseFloat(row.yakit_miktari) : 0;
        });

        const kmSeries = dates.map(d => {
            const row = kmData.find(k => k.tarih === d);
            return row ? parseFloat(row.yapilan_km) : 0;
        });

        const options = {
            series: [
                { name: 'Yakıt (Litre)', type: 'column', data: yakitSeries },
                { name: 'Yapılan KM', type: 'line', data: kmSeries }
            ],
            chart: { height: 400, type: 'line', toolbar: { show: false }, stacked: false, fontFamily: 'inherit' },
            stroke: { width: [0, 3], curve: 'smooth' },
            colors: ['#e74a3b', '#556ee6'],
            xaxis: { 
                categories: dates.map(d => formatDateFull(d)),
                labels: { style: { fontSize: '10px' } }
            },
            yaxis: [
                { title: { text: "Yakıt (Litre)", style: { color: '#e74a3b' } } },
                { opposite: true, title: { text: "Yapılan KM", style: { color: '#556ee6' } } }
            ],
            legend: { position: 'top' },
            tooltip: { shared: true, intersect: false }
        };

        if (aracDetailChart && typeof aracDetailChart.destroy === 'function') {
            aracDetailChart.destroy();
        }
        aracDetailChart = new ApexCharts(document.querySelector("#aracDetailChart"), options);
        aracDetailChart.render();
    }

    // =============================================
    // FİRMA TREND GRAFİĞİ
    // =============================================

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
    if (currentTab === 'arac-analiz') {
        $('#topAracFilterWrapper').show();
    }
    loadData();
});
</script>
