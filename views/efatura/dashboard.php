<?php
\App\Service\Gate::authorizeOrDie('efatura/dashboard');
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Fatura Dashboard';
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
.stat-card-incoming::before { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }
.stat-card-outgoing::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card-kdv::before { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.stat-card-payment::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

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
    font-size: 12px;
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

/* KDV Vurgu Rozeti */
.kdv-hero-badge {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.kdv-payable {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.25);
}
.kdv-carried {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.25);
}

/* Dönem Hızlı Filtre Butonları */
.period-btn-group .btn {
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #475569;
    transition: all .15s ease;
}
.period-btn-group .btn:hover {
    background: #f8fafc;
    color: #1e293b;
}
.period-btn-group .btn.active {
    background: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
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

/* Loading Overlay */
.dash-loading {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 20;
    border-radius: 12px;
    backdrop-filter: blur(1px);
}
</style>

<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık & Hızlı Filtre Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-xl-5 col-lg-4 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-pie-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">E-Fatura & Finans Dashboard</h4>
                <p class="text-muted mb-0 font-size-12">Gelen/Giden fatura matrahları, KDV dengesi ve operasyonel göstergeler</p>
            </div>
        </div>

        <div class="col-xl-7 col-lg-8 col-12 d-flex align-items-center justify-content-lg-end gap-2 flex-wrap mt-2 mt-lg-0">
            <!-- Hızlı Dönem Butonları -->
            <div class="btn-group period-btn-group shadow-sm" role="group" id="quickPeriodGroup">
                <button type="button" class="btn active" data-period="this_month">Bu Ay</button>
                <button type="button" class="btn" data-period="last_month">Geçen Ay</button>
                <button type="button" class="btn" data-period="last_3_months">Son 3 Ay</button>
                <button type="button" class="btn" data-period="this_year">Bu Yıl (<?= date('Y') ?>)</button>
                <button type="button" class="btn" data-period="all">Tümü</button>
            </div>

            <!-- Özel Tarih Seçici -->
            <div class="d-flex align-items-center bg-white border rounded-3 px-2 py-1 shadow-sm gap-1 date-filter-box" style="border-color: #cbd5e1 !important;" title="Özel Tarih Aralığı">
                <i class="bx bx-calendar text-primary font-size-15"></i>
                <input type="text" id="dashStartDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 82px; cursor: pointer;" placeholder="Başlangıç" readonly>
                <span class="text-muted font-size-12">-</span>
                <input type="text" id="dashEndDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 82px; cursor: pointer;" placeholder="Bitiş" readonly>
                <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-danger" id="btnClearDates" title="Tarihi Sıfırla" style="display: none;">
                    <i class="bx bx-x font-size-14"></i>
                </button>
            </div>

            <button type="button" class="btn btn-primary btn-sm px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-sm text-white" id="btnRefreshDashboard" title="Verileri Yenile">
                <i class="bx bx-refresh font-size-16"></i> <span class="d-none d-sm-inline">Yenile</span>
            </button>
        </div>
    </div>

    <!-- 2. ANA ÖZET VE KDV DENGESİ 4 KPI KARTI -->
    <div class="row g-3 mb-3" id="kpiCardsContainer" style="position: relative;">
        <!-- Kart 1: TOPLAM GELEN FATURALAR (ALIŞ / GİDER / İNDİRİLECEK KDV) -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="dashboard-stat-card stat-card-incoming h-100 p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-title">GELEN FATURALAR (ALIŞ)</span>
                        <div class="stat-icon-box bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-download"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <div class="stat-main-value text-info" id="kpi_gelen_tutar">0,00 ₺</div>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-size-11 fw-bold" id="kpi_gelen_adet">0 Adet</span>
                    </div>
                </div>
                <div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Toplam Matrah:</span>
                        <span class="stat-sub-val" id="kpi_gelen_matrah">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Toplam KDV (İndirilecek):</span>
                        <span class="stat-sub-val text-info fw-bold" id="kpi_gelen_kdv">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row mb-0 pb-0">
                        <span class="stat-sub-label font-size-11">Kabul: <strong id="kpi_gelen_kabul" class="text-success">0</strong> | Red: <strong id="kpi_gelen_red" class="text-danger">0</strong></span>
                        <a href="index.php?p=efatura/gelen-list" class="text-info font-size-11 fw-semibold text-decoration-none">Detaylar <i class="bx bx-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: TOPLAM GİDEN FATURALAR (SATIŞ / GELİR / HESAPLANAN KDV) -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="dashboard-stat-card stat-card-outgoing h-100 p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-title">GİDEN FATURALAR (SATIŞ)</span>
                        <div class="stat-icon-box bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-upload"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <div class="stat-main-value text-success" id="kpi_giden_tutar">0,00 ₺</div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11 fw-bold" id="kpi_giden_adet">0 Adet</span>
                    </div>
                </div>
                <div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Toplam Matrah:</span>
                        <span class="stat-sub-val" id="kpi_giden_matrah">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Toplam KDV (Hesaplanan):</span>
                        <span class="stat-sub-val text-success fw-bold" id="kpi_giden_kdv">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row mb-0 pb-0">
                        <span class="stat-sub-label font-size-11">E-Fat: <strong id="kpi_giden_efat">0</strong> | E-Arş: <strong id="kpi_giden_ears">0</strong></span>
                        <a href="index.php?p=efatura/giden-list" class="text-success font-size-11 fw-semibold text-decoration-none">Detaylar <i class="bx bx-chevron-right"></i></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: NET KDV DENGESİ (ÖDENECEK / DEVREDEN KDV) -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="dashboard-stat-card stat-card-kdv h-100 p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-title">NET KDV DENGESİ</span>
                        <div class="stat-icon-box bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-calculator"></i>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="kdv-hero-badge kdv-payable" id="kpi_kdv_status_box">
                            <i class="bx bx-info-circle fs-5" id="kpi_kdv_status_icon"></i>
                            <div>
                                <div class="font-size-11 text-uppercase fw-semibold" id="kpi_kdv_status_label">ÖDENECEK KDV</div>
                                <div class="font-size-18 fw-bolder" id="kpi_kdv_status_value">0,00 ₺</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Hesaplanan KDV (Giden):</span>
                        <span class="stat-sub-val" id="kpi_sub_hesaplanan_kdv">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">İndirilecek KDV (Gelen):</span>
                        <span class="stat-sub-val" id="kpi_sub_indirilecek_kdv">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row mb-0 pb-0">
                        <span class="stat-sub-label">Net Matrah Farkı:</span>
                        <span class="stat-sub-val font-size-12" id="kpi_net_matrah_farki">0,00 ₺</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TAHSİLAT & BAKİYE DURUMU -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="dashboard-stat-card stat-card-payment h-100 p-3 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="stat-title">TAHSİLAT & FİNANS</span>
                        <div class="stat-icon-box bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-wallet-alt"></i>
                        </div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <div class="stat-main-value text-primary" id="kpi_tahsil_edilen">0,00 ₺</div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-11 fw-bold" id="kpi_tahsilat_orani">%0</span>
                    </div>
                </div>
                <div>
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" id="kpi_tahsilat_progress"></div>
                    </div>
                    <div class="stat-sub-row">
                        <span class="stat-sub-label">Kalan / Açık Tahsilat:</span>
                        <span class="stat-sub-val text-danger fw-bold" id="kpi_kalan_tahsilat">0,00 ₺</span>
                    </div>
                    <div class="stat-sub-row mb-0 pb-0">
                        <span class="stat-sub-label">Bekleyen Taslak:</span>
                        <span class="stat-sub-val font-size-12" id="kpi_taslak_ozet">0 Adet (0,00 ₺)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. GRAFİKLER BÖLÜMÜ -->
    <div class="row g-3 mb-3">
        <!-- Grafik 1: Aylık Gelen & Giden Fatura Matrah ve Tutar Karşılaştırması -->
        <div class="col-12 col-xl-8">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="bx bx-bar-chart-alt-2 font-size-18"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Aylık Fatura Trendi & Matrah Karşılaştırması</h5>
                            <p class="text-muted mb-0 font-size-11">Gelen Alış ve Giden Satış faturalarının aylık hacim dağılımı</p>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11"><i class="bx bx-up-arrow-alt"></i> Giden (Satış)</span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-size-11"><i class="bx bx-down-arrow-alt"></i> Gelen (Alış)</span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="monthlyTrendChart" style="min-height: 320px;"></div>
                </div>
            </div>
        </div>

        <!-- Grafik 2: Aylık KDV Akışı & Dengesi -->
        <div class="col-12 col-xl-4">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-warning-subtle text-warning rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="bx bx-line-chart font-size-18"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Aylık Net KDV Trendi</h5>
                            <p class="text-muted mb-0 font-size-11">Hesaplanan vs İndirilecek KDV</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="kdvTrendChart" style="min-height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. İKİNCİL GRAFİKLER & CARİ ANALİZLERİ -->
    <div class="row g-3 mb-3">
        <!-- Grafik 3: KDV Oranlarına Göre Matrah Dağılımı (%20, %10, %1 vb.) -->
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="bx bx-pie-chart font-size-18"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">KDV Oran Dağılımı</h5>
                            <p class="text-muted mb-0 font-size-11">%20, %10, %1 matrah oranları</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div id="kdvRatesChart" style="min-height: 260px;"></div>
                </div>
            </div>
        </div>

        <!-- Tablo: En Çok Satış Yapılan İlk 5 Müşteri (Giden) -->
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="bx bx-user-check font-size-18"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Top 5 Müşteri (Satış)</h5>
                            <p class="text-muted mb-0 font-size-11">En yüksek ciro yapılan müşteriler</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Cari / VKN</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody id="topGidenCarilerBody">
                                <tr><td colspan="3" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tablo: En Çok Alış Yapılan İlk 5 Tedarikçi (Gelen) -->
        <div class="col-12 col-md-12 col-xl-4">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                            <i class="bx bx-store font-size-18"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Top 5 Tedarikçi (Alış)</h5>
                            <p class="text-muted mb-0 font-size-11">En çok fatura gelen tedarikçiler</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tedarikçi / VKN</th>
                                    <th class="text-center">Adet</th>
                                    <th class="text-end">Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody id="topGelenCarilerBody">
                                <tr><td colspan="3" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. SON FATURALAR HIZLI AKIŞ LİSTESİ -->
    <div class="row g-3 mb-4">
        <!-- Son Kesilen Giden Faturalar (5) -->
        <div class="col-12 col-lg-6">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bx-send text-success font-size-18"></i>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son Kesilen Giden Faturalar</h5>
                    </div>
                    <a href="index.php?p=efatura/giden-list" class="btn btn-sm btn-subtle-primary rounded-pill px-2.5 py-1 font-size-11">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fatura No / Tarih</th>
                                    <th>Alıcı Ünvan</th>
                                    <th class="text-end">Tutar</th>
                                    <th class="text-center">Durum</th>
                                </tr>
                            </thead>
                            <tbody id="recentGidenBody">
                                <tr><td colspan="4" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son Alınan Gelen Faturalar (5) -->
        <div class="col-12 col-lg-6">
            <div class="card chart-card h-100 mb-0">
                <div class="card-header bg-transparent border-0 px-3 pt-3 pb-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bx bx-download text-info font-size-18"></i>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Son Gelen Faturalar</h5>
                    </div>
                    <a href="index.php?p=efatura/gelen-list" class="btn btn-sm btn-subtle-info rounded-pill px-2.5 py-1 font-size-11">Tümünü Gör</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dashboard table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fatura No / Tarih</th>
                                    <th>Gönderici Ünvan</th>
                                    <th class="text-end">Tutar</th>
                                    <th class="text-center">Ticari Yanıt</th>
                                </tr>
                            </thead>
                            <tbody id="recentGelenBody">
                                <tr><td colspan="4" class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</td></tr>
                            </tbody>
                        </table>
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
<!-- Dashboard Scripti -->
<script src="views/efatura/js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>"></script>
