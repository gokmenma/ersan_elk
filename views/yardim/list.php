<?php
/**
 * Destek Talepleri Listesi - Yönetici Paneli
 */
use App\Service\Gate;

if (!(Gate::allows('admin_destek_talebi') || Gate::isSuperAdmin())) {
    echo '<script>window.location.href = "?p=yardim/user-list";</script>';
    return;
}

$maintitle = 'Ana Sayfa';
$title = 'Destek Talepleri';
?>
<script>try { document.documentElement.classList.toggle('yardim-summary-hidden', localStorage.getItem('yardim_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.yardim-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

/* Modern Kart ve Tablo Stilleri */
.summary-kpi-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05) !important;
}
.summary-kpi-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.summary-kpi-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    line-height: 1;
}
.summary-kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.summary-kpi-subtext {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
}
.summary-pill-btn {
    font-size: 11px !important;
    height: 24px !important;
    line-height: 1 !important;
    padding: 0 10px !important;
    font-weight: 600 !important;
    border-radius: 20px !important;
    transition: all 0.2s ease;
}

/* Modern Subtle Renkli Butonlar */
.btn-subtle-primary {
    background-color: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    transition: all 0.18s ease;
}
.btn-subtle-primary:hover, .btn-subtle-primary:focus, .btn-subtle-primary.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}

.btn-subtle-success {
    background-color: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    transition: all 0.18s ease;
}
.btn-subtle-success:hover, .btn-subtle-success:focus, .btn-subtle-success.active {
    background-color: #16a34a !important;
    color: #ffffff !important;
    border-color: #16a34a !important;
    box-shadow: 0 2px 5px rgba(22, 163, 74, 0.25);
}

.btn-subtle-danger {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    transition: all 0.18s ease;
}
.btn-subtle-danger:hover, .btn-subtle-danger:focus, .btn-subtle-danger.active {
    background-color: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25);
}

.btn-subtle-warning {
    background-color: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
    transition: all 0.18s ease;
}
.btn-subtle-warning:hover, .btn-subtle-warning:focus, .btn-subtle-warning.active {
    background-color: #d97706 !important;
    color: #ffffff !important;
    border-color: #d97706 !important;
    box-shadow: 0 2px 5px rgba(217, 119, 6, 0.25);
}

.btn-subtle-info {
    background-color: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
    transition: all 0.18s ease;
}
.btn-subtle-info:hover, .btn-subtle-info:focus, .btn-subtle-info.active {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.25);
}

.btn-subtle-secondary {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.18s ease;
}
.btn-subtle-secondary:hover, .btn-subtle-secondary:focus {
    background-color: #475569;
    color: #ffffff !important;
    border-color: #475569;
    box-shadow: 0 2px 5px rgba(71, 85, 105, 0.25);
}

.top-action-btn {
    font-size: 13px;
    padding: 7px 14px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.top-icon-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.15rem;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
}
.top-icon-btn:hover {
    background-color: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}

/* Status Filter Buttons (Pills) */
.status-filter-group {
    background: #f8fafc;
    padding: 3px;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 2px;
}
.status-filter-group .btn-check + .btn {
    margin-bottom: 0 !important;
    border: none !important;
    border-radius: 50px !important;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 5px 12px;
    color: #64748b;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    line-height: normal;
}
.status-filter-group .btn-check + .btn i {
    font-size: 0.9rem;
}
.status-filter-group .btn-check + .btn:hover {
    background: rgba(0, 0, 0, 0.04);
    color: #1e293b;
}
.status-filter-group .btn-check:checked + .btn {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}
.status-filter-group .btn-check:checked + .btn[for="filter-all"] { background: #64748b !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-acik"] { background: #f59e0b !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-isleme-alindi"] { background: #0284c7 !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-yanitlandi"] { background: #10b981 !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-personel-yaniti"] { background: #3b82f6 !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-cozuldu"] { background: #16a34a !important; color: #fff !important; }
.status-filter-group .btn-check:checked + .btn[for="filter-kapali"] { background: #dc2626 !important; color: #fff !important; }

/* Tablo Tipografi ve Düzen */
#tickets-table {
    font-size: 13px !important;
}
#tickets-table thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#tickets-table tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}
#tickets-table tbody tr.ticket-row {
    cursor: pointer !important;
    transition: background-color 0.15s ease;
}
#tickets-table tbody tr.ticket-row.ticket-row-selected > * {
    background-color: rgba(37, 99, 235, 0.08) !important;
}
#tickets-table tbody tr.ticket-row.ticket-row-selected > td:first-child {
    box-shadow: inset 4px 0 0 #2563eb;
}

