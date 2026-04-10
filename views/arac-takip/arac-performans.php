<?php
/**
 * Araç Performans Raporu
 * Araç bazlı yakıt, KM ve servis performans karşılaştırma sayfası
 * Versiyon: 1.0.7 (Diagnostic)
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;

$thisYear = date('Y');
$yearOptions = [];
for ($year = 2025; $year <= (int) $thisYear; $year++) {
    $yearOptions[(string) $year] = (string) $year;
}

$activeTab = $_GET['tab'] ?? 'pane-performans';
$activeSubTab = $_GET['sub'] ?? 'genel-bakis';

if ($activeTab === 'genel-bakis' || $activeTab === 'arac-analiz') {
    $activeSubTab = $activeTab;
    $activeTab = 'pane-performans';
}

// Dönem ve Araç Listesi Hazırlığı
$aracModel = new \App\Model\AracModel();
$aktifAraclar = $aracModel->getAktifAraclar();
$aracOptions = ['' => 'Tüm Araçlar'];
foreach ($aktifAraclar as $a) {
    $aracOptions[$a->id] = $a->plaka . ' - ' . $a->marka . ' ' . $a->model;
}

$periodOptions = [];
$defaultPeriods = [];
for ($i = 1; $i <= 12; $i++) {
    $dateObj = strtotime("-$i month");
    $val = date('Ym', $dateObj);
    $text = date('Y / n', $dateObj);
    $periodOptions[$val] = $text;
    if ($i <= 3) $defaultPeriods[] = $val;
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Araç Takip";
    $subtitle = "Performans";
    $title = "Araç Performans Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Ana Sekme Navigasyonu -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-tabs nav-tabs-custom pt-1 px-1 mb-3" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'pane-performans' ? 'active' : '' ?>" id="main-performans-tab" data-bs-toggle="tab" data-bs-target="#pane-performans" type="button" role="tab">
                        <i class="bx bx-chart me-2"></i>Performans Raporu
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'pane-karsilastirma' ? 'active' : '' ?>" id="main-karsilastirma-tab" data-bs-toggle="tab" data-bs-target="#pane-karsilastirma" type="button" role="tab">
                        <i class="bx bx-git-compare me-2"></i>Karşılaştırma
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="mainTabContent">
        <!-- ANA TAB 1: PERFORMANS RAPORU -->
        <div class="tab-pane <?= $activeTab === 'pane-performans' ? 'show active' : '' ?>" id="pane-performans" role="tabpanel">
            
            <!-- Mevcut Filtre Barı (Sadece bu tabda geçerli) -->
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

            <!-- Sub Tab Navigasyonu -->
            <div class="row mb-3">
                <div class="col-12">
                    <ul class="nav nav-pills nav-pills-custom bg-white p-2 shadow-sm rounded-3 mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $activeSubTab === 'genel-bakis' ? 'active' : '' ?> px-4" id="genel-tab" data-bs-toggle="tab" data-bs-target="#genel-bakis" type="button" role="tab">
                                <i class="bx bx-pie-chart-alt-2 me-2"></i>Genel Performans
                            </button>
                        </li>
                        <li class="nav-item ms-2" role="presentation">
                            <button class="nav-link <?= $activeSubTab === 'arac-analiz' ? 'active' : '' ?> px-4" id="arac-tab" data-bs-toggle="tab" data-bs-target="#arac-analiz" type="button" role="tab">
                                <i class="bx bx-car me-2"></i>Araç Analizi
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="tab-content" id="performansTabContent">
        <!-- TAB 1: GENEL BAKIS -->
        <div class="tab-pane <?= $activeSubTab === 'genel-bakis' ? 'show active' : '' ?>" id="genel-bakis" role="tabpanel" style="position: relative;">
            <span class="diagnostic-anchor badge bg-dark text-white p-1" data-tab="genel-bakis" style="position: absolute; top: 2px; right: 2px; font-size: 0.5rem; z-index: 1000; opacity: 0.4;">Anchor: Genel</span>

    <!-- KPI Kartları (Puantaj Stili) -->
    <div class="row g-3 mb-4 mt-1" id="kpiCards">
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #ef4444 !important;">
                <div class="card-body">
                    <span class="ghost-label">EN YÜKSEK</span>
                    <p class="card-title-text text-danger">En Çok Yakıt Yakan</p>
                    <h5 class="card-value py-1" id="kpiEnCokYakit">-</h5>
                    <small class="text-muted" id="kpiEnCokYakitSub"></small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #10b981 !important;">
                <div class="card-body">
                    <span class="ghost-label">EKONOMİK</span>
                    <p class="card-title-text text-success">En Az Yakıt Yakan</p>
                    <h5 class="card-value py-1" id="kpiEnAzYakit">-</h5>
                    <small class="text-muted" id="kpiEnAzYakitSub"></small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #3b82f6 !important;">
                <div class="card-body">
                    <span class="ghost-label">MESAFE</span>
                    <p class="card-title-text text-primary">En Çok KM Yapan</p>
                    <h5 class="card-value py-1" id="kpiEnCokKm">-</h5>
                    <small class="text-muted" id="kpiEnCokKmSub"></small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #0ea5e9 !important;">
                <div class="card-body">
                    <span class="ghost-label">MİNİMUM</span>
                    <p class="card-title-text text-info">En Az KM Yapan</p>
                    <h5 class="card-value py-1" id="kpiEnAzKm">-</h5>
                    <small class="text-muted" id="kpiEnAzKmSub"></small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <span class="ghost-label">SERVİS</span>
                    <p class="card-title-text text-warning">En Çok Servis</p>
                    <h5 class="card-value py-1" id="kpiEnCokServis">-</h5>
                    <small class="text-muted" id="kpiEnCokServisSub"></small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2">
            <div class="card summary-card h-100" style="border-bottom: 4px solid #1e293b !important;">
                <div class="card-body">
                    <span class="ghost-label">TOPLAM</span>
                    <p class="card-title-text text-dark">Toplam Maliyet</p>
                    <h5 class="card-value py-1 fw-bold" style="font-size: 1.1rem !important;" id="kpiToplamMaliyet">0 ₺</h5>
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
                </div> <!-- /arac-analiz -->
            </div> <!-- /performansTabContent -->
        </div> <!-- /pane-performans -->

        <!-- ANA TAB 2: KARŞILAŞTIRMA -->
        <div class="tab-pane <?= $activeTab === 'pane-karsilastirma' ? 'show active' : '' ?>" id="pane-karsilastirma" role="tabpanel">
            
            <!-- FİLTRE -->
            <div class="row mb-3 mt-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="card-body p-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <?= Form::FormMultipleSelect2('compDonemler', $periodOptions, $defaultPeriods, 'Dönem Seçimi', 'calendar', 'key', '', 'form-select select2-comp') ?>
                                </div>
                                <div class="col-md-4">
                                    <?= Form::FormSelect2('compAraclar', $aracOptions, '', 'Araç Seçimi', 'truck', 'key', '', 'form-select select2-comp') ?>
                                </div>

                                <div class="col-md-4 d-flex align-items-center justify-content-md-end">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary comp-quick-period" data-months="3">Son 3</button>
                                            <button type="button" class="btn btn-outline-primary comp-quick-period" data-months="6">Son 6</button>
                                        </div>
                                        <div class="ms-1 vr" style="height: 20px;"></div>
                                        <button type="button" class="btn btn-sm btn-dark px-3 rounded-pill shadow-sm" id="btnCompFiltrele">
                                            <i class="bx bx-search-alt me-1"></i>Karşılaştır
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KARŞILAŞTIRMA ÖZET KARTLARI (Puantaj Stili) -->
            <div class="row g-3 mb-4" id="compSummaryCards" style="display: none;">
                <div class="col-md-3">
                    <div class="card summary-card h-100" style="border-bottom: 4px solid #3b82f6 !important;">
                        <div class="card-body">
                            <span class="ghost-label">ARAÇ</span>
                            <p class="card-title-text text-primary">Araç Sayısı</p>
                            <h3 class="card-value" id="compTotalArac">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100" style="border-bottom: 4px solid #10b981 !important;">
                        <div class="card-body">
                            <span class="ghost-label">YAKIT</span>
                            <p class="card-title-text text-success">Toplam Yakıt (L)</p>
                            <h3 class="card-value" id="compTotalFuel">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100" style="border-bottom: 4px solid #f59e0b !important;">
                        <div class="card-body">
                            <span class="ghost-label">MESAFE</span>
                            <p class="card-title-text text-warning">Toplam Mesafe (KM)</p>
                            <h3 class="card-value" id="compTotalKm">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card summary-card h-100" style="border-bottom: 4px solid #ef4444 !important;">
                        <div class="card-body">
                            <span class="ghost-label">MALİYET</span>
                            <p class="card-title-text text-danger">Toplam Maliyet</p>
                            <h3 class="card-value" id="compTotalCost">0 ₺</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aksiyonlar ve Görünüm -->
            <div class="row mb-3" id="compActions" style="display: none;">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check v-mode" name="vMode" id="vmList" checked value="list">
                        <label class="btn btn-outline-primary px-3" for="vmList"><i class="bx bx-list-ul me-1"></i> Liste</label>
                        <input type="radio" class="btn-check v-mode" name="vMode" id="vmChart" value="chart">
                        <label class="btn btn-outline-primary px-3" for="vmChart"><i class="bx bx-bar-chart-alt-2 me-1"></i> Grafik</label>
                    </div>
                    <button class="btn btn-sm btn-outline-success" id="btnCompExcel"><i class="mdi mdi-file-excel me-1"></i>Excel'e Aktar</button>
                </div>
            </div>

            <!-- Liste Görünümü -->
            <!-- LİSTE / GRAFİK SEÇİCİ VE ARAMA -->
            <div class="row mb-3 align-items-center" id="compViewControls" style="display: none;">
                <div class="col-auto">
                    <div class="btn-group btn-group-sm rounded-pill p-1 bg-white shadow-sm border">
                        <button type="button" class="btn btn-primary px-3 rounded-pill" id="btnCompListView"><i class="bx bx-list-ul me-1"></i>Liste</button>
                        <button type="button" class="btn btn-outline-primary px-3 rounded-pill border-0" id="btnCompChartView"><i class="bx bx-chart me-1"></i>Grafik</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm shadow-sm" style="border-radius: 8px; overflow: hidden;">
                        <span class="input-group-text bg-white border-end-0"><i class="bx bx-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="compAracAra" placeholder="Plaka veya araç ara...">
                    </div>
                </div>
                <div class="col text-end d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info px-3 rounded-pill shadow-sm" id="btnCompFullScreen">
                        <i class="bx bx-fullscreen me-1"></i>Tam Ekran
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success px-3 rounded-pill shadow-sm" id="btnCompExcel">
                        <i class="bx bx-file me-1"></i>Excel'e Aktar
                    </button>
                </div>
            </div>

            <div class="row" id="compListSection" style="display: none;">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                        <div class="card-body p-0">
                            <div class="table-responsive" id="compTableWrapper" style="max-height: 550px; overflow: auto; border-radius: 12px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafik Görünümü -->
            <div class="row g-3" id="compChartSection" style="display: none;">
                <div class="col-xl-12">
                    <div class="card border-0 shadow-sm p-3" style="border-radius:12px;">
                        <div id="compGroupedChart" style="min-height: 400px;"></div>
                    </div>
                </div>
            </div>

            <div id="compEmptyState" class="text-center py-5">
                <div class="mb-3"><i class="bx bx-calendar-check text-muted" style="font-size: 5rem; opacity: 0.3;"></i></div>
                <h5 class="text-muted fw-bold">Karşılaştırma için lütfen dönem seçiniz</h5>
            </div>

        </div> <!-- /pane-karsilastirma -->
    </div> <!-- /mainTabContent -->

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
    /* Puantaj Tarzı Premium Sekme Tasarımı */
    .nav-tabs-custom {
        background: transparent !important;
        border: none !important;
        padding: 0 !important;
        gap: 8px;
    }

    .nav-tabs-custom .nav-link {
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        background: #fff !important;
        color: #4b5563 !important;
        font-weight: 700;
        padding: 0.6rem 1.4rem !important;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        font-size: 0.82rem;
        margin-bottom: 0 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
    }

    .nav-tabs-custom .nav-link i { font-size: 1.1rem; }

    .nav-tabs-custom .nav-link.active {
        background: #1e293b !important;
        color: #fff !important;
        border-color: #1e293b !important;
        box-shadow: 0 4px 10px rgba(30, 41, 59, 0.15) !important;
    }

    /* Puantaj Tarzı Gelişmiş Kolon Filtresi POPUP */
    .col-filter-popup {
        position: fixed;
        width: 220px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15), 0 5px 10px rgba(0,0,0,0.05);
        z-index: 9999;
        padding: 12px;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .col-filter-popup.show { display: block; animation: fadeInDown 0.2s ease-out; }

    .filter-popup-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .col-filter-popup select, .col-filter-popup input {
        width: 100%;
        margin-bottom: 8px;
        font-size: 0.8rem;
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
    }

    .filter-popup-actions {
        display: flex;
        gap: 8px;
        margin-top: 4px;
    }

    .btn-filter-apply {
        flex: 1;
        background: #1e293b;
        color: #fff;
        border: none;
        padding: 6px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        transition: all 0.2s;
    }

    .btn-filter-apply:hover { background: #0f172a; }

    .btn-filter-clear {
        background: #f1f5f9;
        color: #64748b;
        border: none;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    /* Tablo Başlık Filtre Butonları */
    .col-filter-btn {
        padding: 2px 5px;
        border-radius: 4px;
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 4px;
    }

    .col-filter-btn:hover { background: #e2e8f0; color: #1e293b; }
    .col-filter-active { color: #3b82f6 !important; background: rgba(59, 130, 246, 0.1) !important; }

    .filter-active-dot {
        width: 6px;
        height: 6px;
        background: #3b82f6;
        border-radius: 50%;
        display: inline-block;
        margin-left: -8px;
        margin-top: -12px;
        border: 1px solid #fff;
        position: relative;
        z-index: 5;
    }

    /* Puantaj Tarzı Özet Kartları */
    .summary-card {
        position: relative;
        overflow: hidden;
        border: none !important;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        background: #fff;
        transition: transform 0.2s;
    }

    .summary-card:hover { transform: translateY(-3px); }

    .summary-card .card-body { padding: 1.25rem !important; }

    .summary-card .ghost-label {
        position: absolute;
        top: 8px;
        right: 12px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #94a3b8;
        opacity: 0.3;
        letter-spacing: 1px;
    }

    .summary-card .card-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0;
    }

    .summary-card .card-title-text {
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 2px;
    }

    /* Tablo & Sütun Renkleri */
    #compResultTable th.comp-period-header {
        background-color: #f8fafc !important;
        color: #334155 !important;
        border-bottom: 1px solid #e2e8f0 !important;
        font-size: 0.75rem !important;
        font-weight: 800 !important;
    }

    #compResultTable th.col-litre { color: #f59e0b !important; }
    #compResultTable th.col-km { color: #3b82f6 !important; }

    .text-litre { color: #d97706 !important; font-weight: 600; }
    .text-km { color: #2563eb !important; font-weight: 600; }

    .plaka-badge {
        background: #f8fafc;
        color: #1e293b;
        padding: 3px 8px;
        border-radius: 6px;
        font-weight: 800;
        font-size: 0.8rem;
        border: 1px solid #e2e8f0;
        display: inline-block;
        white-space: nowrap;
    }

    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

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

    /* Karşılaştırma Tablosu & Select2 Stilleri */
    #compResultTable { border-collapse: separate; border-spacing: 0; }
    #compResultTable th { padding: 12px 8px; }
    #compResultTable th.sticky-header-top { position: sticky; top: 0; z-index: 20; background-color: #f1f5f9; border-bottom: 2px solid #e2e8f0; }
    #compResultTable th:first-child, #compResultTable td:first-child { position: sticky; left: 0; z-index: 15; background-color: #fff; border-right: 2px solid #edf2f7; box-shadow: 2px 0 5px rgba(0,0,0,0.02); }
    #compResultTable th:first-child { z-index: 25; background-color: #f1f5f9 !important; }
    #compResultTable tr:hover td:first-child { background-color: #f8fafc; }
    .comp-period-header { background: #556ee6 !important; color: white !important; font-weight: 600; text-align: center; border-bottom: none !important; font-size: 0.85rem; letter-spacing: 1px; }
    .comp-sub-header { background-color: #f8fafc !important; font-size: 0.65rem !important; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: 800; border-top: 1px solid #e2e8f0 !important; }
    .plaka-badge { background: #1e293b; color: #fff; padding: 2px 8px; border-radius: 4px; font-family: 'Monaco', 'Consolas', monospace; font-size: 0.85rem; letter-spacing: 0.5px; }
    
    .select2-container--default .select2-selection--multiple { border: 1px solid #ced4da; border-radius: 8px; min-height: 42px; padding: 2px 6px; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #334155; border: none; color: #fff; border-radius: 6px; padding: 4px 10px; margin-top: 6px; font-weight: 500; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color: #94a3b8; margin-right: 8px; border-right: 1px solid #475569; padding-right: 4px; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { color: #fff; background: none; }

    #loadingOverlay {
        backdrop-filter: blur(2px);
    }
</style>

<script>
$(document).ready(function() {
    console.log("Araç Performans JS Yüklendi. Versiyon: 1.0.6");
    let currentTab = '<?= ($activeTab === "pane-performans") ? $activeSubTab : $activeTab ?>';
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
    
    // Global tab tracking - ensuring we track the LOADABLE sub-tabs inside the performance pane
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const targetId = $(e.target).attr('data-bs-target').replace('#', '');
        
        // If switching to the main performance pane, use the currently active sub-pill
        if (targetId === 'pane-performans') {
            currentTab = $('.nav-pills-custom .nav-link.active').data('bs-target').replace('#', '') || 'genel-bakis';
        } else if (targetId === 'genel-bakis' || targetId === 'arac-analiz' || targetId === 'pane-karsilastirma') {
            currentTab = targetId;
        } else {
            // Keep currentTab as is for other purely visual tabs if any
            return;
        }

        console.log("Aktif sekme güncellendi:", currentTab);
        
        // Toggle vehicle filter visibility
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
        console.log("loadData tetiklendi. Sekme:", currentTab);

        // Failsafe: currentTab 'pane-performans' ise aktif sub-tab'ı bul
        if (currentTab === 'pane-performans') {
            currentTab = $('.nav-pills-custom .nav-link.active').data('bs-target')?.replace('#', '') || 'genel-bakis';
        }
        
        if (currentTab === 'pane-karsilastirma') {
            initComparisonTab();
            hideLoading();
            return;
        }

        if (currentTab === 'genel-bakis') {
            loadGenelBakis(false);
        } else if (currentTab === 'arac-analiz') {
            loadAracAnaliz();
        } else {
            console.warn("Bilinmeyen sekme veya yükleme gerektirmeyen alan:", currentTab);
            hideLoading();
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
                console.log("Dashboard AJAX Response:", res);
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
        if (!Array.isArray(yakitTrend) || !Array.isArray(kmTrend)) {
            console.error("Dashboard: Trend verisi array değil!");
            return;
        }
        const yakitCats = yakitTrend.map(d => d.ay ? formatMonthLabel(d.ay) : '');
        const yakitVals = yakitTrend.map(d => parseFloat(d.toplam_litre) || 0);
        const kmCats = kmTrend.map(d => d.ay ? formatMonthLabel(d.ay) : '');
        const kmVals = kmTrend.map(d => parseFloat(d.toplam_km) || 0);

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
        if (!Array.isArray(yakitData) || !Array.isArray(kmData)) {
            console.error("Dashboard: Chart verisi array değil!");
            return;
        }
        
        const dates = [...new Set([...yakitData.map(d => d.tarih), ...kmData.map(d => d.tarih)])].sort();
        if (dates.length === 0) {
            $('#aracChartContainer').hide();
            $('#aracEmptyState').show().html('<div class="py-5 text-center"><i class="bx bx-info-circle fs-1 text-muted opacity-50 mb-3"></i><h5 class="text-muted">Bu dönem için veri bulunamadı</h5><p class="text-muted small">Seçilen tarihler arasında yakıt veya KM kaydı mevcut değil.</p></div>');
            return;
        }
        
        const yakitSeriesData = dates.map(d => {
            const row = yakitData.find(y => y.tarih === d);
            return row ? parseFloat(row.yakit_miktari) : 0;
        });

        const kmSeriesData = dates.map(d => {
            const row = kmData.find(k => k.tarih === d);
            return row ? parseFloat(row.yapilan_km) : 0;
        });

        const options = {
            series: [
                { name: 'Yakıt (Litre)', type: 'column', data: yakitSeriesData },
                { name: 'Yapılan KM', type: 'line', data: kmSeriesData }
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

    // Eski redundant event listenerlar kaldırıldı. Tüm tab yönetimi yukarıdaki tek bir handler'a toplandı.

    // =============================================
    // KARŞILAŞTIRMA SEKME LOJİĞİ
    // =============================================
    let compTableDataTable = null;
    let compFuelChart = null;
    let compKmChart = null;

    function initComparisonTab() {
        if ($.fn.select2) {
            $('#compDonemler').select2({
                placeholder: "Başlamak için dönem seçin",
                allowClear: true,
                width: '100%'
            });
            $('#compAraclar').select2({
                placeholder: "Seçili araca özel filtrele (Tümü için boş bırakın)",
                allowClear: true,
                width: '100%'
            });
        }
    }

    // Quick Period Seçimi
    $('.comp-quick-period').on('click', function() {
        const months = parseInt($(this).data('months'));
        const values = [];
        const now = new Date();
        for (let i = 1; i <= months; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            values.push(d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'));
        }
        $('#compDonemler').val(values).trigger('change');
    });

    $('#btnCompFiltrele').on('click', function() {
        const selected = $('#compDonemler').val();
        if (!selected || selected.length === 0) {
            Swal.fire('Uyarı', 'Lütfen en az bir dönem seçiniz.', 'warning');
            return;
        }
        loadComparisonData(selected);
    });

    function loadComparisonData(periods) {
        const aracId = $('#compAraclar').val();
        showLoading();
        console.log("Karşılaştırma Verisi İsteniyor. Dönemler:", periods, "Araç ID:", aracId);

        $.ajax({
            url: 'views/arac-takip/api.php',
            type: 'POST',
            data: {
                action: 'arac-karsilastirma',
                donemler: periods,
                arac_id: aracId
            },
            dataType: 'json',
            success: function(res) {
                hideLoading();
                console.log("Karşılaştırma Verisi Geldi:", res);

                if (res.status === 'success') {
                    if (res.data.length === 0) {
                        $('#compTableWrapper').html('<div class="alert alert-info py-3 text-center">Seçilen dönemlerde araç verisi bulunamadı.</div>');
                        $('#compEmptyState').hide();
                        $('#compSummaryCards, #compActions').fadeIn();
                        return;
                    }

                    $('#compEmptyState').hide();
                    $('#compSummaryCards, #compActions, #compListSection').fadeIn();
                    _compSummaryData = res.summary; // Global store
                    updateCompSummary(res.summary);
                    renderCompTable(res.periods, res.data, res.summary);
                    renderCompCharts(res.periods, res.data, res.summary);
                    
                    // Görünüm moduna göre göster
                    toggleCompView($('input[name="vMode"]:checked').val());
                } else {
                    Swal.fire('Hata', res.message || 'Karşılaştırma verisi yüklenemedi.', 'error');
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                console.error("Karşılaştırma AJAX Hatası:", status, error, xhr.responseText);
                Swal.fire('Hata', 'Sunucu ile bağlantı kurulamadı veya bir sunucu hatası oluştu.', 'error');
            }
        });
    }

    function updateCompSummary(summary) {
        $('#compTotalArac').text(summary.toplam_arac);
        
        let totalFuel = 0, totalKm = 0, totalCost = 0;
        Object.values(summary.donemler).forEach(d => {
            totalFuel += d.toplam_litre;
            totalKm += d.toplam_km;
            totalCost += d.toplam_tutar;
        });

        $('#compTotalFuel').text(formatNumber(Math.round(totalFuel)));
        $('#compTotalKm').text(formatNumber(Math.round(totalKm)));
        $('#compTotalCost').text(formatMoney(totalCost) + ' ₺');
    }

    let _numericFilters = {}; // Key: '{donem}_{field}', Value: { operator, value }
    let _compTableData = [];
    let _compPeriods = [];

    function renderCompTable(periods, data, summary) {
        _compTableData = data;
        _compPeriods = periods;
        
        // Sayısal Filtreleri Uygula
        let filteredData = data.filter(item => {
            for (const key in _numericFilters) {
                const f = _numericFilters[key];
                if (!f || f.value === undefined || f.value === '') continue;

                const parts = key.split('_');
                const field = parts.pop(); // litre, km
                const donem = parts.join('_');
                const val = parseFloat(item.donemler[donem] ? item.donemler[donem][field] : 0) || 0;

                const filterVal = parseFloat(f.value);
                switch (f.operator) {
                    case '>': if (!(val > filterVal)) return false; break;
                    case '<': if (!(val < filterVal)) return false; break;
                    case '>=': if (!(val >= filterVal)) return false; break;
                    case '<=': if (!(val <= filterVal)) return false; break;
                    case '=': if (!(Math.abs(val - filterVal) < 0.01)) return false; break;
                }
            }
            return true;
        });

        let html = `<table class="table table-sm table-hover mb-0" id="compResultTable">
            <thead>
                <tr>
                    <th rowspan="2" class="align-middle sticky-header-top bg-light" style="min-width: 160px; border-bottom: 2px solid #e2e8f0; top: 0; position: sticky; z-index: 30;">
                        <div class="d-flex flex-column gap-1">
                            <span class="fw-bold text-dark" style="font-size: 0.75rem;">Araç / Plaka</span>
                            <div class="input-group input-group-sm mt-1">
                                <span class="input-group-text bg-white border-end-0 py-0"><i class="bx bx-search" style="font-size: 0.7rem;"></i></span>
                                <input type="text" id="compAracAra" class="form-control form-control-sm border-start-0 ps-0" placeholder="Ara..." style="font-size: 0.7rem; height: 24px;">
                            </div>
                        </div>
                    </th>`;
        
        periods.forEach(p => {
            const label = p.substring(0, 4) + ' / ' + p.substring(4, 6);
            html += `<th colspan="2" class="comp-period-header text-center bg-light" style="position: sticky; top: 0; z-index: 20; border-bottom: 1px solid #e2e8f0;">${label}</th>`;
        });
        
        html += `</tr><tr>`;
        periods.forEach(p => {
            const getFilterBtn = (field) => {
                const key = p + '_' + field;
                const isActive = _numericFilters[key] && _numericFilters[key].value !== '';
                const activeClass = isActive ? 'col-filter-active' : '';
                const dot = isActive ? '<span class="filter-active-dot"></span>' : '';
                return `<button type="button" class="col-filter-btn ${activeClass}" data-filter-col="${key}"><i class="bx bx-filter-alt"></i></button>${dot}`;
            };

            html += `<th class="text-center comp-sub-header col-litre bg-light" style="position: sticky; top: 31px; z-index: 20;">
                        <div class="d-flex align-items-center justify-content-center">
                            ${getFilterBtn('litre')} Litre
                        </div>
                     </th>
                     <th class="text-center comp-sub-header col-km bg-light" style="position: sticky; top: 31px; z-index: 20;">
                        <div class="d-flex align-items-center justify-content-center">
                            ${getFilterBtn('km')} KM
                        </div>
                     </th>`;
        });
        html += `</tr></thead><tbody>`;

        if (filteredData.length === 0) {
            html += `<tr><td colspan="${(periods.length * 2) + 1}" class="text-center py-5">Veri bulunamadı.</td></tr>`;
        } else {
            filteredData.forEach(arac => {
                html += `<tr class="arac-row">
                    <td class="bg-white">
                        <div class="d-flex flex-column">
                            <span class="plaka-badge mb-1">${escapeHtml(arac.plaka)}</span>
                            <span class="text-muted" style="font-size:0.65rem;">${escapeHtml(arac.marka)}</span>
                        </div>
                    </td>`;
                
                periods.forEach(p => {
                    const d = arac.donemler[p] || { litre: 0, km: 0 };
                    html += `<td class="text-end border-start text-litre ${d.litre > 0 ? '' : 'text-muted opacity-50'}">${d.litre > 0 ? formatNumber(d.litre) : '-'}</td>
                             <td class="text-end text-km ${d.km > 0 ? '' : 'text-muted opacity-50'}">${d.km > 0 ? formatNumber(d.km) : '-'}</td>`;
                });
                html += `</tr>`;
            });
        }
        html += `</tbody><tfoot class="bg-light fw-bold sticky-bottom" style="bottom: 0; z-index: 10;">
            <tr>
                <td class="bg-light text-primary">GENEL TOPLAM</td>`;
        periods.forEach(p => {
            const s = summary.donemler[p];
            html += `<td class="text-end border-start text-primary">${formatNumber(Math.round(s.toplam_litre))}</td>
                     <td class="text-end text-primary">${formatNumber(Math.round(s.toplam_km))}</td>`;
        });
        html += `</tr></tfoot></table>`;

        $('#compTableWrapper').html(html);
        $('#compAracAra').trigger('keyup');
    }

    let compGroupedChart = null;
    function renderCompCharts(periods, data, summary) {
        const categories = periods.map(p => p.substring(0, 4) + '/' + p.substring(4, 6));
        
        const fuelSeries = periods.map(p => Math.round(summary.donemler[p].toplam_litre));
        const kmSeries = periods.map(p => Math.round(summary.donemler[p].toplam_km));

        const options = {
            series: [{
                name: 'Toplam Yakıt (Litre)',
                data: fuelSeries
            }, {
                name: 'Toplam Mesafe (KM)',
                data: kmSeries
            }],
            chart: {
                type: 'bar',
                height: 400,
                toolbar: { show: true },
                fontFamily: 'inherit'
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 5,
                    dataLabels: { position: 'top' }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: (val) => formatNumber(val),
                offsetY: -20,
                style: { fontSize: '10px', colors: ["#304758"] }
            },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            xaxis: { categories: categories },
            yaxis: { title: { text: 'Miktarlar' } },
            fill: { opacity: 1 },
            colors: ['#e74a3b', '#556ee6'],
            tooltip: {
                y: {
                    formatter: (val) => formatNumber(val)
                }
            },
            title: {
                text: 'Dönemsel Performans Karşılaştırması',
                align: 'center',
                style: { fontSize: '16px', fontWeight: 'bold' }
            },
            legend: { position: 'top', horizontalAlign: 'center' }
        };

        if (compGroupedChart) compGroupedChart.destroy();
        compGroupedChart = new ApexCharts(document.querySelector("#compGroupedChart"), options);
        compGroupedChart.render();
    }

    $('input[name="vMode"]').on('change', function() {
        toggleCompView($(this).val());
    });

    function toggleCompView(mode) {
        if (mode === 'chart') {
            $('#compListSection').hide();
            $('#compChartSection').fadeIn();
        } else {
            $('#compChartSection').hide();
            $('#compListSection').fadeIn();
        }
    }

    $('#btnCompExcel').on('click', function() {
        const table = document.getElementById('compResultTable');
        if (!table) return;
        
        let html = '<html><head><meta charset="utf-8"></head><body>' + table.outerHTML + '</body></html>';
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Arac_Karsilastirma_${new Date().getTime()}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });

    $('#btnCompFullScreen').on('click', function() {
        const elem = document.getElementById('pane-karsilastirma');
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    });

    $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
        const isFS = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
        const $elem = $('#pane-karsilastirma');
        if (isFS) {
            $elem.addClass('bg-light p-4 overflow-auto');
        } else {
            $elem.removeClass('bg-light p-4 overflow-auto');
        }
    });

    // =============================================
    // YARDIMCI FONKSİYONLAR & İLK YÜKLEME
    // =============================================
    function formatNumber(n) {
        if (n === null || n === undefined) return '0';
        return Number(n).toLocaleString('tr-TR');
    }

    function formatMoney(n) {
        if (n === null || n === undefined) return '0,00';
        return Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDateFull(dateStr) {
        if (!dateStr) return '';
        if (dateStr.includes('.')) return dateStr;
        const parts = dateStr.split('-');
        if (parts.length < 3) return dateStr;
        return parseInt(parts[2]) + ' ' + turkishMonths[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    function formatMonthLabel(monthStr) {
        if (!monthStr) return '';
        const parts = monthStr.split('-');
        if (parts.length < 2) return monthStr;
        return turkishMonths[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function showLoading() { $('#loadingOverlay').fadeIn(100); }
    function hideLoading() { $('#loadingOverlay').fadeOut(100); }

    // ======= SAYİSAL FİLTRE POPUP MANTIĞI =======
    let _activeFilterCol = null;

    $('body').append(`
        <div class="col-filter-popup shadow-lg" id="colFilterPopup">
            <div class="filter-popup-title border-bottom pb-2 mb-2">
                <i class="bx bx-filter-alt me-1"></i> <span id="colFilterPopupTitle">Filtrele</span>
            </div>
            <div class="mb-2">
                <label class="small fw-bold text-muted mb-1">Operatör</label>
                <select id="colFilterOperator" class="form-select form-select-sm">
                    <option value="">Seçiniz...</option>
                    <option value=">">Büyüktür ( > )</option>
                    <option value="<">Küçüktür ( < )</option>
                    <option value=">=">Büyük Eşit ( ≥ )</option>
                    <option value="<=">Küçük Eşit ( ≤ )</option>
                    <option value="=">Eşit ( = )</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="small fw-bold text-muted mb-1">Değer</label>
                <input type="number" id="colFilterValue" class="form-control form-control-sm" placeholder="Sayı girin..." step="any">
            </div>
            <div class="filter-popup-actions pt-2 border-top text-end">
                <button type="button" class="btn btn-sm btn-light me-1" id="colFilterClear">Temizle</button>
                <button type="button" class="btn btn-sm btn-primary" id="colFilterApply">Uygula</button>
            </div>
        </div>
    `);

    $(document).on('click', '.col-filter-btn', function(e) {
        e.preventDefault(); e.stopPropagation();
        const colKey = $(this).data('filter-col');
        const popup = $('#colFilterPopup');

        if (_activeFilterCol === colKey && popup.hasClass('show')) {
            popup.removeClass('show'); _activeFilterCol = null; return;
        }

        _activeFilterCol = colKey;
        const pts = colKey.split('_');
        const fld = pts.pop();
        const dnm = pts.join('_');
        $('#colFilterPopupTitle').text((dnm.substring(0,4)+'/'+dnm.substring(4)) + ' - ' + fld.toUpperCase());

        const existing = _numericFilters[colKey];
        if (existing) {
            $('#colFilterOperator').val(existing.operator);
            $('#colFilterValue').val(existing.value);
        } else {
            $('#colFilterOperator').val(''); $('#colFilterValue').val('');
        }

        const rect = this.getBoundingClientRect();
        popup.css({ top: (rect.bottom + window.scrollY + 5) + 'px', left: (rect.left + window.scrollX - 100) + 'px' }).addClass('show');
        setTimeout(() => $('#colFilterOperator').focus(), 50);
    });

    $(document).on('click', '#colFilterApply', function() {
        const op = $('#colFilterOperator').val();
        const val = $('#colFilterValue').val();
        if (val === '' || val === null) delete _numericFilters[_activeFilterCol];
        else _numericFilters[_activeFilterCol] = { operator: op, value: parseFloat(val) };
        $('#colFilterPopup').removeClass('show'); _activeFilterCol = null;
        if (_compTableData) renderCompTable(_compPeriods, _compTableData, _compSummaryData); // summary global lazım olabilir
    });

    $(document).on('click', '#colFilterClear', function() {
        if (_activeFilterCol) delete _numericFilters[_activeFilterCol];
        $('#colFilterPopup').removeClass('show'); _activeFilterCol = null;
        if (_compTableData) renderCompTable(_compPeriods, _compTableData, _compSummaryData);
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#colFilterPopup, .col-filter-btn').length) $('#colFilterPopup').removeClass('show');
    });

    // Global summary storage for re-rendering
    let _compSummaryData = null;

    // Karşılaştırma Arama
    $(document).on('keyup', '#compAracAra', function() {
        var val = $(this).val().toLowerCase();
        $("#compTableWrapper tr.arac-row").filter(function() {
            var text = $(this).find('.plaka-badge').text().toLowerCase() + ' ' + 
                       $(this).find('.text-muted').text().toLowerCase();
            $(this).toggle(text.indexOf(val) > -1);
        });
    });

    // Grafik/Liste Geçişleri
    $(document).on('click', '#btnCompListView', function() {
        $('#compListSection').fadeIn();
        $('#compChartSection').hide();
        $(this).addClass('btn-primary').removeClass('btn-outline-primary');
        $('#btnCompChartView').addClass('btn-outline-primary').removeClass('btn-primary').addClass('border-0');
    });

    $(document).on('click', '#btnCompChartView', function() {
        $('#compChartSection').fadeIn();
        $('#compListSection').hide();
        $(this).addClass('btn-primary').removeClass('btn-outline-primary').removeClass('border-0');
        $('#btnCompListView').addClass('btn-outline-primary').removeClass('btn-primary');
    });

    // İlk yükleme
    if (currentTab === 'pane-karsilastirma') {
        const startupPeriods = $('#compDonemler').val();
        if (startupPeriods && startupPeriods.length > 0) {
            loadComparisonData(startupPeriods);
        }
    }

    if (currentTab === 'arac-analiz') {
        $('#topAracFilterWrapper').show();
    }
    loadData();
});
</script>
