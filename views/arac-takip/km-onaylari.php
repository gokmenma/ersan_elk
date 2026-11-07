<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;
use App\Service\Gate;

$maintitle = 'Araç Takip';
$title = 'KM Bildirim Onayları';

$KmBildirim = new AracKmBildirimModel();
$counts = $KmBildirim->getReportCounts();
$pendingCount = (int) ($counts['pending'] ?? 0);
$approvedCount = (int) ($counts['approved'] ?? 0);
$rejectedCount = (int) ($counts['rejected'] ?? 0);
$unreportedCount = (int) ($counts['unreported'] ?? 0);
?>

<script>try { document.documentElement.classList.toggle('km-summary-hidden', localStorage.getItem('km_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
/* Özet Kartları Aç/Kapa Animasyon ve Standartları */
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.km-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#summaryCardsContainer .summary-kpi-card,
#kmListCard { 
    background-color: #ffffff !important; 
    border: 1px solid #e2e8f0 !important; 
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important; 
}
html[data-theme-preset="macos-dark"] #summaryCardsContainer .summary-kpi-card, 
html[data-theme-preset="macos-dark"] #kmListCard,
[data-bs-theme="dark"] #summaryCardsContainer .summary-kpi-card,
[data-bs-theme="dark"] #kmListCard { 
    background-color: #2a3042 !important; 
    border-color: #32394e !important; 
}

/* Durum Filtre Grubu Nav Pills */
.status-filter-group {
    background: #f8fafc; padding: 4px; border-radius: 50px;
    border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 2px;
}
[data-bs-theme="dark"] .status-filter-group,
html[data-theme-preset="macos-dark"] .status-filter-group { background: #2a3042; border-color: #32394e; }

.status-filter-group .nav-link {
    border: none !important;
    border-radius: 100px !important;
    font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #64748b;
    transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
}

[data-bs-theme="dark"] .status-filter-group .nav-link,
html[data-theme-preset="macos-dark"] .status-filter-group .nav-link { color: #a6b0cf; }
.status-filter-group .nav-link:hover { background: rgba(0, 0, 0, 0.04); color: #1e293b; }
[data-bs-theme="dark"] .status-filter-group .nav-link:hover,
html[data-theme-preset="macos-dark"] .status-filter-group .nav-link:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }

.status-filter-group .nav-link.active { 
    border-radius: 100px !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    color: white !important;
}

.status-filter-group .nav-link.active[href="#pending"] { background: #3b82f6 !important; }
.status-filter-group .nav-link.active[href="#approved"] { background: #10b981 !important; }
.status-filter-group .nav-link.active[href="#rejected"] { background: #ef4444 !important; }
.status-filter-group .nav-link.active[href="#unreported"] { background: #f59e0b !important; }

.status-filter-group .nav-link i { font-size: 1rem; }
.status-filter-group .nav-link .badge { font-size: 0.7rem; padding: 0.25em 0.6em; }

/* Özel Checkbox Stilleri */
.unreported-checkbox-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
}
.custom-check {
    cursor: pointer;
    width: 18px;
    height: 18px;
    background: #fff;
    border: 2px solid #cbd5e1;
    border-radius: 4px;
    position: relative;
    transition: all 0.2s;
    margin-bottom: 0;
}
input.unreported-checkbox:checked + .custom-check,
input#checkAllUnreported:checked + .custom-check {
    background: #3b82f6;
    border-color: #3b82f6;
}
input.unreported-checkbox:checked + .custom-check::after,
input#checkAllUnreported:checked + .custom-check::after {
    content: '\2713';
    color: white;
    font-size: 13px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.unreported-checkbox { display: none !important; }

/* DataTables Sayfalama ve Bilgi Elemanları Hizalama */
.dataTables_wrapper .dataTables_paginate {
    display: flex !important;
    justify-content: flex-end !important;
    align-items: center;
}
.dataTables_wrapper .dataTables_info {
    padding-top: 0 !important;
}
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">

    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Standart Format) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-tachometer fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">KM Bildirim Onayları</h4>
                <p class="text-muted mb-0 font-size-12">Personel araç KM bildirimlerinin incelenmesi, yapay zekâ doğrulaması ve onay yönetimi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yapay Zekâ ile Kontrol Butonu -->
            <button type="button" id="btnKmAiKontrol" class="btn btn-primary top-action-btn shadow-sm text-white" title="Seçilen fotoğrafları yapay zekâ ile kontrol et" disabled>
                <i class="bx bx-bot font-size-16"></i> <span>Yapay Zekâ ile Kontrol (<span id="aiSelectedCount">0</span>)</span>
            </button>

            <!-- 2. Toplu Onayla Butonu -->
            <button type="button" id="btnTopluOnayla" class="btn btn-success top-action-btn shadow-sm text-white d-none" title="Seçilenleri Onayla">
                <i class="bx bx-check-double font-size-16"></i> <span>Toplu Onayla (<span id="selectedCount">0</span>)</span>
            </button>

            <!-- 3. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownExportExcel">
                        <i class="bx bx-spreadsheet me-2 font-size-16 text-success"></i> Excel'e Aktar
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#imageCleanupModal">
                        <i class="bx bx-trash me-2 font-size-16 text-danger"></i> Eski Resimleri Arşivle & Temizle
                    </button>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item d-flex align-items-center" href="arac-takip">
                        <i class="bx bx-car me-2 font-size-16 text-primary"></i> Araç Takip Paneli
                    </a>
                </div>
            </div>

            <!-- 4. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı (AGENTS.md Standardı) -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: ONAY BEKLEYENLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ONAY BEKLEYENLER</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-time"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-primary" id="stat_bekleyen_sayi"><?= $pendingCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_pending">İnceleme bekleyen bildirimler</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-tab="#pending">
                            <i class="bx bx-time"></i> Bekleyenler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: ONAYLANANLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ONAYLANANLAR</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_onaylanan_sayi"><?= $approvedCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_approved">Kayıtlara işlenen bildirimler</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tab="#approved">
                            <i class="bx bx-check-double"></i> Onaylananlar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: REDDEDİLENLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">REDDEDİLENLER</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-x-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_reddedilen_sayi"><?= $rejectedCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_rejected">Gerekçe ile reddedilenler</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tab="#rejected">
                            <i class="bx bx-x"></i> Reddedilenler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: BİLDİRİM YAPMAYANLAR -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BİLDİRİM YAPMAYANLAR</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-error-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_yapmayanlar_sayi"><?= $unreportedCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold" id="stat_sub_unreported">Eksik bildirim takibi</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tab="#unreported">
                            <i class="bx bx-bell"></i> Eksikler
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Liste Kartı -->
    <div class="card summary-kpi-card mb-3" id="kmListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <!-- Sol Taraf: Durum Filtre Grubu Sekmeleri (Solda) -->
            <div class="status-filter-group nav nav-pills" role="tablist">
                <a class="nav-link active" data-bs-toggle="tab" href="#pending" role="tab" id="tabPendingLink">
                    <i class="bx bx-time"></i>
                    <span>Onay Bekleyenler</span>
                    <span class="badge bg-danger ms-1" id="badge_pending"><?= $pendingCount ?></span>
                </a>
                <a class="nav-link" data-bs-toggle="tab" href="#approved" role="tab" id="tabApprovedLink">
                    <i class="bx bx-check-circle"></i>
                    <span>Onaylananlar</span>
                    <span class="badge bg-success ms-1" id="badge_approved"><?= $approvedCount ?></span>
                </a>
                <a class="nav-link" data-bs-toggle="tab" href="#rejected" role="tab" id="tabRejectedLink">
                    <i class="bx bx-x-circle"></i>
                    <span>Reddedilenler</span>
                    <span class="badge bg-secondary ms-1" id="badge_rejected"><?= $rejectedCount ?></span>
                </a>
                <a class="nav-link" data-bs-toggle="tab" href="#unreported" role="tab" id="tabUnreportedLink">
                    <i class="bx bx-error-circle"></i>
                    <span>Bildirim Yapmayanlar</span>
                    <span class="badge bg-warning ms-1" id="badge_unreported"><?= $unreportedCount ?></span>
                </a>
            </div>

            <!-- Sağ Araç Çubuğu Butonları -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="exportExcelKm" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="tab-content">
                <!-- 1. Onay Bekleyenler -->
                <div class="tab-pane active" id="pending" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="tablePending">
                            <thead>
                                <tr>
                                    <th data-filter="none" class="text-center" style="width: 40px;">
                                        <div class="form-check font-size-16 d-flex justify-content-center">
                                             <input class="form-check-input" type="checkbox" id="checkAllKm">
                                             <label class="form-check-label" for="checkAllKm"></label>
                                        </div>
                                    </th>
                                    <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                                    <th data-filter="string" style="width: 15%;">PERSONEL</th>
                                    <th data-filter="string" style="width: 15%;">ARAÇ</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">TARİH</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">KAYIT TARİHİ</th>
                                    <th data-filter="select" class="text-center" style="width: 10%;">TÜR</th>
                                    <th data-filter="number" class="text-end" style="width: 10%;">BİLDİRİLEN KM</th>
                                    <th data-filter="string" style="width: 15%;">AÇIKLAMA</th>
                                    <th data-filter="none" class="text-center" style="width: 80px;">RESİM</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. Onaylananlar -->
                <div class="tab-pane" id="approved" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="tableApproved">
                            <thead>
                                <tr>
                                    <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                                    <th data-filter="string" style="width: 15%;">PERSONEL</th>
                                    <th data-filter="string" style="width: 15%;">ARAÇ</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">TARİH</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">KAYIT TARİHİ</th>
                                    <th data-filter="select" class="text-center" style="width: 10%;">TÜR</th>
                                    <th data-filter="number" class="text-end" style="width: 10%;">BİLDİRİLEN KM</th>
                                    <th data-filter="number" class="text-end" style="width: 10%;">ONAYLANAN KM</th>
                                    <th data-filter="string" style="width: 15%;">ONAYLAYAN / TARİH</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">RESİM / İŞLEM</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. Reddedilenler -->
                <div class="tab-pane" id="rejected" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="tableRejected">
                            <thead>
                                <tr>
                                    <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                                    <th data-filter="string" style="width: 15%;">PERSONEL</th>
                                    <th data-filter="string" style="width: 15%;">ARAÇ</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">TARİH</th>
                                    <th data-filter="date" class="text-center" style="width: 10%;">KAYIT TARİHİ</th>
                                    <th data-filter="select" class="text-center" style="width: 10%;">TÜR</th>
                                    <th data-filter="number" class="text-end" style="width: 10%;">BİLDİRİLEN KM</th>
                                    <th data-filter="string" style="width: 20%;">RED NEDENİ</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">RESİM / İŞLEM</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Bildirim Yapmayanlar -->
                <div class="tab-pane" id="unreported" role="tabpanel">
                    <div class="d-flex align-items-center mb-3 gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm d-none shadow-sm" id="btnBulkSendReminder">
                            <i class="bx bx-paper-plane me-1"></i> Seçilenlere Bildirim Gönder
                        </button>
                        <div class="ms-auto text-muted small" id="selectionSummary"></div>
                    </div>
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="tableUnreported">
                            <thead>
                                <tr>
                                    <th data-filter="none" class="text-center" style="width: 40px;">
                                        <div class="unreported-checkbox-wrapper">
                                            <input type="checkbox" id="checkAllUnreported" class="unreported-checkbox">
                                            <label for="checkAllUnreported" class="custom-check mb-0"></label>
                                        </div>
                                    </th>
                                    <th data-filter="string" style="width: 22%;">PERSONEL</th>
                                    <th data-filter="string" style="width: 15%;">ARAÇ / PLAKA</th>
                                    <th data-filter="date" class="text-center" style="width: 12%;">HEDEF TARİH</th>
                                    <th data-filter="select" class="text-center" style="width: 18%;">TÜR / GECİKME</th>
                                    <th data-filter="string" style="width: 15%;">TELEFON</th>
                                    <th data-filter="none" class="text-center" style="width: 130px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody id="unreportedListBody">
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Yükleniyor...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Veriler kontrol ediliyor...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div> <!-- container-fluid -->

<!-- KM Red Modalı -->
<div class="modal fade" id="kmRedModal" tabindex="-1" role="dialog" aria-labelledby="kmRedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header">
                <h5 class="modal-title" id="kmRedModalLabel"><i class="bx bx-x-circle text-danger me-2"></i>Bildirimi Reddet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kmRedForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="km-onay-reddet">
                    <input type="hidden" name="id" id="red_bildirim_id">
                    <div class="mb-3">
                        <label class="form-label" for="red_nedeni">Red Nedeni <small class="text-muted">(Zorunlu değil)</small></label>
                        <textarea class="form-control" name="red_nedeni" id="red_nedeni" rows="4" placeholder="Neden reddettiğinizi buraya yazabilirsiniz..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger" id="btnKmReddetSubmit">Bildirimi Reddet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resim Görüntüleme Modalı -->
<div class="modal fade no-upgrade" id="imgViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content overflow-hidden border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 bg-light py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-soft-info text-info">
                            <i class="bx bx-car"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0">Gün ve KM Bildirimi</h6>
                        <small class="text-muted" id="modalImgHeaderInfo"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <img src="" id="modalViewImg" class="img-fluid w-100" alt="KM Bildirim Resmi" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<!-- KM Resim Arşivleme ve Temizlik Modalı -->
<div class="modal fade" id="imageCleanupModal" tabindex="-1" role="dialog" aria-labelledby="imageCleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-danger py-3 px-4 border-0" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-white text-danger">
                            <i class="bx bx-trash"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold">KM Resimlerini Arşivle & Temizle</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 border rounded bg-light mb-3" style="font-size: 0.85rem; border-left: 4px solid #f46a6a !important;">
                    Belirttiğiniz dönemdeki KM bildirim resimleri araç bazında klasörlenerek ZIP olarak indirilebilir hale getirilecek ve sunucudan kalıcı olarak silinecektir.
                </div>
                
                <div id="cleanupFormSection">
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormDateRange('cleanup_date_range', '', 'Tarih Aralığı Seçin', 'calendar'); ?>
                    </div>
                    
                    <button type="button" id="btnCheckCleanupCount" class="btn btn-secondary w-100 mb-3 fw-bold">
                        <i class="bx bx-search me-1"></i> Resim Adedini Sorgula
                    </button>
                    
                    <div class="alert alert-info text-center py-2 d-none" id="cleanupCountFeedback">
                        Sorgulanıyor...
                    </div>
                    
                    <button type="button" id="btnStartCleanup" class="btn btn-danger w-100 fw-bold d-none">
                        <i class="bx bx-archive-in me-1"></i> Arşivle ve Sunucudan Sil
                    </button>
                </div>
                
                <div id="cleanupLoadingSection" class="text-center py-4 d-none">
                    <div class="spinner-border text-danger mb-3" role="status"></div>
                    <h6 class="fw-bold">Arşiv Hazırlanıyor ve Temizlik Yapılıyor...</h6>
                    <p class="text-muted small">Lütfen bu pencereyi kapatmayın.</p>
                </div>
                
                <div id="cleanupResultSection" class="text-center py-3 d-none">
                    <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
                    <h5 class="mt-3 mb-4 fw-bold">İşlem Tamamlandı!</h5>
                    <a href="" id="btnDownloadCleanupZip" class="btn btn-success w-100 mb-3 fw-bold" target="_blank">
                        <i class="bx bx-download me-2"></i>Arşivi İndir (ZIP)
                    </a>
                    <button type="button" id="btnDeleteServerZip" class="btn btn-outline-danger w-100 fw-bold">
                        <i class="bx bx-trash me-2"></i>Arşiv Dosyasını Sunucudan Sil
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Onaylı KM Düzenleme Modalı -->
<div class="modal fade no-upgrade" id="editApprovedKmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-warning py-3 px-4 border-0" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-white text-warning">
                            <i class="bx bx-edit"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold">Onaylı KM Düzenle</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editApprovedKmForm">
                <input type="hidden" id="edit_approved_id" name="id">
                <input type="hidden" id="edit_approved_arac_id" name="arac_id">
                
                <div class="modal-body p-4">
                    <div class="p-3 border rounded bg-light mb-3" style="font-size: 0.9rem; border-left: 4px solid #ffbb44 !important;">
                        <div class="mb-1"><span class="fw-bold text-muted">Araç Plaka:</span> <span id="lbl_edit_plaka" class="fw-bold text-dark"></span></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="edit_approved_date" class="form-label fw-bold text-dark">Tarih</label>
                            <input type="date" class="form-control" id="edit_approved_date" name="tarih" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_approved_tur" class="form-label fw-bold text-dark">Tür</label>
                            <select class="form-select" id="edit_approved_tur" name="tur" required>
                                <option value="sabah">Sabah</option>
                                <option value="aksam">Akşam</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="edit_approved_km_value" class="form-label fw-bold text-dark">Yeni KM Değeri</label>
                        <input type="number" class="form-control" id="edit_approved_km_value" name="km" min="0" required>
                    </div>

                    <div class="form-group mb-0">
                        <label for="edit_approved_aciklama" class="form-label fw-bold text-dark">Açıklama (İsteğe Bağlı)</label>
                        <textarea class="form-control" id="edit_approved_aciklama" name="aciklama" rows="3" placeholder="KM düzeltme nedeni veya açıklama girin..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-0" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold" id="btnSubmitEditApprovedKm">
                        <i class="bx bx-save me-1"></i>Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KM Düzelt ve Onayla Modalı -->
<div class="modal fade no-upgrade" id="kmDuzeltOnaylaModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-warning py-3 px-4 border-0" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-white text-warning">
                            <i class="bx bx-check-shield"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold">KM Düzelt ve Onayla</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kmDuzeltOnaylaForm">
                <input type="hidden" id="duzelt_onay_id" name="id">
                
                <div class="modal-body p-4">
                    <div class="p-3 border rounded bg-light mb-3" style="font-size: 0.9rem; border-left: 4px solid #ffbb44 !important;">
                        <div class="mb-1"><span class="fw-bold text-muted">Personel:</span> <span id="lbl_duzelt_personel" class="fw-bold text-dark"></span></div>
                        <div class="mb-1"><span class="fw-bold text-muted">Araç Plaka:</span> <span id="lbl_duzelt_plaka" class="fw-bold text-dark"></span></div>
                        <div class="mb-1"><span class="fw-bold text-muted">Tarih / Tür:</span> <span id="lbl_duzelt_tarih_tur" class="fw-bold text-dark"></span></div>
                        <div class="mb-0"><span class="fw-bold text-muted">Bildirilen KM:</span> <span id="lbl_duzelt_bildirilen_km" class="fw-bold text-danger"></span></div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="duzelt_onay_km_value" class="form-label fw-bold text-dark">Doğru KM Değeri</label>
                        <input type="number" class="form-control" id="duzelt_onay_km_value" name="km" min="0" required>
                    </div>

                    <div class="form-group mb-0">
                        <label for="duzelt_onay_aciklama" class="form-label fw-bold text-dark">Açıklama (İsteğe Bağlı)</label>
                        <textarea class="form-control" id="duzelt_onay_aciklama" name="aciklama" rows="3" placeholder="KM düzeltme nedeni veya açıklama girin..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-0" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold" id="btnSubmitKmDuzeltOnayla">
                        <i class="bx bx-check me-1"></i>Güncelle ve Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/arac-takip/js/arac-takip.js?v=<?= time() ?>"></script>
<script>
$(document).ready(function() {
    // Özet Kartları Açma/Kapama (AGENTS.md Standardı)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('km-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
            toggleBtn.attr('title', isHidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('km-summary-hidden');
        $('html').toggleClass('km-summary-hidden', willHide);
        localStorage.setItem('km_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    // Özet Kartları Hızlı Filtre Butonları Tıklanması
    $('.status-quick-filter').on('click', function() {
        const targetTab = $(this).data('tab');
        if (targetTab) {
            $('.status-quick-filter').removeClass('active');
            $(this).addClass('active');
            $(`.status-filter-group a[href="${targetTab}"]`).tab('show');
        }
    });

    const pendingColumns = [
        { 
            data: 'checkbox', 
            orderable: false, 
            searchable: false, 
            className: 'text-center' 
        },
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-primary' },
        { data: 'aciklama', name: 'aciklama' },
        { data: 'resim', orderable: false, searchable: false, className: 'text-center' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    const approvedColumns = [
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-primary' },
        { data: 'onaylanan_km', name: 'onaylanan_km', className: 'text-end fw-bold text-success' },
        { data: 'onaylayan_tarih', name: 'onaylayan_tarih' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    const rejectedColumns = [
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-danger' },
        { data: 'red_nedeni', name: 'red_nedeni' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    function initServerSideDataTable(selector, status, columnsConfig, defaultOrder) {
        if (!$(selector).length) return null;
        
        let options = typeof getDatatableOptions === "function" ? getDatatableOptions() : {
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json" },
            pageLength: 10
        };

        if (typeof applyLengthStateSave === "function") {
            options = applyLengthStateSave(options);
        }

        options.processing = true;
        options.serverSide = true;
        options.ajax = {
            url: "views/arac-takip/api.php",
            type: "POST",
            data: function (d) {
                d.action = "get-km-onay-list-server-side";
                d.status = status;
            }
        };
        options.columns = columnsConfig;
        options.order = defaultOrder || [[0, "desc"]];
        options.dom = '<"d-none"B>t<"row align-items-center mt-3"<"col-sm-12 col-md-6 d-flex align-items-center justify-content-start"i<"ms-3 text-nowrap"l>><"col-sm-12 col-md-6 d-flex justify-content-end"p>>';
        options.buttons = [
            {
                extend: "excelHtml5",
                className: "d-none",
                text: "Excel",
                exportOptions: {
                    columns: ":visible:not(:last-child)"
                }
            }
        ];

        return $(selector).DataTable(options);
    }

    // Initialize Server-Side Tables
    initServerSideDataTable('#tablePending', 'beklemede', pendingColumns, [[5, 'desc']]);
    initServerSideDataTable('#tableApproved', 'onaylandi', approvedColumns, [[4, 'desc']]);
    initServerSideDataTable('#tableRejected', 'reddedildi', rejectedColumns, [[4, 'desc']]);

    // Excel Export Handler
    $('#exportExcelKm, #btnDropdownExportExcel').on('click', function() {
        var activeTable = $('.tab-pane.active table').DataTable();
        if (activeTable) {
            activeTable.button('.buttons-excel').trigger();
        }
    });

    // Yazdır Butonu
    $('#btnHeaderPrint').on('click', function() {
        window.print();
    });

    // Handle tab changes to re-adjust datatable columns and sync quick filter buttons
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href');
        
        // Özet kartlarındaki aktif butonu senkronize et
        $('.status-quick-filter').removeClass('active');
        $(`.status-quick-filter[data-tab="${target}"]`).addClass('active');

        // AI kontrol butonunun görünürlüğü (Yalnızca beklemede sekmesinde)
        if (target === '#pending') {
            $('#btnKmAiKontrol').removeClass('d-none');
            updateBatchButton();
        } else {
            $('#btnKmAiKontrol').addClass('d-none');
            $('#btnTopluOnayla').addClass('d-none');
        }

        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // Image Modal Viewer
    $(document).on('click', '.btn-view-km-img', function() {
        const btn = $(this);
        const imgSrc = btn.data('img');
        const plaka = btn.data('plaka');
        const date = btn.data('date');
        const tur = btn.data('tur');

        $('#modalImgHeaderInfo').text(plaka + ' | ' + date + ' - ' + tur);
        $('#modalViewImg').attr('src', imgSrc);
        $('#imgViewModal').modal('show');
    });

    // Onay Yapmayanlar Listesini Yükle
    function loadUnreported() {
        if ($.fn.DataTable.isDataTable('#tableUnreported')) {
            $('#tableUnreported').DataTable().destroy();
        }
        $('#unreportedListBody').html('<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Veriler kontrol ediliyor...</p></td></tr>');

        $.get('views/arac-takip/api.php?action=get-km-onay-yapmayanlar', function(res) {
            if (res.status === 'success') {
                const body = $('#unreportedListBody');
                body.empty();
                
                const unCount = res.data ? res.data.length : 0;
                $('#stat_yapmayanlar_sayi').text(unCount);
                $('#badge_unreported').text(unCount);

                if (unCount === 0) {
                    body.append('<tr><td colspan="7" class="text-center py-4 text-success"><i class="bx bx-check-double fs-2"></i><br>Tüm bildirimler eksiksiz!</td></tr>');
                    return;
                }
                res.data.forEach((r, i) => {
                    const turBadge = r.hedef_tur === 'sabah' ? '<span class="badge bg-soft-warning text-warning"><i class="bx bx-sun"></i> Sabah</span>' : '<span class="badge bg-soft-info text-info"><i class="bx bx-moon"></i> Akşam</span>';
                    const aksamEksikBadge = Number(r.son_30_gun_aksam_eksik || 0) > 0
                        ? `<br><span class="badge bg-soft-danger text-danger mt-1">Son 30 gün: ${r.son_30_gun_aksam_eksik} akşam eksik</span>`
                        : '';
                    const rowId = `row_un_${r.personel_id}_${r.hedef_tarih}_${r.hedef_tur}`;
                    body.append(`
                        <tr>
                            <td class="text-center">
                                <div class="unreported-checkbox-wrapper">
                                    <input type="checkbox" class="unreported-checkbox row-selector" id="${rowId}"
                                        data-id="${r.personel_id}" 
                                        data-tarih="${r.hedef_tarih}" 
                                        data-tur="${r.hedef_tur}"
                                        data-adi="${r.personel_adi}">
                                    <label for="${rowId}" class="custom-check mb-0"></label>
                                </div>
                            </td>
                            <td><span class="fw-bold">${r.personel_adi}</span>${aksamEksikBadge}</td>
                            <td><span class="badge bg-light text-dark border">${r.plaka}</span></td>
                            <td>${r.hedef_tarih.split('-').reverse().join('.')}</td>
                            <td><span class="fw-bold text-danger">${r.gecikme_turu}</span> <br> ${turBadge}</td>
                            <td><a href="tel:${r.telefon || ''}">${r.telefon || '-'}</a></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-subtle-warning btn-send-reminder" 
                                    data-id="${r.personel_id}" 
                                    data-tarih="${r.hedef_tarih}" 
                                    data-tur="${r.hedef_tur}"
                                    title="Bildirim Gönder">
                                    <i class="bx bx-bell me-1"></i> Bildirim Gönder
                                </button>
                            </td>
                        </tr>
                    `);
                });

                // Initialize DataTable
                if (typeof AracTakip !== 'undefined' && AracTakip.initDataTable) {
                    AracTakip.initDataTable('#tableUnreported');
                }
            } else {
                $('#unreportedListBody').html(`<tr><td colspan="7" class="text-center py-4 text-warning"><i class="bx bx-error fs-2"></i><br>${res.message || 'Veriler alınamadı.'}</td></tr>`);
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error("AJAX Error:", error);
            $('#unreportedListBody').html('<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bx bx-error fs-2"></i><br>Veriler yüklenirken sunucu hatası oluştu.</td></tr>');
        });
    }

    $(document).on('click', '#tabUnreportedLink', function() {
        loadUnreported();
    });

    // Sayfa açıldığında veya yenilendiğinde hash kontrolü
    function checkInitialTab() {
        const hash = window.location.hash;
        const activeTab = $('.nav-link.active').attr('href');
        
        if (hash === '#unreported' || activeTab === '#unreported') {
            loadUnreported();
        }
    }
    setTimeout(checkInitialTab, 300);

    // Toplu Seçim Olayları (DataTables uyumlu)
    $(document).on('change', '#checkAllUnreported', function() {
        const isChecked = $(this).is(':checked');
        $('.row-selector').prop('checked', isChecked);
        
        if (isChecked) {
            $('#tableUnreported').addClass('all-selected-mode');
        } else {
            $('#tableUnreported').removeClass('all-selected-mode');
        }
        
        updateSelectionSummary();
    });

    $(document).on('change', '.row-selector', function() {
        if (!$(this).is(':checked')) {
            $('#checkAllUnreported').prop('checked', false);
            $('#tableUnreported').removeClass('all-selected-mode');
        }
        updateSelectionSummary();
    });

    function updateSelectionSummary() {
        const table = $('#tableUnreported').DataTable();
        let count = 0;
        
        if ($('#tableUnreported').hasClass('all-selected-mode')) {
            count = table.rows({ filter: 'applied' }).count();
        } else {
            count = $('.row-selector:checked').length;
        }
        
        if (count > 0) {
            $('#btnBulkSendReminder').removeClass('d-none');
            $('#selectionSummary').html(`<span class="badge bg-primary">${count}</span> kayıt seçildi`);
        } else {
            $('#btnBulkSendReminder').addClass('d-none');
            $('#selectionSummary').text('');
        }
    }

    // Toplu Bildirim Gönderimi (Tüm sayfaları kapsar)
    $(document).on('click', '#btnBulkSendReminder', function() {
        const table = $('#tableUnreported').DataTable();
        const selectedItems = [];
        
        if ($('#tableUnreported').hasClass('all-selected-mode')) {
            table.rows({ filter: 'applied' }).every(function() {
                const node = this.node();
                const $chk = $(node).find('.row-selector');
                selectedItems.push({
                    personel_id: $chk.data('id'),
                    tarih: $chk.data('tarih'),
                    tur: $chk.data('tur'),
                    adi: $chk.data('adi')
                });
            });
        } else {
            $('.row-selector:checked').each(function() {
                selectedItems.push({
                    personel_id: $(this).data('id'),
                    tarih: $(this).data('tarih'),
                    tur: $(this).data('tur'),
                    adi: $(this).data('adi')
                });
            });
        }

        if (selectedItems.length === 0) return;

        Swal.fire({
            title: 'Toplu Bildirim',
            text: `${selectedItems.length} personele hatırlatma bildirimi gönderilecektir. Onaylıyor musunuz?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#3b82f6'
        }).then((result) => {
            if (result.isConfirmed) {
                processBulkReminders(selectedItems);
            }
        });
    });

    async function processBulkReminders(items) {
        Swal.fire({
            title: 'Bildirimler Gönderiliyor',
            html: `Lütfen pencereyi kapatmayın... <br><br> <div class="progress mb-2"><div class="progress-bar progress-bar-animated progress-bar-striped" role="progressbar" style="width: 0%"></div></div> <b id="bulkProgressText">0 / ${items.length}</b>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let successCount = 0;
        const progressBar = Swal.getHtmlContainer().querySelector('.progress-bar');
        const progressText = Swal.getHtmlContainer().querySelector('#bulkProgressText');

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            try {
                const res = await $.post('views/arac-takip/api.php', {
                    action: 'send-km-bildirim-hatirlatma',
                    personel_id: item.personel_id,
                    tarih: item.tarih,
                    tur: item.tur
                });
                if (res.status === 'success') successCount++;
            } catch (err) {
                console.error("Bulk send error for " + item.adi, err);
            }

            const percent = Math.round(((i + 1) / items.length) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = `${i + 1} / ${items.length}`;
        }

        Swal.fire({
            title: 'İşlem Tamamlandı',
            text: `${successCount} personele bildirim başarıyla iletildi.`,
            icon: 'success',
            confirmButtonText: 'Kapat'
        }).then(() => {
            loadUnreported();
        });
    }

    // Hatırlatma Gönder
    $(document).on('click', '.btn-send-reminder', function() {
        const btn = $(this);
        const personelId = btn.data('id');
        const tarih = btn.data('tarih');
        const tur = btn.data('tur');
        const personelAdi = btn.closest('tr').find('td').eq(1).text();

        const trFmt = tur === 'sabah' ? 'Sabah' : 'Akşam';
        const tarihFmt = tarih.split('-').reverse().join('.');
        const message = `${tarihFmt} tarihli ${trFmt} bildirimini yapmadınız, Lütfen yapınız.`;

        Swal.fire({
            title: 'Bildirim Onayı',
            html: `
                <div class="text-start">
                    <p class="mb-2">Aşağıdaki personele hatırlatma bildirimi gönderilecektir:</p>
                    <div class="p-2 border rounded bg-light mb-3">
                        <span class="fw-bold">Personel:</span> ${personelAdi}
                    </div>
                    <p class="mb-2 fw-bold">Gönderilecek Mesaj:</p>
                    <div class="alert alert-warning py-2 px-3 border-0 bg-soft-warning text-warning mb-0" style="font-size: 0.9rem;">
                        ${message}
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#74788d',
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'İptal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i>');

                const data = {
                    action: 'send-km-bildirim-hatirlatma',
                    personel_id: personelId,
                    tarih: tarih,
                    tur: tur
                };

                $.post('views/arac-takip/api.php', data, function(res) {
                    if (res.status === 'success') {
                        if (typeof Toastify !== 'undefined') {
                            Toastify({
                                text: "Bildirim başarıyla gönderildi.",
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                stopOnFocus: true,
                                style: {
                                    background: "linear-gradient(to right, #00b09b, #96c93d)",
                                    borderRadius: "8px",
                                    boxShadow: "0 4px 12px rgba(0,0,0,0.1)"
                                }
                            }).showToast();
                        }

                        btn.prop('disabled', false)
                            .removeClass('btn-subtle-warning')
                            .addClass('btn-subtle-secondary btn-sm')
                            .html('<i class="bx bx-redo me-1"></i> Tekrar Gönder');
                        
                        if (btn.parent().find('.sent-status-icon').length === 0) {
                            btn.before('<span class="sent-status-icon me-1" style="color: #34B7F1 !important; font-size: 1.3rem; vertical-align: middle;" title="Gönderildi"><i class="bx bx-check-double"></i></span>');
                        }
                    } else {
                        Swal.fire('Hata', res.message, 'error');
                        btn.prop('disabled', false).html('<i class="bx bx-bell me-1"></i> Bildirim Gönder');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
                    btn.prop('disabled', false).html('<i class="bx bx-bell me-1"></i> Bildirim Gönder');
                });
            }
        });
    });

    // Toplu Seçim İşlemleri
    $('#checkAllKm').on('change', function() {
        $('.km-checkbox').prop('checked', $(this).is(':checked'));
        updateBatchButton();
    });

    $(document).on('change', '.km-checkbox', function() {
        updateBatchButton();
        if ($('.km-checkbox:checked').length === $('.km-checkbox').length) {
            $('#checkAllKm').prop('checked', true);
        } else {
            $('#checkAllKm').prop('checked', false);
        }
    });

    function updateBatchButton() {
        const selectedCount = $('.km-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);
        $('#aiSelectedCount').text(selectedCount);
        $('#btnKmAiKontrol').prop('disabled', selectedCount === 0);
        if (selectedCount > 0) {
            $('#btnTopluOnayla').removeClass('d-none');
        } else {
            $('#btnTopluOnayla').addClass('d-none');
        }
    }

    $('#btnKmAiKontrol').on('click', function() {
        const ids = $('.km-checkbox:checked').map(function() {
            return $(this).data('id');
        }).get();
        if (!ids.length) return;

        Swal.fire({
            icon: 'question',
            title: 'Yapay Zekâ ile KM Kontrolü',
            html: `<b>${ids.length}</b> fotoğraf analiz edilecek.<br><small class="text-muted">Yalnızca KM, plaka ve bildirim türü güvenli biçimde eşleşen kayıtlar otomatik onaylanır. Diğerleri beklemede kalır.</small>`,
            showCancelButton: true,
            confirmButtonText: 'Analizi Başlat',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#556ee6'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Fotoğraflar Analiz Ediliyor',
                html: 'KM, plaka ve bildirim türü karşılaştırılıyor.<br><small>Lütfen pencereyi kapatmayın.</small>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: 'views/arac-takip/api.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'km-ai-kontrol-onayla', ids: ids }
            }).done(function(res) {
                if (res.status !== 'success') {
                    return Swal.fire('Hata', res.message || 'Yapay zekâ kontrolü tamamlanamadı.', 'error');
                }

                const detaylar = (res.data.sonuclar || []).map(function(item) {
                    const icon = item.durum === 'onaylandi' ? '✅' : (item.durum === 'hata' ? '❌' : '⚠️');
                    const plaka = item.plaka ? `<b>${item.plaka}</b> – ` : `#${item.id} – `;
                    return `<div class="text-start border-bottom py-2">${icon} ${plaka}${item.neden}</div>`;
                }).join('');

                Swal.fire({
                    icon: res.data.onaylanan > 0 ? 'success' : 'info',
                    title: 'Yapay Zekâ Kontrolü Tamamlandı',
                    html: `<p>${res.message}</p><div style="max-height:260px;overflow:auto">${detaylar}</div>`,
                    confirmButtonText: 'Tamam',
                    width: 620
                }).then(() => window.location.reload());
            }).fail(function(xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Yapay zekâ kontrolü sırasında bağlantı hatası oluştu.';
                Swal.fire('Hata', message, 'error');
            });
        });
    });

    $(document).on('click', '.btn-km-ai-reason', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const reason = String($(this).data('ai-reason') || 'Ayrıntılı gerekçe bulunamadı.');
        const plate = String($(this).data('plaka') || 'KM Bildirimi');
        Swal.fire({
            icon: 'info',
            title: 'Yapay Zekâ Onaylamama Nedeni',
            html: $('<div>').append(
                $('<div>', { class: 'fw-bold mb-2', text: plate }),
                $('<div>', { class: 'text-start small lh-base', text: reason })
            ),
            confirmButtonText: 'Kapat',
            confirmButtonColor: '#556ee6',
            width: 560
        });
    });

    $('#btnTopluOnayla').on('click', function() {
        const selectedIds = [];
        let hasInvalidAksam = false;
        
        const now = new Date();
        const currentHour = now.getHours();
        const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

        $('.km-checkbox:checked').each(function() {
            const id = $(this).data('id');
            const tur = $(this).data('tur');
            const tarih = $(this).data('tarih');

            if (tarih === todayStr && tur === 'aksam' && currentHour < 13) {
                hasInvalidAksam = true;
                $(this).closest('tr').addClass('table-danger');
                setTimeout(() => $(this).closest('tr').removeClass('table-danger'), 5000);
            } else {
                selectedIds.push(id);
            }
        });

        if (hasInvalidAksam) {
            Swal.fire({
                icon: 'warning',
                title: 'Uyarı',
                text: 'Bugün için henüz akşam KM onayı yapılamaz (Saat 13:00\'dan sonra onaylanabilir). Akşam türündeki kayıtlar hariç tutularak devam edilsin mi?',
                showCancelButton: true,
                confirmButtonText: 'Evet, Kalanları Onayla',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#34c38f',
            }).then((result) => {
                if (result.isConfirmed && selectedIds.length > 0) {
                    AracTakip.kmTopluOnayla(selectedIds);
                }
            });
        } else if (selectedIds.length > 0) {
            AracTakip.kmTopluOnayla(selectedIds);
        }
    });

    // Onaylı KM Düzenleme modalını aç
    $(document).on('click', '.btn-edit-approved-km', function() {
        const btn = $(this);
        const id = btn.data('id');
        const aracId = btn.data('arac-id');
        const plaka = btn.data('plaka');
        const tarihRaw = btn.data('date-raw');
        const tur = btn.data('tur');
        const km = btn.data('km');
        const aciklama = btn.data('aciklama');

        $('#edit_approved_id').val(id);
        $('#edit_approved_arac_id').val(aracId);
        $('#lbl_edit_plaka').text(plaka);
        $('#edit_approved_date').val(tarihRaw);
        $('#edit_approved_tur').val(tur);
        $('#edit_approved_km_value').val(km);
        $('#edit_approved_aciklama').val(aciklama || '');

        $('#editApprovedKmModal').modal('show');
    });

    // Onaylı KM Düzenleme formunu gönder
    $('#editApprovedKmForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btnSubmit = $('#btnSubmitEditApprovedKm');
        const oldBtnHtml = btnSubmit.html();
        
        btnSubmit.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Güncelleniyor...');

        const formData = form.serialize() + '&action=onayli-km-guncelle';

        $.post('views/arac-takip/api.php', formData, function(res) {
            if (res.status === 'success') {
                $('#editApprovedKmModal').modal('hide');
                Swal.fire({
                    title: 'Başarılı',
                    text: 'KM kaydı başarıyla güncellendi.',
                    icon: 'success',
                    confirmButtonText: 'Tamam'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata', res.message, 'error');
                btnSubmit.prop('disabled', false).html(oldBtnHtml);
            }
        }, 'json').fail(function() {
            Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
            btnSubmit.prop('disabled', false).html(oldBtnHtml);
        });
    });

    // KM Düzelt ve Onayla modalını aç
    $(document).on('click', '.btn-km-duzelt-onayla', function() {
        const btn = $(this);
        const id = btn.data('id');
        const aracId = btn.data('arac-id');
        const plaka = btn.data('plaka');
        const personel = btn.data('personel');
        const tarihRaw = btn.data('tarih');
        const tur = btn.data('tur');
        const km = btn.data('km');

        $('#duzelt_onay_id').val(id);
        $('#lbl_duzelt_personel').text(personel);
        $('#lbl_duzelt_plaka').text(plaka);
        
        const turFmt = tur === 'sabah' ? 'Sabah' : 'Akşam';
        const dateFmt = tarihRaw.split('-').reverse().join('.');
        $('#lbl_duzelt_tarih_tur').text(dateFmt + ' / ' + turFmt);
        $('#lbl_duzelt_bildirilen_km').text(new Intl.NumberFormat("tr-TR").format(km) + ' KM');
        $('#duzelt_onay_km_value').val(km);
        $('#duzelt_onay_aciklama').val('');

        $('#kmDuzeltOnaylaModal').modal('show');
    });

    // KM Düzelt ve Onayla formunu gönder
    $('#kmDuzeltOnaylaForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btnSubmit = $('#btnSubmitKmDuzeltOnayla');
        const oldBtnHtml = btnSubmit.html();
        
        btnSubmit.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Güncelleniyor...');

        const formData = form.serialize() + '&action=km-onay-duzelt-onayla';

        $.post('views/arac-takip/api.php', formData, function(res) {
            if (res.status === 'success') {
                $('#kmDuzeltOnaylaModal').modal('hide');
                Swal.fire({
                    title: 'Başarılı',
                    text: 'KM kaydı düzeltilerek onaylandı.',
                    icon: 'success',
                    confirmButtonText: 'Tamam'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata', res.message, 'error');
                btnSubmit.prop('disabled', false).html(oldBtnHtml);
            }
        }, 'json').fail(function() {
            Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
            btnSubmit.prop('disabled', false).html(oldBtnHtml);
        });
    });

    // KM Resim Temizleme ve Arşivleme Modalı Olayları
    $('#imageCleanupModal').on('show.bs.modal', function () {
        $('#cleanupFormSection').removeClass('d-none');
        $('#cleanupLoadingSection').addClass('d-none');
        $('#cleanupResultSection').addClass('d-none');
        $('#cleanupCountFeedback').addClass('d-none').text('');
        $('#btnStartCleanup').addClass('d-none');
        
        $('#cleanup_date_range').val('').flatpickr({
            mode: 'range',
            locale: 'tr',
            dateFormat: 'd.m.Y'
        });

        if (typeof feather !== "undefined") {
            setTimeout(function() {
                feather.replace();
            }, 100);
        }
    });

    $('#btnCheckCleanupCount').on('click', function () {
        const dateRange = $('#cleanup_date_range').val();
        if (!dateRange) {
            Swal.fire('Uyarı', 'Lütfen bir tarih aralığı seçin.', 'warning');
            return;
        }

        const btn = $(this);
        const oldHtml = btn.html();

        btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Sorgulanıyor...');
        $('#cleanupCountFeedback').addClass('d-none');
        $('#btnStartCleanup').addClass('d-none');

        $.post('views/arac-takip/api.php', {
            action: 'get-km-image-cleanup-count',
            date_range: dateRange
        }, function (res) {
            btn.prop('disabled', false).html(oldHtml);
            if (res.status === 'success') {
                const count = res.count;
                $('#cleanupCountFeedback')
                    .removeClass('d-none alert-danger alert-info alert-warning')
                    .addClass(count > 0 ? 'alert-info' : 'alert-warning')
                    .html(`Seçilen tarih aralığında <b>${count}</b> adet resim dosyası bulundu.`);
                
                if (count > 0) {
                    $('#btnStartCleanup').removeClass('d-none');
                }
            } else {
                $('#cleanupCountFeedback')
                    .removeClass('d-none alert-info alert-warning')
                    .addClass('alert-danger')
                    .text(res.message || 'Sorgulama sırasında hata oluştu.');
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false).html(oldHtml);
            Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
        });
    });

    $('#btnStartCleanup').on('click', function () {
        const dateRange = $('#cleanup_date_range').val();
        if (!dateRange) {
            Swal.fire('Uyarı', 'Lütfen bir tarih aralığı seçin.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu işlem belirtilen tarih aralığındaki tüm resimleri arşivleyip sunucudan kalıcı olarak silecektir!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f46a6a',
            confirmButtonText: 'Evet, Arşivle ve Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#cleanupFormSection').addClass('d-none');
                $('#cleanupLoadingSection').removeClass('d-none');

                $.post('views/arac-takip/api.php', {
                    action: 'start-km-image-cleanup',
                    date_range: dateRange
                }, function (res) {
                    $('#cleanupLoadingSection').addClass('d-none');
                    if (res.status === 'success') {
                        $('#btnDownloadCleanupZip').attr('href', res.zip_url);
                        $('#cleanupResultSection').removeClass('d-none');
                    } else {
                        $('#cleanupFormSection').removeClass('d-none');
                        Swal.fire('Hata', res.message, 'error');
                    }
                }, 'json').fail(function () {
                    $('#cleanupLoadingSection').addClass('d-none');
                    $('#cleanupFormSection').removeClass('d-none');
                    Swal.fire('Hata', 'İşlem sırasında bağlantı hatası oluştu.', 'error');
                });
            }
        });
    });

    $('#btnDeleteServerZip').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Siliniyor...');
        
        $.post('views/arac-takip/api.php', {
            action: 'delete-km-archive-zip'
        }, function (res) {
            btn.prop('disabled', false).html('<i class="bx bx-trash me-2"></i>Arşiv Dosyasını Sunucudan Sil');
            if (res.status === 'success') {
                $('#imageCleanupModal').modal('hide');
                Swal.fire('Başarılı', 'Arşiv dosyası sunucudan başarıyla silindi.', 'success');
            } else {
                Swal.fire('Hata', 'Arşiv silinirken hata oluştu.', 'error');
            }
        }, 'json').fail(function () {
            btn.prop('disabled', false).html('<i class="bx bx-trash me-2"></i>Arşiv Dosyasını Sunucudan Sil');
            Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
        });
    });
});
</script>
