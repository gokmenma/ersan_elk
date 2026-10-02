<?php
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Gelen Faturalar';
?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-white rounded-3 border d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px; border-color: #e2e8f0 !important;">
                <i class="bx bx-download fs-4 text-success"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Gelen Faturalar</h4>
                <p class="text-muted mb-0 font-size-12">Tedarikçilerden firmanıza düzenlenen e-faturalar ve ticari yanıt yönetimi</p>
            </div>
        </div>
        
        <div class="col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. EDM'den Yeni Faturaları Çek Butonu (Success Yeşil) -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white" id="btnSyncIncoming">
                <i class="bx bx-refresh font-size-16"></i> EDM'den Faturaları Çek
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16"></i> İşlemler
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
                        <div class="summary-kpi-icon bg-light border text-success">
                            <i class="bx bx-download"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_bu_ay_adet_text">Bu Ay: 0 Adet</span>
                        <span class="badge bg-soft-success text-success px-2 py-1 font-size-11">Gelen Kutusu</span>
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
                        <div class="summary-kpi-icon" style="background: #f0fdf4; border: 1px solid #dcfce7; color: #10b981;">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_kabul_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Onaylanan Faturalar</span>
                        <span class="badge bg-soft-success text-success px-2 py-1 font-size-11">Kabul</span>
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
                        <div class="summary-kpi-icon" style="background: #fffbeb; border: 1px solid #fef3c7; color: #f59e0b;">
                            <i class="bx bx-time"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_bekleyen_adet">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Ticari Fatura Yanıtı</span>
                        <span class="badge bg-soft-warning text-warning px-2 py-1 font-size-11">8 Günlük Süre</span>
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
                        <div class="summary-kpi-icon" style="background: #eff6ff; border: 1px solid #dbeafe; color: #2563eb;">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_tutar">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Bu Ay: <strong id="stat_bu_ay_tutar" class="text-dark">0,00 ₺</strong></span>
                        <span class="badge bg-soft-primary text-primary px-2 py-1 font-size-11">TRY</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Ana Tablo Kartı -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tblGelenFaturalar" class="table table-hover align-middle w-100 table-nowrap custom-datatable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th data-filter="string">Fatura No</th>
                            <th data-filter="date">Fatura Tarihi</th>
                            <th data-filter="string">Gönderici Ünvan</th>
                            <th data-filter="string">Gönderici VKN / TCKN</th>
                            <th data-filter="select">Belge Türü</th>
                            <th data-filter="select">Profil</th>
                            <th class="text-end" data-filter="string">Tutar</th>
                            <th class="text-center" data-filter="select">Ticari Yanıt</th>
                            <th class="text-center" style="width: 140px;">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- DataTables AJAX tarafından doldurulacak -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- HTML Fatura Önizleme Modalı -->
<div class="modal fade" id="modalInvoicePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center gap-2">
                    <i class="bx bx-receipt text-primary"></i> <span id="previewModalTitle">Gelen Fatura Önizleme</span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrintPreview">
                        <i class="bx bx-printer"></i> Yazdır
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary bg-opacity-10 d-flex justify-content-center">
                <div id="invoicePreviewContainer" class="bg-white p-3 shadow-sm my-3" style="min-height: 500px; width: 100%; max-width: 900px;">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2">Fatura yükleniyor...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<style>
.summary-kpi-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    transition: all 0.2s ease-in-out;
}
.summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.summary-kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #64748b;
    text-transform: uppercase;
}
.summary-kpi-value {
    font-size: 1.45rem;
    font-weight: 800;
    color: #1e293b;
}
.summary-kpi-subtext {
    font-size: 0.75rem;
    color: #64748b;
}
.summary-kpi-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.top-action-btn {
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.45rem 0.85rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.top-icon-btn {
    width: 36px;
    height: 36px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.2rem;
}
.summary-cards-group {
    transition: max-height 0.3s ease, opacity 0.3s ease;
    overflow: hidden;
}
.summary-cards-group.cards-collapsed {
    max-height: 0 !important;
    opacity: 0 !important;
    margin-bottom: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    pointer-events: none;
}
</style>

<script src="views/efatura/js/gelen-list.js?v=<?= time() ?>"></script>
