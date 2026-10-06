<?php
\App\Service\Gate::authorizeOrDie('efatura/gelen-list');
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Gelen Faturalar';
?>
<script>try { document.documentElement.classList.toggle('efatura-summary-hidden', localStorage.getItem('efatura_gelen_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.efatura-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

/* Focus durumunda inner input border/outline kaldır, dış kutuyu temiz vurgula */
.date-filter-box,
.product-search-box {
    transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
}
.date-filter-box input,
.date-filter-box input:focus,
.date-filter-box input:active,
.date-filter-box input.form-control,
.date-filter-box input.form-control:focus,
.date-filter-box input.form-control:active,
.product-search-box input,
.product-search-box input:focus,
.product-search-box input:active,
.product-search-box input.form-control,
.product-search-box input.form-control:focus,
.product-search-box input.form-control:active {
    outline: none !important;
    box-shadow: none !important;
    border: none !important;
    background: transparent !important;
}
.date-filter-box:focus-within,
.product-search-box:focus-within {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
}

/* Flatpickr Year Dropdown Select */
.flatpickr-current-month .flatpickr-yearDropdown-years {
    font-weight: 700 !important;
    font-size: inherit !important;
    margin-left: 4px !important;
    cursor: pointer !important;
    border: none !important;
    background: transparent !important;
    color: inherit !important;
    padding: 0 4px !important;
    border-radius: 4px !important;
}
.flatpickr-current-month .flatpickr-yearDropdown-years:focus,
.flatpickr-current-month .flatpickr-yearDropdown-years:hover {
    background: rgba(0, 0, 0, 0.05) !important;
    outline: none !important;
}
</style>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js?v=<?= filemtime(__DIR__ . '/js/transport.js') ?>"></script>


<?php include 'layouts/breadcrumb.php'; ?>
<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-download fs-4 text-success"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Gelen Faturalar</h4>
                <p class="text-muted mb-0 font-size-12">Tedarikçilerden firmanıza düzenlenen e-faturalar ve ticari yanıt yönetimi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. EDM'den Yeni Faturaları Çek Butonu (Success Yeşil) -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white" id="btnSyncIncoming">
                <i class="bx bx-refresh font-size-16"></i> EDM'den Faturaları Çek
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/taslak-list">
                        <i class="bx bx-edit-alt me-2 text-primary font-size-16"></i> Taslak Faturalara Git
                    </a>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/giden-list">
                        <i class="bx bx-cloud-upload me-2 text-info font-size-16"></i> Giden Faturalara Git
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="index.php?p=efatura/ayarlar">
                        <i class="bx bx-slider-alt me-2 text-warning font-size-16"></i> Entegratör Ayarları
                    </a>
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
        <!-- Kart 1: TOPLAM GELEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM GELEN FATURA</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-download"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_bu_ay_adet_text">Bu Ay: 0 Adet</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-yanit="">
                            <i class="bx bx-filter"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: KABUL EDİLENLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KABUL EDİLENLER</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_kabul_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Onaylanan Faturalar</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-yanit="KABUL">
                            <i class="bx bx-check"></i> Kabul
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: YANIT BEKLEYENLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">YANIT BEKLEYENLER</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-time"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_bekleyen_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Ticari Fatura Yanıtı</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-yanit="BEKLIYOR">
                            <i class="bx bx-time-five"></i> Bekleyen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TOPLAM GELEN TUTAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM GELEN TUTARI</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_tutar">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Bu Ay: <strong id="stat_bu_ay_tutar" class="text-dark">0,00 ₺</strong></span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
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
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Gelen Fatura Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Tedarikçilerden gelen faturalar ve ticari yanıt işlemleri</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu: Ürün Arama, Tarih Aralığı ve Dışa Aktarma Butonları -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Ürün / Marka / İçerik Arama Alanı -->
                <div class="d-flex align-items-center bg-white border rounded-3 px-2 py-1 shadow-sm gap-1 product-search-box" style="border-color: #cbd5e1 !important;" title="Fatura içeriğindeki ürün adı, marka, ürün kodu veya açıklamaya göre filtrele">
                    <i class="bx bx-package text-primary font-size-16"></i>
                    <input type="text" id="filterProductSearch" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 195px;" placeholder="Ürün / Marka / Kalem Ara..." autocomplete="off">
                    <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-danger" id="btnClearProductSearch" title="Ürün Aramasını Temizle" style="display: none;">
                        <i class="bx bx-x font-size-14"></i>
                    </button>
                </div>

                <!-- Başlangıç Tarihi Filtresi -->
                <div class="d-flex align-items-center bg-white border rounded-3 px-2 py-1 shadow-sm gap-1 date-filter-box" style="border-color: #cbd5e1 !important;" title="Başlangıç Tarihi">
                    <i class="bx bx-calendar text-primary font-size-15"></i>
                    <input type="text" id="filterStartDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 82px; cursor: pointer;" placeholder="Başlangıç" readonly>
                    <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-danger" id="btnClearStartDate" title="Başlangıç Tarihini Temizle">
                        <i class="bx bx-x font-size-14"></i>
                    </button>
                </div>

                <!-- Bitiş Tarihi Filtresi -->
                <div class="d-flex align-items-center bg-white border rounded-3 px-2 py-1 shadow-sm gap-1 date-filter-box" style="border-color: #cbd5e1 !important;" title="Bitiş Tarihi">
                    <i class="bx bx-calendar text-primary font-size-15"></i>
                    <input type="text" id="filterEndDate" class="form-control form-control-sm border-0 bg-transparent p-0 font-size-12 fw-semibold text-dark" style="width: 82px; cursor: pointer;" placeholder="Bitiş" readonly>
                    <button type="button" class="btn btn-sm btn-link p-0 text-muted hover-danger" id="btnClearEndDate" title="Bitiş Tarihini Temizle">
                        <i class="bx bx-x font-size-14"></i>
                    </button>
                </div>

                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-primary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yenile</span>
                </button>

                <!-- Sütunlar Butonu (ColVis & Drag-Drop Yönetimi) -->
                <div class="dropdown d-inline-block">
                    <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="btnHeaderColVis" title="Sütunları Yönet">
                        <i class="bx bx-columns font-size-15 text-primary"></i> <span class="d-none d-sm-inline font-size-12">Sütunlar</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border p-2" style="min-width: 230px; max-height: 400px; overflow-y: auto;" id="columnListContainer">
                        <div class="d-flex align-items-center justify-content-between px-2 pb-1.5 border-bottom mb-1">
                            <span class="font-size-11 fw-bold text-uppercase text-muted">Sütun Görünürlüğü</span>
                            <button type="button" class="btn btn-link p-0 font-size-11 text-primary text-decoration-none" id="btnResetColumns">Sıfırla</button>
                        </div>
                        <div id="columnList" class="d-flex flex-column gap-1"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="tblGelenFaturalar" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 20px;" class="align-middle text-center">
                                <div class="form-check font-size-16">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label" for="checkAll"></label>
                                </div>
                            </th>
                            <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="string" style="width: 135px;">FATURA NO</th>
                            <th data-filter="date" style="width: 105px;">TARİH</th>
                            <th data-filter="string">GÖNDERİCİ / TEDARİKÇİ</th>
                            <th data-filter="string" style="width: 115px;">VKN / TCKN</th>
                            <th data-filter="select" style="width: 105px;">BELGE TÜRÜ</th>
                            <th data-filter="select" style="width: 115px;">SENARYO</th>
                            <th data-filter="string" class="text-end" style="width: 125px;">ÖDENECEK TUTAR</th>
                            <th data-filter="select" class="text-center" style="width: 115px;">DURUM / YANIT</th>
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
                    <i class="bx bx-file me-1 text-primary"></i> Gelen Fatura Önizleme
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
    height: 38px;
    padding: 0 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 1px solid #cbd5e1;
    line-height: normal;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.top-icon-btn {
    height: 38px;
    width: 38px;
    padding: 0;
    font-size: 18px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
}
.top-action-btn.btn-primary {
    background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(37, 99, 235, 0.3) !important;
}
.top-action-btn.btn-primary:hover {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4) !important;
}
.top-action-btn.btn-success {
    background: linear-gradient(135deg, #15803d 0%, #22c55e 100%) !important;
    border: none !important;
    color: #ffffff !important;
    box-shadow: 0 3px 8px rgba(34, 197, 94, 0.3) !important;
}
.top-action-btn.btn-success:hover {
    background: linear-gradient(135deg, #166534 0%, #16a34a 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4) !important;
}
.top-action-btn.btn-outline-secondary,
.top-icon-btn.btn-outline-secondary {
    color: #334155 !important;
    border-color: #cbd5e1 !important;
    background-color: #ffffff !important;
}
.top-action-btn.btn-outline-secondary:hover,
.top-icon-btn.btn-outline-secondary:hover {
    background-color: #f1f5f9 !important;
    color: #0f172a !important;
    border-color: #94a3b8 !important;
    transform: translateY(-1px);
}

/* Minimal KPI Kart Standartları */
.summary-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02), 0 1px 2px rgba(0, 0, 0, 0.03) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.summary-kpi-card:hover {
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

/* Renkli ve Şık Durum Filtre Hapları */
.filter-pill-all {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    font-weight: 600;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 12px;
    transition: all 0.2s ease;
}
.filter-pill-all:hover {
    background: #f1f5f9;
    color: #1e293b;
}
.filter-pill-all.active {
    background: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.35) !important;
}

.filter-pill-warning {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
    font-weight: 600;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 12px;
    transition: all 0.2s ease;
}
.filter-pill-warning:hover {
    background: #fef3c7;
    color: #92400e;
}
.filter-pill-warning.active {
    background: #f59e0b !important;
    color: #ffffff !important;
    border-color: #f59e0b !important;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.35) !important;
}

.filter-pill-success {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-weight: 600;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 12px;
    transition: all 0.2s ease;
}
.filter-pill-success:hover {
    background: #dcfce7;
    color: #166534;
}
.filter-pill-success.active {
    background: #16a34a !important;
    color: #ffffff !important;
    border-color: #16a34a !important;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35) !important;
}

.filter-pill-danger {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    font-weight: 600;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 12px;
    transition: all 0.2s ease;
}
.filter-pill-danger:hover {
    background: #fee2e2;
    color: #991b1b;
}
.filter-pill-danger.active {
    background: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
    box-shadow: 0 2px 6px rgba(220, 38, 38, 0.35) !important;
}

/* Modern Renkli Butonlar (Subtle Buttons) */
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
    background-color: #f5f3ff;
    color: #7c3aed;
    border: 1px solid #ddd6fe;
    transition: all 0.18s ease;
}
.btn-subtle-info:hover, .btn-subtle-info:focus, .btn-subtle-info.active {
    background-color: #7c3aed !important;
    color: #ffffff !important;
    border-color: #7c3aed !important;
    box-shadow: 0 2px 5px rgba(124, 58, 237, 0.25);
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

/* Muhasebe ve Tablo Satır Butonları */
.table-action-btn {
    width: 30px;
    height: 30px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    font-size: 14px;
    cursor: pointer;
    flex-shrink: 0;
}
.action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.summary-cards-group {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 2. Resimle Birebir Tablo ve Satır Çizgileri */
#tblGelenFaturalar {
    border: 1px solid #eef2f6 !important;
    border-collapse: separate !important;
    border-spacing: 0;
}
#tblGelenFaturalar thead th {
    font-size: 0.70rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.4px !important;
    text-transform: uppercase;
    color: #475569 !important;
    background-color: #f8fafc !important;
    border: 1px solid #eef2f6 !important;
    padding-top: 8px !important;
    padding-bottom: 8px !important;
    vertical-align: middle !important;
}

#tblGelenFaturalar thead .dt-filter-row th,
.datatable-premium-shell table.dataTable thead .dt-filter-row th {
    padding: 4px 5px !important;
    background-color: #f8fafc !important;
    border: 1px solid #eef2f6 !important;
}

/* Sütun Filtre Kutuları (Input & Select) */
.dt-filter-control,
#tblGelenFaturalar thead .dt-filter-row input,
#tblGelenFaturalar thead .dt-filter-row select {
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
#tblGelenFaturalar thead .dt-filter-row input:focus,
#tblGelenFaturalar thead .dt-filter-row select:focus {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
    outline: none !important;
}

#tblGelenFaturalar tbody tr td {
    border: 1px solid #eef2f6 !important;
    padding: 8px 10px !important;
    vertical-align: middle;
    font-size: 12.5px;
}
#tblGelenFaturalar tbody tr {
    cursor: pointer;
    transition: background-color 0.15s ease;
}
#tblGelenFaturalar tbody tr:hover td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.05) !important;
}
#tblGelenFaturalar tbody tr.selected td {
    background-color: rgba(var(--bs-primary-rgb, 19, 91, 236), 0.12) !important;
    color: inherit !important;
}
#tblGelenFaturalar tbody tr.selected td a {
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
#tblGelenFaturalar_wrapper > .row:last-child {
    margin-top: 10px !important;
    margin-bottom: 0 !important;
    padding: 6px 0 !important;
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
    height: 28px !important;
    border-radius: 6px !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
}
.dataTables_paginate .pagination {
    margin: 0 !important;
    gap: 4px !important;
}
.dataTables_paginate .pagination .page-item .page-link {
    height: 28px !important;
    min-width: 28px !important;
    padding: 0 8px !important;
    font-size: 12px !important;
    border-radius: 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #475569 !important;
    border: 1px solid #cbd5e1 !important;
    background-color: #ffffff !important;
    transition: all 0.2s ease;
}
.dataTables_paginate .pagination .page-item.active .page-link {
    background-color: #2563eb !important;
    border-color: #2563eb !important;
    color: #ffffff !important;
    font-weight: 600 !important;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3) !important;
}
.dataTables_paginate .pagination .page-item.disabled .page-link {
    color: #94a3b8 !important;
    background-color: #f8fafc !important;
    border-color: #e2e8f0 !important;
}
</style>

<script src="views/efatura/js/gelen-list.js?v=<?= time() ?>"></script>
