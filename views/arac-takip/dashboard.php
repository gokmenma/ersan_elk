<?php
\App\Service\Gate::authorizeOrDie('arac-takip/dashboard');
use App\Service\Gate;

$maintitle = 'Araç Takip & Yönetim';
$title = 'Araç Dashboard';
?>
<style>
/* Dashboard Özel Kart ve Tipografi Stilleri */
.dashboard-stat-card {
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    transition: transform .2s ease, box-shadow .2s ease;
    overflow: hidden;
    position: relative;
}
.dashboard-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
}
.dashboard-stat-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.stat-card-filo::before { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }
.stat-card-km::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card-fuel::before { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.stat-card-service::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

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
.stat-title {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 2px;
}
.stat-main-value {
    font-size: 24px;
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
    font-size: 12.5px;
}
.stat-sub-label {
    color: #64748b;
    font-weight: 500;
}
.stat-sub-val {
    font-weight: 700;
    color: #1e293b;
}

/* Dönem Hızlı Filtre Butonları (Segmented Control Tasarımı) */
.period-btn-group {
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    gap: 3px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
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

.chart-card {
    border-radius: 12px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #ffffff;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.table-dashboard thead th {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 9px 12px;
}
.table-dashboard tbody td {
    font-size: 12.5px;
    padding: 9px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
}

/* Loading Preloader */
.dashboard-content-wrapper {
    position: relative;
    min-height: 450px;
}

.dashboard-preloader {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    min-height: 350px;
    background: rgba(248, 250, 252, 0.72);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 50;
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 80px;
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
    position: sticky;
    top: 160px;
    background: #ffffff;
    padding: 1.5rem 2rem;
    border-radius: 14px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    border: 1px solid rgba(226, 232, 240, 0.9);
    text-align: center;
    min-width: 260px;
    animation: loaderPopIn .2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes loaderPopIn {
    from { transform: scale(0.92); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.date-filter-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

/* =========================================================
   macOS Dark Floating Theme Uyumlandırması
   ========================================================= */
html[data-theme-preset="macos-dark"] .dashboard-stat-card,
html[data-theme-preset="macos-dark"] .chart-card {
    background: rgba(28, 30, 38, 0.75) !important;
    border-color: rgba(255, 255, 255, 0.09) !important;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45) !important;
    backdrop-filter: blur(25px) saturate(160%);
    -webkit-backdrop-filter: blur(25px) saturate(160%);
}
html[data-theme-preset="macos-dark"] .dashboard-stat-card:hover,
html[data-theme-preset="macos-dark"] .chart-card:hover {
    border-color: rgba(255, 255, 255, 0.16) !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55) !important;
}
html[data-theme-preset="macos-dark"] .stat-title {
    color: rgba(200, 205, 218, 0.65) !important;
}
html[data-theme-preset="macos-dark"] .stat-main-value {
    color: #f3f4f8 !important;
}
html[data-theme-preset="macos-dark"] .stat-sub-row {
    border-top: 1px dashed rgba(255, 255, 255, 0.08) !important;
}
html[data-theme-preset="macos-dark"] .stat-sub-label {
    color: rgba(200, 205, 218, 0.6) !important;
}
html[data-theme-preset="macos-dark"] .stat-sub-val {
    color: rgba(235, 238, 245, 0.88) !important;
}
html[data-theme-preset="macos-dark"] .period-btn-group,
[data-bs-theme="dark"] .period-btn-group {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
html[data-theme-preset="macos-dark"] .period-btn-group .btn,
[data-bs-theme="dark"] .period-btn-group .btn {
    background: transparent !important;
    color: rgba(225, 228, 236, 0.78) !important;
    border-color: transparent !important;
}
html[data-theme-preset="macos-dark"] .period-btn-group .btn:hover,
[data-bs-theme="dark"] .period-btn-group .btn:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
}
html[data-theme-preset="macos-dark"] .period-btn-group .btn.active,
[data-bs-theme="dark"] .period-btn-group .btn.active {
    background: #0a84ff !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    border-color: #0071e3 !important;
    box-shadow: 0 2px 8px rgba(10, 132, 255, 0.45) !important;
}
html[data-theme-preset="macos-dark"] .date-filter-box {
    background: rgba(35, 36, 42, 0.8) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}
html[data-theme-preset="macos-dark"] .date-filter-box input {
    color: rgba(235, 238, 245, 0.9) !important;
}
html[data-theme-preset="macos-dark"] .date-filter-box input::placeholder {
    color: rgba(200, 205, 218, 0.45) !important;
}
html[data-theme-preset="macos-dark"] .table-dashboard thead th {
    background-color: rgba(39, 40, 44, 0.96) !important;
    color: rgba(224, 225, 229, 0.75) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}
html[data-theme-preset="macos-dark"] .table-dashboard tbody td {
    background-color: rgba(33, 34, 39, 0.85) !important;
    color: rgba(231, 234, 241, 0.82) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.065) !important;
}
html[data-theme-preset="macos-dark"] .table-dashboard tbody tr:hover td {
    background-color: rgba(255, 255, 255, 0.06) !important;
}
html[data-theme-preset="macos-dark"] .dashboard-preloader,
[data-bs-theme="dark"] .dashboard-preloader {
    background: rgba(18, 20, 26, 0.75) !important;
}
html[data-theme-preset="macos-dark"] .dashboard-preloader .loader-content,
[data-bs-theme="dark"] .dashboard-preloader .loader-content {
    background: rgba(30, 32, 40, 0.95) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5) !important;
    color: #f3f4f8 !important;
}
html[data-theme-preset="macos-dark"] .dashboard-preloader .loader-content h5,
[data-bs-theme="dark"] .dashboard-preloader .loader-content h5 {
    color: #f3f4f8 !important;
}
html[data-theme-preset="macos-dark"] .dashboard-preloader .loader-content p,
[data-bs-theme="dark"] .dashboard-preloader .loader-content p {
    color: rgba(200, 205, 218, 0.65) !important;
}
html[data-theme-preset="macos-dark"] .card-header h5,
html[data-theme-preset="macos-dark"] .card-header .card-title,
html[data-theme-preset="macos-dark"] h4.text-dark {
    color: rgba(240, 242, 246, 0.95) !important;
}
html[data-theme-preset="macos-dark"] .bg-light {
    background-color: rgba(35, 36, 42, 0.8) !important;
}
html[data-theme-preset="macos-dark"] .border {
    border-color: rgba(255, 255, 255, 0.09) !important;
}
html[data-theme-preset="macos-dark"] .text-dark {
    color: rgba(235, 238, 245, 0.9) !important;
}
</style>

<meta name="csrf-token" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık & Hızlı Filtre Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-xl-4 col-lg-3 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bxs-car fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Araç Filo & Operasyon Dashboard</h4>
                <p class="text-muted mb-0 font-size-12">Filo durumu, kilometre, yakıt sarfiyatı, servis ve evrak göstergeleri</p>
            </div>
        </div>

        <div class="col-xl-8 col-lg-9 col-12 d-flex align-items-center justify-content-lg-end gap-2 flex-wrap mt-2 mt-lg-0">
            <!-- Departman Filtresi -->
            <div style="min-width: 150px;">
                <select id="dashDepartmentSelect" class="form-select form-select-sm border shadow-sm font-size-12">
                    <option value="">Tüm Departmanlar</option>
                </select>
            </div>

            <!-- Hızlı Dönem Butonları -->
            <div class="btn-group period-btn-group shadow-sm" role="group" id="quickPeriodGroup">
                <button type="button" class="btn active" data-period="this_month">Bu Ay</button>
                <button type="button" class="btn" data-period="last_month">Geçen Ay</button>
                <button type="button" class="btn" data-period="last_3_months">Son 3 Ay</button>
                <button type="button" class="btn" data-period="this_year">Bu Yıl (<?= date('Y') ?>)</button>
                <button type="button" class="btn" data-period="all">Tümü</button>
            </div>

            <!-- Özel Tarih Seçici -->
            <div class="d-flex align-items-center rounded-3 px-2 py-1 shadow-sm gap-1 date-filter-box" title="Özel Tarih Aralığı">
                <i class="bx bx-calendar text-primary font-size-15"></i>
                <input type="text" id="dashStartDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold" style="width: 82px; cursor: pointer;" placeholder="Başlangıç" readonly>
                <span class="text-muted font-size-12">-</span>
                <input type="text" id="dashEndDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold" style="width: 82px; cursor: pointer;" placeholder="Bitiş" readonly>
                <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-danger" id="btnClearDates" title="Tarihi Sıfırla" style="display: none;">
                    <i class="bx bx-x font-size-14"></i>
                </button>
            </div>

            <!-- Yenile Butonu -->
            <button type="button" class="btn btn-primary btn-sm px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-sm text-white" id="btnRefreshDashboard" title="Verileri Yenile">
                <i class="bx bx-refresh font-size-16"></i> <span class="d-none d-sm-inline">Yenile</span>
            </button>
        </div>
    </div>

    <!-- Dashboard İçerik Sarmalayıcısı & Preloader -->
    <div class="dashboard-content-wrapper position-relative" id="dashboardContentWrapper">
        <!-- Preloader -->
        <div class="dashboard-preloader show" id="dashboard-loader">
            <div class="loader-content">
                <div class="spinner-border text-primary m-1" role="status" style="width: 2.2rem; height: 2.2rem; border-width: 0.22em;">
                    <span class="visually-hidden">Yükleniyor...</span>
                </div>
                <h5 class="mt-2.5 mb-1 font-size-14 fw-bold">Veriler Yükleniyor...</h5>
                <p class="text-muted font-size-11 mb-0">Filo göstergeleri ve grafikler hazırlanıyor...</p>
            </div>
        </div>

        <!-- 2. ANA 4 KPI KARTI -->
        <div class="row g-3 mb-3" id="kpiCardsContainer">
            <!-- Kart 1: FİLO DURUMU -->
            <div class="col-12 col-md-6 col-xl-3">
                <div class="dashboard-stat-card stat-card-filo h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="stat-title">FİLO DURUMU</span>
                            <div class="stat-icon-box bg-info-subtle text-info border border-info-subtle">
                                <i class="bx bxs-car"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <div class="stat-main-value text-info" id="kpi_filo_toplam">0 Araç</div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11 fw-bold" id="kpi_filo_aktif">0 Aktif</span>
                        </div>
                    </div>
                    <div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Zimmetli Araçlar:</span>
                            <span class="stat-sub-val" id="kpi_filo_zimmetli">0</span>
                        </div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Boşta / Havuz:</span>
                            <span class="stat-sub-val text-warning fw-bold" id="kpi_filo_bosta">0</span>
                        </div>
                        <div class="stat-sub-row mb-0 pb-0">
                            <span class="stat-sub-label font-size-11">Serviste: <strong id="kpi_filo_serviste" class="text-danger">0</strong> | İkame: <strong id="kpi_filo_ikame" class="text-muted">0</strong></span>
                            <a href="index.php?p=arac-takip/list" class="text-info font-size-11 fw-semibold text-decoration-none">Araç Listesi <i class="bx bx-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 2: YOL & KİLOMETRE KULLANIMI -->
            <div class="col-12 col-md-6 col-xl-3">
                <div class="dashboard-stat-card stat-card-km h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="stat-title">KİLOMETRE & YOL</span>
                            <div class="stat-icon-box bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-tachometer"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <div class="stat-main-value text-success" id="kpi_km_toplam">0 km</div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11 fw-bold" id="kpi_km_aktif_arac">0 Araç</span>
                        </div>
                    </div>
                    <div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Günlük Ortalama:</span>
                            <span class="stat-sub-val" id="kpi_km_gunluk_ort">0 km</span>
                        </div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Araç Başı Ortalama:</span>
                            <span class="stat-sub-val text-success fw-bold" id="kpi_km_arac_basi">0 km</span>
                        </div>
                        <div class="stat-sub-row mb-0 pb-0">
                            <span class="stat-sub-label font-size-11">Lider: <strong id="kpi_km_lider" class="text-dark">-</strong></span>
                            <a href="index.php?p=arac-takip/puantaj" class="text-success font-size-11 fw-semibold text-decoration-none">Puantaj <i class="bx bx-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 3: YAKIT SARFİYATI & MALİYET -->
            <div class="col-12 col-md-6 col-xl-3">
                <div class="dashboard-stat-card stat-card-fuel h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="stat-title">YAKIT SARFİYATI</span>
                            <div class="stat-icon-box bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bxs-gas-pump"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <div class="stat-main-value text-danger" id="kpi_yakit_toplam_tutar">0,00 ₺</div>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-11 fw-bold" id="kpi_yakit_toplam_litre">0 Lt</span>
                        </div>
                    </div>
                    <div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Ort. Litre Fiyatı:</span>
                            <span class="stat-sub-val" id="kpi_yakit_ort_litre">0,00 ₺</span>
                        </div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">100 km Ortalama:</span>
                            <span class="stat-sub-val text-danger fw-bold" id="kpi_yakit_ort_100km">0 Lt</span>
                        </div>
                        <div class="stat-sub-row mb-0 pb-0">
                            <span class="stat-sub-label font-size-11">Km Maliyeti: <strong id="kpi_yakit_km_basi" class="text-dark">0,00 ₺/km</strong></span>
                            <a href="index.php?p=arac-takip/arac-performans" class="text-danger font-size-11 fw-semibold text-decoration-none">Performans <i class="bx bx-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 4: BAKIM, SERVİS & EVRAKLAR -->
            <div class="col-12 col-md-6 col-xl-3">
                <div class="dashboard-stat-card stat-card-service h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="stat-title">BAKIM & EVRAKLAR</span>
                            <div class="stat-icon-box bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-wrench"></i>
                            </div>
                        </div>
                        <div class="d-flex align-items-baseline justify-content-between mb-2">
                            <div class="stat-main-value text-primary" id="kpi_servis_maliyet">0,00 ₺</div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-11 fw-bold" id="kpi_servis_adet">0 İşlem</span>
                        </div>
                    </div>
                    <div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">Geciken / Dolan Evrak:</span>
                            <span class="stat-sub-val text-danger fw-bold" id="kpi_evrak_gecen">0</span>
                        </div>
                        <div class="stat-sub-row">
                            <span class="stat-sub-label">30 Günde Dolacak:</span>
                            <span class="stat-sub-val text-warning fw-bold" id="kpi_evrak_yaklasan">0</span>
                        </div>
                        <div class="stat-sub-row mb-0 pb-0">
                            <span class="stat-sub-label font-size-11">Servisteki: <strong id="kpi_servis_aktif_servisteki" class="text-danger">0</strong></span>
                            <a href="index.php?p=arac-takip/km-onaylari" class="text-primary font-size-11 fw-semibold text-decoration-none">Km Onayları <i class="bx bx-chevron-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. GRAFİKLER BÖLÜMÜ -->
        <div class="row g-3 mb-3">
            <!-- Grafik 1: Aylık Yakıt & Kilometre Gelişimi -->
            <div class="col-12 col-xl-8">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-bar-chart-alt-2 font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Aylık Yakıt Maliyeti & Kilometre Trendi</h5>
                                <p class="text-muted mb-0 font-size-11">Filonun aylık akaryakıt gideri ve katettiği yol</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-11"><i class="bx bx-gas-pump"></i> Yakıt Tutarı (₺)</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11"><i class="bx bx-tachometer"></i> Yapılan KM</span>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="monthlyFuelKmChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>

            <!-- Grafik 2: Departman Dağılımı -->
            <div class="col-12 col-xl-4">
                <div class="card chart-card h-100 mb-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-pie-chart font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Departman Dağılımı</h5>
                                <p class="text-muted mb-0 font-size-11">Departmanların araç ve kullanım oranları</p>
                            </div>
                        </div>
                        <!-- Metrik Seçici Butonlar -->
                        <div class="btn-group btn-group-sm period-btn-group shadow-xs" id="deptMetricGroup" role="group">
                            <button type="button" class="btn btn-sm py-1 px-2 active" data-metric="km" title="Kilometreye Göre">KM</button>
                            <button type="button" class="btn btn-sm py-1 px-2" data-metric="fuel" title="Yakıt Tutarına Göre">Yakıt</button>
                            <button type="button" class="btn btn-sm py-1 px-2" data-metric="vehicles" title="Araç Sayısına Göre">Araç</button>
                        </div>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-center flex-grow-1">
                        <div id="deptDonutChart" style="min-height: 290px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. İKİNCİL GRAFİKLER & MÜLKİYET / YAKIT TİPİ -->
        <div class="row g-3 mb-3">
            <!-- Grafik 3: Mülkiyet & Filo Yapısı -->
            <div class="col-12 col-md-6">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-warning-subtle text-warning rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-buildings font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Mülkiyet Dağılımı</h5>
                                <p class="text-muted mb-0 font-size-11">Özmal vs Kiralık araç dağılımı</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="ownershipChart" style="min-height: 220px;"></div>
                    </div>
                </div>
            </div>

            <!-- Grafik 4: Yakıt Türü Dağılımı -->
            <div class="col-12 col-md-6">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-droplet font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Yakıt Tipi Dağılımı</h5>
                                <p class="text-muted mb-0 font-size-11">Filonun yakıt türü çeşitliliği</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="fuelTypeChart" style="min-height: 220px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. SIRALAMALAR & TABLOLAR (Top 5 KM, Top 5 Yakıt) -->
        <div class="row g-3 mb-3">
            <!-- Tablo 1: En Çok KM Yapan İlk 5 Araç -->
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-award font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">En Çok Kilometre Yapan Araçlar</h5>
                                <p class="text-muted mb-0 font-size-11">Seçilen dönemde en yüksek mesafe kateden ilk 5 araç</p>
                            </div>
                        </div>
                        <a href="index.php?p=arac-takip/puantaj" class="btn btn-sm btn-subtle-success rounded-pill px-2.5 py-1 font-size-11">Puantaja Git</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dashboard table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 45px;" class="text-center">#</th>
                                        <th>Araç / Plaka</th>
                                        <th>Sürücü / Departman</th>
                                        <th class="text-end">Dönem KM</th>
                                        <th class="text-end">Güncel KM</th>
                                    </tr>
                                </thead>
                                <tbody id="topKmTableBody">
                                    <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tablo 2: En Çok Yakıt Tüketen İlk 5 Araç -->
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-danger-subtle text-danger rounded-3 border border-danger-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bxs-gas-pump font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">En Çok Yakıt Tüketen Araçlar</h5>
                                <p class="text-muted mb-0 font-size-11">Seçilen dönemde en yüksek yakıt harcaması olan ilk 5 araç</p>
                            </div>
                        </div>
                        <a href="index.php?p=arac-takip/arac-performans" class="btn btn-sm btn-subtle-danger rounded-pill px-2.5 py-1 font-size-11">Performansa Git</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dashboard table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 45px;" class="text-center">#</th>
                                        <th>Araç / Plaka</th>
                                        <th>Sürücü</th>
                                        <th class="text-end">Litre</th>
                                        <th class="text-end">Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody id="topFuelTableBody">
                                    <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. EVRAK UYARILARI & SON SERVİS İŞLEMLERİ -->
        <div class="row g-3 mb-4">
            <!-- Tablo 3: Yaklaşan & Geciken Evraklar (Kritik Uyarılar) -->
            <div class="col-12 col-xl-7">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-danger-subtle text-danger rounded-3 border border-danger-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-error-circle font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Muayene, Sigorta & Kasko Uyarıları</h5>
                                <p class="text-muted mb-0 font-size-11">Süresi dolmuş veya 30 gün içinde dolacak evraklar</p>
                            </div>
                        </div>
                        <span class="badge bg-danger text-white rounded-pill font-size-11 px-2 py-1" id="expiringBadgeCount">0 Uyarı</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-dashboard table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Plaka / Araç</th>
                                        <th>Evrak Türü</th>
                                        <th>Bitiş Tarihi</th>
                                        <th class="text-center">Kalan Süre</th>
                                        <th>Sürücü</th>
                                    </tr>
                                </thead>
                                <tbody id="expiringDocsTableBody">
                                    <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tablo 4: Son Servis & Bakım İşlemleri -->
            <div class="col-12 col-xl-5">
                <div class="card chart-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                                <i class="bx bx-wrench font-size-18"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son Bakım & Servis Kayıtları</h5>
                                <p class="text-muted mb-0 font-size-11">Filonun son servis ve onarım hareketleri</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-dashboard table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Araç / Servis</th>
                                        <th>Tarih</th>
                                        <th>İşlem / Neden</th>
                                        <th class="text-end">Tutar</th>
                                    </tr>
                                </thead>
                                <tbody id="recentServicesTableBody">
                                    <tr><td colspan="4" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts Kütüphanesi -->
<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
<!-- Flatpickr Türkçe Destek -->
<script src="assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="assets/libs/flatpickr/l10n/tr.js"></script>
<!-- Araç Dashboard Scripti -->
<script src="views/arac-takip/js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>"></script>
