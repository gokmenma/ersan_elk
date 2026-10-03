<?php
\App\Service\Gate::authorizeOrDie('efatura/giden-list');
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Taslak Faturalar';
?>
<script>try { document.documentElement.classList.toggle('efatura-summary-hidden', localStorage.getItem('efatura_taslak_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.efatura-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js"></script>


<?php include 'layouts/breadcrumb.php'; ?>
<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-white rounded-3 border d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px; border-color: #e2e8f0 !important;">
                <i class="bx bx-edit-alt fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Taslak Faturalar</h4>
                <p class="text-muted mb-0 font-size-12">Hazırlanan ve henüz EDM / GİB sistemine gönderilmemiş taslak faturalar</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Fatura Kes Butonu (Primary Mavi) -->
            <a href="index.php?p=efatura/olustur" class="btn btn-primary top-action-btn shadow-sm text-white">
                <i class="bx bx-plus font-size-16"></i> Yeni Fatura Kes
            </a>

            <!-- 2. EDM'den Taslakları Çek Butonu -->
            <button type="button" class="btn btn-outline-primary bg-white top-action-btn shadow-sm" id="btnSyncDrafts">
                <i class="bx bx-refresh font-size-16"></i> EDM'den Taslakları Çek
            </button>

            <!-- 3. Seçilenleri EDM'ye Gönder Butonu (Yeşil) -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white d-none" id="btnBulkSend">
                <i class="bx bx-send font-size-16"></i> Seçilenleri Gönder (<span id="selectedCount">0</span>)
            </button>

            <!-- 4. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center text-primary" id="btnDropdownSyncDrafts">
                        <i class="bx bx-refresh me-2 font-size-16"></i> EDM'den Taslakları Çek
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/giden-list">
                        <i class="bx bx-cloud-upload me-2 text-primary font-size-16"></i> Giden Faturalara Git
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/gelen-list">
                        <i class="bx bx-cloud-download me-2 text-info font-size-16"></i> Gelen Faturalara Git
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/ayarlar">
                        <i class="bx bx-slider-alt me-2 text-warning font-size-16"></i> Entegratör Ayarları
                    </a>
                </div>
            </div>

            <!-- 4. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM TASLAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM TASLAK</span>
                        <div class="summary-kpi-icon bg-light border text-primary">
                            <i class="bx bx-receipt"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_efatura_earsiv">E-Fatura: 0 | E-Arşiv: 0</span>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-0 text-muted status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-belge="">
                            <i class="bx bx-filter"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: E-FATURA TASLAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">E-FATURA TASLAKLARI</span>
                        <div class="summary-kpi-icon" style="background: #eef2ff; border: 1px solid #e0e7ff; color: #4f46e5;">
                            <i class="bx bx-file"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_efatura_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-primary fw-semibold">Kurumsal Mükellefler</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-belge="EFATURA">
                            <i class="bx bx-buildings"></i> E-Fatura
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: E-ARŞİV TASLAK -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">E-ARŞİV TASLAKLARI</span>
                        <div class="summary-kpi-icon" style="background: #f0fdf4; border: 1px solid #dcfce7; color: #10b981;">
                            <i class="bx bx-user-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_earsiv_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Bireysel / Son Kullanıcı</span>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-0 text-muted status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-belge="EARSIV">
                            <i class="bx bx-user"></i> E-Arşiv
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TOPLAM TASLAK TUTARI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM TASLAK TUTARI</span>
                        <div class="summary-kpi-icon" style="background: #eff6ff; border: 1px solid #dbeafe; color: #2563eb;">
                            <i class="bx bx-money"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_tutar">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Bu Ay: <strong id="stat_bu_ay_tutar" class="text-dark">0,00 ₺</strong></span>
                        <span class="badge bg-light text-primary border rounded-pill px-2 py-1 font-size-11 fw-semibold">
                            <?= date('m/Y') ?> Dönemi
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Fatura Listesi Kartı -->
    <div class="card summary-kpi-card mb-3" id="faturaListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-light rounded-3 border d-flex align-items-center justify-content-center text-secondary shadow-sm flex-shrink-0" style="width: 38px; height: 38px; border-color: #e2e8f0 !important;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="status-filter-group d-flex gap-1 flex-wrap"><button type="button" class="btn btn-sm btn-light border status-quick-filter" data-belge="">Tümü</button><button type="button" class="btn btn-sm btn-light border status-quick-filter" data-belge="EFATURA">e-Fatura</button><button type="button" class="btn btn-sm btn-light border status-quick-filter" data-belge="EARSIV">e-Arşiv</button></div>
                        <span class="badge bg-dark rounded-pill font-size-11 px-2 py-0" id="badgeTotalRecords">0</span>
                    </div>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Taslak faturalar EDM'ye gönderildiğinde otomatik olarak Giden Faturalar modülüne taşınır</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu: Tarih Aralığı ve Dışa Aktarma Butonları -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Tarih Aralığı Filtresi (Varsayılan: İçinde Bulunulan Ay) -->
                <div class="d-flex align-items-center bg-white border rounded-3 px-2 py-1 shadow-sm gap-1" style="border-color: #e2e8f0 !important;">
                    <i class="bx bx-calendar text-primary font-size-16"></i>
                    <input type="text" id="filterDateRange" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 175px; cursor: pointer;" placeholder="Tarih Aralığı..." readonly>
                    <button type="button" class="btn btn-sm btn-link p-0 text-muted" id="btnClearDateRange" title="Filtreyi Temizle (Tüm Zamanlar)">
                        <i class="bx bx-x font-size-14"></i>
                    </button>
                </div>

                <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-success d-flex align-items-center gap-1 rounded-3" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12 fw-semibold">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-dark d-flex align-items-center gap-1 rounded-3" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12 fw-semibold">Yazdır</span>
                </button>
                <button type="button" class="btn btn-sm btn-light border px-2 py-1 text-primary d-flex align-items-center gap-1 rounded-3" id="btnHeaderRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12 fw-semibold">Yenile</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="tblTaslakFaturalar" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 20px;" class="align-middle text-center">
                                <div class="form-check font-size-16">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label" for="checkAll"></label>
                                </div>
                            </th>
                            <th data-filter="string" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="string" style="width: 135px;">FATURA NO</th>
                            <th data-filter="date" style="width: 105px;">TARİH</th>
                            <th data-filter="string">MÜŞTERİ / ALICI</th>
                            <th data-filter="string" style="width: 115px;">VKN / TCKN</th>
                            <th data-filter="select" style="width: 105px;">BELGE TÜRÜ</th>
                            <th data-filter="select" style="width: 115px;">SENARYO</th>
                            <th data-filter="string" class="text-end" style="width: 125px;">ÖDENECEK TUTAR</th>
                            <th data-filter="select" class="text-center" style="width: 105px;">DURUM</th>
                            <th data-filter="none" style="width: 140px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fatura Önizleme Modalı -->
<div class="modal fade" id="modalInvoicePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold" id="previewModalTitle">
                    <i class="bx bx-file me-1 text-primary"></i> Taslak Fatura Önizleme
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4" id="invoicePreviewContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Fatura yükleniyor...</div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary rounded-3 d-flex align-items-center gap-1" id="btnPrintPreview">
                    <i class="bx bx-printer"></i> Yazdır
                </button>
                <button type="button" class="btn btn-success rounded-3 d-flex align-items-center gap-1" id="btnModalSendEdm">
                    <i class="bx bx-send"></i> EDM'ye Gönder
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Footer Gizleme */
.footer {
    display: none !important;
}
.page-content {
    padding-bottom: 20px !important;
}

/* Üst Araç Çubuğu Butonları */
.top-action-btn {
    height: 36px;
    padding: 0 14px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid #e2e8f0;
    line-height: normal;
}
.top-icon-btn {
    height: 36px;
    width: 36px;
    padding: 0;
    font-size: 18px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
}
.top-action-btn.btn-primary {
    background-color: var(--bs-primary, #135bec) !important;
    border-color: var(--bs-primary, #135bec) !important;
    color: #ffffff !important;
}
.top-action-btn.btn-outline-secondary,
.top-icon-btn.btn-outline-secondary {
    color: #475569 !important;
    border-color: #e2e8f0 !important;
}
.top-action-btn.btn-outline-secondary:hover,
.top-icon-btn.btn-outline-secondary:hover {
    background-color: #f8fafc !important;
    color: #1e293b !important;
    border-color: #cbd5e1 !important;
}

/* Minimal KPI Kart Standartları */
.summary-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}
.summary-kpi-label {
    font-size: 10.5px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.summary-kpi-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
    line-height: 1;
}
.summary-kpi-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.summary-kpi-subtext {
    font-size: 11px;
    color: #64748b;
    font-weight: 500;
}
.summary-pill-btn {
    font-size: 10.5px !important;
    height: 22px !important;
    line-height: 1 !important;
    padding: 0 8px !important;
}

.summary-cards-group {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 2. Resimle Birebir Tablo ve Satır Çizgileri */
#tblTaslakFaturalar {
    border: 1px solid #eef2f6 !important;
    border-collapse: separate !important;
    border-spacing: 0;
}
#tblTaslakFaturalar thead th {
    font-size: 0.70rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.4px !important;
    text-transform: uppercase;
    color: #475569 !important;
    background-color: #f8fafc !important;
    border: 1px solid #eef2f6 !important;
    padding: 6px 8px !important;
    vertical-align: middle !important;
}

#tblTaslakFaturalar thead .dt-filter-row th,
.datatable-premium-shell table.dataTable thead .dt-filter-row th {
    padding: 4px 5px !important;
    background-color: #f8fafc !important;
    border: 1px solid #eef2f6 !important;
}

/* Sütun Filtre Kutuları (Input & Select) - Rahat ve Okunaklı Boyut */
.dt-filter-control,
#tblTaslakFaturalar thead .dt-filter-row input,
#tblTaslakFaturalar thead .dt-filter-row select {
    height: 30px !important;
    min-height: 30px !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
    line-height: 1.3 !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background-color: #ffffff !important;
    box-shadow: none !important;
    color: #1e293b !important;
}

.dt-filter-control:focus,
#tblTaslakFaturalar thead .dt-filter-row input:focus,
#tblTaslakFaturalar thead .dt-filter-row select:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
    outline: none !important;
}

#tblTaslakFaturalar tbody tr td {
    border: 1px solid #eef2f6 !important;
    padding: 7px 10px !important;
    vertical-align: middle;
    font-size: 12.5px;
}
#tblTaslakFaturalar tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
#tblTaslakFaturalar tbody tr:hover td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.05) !important;
}
#tblTaslakFaturalar tbody tr.selected td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.12) !important;
    color: inherit !important;
}
#tblTaslakFaturalar tbody tr.selected td a {
    color: var(--bs-primary, #135bec) !important;
    font-weight: 600;
}