/* Tablo Satır Aksiyon Butonları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.table-action-btn:hover {
    transform: translateY(-1px);
}
.action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    justify-content: center;
}

/* Timeline Stilleri */
.timeline-container {
    position: relative;
    padding-left: 3rem;
    margin-top: 1rem;
}
.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 1.75rem;
    bottom: -1rem;
    width: 2px;
    background: #e2e8f0;
}
.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}
.timeline-dot {
    position: absolute;
    left: -2.25rem;
    top: 0.25rem;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    box-shadow: 0 0 0 4px #fff;
}
.timeline-dot i { font-size: 0.7rem; color: #fff; }
.timeline-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    transition: all 0.2s ease;
}
.timeline-content:hover {
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
    border-color: #e2e8f0;
}
.timeline-time {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 600;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}
.timeline-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.25rem;
}
.timeline-desc {
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.4;
}
.timeline-duration {
    font-size: 0.7rem;
    background: #f1f5f9;
    color: #475569;
    padding: 2px 8px;
    border-radius: 100px;
    display: inline-block;
    margin-top: 0.5rem;
    font-weight: 600;
}
.dot-create { border-color: #3b82f6; background: #3b82f6; }
.dot-approval { border-color: #f59e0b; background: #f59e0b; }
.dot-reply { border-color: #10b981; background: #10b981; }
.dot-personel { border-color: #0284c7; background: #0284c7; }
.dot-close { border-color: #dc2626; background: #dc2626; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-support fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Destek Talepleri</h4>
                <p class="text-muted mb-0 font-size-12">Personel destek talepleri, yanıtlar ve çözüm süreçleri yönetimi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- Kullanıcı Paneline Git -->
            <a href="index.php?p=yardim/user-list" class="btn btn-outline-primary bg-white top-action-btn shadow-sm">
                <i class="bx bx-user-voice font-size-16"></i> Kullanıcı Talepleri
            </a>

            <!-- İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-file me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownPrint">
                        <i class="bx bx-printer me-2 font-size-16 text-secondary"></i> Listeyi Yazdır
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=yardim/user-list">
                        <i class="bx bx-message-square-add me-2 text-primary font-size-16"></i> Yeni Talep Oluştur
                    </a>
                </div>
            </div>

            <!-- Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM TALEP -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM TALEP</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-support"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat-toplam">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat-sub-toplam">Tüm Destek Talepleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: BEKLEYEN YANIT -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BEKLEYEN YANIT</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-time-five"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat-bekleyen">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Açık / Personel Yanıtı</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="acik">
                            <i class="bx bx-time-five"></i> Bekleyen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: İŞLEMDE OLANLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İŞLEMDE</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-loader-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat-islemde">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold">İnceleme Aşamasında</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="isleme_alindi">
                            <i class="bx bx-loader"></i> İşlemde
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: ÇÖZÜLEN & YANITLANAN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ÇÖZÜLEN & YANITLANAN</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-double"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat-cozuldu">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Yanıtlanan: <span id="stat-yanitlanan" class="fw-semibold text-success">0</span> | Kapalı: <span id="stat-kapali" class="fw-semibold text-danger">0</span></span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="cozuldu">
                            <i class="bx bx-check-circle"></i> Çözüldü
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Destek Talepleri Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="yardimListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Destek Talepleri Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Canlı filtreleme, durum güncellemeleri ve talep yönetimi</p>
                </div>
            </div>

            <!-- Durum Filtreleme Pills -->
            <div class="status-filter-group" role="group">
                <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="" checked>
                <label class="btn" for="filter-all"><i class="bx bx-grid-alt"></i> Tümü</label>
                
                <input type="radio" class="btn-check" name="status-filter" id="filter-acik" value="acik">
                <label class="btn" for="filter-acik"><i class="bx bx-loader-circle"></i> Açık</label>

                <input type="radio" class="btn-check" name="status-filter" id="filter-isleme-alindi" value="isleme_alindi">
                <label class="btn" for="filter-isleme-alindi"><i class="bx bx-loader bx-spin"></i> İşlemde</label>
                
                <input type="radio" class="btn-check" name="status-filter" id="filter-yanitlandi" value="yanitlandi">
                <label class="btn" for="filter-yanitlandi"><i class="bx bx-check-circle"></i> Yanıtlandı</label>
                
                <input type="radio" class="btn-check" name="status-filter" id="filter-personel-yaniti" value="personel_yaniti">
                <label class="btn" for="filter-personel-yaniti"><i class="bx bx-user-voice"></i> Personel Yanıtı</label>

                <input type="radio" class="btn-check" name="status-filter" id="filter-cozuldu" value="cozuldu">
                <label class="btn" for="filter-cozuldu"><i class="bx bx-check-square"></i> Çözüldü</label>

                <input type="radio" class="btn-check" name="status-filter" id="filter-kapali" value="kapali">
                <label class="btn" for="filter-kapali"><i class="bx bx-lock-alt"></i> Kapalı</label>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-primary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yenile</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="tickets-table" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string" style="width: 100px;">REF NO</th>
                            <th data-filter="string">PERSONEL</th>
                            <th data-filter="string">KONU</th>
                            <th data-filter="select" style="width: 130px;">KATEGORİ</th>
                            <th data-filter="none" class="text-center" style="width: 70px;">MESAJ</th>
                            <th data-filter="none" class="text-center" style="width: 70px;">DOSYA</th>
                            <th data-filter="select" style="width: 110px;">ÖNCELİK</th>
                            <th data-filter="date" style="width: 140px;">SON GÜNCELLEME</th>
                            <th data-filter="select" style="width: 140px;">DURUM</th>
                            <th data-filter="none" class="text-center" style="width: 130px;">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Talep Detay Modalı (Ticket Detail Modal) -->
<div class="modal fade" id="ticketDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-dark text-white p-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-white bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bx bx-message-square-dots fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 font-size-15 fw-bold" id="detail-konu">-</h5>
                        <p class="text-white-50 mb-0 font-size-12" id="detail-ref">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-4 border-end bg-light p-4">
                        <h6 class="text-uppercase fw-bold text-muted font-size-11 mb-3" style="letter-spacing: 0.5px;">TALEP BİLGİLERİ</h6>
                        <div class="mb-3">
                            <label class="text-muted font-size-11 d-block mb-1">Talep Sahibi</label>
                            <p class="fw-bold mb-0 text-dark font-size-13" id="detail-personel">-</p>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted font-size-11 d-block mb-1">Kategori</label>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1 font-size-11 fw-semibold" id="detail-kategori">-</span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted font-size-11 d-block mb-1">Öncelik</label>
                            <div id="detail-oncelik">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted font-size-11 d-block mb-1">Durum</label>
                            <div id="detail-durum">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted font-size-11 d-block mb-1">Tarih</label>
                            <p class="font-size-12 mb-0 text-secondary" id="detail-tarih">-</p>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top d-flex flex-column gap-2" id="admin-actions">
                            <button type="button" class="btn btn-subtle-info btn-sm w-100 rounded-3 fw-semibold" id="btn-process-ticket">
                                <i class="bx bx-loader me-1"></i> İşleme Al
                            </button>
                            <button type="button" class="btn btn-subtle-success btn-sm w-100 rounded-3 fw-semibold" id="btn-resolve-ticket">
                                <i class="bx bx-check-circle me-1"></i> Çözüldü Olarak İşaretle
                            </button>
                            <button type="button" class="btn btn-subtle-danger btn-sm w-100 rounded-3 fw-semibold" id="btn-close-ticket">
                                <i class="bx bx-lock-alt me-1"></i> Talebi Kapat
                            </button>
                            <a href="#" class="btn btn-dark btn-sm w-100 rounded-3 fw-semibold" id="btn-full-view">
                                <i class="bx bx-expand-alt me-1"></i> Tam Detayı Gör
                            </a>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="p-4" style="height: 450px; overflow-y: auto; background: #fff;" id="chat-container">
                            <div id="chat-loading" class="text-center py-5">
                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                <div class="text-muted font-size-12 mt-2">Mesajlar yükleniyor...</div>
                            </div>
                            <div id="chat-messages" class="d-flex flex-column gap-3">
                                <!-- Mesajlar buraya eklenecek -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Zaman Çizelgesi Modalı (Timeline Modal) -->
<div class="modal fade" id="timelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom pb-3 pt-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-primary-subtle text-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-history font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0">İşlem Zaman Çizelgesi</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <div id="timeline-info" class="mb-3 p-2.5 bg-light rounded-3">
                    <div class="text-muted font-size-11 fw-bold">Ref No: <span id="timeline-ref" class="text-primary font-monospace">-</span></div>
                    <div class="text-dark fw-bold font-size-13 mt-1" id="timeline-konu">-</div>
                </div>
                
                <div id="timeline-loading" class="text-center py-4">
                    <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                    <div class="text-muted font-size-12 mt-2">Geçmiş yükleniyor...</div>
                </div>
                
                <div id="timeline-content" class="timeline-container" style="display: none;">
                    <!-- Timeline maddeleri buraya eklenecek -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 1. Özet Kartları Açma/Kapama (AGENTS.md Standardı)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('yardim-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('yardim-summary-hidden');
        $('html').toggleClass('yardim-summary-hidden', willHide);
        localStorage.setItem('yardim_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    // 2. DataTables Tablosunu Başlat
    let tableOptions = {
        order: [[7, 'desc']],
        columns: [
            { 
                data: 'ref_no',
                className: 'fw-semibold text-primary font-monospace',
                width: '100px'
            },
            { 
                data: 'personel_adi',
                render: function(data, type, row) {
                    let dept = row.departman ? `<div class="text-muted font-size-11">${row.departman}</div>` : '';
                    return `<div class="fw-semibold text-dark">${data || '-'}</div>${dept}`;
                }
            },
            { 
                data: 'konu',
                className: 'fw-medium text-dark'
            },
            { 
                data: 'kategori',
                render: function(data) {
                    return `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5 font-size-11">${data || 'Genel'}</span>`;
                }
            },
            { 
                data: 'mesaj_sayisi', 
                className: 'text-center',
                width: '70px',
                render: data => `<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 fw-bold font-size-11"><i class="bx bx-chat me-1"></i>${data || 0}</span>`
            },
            { 
                data: 'dosya_sayisi', 
                className: 'text-center',
                width: '70px',
                render: data => {
                    if(!data || data == 0) return `<span class="text-muted opacity-50 font-size-11">-</span>`;
                    return `<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 fw-bold font-size-11"><i class="bx bx-paperclip me-1"></i>${data}</span>`;
                }
            },
            { 
                data: 'oncelik',
                width: '110px',
                render: function(data) {
                    let badgeClass = 'bg-warning-subtle text-warning border-warning-subtle';
                    let icon = '<i class="bx bx-minus-circle me-1"></i>';
                    let text = (data || 'ORTA').toUpperCase();

                    if(data === 'yuksek') {
                        badgeClass = 'bg-danger-subtle text-danger border-danger-subtle';
                        icon = '<i class="bx bxs-error-circle me-1"></i>';
                    } else if(data === 'orta') {
                        badgeClass = 'bg-warning-subtle text-warning border-warning-subtle';
                        icon = '<i class="bx bx-minus-circle me-1"></i>';
                    } else if(data === 'dusuk') {
                        badgeClass = 'bg-info-subtle text-info border-info-subtle';
                        icon = '<i class="bx bx-chevron-down-circle me-1"></i>';
                    }
                    
                    return `<span class="badge ${badgeClass} border rounded-pill px-2.5 py-1 font-size-11 fw-semibold d-inline-flex align-items-center">${icon} ${text}</span>`;
                }
            },
            { 
                data: 'guncelleme_tarihi',
                width: '140px',
                className: 'font-size-12 text-secondary'
            },
            { 
                data: 'durum', 
                width: '140px',
                render: function(data, type, row) {
                    if ((row.onay_durumu || '') === 'beklemede') {
                        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 font-size-11 fw-semibold"><i class="bx bx-time me-1"></i> ONAY BEKLİYOR</span>';
                    }

                    let badgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                    let text = (data || 'ACIK').toUpperCase().replace('_', ' ');
                    let icon = '<i class="bx bx-loader-circle me-1"></i>';
                    
                    if(data === 'acik') {
                        badgeClass = 'bg-warning-subtle text-warning border-warning-subtle';
                        icon = '<i class="bx bx-loader-circle me-1"></i>';
                    } else if(data === 'yanitlandi') {
                        badgeClass = 'bg-success-subtle text-success border-success-subtle';
                        icon = '<i class="bx bx-check-circle me-1"></i>';
                    } else if(data === 'personel_yaniti') {
                        badgeClass = 'bg-primary-subtle text-primary border-primary-subtle';
                        icon = '<i class="bx bx-user-voice me-1"></i>';
                    } else if(data === 'isleme_alindi') { 
                        badgeClass = 'bg-info-subtle text-info border-info-subtle'; 
                        text = 'İŞLEMDE'; 
                        icon = '<i class="bx bx-loader bx-spin me-1"></i>'; 
                    } else if(data === 'cozuldu') { 
                        badgeClass = 'bg-success-subtle text-success border-success-subtle'; 
                        text = 'ÇÖZÜLDÜ'; 
                        icon = '<i class="bx bx-check-square me-1"></i>'; 
                    } else if(data === 'kapali') {
                        badgeClass = 'bg-danger-subtle text-danger border-danger-subtle';
                        icon = '<i class="bx bx-lock-alt me-1"></i>';
                    }

                    return `<span class="badge ${badgeClass} border rounded-pill px-2.5 py-1 font-size-11 fw-semibold show-timeline-btn d-inline-flex align-items-center" data-id="${row.id}" data-ref="${row.ref_no}" data-konu="${row.konu}" style="cursor: pointer;" title="İşlem geçmişini gör">${icon} ${text}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                width: '130px',
                orderable: false,
                searchable: false,
                render: function(data) {
                    let html = `<div class="action-btn-group">`;
                    html += `<a href="?p=yardim/view&id=${data.encrypted_id || data.id}" class="btn btn-sm btn-subtle-primary table-action-btn" title="Detay Görüntüle"><i class="bx bx-show-alt"></i></a>`;
                    
                    if (data.durum !== 'kapali') {
                        if (data.durum !== 'isleme_alindi') {
                            html += `<button type="button" class="btn btn-sm btn-subtle-info table-action-btn btn-process-ticket-direct" data-id="${data.id}" title="İşleme Al"><i class="bx bx-loader"></i></button>`;
                        }
                        if (data.durum !== 'cozuldu') {
                            html += `<button type="button" class="btn btn-sm btn-subtle-success table-action-btn btn-resolve-ticket-direct" data-id="${data.id}" title="Çözüldü Olarak İşaretle"><i class="bx bx-check-circle"></i></button>`;
                        }
                        html += `<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-close-ticket-direct" data-id="${data.id}" title="Talebi Kapat"><i class="bx bx-lock-alt"></i></button>`;
                    }
                    
                    html += `</div>`;
                    return html;
                }
            }
        ],
        createdRow: function(row, data, dataIndex) {
            $(row).addClass('ticket-row');
            $(row).attr('data-id', data.id);
            $(row).attr('data-encrypted-id', data.encrypted_id);
        }
    };

    if (typeof applyLengthStateSave === 'function') {
        tableOptions = applyLengthStateSave(tableOptions);
    }

    let table = destroyAndInitDataTable('#tickets-table', tableOptions);

    // Satır Tıklama (Detay Modalı Açma) - Butonlar ve Linkler Hariç
    $('#tickets-table tbody').on('click', 'tr.ticket-row', function(e) {
        if ($(e.target).closest('a, button, .badge, .table-action-btn').length) return;

        $('#tickets-table tbody tr.ticket-row')
            .removeClass('ticket-row-selected')
            .attr('aria-selected', 'false');
        $(this)
            .addClass('ticket-row-selected')
            .attr('aria-selected', 'true');
        
        const id = $(this).data('id');
        const encryptedId = $(this).data('encrypted-id');
        openTicketDetail(id, encryptedId);
    });

    // Zaman Çizelgesi Tıklama
    $(document).on('click', '.show-timeline-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        showTimeline($(this).data('id'), $(this).data('ref'), $(this).data('konu'));
    });

    // Biletleri AJAX ile Yükleme
    function loadTickets(status = '') {
        $.post('views/yardim/api.php', { action: 'get-tickets-admin', status: status, limit: 10000 }, function(res) {
            if(res.success) {
                table.clear().rows.add(res.tickets || []).draw();
                
                // İstatistikleri Güncelle
                const stats = res.stats || {};
                $('#stat-toplam').text(stats.toplam || 0);
                $('#stat-bekleyen').text(stats.bekleyen || 0);
                $('#stat-islemde').text(stats.islemde || 0);
                $('#stat-yanitlanan').text(stats.yanitlanan || 0);
                $('#stat-cozuldu').text(stats.cozuldu || 0);
                $('#stat-kapali').text(stats.kapali || 0);
            }
        });
    }

    // İlk yükleme
    loadTickets();

    // Üst Filtre Radyo Butonları
    $('input[name="status-filter"]').on('change', function() {
        const val = $(this).val();
        // Hızlı filtre kartlarındaki butonları da senkronize et
        $('.status-quick-filter').removeClass('active');
        $(`.status-quick-filter[data-status="${val}"]`).addClass('active');
        loadTickets(val);
    });

    // KPI Kartları İçindeki Hızlı Filtre Butonları
    $('.status-quick-filter').on('click', function() {
        const status = $(this).data('status');
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');

        // Radyo butonunu da senkronize et
        if (status === '' || status === 'all') {
            $('#filter-all').prop('checked', true);
            loadTickets('');
        } else {
            $(`input[name="status-filter"][value="${status}"]`).prop('checked', true);
            loadTickets(status);
        }
    });

    // Yenile Butonu
    $('#btnHeaderRefresh').on('click', function() {
        const currentStatus = $('input[name="status-filter"]:checked').val() || '';
        loadTickets(currentStatus);
    });

    // Yazdır Butonları
    $('#btnHeaderPrint, #btnDropdownPrint').on('click', function() {
        window.print();
    });

    // Excel Aktar Butonları
    $('#btnHeaderExportExcel, #btnDropdownExportExcel').on('click', function() {
        if (table.button && table.button('.buttons-excel').length) {
            table.button('.buttons-excel').trigger();
        } else {
            // HTML table export fallback
            let tableEl = document.getElementById('tickets-table');
            if (!tableEl) return;
            let rows = tableEl.querySelectorAll('tr');
            let csv = [];
            rows.forEach(function(row) {
                let cols = row.querySelectorAll('td, th');
                let rowData = [];
                // Skip the last column (actions)
                for (let i = 0; i < cols.length - 1; i++) {
                    let text = cols[i].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
                    rowData.push('"' + text + '"');
                }
                csv.push(rowData.join(';'));
            });
            let csvContent = "\uFEFF" + csv.join("\n");
            let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.setAttribute("download", "Destek_Talepleri_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });

    // Modal İçi Talebi Kapat Aksiyonu
    $('#btn-close-ticket').on('click', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu destek talebi kapatılacaktır!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Evet, Kapat',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'kapali' }, function(res) {
                    if(res.success) {
                        Swal.fire('Kapatıldı!', 'Talep başarıyla kapatıldı.', 'success');
                        $('#ticketDetailModal').modal('hide');
                        loadTickets($('input[name="status-filter"]:checked').val());
                    } else {
                        Swal.fire('Hata!', res.message, 'error');
                    }
                });
            }
        });
    });

    // Modal İçi İşleme Al Aksiyonu
    $('#btn-process-ticket').on('click', function() {
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'isleme_alindi' }, function(res) {
            if(res.success) {
                Swal.fire('Bilgi', 'Talep işleme alındı olarak işaretlendi.', 'info');
                $('#ticketDetailModal').modal('hide');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata!', res.message, 'error');
            }
        });
    });

    // Modal İçi Çözüldü Aksiyonu
    $('#btn-resolve-ticket').on('click', function() {
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'cozuldu' }, function(res) {
            if(res.success) {
                Swal.fire('Başarılı', 'Talep çözüldü olarak işaretlendi.', 'success');
                $('#ticketDetailModal').modal('hide');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata!', res.message, 'error');
            }
        });
    });

    // Tablo Doğrudan İşlem Butonları (Direct Actions)
    $(document).on('click', '.btn-process-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'isleme_alindi' }, function(res) {
            if(res.success) {
                Swal.fire('Bilgi', 'Talep işleme alındı.', 'info');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-resolve-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'cozuldu' }, function(res) {
            if(res.success) {
                Swal.fire('Başarılı', 'Talep çözüldü olarak işaretlendi.', 'success');
                loadTickets($('input[name="status-filter"]:checked').val());
            } else {
                Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-close-ticket-direct', function(e) {
        e.preventDefault(); e.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Talebi Kapat?',
            text: "Bu talep kapatılacaktır.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Evet, Kapat',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/yardim/api.php', { action: 'update-status', bilet_id: id, durum: 'kapali' }, function(res) {
                    if(res.success) {
                        Swal.fire('Başarılı', 'Talep kapatıldı.', 'success');
                        loadTickets($('input[name="status-filter"]:checked').val());
                    } else {
                        Swal.fire('Hata', res.message || 'Hata oluştu.', 'error');
                    }
                });
            }
        });
    });

    // Bilet Detay Modalı
    function openTicketDetail(id, encryptedId) {
        $('#detail-konu').text('Yükleniyor...');
        $('#chat-loading').show();
        $('#chat-messages').empty();
        $('#btn-close-ticket').data('id', id);
        $('#btn-full-view').attr('href', `?p=yardim/view&id=${encryptedId || id}`);
        $('#ticketDetailModal').modal('show');

        $.post('views/yardim/api.php', { action: 'get-ticket-details', bilet_id: id }, function(res) {
            $('#chat-loading').hide();
            if(res.success) {
                const ticket = res.ticket;
                $('#detail-konu').text(ticket.konu);
                $('#detail-ref').text(ticket.ref_no);
                $('#detail-personel').text(ticket.personel_adi || '-');
                $('#detail-kategori').text(ticket.kategori || 'Genel');
                $('#detail-tarih').text(ticket.olusturma_tarihi || '-');
                
                let durumBadgeClass = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                let durumIcon = '<i class="bx bx-loader-circle me-1"></i>';
                let durumText = (ticket.durum || 'ACIK').toUpperCase().replace('_', ' ');

                if(ticket.durum === 'acik') { durumBadgeClass = 'bg-warning-subtle text-warning border-warning-subtle'; durumIcon = '<i class="bx bx-loader-circle me-1"></i>'; }
                if(ticket.durum === 'yanitlandi') { durumBadgeClass = 'bg-success-subtle text-success border-success-subtle'; durumIcon = '<i class="bx bx-check-circle me-1"></i>'; }
                if(ticket.durum === 'personel_yaniti') { durumBadgeClass = 'bg-primary-subtle text-primary border-primary-subtle'; durumIcon = '<i class="bx bx-user-voice me-1"></i>'; }
                if(ticket.durum === 'isleme_alindi') { durumBadgeClass = 'bg-info-subtle text-info border-info-subtle'; durumIcon = '<i class="bx bx-loader bx-spin me-1"></i>'; durumText = 'İŞLEMDE'; }
                if(ticket.durum === 'cozuldu') { durumBadgeClass = 'bg-success-subtle text-success border-success-subtle'; durumIcon = '<i class="bx bx-check-square me-1"></i>'; durumText = 'ÇÖZÜLDÜ'; }
                if(ticket.durum === 'kapali') { durumBadgeClass = 'bg-danger-subtle text-danger border-danger-subtle'; durumIcon = '<i class="bx bx-lock-alt me-1"></i>'; }

                $('#detail-durum').html(`<span class="badge ${durumBadgeClass} border rounded-pill px-2.5 py-1 font-size-11 fw-semibold d-inline-flex align-items-center">${durumIcon} ${durumText}</span>`);

                let oncelikBadgeClass = 'bg-warning-subtle text-warning border-warning-subtle';
                let oncelikIcon = '<i class="bx bx-minus-circle me-1"></i>';
                if(ticket.oncelik === 'yuksek') { oncelikBadgeClass = 'bg-danger-subtle text-danger border-danger-subtle'; oncelikIcon = '<i class="bx bxs-error-circle me-1"></i>'; }
                if(ticket.oncelik === 'dusuk') { oncelikBadgeClass = 'bg-info-subtle text-info border-info-subtle'; oncelikIcon = '<i class="bx bx-chevron-down-circle me-1"></i>'; }
                $('#detail-oncelik').html(`<span class="badge ${oncelikBadgeClass} border rounded-pill px-2.5 py-1 font-size-11 fw-semibold d-inline-flex align-items-center">${oncelikIcon} ${(ticket.oncelik || 'ORTA').toUpperCase()}</span>`);

                if(ticket.durum === 'kapali') {
                    $('#btn-close-ticket, #btn-process-ticket, #btn-resolve-ticket').hide();
                } else {
                    $('#btn-close-ticket').show();
                    if(ticket.durum === 'isleme_alindi') $('#btn-process-ticket').hide(); else $('#btn-process-ticket').show();
                    if(ticket.durum === 'cozuldu') $('#btn-resolve-ticket').hide(); else $('#btn-resolve-ticket').show();
                }

                $('#btn-process-ticket, #btn-resolve-ticket').data('id', ticket.id);

                // Mesajları Çiz
                if(ticket.messages && ticket.messages.length > 0) {
                    let chatHtml = '';
                    ticket.messages.forEach(msg => {
                        const isYonetici = msg.gonderen_tip === 'yonetici';
                        const align = isYonetici ? 'ms-auto bg-dark text-white' : 'me-auto bg-light text-dark border';
                        const name = isYonetici ? 'Destek Ekibi' : msg.gonderen_adi;
                        
                        chatHtml += `
                            <div class="p-3 ${align}" style="max-width: 85%; border-radius: 12px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold font-size-12">${name}</span>
                                    <span class="opacity-75 ms-3 font-size-11">${msg.olusturma_tarihi}</span>
                                </div>
                                <div class="message-text font-size-13">${msg.mesaj.replace(/\n/g, '<br>')}</div>
                                ${msg.dosyalar && msg.dosyalar.length > 0 ? `
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        ${msg.dosyalar.map(file => `<a href="${file}" target="_blank" class="badge bg-primary-subtle text-primary text-decoration-none p-1 px-2 border border-primary-subtle rounded-pill font-size-11"><i class="bx bx-paperclip me-1"></i> Dosya Eki</a>`).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    });
                    $('#chat-messages').html(chatHtml);
                    
                    // Alta kaydır
                    const chatCont = document.getElementById('chat-container');
                    if (chatCont) chatCont.scrollTop = chatCont.scrollHeight;
                }
            }
        });
    }
});

