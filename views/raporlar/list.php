<?php
use App\Helper\Form;
use App\Service\Gate;

$maintitle = 'Raporlar';
$title = 'Toplu Rapor Listesi';
?>
<script>try { document.documentElement.classList.toggle('raporlar-summary-hidden', localStorage.getItem('raporlar_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.raporlar-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

/* Rapor Preloader */
.rapor-preloader {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.82);
    z-index: 1060;
    border-radius: 4px;
    backdrop-filter: blur(3px);
    display: none;
}

[data-bs-theme="dark"] .rapor-preloader {
    background: rgba(25, 30, 34, 0.85);
}

.rapor-preloader .loader-content {
    position: absolute;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    text-align: center;
    min-width: 250px;
}

[data-bs-theme="dark"] .rapor-preloader .loader-content {
    background: #2a3042;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
}

.table-container {
    transition: opacity 0.3s ease;
}

/* Kişi ve Rozet Tasarımları */
.report-person { display: flex; align-items: center; gap: 9px; min-width: 150px; }
.report-person-avatar { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; background: #e8edff; color: #4962d8; font-size: 11px; font-weight: 700; }
.report-person-name { color: #27334a; font-weight: 600; }
.report-badge { display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; border-radius: 20px; padding: 4px 8px; font-size: 11px; font-weight: 600; line-height: 1.2; white-space: nowrap; }
.report-badge i { font-size: 13px; }
.report-badge-success { background: #e8f8f0; border-color: #c8eedb; color: #138a58; }
.report-badge-warning { background: #fff6df; border-color: #f7df9d; color: #b57705; }
.report-badge-danger { background: #feebed; border-color: #fac9ce; color: #d83b48; }
.report-badge-info { background: #e8f5fb; border-color: #c4e6f5; color: #167ca8; }
.report-badge-primary { background: #edf0ff; border-color: #d4dcff; color: #4962d8; }
.report-badge-secondary { background: #f0f2f5; border-color: #dfe3e8; color: #687386; }
.report-date { display: inline-flex; align-items: center; gap: 5px; color: #58657a; white-space: nowrap; }
.report-date i { color: #8a96a8; font-size: 14px; }
.report-money { color: #27334a; font-weight: 700; white-space: nowrap; }
.report-description { display: block; max-width: 320px; overflow: hidden; text-overflow: ellipsis; color: #687386; white-space: nowrap; }

[data-bs-theme="dark"] .report-person-name, [data-bs-theme="dark"] .report-money { color: #e9edf5; }

/* Tablo İşlem Butonları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
}
.btn-subtle-primary { background: rgba(85, 110, 230, .12); color: #556ee6; border: 1px solid transparent; }
.btn-subtle-primary:hover { background: #556ee6; color: #fff; }
.btn-subtle-success { background: rgba(52, 195, 143, .12); color: #34c38f; border: 1px solid transparent; }
.btn-subtle-success:hover { background: #34c38f; color: #fff; }
.btn-subtle-danger { background: rgba(244, 106, 106, .12); color: #f46a6a; border: 1px solid transparent; }
.btn-subtle-danger:hover { background: #f46a6a; color: #fff; }
.btn-subtle-info { background: rgba(80, 165, 241, .12); color: #50a5f1; border: 1px solid transparent; }
.btn-subtle-info:hover { background: #50a5f1; color: #fff; }
.btn-subtle-secondary { background: rgba(116, 120, 141, .12); color: #74788d; border: 1px solid transparent; }
.btn-subtle-secondary:hover { background: #74788d; color: #fff; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık Satırı ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-bar-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Toplu Raporlar</h4>
                <p class="text-muted mb-0 font-size-12">Personel izin, kesinti/ek ödeme, talep ve icra hareketlerinin merkezi raporlanması ve takibi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: İZİN & İSTİRAHAT -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İZİN & İSTİRAHAT</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-calendar-event"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_izin">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_izin">Onaylı: 0 | Bekleyen: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-report="1">
                            <i class="bx bx-layer"></i> İzinler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: KESİNTİ & EK ÖDEME -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KESİNTİ & EK ÖDEME</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-wallet"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_toplam_kesinti_ek">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_sub_kesinti_ek">Ek: 0 | Kesinti: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-report="2">
                            <i class="bx bx-plus-minus"></i> Kesinti/Ek
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: PERSONEL TALEPLERİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">PERSONEL TALEPLERİ</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-message-square-detail"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_toplam_talep">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_sub_talep">Bekleyen: 0 | Çözülen: 0</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-report="3">
                            <i class="bx bx-help-circle"></i> Talepler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: İCRA DOSYALARI -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İCRA DOSYALARI</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-file-blank"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_toplam_icra">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_icra">0,00 ₺ Toplam Borç</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-report="4">
                            <i class="bx bx-error-circle"></i> İcralar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Rapor Filtre Kartı -->
    <div class="card summary-kpi-card mb-3">
        <div class="card-body p-3">
            <form id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <?= Form::FormFloatInput('text', 'baslangic_tarihi', date('01.m.Y'), 'Başlangıç Tarihi', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr', true) ?>
                    </div>
                    <div class="col-12 col-md-3">
                        <?= Form::FormFloatInput('text', 'bitis_tarihi', date('d.m.Y'), 'Bitiş Tarihi', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr', true) ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <?= Form::FormSelect2('rapor_turu', [
                            ['id' => 1, 'text' => 'İzin / Rapor Listesi'],
                            ['id' => 2, 'text' => 'Personel Kesinti / Ek Ödeme Listesi'],
                            ['id' => 3, 'text' => 'Personel Talepleri Listesi'],
                            ['id' => 4, 'text' => 'Personel İcra Listesi']
                        ], 1, 'Rapor Türü', 'list', 'id', 'text', 'form-select select2') ?>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="button" id="btnRaporGetir" class="btn btn-primary w-100 top-action-btn shadow-sm text-white d-flex align-items-center justify-content-center gap-1" style="height: 48px;">
                            <i class="bx bx-filter-alt font-size-16"></i> Raporu Getir
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. DataTables Rapor Sonuçları Kartı -->
    <div class="card summary-kpi-card mb-3" id="raporlarListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark" id="reportCardTitle">İzin / Rapor Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" id="reportCardSubtitle" style="margin-top: 2px;">Filtrelenen tarih aralığındaki personel izin ve istirahat kayıtları</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="exportExcelBtn" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0 position-relative" id="raporCardBody">
            <!-- Preloader -->
            <div class="rapor-preloader" id="rapor-loader">
                <div class="loader-content">
                    <div class="spinner-border text-primary m-1" role="status">
                        <span class="sr-only">Yükleniyor...</span>
                    </div>
                    <h5 class="mt-2 mb-0">Rapor Hazırlanıyor...</h5>
                    <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                </div>
            </div>

            <!-- Rapor Türü 1: İzinler -->
            <div id="tableContainer1" class="table-responsive table-container" style="overflow-x: auto !important;">
                <table id="table1" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable datatable-deferred">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string">Personel</th>
                            <th data-filter="string">TC Kimlik No</th>
                            <th data-filter="select">Departman</th>
                            <th data-filter="select">İzin Türü</th>
                            <th data-filter="date">Başlangıç Tarihi</th>
                            <th data-filter="date">Bitiş Tarihi</th>
                            <th data-filter="string">Gün Sayısı</th>
                            <th data-filter="select">Durum</th>
                            <th data-filter="string">Onaylayan</th>
                            <th data-filter="string">Açıklama</th>
                            <th data-filter="none" style="width: 80px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Rapor Türü 2: Kesintiler/Ek Ödemeler -->
            <div id="tableContainer2" class="table-responsive table-container" style="display: none; overflow-x: auto !important;">
                <table id="table2" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable datatable-deferred">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string">Personel</th>
                            <th data-filter="string">TC Kimlik No</th>
                            <th data-filter="select">Departman</th>
                            <th data-filter="select">İşlem Tipi</th>
                            <th data-filter="select">Tür/Parametre</th>
                            <th data-filter="string">Detay</th>
                            <th data-filter="string">Tutar</th>
                            <th data-filter="date">Tarih</th>
                            <th data-filter="select">Durum</th>
                            <th data-filter="string">Açıklama</th>
                            <th data-filter="none" style="width: 80px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Rapor Türü 3: Talepler -->
            <div id="tableContainer3" class="table-responsive table-container" style="display: none; overflow-x: auto !important;">
                <table id="table3" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable datatable-deferred">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string">Personel</th>
                            <th data-filter="string">TC Kimlik No</th>
                            <th data-filter="select">Departman</th>
                            <th data-filter="select">Kategori</th>
                            <th data-filter="string">Başlık</th>
                            <th data-filter="date">Tarih</th>
                            <th data-filter="select">Durum</th>
                            <th data-filter="date">Çözüm Tarihi</th>
                            <th data-filter="string">Çözüm Açıklaması</th>
                            <th data-filter="string">Açıklama</th>
                            <th data-filter="none" style="width: 80px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Rapor Türü 4: İcralar -->
            <div id="tableContainer4" class="table-responsive table-container" style="display: none; overflow-x: auto !important;">
                <table id="table4" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable datatable-deferred">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="string">Personel</th>
                            <th data-filter="string">TC Kimlik No</th>
                            <th data-filter="select">Departman</th>
                            <th data-filter="select">İcra Dairesi</th>
                            <th data-filter="string">Dosya No</th>
                            <th data-filter="string">Toplam Borç</th>
                            <th data-filter="string">Kesilen Tutar</th>
                            <th data-filter="string">Kalan Tutar</th>
                            <th data-filter="select">Durum</th>
                            <th data-filter="date">Tarih</th>
                            <th data-filter="string">Açıklama</th>
                            <th data-filter="none" style="width: 80px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Silme Modalı -->
<div class="modal fade" id="deleteRowModal" tabindex="-1" aria-labelledby="deleteRowModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteRowModalLabel">Kayıt Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteRowId">
                <input type="hidden" id="deleteRowType">
                <div class="alert alert-warning mb-3">
                    <i class="mdi mdi-alert-outline me-2"></i>Bu kaydı silmek istediğinize emin misiniz? Silme nedenini girmek zorunludur.
                </div>
                <div class="mb-3">
                    <label class="form-label text-danger fw-bold">Silme Nedeni / Açıklama *</label>
                    <textarea class="form-control" id="deleteRowAciklama" rows="3" placeholder="Lütfen neden silindiğini açıklayın..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete"><i class="mdi mdi-delete me-1"></i> Kaydı Sil</button>
            </div>
        </div>
    </div>
</div>

<script>
    var canDeleteTableRow = <?= Gate::allows("toplu_raporlar_satir_silme") ? 'true' : 'false' ?>;
</script>

<script src="views/raporlar/js/list.js?v=<?= time() ?>"></script>