/* Tablo Sarmalayıcı */
.table-responsive {
    overflow-x: auto;
    margin-bottom: 0;
}
.card-body {
    overflow: visible !important;
}

/* DataTables Alt Çubuk (Info & Paginate) Düzeni */
#tblTaslakFaturalar_wrapper > .row:last-child {
    margin-top: 8px !important;
    margin-bottom: 0 !important;
    padding: 4px 0 !important;
}
.dataTables_info {
    font-size: 12.5px !important;
    color: #475569 !important;
    font-weight: 500 !important;
    padding-top: 0 !important;
}
.dataTables_length {
    font-size: 12.5px !important;
    color: #475569 !important;
}
.dataTables_length select {
    padding: 2px 20px 2px 8px !important;
    font-size: 12px !important;
    height: 26px !important;
    border-radius: 5px !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
}
.dataTables_paginate .pagination {
    margin: 0 !important;
    gap: 3px !important;
}
.dataTables_paginate .pagination .page-item .page-link {
    height: 26px !important;
    min-width: 26px !important;
    padding: 0 7px !important;
    font-size: 12px !important;
    border-radius: 5px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #475569 !important;
    border: 1px solid #e2e8f0 !important;
    background-color: #ffffff !important;
}
.dataTables_paginate .pagination .page-item.active .page-link {
    background-color: #1e293b !important;
    border-color: #1e293b !important;
    color: #ffffff !important;
    font-weight: 600 !important;
}
.dataTables_paginate .pagination .page-item.disabled .page-link {
    color: #94a3b8 !important;
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}

/* Muhasebe Butonları */
.table-action-btn {
    width: 26px;
    height: 26px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
}
</style>

<script src="views/efatura/js/taslak-list.js?v=<?= time() ?>"></script>
