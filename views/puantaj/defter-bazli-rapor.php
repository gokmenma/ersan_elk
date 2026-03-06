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
$bolgeListStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT bolge FROM endeks_okuma WHERE firma_id = ? AND silinme_tarihi IS NULL AND bolge IS NOT NULL AND bolge != '' ORDER BY bolge");
$bolgeListStmt->execute([$firmaId]);
$bolgeListRaw = $bolgeListStmt->fetchAll(PDO::FETCH_COLUMN);
$bolgeOptions = ['' => 'Seçiniz...'];
foreach ($bolgeListRaw as $b) {
    $bolgeOptions[$b] = $b;
}

$defterListStmt = $EndeksOkuma->db->prepare("SELECT DISTINCT defter FROM endeks_okuma WHERE firma_id = ? AND silinme_tarihi IS NULL AND defter IS NOT NULL AND defter != '' ORDER BY defter");
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
    $title = "Abone Dönem Karşılaştırma";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

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
                                            id="btnExcelIndir">
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
                    <h4 class="mb-0 fw-bold bordro-text-heading" id="totalBolge" style="font-size: 1.25rem;">0</h4>
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
                    <h4 class="mb-0 fw-bold bordro-text-heading" id="totalKayit" style="font-size: 1.25rem;">0</h4>
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
                    <h4 class="mb-0 fw-bold bordro-text-heading" id="totalAbone" style="font-size: 1.25rem;">0</h4>
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
                    <h4 class="mb-0 fw-bold bordro-text-heading" id="totalDonem" style="font-size: 1.25rem;">0</h4>
                </div>
            </div>
        </div>
    </div>


    <!-- ======= RAPOR TABLOSU ======= -->
    <div class="row" id="reportSection" style="display: none;">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive" id="reportTableWrapper"
                        style="max-height: calc(100vh - 400px); overflow: auto;">
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
                            <span class="fw-medium text-dark"><i class="bx bx-user me-2 text-primary"></i>Abone</span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input col-toggle" type="checkbox" data-col="abone" checked>
                            </div>
                        </label>
                        <label
                            class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                            <span class="fw-medium text-dark"><i class="bx bx-show me-2 text-success"></i>Okunan</span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input col-toggle" type="checkbox" data-col="okunan" checked>
                            </div>
                        </label>
                        <label
                            class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                            <span class="fw-medium text-dark"><i class="bx bx-walk me-2 text-info"></i>Gidilen</span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input col-toggle" type="checkbox" data-col="gidilen" checked>
                            </div>
                        </label>
                        <label
                            class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 cursor-pointer">
                            <span class="fw-medium text-dark"><i
                                    class="bx bx-pie-chart-alt-2 me-2 text-warning"></i>Oran %</span>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input col-toggle" type="checkbox" data-col="oran" checked>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-primary btn-sm w-100 py-2 fw-bold" data-bs-dismiss="modal"
                        style="border-radius: 8px;">Değişiklikleri Uygula</button>
                </div>
            </div>
        </div>
    </div>
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

    .column-search::placeholder {
        color: #adb5bd;
        text-transform: none;
        font-weight: 400;
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
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .col-filter-btn:hover {
        background: #32394e;
        transform: scale(1.1);
        box-shadow: 0 3px 6px rgba(0,0,0,0.15);
    }

    .col-filter-btn.col-filter-active {
        background: #34c38f; /* Green for active */
        box-shadow: 0 0 0 2px rgba(52, 195, 143, 0.3);
    }

    .col-filter-popup {
        position: fixed;
        z-index: 9999;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15), 0 4px 10px rgba(0,0,0,0.08);
        padding: 12px;
        min-width: 200px;
        display: none;
    }

    .col-filter-popup.show {
        display: block;
        animation: filterPopupIn 0.15s ease-out;
    }

    @keyframes filterPopupIn {
        from { opacity: 0; transform: translateY(-4px); }
        to { opacity: 1; transform: translateY(0); }
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
        box-shadow: 0 10px 25px rgba(0,0,0,0.4);
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
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }

    /* Fixed columns */
    #comparisonTable .fix-col-1 {
        position: sticky;
        left: 0;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff) !important;
        min-width: 100px;
        max-width: 100px;
    }

    #comparisonTable .fix-col-2 {
        position: sticky;
        left: 100px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff) !important;
        min-width: 140px;
        max-width: 140px;
    }

    #comparisonTable .fix-col-3 {
        position: sticky;
        left: 240px;
        z-index: 10;
        background-color: var(--bs-card-bg, #fff) !important;
        min-width: 100px;
        max-width: 100px;
    }

    #comparisonTable thead .fix-col-1,
    #comparisonTable thead .fix-col-2,
    #comparisonTable thead .fix-col-3 {
        z-index: 40 !important;
        /* Higher than regular thead th */
        background: linear-gradient(rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1), rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1)), #ffffff !important;
        color: var(--bs-primary, #556ee6) !important;
    }

    /* Fixed columns in Row 2 (Inputs/Subheaders) also need top offset */
    #comparisonTable thead tr:nth-child(2) .fix-col-1,
    #comparisonTable thead tr:nth-child(2) .fix-col-2,
    #comparisonTable thead tr:nth-child(2) .fix-col-3 {
        top: 25px !important;
    }

    [data-bs-theme="dark"] #comparisonTable thead .fix-col-1,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-2,
    [data-bs-theme="dark"] #comparisonTable thead .fix-col-3 {
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
        font-size: 10px !important;
        font-weight: 600 !important;
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

    /* Sticky Footer */
    #comparisonTable tfoot {
        position: sticky;
        bottom: 0;
        z-index: 25;
    }

    #comparisonTable tfoot th {
        background: #f8f9fa !important;
        font-weight: 800 !important;
        border-top: 2px solid var(--bs-primary, #dee2e6) !important;
        color: var(--bs-primary, #2a3042) !important;
        z-index: 24;
    }

    #comparisonTable tfoot .fix-col-1,
    #comparisonTable tfoot .fix-col-2,
    #comparisonTable tfoot .fix-col-3 {
        z-index: 35;
        background: #f8f9fa !important;
        color: var(--bs-primary, #2a3042) !important;
    }

    [data-bs-theme="dark"] #comparisonTable tfoot th,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-1,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-2,
    [data-bs-theme="dark"] #comparisonTable tfoot .fix-col-3 {
        background: #32394e !important;
        color: var(--bs-primary, #eff2f7) !important;
        border-top: 2px solid var(--bs-primary) !important;
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
    .fullscreen-mode #reportTableWrapper {
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
    [data-bs-theme="dark"] .fix-col-3 {
        background-color: var(--bs-card-bg, #282f36) !important;
    }

    [data-bs-theme="dark"] #comparisonTable tbody tr:nth-child(even) td {
        background-color: rgba(255, 255, 255, 0.02);
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
        let _searchFilters = { ilce_tipi: '', bolge: '', defter: '' };
        let _searchTimeout;

        function renderTable(data, donemler, keep) {
            _tableData = data;
            _tableDonemler = donemler;

            if (keep !== true) {
                _sortColumn = null;
                _sortDirection = 'asc';
                _searchFilters = { ilce_tipi: '', bolge: '', defter: '' };
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
                if (!(ilceMatch && bolgeMatch && defterMatch)) return false;

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
                        case '>':  if (!(cellVal > filterVal)) return false; break;
                        case '<':  if (!(cellVal < filterVal)) return false; break;
                        case '>=': if (!(cellVal >= filterVal)) return false; break;
                        case '<=': if (!(cellVal <= filterVal)) return false; break;
                        case '=':  if (!(Math.abs(cellVal - filterVal) < 0.01)) return false; break;
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

            let html = '<table class="table table-bordered table-sm mb-0" id="comparisonTable">';

            // ======= THEAD =======
            html += '<thead>';

            // Row 1: Fixed labels + Periods
            html += '<tr class="main-headers-row">';
            html += `<th class="fix-col-1 sortable-header" data-sort-col="ilce_tipi">İLÇE TİPİ${sortIcon('ilce_tipi')}</th>`;
            html += `<th class="fix-col-2 sortable-header" data-sort-col="bolge">BÖLGE${sortIcon('bolge')}</th>`;
            html += `<th class="fix-col-3 sortable-header" data-sort-col="defter">DEFTER${sortIcon('defter')}</th>`;

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

            sortedData.forEach(function (row) {
                html += '<tr>';
                html += `<td class="fix-col-1 text-start fw-medium">${row.ilce_tipi}</td>`;
                html += `<td class="fix-col-2 text-start fw-medium">${row.bolge}</td>`;
                html += `<td class="fix-col-3 text-start fw-medium">${row.defter}</td>`;

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
            html += '</tbody>';

            // ======= TFOOT =======
            html += '<tfoot>';
            html += '<tr>';
            html += '<th class="fix-col-1 text-center" colspan="3">GENEL TOPLAM</th>';

            donemler.forEach(function (donem, idx) {
                const totals = colTotals[donem];
                const isLast = idx === donemler.length - 1;
                const grandOran = totals.abone > 0
                    ? ((totals.okunan / totals.abone) * 100).toFixed(1)
                    : 0;

                let oranClass = 'oran-low';
                if (grandOran >= 70) oranClass = 'oran-high';
                else if (grandOran >= 50) oranClass = 'oran-medium';

                if (_visibleColumns.abone)
                    html += `<th>${totals.abone.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.okunan)
                    html += `<th>${totals.okunan.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.gidilen)
                    html += `<th class="gidilen-cell">${totals.gidilen.toLocaleString('tr-TR')}</th>`;
                if (_visibleColumns.oran)
                    html += `<th class="${oranClass} ${isLast ? 'period-end' : ''}">${grandOran}%</th>`;
            });
            html += '</tr>';
            html += '</tfoot>';

            html += '</table>';

            $('#reportTableWrapper').html(html);

            // Bind sort click handlers
            $('#comparisonTable').on('click', '.sortable-header', function (e) {
                // Filtre butonuna tıklandıysa sıralamayı tetikleme
                if ($(e.target).closest('.col-filter-btn').length) return;
                const col = $(this).data('sort-col');
                if (_sortColumn === col) {
                    _sortDirection = _sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    _sortColumn = col;
                    _sortDirection = 'asc';
                }
                renderTable(_tableData, _tableDonemler, true);
            });

            // Bind filter button click handlers directly to prevent sort bubbling
            $('#comparisonTable .col-filter-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Trigger the existing handler via document delegation
                // Or just call the show logic if it was a function.
                // Since it's document delegated, we just let it bubble to document?
                // Wait, if I use stopPropagation, it won't reach document!
                // So I MUST open the popup here.
                
                openFilterPopup(this);
            });
        }

        // ======= FILTER POPUP OPENER =======
        function openFilterPopup(btn) {
            const colKey = $(btn).data('filter-col');
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
            const fieldNames = { abone: 'Abone', okunan: 'Okunan', gidilen: 'Gidilen', oran: 'Oran %' };
            const formatted = donem.substring(0, 4) + '/' + donem.substring(4);
            $('#colFilterPopupTitle').text(formatted + ' – ' + (fieldNames[field] || field));

            // Mevcut filtreyi doldur
            const existing = _numericFilters[colKey];
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

        // ======= ARAMA EVENT HANDLER =======
        $(document).on('input', '.column-search', function () {
            const col = $(this).data('col');
            const val = $(this).val();
            const id = $(this).attr('id');
            const pos = this.selectionStart;

            _searchFilters[col] = val;

            clearTimeout(_searchTimeout);
            _searchTimeout = setTimeout(function () {
                renderTable(_tableData, _tableDonemler, true);

                // Focusu geri al
                if (id) {
                    const input = document.getElementById(id);
                    if (input) {
                        input.focus();
                        input.setSelectionRange(pos, pos);
                    }
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

        // Filtre butonuna tıklama (delegated handler kept for safety, but primary is now direct in renderTable)
        $(document).on('click', '.col-filter-btn', function (e) {
            e.stopPropagation();
            e.preventDefault();
            openFilterPopup(this);
        });

        // Uygula
        $(document).on('click', '#colFilterApply', function () {
            if (!_activeFilterCol) return;
            const op = $('#colFilterOperator').val();
            const val = $('#colFilterValue').val();

            if (op && val !== '') {
                _numericFilters[_activeFilterCol] = { operator: op, value: val };
            } else {
                delete _numericFilters[_activeFilterCol];
            }

            $('#colFilterPopup').removeClass('show');
            _activeFilterCol = null;
            renderTable(_tableData, _tableDonemler, true);
        });

        // Temizle
        $(document).on('click', '#colFilterClear', function () {
            if (_activeFilterCol) {
                delete _numericFilters[_activeFilterCol];
            }
            $('#colFilterOperator').val('');
            $('#colFilterValue').val('');
            $('#colFilterPopup').removeClass('show');
            _activeFilterCol = null;
            renderTable(_tableData, _tableDonemler, true);
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

        // ======= EXCEL İNDİR =======
        $('#btnExcelIndir').on('click', function () {
            const table = document.getElementById('comparisonTable');
            if (!table) {
                Swal.fire('Uyarı', 'Lütfen önce raporu getirin.', 'warning');
                return;
            }

            const htmlContent = table.outerHTML;
            const excelHtml = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>td,th{mso-number-format:'\\@';}</style></head><body>${htmlContent}</body></html>`;
            const blob = new Blob(['\ufeff', excelHtml], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = 'abone_donem_karsilastirma.xls';
            link.href = url;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 100);
        });

        // Enter tuşu ile rapor getir
        $('#filterDonemler, #baslangicDonem, #bitisDonem').on('keypress', function (e) {
            if (e.which === 13) {
                loadReport();
            }
        });

        // ======= TAM EKRAN =======
        $(document).on('click', '#btnTamEkran', function () {
            const elem = document.getElementById('reportSection');
            if (!elem) return;
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => console.log('Fullscreen error:', err));
                $(this).html('<i class="mdi mdi-fullscreen-exit me-1"></i>Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                $(this).html('<i class="mdi mdi-fullscreen me-1"></i>Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#btnTamEkran').html('<i class="mdi mdi-fullscreen me-1"></i>Tam Ekran');
                $('#reportSection').removeClass('fullscreen-mode');
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
    });
</script>