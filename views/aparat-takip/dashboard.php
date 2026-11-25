<?php
\App\Service\Gate::authorizeOrDie('aparat-takip/dashboard');

use App\Helper\Form;
use App\Helper\Helper;
use App\Model\AparatTipiModel;
use App\Service\Gate;

$maintitle = 'İş Takip';
$title = 'Aparat Dashboard';

$superAdmin = Gate::isSuperAdmin();
$yetkiListe = Gate::allows('aparat_takip') || Gate::allows('aparat-takip/list') || $superAdmin;
$yetkiGelismis = Gate::allows('aparat_gelismis') || $superAdmin;

$tipModel = new AparatTipiModel();
$tipler = $tipModel->listele(false);
$tipOptions = ['0' => 'Tüm Aparat Tipleri'];
foreach ($tipler as $t) {
    if ((int) $t['is_active'] === 1) {
        $tipOptions[(string) (int) $t['id']] = $t['ad'] . ' (' . $t['kod'] . ')';
    }
}
?>
<script>try { document.documentElement.classList.toggle('aparatdash-summary-hidden', localStorage.getItem('aparat_dash_summary_cards_state') === 'hidden'); } catch (e) {}</script>

<style>
    #summaryCardsContainer {
        overflow: hidden;
        max-height: 1100px;
        opacity: 1;
        transition: max-height .3s ease, opacity .3s ease, margin .3s ease;
    }

    .aparatdash-summary-hidden #summaryCardsContainer {
        max-height: 0 !important;
        opacity: 0;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        pointer-events: none;
    }

    @media (prefers-reduced-motion: reduce) {
        #summaryCardsContainer {
            transition: none;
        }
    }

    .aparat-stat-card {
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.95);
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        transition: transform .2s ease, box-shadow .2s ease;
        overflow: hidden;
        position: relative;
    }

    .aparat-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .aparat-stat-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
    }

    .stat-card-depo::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
    .stat-card-ekip::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .stat-card-saha::before { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }
    .stat-card-akis::before { background: linear-gradient(90deg, #f59e0b, #fb923c); }

    .aparat-dash-card {
        border-radius: 12px;
        border: 1px solid rgba(226, 232, 240, 0.95);
        background: #ffffff;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
    }

    .stat-icon-box {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        flex-shrink: 0;
    }

    .stat-kpi-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.45px;
        color: #64748b;
    }

    .stat-kpi-value {
        font-size: 24px;
        font-weight: 800;
        line-height: 1.2;
        color: #0f172a;
        letter-spacing: -0.4px;
        font-variant-numeric: tabular-nums;
    }

    .stat-sub-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 5px 0;
        border-top: 1px dashed #e2e8f0;
        font-size: 12px;
    }

    .period-btn-group {
        background: #f1f5f9;
        padding: 3px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        gap: 2px;
        flex-wrap: wrap;
    }

    .period-btn-group .btn {
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 6px !important;
        border: 1px solid transparent !important;
        background: transparent !important;
        color: #475569 !important;
        transition: all .15s ease;
        box-shadow: none !important;
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
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3) !important;
    }

    /* Aparat Tipi Select2 Kompakt Üst Çubuk Uyumlaması */
    .aparat-tip-filter-wrap {
        min-width: 215px;
    }

    .aparat-tip-filter-wrap .form-floating-select2 {
        height: 38px;
    }

    .aparat-tip-filter-wrap .form-floating-select2 .select2-container--default .select2-selection--single,
    .aparat-tip-filter-wrap .select2-container .select2-selection--single {
        min-height: 38px !important;
        height: 38px !important;
        padding: 0 30px 0 36px !important;
        display: flex !important;
        align-items: center !important;
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04) !important;
    }

    .aparat-tip-filter-wrap .form-floating-select2 > label {
        display: none !important;
    }

    .aparat-tip-filter-wrap .form-floating-icon {
        top: 0 !important;
        bottom: 0 !important;
        height: 38px !important;
        width: 36px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        font-size: 16px !important;
        color: #64748b !important;
    }

    .aparat-tip-filter-wrap .form-floating-select2 .select2-selection__rendered {
        line-height: 36px !important;
        padding: 0 !important;
        margin: 0 !important;
        font-size: 12px !important;
        font-weight: 600;
        color: #334155 !important;
    }

    .aparat-tip-filter-wrap .form-floating-select2 .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        top: 0 !important;
        right: 8px !important;
        display: flex !important;
        align-items: center !important;
    }

    .aparat-tip-filter-wrap .form-floating-select2 .select2-selection__arrow b {
        position: static !important;
        margin: 0 !important;
    }

    .table-dashboard thead th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
        background-color: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 12px;
        white-space: nowrap;
    }

    .table-dashboard tbody td {
        font-size: 12.5px;
        padding: 8.5px 12px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .rank-badge {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11.5px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .rank-badge-1 { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .rank-badge-2 { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .rank-badge-3 { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
    .rank-badge-other { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

    .team-rank-row {
        border-radius: 8px;
        padding: 8px 10px;
        margin-bottom: 6px;
        background: #f8fafc;
        border: 1px solid #eef2f6;
        transition: background-color .15s ease;
    }

    .team-rank-row:hover {
        background: #f1f5f9;
    }

    .alert-strip-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
    }

    .dashboard-preloader {
        position: absolute;
        inset: 0;
        min-height: 280px;
        background: rgba(248, 250, 252, 0.72);
        backdrop-filter: blur(3px);
        -webkit-backdrop-filter: blur(3px);
        z-index: 40;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 90px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity .2s ease, visibility .2s ease;
    }

    .dashboard-preloader.show {
        opacity: 1;
        visibility: visible;
        pointer-events: all;
    }

    .dashboard-preloader .loader-box {
        background: #ffffff;
        padding: 1.25rem 1.75rem;
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        border: 1px solid rgba(226, 232, 240, 0.95);
        text-align: center;
    }

    /* Koyu Tema & macOS Dark Uyumlaması */
    [data-bs-theme="dark"] .aparat-stat-card,
    [data-bs-theme="dark"] .aparat-dash-card,
    html[data-theme-preset="macos-dark"] .aparat-stat-card,
    html[data-theme-preset="macos-dark"] .aparat-dash-card {
        background: rgba(28, 30, 38, 0.82) !important;
        border-color: rgba(255, 255, 255, 0.09) !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.35) !important;
    }

    [data-bs-theme="dark"] .stat-kpi-label,
    html[data-theme-preset="macos-dark"] .stat-kpi-label {
        color: rgba(200, 205, 218, 0.68) !important;
    }

    [data-bs-theme="dark"] .stat-kpi-value,
    [data-bs-theme="dark"] .aparat-dash-card h5,
    [data-bs-theme="dark"] h4.text-dark,
    html[data-theme-preset="macos-dark"] .stat-kpi-value,
    html[data-theme-preset="macos-dark"] .aparat-dash-card h5,
    html[data-theme-preset="macos-dark"] h4.text-dark {
        color: #f3f4f8 !important;
    }

    [data-bs-theme="dark"] .stat-sub-row,
    html[data-theme-preset="macos-dark"] .stat-sub-row {
        border-top-color: rgba(255, 255, 255, 0.08) !important;
    }

    [data-bs-theme="dark"] .period-btn-group,
    html[data-theme-preset="macos-dark"] .period-btn-group {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-bs-theme="dark"] .period-btn-group .btn,
    html[data-theme-preset="macos-dark"] .period-btn-group .btn {
        color: rgba(225, 228, 236, 0.78) !important;
    }

    [data-bs-theme="dark"] .period-btn-group .btn:hover,
    html[data-theme-preset="macos-dark"] .period-btn-group .btn:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] .period-btn-group .btn.active,
    html[data-theme-preset="macos-dark"] .period-btn-group .btn.active {
        background: #0a84ff !important;
        border-color: #0071e3 !important;
        color: #ffffff !important;
    }

    [data-bs-theme="dark"] .table-dashboard thead th,
    html[data-theme-preset="macos-dark"] .table-dashboard thead th {
        background-color: rgba(39, 41, 52, 0.92) !important;
        color: rgba(220, 225, 235, 0.75) !important;
        border-bottom-color: rgba(255, 255, 255, 0.08) !important;
    }

    [data-bs-theme="dark"] .table-dashboard tbody td,
    html[data-theme-preset="macos-dark"] .table-dashboard tbody td {
        border-bottom-color: rgba(255, 255, 255, 0.06) !important;
        color: rgba(235, 238, 245, 0.88) !important;
    }

    [data-bs-theme="dark"] .team-rank-row,
    html[data-theme-preset="macos-dark"] .team-rank-row {
        background: rgba(255, 255, 255, 0.035) !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }

    [data-bs-theme="dark"] .team-rank-row:hover,
    html[data-theme-preset="macos-dark"] .team-rank-row:hover {
        background: rgba(255, 255, 255, 0.07) !important;
    }

    [data-bs-theme="dark"] .dashboard-preloader,
    html[data-theme-preset="macos-dark"] .dashboard-preloader {
        background: rgba(18, 20, 26, 0.72) !important;
    }

    [data-bs-theme="dark"] .dashboard-preloader .loader-box,
    html[data-theme-preset="macos-dark"] .dashboard-preloader .loader-box {
        background: rgba(30, 32, 40, 0.95) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        color: #f3f4f8 !important;
    }
</style>

<script>
    window.aparatDashConfig = {
        canViewList: <?= $yetkiListe ? 'true' : 'false' ?>,
        canViewAdvanced: <?= $yetkiGelismis ? 'true' : 'false' ?>
    };
    window.aparatDashIlk = fetch('views/aparat-takip/api.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=dashboard-data&period=this_month&aparat_tip_id=0'
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
</script>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Filtre / Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-xl-4 col-lg-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-pie-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Aparat Takip Dashboard</h4>
                <p class="text-muted mb-0 font-size-12">Depo, ekip zimmetleri, sahada takılı aparatlar ve kesme-açma hareket analizi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-xl-8 col-lg-12 d-flex align-items-center justify-content-xl-end gap-2 flex-wrap mt-2 mt-xl-0">
            <!-- Aparat Tipi Filtresi (Form::FormSelect2 Standardı) -->
            <div class="aparat-tip-filter-wrap">
                <?= Form::FormSelect2('dash_aparat_tip', $tipOptions, '0', 'Aparat Tipi', 'bx bx-package') ?>
            </div>

            <!-- Dönem Hızlı Seçim Grubu -->
            <div class="period-btn-group shadow-xs" id="aparatPeriodGroup" role="group" aria-label="Dönem Seçimi">
                <button type="button" class="btn period-btn" data-period="today">Bugün</button>
                <button type="button" class="btn period-btn active" data-period="this_month">Bu Ay</button>
                <button type="button" class="btn period-btn" data-period="last_30_days">Son 30 Gün</button>
                <button type="button" class="btn period-btn" data-period="last_3_months">Son 3 Ay</button>
                <button type="button" class="btn period-btn" data-period="this_year">Bu Yıl</button>
                <button type="button" class="btn period-btn" data-period="all">Tümü</button>
                <button type="button" class="btn period-btn" data-period="custom" id="btnCustomPeriod">
                    <i class="bx bx-calendar me-1"></i><span id="customPeriodLabel">Özel</span>
                </button>
            </div>

            <?php if ($yetkiListe): ?>
                <a href="index.php?p=aparat-takip/list" class="btn btn-primary top-action-btn shadow-sm text-white">
                    <i class="bx bx-package font-size-16"></i> <span class="d-none d-sm-inline">Aparat Takip</span>
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Gizle" aria-label="Özet Kartları Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- Ana Dashboard Sarmalayıcı -->
    <div class="position-relative" id="aparatDashboardWrapper">
        <div class="dashboard-preloader" id="aparatDashPreloader">
            <div class="loader-box">
                <div class="spinner-border text-primary mb-2" role="status" style="width: 1.9rem; height: 1.9rem;"></div>
                <div class="fw-bold font-size-13">Aparat Verileri Yükleniyor...</div>
                <div class="text-muted font-size-11">Stok havuzları ve hareket istatistikleri hesaplanıyor</div>
            </div>
        </div>

        <!-- 2. Operasyonel Uyarı Şeridi (Dinamik) -->
        <div id="aparatAlertStrip" class="d-none flex-wrap align-items-center gap-2 mb-3"></div>

        <!-- 3. 4 Adet Minimal Özet KPI Kartı (#summaryCardsContainer) -->
        <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
            <!-- Kart 1: Depo & Toplam Envanter -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card aparat-stat-card stat-card-depo h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="stat-kpi-label">MERKEZ DEPO STOĞU</span>
                                    <h3 class="stat-kpi-value text-primary my-1" id="kpi_depo_adet">-</h3>
                                </div>
                                <div class="stat-icon-box bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bx bx-buildings"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="stat-sub-row">
                                <span class="text-muted">Toplam Envanter Varlığı:</span>
                                <span class="fw-bold text-dark" id="kpi_toplam_envanter">-</span>
                            </div>
                            <div class="stat-sub-row pb-0">
                                <span class="text-muted" id="kpi_aktif_tip_label">Aktif Tip: -</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold" id="kpi_depo_oran">%0 Depoda</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 2: Ekiplerde Bulunan Zimmet -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card aparat-stat-card stat-card-ekip h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="stat-kpi-label">EKİPLERDEKİ APARAT</span>
                                    <h3 class="stat-kpi-value text-success my-1" id="kpi_ekip_adet">-</h3>
                                </div>
                                <div class="stat-icon-box bg-success-subtle text-success border border-success-subtle">
                                    <i class="bx bx-group"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="stat-sub-row">
                                <span class="text-muted">Zimmetli Saha Ekibi:</span>
                                <span class="fw-bold text-dark" id="kpi_zimmetli_ekip">-</span>
                            </div>
                            <div class="stat-sub-row pb-0">
                                <span class="text-muted" id="kpi_kritik_ozet">Kritik Uyarı: 0</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold" id="kpi_ekip_durum_badge">Stok Dengeli</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 3: Sahada Takılı & Kesme/Açma -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card aparat-stat-card stat-card-saha h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="stat-kpi-label">ABONEDE TAKILI (SAHA)</span>
                                    <h3 class="stat-kpi-value text-info my-1" id="kpi_saha_adet">-</h3>
                                </div>
                                <div class="stat-icon-box bg-info-subtle text-info border border-info-subtle">
                                    <i class="bx bx-map-pin"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="stat-sub-row">
                                <span class="text-muted">Dönem Kesme / Açma:</span>
                                <span class="fw-bold" id="kpi_kesme_acma_oran">0 Kesme · 0 Açma</span>
                            </div>
                            <div class="stat-sub-row pb-0">
                                <span class="text-muted" id="kpi_uzun_saha_label">30+ Gün Takılı: 0</span>
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold" id="kpi_donus_badge">Dönüş: %0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kart 4: Dönemsel Depo Akışı & Fire -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card aparat-stat-card stat-card-akis h-100 mb-0">
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="stat-kpi-label">DÖNEM DEPO AKIŞI</span>
                                    <h3 class="stat-kpi-value text-warning my-1" id="kpi_depo_cikis">-</h3>
                                </div>
                                <div class="stat-icon-box bg-warning-subtle text-warning border border-warning-subtle">
                                    <i class="bx bx-transfer-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="stat-sub-row">
                                <span class="text-muted">Depo Giriş / İade Alınan:</span>
                                <span class="fw-bold text-dark" id="kpi_giris_iade">0 Giriş · 0 İade</span>
                            </div>
                            <div class="stat-sub-row pb-0">
                                <span class="text-muted" id="kpi_hurda_kayip_label">Hurda & Kayıp: 0</span>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold" id="kpi_transfer_badge">0 Bekleyen Transfer</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Birincil Grafikler: Dönemsel Hareket Trendi + Havuz Dağılımı -->
        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-8">
                <div class="card aparat-dash-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bx bx-line-chart fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold text-dark">Aparat Hareket & Saha Operasyon Trendi</h5>
                                <p class="text-muted mb-0 font-size-11" id="trendSubtitle">Seçili dönemdeki kesme, açma, ekibe çıkış ve iade adetleri</p>
                            </div>
                        </div>
                        <span class="badge bg-light text-secondary border font-size-11" id="activePeriodBadge">Bu Ay</span>
                    </div>
                    <div class="card-body p-3">
                        <div id="chartMovementTrend" style="min-height: 310px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card aparat-dash-card h-100 mb-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center gap-2">
                        <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bx bx-doughnut-chart fs-5"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-size-14 fw-bold text-dark">Stok Havuzu Dağılımı</h5>
                            <p class="text-muted mb-0 font-size-11">Mevcut aparatların havuzlara göre anlık konumu</p>
                        </div>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between flex-grow-1">
                        <div id="chartPoolDonut" style="min-height: 235px;"></div>
                        <div class="row g-2 pt-2 border-top text-center font-size-11" id="poolLegendStats">
                            <div class="col-4">
                                <div class="text-muted">Depoda</div>
                                <div class="fw-bold text-primary font-size-13" id="leg_depo">0</div>
                            </div>
                            <div class="col-4 border-start border-end">
                                <div class="text-muted">Ekiplerde</div>
                                <div class="fw-bold text-success font-size-13" id="leg_ekip">0</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">Sahada</div>
                                <div class="fw-bold text-info font-size-13" id="leg_saha">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. İkincil Grafikler & Sıralama: Aparat Tipi Kırılımı + Ekip Zimmet Sıralaması -->
        <div class="row g-3 mb-3">
            <!-- Aparat Tipi Bazında Yığılmış Sütun & Özet Tablo -->
            <div class="col-12 col-xl-7">
                <div class="card aparat-dash-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bx bx-bar-chart-alt-2 fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold text-dark">Aparat Tipi Bazında Stok Dağılımı</h5>
                                <p class="text-muted mb-0 font-size-11">Her aparat tipinin depo, ekip ve saha havuzlarındaki adedi</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div id="chartTypeBreakdown" style="min-height: 250px;"></div>
                        <div class="table-responsive mt-2">
                            <table class="table table-dashboard table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Aparat Tipi</th>
                                        <th class="text-center">Depo</th>
                                        <th class="text-center">Ekipler</th>
                                        <th class="text-center">Sahada</th>
                                        <th class="text-center">Hurda/Kayıp</th>
                                        <th class="text-end">Toplam</th>
                                    </tr>
                                </thead>
                                <tbody id="typeBreakdownTbody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ekip Bazlı Zimmet & Operasyon Sıralaması -->
            <div class="col-12 col-xl-5">
                <div class="card aparat-dash-card h-100 mb-0">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-warning-subtle text-warning rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="bx bx-trophy fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold text-dark">Ekip Zimmet & Saha Durumu</h5>
                                <p class="text-muted mb-0 font-size-11">Ekiplerin güncel aparat mevcudu ve dönem içi kesme/açma adedi</p>
                            </div>
                        </div>
                        <?php if ($yetkiListe): ?>
                            <a href="index.php?p=aparat-takip/list" class="btn btn-sm btn-subtle-primary rounded-pill px-2.5 py-1 font-size-11 fw-semibold">
                                Stok Matrisi <i class="bx bx-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body px-3 pt-1 pb-3" style="max-height: 455px; overflow-y: auto;" id="teamRankingList">
                        <div class="text-center text-muted py-4 font-size-12">Yükleniyor...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Alt Paneller: Kritik Stok & Uzun Süreli Saha Takibi + Son Aparat Hareketleri -->
        <div class="row g-3 mb-3">
            <!-- Kritik & Sahada Bekleyen Aparatlar -->
            <div class="col-12 col-xl-5">
                <div class="card aparat-dash-card h-100 mb-0">
                    <div class="card-header bg-transparent border-bottom px-3 py-2.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-1.5 bg-danger-subtle text-danger rounded-3 border border-danger-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                <i class="bx bx-bell fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold text-dark">Dikkat Gerektiren Kayıtlar</h5>
                                <p class="text-muted mb-0 font-size-11">Kritik seviyedeki stoklar ve abonede en eski takılı aparatlar</p>
                            </div>
                        </div>
                        <div class="period-btn-group" id="attentionTabGroup" role="tablist">
                            <button type="button" class="btn active" data-pane="kritik">
                                Kritik Stok <span class="badge bg-warning-subtle text-warning ms-1" id="badgeKritikCount">0</span>
                            </button>
                            <button type="button" class="btn" data-pane="saha">
                                Sahada Bekleyen <span class="badge bg-info-subtle text-info ms-1" id="badgeSahaCount">0</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3" style="max-height: 360px; overflow-y: auto;">
                        <div id="attentionPaneKritik">
                            <div class="text-center text-muted py-4 font-size-12">Yükleniyor...</div>
                        </div>
                        <div id="attentionPaneSaha" class="d-none">
                            <div class="text-center text-muted py-4 font-size-12">Yükleniyor...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Aparat Hareketleri -->
            <div class="col-12 col-xl-7">
                <div class="card aparat-dash-card h-100 mb-0">
                    <div class="card-header bg-transparent border-bottom px-3 py-2.5 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-1.5 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                <i class="bx bx-history fs-5"></i>
                            </div>
                            <div>
                                <h5 class="mb-0 font-size-14 fw-bold text-dark">Son Aparat Hareketleri</h5>
                                <p class="text-muted mb-0 font-size-11">Depo giriş/çıkış, kesme/açma ve transfer defterindeki son olaylar</p>
                            </div>
                        </div>
                        <?php if ($yetkiListe && $yetkiGelismis): ?>
                            <a href="index.php?p=aparat-takip/list#pane-hareketler" class="btn btn-sm btn-subtle-secondary rounded-pill px-2.5 py-1 font-size-11 fw-semibold">
                                Tüm Hareketler <i class="bx bx-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dashboard table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tarih</th>
                                        <th>İşlem Türü</th>
                                        <th>Aparat</th>
                                        <th class="text-center">Adet</th>
                                        <th>Ekip / İşlemi Yapan</th>
                                        <th>Açıklama</th>
                                    </tr>
                                </thead>
                                <tbody id="recentMovementsTbody">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Yükleniyor...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Özel Tarih Aralığı Seçim Modalı -->
<div class="modal fade" id="aparatCustomDateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2.5 px-3 border-bottom">
                <h6 class="modal-title fw-bold mb-0"><i class="bx bx-calendar text-primary me-1"></i> Özel Tarih Aralığı</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2.5">
                    <?= Form::FormDate('dash_custom_bas', date('01.m.Y'), 'Başlangıç Tarihi') ?>
                </div>
                <div class="mb-1">
                    <?= Form::FormDate('dash_custom_bit', date('d.m.Y'), 'Bitiş Tarihi') ?>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 border-top">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-sm btn-primary text-white" id="btnApplyAparatCustomDate">
                    <i class="bx bx-check me-1"></i> Uygula
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= Helper::assetVersion('views/aparat-takip/js/dashboard.js') ?>"></script>
