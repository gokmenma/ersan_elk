<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;

$maintitle = 'Cari Yönetimi';
$title = 'Cari Dashboard';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* Cari Dashboard Özel Stilleri */
.cari-stat-card {
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    transition: transform .2s ease, box-shadow .2s ease;
    overflow: hidden;
    position: relative;
}
.cari-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
}
.cari-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3.5px;
}
.stat-card-alacak::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card-borc::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.stat-card-bakiye::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card-cari::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

.stat-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.stat-kpi-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 2px;
}
.stat-kpi-value {
    font-size: 23px;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
    letter-spacing: -0.5px;
}
.stat-sub-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    border-top: 1px dashed #e2e8f0;
    font-size: 12px;
}

/* Dönem Filtresi Segmented Control Tasarımı */
.period-btn-group {
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    gap: 3px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    flex-wrap: wrap;
}
.period-btn-group .btn {
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px !important;
    border: 1px solid transparent !important;
    background: transparent !important;
    color: #475569 !important;
    transition: all .15s ease;
    box-shadow: none !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.period-btn-group .btn:hover {
    background: rgba(255, 255, 255, 0.85) !important;
    color: #0f172a !important;
}
.period-btn-group .btn.active,
.period-btn-group .btn.active:hover,
.period-btn-group .btn.active:focus {
    background: #2563eb !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    border-color: #1d4ed8 !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

.dashboard-card {
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.rank-badge {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}
.rank-badge-1 { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.rank-badge-2 { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.rank-badge-3 { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
.rank-badge-other { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

.table-dashboard thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 12px;
}
.table-dashboard tbody td {
    font-size: 12.5px;
    padding: 9px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* Yükleme Ekranı (Preloader) */
.dashboard-preloader {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    min-height: 300px;
    background: rgba(248, 250, 252, 0.75);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 50;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.2s ease, visibility 0.2s ease;
}
.dashboard-preloader.show {
    opacity: 1;
    visibility: visible;
    pointer-events: all;
}
.dashboard-preloader .loader-content {
    background: #ffffff;
    padding: 1.5rem 2rem;
    border-radius: 14px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(226, 232, 240, 0.9);
    text-align: center;
}

.top-item-row {
    transition: background-color .15s ease;
    border-radius: 8px;
    padding: 8px 10px;
    margin-bottom: 6px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
}
.top-item-row:hover {
    background: #f1f5f9;
}
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-lg-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 46px; height: 46px;">
                <i class="bx bx-pie-chart-alt-2 fs-3 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Cari Finansal Dashboard</h4>
                <p class="text-muted mb-0 font-size-12">Nakit akışı, borç/alacak risk dengesi, aylık trendler ve en yüksek bakiyeli cariler</p>
            </div>
        </div>

        <div class="col-lg-7 col-12 d-flex align-items-center justify-content-lg-end gap-2 mt-3 mt-lg-0 flex-wrap">
            <!-- Dönem Filtre Grubu -->
            <div class="period-btn-group" id="dashboardPeriodGroup">
                <button type="button" class="btn period-btn active" data-period="all">Tümü</button>
                <button type="button" class="btn period-btn" data-period="bu_ay">Bu Ay</button>
                <button type="button" class="btn period-btn" data-period="son_3_ay">Son 3 Ay</button>
                <button type="button" class="btn period-btn" data-period="bu_yil">Bu Yıl</button>
                <button type="button" class="btn period-btn" data-period="custom" id="btnCustomRange">
                    <i class="bx bx-calendar me-1"></i> <span id="customRangeLabel">Özel</span>
                </button>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" id="btnRefreshDashboard" title="Verileri Yenile">
                    <i class="bx bx-refresh font-size-16 text-primary"></i>
                </button>
                <a href="index.php?p=cari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                    <i class="bx bx-list-ul font-size-16 text-primary"></i> Cari Listesi
                </a>
                <a href="index.php?p=cari/tum-hareketler" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                    <i class="bx bx-history font-size-16 text-primary"></i> Tüm Hareketler
                </a>
            </div>
        </div>
    </div>

    <!-- Dashboard Ana Kapsayıcı (Preloader ile Korunan Alan) -->
    <div class="position-relative" id="dashboardMainWrapper">
        <div class="dashboard-preloader show" id="dashboardPreloader">
            <div class="loader-content">
                <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
                <div class="fw-bold text-dark font-size-13">Cari Verileri Yükleniyor...</div>
                <div class="text-muted font-size-11">Finansal hareketler ve grafikler hesaplanıyor</div>
            </div>
        </div>

        <!-- 2. 4 Adet Ana KPI Kartı -->
        <div class="row g-3 mb-3">
            <!-- Kart 1: TOPLAM ALACAK (VERDİĞİMİZ) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card cari-stat-card stat-card-alacak h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-kpi-label">TOPLAM ALACAK (VERDİM)</span>
                            <div class="stat-icon-box bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-trending-up"></i>
                            </div>
                        </div>
                        <h3 class="stat-kpi-value text-success my-1" id="kpi_toplam_alacak">0,00 ₺</h3>
                        <div class="stat-sub-row mb-0">
                            <span class="text-muted" id="kpi_alacakli_sayisi_label">Alacaklı: 0 Cari</span>
                            <span class="fw-bold text-success" id="kpi_toplam_alacak_bakiye">Net: 0,00 ₺</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 2: TOPLAM BORÇ (ALDIĞIMIZ) -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card cari-stat-card stat-card-borc h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-kpi-label">TOPLAM BORÇ (ALDIM)</span>
                            <div class="stat-icon-box bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bx bx-trending-down"></i>
                            </div>
                        </div>
                        <h3 class="stat-kpi-value text-danger my-1" id="kpi_toplam_borc">0,00 ₺</h3>
                        <div class="stat-sub-row mb-0">
                            <span class="text-muted" id="kpi_borclu_sayisi_label">Borçlu: 0 Cari</span>
                            <span class="fw-bold text-danger" id="kpi_toplam_borc_bakiye">Net: 0,00 ₺</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 3: GENEL NET BAKİYE -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card cari-stat-card stat-card-bakiye h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-kpi-label">NET BAKİYE DURUMU</span>
                            <div class="stat-icon-box bg-primary-subtle text-primary border border-primary-subtle" id="kpi_net_icon_box">
                                <i class="bx bx-wallet" id="kpi_net_icon"></i>
                            </div>
                        </div>
                        <h3 class="stat-kpi-value my-1" id="kpi_net_bakiye">0,00 ₺</h3>
                        <div class="stat-sub-row mb-0">
                            <span class="text-muted" id="kpi_bakiye_status_text">Bakiye Dengesi</span>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="kpi_bakiye_badge">Dengede</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 4: TOPLAM CARİ VE İŞLEM HACMİ -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card cari-stat-card stat-card-cari h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="stat-kpi-label">CARİ PORTFÖY & İŞLEM</span>
                            <div class="stat-icon-box bg-purple-subtle text-purple border border-purple-subtle" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border: 1px solid rgba(139, 92, 246, 0.25);">
                                <i class="bx bx-group"></i>
                            </div>
                        </div>
                        <h3 class="stat-kpi-value my-1 text-dark" id="kpi_toplam_cari">0</h3>
                        <div class="stat-sub-row mb-0">
                            <span class="text-muted" id="kpi_islem_sayisi">Toplam 0 Hareket</span>
                            <span class="fw-semibold text-secondary" id="kpi_dengede_sayisi">0 Dengede</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Grafikler Satırı (Aylık Trend & Dağılım Donut) -->
        <div class="row g-3 mb-3">
            <!-- 3.1. Aylık Tahsilat & Ödeme Trendi (ApexCharts Area Chart) -->
            <div class="col-12 col-xl-8">
                <div class="card dashboard-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                                <i class="bx bx-line-chart font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Aylık Nakit Akışı ve Hareket Trendi</h5>
                                <p class="text-muted mb-0 font-size-12">Son 12 ayın alacak (verdim) ve borç (aldım) karşılaştırması</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-secondary border px-2.5 py-1 rounded-pill font-size-11" id="trendPeriodBadge">Son 12 Ay</span>
                        </div>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div id="chartMonthlyTrend" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>

            <!-- 3.2. Finansal Dağılım & Risk Dengesi (ApexCharts Donut) -->
            <div class="col-12 col-xl-4">
                <div class="card dashboard-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                                <i class="bx bx-doughnut-chart font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Bakiye & Risk Dağılımı</h5>
                                <p class="text-muted mb-0 font-size-12">Portföy bakiye ağırlıkları</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3 pt-0 d-flex flex-column justify-content-between">
                        <div id="chartDistribution" style="min-height: 220px;"></div>
                        
                        <div class="border-top pt-2 mt-2">
                            <div class="d-flex justify-content-between align-items-center py-1 font-size-12">
                                <span class="d-flex align-items-center gap-1.5"><span class="badge rounded-circle p-1 bg-success"></span> Alacaklı Bakiyeler:</span>
                                <strong class="text-success" id="dist_alacak_val">0,00 ₺</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 font-size-12">
                                <span class="d-flex align-items-center gap-1.5"><span class="badge rounded-circle p-1 bg-danger"></span> Borçlu Bakiyeler:</span>
                                <strong class="text-danger" id="dist_borc_val">0,00 ₺</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 font-size-12 border-top border-dashed">
                                <span class="text-muted">Cari Dağılımı:</span>
                                <span class="text-dark fw-semibold" id="dist_cari_counts">0 Alacaklı / 0 Borçlu</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. En Yüksek Bakiyeli Cariler (Top 5 Alacaklılar vs Top 5 Borçlular) -->
        <div class="row g-3 mb-3">
            <!-- 4.1. En Çok Alacaklı Olduğumuz Cariler (Bizim Paramız Olanlar) -->
            <div class="col-12 col-lg-6">
                <div class="card dashboard-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                                <i class="bx bx-up-arrow-circle font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">En Çok Alacaklı Olduğumuz Cariler</h5>
                                <p class="text-muted mb-0 font-size-12">Bize en yüksek borcu bulunan ilk 5 müşteri/firma</p>
                            </div>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Top 5 Alacak</span>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div id="topAlacaklilarList" class="d-flex flex-column gap-1">
                            <div class="text-center py-4 text-muted font-size-12">
                                <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                                Yükleniyor...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4.2. En Çok Borçlu Olduğumuz Cariler (Bizim Borcumuz Olanlar) -->
            <div class="col-12 col-lg-6">
                <div class="card dashboard-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-danger-subtle text-danger rounded-3 border border-danger-subtle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                                <i class="bx bx-down-arrow-circle font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">En Çok Borçlu Olduğumuz Cariler</h5>
                                <p class="text-muted mb-0 font-size-12">Bizim borçlu olduğumuz ilk 5 tedarikçi/firma</p>
                            </div>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Top 5 Borç</span>
                    </div>
                    <div class="card-body p-3 pt-0">
                        <div id="topBorclularList" class="d-flex flex-column gap-1">
                            <div class="text-center py-4 text-muted font-size-12">
                                <div class="spinner-border spinner-border-sm text-danger me-2" role="status"></div>
                                Yükleniyor...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Son Cari Hesap Hareketleri Akışı (Canlı Tablo) -->
        <div class="card dashboard-card mb-3">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-sm" style="width: 36px; height: 36px;">
                        <i class="bx bx-history font-size-18"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son Cari Hesap Hareketleri</h5>
                        <p class="text-muted mb-0 font-size-12">Sisteme kaydedilen en son finansal işlemler ve hareket detayları</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?p=cari/tum-hareketler" class="btn btn-sm btn-subtle-primary px-3 py-1.5 d-flex align-items-center gap-1 rounded-pill fw-semibold shadow-xs">
                        <i class="bx bx-window-open font-size-15"></i> <span class="font-size-12">Tüm Hareketleri Görüntüle</span>
                    </a>
                </div>
            </div>

            <div class="card-body p-3 pt-0">
                <div class="table-responsive">
                    <table class="table table-hover table-dashboard align-middle w-100 mb-0">
                        <thead>
                            <tr>
                                <th style="width: 140px;">TARİH & SAAT</th>
                                <th>CARİ HESAP / FİRMA</th>
                                <th style="width: 110px;" class="text-center">TÜR</th>
                                <th style="width: 120px;">BELGE NO</th>
                                <th>AÇIKLAMA</th>
                                <th class="text-end" style="width: 140px;">TUTAR</th>
                                <th style="width: 140px;">EKLEYEN</th>
                                <th style="width: 70px;" class="text-center">İŞLEM</th>
                            </tr>
                        </thead>
                        <tbody id="recentMovementsTbody">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    Hareketler yükleniyor...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Özel Tarih Aralığı Modalı -->
<div class="modal fade" id="customRangeModal" tabindex="-1" aria-labelledby="customRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom px-3 py-2.5">
                <h6 class="modal-title fw-bold" id="customRangeModalLabel">
                    <i class="bx bx-calendar text-primary me-1"></i> Özel Tarih Aralığı Seçin
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="customStartDate" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="customEndDate" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer border-top px-3 py-2 justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnApplyCustomRange">Uygula ve Filtrele</button>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts Kütüphanesi -->
<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
<!-- Dashboard Scripti -->
<script src="views/cari/js/dashboard.js?v=<?= time() ?>"></script>

