<?php

use App\Helper\Date;
use App\Helper\Form;
use App\Model\EndeksOkumaModel;

$EndeksOkuma = new EndeksOkumaModel();

// Varsayılan dönem değerleri
$defaultEnd = date('Ym');
$defaultStart = date('Ym', strtotime('-2 months'));

$baslangicDonem = $_GET['baslangic_donem'] ?? $defaultStart;
$bitisDonem = $_GET['bitis_donem'] ?? $defaultEnd;
$ilceTipi = $_GET['ilce_tipi'] ?? '';
$bolge = $_GET['bolge'] ?? '';
$defter = $_GET['defter'] ?? '';

// Bölge ve Defter listelerini endeks_okuma tablosundan çek
$firmaId = $_SESSION['firma_id'] ?? 0;
$bolgeListStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT defter_bolge FROM tanimlamalar WHERE firma_id = ? AND grup = 'defter_kodu' AND silinme_tarihi IS NULL AND defter_bolge IS NOT NULL AND defter_bolge != '' ORDER BY defter_bolge");
$bolgeListStmt->execute([$firmaId]);
$bolgeListRaw = $bolgeListStmt->fetchAll(PDO::FETCH_COLUMN);
$bolgeOptions = ['' => 'Seçiniz...'];
foreach ($bolgeListRaw as $b) {
    $bolgeOptions[$b] = $b;
}

$defterListStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT tur_adi FROM tanimlamalar WHERE firma_id = ? AND grup = 'defter_kodu' AND silinme_tarihi IS NULL AND tur_adi IS NOT NULL AND tur_adi != '' ORDER BY tur_adi");
$defterListStmt->execute([$firmaId]);
$defterListRaw = $defterListStmt->fetchAll(PDO::FETCH_COLUMN);
$defterOptions = ['' => 'Seçiniz...'];
foreach ($defterListRaw as $df) {
    $defterOptions[$df] = $df;
}

// Mevcut dönemleri çek
$donemListStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT DATE_FORMAT(tarih, '%Y%m') as donem FROM endeks_okuma WHERE firma_id = ? AND silinme_tarihi IS NULL ORDER BY donem DESC");
$donemListStmt->execute([$firmaId]);
$donemListRaw = $donemListStmt->fetchAll(PDO::FETCH_COLUMN);
$donemOptions = [];
foreach ($donemListRaw as $d) {
    $donemOptions[$d] = substr($d, 0, 4) . '/' . substr($d, 4, 2);
}

// İlçe Tipi - endeks_okuma tablosunda yok, sabit liste
$ilceTipiOptions = ['' => 'Seçiniz...', 'Uzak İlçeler' => 'Uzak İlçeler', 'Merkez' => 'Merkez', 'Yakın İlçeler' => 'Yakın İlçeler'];
?>

