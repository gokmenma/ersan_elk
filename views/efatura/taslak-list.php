<?php
use App\Service\Gate;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Taslak Faturalar';
?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-white rounded-3 border d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 40px; height: 40px; border-color: #e2e8f0 !important;">
                <i class="bx bx-edit-alt fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Taslak Faturalar</h4>
                <p class="text-muted mb-0 font-size-12">Hazırlanan ve henüz EDM / GİB sistemine gönderilmemiş faturalar</p>
            </div>
        </div>
        
        <div class="col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Fatura Kes Butonu -->
            <a href="index.php?p=efatura/olustur" class="btn btn-primary top-action-btn shadow-sm text-white">
                <i class="bx bx-plus font-size-16"></i> Yeni Fatura Kes
            </a>

            <!-- 2. Seçilenleri EDM'ye Gönder Butonu -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white d-none" id="btnBulkSend">
                <i class="bx bx-send font-size-16"></i> Seçilenleri Gönder (<span id="selectedCount">0</span>)
            </button>

            <!-- 3. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
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
                        <span class="badge bg-soft-primary text-primary px-2 py-1 font-size-11">Taslak</span>
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
                        <span class="summary-kpi-subtext text-indigo fw-semibold">Kurumsal Mükellefler</span>
                        <span class="badge bg-soft-indigo text-indigo px-2 py-1 font-size-11">B2B</span>
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
                        <span class="badge bg-soft-success text-success px-2 py-1 font-size-11">B2C</span>
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
                        <div class="summary-kpi-icon" style="background: #fdf2f8; border: 1px solid #fce7f3; color: #db2777;">
                            <i class="bx bx-money"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_tutar">0,00 ₺</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Bu Ay: <strong id="stat_bu_ay_tutar" class="text-dark">0,00 ₺</strong></span>
                        <span class="badge bg-soft-dark text-dark px-2 py-1 font-size-11">TRY</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Ana Tablo Kartı -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3">
            <!-- Tablo Üstü Bilgilendirme ve Filtre -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark px-2 py-1 font-size-12">
                        <i class="bx bx-info-circle me-1"></i> Taslak faturalar EDM'ye gönderildiğinde otomatik olarak <strong>Giden Faturalar</strong> modülüne taşınır.
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" id="selectBelgeTuruFiltre" style="width: 160px;">
                        <option value="all">Tüm Belge Türleri</option>
                        <option value="EFATURA">E-Fatura</option>
                        <option value="EARSIV">E-Arşiv</option>
                    </select>
                </div>
            </div>

            <!-- DataTables Tablosu -->
            <div class="table-responsive">
                <table id="tblTaslakFaturalar" class="table table-hover align-middle w-100 table-nowrap custom-datatable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 28px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="checkAllInvoices">
                            </th>
                            <th style="width: 50px;">#</th>
                            <th data-filter="string">Taslak / Fatura No</th>
                            <th data-filter="date">Tarih</th>
                            <th data-filter="string">Alıcı Ünvan</th>
                            <th data-filter="string">VKN / TCKN</th>
                            <th data-filter="select">Belge Türü</th>
                            <th data-filter="select">Profil</th>
                            <th class="text-end" data-filter="string">Tutar</th>
                            <th class="text-center" data-filter="select">Durum</th>
                            <th class="text-center" style="width: 120px;">İşlemler</th>
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
                    <i class="bx bx-receipt text-primary"></i> <span id="previewModalTitle">Fatura Önizleme</span>
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
                <button type="button" class="btn btn-primary btn-sm" id="btnModalSendEdm">
                    <i class="bx bx-send me-1"></i> EDM'ye Gönder
                </button>
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

<script src="views/efatura/js/taslak-list.js?v=<?= time() ?>"></script>
