<?php
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Giden Faturalar';
?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-white rounded-3 border d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px; border-color: #e2e8f0 !important;">
                <i class="bx bx-receipt fs-4 text-secondary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">E-Fatura & E-Arşiv Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Sistemdeki tüm faturalar, GİB durumları ve e-fatura / e-arşiv işlemleri</p>
            </div>
        </div>
        
        <div class="col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Fatura Kes Butonu (Primary Mavi) -->
            <a href="index.php?p=efatura/olustur" class="btn btn-primary top-action-btn shadow-sm text-white">
                <i class="bx bx-plus font-size-16"></i> Yeni Fatura Kes
            </a>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/ayarlar">
                        <i class="bx bx-slider-alt me-2 text-primary font-size-16"></i> Entegratör Ayarları
                    </a>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item d-flex align-items-center text-success" id="exportExcel">
                        <i class="bx bx-file me-2 font-size-16"></i> Excel'e Aktar
                    </button>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM FATURA -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM FATURA</span>
                        <div class="summary-kpi-icon bg-light border text-secondary">
                            <i class="bx bx-receipt"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_efatura_earsiv">E-Fatura: 0 | E-Arşiv: 0</span>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-0 text-muted status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="">
                            <i class="bx bx-filter"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: GİB ONAYLI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GİB ONAYLI</span>
                        <div class="summary-kpi-icon" style="background: #fffbeb; border: 1px solid #fef3c7; color: #f59e0b;">
                            <i class="bx bx-hourglass"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_onaylanan_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold" id="stat_onaylanan_tutar">0,00 ₺</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="ONAYLANDI">
                            <i class="bx bx-check-double"></i> Onaylı
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: BEKLEYEN / İLETİLEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BEKLEYEN / İLETİLEN</span>
                        <div class="summary-kpi-icon" style="background: #f0fdf4; border: 1px solid #dcfce7; color: #10b981;">
                            <i class="bx bx-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_bekleyen_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success" id="stat_bekleyen_tutar">0,00 ₺</span>
                        <button type="button" class="btn btn-sm btn-light border rounded-pill px-2 py-0 text-muted status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="GONDERILDI">
                            <i class="bx bx-send"></i> İletilen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: BU AY KESİLEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BU AY KESİLEN</span>
                        <div class="summary-kpi-icon" style="background: #eff6ff; border: 1px solid #dbeafe; color: #2563eb;">
                            <i class="bx bx-calendar"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_bu_ay_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_bu_ay_tutar">0,00 ₺</span>
                        <span class="badge bg-light text-primary border rounded-pill px-2 py-1 font-size-11 fw-semibold" id="stat_donem_badge">
                            <?= date('m/Y') ?> Dönemi
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Minimal DataTables Fatura Listesi Kartı -->
    <div class="card summary-kpi-card mb-3">
        <div class="card-header bg-transparent border-0 px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bx bx-list-ul text-secondary font-size-22 d-inline-flex align-items-center"></i>
                <span class="fw-bold text-dark font-size-15 d-inline-flex align-items-center" style="line-height: 1;">Fatura Listesi</span>
                <span class="badge bg-dark rounded-pill font-size-11 px-2 py-1 d-inline-flex align-items-center" style="line-height: 1;" id="badgeTotalRecords">0</span>
            </div>

            <!-- Muhasebe Standartları Hızlı Araç Çubuğu -->
            <div class="d-flex align-items-center gap-1">
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
                <table id="tblFaturalar" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 20px;" class="align-middle text-center">
                                <div class="form-check font-size-16">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label" for="checkAll"></label>
                                </div>
                            </th>
                            <th data-filter="string" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="string" style="width: 140px;">FATURA NO</th>
                            <th data-filter="date" style="width: 110px;">TARİH</th>
                            <th data-filter="string">MÜŞTERİ / ALICI</th>
                            <th data-filter="string" style="width: 120px;">VKN / TCKN</th>
                            <th data-filter="select" style="width: 110px;">BELGE TÜRÜ</th>
                            <th data-filter="select" style="width: 120px;">SENARYO</th>
                            <th data-filter="string" class="text-end" style="width: 130px;">ÖDENECEK TUTAR</th>
                            <th data-filter="select" class="text-center" style="width: 110px;">DURUM</th>
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
<div class="modal fade" id="modalFaturaOnizleme" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold" id="onizlemeModalTitle">
                    <i class="bx bx-file me-1 text-primary"></i> Fatura Önizleme
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4" id="onizlemeModalContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Fatura yükleniyor...</div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary rounded-3 d-flex align-items-center gap-1" id="btnModalYazdir">
                    <i class="bx bx-printer"></i> Yazdır
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Faturayı İptal Et Modalı (Standart Bootstrap Modal) -->
<div class="modal fade" id="modalFaturaIptal" tabindex="-1" aria-labelledby="modalFaturaIptalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalFaturaIptalLabel">
                    <i class="bx bx-error-circle text-danger font-size-20"></i> Faturayı İptal Et
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formFaturaIptal">
                <input type="hidden" id="iptalInvoiceId" name="invoice_id" value="">
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 bg-warning-subtle text-warning-emphasis d-flex align-items-center rounded-3 p-3 mb-3 font-size-13">
                        <i class="bx bx-info-circle font-size-20 me-2 flex-shrink-0"></i>
                        <div>Bu işlem faturayı iptal durumuna getirecektir. Bu işlem geri alınamaz.</div>
                    </div>
                    <div class="mb-2">
                        <label for="iptalNedeni" class="form-label fw-semibold text-dark font-size-13">İptal Gerekçesi <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-3" id="iptalNedeni" name="reason" rows="3" placeholder="İptal gerekçesini giriniz..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-light border rounded-3 px-3 fw-semibold" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-3 fw-semibold d-flex align-items-center gap-1" id="btnSubmitIptal">
                        <i class="bx bx-check"></i> İptal Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Özel Sağ Tık (Context Menu) Bileşeni -->