// Zaman Çizelgesi Gösterimi
function showTimeline(id, refNo, konu) {
    $('#timeline-ref').text(refNo);
    $('#timeline-konu').text(konu);
    $('#timeline-loading').show();
    $('#timeline-content').hide().empty();
    $('#timelineModal').modal('show');

    $.post('views/yardim/api.php', { action: 'get-ticket-details', bilet_id: id }, function(res) {
        $('#timeline-loading').hide();
        if(res.success) {
            const ticket = res.ticket;
            let items = [];

            // 1. Oluşturma
            items.push({
                time: ticket.olusturma_tarihi,
                title: 'Talep Oluşturuldu',
                desc: `Talep <b>${ticket.personel_adi}</b> tarafından sisteme kaydedildi.`,
                icon: 'bx bx-plus',
                class: 'dot-create',
                dateObj: new Date(ticket.olusturma_tarihi)
            });

            // 2. Onay (Eğer onaylanmış/reddedilmişse ve tarihi varsa)
            if(ticket.onay_durumu !== 'beklemede' && ticket.onay_tarihi) {
                const statusText = ticket.onay_durumu === 'onaylandi' ? 'Onaylandı' : 'Reddedildi';
                items.push({
                    time: ticket.onay_tarihi,
                    title: `Yönetici Onay: ${statusText}`,
                    desc: `Talep ön onay sürecinden geçti.`,
                    icon: ticket.onay_durumu === 'onaylandi' ? 'bx bx-check' : 'bx bx-x',
                    class: 'dot-approval',
                    dateObj: new Date(ticket.onay_tarihi)
                });
            }

            // 3. Mesajlar
            if(ticket.messages && ticket.messages.length > 0) {
                ticket.messages.forEach(msg => {
                    if(msg.olusturma_tarihi === ticket.olusturma_tarihi) return;

                    const isYonetici = msg.gonderen_tip === 'yonetici';
                    items.push({
                        time: msg.olusturma_tarihi,
                        title: isYonetici ? 'Destek Yanıtı' : 'Personel Mesajı',
                        desc: `<b>${msg.gonderen_adi}</b> tarafından mesaj eklendi.`,
                        icon: isYonetici ? 'bx bx-message-rounded-dots' : 'bx bx-reply',
                        class: isYonetici ? 'dot-reply' : 'dot-personel',
                        dateObj: new Date(msg.olusturma_tarihi)
                    });
                });
            }

            // 4. Kapatma
            if(ticket.durum === 'kapali' && ticket.kapatma_tarihi) {
                items.push({
                    time: ticket.kapatma_tarihi,
                    title: 'Talep Kapatıldı',
                    desc: `Talebiniz <b>${ticket.kapatan_adi || 'Sistem'}</b> tarafından sonlandırıldı.`,
                    icon: 'bx bx-lock',
                    class: 'dot-close',
                    dateObj: new Date(ticket.kapatma_tarihi)
                });
            }

            // Tarihe göre sırala
            items.sort((a, b) => a.dateObj - b.dateObj);

            // Render
            let html = '';
            items.forEach((item, index) => {
                let durationHtml = '';
                if(index > 0) {
                    const diffMs = items[index].dateObj - items[index-1].dateObj;
                    const diffMins = Math.round(diffMs / 60000);
                    if(diffMins < 60) {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${diffMins} dk</span>`;
                    } else if(diffMins < 1440) {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${Math.round(diffMins/60)} saat</span>`;
                    } else {
                        durationHtml = `<span class="timeline-duration"><i class="bx bx-time-five me-1"></i>+${Math.round(diffMins/1440)} gün</span>`;
                    }
                }

                html += `
                    <div class="timeline-item">
                        <div class="timeline-dot ${item.class}">
                            <i class="${item.icon}"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-time">${item.time}</div>
                            <div class="timeline-title">${item.title}</div>
                            <div class="timeline-desc">${item.desc}</div>
                            ${durationHtml}
                        </div>
                    </div>
                `;
            });

            $('#timeline-content').html(html).fadeIn();
        } else {
            $('#timeline-loading').html(`<div class="text-danger">${res.message}</div>`);
        }
    });
}
</script>