<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "Defter Bazlı Rapor";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <?php if (\App\Service\Gate::allows('defter_bazli_rapor_alt_limit')): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#defterLimitAyarlarModal">
                <i class="bx bx-cog fs-5"></i> 
            </button>
        </div>
    <?php endif; ?>

    <!-- ======= SEKME NAVİGASYONU ======= -->
    <ul class="nav nav-tabs nav-tabs-custom mb-3" id="defterRaporTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-abone-donem" data-bs-toggle="tab" data-bs-target="#pane-abone-donem"
                type="button" role="tab" aria-controls="pane-abone-donem" aria-selected="true">
                <i class="bx bx-bar-chart-alt-2 me-1"></i>Abone Dönem Karşılaştırma
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-okuma-gun" data-bs-toggle="tab" data-bs-target="#pane-okuma-gun"
                type="button" role="tab" aria-controls="pane-okuma-gun" aria-selected="false">
                <i class="bx bx-calendar-event me-1"></i>Okuma Gün Sayıları
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-defter-ozet" data-bs-toggle="tab" data-bs-target="#pane-defter-ozet"
                type="button" role="tab" aria-controls="pane-defter-ozet" aria-selected="false">
                <i class="bx bx-pie-chart-alt-2 me-1"></i>Aylık Defter Özeti
            </button>
        </li>
    </ul>

    <!-- Flatpickr MonthSelect Plugin Assets -->
    <link rel="stylesheet" href="assets/libs/flatpickr/plugins/monthSelect/style.css">
    <script src="assets/libs/flatpickr/plugins/monthSelect/index.js"></script>

    <style>
        /* MonthSelect premium aesthetic with borders */
        .flatpickr-monthSelect-months {
            padding: 5px !important;
            gap: 4px !important;
            display: flex !important;
            justify-content: center !important;
        }

        .flatpickr-monthSelect-month {
            color: #334155 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            margin: 0 !important;
            width: calc(33% - 4px) !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 40px !important;
        }

        .flatpickr-monthSelect-month:hover {
            background: #f8fafc !important;
            border-color: var(--bs-primary) !important;
            color: var(--bs-primary) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1) !important;
        }

        .flatpickr-monthSelect-month.selected {
            background: var(--bs-primary) !important;
            color: #fff !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 4px 6px -1px rgba(var(--bs-primary-rgb), 0.4) !important;
        }

        .flatpickr-monthSelect-month.today {
            border-color: #94a3b8 !important;
            border-width: 1px !important;
        }
    </style>

    <!-- ======= FİLTRE ACCORDION ======= -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="accordion" id="filterAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header" id="headingFilter">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseFilter" aria-expanded="false"
                                    aria-controls="collapseFilter">
                                    <div class="d-flex align-items-center justify-content-between w-100 me-3">
                                        <div>
                                            <i class="bx bx-filter-alt me-2"></i> Filtreleme Seçenekleri
                                        </div>
                                        <div id="filterSummary" class="d-none d-md-flex gap-2">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                    </div>
                                </button>
                            </h2>
                        </div>
                    </div>

                    <div id="collapseFilter" class="accordion-collapse collapse" aria-labelledby="headingFilter"
                        data-bs-parent="#filterAccordion">
                        <div class="accordion-body pt-3">
                            <!-- Satır 1: Tarihle İlgili Filtreler (3-3-6) -->
                            <!-- Satır 1: Tarihle İlgili Filtreler -->
                            <!-- Satır 1: Tarihle İlgili Filtreler -->
                            <div class="row g-3 mb-3 align-items-center">
                                <div class="col-md-4">
                                    <!-- Araba/Aralık Container -->
                                    <div id="rangeContainer">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <?php echo Form::FormFloatInput(
                                                    type: 'text',
                                                    name: 'baslangicDonem',
                                                    value: $baslangicDonem,
                                                    placeholder: '202601',
                                                    label: 'Başlangıç',
                                                    icon: 'calendar',
                                                    maxlength: 6,
                                                    class: 'form-control month-picker'
                                                ); ?>
                                            </div>
                                            <div class="col-6">
                                                <?php echo Form::FormFloatInput(
                                                    type: 'text',
                                                    name: 'bitisDonem',
                                                    value: $bitisDonem,
                                                    placeholder: '202602',
                                                    label: 'Bitiş',
                                                    icon: 'calendar',
                                                    maxlength: 6,
                                                    class: 'form-control month-picker'
                                                ); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Manuel Dönem Selection Container (Başta Gizli) -->
                                    <div id="manualContainer" style="display: none;">
                                        <?php echo Form::FormMultipleSelect2(
                                            name: 'filterDonemler',
                                            options: $donemOptions,
                                            selectedValues: [],
                                            label: 'Karşılaştırılacak Dönemleri Seçin',
                                            icon: 'bx bx-calendar',
                                            class: 'form-select select2'
                                        ); ?>
                                    </div>
                                </div>

                                <div class="col-md-8 d-flex align-items-center justify-content-md-end">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fw-semibold text-muted small"><i
                                                class="bx bx-bolt-circle me-1"></i>Hızlı Seçim:</span>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-type="manuel">Dönem Seçerek</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-months="3">Son 3 Ay</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-months="6">Son 6 Ay</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-type="bu-yil">Bu Yıl</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-type="gecen-yil">Geçen Yıl</button>
                                            <button type="button" class="btn btn-sm btn-outline-primary quick-period"
                                                data-months="12">Son 12 Ay</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Satır 2: Yerleşim Filtreleri ve Butonlar (3-3-3-3) -->
                            <div class="row g-3 align-items-center">
                                <div class="col-md-2">
                                    <?php echo Form::FormSelect2(
                                        name: 'filterIlceTipi',
                                        options: $ilceTipiOptions,
                                        selectedValue: $ilceTipi,
                                        label: 'İlçe Tipi',
                                        icon: 'bx bx-map-pin',
                                        class: 'form-select select2'
                                    ); ?>
                                </div>
                                <div class="col-md-2">
                                    <?php echo Form::FormSelect2(
                                        name: 'filterBolge',
                                        options: $bolgeOptions,
                                        selectedValue: $bolge,
                                        label: 'Bölge',
                                        icon: 'bx bx-map',
                                        class: 'form-select select2'
                                    ); ?>
                                </div>
                                <div class="col-md-2">
                                    <?php echo Form::FormSelect2(
                                        name: 'filterDefter',
                                        options: $defterOptions,
                                        selectedValue: $defter,
                                        label: 'Defter',
                                        icon: 'bx bx-book',
                                        class: 'form-select select2'
                                    ); ?>
                                </div>
                                <div class="col-md-6 d-flex align-items-center justify-content-end">
                                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">

                                        <button type="button"
                                            class="btn btn-link btn-sm text-dark text-decoration-none px-2 d-flex align-items-center"
                                            id="btnTamEkran">
                                            <i class="mdi mdi-fullscreen fs-5 me-1"></i>
                                            <span class="d-none d-xl-inline"></span>
                                        </button>
                                        <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-link btn-sm text-secondary text-decoration-none px-2"
                                            id="btnTemizle" title="Temizle">
                                            <i class="mdi mdi-filter-remove fs-5"></i>
                                        </button>
                                        <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                            id="btnExcelIndir" data-filename="rapor.xls">
                                            <i class="mdi mdi-file-excel fs-5 me-1"></i>
                                            <span class="d-none d-xl-inline">Excel</span>
                                        </button>
                                        <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                            data-bs-toggle="modal" data-bs-target="#modalManageColumns"
                                            title="Sütun Seçimi">
                                            <i class="bx bx-columns fs-5 me-1"></i>
                                            <span class="d-none d-xl-inline">Sütunlar</span>
                                        </button>
                                        <div class="vr mx-1" style="height: 20px; align-self: center;"></div>

                                        <button type="button" class="btn d-flex align-items-center" id="btnRaporGetir">
                                            <i class="mdi mdi-file-chart fs-5 me-1"></i>
                                            <span class="d-none d-xl-inline">Raporu Getir</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======= TAB 1: Abone Dönem Karşılaştırma ======= -->
    <div class="tab-content" id="defterRaporTabContent">
        <div class="tab-pane fade show active" id="pane-abone-donem" role="tabpanel" aria-labelledby="tab-abone-donem">

            <!-- ======= ÖZET KARTLARI (Minimal & Premium) ======= -->
            <div class="row g-3 mb-4" id="summaryCards" style="display: none;">
                <!-- Toplam Bölge -->
                <div class="col-xl col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: var(--bs-primary, #556ee6); border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1);">
                                    <i class="bx bx-map-alt fs-5" style="color: var(--bs-primary, #556ee6);"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">LOKASYON</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM BÖLGE</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="totalBolge" style="font-size: 1.25rem;">0
                            </h4>
                        </div>
                    </div>
                </div>
                <!-- Toplam Kayıt -->
                <div class="col-xl col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(52, 195, 143, 0.1);">
                                    <i class="bx bx-list-ul fs-5 text-success"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">VERİ</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM KAYİT</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="totalKayit" style="font-size: 1.25rem;">0
                            </h4>
                        </div>
                    </div>
                </div>
                <!-- Toplam Abone -->
                <div class="col-xl col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(244, 63, 94, 0.1);">
                                    <i class="bx bx-user fs-5 text-danger"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">ABONE</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">SON DÖNEM ABONE</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="totalAbone" style="font-size: 1.25rem;">0
                            </h4>
                        </div>
                    </div>
                </div>
                <!-- Dönem Sayısı -->
                <div class="col-xl col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(241, 180, 76, 0.1);">
                                    <i class="bx bx-calendar fs-5 text-warning"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">DÖNEM</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">DÖNEM SAYISI</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="totalDonem" style="font-size: 1.25rem;">0
                            </h4>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Tab 1 Actions -->
            <div class="row mb-2" id="reportActions" style="display: none;">
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info btn-tab-fullscreen"
                        data-target="reportSection">
                        <i class="mdi mdi-fullscreen me-1"></i>Tam Ekran
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success btn-tab-excel"
                        data-table="comparisonTable" data-filename="abone_donem_karsilastirma.xls">
                        <i class="mdi mdi-file-excel me-1"></i>Excel’e Aktar
                    </button>
                </div>
            </div>
            <div class="row" id="reportSection" style="display: none;">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive" id="reportTableWrapper"
                                style="max-height: calc(100vh - 550px); overflow: auto;">
                                <!-- AJAX ile doldurulacak -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div class="row" id="loadingSection" style="display: none;">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Rapor hazırlanıyor...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======= SÜTUN YÖNETİM MODALI ======= -->
            <div class="modal fade" id="modalManageColumns" tabindex="-1" aria-labelledby="modalManageColumnsLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                        <div class="modal-header bg-light border-bottom-0 pb-1" style="border-radius: 12px 12px 0 0;">
                            <h5 class="modal-title fw-bold fs-6" id="modalManageColumnsLabel">
                                <i class="bx bx-columns me-2 text-info"></i>Sütun Görünümü
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pt-2">
                            <div class="list-group list-group-flush">
                                <label
                                    class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                                    <span class="fw-medium text-dark"><i
                                            class="bx bx-user me-2 text-primary"></i>Abone</span>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input col-toggle" type="checkbox" data-col="abone"
                                            checked>
                                    </div>
                                </label>
                                <label
                                    class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                                    <span class="fw-medium text-dark"><i
                                            class="bx bx-show me-2 text-success"></i>Okunan</span>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input col-toggle" type="checkbox" data-col="okunan"
                                            checked>
                                    </div>
                                </label>
                                <label
                                    class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                                    <span class="fw-medium text-dark"><i
                                            class="bx bx-walk me-2 text-info"></i>Gidilen</span>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input col-toggle" type="checkbox" data-col="gidilen"
                                            checked>
                                    </div>
                                </label>
                                <label
                                    class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                                    <span class="fw-medium text-dark"><i
                                            class="bx bx-pie-chart-alt-2 me-2 text-warning"></i>Oran %</span>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input col-toggle" type="checkbox" data-col="oran"
                                            checked>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-primary btn-sm w-100 py-2 fw-bold"
                                data-bs-dismiss="modal" style="border-radius: 8px;">Değişiklikleri Uygula</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /tab-pane abone-donem -->

        <!-- ======= TAB 2: Okuma Gün Sayıları ======= -->
        <div class="tab-pane fade" id="pane-okuma-gun" role="tabpanel" aria-labelledby="tab-okuma-gun">

            <!-- Özet Kartları -->
            <div class="row g-3 mb-4" id="okumaGunSummaryCards" style="display: none;">
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: var(--bs-primary, #556ee6); border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1);">
                                    <i class="bx bx-book-open fs-5" style="color: var(--bs-primary, #556ee6);"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">DEFTER</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM DEFTER</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="okumaGunTotalDefter"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(52, 195, 143, 0.1);">
                                    <i class="bx bx-map-alt fs-5 text-success"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">BÖLGE</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM BÖLGE</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="okumaGunTotalBolge"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(241, 180, 76, 0.1);">
                                    <i class="bx bx-calendar fs-5 text-warning"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">DÖNEM</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">DÖNEM SAYISI</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="okumaGunTotalDonem"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 35+ Gün Filtresi -->
            <div class="row mb-3" id="okumaGunFilterRow" style="display: none;">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between gap-3 w-100">
                            <div class="d-flex align-items-center gap-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chk35Plus" style="cursor:pointer;">
                                    <label class="form-check-label fw-semibold" for="chk35Plus" style="cursor:pointer;">
                                        <i class="bx bx-error-circle text-danger me-1"></i>Sadece 35 ve üzeri gün farkı
                                        olanları göster
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chk35PlusNoRead"
                                        style="cursor:pointer;">
                                    <label class="form-check-label fw-semibold" for="chk35PlusNoRead"
                                        style="cursor:pointer;">
                                        <i class="bx bx-time text-warning me-1"></i>35 gündür okuma yapılmayanlar
                                    </label>
                                </div>
                            </div>
                        <div id="okumaGunFilterBadges" class="d-flex flex-wrap gap-2 flex-grow-1 ms-3">
                            <!-- JS ile dolacak -->
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-info btn-tab-fullscreen"
                                data-target="okumaGunReportSection">
                                <i class="mdi mdi-fullscreen me-1"></i>Tam Ekran
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success btn-tab-excel"
                                data-table="okumaGunTable" data-filename="okuma_gun_sayilari.xls">
                                <i class="mdi mdi-file-excel me-1"></i>Excel’e Aktar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapor Tablosu -->
            <div class="row" id="okumaGunReportSection" style="display: none;">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive" id="okumaGunTableWrapper"
                                style="max-height: calc(100vh - 550px); overflow: auto;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div class="row" id="okumaGunLoadingSection" style="display: none;">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Okuma Gün Sayıları raporu hazırlanıyor...</p>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /tab-pane okuma-gun -->

        <!-- ======= TAB 3: Aylık Defter Özeti ======= -->
        <div class="tab-pane fade" id="pane-defter-ozet" role="tabpanel" aria-labelledby="tab-defter-ozet">

            <!-- Özet Kartları -->
            <div class="row g-3 mb-4" id="defterOzetSummaryCards" style="display: none;">
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: var(--bs-primary, #556ee6); border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1);">
                                    <i class="bx bx-book-open fs-5" style="color: var(--bs-primary, #556ee6);"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">DEFTER</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM DEFTER</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="defterOzetTotalDefter"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(52, 195, 143, 0.1);">
                                    <i class="bx bx-map-alt fs-5 text-success"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">BÖLGE</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">TOPLAM BÖLGE</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="defterOzetTotalBolge"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-2 px-3">
                            <div class="icon-label-container mb-2">
                                <div class="icon-box"
                                    style="width: 32px; height: 32px; border-radius: 8px; background: rgba(241, 180, 76, 0.1);">
                                    <i class="bx bx-calendar fs-5 text-warning"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.6rem;">DÖNEM</span>
                            </div>
                            <p class="text-muted mb-0 small fw-bold"
                                style="letter-spacing: 0.5px; opacity: 0.7; font-size: 0.65rem;">DÖNEM SAYISI</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="defterOzetTotalDonem"
                                style="font-size: 1.25rem;">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Aksiyon Butonları -->
            <div class="row mb-2" id="defterOzetActions" style="display: none;">
                <div class="col-12 d-flex justify-content-end align-items-center gap-2">
                    <div class="btn-group btn-group-sm me-auto" role="group" aria-label="Görünüm Seçimi">
                        <input type="radio" class="btn-check view-toggle" name="vOzetView" id="vOzetList" checked data-view="list" data-tab="ozet">
                        <label class="btn btn-outline-primary px-3" for="vOzetList"><i class="bx bx-list-ul me-1"></i> Liste</label>

                        <input type="radio" class="btn-check view-toggle" name="vOzetView" id="vOzetChart" data-view="chart" data-tab="ozet">
                        <label class="btn btn-outline-primary px-3" for="vOzetChart"><i class="bx bx-bar-chart-alt-2 me-1"></i> Grafik</label>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-info btn-tab-fullscreen"
                        data-target="defterOzetReportSection">
                        <i class="mdi mdi-fullscreen me-1"></i>Tam Ekran
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success btn-tab-excel"
                        data-table="defterOzetTable" data-filename="aylik_defter_ozeti.xls">
                        <i class="mdi mdi-file-excel me-1"></i>Excel'e Aktar
                    </button>
                </div>
            </div>

            <!-- Grafik Bölümü -->
            <div class="row" id="defterOzetChartSection" style="display: none;">
                <div class="col-12">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h5 class="card-title mb-0 fw-bold"><i class="bx bx-bar-chart-alt-2 me-2 text-primary"></i>Dönem Bazlı Bölge Dağılımı</h5>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary active do-chart-type" data-type="percent">Oran (%)</button>
                                    <button type="button" class="btn btn-outline-secondary do-chart-type" data-type="compare">Karşılaştırmalı (Sayı)</button>
                                    <button type="button" class="btn btn-outline-secondary do-chart-type" data-type="toplam">Toplam</button>
                                    <button type="button" class="btn btn-outline-secondary do-chart-type" data-type="okunan">Okunan</button>
                                    <button type="button" class="btn btn-outline-secondary do-chart-type" data-type="okunmayan">Okunmayan</button>
                                </div>
                            </div>
                            <div id="defterOzetChart" style="min-height: 450px; width: 100%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rapor Tablosu -->
            <div class="row" id="defterOzetReportSection" style="display: none;">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive" id="defterOzetTableWrapper"
                                style="max-height: calc(100vh - 450px); overflow: auto;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading Spinner -->
            <div class="row" id="defterOzetLoadingSection" style="display: none;">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2 text-muted">Defter özet raporu hazırlanıyor...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Okunmayan Defter Listesi Modal -->
            <div class="modal fade no-upgrade" id="modalOkunmayanDefterler" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px;">
                        <div class="modal-header border-bottom-0 pb-2" style="border-radius: 14px 14px 0 0; background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);">
                            <h5 class="modal-title fw-bold text-white" id="modalDefterListTitle">
                                <i class="bx bx-error-circle me-2"></i>Defter Listesi
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        </div>
                        <div class="modal-body pt-3" id="modalDefterListBody">
                            <!-- JS ile doldurulacak -->
                        </div>
                        <div class="modal-footer border-top-0 pt-0">
                            <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal" style="border-radius: 8px;">Kapat</button>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /tab-pane defter-ozet -->
    </div><!-- /tab-content -->

</div>

<style>
    /* ======= ACCORDION FILTER ======= */
    .accordion-button:not(.collapsed) #filterSummary {
        display: none !important;
    }

    .filter-summary-badge {
        display: flex;
        align-items: center;
        background: var(--bs-primary);
        color: #fff;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        overflow: hidden;
        border: 1px solid var(--bs-primary);
        box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
    }

    .filter-summary-badge .badge-label {
        padding: 6px 8px;
        background: rgba(0, 0, 0, 0.15);
        color: rgba(255, 255, 255, 0.85);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    .filter-summary-badge .badge-value {
        padding: 6px 10px;
        font-weight: 600;
    }

    .btn-clear-filter {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #fff;
        padding: 4px 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        height: 100%;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
    }

    .do-badge-toplam.clickable:hover {
        background: #3b82f6;
        color: white;
        border-color: #1d4ed8;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }

    .do-badge-okunan {
        display: inline-block;
        padding: 4px 10px;
        background: #ecfdf5;
        color: #059669;
        font-weight: 600;
        border-radius: 6px;
        font-size: 11px;
        min-width: 35px;
        border: 1px solid transparent;
        transition: all 0.2s;
    }

    .do-badge-okunan.clickable:hover {
        background: #10b981;
        color: white;
        border-color: #059669;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }

    .do-badge-okunmayan {
        display: inline-block;
        padding: 4px 10px;
        background: #fff1f2;
        color: #e11d48;
        font-weight: 600;
        border-radius: 6px;
        font-size: 11px;
        min-width: 35px;
        border: 1px solid #fecdd3;
        transition: all 0.2s;
    }

    .do-badge-okunmayan.zero {
        background: transparent;
        border: 1px solid #eee;
        color: #ccc;
        opacity: 0.5;
    }

    .do-badge-okunmayan:not(.zero):hover {
        background: #f43f5e;
        color: white;
        border-color: #e11d48;
        box-shadow: 0 0 0 2px rgba(225, 29, 72, 0.2);
        cursor: pointer;
    }

    /* Clickable Cursor */
    .clickable {
        cursor: pointer !important;
    }

    .btn-clear-filter:hover {
        background: rgba(255, 255, 255, 0.35);
        color: #fff;
    }

    .btn-clear-filter i {
        pointer-events: none;
    }

    /* ======= QUICK PERIOD BUTTONS ======= */
    .quick-period {
        font-size: 12px;
        padding: 4px 12px;
        font-weight: 500;
    }

    .quick-period.active-period {
        background: var(--bs-primary) !important;
        color: #fff !important;
        border-color: var(--bs-primary) !important;
    }

    /* ======= SORTABLE HEADERS ======= */
    .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 18px !important;
    }

    .sortable-header:hover {
        filter: brightness(1.15);
    }

    .sort-icon {
        position: absolute;
        right: 3px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 10px;
        opacity: 0.4;
    }

    .sort-icon.active {
        opacity: 1;
    }

    /* ======= COMPARISON TABLE ======= */
    #reportTableWrapper {
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--bs-border-color, #eee);
    }

    #comparisonTable {
        border-collapse: collapse !important;
        font-size: 12px;
        width: 100%;
        table-layout: auto;
        min-width: 1200px;
        border: 1px solid var(--bs-border-color, #eee) !important;
    }

    #comparisonTable th,
    #comparisonTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid var(--bs-border-color, #eee) !important;
        padding: 4px 4px !important;
        white-space: nowrap;
    }

    #comparisonTable thead th {
        /* Theme compatible opaque background (White + 10% Primary Tint) */
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
        font-weight: 800;
        font-size: 11px;
        position: sticky;
        top: 0;
        z-index: 20;
        border-color: rgba(var(--bs-primary-rgb), 0.15) !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    [data-bs-theme="dark"] #comparisonTable thead th {
        background: #2a3042 !important;
        color: #eff2f7 !important;
        border-color: #32394e !important;
    }

    /* thead offset adjustments for Consolidated 2-row structure - pixel perfect fix */
    #comparisonTable thead tr:nth-child(1) th {
        top: -1px;
        z-index: 30;
    }

    #comparisonTable thead tr:nth-child(2) th {
        top: 25px;
        /* Tightened to remove gap */
        z-index: 29;
    }

    .column-search {
        height: 34px !important;
        font-size: 11px !important;
        padding: 4px 10px !important;
        background-color: #ffffff !important;
        border: 1px solid #e9ecef !important;
        border-radius: 6px !important;
        /* As seen in the reference */
        width: 100% !important;
        transition: all 0.2s ease;
        color: #495057;
        margin: 0 !important;
    }

    /* Premium Report Layout */
    .do-genel-row {
        background-color: #f1f5f9 !important;
        border-top: 3px solid var(--bs-primary) !important;
    }
    
    .do-bolge-row td {
        border-top: 2px solid #edf2f7 !important;
    }
    
    .do-oran-cell {
        background-color: rgba(var(--bs-primary-rgb), 0.04) !important;
        border-left: 1px dashed rgba(var(--bs-primary-rgb), 0.2) !important;
    }

    .do-sub-info-text {
        font-size: 8px;
        color: #94a3b8;
        text-transform: uppercase;
        display: block;
        margin-top: -2px;
        letter-spacing: 0.5px;
    }
    
    .do-period-end {
        border-right: 3px solid rgba(var(--bs-primary-rgb), 0.4) !important;
    }

    /* Sub-row Indentation & Shading */
    .do-sub-row td {
        background-color: rgba(var(--bs-primary-rgb), 0.005) !important;
        border-top: none !important;
        padding-top: 0 !important;
        padding-bottom: 8px !important;
        vertical-align: top !important;
    }
    
    .do-sub-row .do-badge-sub {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
    }

    /* Enhanced Data Points */
    .do-badge-toplam {
        color: #1e293b !important;
        font-weight: 800 !important;
        font-size: 14px !important;
    }
    .do-badge-okunan {
        color: #059669 !important;
        font-weight: 700 !important;
        font-size: 13.5px !important;
    }
    .do-badge-okunmayan {
        color: #dc2626 !important;
        font-weight: 700 !important;
        font-size: 13.5px !important;
    }

    /* Period Headers */
    #defterOzetTable thead .period-header {
        background: #f8fafc !important;
        font-weight: 800 !important;
        color: #334155 !important;
        font-size: 12px !important;
        letter-spacing: 1px;
    }

    #defterOzetTable .column-search {
        height: 30px !important;
        font-size: 11px !important;
        border-radius: 4px !important;
    }

    .column-search::placeholder {
        color: #94a3b8;
        opacity: 0.7;
    }

    .column-search:focus {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb), 0.15) !important;
        outline: none;
        background-color: #fff !important;
    }

    .search-row th {
        background-color: #f8f9fa !important;
        padding: 0 !important;
        /* Key fix to fill cell */
        border-top: none !important;
    }

    [data-bs-theme="dark"] .column-search {
        background-color: #2e3548 !important;
        border-color: #32394e !important;
        color: #eff2f7 !important;
    }

    [data-bs-theme="dark"] .search-row th {
        background-color: #2a3042 !important;
    }

    /* ======= COLUMN FILTER POPUP ======= */
    .sub-header {
        position: relative;
    }

    .col-filter-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border: none;
        background: var(--bs-primary, #556ee6);
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
        color: #fff !important;
        margin-right: 6px;
        padding: 0;
        vertical-align: middle;
        transition: all 0.2s ease;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .col-filter-btn:hover {
        background: #32394e;
        transform: scale(1.1);
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    .col-filter-btn.col-filter-active {
        background: #34c38f;
        /* Green for active */
        box-shadow: 0 0 0 2px rgba(52, 195, 143, 0.3);
    }

    .col-filter-popup {
        position: fixed;
        z-index: 9999;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08);
        padding: 12px;
        min-width: 200px;
        display: none;
    }

    .col-filter-popup.show {
        display: block;
        animation: filterPopupIn 0.15s ease-out;
    }

    @keyframes filterPopupIn {
        from {
            opacity: 0;
            transform: translateY(-4px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .col-filter-popup .filter-popup-title {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .col-filter-popup .filter-popup-title i {
        font-size: 14px;
        color: var(--bs-primary);
    }

    .col-filter-popup select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 6px;
        background: #f8fafc;
        color: #334155;
        cursor: pointer;
        transition: border-color 0.2s;
    }

    .col-filter-popup select:focus {
        border-color: var(--bs-primary);
        outline: none;
    }

    .col-filter-popup input[type="number"] {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 8px;
        transition: border-color 0.2s;
        color: #334155;
    }

    .col-filter-popup input[type="number"]:focus {
        border-color: var(--bs-primary);
        outline: none;
        box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.1);
    }

    .col-filter-popup .filter-popup-actions {
        display: flex;
        gap: 6px;
    }

    .col-filter-popup .btn-filter-apply {
        flex: 1;
        padding: 5px 10px;
        border: none;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        background: var(--bs-primary, #556ee6);
        color: #fff;
        transition: all 0.2s;
    }

    .col-filter-popup .btn-filter-apply:hover {
        filter: brightness(1.1);
    }

    .col-filter-popup .btn-filter-clear {
        padding: 5px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        cursor: pointer;
        background: #f8fafc;
        color: #64748b;
        transition: all 0.2s;
    }

    .col-filter-popup .btn-filter-clear:hover {
        background: #fee2e2;
        color: #ef4444;
        border-color: #fecaca;
    }

    [data-bs-theme="dark"] .col-filter-popup {
        background: #2a3042;
        border-color: #32394e;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
    }

    [data-bs-theme="dark"] .col-filter-popup select,
    [data-bs-theme="dark"] .col-filter-popup input[type="number"] {
        background: #32394e;
        border-color: #3b4565;
        color: #eff2f7;
    }

    [data-bs-theme="dark"] .col-filter-popup .btn-filter-clear {
        background: #32394e;
        border-color: #3b4565;
        color: #a6b0cf;
    }

    /* Active filter indicator badge on sub-header */
    .filter-active-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #34c38f;
        border-radius: 50%;
        margin-left: 2px;
        vertical-align: middle;
        animation: filterDotPulse 1.5s infinite;
    }

    @keyframes filterDotPulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.4;
        }
    }

    /* Fixed columns */
    #comparisonTable .fix-col-1 {
        position: sticky;
        left: 0;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff);
        min-width: 100px;
        max-width: 100px;
    }

    #comparisonTable .fix-col-2 {
        position: sticky;
        left: 100px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff);
        min-width: 140px;
        max-width: 140px;
    }

    #comparisonTable .fix-col-3 {
        position: sticky;
        left: 240px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff);
        min-width: 80px;
        max-width: 80px;
    }

    #comparisonTable .fix-col-4 {
        position: sticky;
        left: 320px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff);
        min-width: 130px;
        max-width: 130px;
    }

    #comparisonTable .fix-col-5 {
        position: sticky;
        left: 450px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff);
        min-width: 80px;
        max-width: 80px;
    }

    #comparisonTable thead .fix-col-1,
    #comparisonTable thead .fix-col-2,
    #comparisonTable thead .fix-col-3,
    #comparisonTable thead .fix-col-4,
    #comparisonTable thead .fix-col-5 {
        z-index: 40 !important;
        /* Higher than regular thead th */
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
    }

    /* Fixed columns in Row 2 (Inputs/Subheaders) also need top offset */
    #comparisonTable thead tr:nth-child(2) .fix-col-1,
    #comparisonTable thead tr:nth-child(2) .fix-col-2,
    #comparisonTable thead tr:nth-child(2) .fix-col-3,
    #comparisonTable thead tr:nth-child(2) .fix-col-4,
    #comparisonTable thead tr:nth-child(2) .fix-col-5 {
        top: 25px !important;
    }

    [data-bs-theme="dark"] #comparisonTable thead .fix-col-1,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-2,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-3,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-4,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-5 {
        background: #2a3042 !important;
        color: #eff2f7 !important;
        border-color: #32394e !important;
    }


    /* Period group header */
    .period-header {
        text-align: center !important;
        font-weight: 700 !important;
        font-size: 12px !important;
        letter-spacing: 0.5px;
    }

    /* Sub-header columns */
    .sub-header {
        position: relative;
    }

    .sub-header-abone {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    .sub-header-okunan {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    .sub-header-gidilen {
        color: rgba(255, 255, 255, 0.85) !important;
    }

    .sub-header-oran {
        color: rgba(255, 255, 255, 1) !important;
    }

    /* Oran cell coloring */
    .oran-high {
        background-color: rgba(52, 195, 143, 0.15) !important;
        color: #34c38f !important;
        font-weight: 700;
    }

    .oran-medium {
        background-color: rgba(241, 180, 76, 0.15) !important;
        color: #f1b44c !important;
        font-weight: 700;
    }

    .oran-low {
        background-color: rgba(244, 106, 106, 0.15) !important;
        color: #f46a6a !important;
        font-weight: 700;
    }

    .gidilen-cell {
        background-color: rgba(244, 106, 106, 0.08) !important;
    }

    #comparisonTable td {
        padding: 4px 6px !important;
        /* "ias" padding (small) */
    }

    /* Row hover */
    #comparisonTable tbody tr:hover td {
        background-color: rgba(85, 110, 230, 0.05) !important;
    }

    #comparisonTable tbody tr:nth-child(even) td {
        background-color: rgba(0, 0, 0, 0.015);
    }

    #comparisonTable tbody tr:nth-child(even):hover td {
        background-color: rgba(85, 110, 230, 0.05) !important;
    }

    /* Period separator */
    .period-end {
        border-right: 2px solid var(--bs-primary, #556ee6) !important;
    }

    /* ======= STICKY FOOTER (TOTALS) ======= */
    #comparisonTable tfoot {
        position: sticky;
        bottom: -1px;
        z-index: 25;
    }

    #comparisonTable tfoot th,
    #comparisonTable tfoot td {
        background-color: #f8f9fa !important;
        font-weight: 800;
        border-top: 2px solid var(--bs-primary, #556ee6) !important;
        color: var(--bs-primary, #2a3042);
        z-index: 24;
    }

    /* Fixed columns in footer also need stickiness */
    #comparisonTable tfoot .fix-col-1,
    #comparisonTable tfoot .fix-col-2,
    #comparisonTable tfoot .fix-col-3,
    #comparisonTable tfoot .fix-col-4,
    #comparisonTable tfoot .fix-col-5 {
        z-index: 35 !important;
        background-color: #f0f2f5 !important;
        position: sticky;
        bottom: -1px;
    }

    [data-bs-theme="dark"] #comparisonTable tfoot th,
    [data-bs-theme="dark"] #comparisonTable tfoot td {
        background-color: #32394e !important;
        color: #eff2f7;
        border-top-color: var(--bs-primary) !important;
    }

    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-1,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-2,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-3,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-4,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-5 {
        background-color: #3b4258 !important;
    }

    /* Rapor Getir Button Style (Maaş Hesaplama Style) */
    #btnRaporGetir {
        background-color: #2a3042 !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 6px !important;
        padding: 8px 16px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    }

    #btnRaporGetir:hover {
        background-color: #32394e !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15) !important;
    }

    #btnRaporGetir i {
        color: #ffffff !important;
    }

    #btnRaporGetir span {
        font-size: 13px !important;
    }

    /* Fullscreen Mode */
    .fullscreen-mode {
        background: var(--bs-card-bg, #f4f5f8) !important;
        padding: 20px !important;
        display: flex !important;
        flex-direction: column !important;
        height: 100vh !important;
        width: 100% !important;
    }

    [data-bs-theme="dark"] .fullscreen-mode {
        background: #191e22 !important;
    }

    .fullscreen-mode>.col-12 {
        flex: 1;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .fullscreen-mode .card {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin-bottom: 0;
    }

    .fullscreen-mode .card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0 !important;
    }

    .fullscreen-mode .table-responsive,
    .fullscreen-mode #reportTableWrapper,
    .fullscreen-mode #okumaGunTableWrapper {
        max-height: none !important;
        flex: 1;
        overflow: auto !important;
    }

    /* ======= DARK MODE ======= */
    [data-bs-theme="dark"] #comparisonTable {
        color: #eff2f7;
    }

    [data-bs-theme="dark"] #comparisonTable th,
    [data-bs-theme="dark"] #comparisonTable td {
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] .fix-col-1,
    [data-bs-theme="dark"] .fix-col-2,
    [data-bs-theme="dark"] .fix-col-3,
    [data-bs-theme="dark"] .fix-col-4,
    [data-bs-theme="dark"] .fix-col-5 {
        background-color: var(--bs-card-bg, #282f36) !important;
    }

    [data-bs-theme="dark"] #comparisonTable tbody tr:nth-child(even) td {
        background-color: rgba(255, 255, 255, 0.02);
    }

    /* ======= OKUMA GÜN SAYILARI TABLE ======= */
    #okumaGunTable {
        border-collapse: collapse !important;
        font-size: 12px;
        width: 100%;
        table-layout: auto;
        min-width: 900px;
        border: 1px solid var(--bs-border-color, #eee) !important;
    }

    #okumaGunTable th,
    #okumaGunTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid var(--bs-border-color, #eee) !important;
        padding: 5px 6px !important;
        white-space: nowrap;
    }

    #okumaGunTable thead th {
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
        font-weight: 800;
        font-size: 11px;
        position: sticky;
        top: 0;
        z-index: 20;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #okumaGunTable thead tr:nth-child(1) th {
        top: -1px;
        z-index: 30;
    }

    #okumaGunTable thead tr:nth-child(2) th {
        top: 25px;
        z-index: 29;
    }

    #okumaGunTable .fix-col-1 {
        position: sticky;
        left: 0;
        z-index: 10;
        min-width: 100px;
        max-width: 100px;
    }

    #okumaGunTable .fix-col-2 {
        position: sticky;
        left: 100px;
        z-index: 10;
        min-width: 140px;
        max-width: 140px;
    }

    #okumaGunTable .fix-col-3 {
        position: sticky;
        left: 240px;
        z-index: 10;
        min-width: 80px;
        max-width: 80px;
    }

    #okumaGunTable .fix-col-4 {
        position: sticky;
        left: 320px;
        z-index: 10;
        min-width: 130px;
        max-width: 130px;
    }

    #okumaGunTable .fix-col-5 {
        position: sticky;
        left: 450px;
        z-index: 10;
        min-width: 80px;
        max-width: 80px;
    }

    #okumaGunTable thead .fix-col-1,
    #okumaGunTable thead .fix-col-2,
    #okumaGunTable thead .fix-col-3,
    #okumaGunTable thead .fix-col-4,
    #okumaGunTable thead .fix-col-5 {
        z-index: 40 !important;
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
    }

    /* Region row coloring */
    .ogr-region-header {
        font-weight: 700 !important;
        font-size: 12px !important;
        letter-spacing: 0.5px;
    }

    /* Fark cell: 35+ gün kırmızı */
    .fark-danger {
        background-color: rgba(244, 63, 94, 0.15) !important;
        color: #e11d48 !important;
        font-weight: 700;
    }

    /* Fark cell: normal */
    .fark-normal {
        color: #059669 !important;
        font-weight: 600;
    }

    /* ======= FILTER BADGES (Tab 2) ======= */
    .filter-badge {
        display: inline-flex;
        align-items: center;
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 500;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }

    [data-bs-theme="dark"] .filter-badge {
        background: rgba(52, 195, 143, 0.1);
        color: #34c38f;
        border-color: rgba(52, 195, 143, 0.2);
    }

    .filter-badge .badge-label {
        font-weight: 700;
        margin-right: 4px;
        opacity: 0.8;
    }

    .filter-badge .badge-value {
        font-weight: 800;
    }

    .filter-badge .badge-close {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        margin-left: 8px;
        padding: 0;
        background: rgba(22, 101, 52, 0.1);
        border: none;
        border-radius: 4px;
        color: inherit;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .filter-badge .badge-close:hover {
        background: #ef4444;
        color: #fff;
    }

    /* Period separator */
    .ogr-period-end {
        border-right: 2px solid var(--bs-primary, #556ee6) !important;
    }

    #okumaGunTable tbody tr:hover td {
        filter: brightness(0.97);
    }

    .og-sortable-header {
        cursor: pointer;
        user-select: none;
    }

    .og-sortable-header:hover {
        background-color: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.15) !important;
    }

    .og-column-search {
        font-size: 10px !important;
        padding: 2px 4px !important;
        height: 22px !important;
        border-radius: 4px !important;
    }

    [data-bs-theme="dark"] #okumaGunTable thead th {
        background: #2a3042 !important;
        color: #eff2f7 !important;
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] #okumaGunTable th,
    [data-bs-theme="dark"] #okumaGunTable td {
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] #okumaGunTable .fix-col-ilce,
    [data-bs-theme="dark"] #okumaGunTable .fix-col-mahalle,
    [data-bs-theme="dark"] #okumaGunTable .fix-col-defter,
    [data-bs-theme="dark"] #okumaGunTable .fix-col-abone {
        background-color: var(--bs-card-bg, #282f36) !important;
    }

    [data-bs-theme="dark"] #okumaGunTable thead .fix-col-ilce,
    [data-bs-theme="dark"] #okumaGunTable thead .fix-col-mahalle,
    [data-bs-theme="dark"] #okumaGunTable thead .fix-col-defter,
    [data-bs-theme="dark"] #okumaGunTable thead .fix-col-abone {
        background: #2a3042 !important;
        color: #eff2f7 !important;
    }

    /* ======= DEFTER ÖZET TABLOSU ======= */
    #defterOzetTable {
        border-collapse: collapse !important;
        font-size: 12px;
        width: 100%;
        table-layout: auto;
        border: 1px solid var(--bs-border-color, #eee) !important;
    }

    #defterOzetTable th,
    #defterOzetTable td {
        vertical-align: middle !important;
        text-align: center !important;
        border: 1px solid var(--bs-border-color, #eee) !important;
        padding: 6px 8px !important;
        white-space: nowrap;
    }

    #defterOzetTable thead th {
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
        font-weight: 800;
        font-size: 11px;
        position: sticky;
        top: 0;
        z-index: 20;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #defterOzetTable thead tr:nth-child(1) th {
        top: -1px;
        z-index: 30;
    }

    #defterOzetTable thead tr:nth-child(2) th {
        top: 27px;
        z-index: 29;
    }

    #defterOzetTable .fix-col-bolge {
        position: sticky;
        left: 0;
        z-index: 10;
        min-width: 160px;
        max-width: 200px;
        text-align: left !important;
        background-color: var(--bs-card-bg, #fff);
    }

    #defterOzetTable thead .fix-col-bolge {
        z-index: 40 !important;
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
    }

    #defterOzetTable tfoot {
        position: sticky;
        bottom: -1px;
        z-index: 25;
    }

    #defterOzetTable tfoot th {
        background-color: #f0f2f5 !important;
        font-weight: 800;
        border-top: 2px solid var(--bs-primary) !important;
        color: var(--bs-dark);
    }

    #defterOzetTable tfoot .fix-col-bolge {
        z-index: 35 !important;
        background-color: #f0f2f5 !important;
    }

    #defterOzetTable tbody tr:hover td {
        background-color: rgba(85, 110, 230, 0.04) !important;
    }

    .do-period-end {
        border-right: 2px solid var(--bs-primary, #556ee6) !important;
    }

    .do-badge-toplam {
        background: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
        font-weight: 700;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 12px;
        display: inline-block;
        min-width: 40px;
    }

    .do-badge-okunan {
        background: rgba(52, 195, 143, 0.12);
        color: #059669;
        font-weight: 700;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 12px;
        display: inline-block;
        min-width: 40px;
    }

    .do-badge-okunmayan {
        background: rgba(244, 63, 94, 0.12);
        color: #e11d48;
        font-weight: 700;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 12px;
        display: inline-block;
        min-width: 40px;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .do-badge-okunmayan:hover {
        background: rgba(244, 63, 94, 0.25);
        border-color: #f43f5e;
        transform: scale(1.05);
        box-shadow: 0 3px 8px rgba(244, 63, 94, 0.2);
    }

    .do-badge-okunmayan.zero {
        cursor: default;
        opacity: 0.5;
    }

    .do-badge-okunmayan.zero:hover {
        background: rgba(244, 63, 94, 0.12);
        border-color: transparent;
        transform: none;
        box-shadow: none;
    }

    .do-badge-oran {
        font-weight: 700;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 11px;
        display: inline-block;
        min-width: 50px;
    }

    .do-oran-high {
        background: rgba(52, 195, 143, 0.15);
        color: #059669;
    }

    .do-oran-medium {
        background: rgba(241, 180, 76, 0.15);
        color: #d97706;
    }

    .do-oran-low {
        background: rgba(244, 63, 94, 0.15);
        color: #e11d48;
    }

    .do-bolge-row {
        background: rgba(var(--bs-primary-rgb), 0.03);
    }

    .do-genel-row td {
        background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.06), rgba(var(--bs-primary-rgb), 0.02)) !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        border-top: 2px solid rgba(var(--bs-primary-rgb), 0.2) !important;
    }

    [data-bs-theme="dark"] #defterOzetTable thead th {
        background: #2a3042 !important;
        color: #eff2f7 !important;
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] #defterOzetTable th,
    [data-bs-theme="dark"] #defterOzetTable td {
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] #defterOzetTable .fix-col-bolge {
        background-color: var(--bs-card-bg, #282f36) !important;
    }

    [data-bs-theme="dark"] #defterOzetTable tfoot th {
        background-color: #32394e !important;
        color: #eff2f7;
    }

    /* Modal defter listesi tablosu */
    #okunmayanDefterTable {
        font-size: 12px;
    }

    #okunmayanDefterTable th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #f8fafc;
        color: #64748b;
    }

    #okunmayanDefterTable td {
        padding: 8px 12px;
    }