<div id="faturaContextMenu" class="dropdown-menu shadow-lg border rounded-3 p-1" style="display: none; position: fixed; z-index: 99999; min-width: 220px;">
    <div class="dropdown-header text-muted font-size-11 text-uppercase fw-bold pb-1">Fatura İşlemleri</div>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action cm-edit-action text-warning" data-action="edit" href="javascript:void(0)">
        <i class="bx bx-edit me-2 font-size-16"></i> Faturayı Düzenle
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="preview" href="javascript:void(0)">
        <i class="bx bx-show me-2 text-primary font-size-16"></i> Görüntüle / Önizle
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="print" href="javascript:void(0)">
        <i class="bx bx-printer me-2 text-dark font-size-16"></i> Yazdır
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="download-pdf" href="javascript:void(0)">
        <i class="bx bxs-file-pdf me-2 text-danger font-size-16"></i> PDF İndir
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="download-xml" href="javascript:void(0)">
        <i class="bx bx-code-alt me-2 text-info font-size-16"></i> UBL (XML) İndir
    </a>
    <div class="dropdown-divider my-1"></div>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action cm-send-action" data-action="send" href="javascript:void(0)">
        <i class="bx bx-send me-2 text-success font-size-16"></i> GİB / EDM'ye Gönder
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action cm-sync-action" data-action="sync" href="javascript:void(0)">
        <i class="bx bx-refresh me-2 text-warning font-size-16"></i> GİB Durumunu Güncelle
    </a>
    <div class="dropdown-divider my-1"></div>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="copy-no" href="javascript:void(0)">
        <i class="bx bx-copy me-2 text-secondary font-size-16"></i> Fatura No Kopyala
    </a>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 cm-action" data-action="copy-ettn" href="javascript:void(0)">
        <i class="bx bx-key me-2 text-secondary font-size-16"></i> ETTN (UUID) Kopyala
    </a>
    <div class="dropdown-divider my-1"></div>
    <a class="dropdown-item d-flex align-items-center py-1 font-size-13 text-danger cm-action cm-cancel-action" data-action="cancel" href="javascript:void(0)">
        <i class="bx bx-x-circle me-2 font-size-16"></i> Faturayı İptal Et
    </a>
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
#tblFaturalar {
    border: 1px solid #eff2f7 !important;
    border-collapse: separate !important;
    border-spacing: 0;
}
#tblFaturalar thead th {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: #495057;
    background-color: #f8f9fa !important;
    border: 1px solid #eff2f7 !important;
    border-top: 1px solid #eff2f7 !important;
    border-bottom: 1px solid #eff2f7 !important;
    padding: 10px 12px;
    vertical-align: middle;
}
#tblFaturalar tbody tr td {
    border: 1px solid #eff2f7 !important;
    border-top: 1px solid #eff2f7 !important;
    border-bottom: 1px solid #eff2f7 !important;
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 13px;
}
#tblFaturalar tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
#tblFaturalar tbody tr:hover td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.05) !important;
}
#tblFaturalar tbody tr.selected td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.12) !important;
    color: inherit !important;
}
#tblFaturalar tbody tr.selected td a {
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
.dropdown-menu {
    z-index: 9999 !important;
}
.dropdown-item {
    color: #334155 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    white-space: nowrap !important;
}
.dropdown-item:hover {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
}

/* DataTables Alt Çubuk (Info & Paginate) Düzeni */
#tblFaturalar_wrapper .row:last-child {
    margin-top: 10px !important;
    margin-bottom: 0 !important;
}
.dataTables_info,
.dataTables_paginate,
.dataTables_length {
    font-size: 13px !important;
}

/* Muhasebe Butonları */
.table-action-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 14px;
}

/* 3. Profesyonel Yazdırma (Print) Standartları */
@media print {
    #page-topbar,
    .vertical-menu,
    .navbar-brand-box,
    .page-title-box,
    .footer,
    .summary-cards-group,
    .top-action-btn,
    .top-icon-btn,
    .btn-header-group,
    .dt-filter-row,
    .search-input-row,
    .dt-filter-mode-trigger,
    .dt-filter-mode-dropdown,
    .dataTables_length,
    .dataTables_paginate,
    .dataTables_info,
    #faturaContextMenu,
    .modal,
    .modal-backdrop,
    th:first-child,
    td:first-child,
    th:last-child,
    td:last-child,
    .form-check {
        display: none !important;
    }

    body, .main-content, .page-content, .card, .card-body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
    }

    #tblFaturalar {
        width: 100% !important;
        border-collapse: collapse !important;
        border: 1px solid #cbd5e1 !important;
        margin-top: 10px !important;
    }

    #tblFaturalar thead th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 9pt !important;
        padding: 6px 8px !important;
    }

    #tblFaturalar tbody td {
        border: 1px solid #e2e8f0 !important;
        font-size: 8.5pt !important;
        padding: 5px 8px !important;
        color: #000000 !important;
    }

    .badge {
        border: 1px solid #94a3b8 !important;
        color: #000000 !important;
        background: transparent !important;
        font-weight: normal !important;
    }
}
</style>

<!-- Giden Faturalar JS Scripti -->
<script src="views/efatura/js/giden-list.js?v=<?= time() ?>"></script>
