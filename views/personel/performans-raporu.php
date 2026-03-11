<?php
/**
 * Personel Performans Raporu
 * Departman bazlı personel performans karşılaştırma sayfası
 * Kesme Açma / Endeks Okuma / Sayaç Sökme Takma
 */
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Service\Gate;
use App\Helper\Form;

?>

<div class="container-fluid">
    <?php
    $maintitle = "Personel";
    $subtitle = "Performans";
    $title = "Personel Performans Raporu";
    $todayDmy = date('d.m.Y');
    $thisMonthDmy = date('m.Y');
    $thisYear = date('Y');
    $yearOptions = [];
    for ($year = 2025; $year <= (int) $thisYear; $year++) {
        $yearOptions[(string) $year] = (string) $year;
    }
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
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="haftalik">Haftalık</button>
                                <button type="button" class="btn btn-primary period-btn active" data-period="aylik">Aylık</button>
                                <button type="button" class="btn btn-outline-primary period-btn" data-period="yillik">Yıllık</button>
                            </div>
                        </div>

                        <!-- Tarih Seçici -->
                        <div class="d-flex flex-wrap align-items-end gap-2">
                             <!-- Personel Seçimi -->
                        <div id="topPersonelFilterWrapper" style="display:none;">
                            <div class="filter-field" style="min-width: 220px;">
                                <?php echo Form::FormSelect2("kisiselPersonelSelect", ["" => "Personel Seçiniz..."], "", "Personel", "users", "key", "", "form-select form-select-sm select2", false, "width:100%", 'data-placeholder="Personel Seçiniz..."'); ?>
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
            <div class="alert alert-warning" role="alert">
                <h6 class="alert-heading">Dönem Bilgisi</h6>
                <p class="mb-0" id="donemText">Yükleniyor...</p>
            </div>
            </div>    <!-- Tab Navigasyonu -->
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills nav-justified bg-white p-1 shadow-sm mb-3" role="tablist" style="border-radius: 12px;">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold py-2" id="genel-tab" data-bs-toggle="tab" data-bs-target="#genel-bakis" type="button" role="tab">
                        <i class="bx bx-pie-chart-alt-2 me-2"></i>Genel Performans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold py-2" id="personel-tab" data-bs-toggle="tab" data-bs-target="#personel-analiz" type="button" role="tab">
                        <i class="bx bx-user-circle me-2"></i>Personel Analizi
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content">
        <!-- TAB 1: GENEL BAKIS -->
        <div class="tab-pane fade show active" id="genel-bakis" role="tabpanel">
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
                                    <tbody id="performansTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PERSONEL ANALIZ -->
        <div class="tab-pane fade" id="personel-analiz" role="tabpanel">
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px; border-top: 3px solid var(--dept-color, #e74a3b);">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center" style="font-size: 0.95rem;">
                                        <i class="bx bx-bar-chart-alt-2 me-2 text-muted fs-5"></i>Personel Detay Analizi
                                    </h6>
                                </div>

                                <div class="btn-group btn-group-sm d-none" role="group" id="kisiselSiralamaGroup">
                                    <button type="button" class="btn btn-outline-secondary kisisel-sort-btn active" data-sort="tarih" title="Zamana Göre">
                                        <i class="bx bx-time-five"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary kisisel-sort-btn" data-sort="desc" title="En İyiden En Kötüye">
                                        <i class="bx bx-sort-down"></i> En İyi
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary kisisel-sort-btn" data-sort="asc" title="En Kötüden En İyiye">
                                        <i class="bx bx-sort-up"></i> En Kötü
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personel KPI Kartları -->
            <div id="kisiselKPIs" class="row g-3 mb-4" style="display:none;">
                <!-- Toplam İş -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: rgba(var(--dept-rgb, 231, 74, 59), 0.05);">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">TOPLAM İŞ</p>
                            <h4 class="mb-0 fw-bold" id="kp_toplam" style="color: var(--dept-color, #e74a3b);">0</h4>
                        </div>
                    </div>
                </div>
                <!-- Günlük Ortalama -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">GÜNLÜK ORT.</p>
                            <h4 class="mb-0 fw-bold text-primary" id="kp_gunluk_ort">0</h4>
                        </div>
                    </div>
                </div>
                <!-- Aylık Ortalama -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">AYLIK ORT.</p>
                            <h4 class="mb-0 fw-bold text-success" id="kp_aylik_ort">0</h4>
                        </div>
                    </div>
                </div>
                <!-- Sıralama -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; background: rgba(241, 180, 76, 0.05);">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">EKİP SIRALAMASI</p>
                            <h4 class="mb-0 fw-bold" id="kp_siralama" style="color: #f1b44c;">0</h4>
                            <small class="text-muted" id="kp_toplam_personel">/ 0</small>
                        </div>
                    </div>
                </div>
                <!-- En Yüksek Gün -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">EN YÜKSEK GÜN</p>
                            <h4 class="mb-0 fw-bold text-info" id="kp_en_yuksek">0</h4>
                            <small class="text-muted" id="kp_en_yuksek_tarih">-</small>
                        </div>
                    </div>
                </div>
                <!-- En Düşük Gün -->
                <div class="col-md-4 col-xl-2">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body p-3 text-center">
                            <p class="text-muted mb-1 small fw-bold">EN DÜŞÜK GÜN</p>
                            <h4 class="mb-0 fw-bold text-danger" id="kp_en_dusuk">0</h4>
                            <small class="text-muted" id="kp_en_dusuk_tarih">-</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kişisel Grafik -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                        <div class="card-body p-3">
                            <div id="kisiselChartContainer" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <img id="kisiselImg" src="assets/images/users/user-dummy-img.jpg" class="rounded-circle" style="width:40px; height:40px; object-fit:cover; border: 2px solid #eee;">
                                        <div>
                                            <h6 class="mb-0 fw-bold" id="kisiselAd" style="font-size:1rem;">-</h6>
                                            <small class="text-muted fw-bold" id="kisiselDepartman">-</small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-soft-primary text-primary px-3 py-2" style="font-size: 0.85rem;" id="kisiselChartLabel">Performas Grafiği</span>
                                    </div>
                                </div>
                                <div id="kisiselChart" style="min-height: 350px;"></div>
                            </div>

                            <div id="kisiselEmptyState" class="text-center py-5">
                                <div class="mb-3">
                                    <i class="bx bx-user-circle text-muted" style="font-size: 5rem; opacity: 0.3;"></i>
                                </div>
                                <h5 class="text-muted fw-bold">Analiz için personel seçiniz</h5>
                                <p class="text-muted small">Personel seçerek detaylı performans verilerini görüntüleyebilirsiniz.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.73); z-index:9999; display:flex; align-items:center; justify-content:center; backdrop-filter: blur(3px);">
    <div class="text-center">
        <div class="spinner-border text-primary mb-3" role="status" style="width:3.5rem; height:3.5rem; border-width: 0.3rem;">
            <span class="visually-hidden">Yükleniyor...</span>
        </div>
        <h5 class="text-dark fw-bold mb-1">Veriler Hazırlanıyor</h5>
        <p class="text-muted">Lütfen bekleyiniz...</p>
    </div>