</style>

<script>
    $(document).ready(function () {
        // Initialize Select2
        $('.select2').select2({
            width: '100%',
            allowClear: true
        });

        // Initialize Flatpickr MonthSelect
        $(".month-picker").flatpickr({
            locale: "tr",
            disableMobile: true,
            plugins: [
                new monthSelectPlugin({
                    shorthand: false, // Long names to avoid 'Ara' confusion
                    dateFormat: "Ym",
                    altFormat: "Ym",
                    theme: "light" // Matches the forced white background
                })
            ],
            onChange: function (selectedDates, dateStr, instance) {
                updateFilterSummary();
            }
        });

        // Feather icons reinit
        if (typeof feather !== 'undefined') feather.replace();

        // ======= FİLTER SUMMARY (Accordion kapalıyken görünür) =======
        function updateFilterSummary() {
            let summary = '';
            const baslangic = $('#baslangicDonem').val();
            const bitis = $('#bitisDonem').val();
            const donemler = $('#filterDonemler').val();
            const ilceTipiText = $('#filterIlceTipi option:selected').text();
            const bolgeText = $('#filterBolge option:selected').text();
            const defterText = $('#filterDefter option:selected').text();

            if (baslangic || bitis) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Aralık:</span><span class="badge-value">${baslangic || '?'} - ${bitis || '?'}</span></div>`;
            }
            if (donemler && donemler.length > 0) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Özel:</span><span class="badge-value">${donemler.join(', ')}</span></div>`;
            }
            if ($('#filterIlceTipi').val()) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">İlçe:</span><span class="badge-value">${ilceTipiText}</span><button type="button" class="btn-clear-filter" data-filter="filterIlceTipi"><i class="bx bx-x"></i></button></div>`;
            }
            if ($('#filterBolge').val()) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Bölge:</span><span class="badge-value">${bolgeText}</span><button type="button" class="btn-clear-filter" data-filter="filterBolge"><i class="bx bx-x"></i></button></div>`;
            }
            if ($('#filterDefter').val()) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Defter:</span><span class="badge-value">${defterText}</span><button type="button" class="btn-clear-filter" data-filter="filterDefter"><i class="bx bx-x"></i></button></div>`;
            }

            $('#filterSummary').html(summary);
        }

        // Initial summary
        updateFilterSummary();

        $('#filterIlceTipi, #filterBolge, #filterDefter, #filterDonemler').on('change', function () {
            updateFilterSummary();
        });

        $('#baslangicDonem, #bitisDonem').on('input', function () {
            updateFilterSummary();
        });

        // Clear filter badge click
        $(document).on('click', '.btn-clear-filter', function (e) {
            e.stopPropagation();
            const filterType = $(this).data('filter');
            $('#' + filterType).val('').trigger('change');
            updateFilterSummary();
        });

        $('.quick-period').on('click', function () {
            const months = $(this).data('months');
            const type = $(this).data('type');
            const now = new Date();

            if (type === 'manuel') {
                // Manuel seçimi göster, aralığı gizle
                $('#rangeContainer').hide();
                $('#manualContainer').fadeIn(300);
                $('#baslangicDonem, #bitisDonem').val('');
            } else {
                // Aralığı göster, manueli gizle
                $('#manualContainer').hide();
                $('#rangeContainer').fadeIn(300);
                $('#filterDonemler').val([]).trigger('change');

                if (type === 'bu-yil') {
                    $('#baslangicDonem').val(now.getFullYear() + '01');
                    $('#bitisDonem').val(formatDonem(now));
                } else if (type === 'gecen-yil') {
                    const lastYear = now.getFullYear() - 1;
                    $('#baslangicDonem').val(lastYear + '01');
                    $('#bitisDonem').val(lastYear + '12');
                } else if (months) {
                    const start = new Date(now);
                    start.setMonth(start.getMonth() - (months - 1));
                    $('#baslangicDonem').val(formatDonem(start));
                    $('#bitisDonem').val(formatDonem(now));
                }
            }

            // Highlight active button
            $('.quick-period').removeClass('active-period');
            $(this).addClass('active-period');
            updateFilterSummary();
        });

        function formatDonem(d) {
            return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0');
        }

        // ======= RAPORU GETİR =======
        $('#btnRaporGetir').on('click', function () {
            loadReport();
        });

        $('#btnTemizle').on('click', function () {
            $('#filterIlceTipi').val('').trigger('change');
            $('#filterBolge').val('').trigger('change');
            $('#filterDefter').val('').trigger('change');
            $('#filterDonemler').val([]).trigger('change');

            const now = new Date();
            const start = new Date(now);
            start.setMonth(start.getMonth() - 2);
            $('#baslangicDonem').val(formatDonem(start));
            $('#bitisDonem').val(formatDonem(now));

            $('.quick-period').removeClass('active-period');

            // Arama filtrelerini de sıfırla
            _searchFilters = { ilce_tipi: '', bolge: '', defter: '' };
            _numericFilters = {};
            if (_tableData && _tableData.length > 0) {
                renderTable(_tableData, _tableDonemler, true);
            }

            updateFilterSummary();
        });

        // ======= RAPOR YÜKLEME =======
        function loadReport() {
            const baslangicDonem = $('#baslangicDonem').val().trim();
            const bitisDonem = $('#bitisDonem').val().trim();
            const donemler = $('#filterDonemler').val();
            const ilceTipi = $('#filterIlceTipi').val();
            const bolge = $('#filterBolge').val();
            const defterVal = $('#filterDefter').val();

            if ((!baslangicDonem || !bitisDonem) && (!donemler || donemler.length === 0)) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem veya aralık seçiniz.', 'warning');
                return;
            }

            // Accordion'u kapat
            var collapseElement = document.getElementById('collapseFilter');
            var collapse = bootstrap.Collapse.getInstance(collapseElement);
            if (collapse) collapse.hide();
            else if (collapseElement.classList.contains('show')) new bootstrap.Collapse(collapseElement, { toggle: false }).hide();

            $('#loadingSection').show();
            $('#reportSection').hide();
            $('#summaryCards').hide();

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: {
                    action: 'defter-bazli-rapor',
                    baslangic_donem: baslangicDonem,
                    bitis_donem: bitisDonem,
                    donemler: donemler,
                    ilce_tipi: ilceTipi,
                    bolge: bolge,
                    defter: defterVal
                },
                dataType: 'json',
                success: function (response) {
                    $('#loadingSection').hide();

                    if (response.status === 'success') {
                        renderSummaryCards(response.summary);
                        renderTable(response.data, response.donemler);
                        $('#summaryCards').fadeIn(300);
                        $('#reportSection').fadeIn(300);
                        updateFilterSummary();
                    } else {
                        Swal.fire('Hata', response.message || 'Rapor oluşturulurken bir hata oluştu.', 'error');
                    }
                },
                error: function () {
                    $('#loadingSection').hide();
                    Swal.fire('Hata', 'Sunucuyla iletişim kurulamadı.', 'error');
                }
            });
        }

        // ======= ÖZET KARTLARI =======
        function renderSummaryCards(summary) {
            $('#totalBolge').text(summary.toplam_bolge.toLocaleString('tr-TR'));
            $('#totalKayit').text(summary.toplam_kayit.toLocaleString('tr-TR'));
            $('#totalAbone').text(summary.toplam_abone.toLocaleString('tr-TR'));
            $('#totalAboneDonem').text(summary.son_donem);
            $('#totalDonem').text(summary.donem_sayisi);
        }

        // ======= SORT STATE =======
        let _tableData = [];
        let _tableDonemler = [];
        let _sortColumn = null; // 'ilce_tipi', 'bolge', 'defter', or '{donem}_{field}'
        let _sortDirection = 'asc';
        let _visibleColumns = {
            abone: true,
            okunan: true,
            gidilen: true,
            oran: true
        };

        // ======= NUMERIC FILTER STATE =======
        // Key: '{donem}_{field}', Value: { operator: '>' | '<' | '>=' | '<=' | '=', value: number }
        let _numericFilters = {};

        // ======= TABLO OLUŞTURMA =======
        let _searchFilters = { ilce_tipi: '', bolge: '', defter: '', mahalle: '', abone_sayisi: '' };
        let _searchTimeout;

        function renderTable(data, donemler, keep) {
            _tableData = data;
            _tableDonemler = donemler;

            if (keep !== true) {
                _sortColumn = null;
                _sortDirection = 'asc';
                _searchFilters = { ilce_tipi: '', bolge: '', defter: '', mahalle: '', abone_sayisi: '' };
            }

            if (!data || data.length === 0) {
                $('#reportTableWrapper').html('<div class="text-center p-5 text-muted"><i class="bx bx-search-alt fs-1 d-block mb-2"></i>Seçilen kriterlere uygun veri bulunamadı.</div>');
                return;
            }

            // Arama filtrelerini uygula
            let filteredData = data.filter(function (item) {
                const ilceMatch = !_searchFilters.ilce_tipi || (item.ilce_tipi || '').toLowerCase().includes(_searchFilters.ilce_tipi.toLowerCase());
                const bolgeMatch = !_searchFilters.bolge || (item.bolge || '').toLowerCase().includes(_searchFilters.bolge.toLowerCase());
                const defterMatch = !_searchFilters.defter || (item.defter || '').toString().toLowerCase().includes(_searchFilters.defter.toLowerCase());
                const mahalleMatch = !_searchFilters.mahalle || (item.mahalle || '').toLowerCase().includes(_searchFilters.mahalle.toLowerCase());
                const aboneMatch = !_searchFilters.abone_sayisi || (item.abone_sayisi || '').toString().includes(_searchFilters.abone_sayisi);
                if (!(ilceMatch && bolgeMatch && defterMatch && mahalleMatch && aboneMatch)) return false;

                // Sayısal kolon filtrelerini uygula
                for (const filterKey in _numericFilters) {
                    const f = _numericFilters[filterKey];
                    if (!f || !f.operator || f.value === '' || f.value === null || f.value === undefined) continue;

                    const parts = filterKey.split('_');
                    const field = parts.pop(); // abone, okunan, gidilen, oran
                    const donemKey = parts.join('_');
                    const donemData = item.donemler[donemKey] || { abone: 0, okunan: 0, gidilen: 0 };

                    let cellVal;
                    if (field === 'oran') {
                        cellVal = donemData.abone > 0 ? (donemData.okunan / donemData.abone) * 100 : 0;
                    } else {
                        cellVal = parseFloat(donemData[field]) || 0;
                    }

                    const filterVal = parseFloat(f.value);
                    if (isNaN(filterVal)) continue;

                    switch (f.operator) {
                        case '>': if (!(cellVal > filterVal)) return false; break;
                        case '<': if (!(cellVal < filterVal)) return false; break;
                        case '>=': if (!(cellVal >= filterVal)) return false; break;
                        case '<=': if (!(cellVal <= filterVal)) return false; break;
                        case '=': if (!(Math.abs(cellVal - filterVal) < 0.01)) return false; break;
                    }
                }
                return true;
            });

            // Sıralamayı uygula
            let sortedData = [...filteredData];
            if (_sortColumn) {
                sortedData.sort(function (a, b) {
                    let valA, valB;

                    if (_sortColumn === 'ilce_tipi') {
                        valA = (a.ilce_tipi || '').toLowerCase();
                        valB = (b.ilce_tipi || '').toLowerCase();
                    } else if (_sortColumn === 'bolge') {
                        valA = (a.bolge || '').toLowerCase();
                        valB = (b.bolge || '').toLowerCase();
                    } else if (_sortColumn === 'defter') {
                        valA = (a.defter || '').toString();
                        valB = (b.defter || '').toString();
                        // Try numeric sort for defter
                        const numA = parseInt(valA), numB = parseInt(valB);
                        if (!isNaN(numA) && !isNaN(numB)) {
                            valA = numA; valB = numB;
                        }
                    } else if (_sortColumn === 'mahalle') {
                        valA = (a.mahalle || '').toLowerCase();
                        valB = (b.mahalle || '').toLowerCase();
                    } else if (_sortColumn === 'abone_sayisi') {
                        valA = parseInt(a.abone_sayisi) || 0;
                        valB = parseInt(b.abone_sayisi) || 0;
                    } else {
                        // Period column: format is '{donem}_{field}'
                        const parts = _sortColumn.split('_');
                        const field = parts.pop(); // abone, okunan, gidilen, oran
                        const donem = parts.join('_');
                        const dA = a.donemler[donem] || { abone: 0, okunan: 0, gidilen: 0 };
                        const dB = b.donemler[donem] || { abone: 0, okunan: 0, gidilen: 0 };

                        if (field === 'oran') {
                            valA = dA.abone > 0 ? (dA.okunan / dA.abone) * 100 : 0;
                            valB = dB.abone > 0 ? (dB.okunan / dB.abone) * 100 : 0;
                        } else {
                            valA = parseInt(dA[field]) || 0;
                            valB = parseInt(dB[field]) || 0;
                        }
                    }

                    if (valA < valB) return _sortDirection === 'asc' ? -1 : 1;
                    if (valA > valB) return _sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
            }

            // Sort indicator helper
            function sortIcon(colKey) {
                if (_sortColumn === colKey) {
                    const icon = _sortDirection === 'asc' ? '▲' : '▼';
                    return `<span class="sort-icon active">${icon}</span>`;
                }
                return '<span class="sort-icon">⇅</span>';
            }

            // Bölgelere göre grupla
            const regionMap = {};
            const regionOrder = [];
            sortedData.forEach(function (row) {
                const region = row.bolge || 'TANIMSIZ';
                if (!regionMap[region]) {
                    regionMap[region] = [];
                    regionOrder.push(region);
                }
                regionMap[region].push(row);
            });

            let html = '<table class="table table-bordered table-sm mb-0" id="comparisonTable">';

            // ======= THEAD =======
            html += '<thead>';

            // Row 1: Fixed labels + Periods
            html += '<tr class="main-headers-row">';
            html += `<th class="fix-col-1 sortable-header" data-sort-col="ilce_tipi">İLÇE TİPİ${sortIcon('ilce_tipi')}</th>`;
            html += `<th class="fix-col-2 sortable-header" data-sort-col="bolge">BÖLGE${sortIcon('bolge')}</th>`;
            html += `<th class="fix-col-3 sortable-header" data-sort-col="defter">DEFTER${sortIcon('defter')}</th>`;
            html += `<th class="fix-col-4 sortable-header" data-sort-col="mahalle">MAHALLE${sortIcon('mahalle')}</th>`;
            html += `<th class="fix-col-5 sortable-header" data-sort-col="abone_sayisi">ABONE SAYISI${sortIcon('abone_sayisi')}</th>`;

            const visibleCount = Object.values(_visibleColumns).filter(v => v).length;

            donemler.forEach(function (donem, idx) {
                if (visibleCount === 0) return;
                const isLast = idx === donemler.length - 1;
                const formatted = donem.substring(0, 4) + '/' + donem.substring(4);
                html += `<th colspan="${visibleCount}" class="period-header ${isLast ? 'period-end' : ''}">${formatted}</th>`;
            });
            html += '</tr>';

            // Row 2: Search Inputs + Sub-headers
            html += '<tr class="sub-headers-row search-row">';
            html += `<th class="fix-col-1"><input type="text" class="form-control column-search" id="search_ilce_tipi" data-col="ilce_tipi" value="${_searchFilters.ilce_tipi || ''}" placeholder="İLÇE TİPİ"></th>`;
            html += `<th class="fix-col-2"><input type="text" class="form-control column-search" id="search_bolge" data-col="bolge" value="${_searchFilters.bolge || ''}" placeholder="BÖLGE"></th>`;
            html += `<th class="fix-col-3"><input type="text" class="form-control column-search" id="search_defter" data-col="defter" value="${_searchFilters.defter || ''}" placeholder="DEFTER"></th>`;
            html += `<th class="fix-col-4"><input type="text" class="form-control column-search" id="search_mahalle" data-col="mahalle" value="${_searchFilters.mahalle || ''}" placeholder="MAHALLE"></th>`;
            html += `<th class="fix-col-5"><input type="text" class="form-control column-search" id="search_abone_sayisi" data-col="abone_sayisi" value="${_searchFilters.abone_sayisi || ''}" placeholder="ABONE"></th>`;

            // Filter button helper
            function filterBtn(colKey) {
                const isActive = _numericFilters[colKey] && _numericFilters[colKey].operator && _numericFilters[colKey].value !== '' && _numericFilters[colKey].value !== null;
                const activeClass = isActive ? 'col-filter-active' : '';
                const dot = isActive ? '<span class="filter-active-dot"></span>' : '';
                return `<button type="button" class="col-filter-btn ${activeClass}" data-filter-col="${colKey}" title="Filtrele"><i class="bx bx-filter-alt"></i></button>${dot}`;
            }

            donemler.forEach(function (donem, idx) {
                const isLast = idx === donemler.length - 1;
                if (_visibleColumns.abone)
                    html += `<th class="sub-header sub-header-abone sortable-header" data-sort-col="${donem}_abone">${filterBtn(donem + '_abone')} ABONE${sortIcon(donem + '_abone')}</th>`;
                if (_visibleColumns.okunan)
                    html += `<th class="sub-header sub-header-okunan sortable-header" data-sort-col="${donem}_okunan">${filterBtn(donem + '_okunan')} OKUNAN${sortIcon(donem + '_okunan')}</th>`;
                if (_visibleColumns.gidilen)
                    html += `<th class="sub-header sub-header-gidilen sortable-header" data-sort-col="${donem}_gidilen">${filterBtn(donem + '_gidilen')} GİDİLEN${sortIcon(donem + '_gidilen')}</th>`;
                if (_visibleColumns.oran)
                    html += `<th class="sub-header sub-header-oran sortable-header ${isLast ? 'period-end' : ''}" data-sort-col="${donem}_oran">${filterBtn(donem + '_oran')} ORAN %${sortIcon(donem + '_oran')}</th>`;
            });
            html += '</tr>';

            html += '</thead>';

            // ======= TBODY =======
            html += '<tbody>';

            // Totals calculation object
            let colTotals = {};
            donemler.forEach(d => {
                colTotals[d] = { abone: 0, okunan: 0, gidilen: 0 };
            });

            // Calculate active columns for colspan
            let visibleCountPerPeriod = 0;
            if (_visibleColumns.abone) visibleCountPerPeriod++;
            if (_visibleColumns.okunan) visibleCountPerPeriod++;
            if (_visibleColumns.gidilen) visibleCountPerPeriod++;
            if (_visibleColumns.oran) visibleCountPerPeriod++;
            const totalHeaderCols = 5 + (donemler.length * visibleCountPerPeriod);

            let rowNum = 0;
            regionOrder.forEach(function (region, regionIdx) {
                const regionColor = _regionColors[regionIdx % _regionColors.length];
                const rows = regionMap[region];

                // Calculate Region Totals
                let regionTotals = {
                    master_abone: 0,
                    donemler: {}
                };
                donemler.forEach(d => {
                    regionTotals.donemler[d] = { abone: 0, okunan: 0, gidilen: 0 };
                });

                rows.forEach(function (row) {
                    regionTotals.master_abone += parseInt(row.abone_sayisi) || 0;
                    donemler.forEach(d => {
                        const dData = row.donemler[d] || { abone: 0, okunan: 0, gidilen: 0 };
                        regionTotals.donemler[d].abone += parseInt(dData.abone) || 0;
                        regionTotals.donemler[d].okunan += parseInt(dData.okunan) || 0;
                        regionTotals.donemler[d].gidilen += parseInt(dData.gidilen) || 0;
                    });
                });

                // Calculate Region Stats (Defter counts)
                let regionDefterStats = {};
                donemler.forEach(d => {
                    let rCount = 0;
                    rows.forEach(item => {
                        const dData = item.donemler[d];
                        if (dData && parseInt(dData.okunan) > 0) rCount++;
                    });
                    regionDefterStats[d] = { read: rCount, unread: rows.length - rCount };
                });

                // Defter Durum Satırı (Bölge başlığı üstüne)
                html += `<tr class="ogr-region-stats-row"><td colspan="5" class="text-end fw-bold py-1" style="background: rgba(0,0,0,0.02); font-size: 10px; color: #64748b;">OKUMA DURUMU (DEFTER):</td>`;
                donemler.forEach(donem => {
                    const stats = regionDefterStats[donem];
                    const rTotals = regionTotals.donemler[donem];
                    const content = `<div class="d-flex justify-content-center gap-1">
                        <span class="badge bg-soft-success text-success border border-success-subtle px-2" style="font-size:9.5px;" title="Okunan Defter Sayısı">Okunan: <b>${stats.read}</b></span>
                        <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2" style="font-size:9.5px;" title="Okunmayan Defter Sayısı">Okunmayan: <b>${stats.unread}</b></span>
                        <span class="badge bg-light text-dark border px-2 ms-1" style="font-size:9.5px;" title="Toplam Okunan Abone (92173 gibi)">Abone: <b>${rTotals.okunan.toLocaleString('tr-TR')}</b></span>
                    </div>`;
                    html += `<td colspan="${visibleCountPerPeriod}" class="text-center py-1" style="background: rgba(0,0,0,0.02);">${content}</td>`;
                });
                html += '</tr>';

                // Bölge başlık satırı
                html += `<tr class="ogr-region-row">`;
                html += `<td colspan="4" class="ogr-region-header text-start" style="background: ${regionColor.header}; color: ${regionColor.text};">`;
                html += `<i class="bx bx-map me-1"></i>${region} <span class="badge bg-white text-dark ms-2" style="font-size:10px;">${rows.length} defter</span>`;
                html += '</td>';
                
                // Master Abone Sayısı Total for Region
                html += `<td class="ogr-region-header text-end" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;">${regionTotals.master_abone.toLocaleString('tr-TR')}</td>`;

                // Period Totals for Region
                donemler.forEach(function (donem, idx) {
                    const isLast = idx === donemler.length - 1;
                    const rTotals = regionTotals.donemler[donem];
                    const rOran = rTotals.abone > 0 ? ((rTotals.okunan / rTotals.abone) * 100).toFixed(1) : 0;
                    
                    if (_visibleColumns.abone)
                        html += `<td class="ogr-region-header text-end" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;">${rTotals.abone.toLocaleString('tr-TR')}</td>`;
                    if (_visibleColumns.okunan)
                        html += `<td class="ogr-region-header text-end" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;">${rTotals.okunan.toLocaleString('tr-TR')}</td>`;
                    if (_visibleColumns.gidilen)
                        html += `<td class="ogr-region-header text-end" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;">${rTotals.gidilen.toLocaleString('tr-TR')}</td>`;
                    if (_visibleColumns.oran)
                        html += `<td class="ogr-region-header text-end ${isLast ? 'period-end' : ''}" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;"></td>`;
                });
                html += '</tr>';

                rows.forEach(function (row) {
                    rowNum++;
                    html += `<tr style="background-color: ${regionColor.bg};">`;
                    html += `<td class="fix-col-1 text-start fw-medium" style="background-color: ${regionColor.bg};">${row.ilce_tipi}</td>`;
                    html += `<td class="fix-col-2 text-start fw-medium" style="background-color: ${regionColor.bg};">${row.bolge}</td>`;
                    html += `<td class="fix-col-3 text-start fw-medium" style="background-color: ${regionColor.bg};">${row.defter}</td>`;
                    html += `<td class="fix-col-4 text-start" style="background-color: ${regionColor.bg};">${row.mahalle || ''}</td>`;
                    html += `<td class="fix-col-5" style="background-color: ${regionColor.bg};">${row.abone_sayisi ? row.abone_sayisi.toLocaleString('tr-TR') : ''}</td>`;

                    donemler.forEach(function (donem, idx) {
                        const isLast = idx === donemler.length - 1;
                        const donemData = row.donemler[donem] || { abone: 0, okunan: 0, gidilen: 0 };

                        // Add to totals
                        colTotals[donem].abone += parseInt(donemData.abone) || 0;
                        colTotals[donem].okunan += parseInt(donemData.okunan) || 0;
                        colTotals[donem].gidilen += parseInt(donemData.gidilen) || 0;

                        const oran = donemData.abone > 0
                            ? ((donemData.okunan / donemData.abone) * 100).toFixed(1)
                            : 0;

                        let oranClass = 'oran-low';
                        if (oran >= 70) oranClass = 'oran-high';
                        else if (oran >= 50) oranClass = 'oran-medium';

                        if (_visibleColumns.abone)
                            html += `<td>${donemData.abone > 0 ? donemData.abone.toLocaleString('tr-TR') : ''}</td>`;
                        if (_visibleColumns.okunan)
                            html += `<td>${donemData.okunan > 0 ? donemData.okunan.toLocaleString('tr-TR') : ''}</td>`;
                        if (_visibleColumns.gidilen)
                            html += `<td class="gidilen-cell">${donemData.gidilen > 0 ? donemData.gidilen.toLocaleString('tr-TR') : ''}</td>`;
                        if (_visibleColumns.oran)
                            html += `<td class="${oranClass} ${isLast ? 'period-end' : ''}">${donemData.abone > 0 ? oran + '%' : ''}</td>`;
                    });

                    html += '</tr>';
                });
            });
            html += '</tbody>';

            // ======= TFOOT =======
            html += '<tfoot>';
            html += '<tr>';
            html += '<th class="fix-col-1 text-center" colspan="5">GENEL TOPLAM</th>';

            donemler.forEach(function (donem, idx) {
                const totals = colTotals[donem];
                const isLast = idx === donemler.length - 1;

                if (_visibleColumns.abone)
                    html += `<th>${totals.abone.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.okunan)
                    html += `<th>${totals.okunan.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.gidilen)
                    html += `<th class="gidilen-cell">${totals.gidilen.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.oran)
                    html += `<th class="${isLast ? 'period-end' : ''}"></th>`; // Oranları toplama
            });
            html += '</tr>';
            html += '</tfoot>';

            html += '</table>';

            $('#reportTableWrapper').html(html);
            $('#reportActions').fadeIn(300);

            // Bind sort click handlers
            $('#comparisonTable').on('click', '.sortable-header', function (e) {
                // Filtre butonuna veya arama kutusuna tıklandıysa sıralamayı tetikleme
                if ($(e.target).closest('.col-filter-btn, .column-search').length) return;
                
                const col = $(this).data('sort-col');
                if (_sortColumn === col) {
                    _sortDirection = _sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    _sortColumn = col;
                    _sortDirection = 'asc';
                }
                renderTable(_tableData, _tableDonemler, true);
            });
        }

        // ======= FILTER POPUP OPENER =======
        function openFilterPopup(btn) {
            const colKey = $(btn).data('filter-col') || $(btn).data('do-filter-col');
            const popup = $('#colFilterPopup');

            // Aynı butona tekrar tıklanırsa kapat
            if (_activeFilterCol === colKey && popup.hasClass('show')) {
                popup.removeClass('show');
                _activeFilterCol = null;
                return;
            }

            _activeFilterCol = colKey;

            // Sütun adını parse et
            const parts = colKey.split('_');
            const field = parts.pop();
            const donem = parts.join('_');
            const fieldNames = { abone: 'Abone', okunan: 'Okunan', gidilen: 'Gidilen', oran: 'Oran %', fark: 'Fark', tarih: 'Okuma Tarihi', toplam: 'Toplam' };
            const formatted = donem.substring(0, 4) + '/' + donem.substring(4);
            $('#colFilterPopupTitle').text(formatted + ' – ' + (fieldNames[field] || field));

            // Mevcut filtreyi doldur
            const existing = _numericFilters[colKey] || _doNumericFilters[colKey];
            if (existing) {
                $('#colFilterOperator').val(existing.operator || '');
                $('#colFilterValue').val(existing.value !== null && existing.value !== undefined ? existing.value : '');
            } else {
                $('#colFilterOperator').val('');
                $('#colFilterValue').val('');
            }

            // Pozisyon hesapla
            const btnRect = btn.getBoundingClientRect();
            let top = btnRect.bottom + 6;
            let left = btnRect.left - 80;

            // Ekran sınırları
            if (left + 220 > window.innerWidth) left = window.innerWidth - 230;
            if (left < 10) left = 10;
            if (top + 200 > window.innerHeight) top = btnRect.top - 200;

            popup.css({ top: top + 'px', left: left + 'px' }).addClass('show');
            setTimeout(() => $('#colFilterOperator').focus(), 50);
        }

        // ======= GLOBAL FILTER BUTTON HANDLER =======
        $(document).on('click', '.col-filter-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const tableId = $(this).closest('table').attr('id');
            if (tableId === 'defterOzetTable') {
                _activeFilterTab = 'defter-ozet';
            } else {
                _activeFilterTab = null;
            }
            
            openFilterPopup(this);
        });

        // ======= ARAMA EVENT HANDLER (TAB 1) =======
        $(document).on('input', '#comparisonTable .column-search', function () {
            const col = $(this).data('col');
            const val = $(this).val();
            const id = $(this).attr('id');
            const pos = this.selectionStart;

            _searchFilters[col] = val;

            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(function () {
                renderTable(_tableData, _tableDonemler, true);

                // Focusu geri al - Robust handling
                if (id) {
                    setTimeout(() => {
                        const input = document.getElementById(id);
                        if (input) {
                            input.focus();
                            input.setSelectionRange(pos, pos);
                        }
                    }, 0);
                }
            }, 400);
        });

        $(document).on('click', '.column-search', function (e) {
            e.stopPropagation(); // Sıralama işlemini tetiklemesin
        });

        // ======= KOLON FİLTRE POPUP =======
        // Popup HTML – sayfaya bir kez ekle
        $('body').append(`
            <div class="col-filter-popup" id="colFilterPopup">
                <div class="filter-popup-title"><i class="bx bx-filter-alt"></i> <span id="colFilterPopupTitle">Filtre</span></div>
                <select id="colFilterOperator">
                    <option value="">Operatör Seçin</option>
                    <option value=">">Büyüktür ( > )</option>
                    <option value="<">Küçüktür ( < )</option>
                    <option value=">=">Büyük Eşit ( ≥ )</option>
                    <option value="<=">Küçük Eşit ( ≤ )</option>
                    <option value="=">Eşit ( = )</option>
                </select>
                <input type="number" id="colFilterValue" placeholder="Değer girin..." step="any">
                <div class="filter-popup-actions">
                    <button type="button" class="btn-filter-clear" id="colFilterClear"><i class="bx bx-trash-alt me-1"></i>Temizle</button>
                    <button type="button" class="btn-filter-apply" id="colFilterApply"><i class="bx bx-check me-1"></i>Uygula</button>
                </div>
            </div>
        `);

        let _activeFilterCol = null;
        let _activeFilterTab = null;

        // Filtre Uygula (Popup içindeki buton)
        $(document).on('click', '#colFilterApply', function () {
            const operator = $('#colFilterOperator').val();
            const value = $('#colFilterValue').val();

            if (_activeFilterTab === 'defter-ozet') {
                if (value === '') {
                    delete _doNumericFilters[_activeFilterCol];
                } else {
                    _doNumericFilters[_activeFilterCol] = { operator, value };
                }
                renderDefterOzetTable(_defterOzetData);
            } else if ($('#tab-okuma-gun').hasClass('active')) {
                if (value === '') {
                    delete _numericFilters[_activeFilterCol];
                } else {
                    _numericFilters[_activeFilterCol] = { operator, value };
                }
                renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
            } else {
                if (value === '') {
                    delete _numericFilters[_activeFilterCol];
                } else {
                    _numericFilters[_activeFilterCol] = { operator, value };
                }
                renderTable(_tableData, _tableDonemler, true);
            }

            $('#colFilterPopup').removeClass('show');
            _activeFilterCol = null;
            _activeFilterTab = null;
        });

        // Temizle
        $(document).on('click', '#colFilterClear', function () {
            if (_activeFilterTab === 'defter-ozet') {
                if (_activeFilterCol) delete _doNumericFilters[_activeFilterCol];
                renderDefterOzetTable(_defterOzetData);
            } else if (_activeFilterCol) {
                delete _numericFilters[_activeFilterCol];
                if ($('#tab-okuma-gun').hasClass('active')) {
                    renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
                } else {
                    renderTable(_tableData, _tableDonemler, true);
                }
            }
            $('#colFilterOperator').val('');
            $('#colFilterValue').val('');
            $('#colFilterPopup').removeClass('show');
            _activeFilterCol = null;
            _activeFilterTab = null;
        });

        // Popup dışına tıklanınca kapat
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#colFilterPopup, .col-filter-btn').length) {
                $('#colFilterPopup').removeClass('show');
                _activeFilterCol = null;
            }
        });

        // Enter tuşu ile filtreyi uygula (popup içinde)
        $(document).on('keypress', '#colFilterValue, #colFilterOperator', function (e) {
            if (e.which === 13) {
                $('#colFilterApply').trigger('click');
            }
        });

        // ======= FONKSİYONLAR: EXCEL VE TAM EKRAN =======
        function exportTableToExcel(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) {
                Swal.fire('Uyarı', 'Tablo bulunamadı.', 'warning');
                return;
            }

            const htmlContent = table.outerHTML;
            const excelHtml = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>td,th{mso-number-format:'\\@';}</style></head><body>${htmlContent}</body></html>`;
            const blob = new Blob(['\ufeff', excelHtml], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = filename || 'rapor.xls';
            link.href = url;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 100);
        }

        function toggleFullscreen(elemId, btn) {
            const elem = document.getElementById(elemId);
            if (!elem) return;

            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => console.log('Fullscreen error:', err));
                if (btn) $(btn).html('<i class="mdi mdi-fullscreen-exit me-1"></i>Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                if (btn) $(btn).html('<i class="mdi mdi-fullscreen me-1"></i>Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        }

        // ======= EXCEL İNDİR =======
        $('#btnExcelIndir').on('click', function () {
            const isTab2 = $('#tab-okuma-gun').hasClass('active');
            const tableId = isTab2 ? 'okumaGunTable' : 'comparisonTable';
            const filename = $(this).data('filename') || (isTab2 ? 'okuma_gun_sayilari.xls' : 'abone_donem_karsilastirma.xls');
            exportTableToExcel(tableId, filename);
        });

        $(document).on('click', '.btn-tab-excel', function () {
            const tableId = $(this).data('table');
            const filename = $(this).data('filename');
            exportTableToExcel(tableId, filename);
        });

        // Enter tuşu ile rapor getir
        $('#filterDonemler, #baslangicDonem, #bitisDonem').on('keypress', function (e) {
            if (e.which === 13) {
                loadReport();
            }
        });

        // ======= TAM EKRAN =======
        $(document).on('click', '#btnTamEkran', function () {
            const isTab2 = $('#tab-okuma-gun').hasClass('active');
            const targetId = isTab2 ? 'okumaGunReportSection' : 'reportSection';
            toggleFullscreen(targetId, this);
        });

        $(document).on('click', '.btn-tab-fullscreen', function () {
            const targetId = $(this).data('target');
            toggleFullscreen(targetId, this);
        });

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#btnTamEkran, .btn-tab-fullscreen').html('<i class="mdi mdi-fullscreen me-1"></i>Tam Ekran');
                $('#reportSection, #okumaGunReportSection').removeClass('fullscreen-mode');
            }
        });

        // ======= SÜTUN YÖNETİMİ =======
        $(document).on('change', '.col-toggle', function () {
            const col = $(this).data('col');
            const isVisible = $(this).is(':checked');
            _visibleColumns[col] = isVisible;

            if (_tableData && _tableData.length > 0) {
                renderTable(_tableData, _tableDonemler, true);
            }
        });

        // Sayfa açıldığında otomatik olarak raporu yükle
        loadReport();

        // ======= TAB 2: OKUMA GÜN SAYILARI =======
        let _okumaGunData = [];
        let _okumaGunDonemler = [];
        let _okumaGunLoaded = false;

        // Sort state for Tab 2
        let _ogSortColumn = null; // 'ilce', 'mahalle', 'defter', 'abone_sayisi', '{donem}_fark'
        let _ogSortDirection = 'asc';
        let _ogSearchFilters = { ilce: '', mahalle: '', defter: '', abone_sayisi: '' };
        let _ogSearchTimeout;

        // Bölge renk paleti (pastel, birbirinden ayrışan renkler)
        const _regionColors = [
            { bg: 'rgba(59, 130, 246, 0.08)', header: 'rgba(59, 130, 246, 0.18)', text: '#1e40af' },
            { bg: 'rgba(16, 185, 129, 0.08)', header: 'rgba(16, 185, 129, 0.18)', text: '#065f46' },
            { bg: 'rgba(245, 158, 11, 0.08)', header: 'rgba(245, 158, 11, 0.18)', text: '#92400e' },
            { bg: 'rgba(239, 68, 68, 0.08)', header: 'rgba(239, 68, 68, 0.18)', text: '#991b1b' },
            { bg: 'rgba(139, 92, 246, 0.08)', header: 'rgba(139, 92, 246, 0.18)', text: '#5b21b6' },
            { bg: 'rgba(236, 72, 153, 0.08)', header: 'rgba(236, 72, 153, 0.18)', text: '#9d174d' },
            { bg: 'rgba(6, 182, 212, 0.08)', header: 'rgba(6, 182, 212, 0.18)', text: '#155e75' },
            { bg: 'rgba(132, 204, 22, 0.08)', header: 'rgba(132, 204, 22, 0.18)', text: '#3f6212' },
            { bg: 'rgba(251, 146, 60, 0.08)', header: 'rgba(251, 146, 60, 0.18)', text: '#9a3412' },
            { bg: 'rgba(168, 85, 247, 0.08)', header: 'rgba(168, 85, 247, 0.18)', text: '#6b21a8' },
        ];

        // Sekme değiştiğinde Tab 2 verilerini yükle
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'tab-okuma-gun') {
                loadOkumaGunSayilari();
            }
        });

        // Rapor Getir butonuna tıklanınca aktif sekmeye göre yükle
        $(document).on('click', '#btnRaporGetir', function () {
            if ($('#tab-okuma-gun').hasClass('active')) {
                _okumaGunLoaded = false;
                loadOkumaGunSayilari();
            }
        });

        // 35+ gün filtreleri
        $('#chk35Plus, #chk35PlusNoRead').on('change', function () {
            if (_okumaGunData.length > 0) {
                renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
            }
        });

        // Tab 2 Sort click handler
        $(document).on('click', '#okumaGunTable .og-sortable-header', function (e) {
            if ($(e.target).closest('.column-search, .col-filter-btn').length) return;
            const col = $(this).data('sort-col');
            if (!col) return;
            if (_ogSortColumn === col) {
                _ogSortDirection = _ogSortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                _ogSortColumn = col;
                _ogSortDirection = 'asc';
            }
            renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
        });

        // Tab 2 Search input handler
        $(document).on('input', '#okumaGunTable .column-search', function () {
            const col = $(this).data('col');
            const val = $(this).val();
            const id = $(this).attr('id');
            const pos = this.selectionStart;
            
            _ogSearchFilters[col] = val;
            clearTimeout(_ogSearchTimeout);
            
            _ogSearchTimeout = setTimeout(function () {
                renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
                
                // Focusu geri al - Detached olan self yerine ID üzerinden buluyoruz
                if (id) {
                    setTimeout(() => {
                        const input = document.getElementById(id);
                        if (input) {
                            input.focus();
                            input.setSelectionRange(pos, pos);
                        }
                    }, 0);
                }
            }, 300);
        });

        function loadOkumaGunSayilari() {
            const baslangicDonem = $('#baslangicDonem').val().trim();
            const bitisDonem = $('#bitisDonem').val().trim();
            const donemler = $('#filterDonemler').val();
            const bolge = $('#filterBolge').val();
            const defterVal = $('#filterDefter').val();

            if ((!baslangicDonem || !bitisDonem) && (!donemler || donemler.length === 0)) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem veya aralık seçiniz.', 'warning');
                return;
            }

            // Accordion'u kapat
            var collapseElement = document.getElementById('collapseFilter');
            var collapse = bootstrap.Collapse.getInstance(collapseElement);
            if (collapse) collapse.hide();
            else if (collapseElement.classList.contains('show')) new bootstrap.Collapse(collapseElement, { toggle: false }).hide();

            $('#okumaGunLoadingSection').show();
            $('#okumaGunReportSection').hide();
            $('#okumaGunSummaryCards').hide();
            $('#okumaGunFilterRow').hide();

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: {
                    action: 'okuma-gun-sayilari',
                    baslangic_donem: baslangicDonem,
                    bitis_donem: bitisDonem,
                    donemler: donemler,
                    bolge: bolge,
                    defter: defterVal
                },
                dataType: 'json',
                success: function (response) {
                    $('#okumaGunLoadingSection').hide();

                    if (response.status === 'success') {
                        _okumaGunData = response.data;
                        _okumaGunDonemler = response.donemler;
                        _okumaGunLoaded = true;

                        // Özet kartlarını güncelle
                        $('#okumaGunTotalDefter').text((response.summary.toplam_defter || 0).toLocaleString('tr-TR'));
                        $('#okumaGunTotalBolge').text((response.summary.toplam_bolge || 0).toLocaleString('tr-TR'));
                        $('#okumaGunTotalDonem').text((response.summary.donem_sayisi || 0).toLocaleString('tr-TR'));

                        // Reset sort/search on new data
                        _ogSortColumn = null;
                        _ogSortDirection = 'asc';
                        _ogSearchFilters = { ilce_tipi: '', bolge: '', defter: '', mahalle: '', abone_sayisi: '' };

                        renderOkumaGunTable(response.data, response.donemler);
                        $('#okumaGunSummaryCards').fadeIn(300);
                        $('#okumaGunFilterRow').fadeIn(300);
                        $('#okumaGunReportSection').fadeIn(300);
                    } else {
                        Swal.fire('Hata', response.message || 'Rapor oluşturulurken bir hata oluştu.', 'error');
                    }
                },
                error: function () {
                    $('#okumaGunLoadingSection').hide();
                    Swal.fire('Hata', 'Sunucuyla iletişim kurulamadı.', 'error');
                }
            });
        }

        function renderOkumaGunTable(data, donemler, keepState) {
            if (!data || data.length === 0) {
                $('#okumaGunTableWrapper').html('<div class="text-center p-5 text-muted"><i class="bx bx-search-alt fs-1 d-block mb-2"></i>Seçilen kriterlere uygun veri bulunamadı.</div>');
                return;
            }

            if (keepState !== true) {
                _ogSortColumn = null;
                _ogSortDirection = 'asc';
                _ogSearchFilters = { ilce_tipi: '', bolge: '', defter: '', mahalle: '', abone_sayisi: '' };
            }

            const only35Plus = $('#chk35Plus').is(':checked');
            const only35PlusNoRead = $('#chk35PlusNoRead').is(':checked');
            const today = new Date();

            // Apply search filters
            let filteredData = data.filter(function (row) {
                const ilceTipiMatch = !_ogSearchFilters.ilce_tipi || (row.ilce_tipi || '').toLowerCase().includes(_ogSearchFilters.ilce_tipi.toLowerCase());
                const bolgeMatch = !_ogSearchFilters.bolge || (row.bolge || '').toLowerCase().includes(_ogSearchFilters.bolge.toLowerCase());
                const defterMatch = !_ogSearchFilters.defter || (row.defter || '').toString().toLowerCase().includes(_ogSearchFilters.defter.toLowerCase());
                const mahalleMatch = !_ogSearchFilters.mahalle || (row.mahalle || '').toLowerCase().includes(_ogSearchFilters.mahalle.toLowerCase());
                const aboneMatch = !_ogSearchFilters.abone_sayisi || (row.abone_sayisi || '').toString().includes(_ogSearchFilters.abone_sayisi);
                
                let dateMatch = true;
                for (const d of donemler) {
                    const filterVal = _ogSearchFilters[d + '_tarih'];
                    if (filterVal) {
                        const di = row.donemler[d];
                        const dateStr = di ? (di.okuma_tarihi || '') : '';
                        if (!dateStr.toLowerCase().includes(filterVal.toLowerCase())) {
                            dateMatch = false;
                            break;
                        }
                    }
                }
                
                return ilceTipiMatch && bolgeMatch && defterMatch && mahalleMatch && aboneMatch && dateMatch;
            });

            // 1. "Sadece 35 ve üzeri gün farkı olanları göster" (Dönemler arası fark)
            if (only35Plus) {
                filteredData = filteredData.filter(function (row) {
                    for (const d of donemler) {
                        const di = row.donemler[d];
                        if (di && di.fark !== null && di.fark >= 35) return true;
                    }
                    return false;
                });
            }

            // 2. "Son okumadan itibaren 35+ gün geçenler" (En son okuma vs Bugün)
            if (only35PlusNoRead) {
                filteredData = filteredData.filter(function (row) {
                    let lastOkuma = null;
                    for (const d of donemler) {
                        const di = row.donemler[d];
                        if (di && di.okuma_tarihi_raw) {
                            const readDate = new Date(di.okuma_tarihi_raw);
                            if (!lastOkuma || readDate > lastOkuma) {
                                lastOkuma = readDate;
                            }
                        }
                    }
                    if (!lastOkuma) return false;
                    const diffTime = Math.abs(today - lastOkuma);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    return diffDays >= 35;
                });
            }

            // Sayısal filtreleri uygula (FARK ve TARIH için)
            for (const filterKey in _numericFilters) {
                const filter = _numericFilters[filterKey];
                if (!filter || filter.value === null || filter.value === '') continue;

                const isOkumaGun = filterKey.endsWith('_fark') || filterKey.endsWith('_tarih');
                if (!isOkumaGun) continue;

                const parts = filterKey.split('_');
                const field = parts.pop();
                const filterDonem = parts.join('_');

                filteredData = filteredData.filter(function (row) {
                    const donemData = row.donemler[filterDonem];
                    if (!donemData) return false;

                    let rowVal = null;
                    if (field === 'fark') {
                        rowVal = donemData.fark;
                    } else if (field === 'tarih') {
                        // Tarih filtresi için string karşılaştırma veya parselama gerekebilir, 
                        // ancak genellikle sayısal filtreler (fark) daha yaygındır.
                    }

                    if (rowVal === null || rowVal === undefined) return false;

                    const fVal = parseFloat(filter.value);
                    const rVal = parseFloat(rowVal);

                    if (isNaN(fVal) || isNaN(rVal)) return false;

                    switch (filter.operator) {
                        case '>': return rVal > fVal;
                        case '<': return rVal < fVal;
                        case '>=': return rVal >= fVal;
                        case '<=': return rVal <= fVal;
                        case '=': return rVal === fVal;
                        default: return true;
                    }
                });
            }



            // Apply sort
            let sortedData = [...filteredData];
            if (_ogSortColumn) {
                sortedData.sort(function (a, b) {
                    let valA, valB;
                    if (_ogSortColumn === 'ilce_tipi') {
                        valA = (a.ilce_tipi || '').toLowerCase();
                        valB = (b.ilce_tipi || '').toLowerCase();
                    } else if (_ogSortColumn === 'bolge') {
                        valA = (a.bolge || '').toLowerCase();
                        valB = (b.bolge || '').toLowerCase();
                    } else if (_ogSortColumn === 'defter') {
                        valA = (a.defter || '').toString();
                        valB = (b.defter || '').toString();
                        const nA = parseInt(valA), nB = parseInt(valB);
                        if (!isNaN(nA) && !isNaN(nB)) { valA = nA; valB = nB; }
                    } else if (_ogSortColumn === 'mahalle') {
                        valA = (a.mahalle || '').toLowerCase();
                        valB = (b.mahalle || '').toLowerCase();
                    } else if (_ogSortColumn === 'abone_sayisi') {
                        valA = parseInt(a.abone_sayisi) || 0;
                        valB = parseInt(b.abone_sayisi) || 0;
                    } else if (_ogSortColumn.endsWith('_fark')) {
                        const donem = _ogSortColumn.replace('_fark', '');
                        valA = (a.donemler[donem] && a.donemler[donem].fark !== null) ? a.donemler[donem].fark : -1;
                        valB = (b.donemler[donem] && b.donemler[donem].fark !== null) ? b.donemler[donem].fark : -1;
                    } else if (_ogSortColumn.endsWith('_tarih')) {
                        const donem = _ogSortColumn.replace('_tarih', '');
                        valA = (a.donemler[donem] && a.donemler[donem].okuma_tarihi_raw) ? a.donemler[donem].okuma_tarihi_raw : '';
                        valB = (b.donemler[donem] && b.donemler[donem].okuma_tarihi_raw) ? b.donemler[donem].okuma_tarihi_raw : '';
                    }
                    if (valA < valB) return _ogSortDirection === 'asc' ? -1 : 1;
                    if (valA > valB) return _ogSortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
            }

            // Sort indicator helper
            function ogSortIcon(colKey) {
                if (_ogSortColumn === colKey) {
                    const icon = _ogSortDirection === 'asc' ? '▲' : '▼';
                    return `<span class="sort-icon active">${icon}</span>`;
                }
                return '<span class="sort-icon">⇅</span>';
            }

            // Bölgelere göre grupla
            const regionMap = {};
            const regionOrder = [];
            sortedData.forEach(function (row) {
                const region = row.bolge || 'TANIMSIZ';
                if (!regionMap[region]) {
                    regionMap[region] = [];
                    regionOrder.push(region);
                }
                regionMap[region].push(row);
            });

            let html = '<table class="table table-bordered table-sm mb-0" id="okumaGunTable">';

            // ======= THEAD =======
            html += '<thead>';

            // Row 1: Fixed labels + Periods
            html += '<tr class="main-headers-row">';
            html += `<th class="fix-col-1 og-sortable-header" data-sort-col="ilce_tipi">İLÇE TİPİ${ogSortIcon('ilce_tipi')}</th>`;
            html += `<th class="fix-col-2 og-sortable-header" data-sort-col="bolge">BÖLGE${ogSortIcon('bolge')}</th>`;
            html += `<th class="fix-col-3 og-sortable-header" data-sort-col="defter">DEFTER${ogSortIcon('defter')}</th>`;
            html += `<th class="fix-col-4 og-sortable-header" data-sort-col="mahalle">MAHALLE${ogSortIcon('mahalle')}</th>`;
            html += `<th class="fix-col-5 og-sortable-header" data-sort-col="abone_sayisi">ABONE SAYISI${ogSortIcon('abone_sayisi')}</th>`;

            donemler.forEach(function (donem, idx) {
                const isLast = idx === donemler.length - 1;
                const formatted = donem.substring(0, 4) + '/' + donem.substring(4);
                html += `<th colspan="2" class="period-header ${isLast ? 'ogr-period-end' : ''}">${formatted}</th>`;
            });
            html += '</tr>';

            // Row 2: Search Inputs + Sub-headers
            html += '<tr class="sub-headers-row search-row">';
            html += `<th class="fix-col-1"><input type="text" class="form-control column-search" id="og_search_ilce_tipi" data-col="ilce_tipi" value="${_ogSearchFilters.ilce_tipi || ''}" placeholder="İLÇE TİPİ"></th>`;
            html += `<th class="fix-col-2"><input type="text" class="form-control column-search" id="og_search_bolge" data-col="bolge" value="${_ogSearchFilters.bolge || ''}" placeholder="BÖLGE"></th>`;
            html += `<th class="fix-col-3"><input type="text" class="form-control column-search" id="og_search_defter" data-col="defter" value="${_ogSearchFilters.defter || ''}" placeholder="DEFTER"></th>`;
            html += `<th class="fix-col-4"><input type="text" class="form-control column-search" id="og_search_mahalle" data-col="mahalle" value="${_ogSearchFilters.mahalle || ''}" placeholder="MAHALLE"></th>`;
            html += `<th class="fix-col-5"><input type="text" class="form-control column-search" id="og_search_abone_sayisi" data-col="abone_sayisi" value="${_ogSearchFilters.abone_sayisi || ''}" placeholder="ABONE"></th>`;

            donemler.forEach(function (donem, idx) {
                const isLast = idx === donemler.length - 1;
                
                // Okuma Tarihi (Search input inside header)
                html += `<th class="sub-header og-sortable-header" data-sort-col="${donem}_tarih">
                    <input type="text" class="form-control column-search text-center mb-1 mx-auto" id="og_search_${donem}_tarih" style="height:22px; width:90%; padding:2px; font-size:10px;" data-col="${donem}_tarih" value="${_ogSearchFilters[donem + '_tarih'] || ''}" placeholder="Ara...">
                    <br>OKUMA TARİHİ${ogSortIcon(donem + '_tarih')}
                </th>`;
                
                // Fark (Numeric filter button)
                const filterKey = donem + '_fark';
                const isActive = _numericFilters[filterKey] && _numericFilters[filterKey].operator && _numericFilters[filterKey].value !== '';
                const activeClass = isActive ? 'col-filter-active' : '';
                const dot = isActive ? '<span class="filter-dot"></span>' : '';
                html += `<th class="sub-header og-sortable-header ${isLast ? 'ogr-period-end' : ''}" data-sort-col="${filterKey}">
                    <div class="d-flex align-items-center justify-content-center gap-1">
                        <button type="button" class="col-filter-btn ${activeClass}" data-filter-col="${filterKey}" title="Sayısal Filtrele">
                            <i class="bx bx-filter-alt"></i>
                        </button>${dot}
                    </div>
                    FARK${ogSortIcon(filterKey)}
                </th>`;
            });
            html += '</tr>';
            html += '</thead>';

            // TBODY
            html += '<tbody>';

            if (filteredData.length === 0) {
                const totalCols = 5 + (donemler.length * 2);
                html += `<tr><td colspan="${totalCols}" class="text-center p-4 text-muted"><i class="bx bx-info-circle me-1"></i>Filtrelere uygun veri bulunamadı.</td></tr>`;
            }

            let rowNum = 0;
            regionOrder.forEach(function (region, regionIdx) {
                const regionColor = _regionColors[regionIdx % _regionColors.length];
                const rows = regionMap[region];

                // Calculate Region Totals (Tab 2)
                let regionAboneSum = 0;
                rows.forEach(function (row) {
                    regionAboneSum += parseInt(row.abone_sayisi) || 0;
                });

                // Bölge başlık satırı
                html += `<tr class="ogr-region-row">`;
                html += `<td colspan="4" class="ogr-region-header text-start" style="background: ${regionColor.header}; color: ${regionColor.text};">`;
                html += `<i class="bx bx-map me-1"></i>${region} <span class="badge bg-white text-dark ms-2" style="font-size:10px;">${rows.length} defter</span>`;
                html += '</td>';
                
                // Master Abone Sayısı Total for Region
                html += `<td class="ogr-region-header text-end" style="background: ${regionColor.header}; color: ${regionColor.text}; font-weight: 800;">${regionAboneSum.toLocaleString('tr-TR')}</td>`;

                // Empty cells for periods (Date and Diff don't sum)
                donemler.forEach(function (donem, idx) {
                    const isLast = idx === donemler.length - 1;
                    html += `<td class="ogr-region-header" style="background: ${regionColor.header};" colspan="2"></td>`;
                });
                html += '</tr>';

                rows.forEach(function (row) {
                    rowNum++;
                    html += `<tr style="background-color: ${regionColor.bg};">`;
                    html += `<td class="fix-col-1 text-start" style="background-color: ${regionColor.bg};">${row.ilce_tipi || '-'}</td>`;
                    html += `<td class="fix-col-2 text-start fw-medium" style="background-color: ${regionColor.bg};">${row.bolge || '-'}</td>`;
                    html += `<td class="fix-col-3 fw-bold" style="background-color: ${regionColor.bg};">${row.defter}</td>`;
                    html += `<td class="fix-col-4 text-start" style="background-color: ${regionColor.bg};">${row.mahalle || '-'}</td>`;
                    html += `<td class="fix-col-5" style="background-color: ${regionColor.bg};">${row.abone_sayisi ? row.abone_sayisi.toLocaleString('tr-TR') : '-'}</td>`;

                    donemler.forEach(function (donem, idx) {
                        const isLast = idx === donemler.length - 1;
                        const di = row.donemler[donem] || { okuma_tarihi: '', fark: null };

                        html += `<td>${di.okuma_tarihi || ''}</td>`;

                        let farkClass = '';
                        let farkText = '';
                        if (di.fark !== null && di.fark !== undefined) {
                            farkText = di.fark;
                            farkClass = di.fark >= 35 ? 'fark-danger' : 'fark-normal';
                        }
                        html += `<td class="${farkClass} ${isLast ? 'ogr-period-end' : ''}">${farkText}</td>`;
                    });

                    html += '</tr>';
                });
            });

            html += '</tbody>';
            html += '</table>';

            $('#okumaGunTableWrapper').html(html);
            updateNumericFilterBadges();
        }

        function updateNumericFilterBadges() {
            const container = $('#okumaGunFilterBadges');
            if (!container.length) return;
            container.empty();

            for (const key in _numericFilters) {
                const f = _numericFilters[key];
                if (!f) continue;

                // Sadece Tab 2 için olanları (fark) göster
                if (!key.endsWith('_fark')) continue;

                const parts = key.split('_');
                const field = parts.pop();
                const donem = parts.join('_');
                const formattedDonem = donem.substring(0, 4) + '/' + donem.substring(4);
                const fieldName = field === 'fark' ? 'FARK' : field.toUpperCase();

                container.append(`
                    <div class="filter-badge">
                        <span class="badge-label">${formattedDonem} ${fieldName}:</span>
                        <span class="badge-value">${f.operator} ${f.value}</span>
                        <button type="button" class="badge-close" data-filter-key="${key}" title="Filtreyi Kaldır">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                `);
            }
        }

        // Badge temizleme
        $(document).on('click', '.badge-close', function (e) {
            const key = $(this).data('filter-key');
            if (key) {
                delete _numericFilters[key];
                if ($('#tab-okuma-gun').hasClass('active')) {
                    renderOkumaGunTable(_okumaGunData, _okumaGunDonemler, true);
                } else {
                    renderTable(_tableData, _tableDonemler, true);
                }
            }
        });

        // ======= TAB 3: AYLIK DEFTER ÖZETİ =======
        let _defterOzetData = null;
        let _defterOzetLoaded = false;
        
        // Tab 3 State
        let _doSearchFilters = { bolge: '' };
        let _doNumericFilters = {}; // key: donem_field -> {operator, value}
        let _doSortColumn = null;
        let _doSortDirection = 'asc';
        let _doSearchTimeout;

        // Sekme değiştiğinde Tab 3 verilerini yükle
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'tab-defter-ozet') {
                loadDefterOzet();
            }
        });

        // Rapor Getir butonuna tıklanınca aktif sekmeye göre yükle
        $(document).on('click', '#btnRaporGetir', function () {
            if ($('#tab-defter-ozet').hasClass('active')) {
                _defterOzetLoaded = false;
                loadDefterOzet();
            }
        });

        function loadDefterOzet() {
            const baslangicDonem = $('#baslangicDonem').val().trim();
            const bitisDonem = $('#bitisDonem').val().trim();
            const donemler = $('#filterDonemler').val();
            const bolge = $('#filterBolge').val();
            const defterVal = $('#filterDefter').val();

            if ((!baslangicDonem || !bitisDonem) && (!donemler || donemler.length === 0)) {
                Swal.fire('Uyarı', 'Lütfen en az bir dönem veya aralık seçiniz.', 'warning');
                return;
            }

            // Accordion'u kapat
            var collapseElement = document.getElementById('collapseFilter');
            var collapse = bootstrap.Collapse.getInstance(collapseElement);
            if (collapse) collapse.hide();
            else if (collapseElement.classList.contains('show')) new bootstrap.Collapse(collapseElement, { toggle: false }).hide();

            $('#defterOzetLoadingSection').show();
            $('#defterOzetReportSection').hide();
            $('#defterOzetSummaryCards').hide();
            $('#defterOzetActions').hide();

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: {
                    action: 'defter-ozet-rapor',
                    baslangic_donem: baslangicDonem,
                    bitis_donem: bitisDonem,
                    donemler: donemler,
                    bolge: bolge,
                    defter: defterVal
                },
                dataType: 'json',
                success: function (response) {
                    $('#defterOzetLoadingSection').hide();

                    if (response.status === 'success') {
                        _defterOzetData = response;
                        _defterOzetLoaded = true;

                        // Reset filters on new data load (Optional, or keep them)
                        // _doNumericFilters = {};
                        // _doSearchFilters = { bolge: '' };

                        // Özet kartlarını güncelle
                        $('#defterOzetTotalDefter').text((response.summary.toplam_defter || 0).toLocaleString('tr-TR'));
                        $('#defterOzetTotalBolge').text((response.summary.toplam_bolge || 0).toLocaleString('tr-TR'));
                        $('#defterOzetTotalDonem').text((response.summary.donem_sayisi || 0).toLocaleString('tr-TR'));

                        renderDefterOzetTable(response, true);
                        $('#defterOzetSummaryCards').fadeIn(300);
                        $('#defterOzetActions').fadeIn(300);
                        $('#defterOzetReportSection').fadeIn(300);
                    } else {
                        Swal.fire('Hata', response.message || 'Rapor oluşturulurken bir hata oluştu.', 'error');
                    }
                },
                error: function () {
                    $('#defterOzetLoadingSection').hide();
                    Swal.fire('Hata', 'Sunucuyla iletişim kurulamadı.', 'error');
                }
            });
        }

        function renderDefterOzetTable(data, resetScroll) {
            const donemler = data.donemler;
            const genel = data.genel;
            const bolgeData = data.bolge;

            if (!donemler || donemler.length === 0) {
                $('#defterOzetTableWrapper').html('<div class="text-center p-5 text-muted"><i class="bx bx-search-alt fs-1 d-block mb-2"></i>Veri bulunamadı.</div>');
                return;
            }

            // === FILTRELEME MANTIGI ===
            let filteredBolgeNames = Object.keys(bolgeData).filter(function(bName) {
                // 1. Bolge Ismi Ara
                if (_doSearchFilters.bolge && !bName.toLowerCase().includes(_doSearchFilters.bolge.toLowerCase())) {
                    return false;
                }

                // 2. Sayisal Filtreler
                for (let fKey in _doNumericFilters) {
                    const filter = _doNumericFilters[fKey];
                    if (!filter || filter.value === '' || filter.value === null) continue;

                    const parts = fKey.split('_');
                    const field = parts.pop(); // toplam, okunan, okunmayan, oran
                    const donem = parts.join('_');

                    const bStat = (bolgeData[bName] && bolgeData[bName][donem]) || { toplam_defter: 0, okunan_defter: 0, okunmayan_defter: 0, oran: 0 };
                    let rowVal = 0;
                    if (field === 'toplam') rowVal = bStat.toplam_defter;
                    else if (field === 'okunan') rowVal = bStat.okunan_defter;
                    else if (field === 'okunmayan') rowVal = bStat.okunmayan_defter;
                    else if (field === 'oran') rowVal = bStat.oran;

                    const fVal = parseFloat(filter.value);
                    const rVal = parseFloat(rowVal);
                    if (isNaN(fVal) || isNaN(rVal)) continue;

                    let match = false;
                    switch (filter.operator) {
                        case '>': match = rVal > fVal; break;
                        case '<': match = rVal < fVal; break;
                        case '>=': match = rVal >= fVal; break;
                        case '<=': match = rVal <= fVal; break;
                        case '=': match = rVal === fVal; break;
                        default: match = true;
                    }
                    if (!match) return false;
                }
                return true;
            });

            // === SIRALAMA MANTIGI (Opsiyonel, simdilik ksort gibi) ===
            filteredBolgeNames.sort();

            let html = '<table class="table table-bordered table-sm mb-0" id="defterOzetTable">';

            // ======= THEAD =======
            html += '<thead>';

            // Row 1: Basliklar
            html += '<tr>';
            html += '<th class="fix-col-bolge" colspan="2">BÖLGE / TÜR</th>';
            donemler.forEach(function (donem, idx) {
                const isLast = idx === donemler.length - 1;
                const formatted = String(donem).substring(0, 4) + '/' + String(donem).substring(4);
                html += '<th colspan="4" class="period-header ' + (isLast ? 'do-period-end' : '') + '">' + formatted + '</th>';
            });
            html += '</tr>';

            // Row 2: Arama ve Filtre Butonlari
            html += '<tr class="search-row">';
            // Bolge Arama
            html += '<th class="fix-col-bolge"><input type="text" class="form-control do-column-search" id="do_search_bolge" placeholder="Bölge Ara..." value="' + (_doSearchFilters.bolge || '') + '"></th>';
            html += '<th style="width: 30px; font-size: 8px; font-weight: 800; color: #94a3b8; background: #f8f9fa; vertical-align: middle; text-align: center;">TÜR</th>';
            
            donemler.forEach(function (donem, idx) {
                const isLast = idx === donemler.length - 1;
                const fields = ['toplam', 'okunan', 'okunmayan', 'oran'];
                const labels = ['TOPLAM', 'OKUNAN', 'KALAN', 'BAŞARI %'];

                fields.forEach(function(f, fIdx) {
                    const fKey = donem + '_' + f;
                    const isActive = _doNumericFilters[fKey] && _doNumericFilters[fKey].value !== '';
                    const activeClass = isActive ? 'col-filter-active' : '';
                    const dot = isActive ? '<span class="filter-dot"></span>' : '';
                    const isLastField = fIdx === 3;

                    html += '<th class="sub-header ' + (isLast && isLastField ? 'do-period-end' : '') + '">';
                    html += '<div class="d-flex align-items-center justify-content-center gap-1 mb-1">';
                    html += '<button type="button" class="col-filter-btn ' + activeClass + '" data-do-filter-col="' + fKey + '" title="' + labels[fIdx] + ' Filtrele">';
                    html += '<i class="bx bx-filter-alt" style="font-size:10px;"></i>';
                    html += '</button>' + dot;
                    html += '</div>';
                    html += '<div style="font-size:9px;">' + labels[fIdx] + '</div>';
                    html += '</th>';
                });
            });
            html += '</tr>';
            html += '</thead>';

            // ======= TBODY =======
            html += '<tbody>';

            if (filteredBolgeNames.length === 0) {
                const totalCols = 2 + (donemler.length * 4);
                html += '<tr><td colspan="' + totalCols + '" class="text-center p-4 text-muted">Filtrelere uygun bölge bulunamadı.</td></tr>';
            } else {
                // GENEL TOPLAM SATIRI
                html += '<tr class="do-genel-row">';
                html += '<td rowspan="2" class="fix-col-bolge fw-bold" style="font-size: 13px; vertical-align: middle; background: #fff; border-right: none;"><i class="bx bx-globe me-1 text-primary"></i>GENEL TOPLAM</td>';
                html += '<td style="vertical-align: middle; background: #fff; text-align: center; border-left: none;" title="Defter Bazlı Veriler"><i class="bx bx-book-open text-muted fs-5"></i></td>';
                
                donemler.forEach(function (donem, idx) {
                    const isLast = idx === donemler.length - 1;
                    const d = String(donem);
                    const g = genel[d] || { toplam_defter: 0, okunan_defter: 0, okunmayan_defter: 0, oran: 0, sub_toplam: 0, sub_okunan: 0, sub_kalan: 0, sub_oran: 0 };
                    const oranClass = g.oran >= 80 ? 'do-oran-high' : (g.oran >= 50 ? 'do-oran-medium' : 'do-oran-low');

                    html += '<td><span class="do-badge-toplam clickable no-upgrade" data-type="toplam_detay" data-donem="' + d + '" data-bolge="__GENEL__" title="Tıklayın: Toplam defterleri görün">' + g.toplam_defter + '</span></td>';
                    html += '<td><span class="do-badge-okunan clickable no-upgrade" data-type="okunan_detay" data-donem="' + d + '" data-bolge="__GENEL__" title="Tıklayın: Okunan defterleri görün">' + g.okunan_defter + '</span></td>';

                    if (g.okunmayan_defter > 0) {
                        html += '<td><span class="do-badge-okunmayan clickable no-upgrade" data-type="okunmayan_detay" data-donem="' + d + '" data-bolge="__GENEL__" title="Tıklayın: Okunmayan defterleri görün">' + g.okunmayan_defter + '</span></td>';
                    } else {
                        html += '<td><span class="do-badge-okunmayan zero no-upgrade">' + g.okunmayan_defter + '</span></td>';
                    }

                    html += '<td class="do-oran-cell ' + (isLast ? 'do-period-end' : '') + '">';
                    html += '<span class="do-badge-oran ' + oranClass + '" style="padding: 2px 8px; font-size: 13px;">' + g.oran + '%</span>';
                    html += '</td>';
                });
                html += '</tr>';

                // GENEL TOPLAM ABONE SATIRI
                html += '<tr class="do-sub-row">';
                html += '<td style="vertical-align: middle; text-align: center; border-left: none; background: rgba(var(--bs-primary-rgb), 0.01);" title="Abone Bazlı Veriler"><i class="bx bx-user text-muted fs-5"></i></td>';
                
                donemler.forEach(function (donem, idx) {
                    const isLast = idx === donemler.length - 1;
                    const d = String(donem);
                    const g = genel[d] || { sub_toplam: 0, sub_okunan: 0, sub_kalan: 0, sub_oran: 0 };

                    html += '<td><span class="do-badge-sub do-badge-sub-toplam">' + (g.sub_toplam || 0).toLocaleString('tr-TR') + '</span></td>';
                    html += '<td><span class="do-badge-sub do-badge-sub-okunan">' + (g.sub_okunan || 0).toLocaleString('tr-TR') + '</span></td>';
                    html += '<td><span class="do-badge-sub do-badge-sub-kalan">' + (g.sub_kalan || 0).toLocaleString('tr-TR') + '</span></td>';
                    
                    let subOranClassGenel = g.sub_oran >= 80 ? 'text-success' : (g.sub_oran >= 50 ? 'text-warning' : 'text-danger');
                    html += '<td class="do-oran-cell ' + (isLast ? 'do-period-end' : '') + '"><span class="' + subOranClassGenel + ' fw-bold" style="font-size: 11.5px;">' + g.sub_oran + '%</span></td>';
                });
                html += '</tr>';

                // BÖLGE SATIRLARI
                let regionIdx = 0;
                filteredBolgeNames.forEach(function (bName) {
                    const regionColor = _regionColors[regionIdx % _regionColors.length];
                    regionIdx++;

                    html += '<tr class="do-bolge-row">';
                    html += '<td rowspan="2" class="fix-col-bolge fw-semibold text-start" style="background: ' + regionColor.bg + '; vertical-align: middle; border-left: 4px solid ' + regionColor.text + '; border-right: none;">';
                    html += bName;
                    html += '</td>';
                    html += '<td style="vertical-align: middle; background: ' + regionColor.bg + '; text-align: center; border-left: none;" title="Defter Verileri"><i class="bx bx-book-open text-muted fs-6"></i></td>';

                    donemler.forEach(function (donem, idx) {
                        const isLast = idx === donemler.length - 1;
                        const d = String(donem);
                        const bStat = (bolgeData[bName] && bolgeData[bName][d]) || { toplam_defter: 0, okunan_defter: 0, okunmayan_defter: 0, oran: 0 };
                        const oranClass = bStat.oran >= 80 ? 'do-oran-high' : (bStat.oran >= 50 ? 'do-oran-medium' : 'do-oran-low');

                        html += '<td style="background: ' + regionColor.bg + ';"><span class="do-badge-toplam clickable no-upgrade" data-type="toplam_detay" data-donem="' + d + '" data-bolge="' + bName + '" title="Tıklayın: ' + bName + ' toplam defterleri">' + bStat.toplam_defter + '</span></td>';
                        html += '<td style="background: ' + regionColor.bg + ';"><span class="do-badge-okunan clickable no-upgrade" data-type="okunan_detay" data-donem="' + d + '" data-bolge="' + bName + '" title="Tıklayın: ' + bName + ' okunan defterleri">' + bStat.okunan_defter + '</span></td>';

                        if (bStat.okunmayan_defter > 0) {
                            html += '<td style="background: ' + regionColor.bg + ';"><span class="do-badge-okunmayan clickable no-upgrade" data-type="okunmayan_detay" data-donem="' + d + '" data-bolge="' + bName + '" title="Tıklayın: ' + bName + ' okunmayan defterleri">' + bStat.okunmayan_defter + '</span></td>';
                        } else {
                            html += '<td style="background: ' + regionColor.bg + ';"><span class="do-badge-okunmayan zero no-upgrade">' + bStat.okunmayan_defter + '</span></td>';
                        }

                        html += '<td class="do-oran-cell ' + (isLast ? 'do-period-end' : '') + '" style="background: ' + regionColor.bg + ';">';
                        html += '<span class="do-badge-oran ' + oranClass + '" style="padding: 2px 8px; font-size: 13px;">' + bStat.oran + '%</span>';
                        html += '</td>';
                    });
                    html += '</tr>';

                    // BÖLGE ABONE SATIRI
                    html += '<tr class="do-sub-row">';
                    html += '<td style="vertical-align: middle; text-align: center; border-left: none; background: rgba(var(--bs-primary-rgb), 0.005);" title="Abone Verileri"><i class="bx bx-user text-muted fs-6"></i></td>';
                    
                    donemler.forEach(function (donem, idx) {
                        const isLast = idx === donemler.length - 1;
                        const d = String(donem);
                        const bStat = (bolgeData[bName] && bolgeData[bName][d]) || { sub_toplam: 0, sub_okunan: 0, sub_kalan: 0, sub_oran: 0 };

                        html += '<td><span class="do-badge-sub do-badge-sub-toplam">' + (bStat.sub_toplam || 0).toLocaleString('tr-TR') + '</span></td>';
                        html += '<td><span class="do-badge-sub do-badge-sub-okunan">' + (bStat.sub_okunan || 0).toLocaleString('tr-TR') + '</span></td>';
                        html += '<td><span class="do-badge-sub do-badge-sub-kalan">' + (bStat.sub_kalan || 0).toLocaleString('tr-TR') + '</span></td>';
                        
                        let subOranClass = bStat.sub_oran >= 80 ? 'text-success' : (bStat.sub_oran >= 50 ? 'text-warning' : 'text-danger');
                        html += '<td class="do-oran-cell ' + (isLast ? 'do-period-end' : '') + '"><span class="' + subOranClass + ' fw-bold" style="font-size: 11.5px;">' + bStat.sub_oran + '%</span></td>';
                    });
                    html += '</tr>';
                });
            }

            html += '</tbody>';
            html += '</table>';

            $('#defterOzetTableWrapper').html(html);
            if (resetScroll) $('#defterOzetTableWrapper').scrollTop(0);
        }

        // ======= TAB 3 OLAYLARI (Arama ve Filtreleme) =======
        
        // 1. Bolge Arama (Input Handler)
        $(document).on('input', '#defterOzetTable .do-column-search', function () {
            const val = $(this).val();
            const id = $(this).attr('id');
            const pos = this.selectionStart;
            _doSearchFilters.bolge = val;
            
            clearTimeout(_doSearchTimeout);
            _doSearchTimeout = setTimeout(function () {
                if (_defterOzetData) renderDefterOzetTable(_defterOzetData);
                if (id) {
                    setTimeout(() => {
                        const input = document.getElementById(id);
                        if (input) {
                            input.focus();
                            input.setSelectionRange(pos, pos);
                        }
                    }, 0);
                }
            }, 300);
        });

        // 2. Sayisal Filtre Popu'unu Ac - (Global handler handles this now)

        // ======= BADGE CLICK HANDLER (Dışarıda, tek sefer bağlanır) =======
        $(document).on('click', '.do-badge-toplam.clickable, .do-badge-okunan.clickable, .do-badge-okunmayan.clickable', function (e) {
            e.stopPropagation();
            const $el = $(this);
            const donem = $el.attr('data-donem');
            const bolge = $el.attr('data-bolge');
            const type = $el.attr('data-type'); // toplam_detay, okunan_detay, okunmayan_detay

            if (!donem || !_defterOzetData) {
                console.warn('Defter özet click: donem veya data yok', donem, _defterOzetData);
                return;
            }

            // Başlık belirleme
            let typeTitle = 'Defterler';
            let titleIcon = 'bx-book';
            let titleBg = 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)';

            if (type === 'okunan_detay') {
                typeTitle = 'Okunan Defterler';
                titleIcon = 'bx-check-double';
                titleBg = 'linear-gradient(135deg, #10b981 0%, #047857 100%)';
            } else if (type === 'okunmayan_detay') {
                typeTitle = 'Okunmayan Defterler';
                titleIcon = 'bx-x-circle';
                titleBg = 'linear-gradient(135deg, #f43f5e 0%, #e11d48 100%)';
            } else if (type === 'toplam_detay') {
                typeTitle = 'Toplam Defterler';
                titleIcon = 'bx-list-ul';
                titleBg = 'linear-gradient(135deg, #64748b 0%, #334155 100%)';
            }

            const formatted = String(donem).substring(0, 4) + '/' + String(donem).substring(4);
            let defterList = [];

            const details = _defterOzetData[type] || {};
            const donemDetails = details[donem] || {};

            if (bolge === '__GENEL__') {
                // Tüm bölgelerdeki defterleri birleştir
                for (const bName in donemDetails) {
                    defterList = defterList.concat(donemDetails[bName]);
                }
                $('#modalDefterListTitle').html('<i class="bx ' + titleIcon + ' me-2"></i>' + typeTitle + ' — ' + formatted + ' (Tüm Bölgeler)');
            } else {
                defterList = donemDetails[bolge] || [];
                $('#modalDefterListTitle').html('<i class="bx ' + titleIcon + ' me-2"></i>' + typeTitle + ' — ' + formatted + ' — <span class="text-warning">' + bolge + '</span>');
            }

            // Modal Header rengini güncelle
            $('#modalOkunmayanDefterler .modal-header').css('background', titleBg);

            // Modal içeriğini oluştur
            let modalHtml = '';

            if (defterList.length === 0) {
                modalHtml = '<div class="text-center p-4 text-muted"><i class="bx bx-info-circle fs-1 text-info d-block mb-2"></i>Bu dönem için veri bulunmuyor.</div>';
            } else {
                // Bölge bazlı grupla ve toplamları hesapla
                const grouped = {};
                let grandTotalAbone = 0;
                let badgeLabel = 'Abone';
                let colTitle = 'Abone Sayısı';

                if (type === 'okunan_detay') {
                    badgeLabel = 'Okunan';
                    colTitle = 'Okunan Sayısı';
                } else if (type === 'okunmayan_detay') {
                    badgeLabel = 'Okunmayan';
                    colTitle = 'Okunmayan Sayısı';
                }

                defterList.forEach(function (d) {
                    const b = d.bolge || 'TANIMSIZ';
                    if (!grouped[b]) grouped[b] = [];
                    grouped[b].push(d);
                    
                    if (type === 'okunan_detay') {
                        grandTotalAbone += parseInt(d.okunan) || 0;
                    } else if (type === 'okunmayan_detay') {
                        grandTotalAbone += parseInt(d.okunmayan) || 0;
                    } else {
                        grandTotalAbone += parseInt(d.abone_sayisi) || 0;
                    }
                });

                modalHtml += '<div class="d-flex align-items-center flex-wrap gap-2 mb-3">';
                modalHtml += '<span class="badge bg-primary-subtle text-primary" style="font-size: 13px; padding: 8px 16px; border-radius: 8px;"><i class="bx bx-list-ul me-1"></i>Toplam ' + defterList.length + ' defter</span>';
                modalHtml += '<span class="badge bg-danger-subtle text-danger" style="font-size: 13px; padding: 8px 16px; border-radius: 8px;"><i class="bx bx-user me-1"></i>' + grandTotalAbone.toLocaleString('tr-TR') + ' ' + badgeLabel + '</span>';
                modalHtml += '<span class="badge bg-secondary-subtle text-secondary" style="font-size: 12px; padding: 6px 12px; border-radius: 8px;">' + Object.keys(grouped).length + ' bölge</span>';
                modalHtml += '</div>';

                modalHtml += '<div class="table-responsive" style="max-height: 450px; overflow: auto;">';
                modalHtml += '<table class="table table-sm table-bordered mb-0" id="okunmayanDefterTable">';
                modalHtml += '<thead><tr>';
                modalHtml += '<th style="width:40px;">#</th>';
                modalHtml += '<th>Bölge</th>';
                modalHtml += '<th>Defter</th>';
                modalHtml += '<th>Mahalle</th>';
                modalHtml += '<th>' + colTitle + '</th>';
                modalHtml += '</tr></thead>';
                modalHtml += '<tbody>';

                let sira = 0;
                const groupedKeys = Object.keys(grouped).sort();
                groupedKeys.forEach(function (bName) {
                    const items = grouped[bName];
                    let regionAboneSum = 0;
                    items.forEach(function (d) { 
                        if (type === 'okunan_detay') {
                            regionAboneSum += parseInt(d.okunan) || 0; 
                        } else if (type === 'okunmayan_detay') {
                            regionAboneSum += parseInt(d.okunmayan) || 0;
                        } else {
                            regionAboneSum += parseInt(d.abone_sayisi) || 0; 
                        }
                    });

                    // Bölge başlık satırı
                    modalHtml += '<tr style="background: rgba(var(--bs-primary-rgb), 0.06);">';
                    modalHtml += '<td colspan="4" class="fw-bold text-start" style="font-size: 12px;"><i class="bx bx-map me-1 text-primary"></i>' + bName + ' <span class="badge bg-white text-dark border ms-2" style="font-size: 10px;">' + items.length + ' defter</span></td>';
                    modalHtml += '<td class="text-end fw-bold" style="font-size: 12px;">' + regionAboneSum.toLocaleString('tr-TR') + '</td>';
                    modalHtml += '</tr>';

                    items.forEach(function (d) {
                        sira++;
                        const displayVal = (type === 'okunan_detay') ? (parseInt(d.okunan) || 0) : 
                                           (type === 'okunmayan_detay' ? (parseInt(d.okunmayan) || 0) : (parseInt(d.abone_sayisi) || 0));
                        modalHtml += '<tr>';
                        modalHtml += '<td class="text-muted">' + sira + '</td>';
                        modalHtml += '<td class="fw-medium">' + (d.bolge || '-') + '</td>';
                        modalHtml += '<td class="fw-bold">' + d.defter + '</td>';
                        modalHtml += '<td>' + (d.mahalle || '-') + '</td>';
                        modalHtml += '<td class="fw-semibold">' + displayVal.toLocaleString('tr-TR') + '</td>';
                        modalHtml += '</tr>';
                    });
                });

                modalHtml += '</tbody></table></div>';
            }

            $('#modalDefterListBody').html(modalHtml);

            // Modal'ı aç (mevcut instance varsa onu kullan)
            var modalEl = document.getElementById('modalOkunmayanDefterler');
            var existingModal = bootstrap.Modal.getInstance(modalEl);
            if (existingModal) {
                existingModal.show();
            } else {
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        });

        // ======= GRAFİK MANTİĞI =======
        let _defterOzetChart = null;

        $(document).on('change', '.view-toggle', function() {
            const view = $(this).data('view');
            const tab = $(this).data('tab');
            
            if (tab === 'ozet') {
                if (view === 'chart') {
                    $('#defterOzetReportSection').hide();
                    $('#defterOzetChartSection').fadeIn(300);
                    renderDefterOzetChart(_defterOzetData);
                } else {
                    $('#defterOzetChartSection').hide();
                    $('#defterOzetReportSection').fadeIn(300);
                }
            }
        });

        $(document).on('click', '.do-chart-type', function() {
            $('.do-chart-type').removeClass('active');
            $(this).addClass('active');
            const type = $(this).data('type');
            renderDefterOzetChart(_defterOzetData, type);
        });

        // ======= GRAFİK MANTİĞI =======
        let _defterOzetChartInstances = [];

        function renderDefterOzetChart(data, type = 'percent') {
            if (!data || !data.donemler || !data.bolge) return;
            
            const chartWrapper = document.querySelector("#defterOzetChart");
            if (!chartWrapper) return;

            // Önceki grafikleri temizle
            _defterOzetChartInstances.forEach(ch => { if (ch && ch.destroy) ch.destroy(); });
            _defterOzetChartInstances = [];
            chartWrapper.innerHTML = ''; 

            const donemler = data.donemler;
            const bolgeData = data.bolge;
            const regions = Object.keys(bolgeData).sort();

            if (type === 'compare') {
                const row = document.createElement('div');
                row.className = 'row g-4';
                chartWrapper.appendChild(row);

                donemler.forEach((d, dIdx) => {
                    const formattedDonem = String(d).substring(0, 4) + '/' + String(d).substring(4);
                    const chartId = 'chart_compare_' + d;
                    
                    const col = document.createElement('div');
                    let colClass = 'col-xl-4 col-lg-6';
                    if (donemler.length === 1) colClass = 'col-12';
                    else if (donemler.length === 2) colClass = 'col-lg-6';
                    col.className = colClass;

                    col.innerHTML = `
                        <div class="card border shadow-none mb-0">
                            <div class="card-header bg-light py-2">
                                <h6 class="text-center fw-bold text-primary mb-0" style="font-size: 0.9rem;">
                                    <i class="bx bx-calendar-event me-2"></i>Dönem: ${formattedDonem}
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <div id="${chartId}" style="min-height: 350px;"></div>
                            </div>
                        </div>
                    `;
                    row.appendChild(col);

                    // Verileri hazırla
                    const sToplam = { name: 'Toplam', data: [] };
                    const sOkunan = { name: 'Okunan', data: [] };
                    const sOkunmayan = { name: 'Okunmayan', data: [] };

                    regions.forEach(bName => {
                        const stat = (bolgeData[bName] && bolgeData[bName][d]) || { toplam_defter: 0, okunan_defter: 0, okunmayan_defter: 0 };
                        sToplam.data.push(stat.toplam_defter || 0);
                        sOkunan.data.push(stat.okunan_defter || 0);
                        sOkunmayan.data.push(stat.okunmayan_defter || 0);
                    });

                    const options = {
                        series: [sToplam, sOkunan, sOkunmayan],
                        chart: {
                            type: 'bar',
                            height: 350,
                            toolbar: { show: true },
                            fontFamily: 'inherit'
                        },
                        plotOptions: {
                            bar: {
                                horizontal: false,
                                columnWidth: '75%',
                                borderRadius: 3,
                                dataLabels: { position: 'top' }
                            }
                        },
                        dataLabels: {
                            enabled: true,
                            offsetY: -18,
                            style: { fontSize: '8px', colors: ["#304758"] }
                        },
                        stroke: { show: true, width: 2, colors: ['transparent'] },
                        xaxis: {
                            categories: regions,
                            labels: { rotate: -45, style: { fontSize: '9px' } }
                        },
                        yaxis: {
                            labels: { 
                                style: { fontSize: '10px' },
                                formatter: function(v) { return v.toFixed(0); } 
                            }
                        },
                        colors: ['#556ee6', '#34c38f', '#f1b44c'],
                        tooltip: { y: { formatter: function(v) { return v + " Defter"; } } },
                        grid: { padding: { top: 15 } },
                        legend: { position: 'bottom', horizontalAlign: 'center', fontSize: '10px', offsetY: 5 }
                    };

                    const chart = new ApexCharts(document.querySelector("#" + chartId), options);
                    chart.render();
                    _defterOzetChartInstances.push(chart);
                });
            } else {
                // Standart Tekli Grafik Görünümü (Oran, Toplam, Okunan, Okunmayan)
                const chartId = 'chart_single_view';
                const container = document.createElement('div');
                container.id = chartId;
                container.style.minHeight = '450px';
                chartWrapper.appendChild(container);

                const categories = donemler.map(d => String(d).substring(0, 4) + '/' + String(d).substring(4));
                const series = [];
                let chartColors = ['#556ee6', '#34c38f', '#f1b44c', '#f46a6a', '#50a5f1', '#2ca01c', '#e83e8c', '#6f42c1', '#fd7e14', '#20c997'];

                regions.forEach(bName => {
                    const bValues = [];
                    donemler.forEach(d => {
                        const stat = (bolgeData[bName] && bolgeData[bName][d]) || { toplam_defter: 0, okunan_defter: 0, okunmayan_defter: 0, oran: 0 };
                        if (type === 'percent') bValues.push(stat.oran || 0);
                        else if (type === 'toplam') bValues.push(stat.toplam_defter || 0);
                        else if (type === 'okunan') bValues.push(stat.okunan_defter || 0);
                        else if (type === 'okunmayan') bValues.push(stat.okunmayan_defter || 0);
                    });
                    series.push({ name: bName, data: bValues });
                });

                const options = {
                    series: series,
                    chart: { type: 'bar', height: 450, toolbar: { show: true }, fontFamily: 'inherit' },
                    plotOptions: { bar: { horizontal: false, columnWidth: '70%', borderRadius: 4, dataLabels: { position: 'top' } } },
                    dataLabels: {
                        enabled: true,
                        offsetY: -20,
                        style: { fontSize: '9px', colors: ["#5156be"] },
                        formatter: function(val) { return type === 'percent' ? val + "%" : val; }
                    },
                    stroke: { show: true, width: 2, colors: ['transparent'] },
                    xaxis: { categories: categories, labels: { rotate: -45, style: { fontSize: '10px' } } },
                    yaxis: {
                        title: { text: type === 'percent' ? 'Okuma Oranı (%)' : 'Defter Sayısı' },
                        max: type === 'percent' ? 110 : undefined,
                        labels: { formatter: function(v) { return v.toFixed(0); } }
                    },
                    grid: { padding: { top: 15 } },
                    fill: { opacity: 1 },
                    tooltip: { y: { formatter: function(val) { return type === 'percent' ? val + " %" : val + " Defter"; } } },
                    colors: chartColors,
                    legend: { position: 'bottom', horizontalAlign: 'center', offsetY: 8 }
                };

                const chart = new ApexCharts(document.querySelector("#" + chartId), options);
                chart.render();
                _defterOzetChartInstances.push(chart);
            }
        }

        // ======= AYARLARI KAYDET =======
        $('#btnSaveDefterLimit').on('click', function () {
            const limit = $('#defterOkunanLimit').val();
            const $btn = $(this);
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...');
            
            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: {
                    action: 'save-report-settings',
                    defter_bazli_rapor_alt_limit: limit
                },
                dataType: 'json',
                success: function (response) {
                    $btn.prop('disabled', false).html('Değişiklikleri Kaydet');
                    if (response.status === 'success') {
                        Swal.fire({
                            title: 'Başarılı',
                            text: 'Ayarlar kaydedildi.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                        $('#defterLimitAyarlarModal').modal('hide');
                    } else {
                        Swal.fire('Hata', response.message || 'Bir hata oluştu.', 'error');
                    }
                },
                error: function () {
                    $btn.prop('disabled', false).html('Değişiklikleri Kaydet');
                    Swal.fire('Hata', 'Sunucuyla iletişim kurulamadı.', 'error');
                }
            });
        });
    });