</div>

<style>
    :root {
        --dept-color: #e74a3b;
        --dept-rgb: 231, 74, 59;
    }

    .nav-pills .nav-link {
        color: #64748b;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        margin: 0 4px;
    }

    .nav-pills .nav-link.active {
        background: #fff !important;
        color: var(--dept-color, #e74a3b) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(var(--dept-rgb), 0.1);
    }

    .nav-pills .nav-link:not(.active):hover {
        background: rgba(var(--dept-rgb), 0.05);
        color: var(--dept-color);
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
    const todayYmd = '<?= date("Y-m-d") ?>';
    const thisMonthStart = '<?= date("Y-m-01") ?>';
    const thisYearStart = '<?= date("Y-01-01") ?>';
    let currentDate = thisMonthStart;
    let currentYear = '<?= date("Y") ?>';
    let currentEffectivePeriod = 'aylik';
    let trendChart = null;
    let barChart = null;
    let dataTable = null;
    let kisiselChart = null;
    let kisiselSortOrder = 'tarih';
    let currentKisiselData = [];
    let kisiselPeriod = 'aylik';

    // Flatpickr
    let fpSingle = null;
    let fpStart = null;
    let fpEnd = null;
    let fpKisiselSingle = null;
    let fpKisiselRange = null;

    function parseTrDateToYmd(value) {
        if (!value) return '';
        const m = value.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
        if (!m) return '';
        return `${m[3]}-${m[2]}-${m[1]}`;
    }

    function formatYmdToTrDate(value) {
        if (!value) return '';
        const p = value.split('-');
        if (p.length !== 3) return '';
        return `${p[2]}.${p[1]}.${p[0]}`;
    }

    function ymdFromDateObj(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // Unused range pickers removed

    // Flatpickr declaration moved to top

    function initSingleDatePicker(mode) {
        if (fpSingle) {
            fpSingle.destroy();
        }

        let options = {
            locale: 'tr',
            allowInput: true,
            onChange: function(selectedDates, dateStr) {
                // Her değişimde yükleme yapmıyoruz, Filtrele butonuna basılınca veya period değişince
            }
        };

        if (mode === 'aylik') {
            options.defaultDate = new Date(); // Varsayılan bu ay
            options.plugins = [
                new monthSelectPlugin({
                    shorthand: false,
                    dateFormat: "F Y",
                    altFormat: "F Y",
                    theme: "light"
                })
            ];
        } else if (mode === 'haftalik') {
            options.defaultDate = new Date();
            options.plugins = [
                new weekSelect({})
            ];
            options.onChange = function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const start = selectedDates[0];
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    instance.input.value = instance.formatDate(start, "d.m.Y") + " - " + instance.formatDate(end, "d.m.Y");
                }
            };
            options.onReady = function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const start = selectedDates[0];
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    instance.input.value = instance.formatDate(start, "d.m.Y") + " - " + instance.formatDate(end, "d.m.Y");
                }
            };
            options.dateFormat = "d.m.Y";
        } else if (mode === 'gunluk') {
            options.mode = "range";
            options.dateFormat = "d.m.Y";
            options.defaultDate = [new Date(), new Date()];
        } else {
            options.dateFormat = 'd.m.Y';
            options.defaultDate = new Date();
        }

        fpSingle = flatpickr('#tarihSecici', options);
    }

    function syncPeriodDefaults() {
        if (currentPeriod === 'gunluk') {
            currentDate = todayYmd;
        } else if (currentPeriod === 'haftalik') {
            currentDate = todayYmd;
        } else if (currentPeriod === 'yillik') {
            currentDate = currentYear + '-01-01';
        } else {
            currentDate = thisMonthStart;
        }
    }

    function toggleDateMode() {
        const isYearly = currentPeriod === 'yillik';
        $('#singleDateWrapper').toggle(!isYearly);
        $('#yearSelectWrapper').toggle(isYearly);
    }

    function updateDateControlsByPeriod() {
        syncPeriodDefaults();
        toggleDateMode();

        if (currentPeriod !== 'yillik') {
            initSingleDatePicker(currentPeriod);
        }
    }

    if ($.fn.select2) {
        $('#yilSecici').select2({
            width: '100%',
            minimumResultsForSearch: Infinity
        });
    }

    $('#yilSecici').on('change', function() {
        currentYear = String($(this).val() || new Date().getFullYear());
        currentDate = currentYear + '-01-01';
    });


    updateDateControlsByPeriod();

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

        // Döneme göre varsayılan tarih ve input tipi güncelle
        updateDateControlsByPeriod();
        loadData();
    });

    // Tab değişimi - Grafikleri yeniden boyutlandır
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        // ApexCharts bazen gizli tabda iken boyutu yanlış hesaplar, bu yüzden resize tetikliyoruz
        window.dispatchEvent(new Event('resize'));
        
        if (e.target.id === 'personel-tab') {
            $('#topPersonelFilterWrapper').show();
            // Personel tabına geçince eğer personel seçili değilse boş göster
            if (!$('#kisiselPersonelSelect').val()) {
                $('#kisiselEmptyState').show();
                $('#kisiselChartContainer').hide();
                $('#kisiselKPIs').hide();
                $('#kisiselSiralamaGroup').addClass('d-none');
            }
        } else {
            $('#topPersonelFilterWrapper').hide();
        }
    });

    // Tablo satırına tıklayınca personel analizine geç
    $(document).on('click', '#performansTable tbody tr', function() {
        // Personel ID'sini bul (id gizli bir yerde veya satırda olabilir, şimdilik isimden eşleştirelim veya data attrib ekleyelim)
        // Data attribute ekleyip oradan alacağız.
        const pId = $(this).data('personel-id');
        if (pId) {
            $('#kisiselPersonelSelect').val(pId).trigger('change');
            const tabEl = document.querySelector('#personel-tab');
            const tab = new bootstrap.Tab(tabEl);
            tab.show();
        }
    });


    // Filtrele butonu
    $('#btnFiltrele').on('click', function() {
        loadData();
    });

    // Veri yükle
    function loadData() {
        let startDate = '';
        let endDate = '';
        let dateVal = currentDate;

        if (currentPeriod === 'yillik') {
            dateVal = $('#yilSecici').val() + '-01-01';
        } else if (fpSingle && fpSingle.selectedDates.length > 0) {
            const dates = fpSingle.selectedDates;
            if (currentPeriod === 'gunluk' && dates.length === 2) {
                startDate = ymdFromDateObj(dates[0]);
                endDate = ymdFromDateObj(dates[1]);
                dateVal = startDate;
            } else {
                dateVal = ymdFromDateObj(dates[0]);
            }
        }

        showLoading();

        $.ajax({
            url: 'views/personel/api/performans-raporu-api.php',
            type: 'GET',
            data: {
                action: 'get-performans',
                departman: currentDept,
                period: currentPeriod,
                tarih: dateVal,
                baslangic_tarih: startDate,
                bitis_tarih: endDate
            },
            dataType: 'json',
            success: function(res) {
                hideLoading();
                if (res.status === 'success') {
                    currentEffectivePeriod = res.effective_period || currentPeriod;
                    updateKPIs(res.summary);
                    updateTrendChart(res.gunluk_trend);
                    updateBarChart(res.personeller);
                    updateTable(res.personeller);
                    updateDonemBilgisi(res);
                    
                    // Kişisel performans dropdown'ını güncelle
                    updateKisiselPersonelSelect(res.personeller);
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

        const periodLabels = { gunluk: 'Günlük', haftalik: 'Haftalık', aylik: 'Aylık', yillik: 'Yıllık', aralik: 'Tarih Aralığı' };
        $('#trendPeriodLabel').text(periodLabels[currentEffectivePeriod] || 'Aylık');

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

            html += `<tr data-personel-id="${p.personel_id}" style="cursor:pointer;">
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
        const periodLabels = { gunluk: 'Günlük', haftalik: 'Haftalık', aylik: 'Aylık', yillik: 'Yıllık', aralik: 'Tarih Aralığı' };
        const info = deptColors[currentDept];
        const start = formatDateFull(res.start_date);
        const end = formatDateFull(res.end_date);
        const selectedPeriod = res.effective_period || currentPeriod;
        $('#donemText').html(
            `<strong>${info.label}</strong> departmanı | ${periodLabels[selectedPeriod]} rapor | ` +
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

    // Kişisel Performans Fonksiyonları
    function updateKisiselPersonelSelect(personeller) {
        let optionsHtml = '<option value="">Personel Seçiniz...</option>';
        personeller.forEach(p => {
            optionsHtml += `<option value="${p.personel_id}" data-ad="${escapeHtml(p.adi_soyadi || '')}" data-img="${p.resim_yolu || ''}" data-dept="${escapeHtml(p.departman || '')}">${escapeHtml(p.adi_soyadi || 'Bilinmeyen')}</option>`;
        });
        
        let select = $('#kisiselPersonelSelect');
        const selectedId = select.val();
        
        select.html(optionsHtml);
        
        if (selectedId && select.find(`option[value="${selectedId}"]`).length > 0) {
            select.val(selectedId).trigger('change.select2').trigger('change');
        } else {
            select.val('').trigger('change.select2').trigger('change');
            $('#kisiselChartContainer').hide();
            $('#kisiselEmptyState').show();
            $('#kisiselSiralamaGroup').addClass('d-none');
        }
    }

    $('#kisiselPersonelSelect').on('change', function() {
        if ($(this).val()) {
            $('#kisiselEmptyState').hide();
            $('#kisiselSiralamaGroup').removeClass('d-none');
            $('#kisiselChartContainer').show();
            $('#kisiselKPIs').show();
            
            const selectedOpt = $(this).find('option:selected');
            $('#kisiselAd').text(selectedOpt.data('ad'));
            $('#kisiselDepartman').text(selectedOpt.data('dept') || deptColors[currentDept].label);
            $('#kisiselImg').attr('src', selectedOpt.data('img') || 'assets/images/users/user-dummy-img.jpg');
            $('#kisiselBirimLabel').text('TOPLAM ' + deptColors[currentDept].unit.toUpperCase());
            
            loadKisiselData();
        } else {
            $('#kisiselChartContainer').hide();
            $('#kisiselEmptyState').show();
            $('#kisiselKPIs').hide();
            $('#kisiselSiralamaGroup').addClass('d-none');
        }
    });

    $('.kisisel-sort-btn').on('click', function() {
        $('.kisisel-sort-btn').removeClass('active');
        $(this).addClass('active');
        kisiselSortOrder = $(this).data('sort');
        renderKisiselChart(currentKisiselData);
    });

    function loadKisiselData() {
        const personelId = $('#kisiselPersonelSelect').val();
        if (!personelId) return;
        
        let startDate = '';
        let endDate = '';
        let dateVal = currentDate;

        if (currentPeriod === 'yillik') {
            dateVal = $('#yilSecici').val() + '-01-01';
        } else if (fpSingle && fpSingle.selectedDates.length > 0) {
            const dates = fpSingle.selectedDates;
            if (currentPeriod === 'gunluk' && dates.length === 2) {
                startDate = ymdFromDateObj(dates[0]);
                endDate = ymdFromDateObj(dates[1]);
                dateVal = startDate;
            } else {
                dateVal = ymdFromDateObj(dates[0]);
            }
        }

        showLoading();

        $.ajax({
            url: 'views/personel/api/performans-raporu-api.php',
            type: 'GET',
            data: {
                action: 'get-kisisel-performans',
                personel_id: personelId,
                departman: currentDept,
                period: currentPeriod,
                tarih: dateVal,
                baslangic_tarih: startDate,
                bitis_tarih: endDate
            },
            dataType: 'json',
            success: function(res) {
                hideLoading();
                if (res.status === 'success') {
                    currentKisiselData = res.kisisel_trend || [];
                    
                    // Özet verileri güncelle
                    updateKisiselKPIs(res.summary);
                    
                    renderKisiselChart(currentKisiselData, res.group_by);
                } else {
                    Swal.fire('Hata', res.message || 'Kişisel veri yüklenemedi.', 'error');
                }
            },
            error: function() {
                hideLoading();
                Swal.fire('Hata', 'Sunucu ile bağlantı kurulamadı.', 'error');
            }
        });
    }

    function updateKisiselKPIs(summary) {
        if (!summary) return;
        
        $('#kp_toplam').text(formatNumber(summary.toplam));
        $('#kp_gunluk_ort').text(formatNumber(summary.gunluk_ortalama));
        $('#kp_aylik_ort').text(formatNumber(summary.aylik_ortalama));
        $('#kp_siralama').text(summary.siralama);
        $('#kp_toplam_personel').text('/ ' + summary.toplam_personel);
        
        if (summary.en_yuksek_gun) {
            $('#kp_en_yuksek').text(formatNumber(summary.en_yuksek_gun.toplam));
            $('#kp_en_yuksek_tarih').text(formatDateFull(summary.en_yuksek_gun.tarih));
        } else {
            $('#kp_en_yuksek').text('0');
            $('#kp_en_yuksek_tarih').text('-');
        }

        if (summary.en_dusuk_gun) {
            $('#kp_en_dusuk').text(formatNumber(summary.en_dusuk_gun.toplam));
            $('#kp_en_dusuk_tarih').text(formatDateFull(summary.en_dusuk_gun.tarih));
        } else {
            $('#kp_en_dusuk').text('0');
            $('#kp_en_dusuk_tarih').text('-');
        }
    }

    function renderKisiselChart(data, groupBy = 'gunluk') {
        const info = deptColors[currentDept];
        let chartData = [...data]; // Kopyasını al

        if (kisiselSortOrder === 'desc') {
            chartData.sort((a, b) => parseInt(b.toplam) - parseInt(a.toplam));
        } else if (kisiselSortOrder === 'asc') {
            chartData.sort((a, b) => parseInt(a.toplam) - parseInt(b.toplam));
        }
        // Eğer kisiselSortOrder === 'tarih', karışma

        let categories = [];
        let values = [];

        chartData.forEach(d => {
            if (groupBy === 'aylik') {
                // d.tarih formatı Y-m
                const parts = d.tarih.split('-');
                if(parts.length >= 2) {
                    const months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
                    categories.push(months[parseInt(parts[1])-1] + ' ' + parts[0]);
                } else {
                    categories.push(d.tarih);
                }
            } else {
                categories.push(formatDateLabel(d.tarih));
            }
            values.push(parseInt(d.toplam));
        });

        if (kisiselChart) kisiselChart.destroy();

        kisiselChart = new ApexCharts(document.querySelector("#kisiselChart"), {
            series: [{
                name: info.unit,
                data: values
            }],
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: true, tools: { download: true, selection: false, zoom: true, zoomin: true, zoomout: true, pan: false, reset: true } },
                fontFamily: 'inherit',
                animations: { enabled: true, easing: 'easeinout', speed: 500 }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    borderRadius: 4,
                    columnWidth: '40%',
                    distributed: false,
                    dataLabels: { position: 'top' }
                }
            },
            colors: [info.color],
            dataLabels: {
                enabled: true,
                formatter: val => val > 0 ? formatNumber(val) : '',
                offsetY: -20,
                style: { fontSize: '10px', colors: ["#304758"] }
            },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            xaxis: {
                categories: categories,
                labels: { style: { fontSize: '10px', colors: '#94a3b8' }, rotate: -45, rotateAlways: categories.length > 10 },
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
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 }
        });
        kisiselChart.render();
    }
});
</script>