</script>

<?php if (\App\Service\Gate::allows('defter_bazli_rapor_alt_limit')): 
    $SettingsModel = new \App\Model\SettingsModel();
    $currentLimit = $SettingsModel->getAllSettingsAsKeyValue($_SESSION['firma_id'])['defter_bazli_rapor_alt_limit'] ?? 0;
?>
<!-- Ayarlar Modalı -->
<div class="modal fade" id="defterLimitAyarlarModal" tabindex="-1" aria-labelledby="defterLimitAyarlarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="defterLimitAyarlarModalLabel">
                    <i class="bx bx-cog me-1"></i> Rapor Ayarları
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label for="defterOkunanLimit" class="form-label fw-bold primary-text">Okuma Alt Sınırı</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="defterOkunanLimit" value="<?= (int)$currentLimit ?>" min="0">
                        <span class="input-group-text">Abone</span>
                    </div>
                    <div class="form-text mt-2">
                        <i class="bx bx-info-circle me-1"></i>
                        Defterdeki "Okunan abone sayısı" bu değerden az ise, ilgili dönemde o defter <b>okunmuş</b> kabul edilmeyecektir. (Örn: 8 yazarsanız 8'den az okunanlar okunmamış sayılır.)
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary px-4" id="btnSaveDefterLimit">Değişiklikleri Kaydet</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>