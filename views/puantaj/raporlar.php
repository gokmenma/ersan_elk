<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\TanimlamalarModel;
use App\Model\EndeksOkumaModel;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$Tanimlamalar = new TanimlamalarModel();
$EndeksOkuma = new EndeksOkumaModel();
$Puantaj = new PuantajModel();
$Personel = new PersonelModel();
$Settings = new \App\Model\SettingsModel();

$reportSettings = $Settings->getAllSettingsAsKeyValue($_SESSION['firma_id'] ?? null);

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$personel_id = $_GET['personel_id'] ?? '';
$region = $_GET['region'] ?? '';
$defter = $_GET['defter'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$filterType = $_GET['filter_type'] ?? 'period';
$activeTab = $_GET['tab'] ?? 'okuma';

$yearOptions = [];
for ($y = date('Y'); $y >= 2020; $y--) {
    $yearOptions[$y] = $y;
}

$monthOptions = [];
for ($m = 1; $m <= 12; $m++) {
    $m_val = str_pad($m, 2, '0', STR_PAD_LEFT);
    $monthOptions[$m_val] = Date::monthName($m_val);
}

$targetDate = date('Y-m-d');
if (isset($filterType)) {
    if ($filterType === 'range' && !empty($endDate)) {
        $targetDate = date('Y-m-d', strtotime($endDate));
    } elseif ($filterType === 'period' && !empty($month) && !empty($year)) {
        $m_val = str_pad($month, 2, '0', STR_PAD_LEFT);
        $targetDate = date('Y-m-t', strtotime("$year-$m_val-01"));
    }
}
$personelList = $Personel->all(false, 'puantaj', $targetDate);
$personelOptions = ['' => 'Tüm Personeller'];
foreach ($personelList as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}

$regionList = $Tanimlamalar->getFilteredEkipBolgeleri();
$regionOptions = ['' => 'Tüm Bölgeler'];
foreach ($regionList as $r) {
    $regionOptions[$r] = $r;
}

$defterList = $Tanimlamalar->getDefterKodlari();
$defterOptions = ['' => 'Tüm Defterler'];
foreach ($defterList as $d) {
    $defterOptions[$d] = $d;
}

$kesmeWorkTypes = $Tanimlamalar->getIsTurleriByRaporTuru('kesme');
$kesmeIsTurleriOptions = [];
foreach ($kesmeWorkTypes as $wt) {
    if (!empty($wt->is_emri_sonucu)) {
        $kesmeIsTurleriOptions[$wt->is_emri_sonucu] = $wt->is_emri_sonucu;
    }
}
if (!isset($kesmeIsTurleriOptions['Ödeme Yaptırıldı'])) {
    $kesmeIsTurleriOptions['Ödeme Yaptırıldı'] = 'Ödeme Yaptırıldı';
}

?>
<style>
    :root {
        --reg-color-0: rgb(227, 242, 253);
        --reg-color-1: rgb(243, 229, 245);
        --reg-color-2: rgb(232, 245, 233);
        --reg-color-3: rgb(255, 243, 224);
        --reg-color-4: rgb(255, 235, 238);
        --reg-color-5: rgb(224, 247, 250);
        --reg-color-6: rgb(252, 228, 236);
        --reg-color-7: rgb(241, 248, 233);
        --reg-color-8: rgb(255, 248, 225);
        --reg-color-9: rgb(237, 231, 246);
    }
    html[data-bs-theme="dark"],
    html[data-theme-mode="dark"],
    [data-bs-theme="dark"],
    [data-theme-mode="dark"] {
        --reg-color-0: rgba(30, 41, 59, 0.4);
        --reg-color-1: rgba(88, 28, 135, 0.2);
        --reg-color-2: rgba(20, 83, 45, 0.2);
        --reg-color-3: rgba(120, 53, 4, 0.2);
        --reg-color-4: rgba(153, 27, 27, 0.2);
        --reg-color-5: rgba(21, 94, 117, 0.2);
        --reg-color-6: rgba(131, 24, 67, 0.2);
        --reg-color-7: rgba(101, 163, 13, 0.2);
        --reg-color-8: rgba(146, 64, 14, 0.2);
        --reg-color-9: rgba(107, 33, 168, 0.2);
    }

    /* Kaçak Kontrol Repeater Row Theme Support */
    .kacak-repeater-row {
        background-color: #fcfcfd !important;
        border-color: #e2e8f0 !important;
        transition: all 0.2s ease;
    }
    [data-bs-theme="dark"] .kacak-repeater-row {
        background-color: #2e3548 !important;
        border-color: #3b445e !important;
    }

    .accordion-button.collapsed~.only-show-open {
        display: none !important;
    }

    .filter-summary-badge {
        display: flex;
        align-items: center;
        background: #2a2f34;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        border: 1px solid #323940;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .filter-summary-badge .badge-label {
        color: #adb5bd;
        margin-right: 4px;
        font-weight: 600;
    }

    .filter-summary-badge .badge-value {
        color: #ffffff;
        font-weight: 700;
    }

    .btn-clear-filter {
        background: none;
        border: none;
        padding: 0 0 0 4px;
        color: #fa5f7e;
        cursor: pointer;
        display: flex;
        align-items: center;
    }

    .btn-clear-filter:hover {
        color: #e83e8c;
    }

    .filter-type-switcher {
        display: inline-flex;
        background: #f1f3f7;
        padding: 3px;
        border-radius: 8px;
        border: 1px solid #e2e5e9;
    }

    .filter-type-switcher .form-check {
        padding-left: 0;
        margin: 0;
    }

    .filter-type-switcher .form-check-input {
        display: none;
    }

    .filter-type-switcher .form-check-label {
        padding: 4px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 11px;
        font-weight: 600;
        transition: all 0.2s;
        margin-bottom: 0;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-type-switcher .form-check-input:checked+.form-check-label {
        background: #fff;
        color: #5156be;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
    }

    /* Puantaj Raporlar Tablo Hücre Kenarlıkları (Hafif Siyah) */
    #reportContent table th,
    #reportContent table td,
    #compareContent table th,
    #compareContent table td,
    #raporTable th,
    #raporTable td,
    #compareTable th,
    #compareTable td,
    .table-responsive table th,
    .table-responsive table td {
        border-color: rgba(0, 0, 0, 0.18) !important;
    }

    [data-bs-theme="dark"] #reportContent table th,
    [data-bs-theme="dark"] #reportContent table td,
    [data-bs-theme="dark"] #compareContent table th,
    [data-bs-theme="dark"] #compareContent table td,
    [data-bs-theme="dark"] #raporTable th,
    [data-bs-theme="dark"] #raporTable td,
    [data-bs-theme="dark"] #compareTable th,
    [data-bs-theme="dark"] #compareTable td,
    [data-theme-mode="dark"] #reportContent table th,
    [data-theme-mode="dark"] #reportContent table td,
    [data-theme-mode="dark"] #compareContent table th,
    [data-theme-mode="dark"] #compareContent table td,
    [data-theme-mode="dark"] #raporTable th,
    [data-theme-mode="dark"] #raporTable td,
    [data-theme-mode="dark"] #compareTable th,
    [data-theme-mode="dark"] #compareTable td {
        border-color: #32394e !important;
    }
</style>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "Özet Raporlar";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- ========== ANA MOD SEKMELERİ ========== -->
    <div class="d-flex align-items-center gap-2 mb-3" id="mainModeTabs">
        <button
            class="btn btn-sm fw-bold d-flex align-items-center gap-1 main-mode-btn <?= $activeTab !== 'karsilastirma' ? 'active' : '' ?>"
            data-mode="rapor" id="btnModeRapor">
            <i class="bx bx-table"></i> Özet Raporlar
        </button>
        <button
            class="btn btn-sm fw-bold d-flex align-items-center gap-1 main-mode-btn <?= $activeTab === 'karsilastirma' ? 'active' : '' ?>"
            data-mode="karsilastirma" id="btnModeKarsilastirma">
            <i class="bx bx-git-compare"></i> Karşılaştırma
        </button>
    </div>

    <!-- =================== ÖZET RAPORLAR İÇERİĞİ =================== -->
    <div id="modeRaporContent" <?= $activeTab === 'karsilastirma' ? 'style="display:none"' : '' ?>>

        <div class="row mb-3">
            <div class="col-12">
                <form method="GET" action="" id="filterForm">
                    <div class="card">
                        <div class="card-body p-2">
                            <div class="accordion" id="filterAccordion">
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header position-relative" id="headingOne">
                                        <button class="accordion-button collapsed py-2" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseOne"
                                            aria-expanded="false" aria-controls="collapseOne">
                                            <i class="bx bx-filter-alt me-2 text-primary"></i> Filtreleme Seçenekleri
                                        </button>

                                        <div class="only-show-open animate__animated animate__fadeIn position-absolute"
                                            style="left: 210px; top: 50%; transform: translateY(-50%); z-index: 5;">
                                            <div class="filter-type-switcher">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_type"
                                                        id="typePeriod" value="period" <?= $filterType === 'period' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="typePeriod">
                                                        <i class="bx bx-calendar-event"></i> Dönem Bazlı
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="filter_type"
                                                        id="typeRange" value="range" <?= $filterType === 'range' ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="typeRange">
                                                        <i class="bx bx-calendar-week"></i> Tarih Aralığı
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="filterSummary" class="d-none d-md-flex gap-2 position-absolute"
                                            style="right: 60px; top: 50%; transform: translateY(-50%); z-index: 5;">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                    </h2>
                                </div>
                            </div>

                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                                data-bs-parent="#filterAccordion">
                                <div class="accordion-body pt-3 pb-2">
                                    <div class="row g-3">
                                        <div class="col-md-2 filter-group-period" <?= $filterType === 'range' ? 'style="display:none"' : '' ?>>
                                            <?php echo Form::FormSelect2("year", $yearOptions, $year, "Yıl Seçiniz", "bx bx-calendar-event", "key", "", "form-select select2"); ?>
                                        </div>
                                        <div class="col-md-2 filter-group-period" <?= $filterType === 'range' ? 'style="display:none"' : '' ?>>
                                            <?php echo Form::FormSelect2("month", $monthOptions, $month, "Ay Seçiniz", "bx bx-calendar-check", "key", "", "form-select select2"); ?>
                                        </div>

                                        <div class="col-md-2 filter-group-range" <?= $filterType === 'period' ? 'style="display:none"' : '' ?>>
                                            <?php
                                            $startDateFormatted = !empty($startDate) ? date('d.m.Y', strtotime($startDate)) : '';
                                            echo Form::FormFloatInput("text", "start_date", $startDateFormatted, "gg.aa.yyyy", "Başlangıç Tarihi", "bx bx-calendar", "form-control flatpickr");
                                            ?>
                                        </div>
                                        <div class="col-md-2 filter-group-range" <?= $filterType === 'period' ? 'style="display:none"' : '' ?>>
                                            <?php
                                            $endDateFormatted = !empty($endDate) ? date('d.m.Y', strtotime($endDate)) : '';
                                            echo Form::FormFloatInput("text", "end_date", $endDateFormatted, "gg.aa.yyyy", "Bitiş Tarihi", "bx bx-calendar", "form-control flatpickr");
                                            ?>
                                        </div>

                                        <div class="col-md-2">
                                            <?php echo Form::FormSelect2("personel_id", $personelOptions, $personel_id, "Personel", "bx bx-user", "key", "", "form-control-sm select2"); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <?php echo Form::FormSelect2("region", $regionOptions, $region, "Bölge", "bx bx-map-pin", "key", "", "form-control-sm select2"); ?>
                                        </div>
                                        <div class="col-md-2">
                                            <?php echo Form::FormSelect2("defter", $defterOptions, $defter, "Defter", "bx bx-book", "key", "", "form-control-sm select2"); ?>
                                        </div>

                                        <div class="col-md-2 d-flex align-items-end">
                                            <div
                                                class="action-button-container d-flex align-items-center border rounded shadow-sm p-1 gap-1 w-100 bg-white">
                                                <button type="submit"
                                                    class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                                    <i class="mdi mdi-magnify me-1"></i> Sorgula
                                                </button>
                                                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                                <button type="button"
                                                    class="btn btn-link btn-sm text-secondary text-decoration-none px-2"
                                                    id="btnClearFilters">
                                                    <i class="mdi mdi-filter-remove"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <ul class="nav nav-tabs nav-tabs-custom nav-success mb-0" role="tablist" id="raporTabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($activeTab === 'okuma' || !in_array($activeTab, ['okuma', 'kesme', 'sokme_takma', 'muhurleme', 'kacakkontrol'])) ? 'active' : '' ?>"
                        href="javascript:void(0);" data-tab="okuma">
                        <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                        <span class="d-none d-sm-block">Endeks Okuma</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'kesme' ? 'active' : '' ?>" href="javascript:void(0);"
                        data-tab="kesme">
                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                        <span class="d-none d-sm-block">Kesme/Açma İşlm.</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'sokme_takma' ? 'active' : '' ?>" href="javascript:void(0);"
                        data-tab="sokme_takma">
                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                        <span class="d-none d-sm-block">Sayaç Sökme Takma</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'muhurleme' ? 'active' : '' ?>" href="javascript:void(0);"
                        data-tab="muhurleme">
                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                        <span class="d-none d-sm-block">Mühürleme</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $activeTab === 'kacakkontrol' ? 'active' : '' ?>" href="javascript:void(0);"
                        data-tab="kacakkontrol">
                        <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                        <span class="d-none d-sm-block">Kaçak Kontrol</span>
                    </a>
                </li>
            </ul>
            <div class="action-button-container d-flex align-items-center border rounded shadow-sm p-1 gap-1">
                <button type="button"
                    class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                    id="btnFullScreen">
                    <i class="mdi mdi-fullscreen fs-5 me-1"></i> Tam Ekran
                </button>
                <div class="vr mx-1 vr-online-sorgula <?= in_array($activeTab, ['okuma', 'kesme', 'sokme_takma', 'muhurleme']) ? '' : 'd-none' ?>"
                    style="height: 25px; align-self: center;"></div>
                <button type="button"
                    class="btn btn-link btn-sm text-info text-decoration-none px-2 align-items-center btn-online-sorgula <?= $activeTab === 'okuma' ? 'd-flex' : 'd-none' ?>"
                    id="btnOnlineSorgulaEndeks" data-bs-toggle="modal" data-bs-target="#importOnlineIcmalRaporuModal">
                    <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Endeks Sorgula
                </button>
                <button type="button"
                    class="btn btn-link btn-sm text-info text-decoration-none px-2 align-items-center btn-online-sorgula <?= $activeTab === 'kesme' ? 'd-flex' : 'd-none' ?>"
                    id="btnOnlineSorgulaPuantaj" data-bs-toggle="modal" data-bs-target="#importOnlinePuantajModal">
                    <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Puantaj Sorgula
                </button>
                <button type="button"
                    class="btn btn-link btn-sm text-info text-decoration-none px-2 align-items-center btn-online-sorgula <?= $activeTab === 'sokme_takma' ? 'd-flex' : 'd-none' ?>"
                    id="btnOnlineSorgulaSayac" data-bs-toggle="modal" data-bs-target="#importOnlineSayacModal">
                    <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Sayaç Sorgula
                </button>
                <button type="button"
                    class="btn btn-link btn-sm text-info text-decoration-none px-2 align-items-center btn-online-sorgula <?= $activeTab === 'muhurleme' ? 'd-flex' : 'd-none' ?>"
                    id="btnOnlineSorgulaMuhurleme" data-bs-toggle="modal" data-bs-target="#importOnlineMuhurlemeModal">
                    <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Mühürleme Sorgula
                </button>
                <div class="vr mx-1 vr-kacak-action <?= $activeTab === 'kacakkontrol' ? '' : 'd-none' ?>"
                    style="height: 25px; align-self: center;"></div>
                <button type="button"
                    class="btn btn-link btn-sm text-primary text-decoration-none px-2 align-items-center btn-kacak-action <?= $activeTab === 'kacakkontrol' ? 'd-flex' : 'd-none' ?>"
                    id="btnNewKacak">
                    <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni Ekle
                </button>

                <div class="dropdown ms-2">
                    <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="islemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                        <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="islemlerDropdown">
                        <li>
                            <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportExcel">
                                <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item align-items-center text-primary fw-medium btn-kacak-action <?= $activeTab === 'kacakkontrol' ? 'd-flex' : 'd-none' ?>" type="button" data-bs-toggle="modal" data-bs-target="#importKacakModal">
                                <i class="mdi mdi-upload fs-5 me-2"></i> Excel Yükle
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row" id="reportCardRow">
            <div class="col-12">
                <div id="kacakHelpInfo" class="alert alert-soft-primary alert-dismissible fade show mb-2 p-2"
                    role="alert" style="display: none;">
                    <div class="d-flex align-items-center">
                        <i class="bx bxs-info-circle fs-5 me-2"></i>
                        <div>
                            <strong>İpucu:</strong> Kaçak Kontrol tablosunda gün kutucuklarına <strong>çift
                                tıklayarak</strong> o tarih ve o ekip için hızlıca yeni kayıt oluşturabilirsiniz.
                        </div>
                    </div>
                    <button type="button" class="btn-close p-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div class="card">
                    <div class="card-body" id="reportContent">
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2">Rapor hazırlanıyor...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /modeRaporContent -->

    <!-- =================== KARŞILAŞTIRMA İÇERİĞİ =================== -->
    <div id="modeKarsilastirmaContent" <?= $activeTab === 'karsilastirma' ? '' : 'style="display:none"' ?>>

        <!-- Dönem Seçici (üstte - filtreleme gibi) -->
        <div class="row mb-3" id="comparePeriodRow">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3 px-4">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bx bx-calendar-event text-primary fs-5"></i>
                                <span class="fw-semibold text-muted" style="font-size: 12px;">Karşılaştırma
                                    Dönemleri:</span>
                            </div>
                            <div id="selectedPeriods" class="d-flex gap-2 flex-wrap"></div>
                            <div class="d-flex align-items-center gap-2">
                                <select id="addPeriodYear" class="form-select form-select-sm" style="width: 90px;">
                                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select id="addPeriodMonth" class="form-select form-select-sm" style="width: 120px;">
                                    <?php for ($m = 1; $m <= 12; $m++):
                                        $m_val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?= $m_val ?>" <?= $m_val == date('m') ? 'selected' : '' ?>>
                                            <?= $monthOptions[$m_val] ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-1"
                                    id="btnAddPeriod">
                                    <i class="bx bx-plus"></i> Ekle
                                </button>
                            </div>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearPeriods">
                                    <i class="bx bx-trash me-1"></i>Temizle
                                </button>
                                <button type="button" class="btn btn-success btn-sm fw-bold" id="btnCompare">
                                    <i class="bx bx-git-compare me-1"></i>Karşılaştır
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Karşılaştırma Alt Sekmeleri (altta - rapor sekmeleri gibi) -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <ul class="nav nav-tabs nav-tabs-custom nav-success mb-0" role="tablist" id="compareTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="javascript:void(0);" data-tab="okuma">
                        <span class="d-none d-sm-block">Endeks Okuma</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);" data-tab="kesme">
                        <span class="d-none d-sm-block">Kesme/Açma</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);" data-tab="sokme_takma">
                        <span class="d-none d-sm-block">Sayaç Sökme Takma</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);" data-tab="muhurleme">
                        <span class="d-none d-sm-block">Mühürleme</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0);" data-tab="kacakkontrol">
                        <span class="d-none d-sm-block">Kaçak Kontrol</span>
                    </a>
                </li>
            </ul>
            <div class="action-button-container d-flex align-items-center border rounded shadow-sm p-1 gap-1">
                <button type="button"
                    class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                    id="btnCompareFullScreen">
                    <i class="mdi mdi-fullscreen fs-5 me-1"></i> Tam Ekran
                </button>
            </div>
        </div>

        <!-- Karşılaştırma Rapor İçeriği -->
        <div class="row" id="compareCardRow">
            <div class="col-12">
                <div class="card">
                    <div class="card-body" id="compareContent">
                        <div class="text-center p-5 text-muted">
                            <i class="bx bx-git-compare" style="font-size: 48px; opacity: 0.3;"></i>
                            <p class="mt-2">Dönemleri seçip <strong>Karşılaştır</strong> butonuna basın.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /modeKarsilastirmaContent -->

</div>

<script>
    $(document).ready(function () {
        let currentTab = '<?= $activeTab ?>';
        let currentYear = '<?= $year ?>';
        let currentMonth = '<?= $month ?>';
        let currentPersonelId = '<?= $personel_id ?>';
        let currentRegion = '<?= $region ?>';
        let currentDefter = '<?= $defter ?>';
        let currentStartDate = '<?= $startDate ?>';
        let currentEndDate = '<?= $endDate ?>';
        let currentFilterType = '<?= $filterType ?>';

        const STORAGE_KEY = 'raporlar_filters';

        const saveFiltersToStorage = function () {
            const filters = {
                tab: currentTab,
                year: currentYear,
                month: currentMonth,
                personel_id: currentPersonelId,
                region: currentRegion,
                defter: currentDefter,
                start_date: currentStartDate,
                end_date: currentEndDate,
                filter_type: currentFilterType
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filters));
        };

        const loadFiltersFromStorage = function () {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                const filters = JSON.parse(saved);
                const urlParams = new URLSearchParams(window.location.search);

                // Sadece URL'de olmayan parametreleri storage'dan alalım
                if (!urlParams.has('tab')) currentTab = filters.tab || currentTab;
                if (!urlParams.has('year')) currentYear = filters.year || currentYear;
                if (!urlParams.has('month')) currentMonth = filters.month || currentMonth;
                if (!urlParams.has('personel_id')) currentPersonelId = filters.personel_id || currentPersonelId;
                if (!urlParams.has('region')) currentRegion = filters.region || currentRegion;
                if (!urlParams.has('defter')) currentDefter = filters.defter || currentDefter;
                if (!urlParams.has('start_date')) currentStartDate = filters.start_date || currentStartDate;
                if (!urlParams.has('end_date')) currentEndDate = filters.end_date || currentEndDate;
                if (!urlParams.has('filter_type')) currentFilterType = filters.filter_type || currentFilterType;

                // UI bileşenlerini güncelle
                $(`#raporTabs .nav-link[data-tab="${currentTab}"]`).addClass('active').parent().siblings().find('.nav-link').removeClass('active');
                $('select[name="year"]').val(currentYear).trigger('change.select2');
                $('select[name="month"]').val(currentMonth).trigger('change.select2');
                $('select[name="personel_id"]').val(currentPersonelId).trigger('change.select2');
                $('select[name="region"]').val(currentRegion).trigger('change.select2');
                $('select[name="defter"]').val(currentDefter).trigger('change.select2');
                $('input[name="start_date"]').val(currentStartDate);
                $('input[name="end_date"]').val(currentEndDate);
                $(`input[name="filter_type"][value="${currentFilterType}"]`).prop('checked', true).trigger('change');
            }
        };

        window.loadReport = function () {
            $('#reportContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Rapor hazırlanıyor...</p></div>');
            updateFilterSummary();

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: {
                    action: 'get-report-table',
                    tab: currentTab,
                    year: currentYear,
                    month: currentMonth,
                    personel_id: currentPersonelId,
                    region: currentRegion,
                    defter: currentDefter,
                    start_date: currentStartDate,
                    end_date: currentEndDate,
                    filter_type: currentFilterType
                },
                success: function (html) {
                    $('#reportContent').html(html);
                    updateUrl();
                    saveFiltersToStorage();

                    // Trigger height adjustment after content is loaded
                    setTimeout(adjustTableHeight, 100);

                    // Show help info if it's kacak tab
                    if (currentTab === 'kacakkontrol') {
                        $('#kacakHelpInfo').show();
                    } else {
                        $('#kacakHelpInfo').hide();
                    }
                },
                error: function () {
                    $('#reportContent').html('<div class="alert alert-danger">Rapor yüklenirken bir hata oluştu.</div>');
                }
            });
        };

        const updateFilterSummary = function () {
            let summary = '';
            const yearText = $('select[name="year"] option:selected').text();
            const monthText = $('select[name="month"] option:selected').text();
            const personelText = $('select[name="personel_id"] option:selected').text();
            const regionText = $('select[name="region"] option:selected').text();
            const defterText = $('select[name="defter"] option:selected').text();
            const startDate = $('input[name="start_date"]').val();
            const endDate = $('input[name="end_date"]').val();
            const filterType = $('input[name="filter_type"]:checked').val();

            if (filterType === 'period') {
                if (yearText) summary += `<div class="filter-summary-badge"><span class="badge-label">Yıl:</span><span class="badge-value">${yearText}</span></div>`;
                if (monthText) summary += `<div class="filter-summary-badge"><span class="badge-label">Ay:</span><span class="badge-value">${monthText}</span></div>`;
            } else {
                if (startDate) {
                    summary += `<div class="filter-summary-badge"><span class="badge-label">Başl:</span><span class="badge-value">${startDate}</span><button type="button" class="btn-clear-filter" data-filter="start_date"><i class="bx bx-x"></i></button></div>`;
                }
                if (endDate) {
                    summary += `<div class="filter-summary-badge"><span class="badge-label">Bitiş:</span><span class="badge-value">${endDate}</span><button type="button" class="btn-clear-filter" data-filter="end_date"><i class="bx bx-x"></i></button></div>`;
                }
            }

            if (currentPersonelId && currentPersonelId !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Pers:</span><span class="badge-value">${personelText}</span><button type="button" class="btn-clear-filter" data-filter="personel_id"><i class="bx bx-x"></i></button></div>`;
            }

            if (currentRegion && currentRegion !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Bölge:</span><span class="badge-value">${regionText}</span><button type="button" class="btn-clear-filter" data-filter="region"><i class="bx bx-x"></i></button></div>`;
            }

            if (currentDefter && currentDefter !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Defter:</span><span class="badge-value">${defterText}</span><button type="button" class="btn-clear-filter" data-filter="defter"><i class="bx bx-x"></i></button></div>`;
            }

            $('#filterSummary').html(summary);
        };

        $(document).on('click', '.btn-clear-filter', function (e) {
            e.stopPropagation();
            const filterType = $(this).data('filter');
            if (filterType === 'personel_id') {
                currentPersonelId = '';
                $('select[name="personel_id"]').val('').trigger('change');
            } else if (filterType === 'region') {
                currentRegion = '';
                $('select[name="region"]').val('').trigger('change');
            } else if (filterType === 'defter') {
                currentDefter = '';
                $('select[name="defter"]').val('').trigger('change');
            } else if (filterType === 'start_date') {
                currentStartDate = '';
                $('input[name="start_date"]').val('');
            } else if (filterType === 'end_date') {
                currentEndDate = '';
                $('input[name="end_date"]').val('');
            }
            loadReport();
        });

        const updateUrl = function () {
            const url = new URL(window.location);
            url.searchParams.set('tab', currentTab);
            url.searchParams.set('year', currentYear);
            url.searchParams.set('month', currentMonth);
            if (currentPersonelId) url.searchParams.set('personel_id', currentPersonelId); else url.searchParams.delete('personel_id');
            if (currentRegion) url.searchParams.set('region', currentRegion); else url.searchParams.delete('region');
            if (currentDefter) url.searchParams.set('defter', currentDefter); else url.searchParams.delete('defter');
            if (currentStartDate) url.searchParams.set('start_date', currentStartDate); else url.searchParams.delete('start_date');
            if (currentEndDate) url.searchParams.set('end_date', currentEndDate); else url.searchParams.delete('end_date');
            if (currentFilterType) url.searchParams.set('filter_type', currentFilterType); else url.searchParams.delete('filter_type');
            window.history.pushState({}, '', url);
        };

        const adjustTableHeight = function () {
            const $tableResp = $('.table-responsive');
            if ($tableResp.length === 0 || document.fullscreenElement) return;

            const windowHeight = $(window).height();
            const tableTop = $tableResp.offset().top;
            const buffer = 45; // Space for the page footer and a bit of margin
            const availableHeight = windowHeight - tableTop - buffer;

            if (availableHeight > 200) { // Don't make it too small
                $tableResp.css({
                    'max-height': availableHeight + 'px',
                    'height': availableHeight + 'px' // Also set height to force footer to bottom if rows are few
                });
            }
        };
        const updateOnlineSorgulaVisibility = function () {
            $('.btn-online-sorgula').addClass('d-none').removeClass('d-flex');
            $('.vr-online-sorgula').addClass('d-none');
            $('.btn-kacak-action').addClass('d-none').removeClass('d-flex');
            $('.vr-kacak-action').addClass('d-none');

            if (currentTab === 'okuma') {
                $('#btnOnlineSorgulaEndeks').addClass('d-flex').removeClass('d-none');
                $('.vr-online-sorgula').removeClass('d-none');
            } else if (currentTab === 'kesme') {
                $('#btnOnlineSorgulaPuantaj').addClass('d-flex').removeClass('d-none');
                $('.vr-online-sorgula').removeClass('d-none');
            } else if (currentTab === 'sokme_takma') {
                $('#btnOnlineSorgulaSayac').addClass('d-flex').removeClass('d-none');
                $('.vr-online-sorgula').removeClass('d-none');
            } else if (currentTab === 'muhurleme') {
                $('#btnOnlineSorgulaMuhurleme').addClass('d-flex').removeClass('d-none');
                $('.vr-online-sorgula').removeClass('d-none');
            } else if (currentTab === 'kacakkontrol') {
                $('.btn-kacak-action').addClass('d-flex').removeClass('d-none');
                $('.vr-kacak-action').removeClass('d-none');
            }

            // Show/Hide Kacak Help Info
            if (currentTab === 'kacakkontrol') {
                $('#kacakHelpInfo').fadeIn();
            } else {
                $('#kacakHelpInfo').fadeOut();
            }
        };

        $('input[name="filter_type"]').on('change', function () {
            const type = $(this).val();
            currentFilterType = type;
            if (type === 'period') {
                $('.filter-group-period').show();
                $('.filter-group-range').hide();
            } else {
                $('.filter-group-period').hide();
                $('.filter-group-range').show();
            }
        });

        // Adjust height on window resize
        $(window).on('resize', function () {
            adjustTableHeight();
        });

        // Adjust height when filter accordion is toggled
        $('#collapseOne').on('shown.bs.collapse hidden.bs.collapse', function () {
            adjustTableHeight();
        });

        // ========== ANA MOD VE SEKME YÖNETİMİ ==========
        let currentMode = '<?= $activeTab === 'karsilastirma' ? 'karsilastirma' : 'rapor' ?>';

        // Ana mod sekmesi tıklaması (Özet Raporlar / Karşılaştırma)
        $('.main-mode-btn').on('click', function () {
            $('.main-mode-btn').removeClass('active');
            $(this).addClass('active');
            currentMode = $(this).data('mode');

            if (currentMode === 'karsilastirma') {
                $('#modeRaporContent').hide();
                $('#modeKarsilastirmaContent').show();
                // İlk yüklemede default dönemleri ekle
                if (comparisonPeriods.length === 0) {
                    addDefaultPeriods();
                } else {
                    renderPeriodChips();
                }
                loadComparisonReport();
            } else {
                $('#modeKarsilastirmaContent').hide();
                $('#modeRaporContent').show();
                updateOnlineSorgulaVisibility();
                loadReport();
            }
        });

        // Rapor alt sekme tıklaması (sadece rapor modu)
        $('#raporTabs .nav-link').on('click', function () {
            $('#raporTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            currentTab = $(this).data('tab');
            updateOnlineSorgulaVisibility();
            loadReport();
        });

        // Karşılaştırma alt sekme tıklaması (sadece karşılaştırma modu)
        $('#compareTabs .nav-link').on('click', function () {
            $('#compareTabs .nav-link').removeClass('active');
            $(this).addClass('active');
            currentCompareTab = $(this).data('tab');
            loadComparisonReport();
        });

        $(document).on('dblclick', '.kacak-quick-cell', function () {
            // Remove previous selection
            $('.kacak-quick-cell').removeClass('kacak-cell-selected');
            // Add selection to current cell
            $(this).addClass('kacak-cell-selected');

            let tarih = $(this).attr('data-date');
            let pIds = $(this).attr('data-personel-ids');
            let ekipAdi = $(this).attr('data-ekip-adi'); // Yeni
            let sayi = $(this).text().trim() || '';
            window.openKacakModal(tarih, pIds, sayi, ekipAdi);
        });

        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            currentYear = $('select[name="year"]').val();
            currentMonth = $('select[name="month"]').val();
            currentPersonelId = $('select[name="personel_id"]').val();
            currentRegion = $('select[name="region"]').val();
            currentDefter = $('select[name="defter"]').val();
            currentStartDate = $('input[name="start_date"]').val();
            currentEndDate = $('input[name="end_date"]').val();
            currentFilterType = $('input[name="filter_type"]:checked').val();
            loadReport();
            const collapseElement = document.getElementById('collapseOne');
            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement);
            if (bsCollapse) bsCollapse.hide();
        });

        $('#btnClearFilters').on('click', function () {
            currentPersonelId = '';
            currentRegion = '';
            currentDefter = '';
            currentStartDate = '';
            currentEndDate = '';
            $('select[name="personel_id"]').val('').trigger('change');
            $('select[name="region"]').val('').trigger('change');
            $('select[name="defter"]').val('').trigger('change');
            $('input[name="start_date"]').val('');
            $('input[name="end_date"]').val('');
            // Reset to period mode maybe? Let's keep the user's selected mode but clear values.
            loadReport();
        });

        $('#btnExportExcel').on('click', function () {
            const url = `views/puantaj/rapor-excel.php?tab=${currentTab}&year=${currentYear}&month=${currentMonth}&personel_id=${currentPersonelId}&region=${currentRegion}&defter=${currentDefter}&start_date=${currentStartDate}&end_date=${currentEndDate}&filter_type=${currentFilterType}`;
            window.location.href = url;
        });

        $('#btnFullScreen').on('click', function () {
            const elem = document.getElementById('reportCardRow');
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
                $(this).html('<i class="bx bx-exit-fullscreen me-1"></i> Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                $(this).html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        });

        $('#btnCompareFullScreen').on('click', function () {
            const elem = document.getElementById('compareCardRow');
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
                $(this).html('<i class="bx bx-exit-fullscreen me-1"></i> Küçült');
                $(elem).addClass('fullscreen-mode');
            } else {
                document.exitFullscreen();
                $(this).html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $(elem).removeClass('fullscreen-mode');
            }
        });

        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                $('#btnFullScreen').html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $('#reportCardRow').removeClass('fullscreen-mode');

                $('#btnCompareFullScreen').html('<i class="bx bx-fullscreen me-1"></i> Tam Ekran');
                $('#compareCardRow').removeClass('fullscreen-mode');
            }
        });

        // ========== KARŞILAŞTIRMA BÖLÜMÜ ==========
        let comparisonPeriods = []; // ['2026-01', '2026-02', ...]
        let currentCompareTab = 'okuma';
        let currentCompareMode = 'personel';

        const COMPARE_STORAGE_KEY = 'comparison_periods';
        const monthNamesTr = {
            '01': 'Ocak', '02': 'Şubat', '03': 'Mart', '04': 'Nisan',
            '05': 'Mayıs', '06': 'Haziran', '07': 'Temmuz', '08': 'Ağustos',
            '09': 'Eylül', '10': 'Ekim', '11': 'Kasım', '12': 'Aralık'
        };

        function addDefaultPeriods() {
            const now = new Date();
            comparisonPeriods = [];
            // Son 3 ay
            for (let i = 2; i >= 0; i--) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                comparisonPeriods.push(key);
            }
            renderPeriodChips();
        }

        function renderPeriodChips() {
            const container = $('#selectedPeriods');
            container.empty();
            comparisonPeriods.forEach(function (p) {
                const parts = p.split('-');
                const label = (monthNamesTr[parts[1]] || parts[1]) + ' ' + parts[0];
                container.append(
                    `<div class="badge bg-primary-subtle text-primary d-flex align-items-center gap-1 py-2 px-3" style="font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid rgba(81,86,190,0.2);">
                        <i class="bx bx-calendar-event"></i>
                        ${label}
                        <button type="button" class="btn-close btn-close-sm ms-1" style="font-size: 8px; filter: none; opacity: 0.7;" data-period="${p}"></button>
                    </div>`
                );
            });
            // Save to storage
            localStorage.setItem(COMPARE_STORAGE_KEY, JSON.stringify(comparisonPeriods));
        }

        // Period chip remove
        $(document).on('click', '#selectedPeriods .btn-close', function () {
            const period = $(this).data('period');
            comparisonPeriods = comparisonPeriods.filter(p => p !== period);
            renderPeriodChips();
        });

        // Add period button
        $('#btnAddPeriod').on('click', function () {
            const year = $('#addPeriodYear').val();
            const month = $('#addPeriodMonth').val();
            const key = year + '-' + month;
            if (!comparisonPeriods.includes(key)) {
                comparisonPeriods.push(key);
                // Sort chronologically
                comparisonPeriods.sort();
                renderPeriodChips();
            } else {
                Swal.fire({ icon: 'info', title: 'Bilgi', text: 'Bu dönem zaten ekli!', timer: 1500, showConfirmButton: false });
            }
        });

        // Clear periods
        $('#btnClearPeriods').on('click', function () {
            comparisonPeriods = [];
            renderPeriodChips();
        });

        // Compare button
        $('#btnCompare').on('click', function () {
            loadComparisonReport();
        });

        // Load comparison report
        window.loadComparisonReport = function (mode, tab) {
            if (mode) currentCompareMode = mode;
            if (tab) currentCompareTab = tab;

            if (comparisonPeriods.length < 2) {
                $('#compareContent').html(
                    '<div class="alert alert-warning d-flex align-items-center gap-2 m-3">'
                    + '<i class="bx bx-info-circle fs-4"></i>'
                    + '<div>Karşılaştırma yapmak için en az <strong>2 dönem</strong> seçmelisiniz. Yukarıdaki dönem seçiciden ay ekleyip <strong>Karşılaştır</strong> butonuna basın.</div>'
                    + '</div>'
                );
                return;
            }

            $('#compareContent').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Karşılaştırma raporu hazırlanıyor...</p></div>');

            // Build query string
            let params = 'action=get-comparison-report';
            params += '&compare_tab=' + currentCompareTab;
            params += '&compare_mode=' + currentCompareMode;
            comparisonPeriods.forEach(function (p) {
                params += '&periods[]=' + encodeURIComponent(p);
            });

            $.ajax({
                url: 'views/puantaj/api.php?' + params,
                type: 'GET',
                success: function (html) {
                    $('#compareContent').html(html);
                    saveFiltersToStorage();
                },
                error: function () {
                    $('#compareContent').html('<div class="alert alert-danger">Karşılaştırma raporu yüklenirken bir hata oluştu.</div>');
                }
            });
        };

        // Load saved periods from storage
        const savedPeriods = localStorage.getItem(COMPARE_STORAGE_KEY);
        if (savedPeriods) {
            try {
                comparisonPeriods = JSON.parse(savedPeriods);
            } catch (e) { }
        }

        // ========== /KARŞILAŞTIRMA BÖLÜMÜ ==========

        // Initial load
        loadFiltersFromStorage();
        updateOnlineSorgulaVisibility();

        if (currentMode === 'karsilastirma') {
            if (comparisonPeriods.length === 0) {
                addDefaultPeriods();
            } else {
                renderPeriodChips();
            }
            loadComparisonReport();
        } else {
            loadReport();
        }

        var regionListJson = <?= json_encode($regionList) ?>;

        function addKacakRow(data = null) {
            var rowId = 'kacak_row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
            var guven = data && data.guven ? data.guven : null;

            var ilceOptionsHtml = '<option value="">İlçe Seçiniz</option>';
            regionListJson.forEach(function(r) {
                var selected = (data && data.ilce === r) ? 'selected' : '';
                ilceOptionsHtml += '<option value="' + r + '" ' + selected + '>' + r + '</option>';
            });

            var turOptionsHtml = `
                <option value="">Tür Seçiniz</option>
                <option value="Kaçak" ${(!data || data.tur === 'Kaçak') ? 'selected' : ''}>Kaçak</option>
                <option value="Abonesiz" ${(data && data.tur === 'Abonesiz') ? 'selected' : ''}>Abonesiz</option>
            `;

            var aiConfClass = function (guven, field) {
                if (!guven) return '';
                var g = guven[field];
                if (g === undefined) return '';
                if (g >= 0.8) return 'border-success bg-success-subtle text-success';
                if (g >= 0.4) return 'border-warning bg-warning-subtle text-warning';
                return 'border-danger bg-danger-subtle text-danger';
            };

            var aiConfBadgeHtml = function (guven, field) {
                if (!guven) return '';
                var g = guven[field];
                if (g === undefined) return '';
                var pct = Math.round(g * 100);
                if (g >= 0.8) return '<span class="badge bg-success-subtle text-success ai-conf-badge px-1 py-0.5 ms-1" style="font-size: 8px;" title="AI Güven Skoru: %' + pct + '"><i class="bx bx-check-shield"></i> %' + pct + '</span>';
                if (g >= 0.4) return '<span class="badge bg-warning-subtle text-warning ai-conf-badge px-1 py-0.5 ms-1" style="font-size: 8px;" title="AI Güven Skoru: %' + pct + '"><i class="bx bx-info-circle"></i> %' + pct + '</span>';
                return '<span class="badge bg-danger-subtle text-danger ai-conf-badge px-1 py-0.5 ms-1" style="font-size: 8px;" title="AI Güven Skoru: %' + pct + '"><i class="bx bx-error"></i> %' + pct + '</span>';
            };

            var rowHtml = `
                <div class="border p-2 rounded mb-2 kacak-repeater-row" id="${rowId}">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">İlçe ${aiConfBadgeHtml(guven, 'ilce')}</label>
                            <select name="ilce[]" class="form-select select2-ilce" required>
                                ${ilceOptionsHtml}
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">Tür ${aiConfBadgeHtml(guven, 'tur')}</label>
                            <select name="tur[]" class="form-select select2-tur" required>
                                ${turOptionsHtml}
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="small text-muted mb-0">Tutanak No ${aiConfBadgeHtml(guven, 'tutanak_no')}</label>
                            <input type="text" name="tutanak_no[]" class="form-control ai-field ${aiConfClass(guven, 'tutanak_no')}" placeholder="No" value="${data && data.tutanak_no ? data.tutanak_no : ''}">
                        </div>
                        <div class="col-md-2">
                            <label class="small text-muted mb-0">Abone İsim Soyisim ${aiConfBadgeHtml(guven, 'abone_adi')}</label>
                            <input type="text" name="abone_adi[]" class="form-control ai-field ${aiConfClass(guven, 'abone_adi')}" placeholder="Abone Adı Soyadı" value="${data && data.abone_adi ? data.abone_adi : ''}">
                        </div>
                        <div class="col-md-1">
                            <label class="small text-muted mb-0">Sayaç No ${aiConfBadgeHtml(guven, 'sayac_no')}</label>
                            <input type="text" name="sayac_no[]" class="form-control ai-field ${aiConfClass(guven, 'sayac_no')}" placeholder="Sayaç No" value="${data && data.sayac_no ? data.sayac_no : ''}">
                        </div>
                        <div class="col-md-1">
                            <label class="small text-muted mb-0">Endeks ${aiConfBadgeHtml(guven, 'endeks')}</label>
                            <input type="text" name="endeks[]" class="form-control ai-field ${aiConfClass(guven, 'endeks')}" placeholder="Endeks" value="${data && data.endeks ? data.endeks : ''}">
                        </div>
                        <div class="col-md-1">
                            <label class="small text-muted mb-0">Sayı ${aiConfBadgeHtml(guven, 'sayi')}</label>
                            <input type="number" name="sayi[]" class="form-control ai-field px-1 text-center ${aiConfClass(guven, 'sayi')}" placeholder="Sayı" value="${data ? data.sayi : '1'}" required min="1">
                        </div>
                        <div class="col-md-1">
                            <label class="small text-muted mb-0">Açıklama ${aiConfBadgeHtml(guven, 'aciklama')}</label>
                            <input type="text" name="aciklama[]" class="form-control ai-field ${aiConfClass(guven, 'aciklama')}" placeholder="Açıklama" value="${data ? data.aciklama : ''}">
                        </div>
                        <div class="col-md-1 text-end mt-3">
                            <button type="button" class="btn btn-soft-danger btn-sm btnRemoveKacakRow">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('#kacakRowsContainer').append(rowHtml);

            // Initialize select2-ilce
            $('#' + rowId + ' .select2-ilce').select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'İlçe Seçiniz',
                allowClear: true,
                width: '100%'
            }).next('.select2-container').find('.select2-selection').addClass(aiConfClass(guven, 'ilce'));

            // Initialize select2-tur
            $('#' + rowId + ' .select2-tur').select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'Tür Seçiniz',
                allowClear: true,
                width: '100%'
            }).next('.select2-container').find('.select2-selection').addClass(aiConfClass(guven, 'tur'));
        }

        function markFieldAsConfirmed($el) {
            $el.removeClass('border-warning bg-warning-subtle text-warning border-danger bg-danger-subtle text-danger')
               .addClass('border-success bg-success-subtle text-success');
            // Remove confidence percentage badge
            $el.closest('div').find('.ai-conf-badge').fadeOut(300, function() { $(this).remove(); });
        }

        $(document).on('input', '#kacakRowsContainer input.ai-field', function () {
            markFieldAsConfirmed($(this));
        });
        $(document).on('change', '#kacakRowsContainer .select2-ilce, #kacakRowsContainer .select2-tur', function () {
            markFieldAsConfirmed($(this).next('.select2-container').find('.select2-selection'));
        });
        $(document).on('input change', '#kacakManualForm input[name="tarih"]', function () {
            markFieldAsConfirmed($(this));
        });
        $(document).on('change', '#kacak_personel_ids', function () {
            markFieldAsConfirmed($(this).next('.select2-container').find('.select2-selection'));
        });

        $('#btnAddKacakRow').on('click', function() {
            addKacakRow();
        });

        $(document).on('click', '.btnRemoveKacakRow', function() {
            if ($('.kacak-repeater-row').length > 1) {
                $(this).closest('.kacak-repeater-row').remove();
            } else {
                Swal.fire('Hata', 'En az bir satır bulunmalıdır.', 'warning');
            }
        });

        window.openKacakModal = function (tarih, pIds, sayi, ekipAdi) {
            $('#kacakManualForm input[name="id"]').val(0);
            $('#kacakManualForm')[0].reset();
            $('#kacakRowsContainer').empty();

            $('#kacakModalTitle').text('Hızlı Kaçak Kontrol Kaydı');

            // Force ID to 0 for new entry from cell
            $('#kacak_id').val(0);

            // Convert to array of strings for Select2 compatibility
            let pIdsArr = [];
            if (pIds && typeof pIds === 'string' && pIds.trim() !== '') {
                pIdsArr = pIds.split(',').map(x => x.trim()).filter(x => x !== '');
            }

            console.log('Opening Kacak Modal - Date:', tarih, 'Personnel IDs:', pIdsArr, 'Sayi:', sayi);

            // Set Date - Handle Y-m-d to d.m.Y conversion for display
            let displayDate = tarih;
            if (tarih && tarih.indexOf('-') !== -1) {
                let parts = tarih.split('-');
                if (parts.length === 3) {
                    displayDate = parts[2] + '.' + parts[1] + '.' + parts[0];
                }
            }
            let dateInput = $('#kacakManualForm input[name="tarih"]');
            dateInput.val(displayDate);

            // Initialize/Update Flatpickr
            if (dateInput.length > 0) {
                if (dateInput[0]._flatpickr) {
                    dateInput[0]._flatpickr.setDate(displayDate);
                } else {
                    dateInput.flatpickr({
                        dateFormat: "d.m.Y",
                        locale: "tr",
                        allowInput: true
                    });
                }
            }

            // Set Ekip Adi
            $('#kacakManualForm input[name="ekip_adi"]').val(ekipAdi || '');

            // Initialize Select2 with pre-selected values
            initPersonelSelect2(pIdsArr);

            // If sayi > 0, fetch existing records for this day and team to edit them instead of duplicating
            if (sayi > 0) {
                $.get('views/puantaj/api.php', {
                    action: 'get-kacak-cell-records',
                    tarih: tarih,
                    ekip_adi: ekipAdi,
                    personel_ids: pIds
                }, function (response) {
                    try {
                        var records = typeof response === 'object' ? response : JSON.parse(response);
                        if (records && records.length > 0) {
                            records.forEach(function(rec) {
                                addKacakRow({
                                    ilce: rec.ilce,
                                    tur: rec.tur,
                                    tutanak_no: rec.tutanak_no,
                                    abone_adi: rec.abone_adi,
                                    sayac_no: rec.sayac_no,
                                    endeks: rec.endeks,
                                    sayi: rec.sayi,
                                    aciklama: rec.aciklama
                                });
                            });
                        } else {
                            addKacakRow({
                                ilce: '',
                                tur: 'Kaçak',
                                tutanak_no: '',
                                abone_adi: '',
                                sayac_no: '',
                                endeks: '',
                                sayi: sayi,
                                aciklama: ''
                            });
                        }
                    } catch (e) {
                        addKacakRow({
                            ilce: '',
                            tur: 'Kaçak',
                            tutanak_no: '',
                            abone_adi: '',
                            sayac_no: '',
                            endeks: '',
                            sayi: sayi,
                            aciklama: ''
                        });
                    }
                });
            } else {
                // Add initial row with default values if new
                addKacakRow({
                    ilce: '',
                    tur: 'Kaçak',
                    tutanak_no: '',
                    abone_adi: '',
                    sayac_no: '',
                    endeks: '',
                    sayi: '',
                    aciklama: ''
                });
            }

            $('#kacakModal').modal('show');
        }

        const initPersonelSelect2 = function (selectedValues) {
            var $el = $('#kacak_personel_ids');
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }

            // Set selected values before initializing Select2
            if (selectedValues && selectedValues.length > 0) {
                $el.val(selectedValues);
            }

            $el.select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'Personel Seçiniz',
                allowClear: true,
                maximumSelectionLength: 2,
                width: '100%'
            });

            // Trigger change to update Select2 display
            if (selectedValues && selectedValues.length > 0) {
                $el.trigger('change');
            }
        };

        $('#kacakManualForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=kacak-kaydet';

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: 'Kayıtlar başarıyla kaydedildi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            $('#kacakModal').modal('hide');
                            loadReport();
                        } else {
                            Swal.fire('Hata', res.message || 'Kayıt sırasında bir hata oluştu.', 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                }
            });
        });

        // Column Search Filtering
        let searchTimeout;
        $(document).on('keyup', '.column-search', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                const searchValues = {};
                $('.column-search').each(function () {
                    const col = $(this).data('col');
                    const val = $(this).val().toLowerCase().trim();
                    if (val) searchValues[col] = val;
                });

                $('#raporTable tbody tr').each(function () {
                    const $row = $(this);
                    let show = true;

                    for (let col in searchValues) {
                        const searchVal = searchValues[col];
                        let cellText = '';

                        if (col === 'sira') {
                            cellText = $row.find('.sticky-col-1').text().toLowerCase();
                        } else if (col === 'ekip') {
                            cellText = $row.find('.sticky-col-2').text().toLowerCase();
                        } else if (col === 'isim') {
                            cellText = $row.find('.sticky-col-3, .kacakkontrol-name-col').text().toLowerCase();
                        }

                        if (cellText.indexOf(searchVal) === -1) {
                            show = false;
                            break;
                        }
                    }

                    if (show) $row.show(); else $row.hide();
                });

                if (typeof updateDynamicTotals === 'function') {
                    updateDynamicTotals();
                }
            }, 300);
        });

        // Online Puantaj (Kesme/Açma) Sorgulama
        $('#onlinePuantajForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-puantaj-sorgula';
            formData += '&active_tab=kesme';

            $('#onlinePuantajSpinner').show();
            $('#onlinePuantajResult').hide();
            $('#btnOnlinePuantajSorgula').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    $('#onlinePuantajSpinner').hide();
                    $('#btnOnlinePuantajSorgula').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı! (Toplam ' + (res.toplam_api_kayit || 0) + ' kayıt)</strong><br>';
                            resultHtml += '<span class="fs-5">' + (res.message || res.yeni_kayit + ' adet yeni kayıt eklendi.') + '</span>';
                            if (res.guncellenen_kayit > 0) {
                                resultHtml += '<br><span class="text-warning">' + res.guncellenen_kayit + ' adet kayıt güncellendi.</span>';
                            }

                            if (res.mevcut_kayitlar && res.mevcut_kayitlar.length > 0) {
                                resultHtml += '<hr><strong>Daha önce çekilmiş kayıtlar:</strong>';
                                if (res.mevcut_kayitlar.length > 5) {
                                    resultHtml += '<div class="d-flex align-items-center justify-content-between mt-1">';
                                    resultHtml += '<span>' + res.mevcut_kayitlar.length + ' adet kayıt daha önce işlendiği için atlandı.</span>';
                                    resultHtml += '<button type="button" class="btn btn-sm btn-outline-info fw-bold" onclick=\'exportToCsv(this, ' + JSON.stringify(res.mevcut_kayitlar) + ', {"islem_id":"İşlem ID","tarih":"Tarih","ekip_kodu":"Ekip Kodu","is_emri_tipi":"İş Emri Tipi","is_emri_sonucu":"İş Emri Sonucu"}, "mevcut_kayitlar")\' style="font-size: 11px;"><i class="mdi mdi-file-excel me-1"></i>Excel Olarak İndir</button>';
                                    resultHtml += '</div>';
                                } else {
                                    resultHtml += '<ul class="mb-0 mt-1 small">';
                                    res.mevcut_kayitlar.forEach(function (item) {
                                        resultHtml += '<li>İşlem ID: ' + item.islem_id + ' - ' + item.ekip_kodu + ' - ' + item.is_emri_tipi + '</li>';
                                    });
                                    resultHtml += '</ul>';
                                }
                            }

                            if (res.atlanAn_kayitlar && res.atlanAn_kayitlar.length > 0) {
                                resultHtml += '<hr><div class="alert alert-warning mb-0 p-2"><strong>⚠️ Eşleşmeyen Ekipler (' + res.atlanAn_kayitlar.length + '):</strong><br>';
                                if (res.atlanAn_kayitlar.length > 5) {
                                    resultHtml += '<div class="d-flex align-items-center justify-content-between mt-1">';
                                    resultHtml += '<span>' + res.atlanAn_kayitlar.length + ' adet kayıt ekip kodu uyuşmadığı için atlandı.</span>';
                                    resultHtml += '<button type="button" class="btn btn-sm btn-outline-dark fw-bold" onclick=\'exportToCsv(this, ' + JSON.stringify(res.atlanAn_kayitlar) + ', {"ekip_kodu":"Ekip Kodu","is_emri_tipi":"İş Emri Tipi","is_emri_sonucu":"İş Emri Sonucu","tarih":"Tarih"}, "eslesmeyen_ekipler")\' style="font-size: 11px;"><i class="mdi mdi-file-excel me-1"></i>Excel Olarak İndir</button>';
                                    resultHtml += '</div>';
                                } else {
                                    resultHtml += '<ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                    res.atlanAn_kayitlar.forEach(function (item) {
                                        resultHtml += '<li>' + item.ekip_kodu + ' (' + item.tarih + ')</li>';
                                    });
                                    resultHtml += '</ul>';
                                }
                                resultHtml += '</div>';
                            }

                            if (res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0) {
                                resultHtml += '<hr><div class="alert alert-danger mb-0 p-2"><strong>⚠️ Aparat Zimmeti Eksik Personeller (' + Object.keys(res.eksik_zimmetler).length + '):</strong><br>';
                                resultHtml += '<small>Şu personellerin zimmetinde aparat olmadığı için tüketim düşülemedi:</small><ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                Object.values(res.eksik_zimmetler).forEach(function (isim) {
                                    resultHtml += '<li>' + isim + '</li>';
                                });
                                resultHtml += '</ul></div>';
                            }

                            resultHtml += '</div>';
                            loadReport();
                        } else {
                            resultHtml = '<div class="alert alert-danger"><strong>Hata!</strong><br>' + res.message + '</div>';
                        }
                        $('#onlinePuantajResult').html(resultHtml).show();
                    } catch (err) {
                        $('#onlinePuantajResult').html('<div class="alert alert-danger">Sunucudan geçersiz yanıt alındı.</div>').show();
                    }
                },
                error: function () {
                    $('#onlinePuantajSpinner').hide();
                    $('#btnOnlinePuantajSorgula').prop('disabled', false);
                    $('#onlinePuantajResult').html('<div class="alert alert-danger">Bağlantı hatası oluştu.</div>').show();
                }
            });
        });



        // Online Mühürleme Sorgulama
        $('#onlineMuhurlemeForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-puantaj-sorgula';
            formData += '&active_tab=muhurleme';

            $('#onlineMuhurlemeSpinner').show();
            $('#onlineMuhurlemeResult').hide();
            $('#btnOnlineMuhurlemeSorgula').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    $('#onlineMuhurlemeSpinner').hide();
                    $('#btnOnlineMuhurlemeSorgula').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı!</strong><br>';
                            resultHtml += '<span class="fs-5">' + (res.message || res.yeni_kayit + ' adet yeni kayıt eklendi.') + '</span>';
                            if (res.guncellenen_kayit > 0) {
                                resultHtml += '<br><span class="text-warning">' + res.guncellenen_kayit + ' adet kayıt güncellendi.</span>';
                            }

                            if (res.atlanAn_kayitlar && res.atlanAn_kayitlar.length > 0) {
                                resultHtml += '<hr><div class="alert alert-warning mb-0 p-2"><strong>⚠️ Eşleşmeyen Ekipler (' + res.atlanAn_kayitlar.length + '):</strong><br>';
                                if (res.atlanAn_kayitlar.length > 5) {
                                    resultHtml += '<div class="d-flex align-items-center justify-content-between mt-1">';
                                    resultHtml += '<span>' + res.atlanAn_kayitlar.length + ' adet kayıt ekip kodu uyuşmadığı için atlandı.</span>';
                                    resultHtml += '<button type="button" class="btn btn-sm btn-outline-dark fw-bold" onclick=\'exportToCsv(this, ' + JSON.stringify(res.atlanAn_kayitlar) + ', {"ekip_kodu":"Ekip Kodu","is_emri_tipi":"İş Emri Tipi","is_emri_sonucu":"İş Emri Sonucu","tarih":"Tarih"}, "eslesmeyen_ekipler")\' style="font-size: 11px;"><i class="mdi mdi-file-excel me-1"></i>Excel Olarak İndir</button>';
                                    resultHtml += '</div>';
                                } else {
                                    resultHtml += '<ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                    res.atlanAn_kayitlar.forEach(function (item) {
                                        resultHtml += '<li>' + item.ekip_kodu + ' (' + item.tarih + ')</li>';
                                    });
                                    resultHtml += '</ul>';
                                }
                                resultHtml += '</div>';
                            }
                            resultHtml += '</div>';
                            loadReport();
                        } else {
                            resultHtml = '<div class="alert alert-danger"><strong>Hata!</strong><br>' + res.message + '</div>';
                        }
                        $('#onlineMuhurlemeResult').html(resultHtml).show();
                    } catch (err) {
                        $('#onlineMuhurlemeResult').html('<div class="alert alert-danger">Sunucudan geçersiz yanıt alındı.</div>').show();
                    }
                },
                error: function () {
                    $('#onlineMuhurlemeSpinner').hide();
                    $('#btnOnlineMuhurlemeSorgula').prop('disabled', false);
                    $('#onlineMuhurlemeResult').html('<div class="alert alert-danger">Bağlantı hatası oluştu.</div>').show();
                }
            });
        });

        // Online İcmal (Endeks Okuma) Sorgulama
        $('#onlineIcmalForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-icmal-sorgula';

            $('#onlineIcmalSpinner').show();
            $('#onlineIcmalResult').hide();
            $('#btnOnlineIcmalSorgula').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    $('#onlineIcmalSpinner').hide();
                    $('#btnOnlineIcmalSorgula').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı!</strong><br>';
                            resultHtml += '<span class="fs-5">' + (res.message || res.yeni_kayit + ' adet yeni kayıt eklendi.') + '</span>';
                            if (res.silinen_kayit > 0) {
                                resultHtml += '<br><span class="text-info">' + res.silinen_kayit + ' adet eski kayıt temizlendi.</span>';
                            }

                            if (res.atlanAn_kayitlar && res.atlanAn_kayitlar.length > 0) {
                                resultHtml += '<hr><div class="alert alert-warning mb-0 p-2"><strong>⚠️ Eşleşmeyen Ekipler (' + res.atlanAn_kayitlar.length + '):</strong><br>';
                                if (res.atlanAn_kayitlar.length > 5) {
                                    resultHtml += '<div class="d-flex align-items-center justify-content-between mt-1">';
                                    resultHtml += '<span>' + res.atlanAn_kayitlar.length + ' adet kayıt ekip kodu uyuşmadığı için atlandı.</span>';
                                    resultHtml += '<button type="button" class="btn btn-sm btn-outline-dark fw-bold" onclick=\'exportToCsv(this, ' + JSON.stringify(res.atlanAn_kayitlar) + ', {"kullanici_adi":"Okuyucu Adı","bolge":"Bölge","okuyucu_no":"Okuyucu No"}, "eslesmeyen_ekipler")\' style="font-size: 11px;"><i class="mdi mdi-file-excel me-1"></i>Excel Olarak İndir</button>';
                                    resultHtml += '</div>';
                                } else {
                                    resultHtml += '<small>Sistemde tanımlı olmadığı için atlanan ekipler:</small><ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                    res.atlanAn_kayitlar.forEach(function (item) {
                                        resultHtml += '<li>' + item.kullanici_adi + ' (' + item.bolge + ')</li>';
                                    });
                                    resultHtml += '</ul>';
                                }
                                resultHtml += '</div>';
                            }
                            resultHtml += '</div>';
                            loadReport();
                        } else {
                            resultHtml = '<div class="alert alert-danger"><strong>Hata!</strong><br>' + res.message + '</div>';
                        }
                        $('#onlineIcmalResult').html(resultHtml).show();
                    } catch (err) {
                        $('#onlineIcmalResult').html('<div class="alert alert-danger">Sunucudan geçersiz yanıt alındı.</div>').show();
                    }
                },
                error: function () {
                    $('#onlineIcmalSpinner').hide();
                    $('#btnOnlineIcmalSorgula').prop('disabled', false);
                    $('#onlineIcmalResult').html('<div class="alert alert-danger">Bağlantı hatası oluştu.</div>').show();
                }
            });
        });

        // Online Sayaç Değişim Sorgulama
        $('#onlineSayacForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-sayac-degisim-sorgula';

            $('#onlineSayacSpinner').show();
            $('#onlineSayacResult').hide();
            $('#btnOnlineSayacSorgula').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    $('#onlineSayacSpinner').hide();
                    $('#btnOnlineSayacSorgula').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı! (Toplam ' + (res.toplam_api_kayit || 0) + ' kayıt)</strong><br>';
                            resultHtml += '<span class="fs-5">' + res.yeni_kayit + ' adet yeni kayıt eklendi.</span>';
                            if (res.atlanan_kayit > 0) {
                                resultHtml += '<br><small class="text-secondary">' + res.atlanan_kayit + ' adet kayıt daha önce işlendiği için atlandı.</small>';
                            }
                            if (res.atlanAn_kayitlar && res.atlanAn_kayitlar.length > 0) {
                                resultHtml += '<hr><div class="alert alert-warning mb-0 p-2"><strong>⚠️ Eşleşmeyen Ekipler (' + res.atlanAn_kayitlar.length + '):</strong><br>';
                                resultHtml += '<small>Sistemde tanımlı olmadığı için atlandı:</small><ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                res.atlanAn_kayitlar.forEach(function (item) {
                                    resultHtml += '<li>' + item.ekip_kodu + '</li>';
                                });
                                resultHtml += '</ul></div>';
                            }
                            resultHtml += '</div>';
                            // Tabloyu güncelle
                            loadReport();
                        } else {
                            resultHtml = '<div class="alert alert-danger">';
                            resultHtml += '<strong><i class="bx bx-error-circle me-2"></i>Hata!</strong><br>';
                            resultHtml += res.message;
                            resultHtml += '</div>';
                        }
                        $('#onlineSayacResult').html(resultHtml).show();
                    } catch (err) {
                        $('#onlineSayacResult').html('<div class="alert alert-danger">Sunucudan geçersiz yanıt alındı.</div>').show();
                    }
                },
                error: function () {
                    $('#onlineSayacSpinner').hide();
                    $('#btnOnlineSayacSorgula').prop('disabled', false);
                    $('#onlineSayacResult').html('<div class="alert alert-danger">Bağlantı hatası oluştu.</div>').show();
                }
            });
        });

        // Modal cleanup
        $('#importOnlinePuantajModal').on('hidden.bs.modal', function () { $('#onlinePuantajResult').hide().html(''); });
        $('#importOnlineSayacModal').on('hidden.bs.modal', function () { $('#onlineSayacResult').hide().html(''); });
        $('#importOnlineMuhurlemeModal').on('hidden.bs.modal', function () { $('#onlineMuhurlemeResult').hide().html(''); });
        $('#importOnlineIcmalRaporuModal').on('hidden.bs.modal', function () { $('#onlineIcmalResult').hide().html(''); });
    });

    function exportToCsv(btn, data, Mapping, filename) {
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
        csvContent += Object.values(Mapping).join(";") + "\r\n";
        data.forEach(function (row) {
            let rowData = [];
            Object.keys(Mapping).forEach(key => { rowData.push(row[key] || ''); });
            csvContent += rowData.join(";") + "\r\n";
        });
        var encodedUri = encodeURI(csvContent);
        var link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", filename + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    $(document).ready(function() {
        $('#btnNewKacak').on('click', function () {
            if (typeof openKacakModal === 'function') {
                let today = new Date().toLocaleDateString('tr-TR');
                openKacakModal(today, '', 0, '');
            } else {
                // Fallback if not defined
                $('#kacakManualForm input[name="id"]').val(0);
                $('#kacakManualForm')[0].reset();
                $('#kacakModal').modal('show');
            }
        });

        $('#kacakUploadForm input[name="upload_date"]').on('change', function () {
            $('#kacakUploadForm input[name="excel_file"]').val('');
        });

        $('#kacakUploadForm').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'kacak-excel-kaydet');

            $('#kacakSpinner').show();
            $('#btnKacakUpload').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#kacakSpinner').hide();
                    $('#btnKacakUpload').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            var htmlMessage = res.message;
                            if (res.skipped_rows && res.skipped_rows.length > 0) {
                                htmlMessage += '<br><br><div class="alert alert-warning mb-0 mt-2"><strong>⚠️ Atlanan Satırlar (' + res.skipped_rows.length + '):</strong><ul class="mb-0 mt-2" style="max-height: 200px; overflow-y: auto;">';
                                res.skipped_rows.forEach(function (row) {
                                    htmlMessage += '<li><strong>Satır ' + row.satir + ':</strong> ' + row.ekip + ' - <em>' + row.neden + '</em></li>';
                                });
                                htmlMessage += '</ul></div>';
                            }
                            Swal.fire({
                                title: 'Başarılı',
                                html: htmlMessage,
                                icon: 'success',
                                width: res.skipped_rows && res.skipped_rows.length > 0 ? '600px' : '400px'
                            }).then(() => {
                                $('#importKacakModal').modal('hide');
                                loadReport();
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                },
                error: function () {
                    $('#kacakSpinner').hide();
                    $('#btnKacakUpload').prop('disabled', false);
                    Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                }
            });
        });

        $(document).on('click', '#btnKacakAnalizEt', function() {
            var fileInput = $('#kacakManualForm input[name="excel_file"]')[0];
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire('Uyarı', 'Lütfen önce analiz edilecek tutanak dosyasını (Görsel, PDF veya Excel) seçin.', 'warning');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'kacak-excel-kaydet');
            formData.append('use_openai', '1');
            formData.append('excel_file', fileInput.files[0]);
            
            // Tarih bilgisini de analize gönder (Varsayılan tarih ataması için)
            var uploadDate = $('#kacakManualForm input[name="tarih"]').val();
            formData.append('upload_date', uploadDate);

            // Seçilen personelleri OpenAI endpoint'inin beklediği isimle ekleyelim
            var selectedPersonels = $('#kacak_personel_ids').val();
            if (selectedPersonels && selectedPersonels.length > 0) {
                selectedPersonels.forEach(function(pid) {
                    formData.append('kacak_api_personel_ids[]', pid);
                });
            }

            // AI'ın döndüreceği ID'lerin ekrandaki dropdown ile birebir eşleşmesi için
            // dropdown'da gerçekten seçilebilir olan personel listesini de gönderelim
            var personelOptionsList = [];
            $('#kacak_personel_ids option').each(function () {
                var val = $(this).val();
                if (val) {
                    personelOptionsList.push({ id: parseInt(val, 10), name: $(this).text().trim() });
                }
            });
            formData.append('kacak_personel_list', JSON.stringify(personelOptionsList));

            $('#kacakManualSpinner').show();
            $('#btnKacakAnalizEt').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#kacakManualSpinner').hide();
                    $('#btnKacakAnalizEt').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire({
                                title: 'Analiz Başarılı',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Verileri Kontrol Et'
                            }).then(() => {
                                // Formu temizle ve repeater satırlarını boşalt
                                $('#kacakRowsContainer').empty();
                                
                                // AI verilerini tek tek ekle
                                if (res.extracted_data && res.extracted_data.length > 0) {
                                    res.extracted_data.forEach(function(item) {
                                        addKacakRow({
                                            ilce: item.ilçe || item.ilce || '',
                                            tur: item.tur || 'Kaçak',
                                            tutanak_no: item.tutanak_no || '',
                                            abone_adi: item.abone_adi || '',
                                            sayac_no: item.sayac_no || '',
                                            endeks: item.endeks || '',
                                            sayi: item.sayi || 1,
                                            aciklama: item.aciklama || '',
                                            guven: item.guven || null
                                        });
                                    });

                                    // Tarih bilgisini al ve güncelle
                                    var firstItem = res.extracted_data[0];
                                    if (firstItem.tarih) {
                                        var formattedDate = '';
                                        if (firstItem.tarih.indexOf('-') !== -1) {
                                            var parts = firstItem.tarih.split('-');
                                            if (parts.length === 3) {
                                                formattedDate = parts[2] + '.' + parts[1] + '.' + parts[0];
                                            }
                                        } else if (firstItem.tarih.indexOf('.') !== -1) {
                                            formattedDate = firstItem.tarih;
                                        }
                                        if (formattedDate) {
                                            $('#kacakManualForm input[name="tarih"]').val(formattedDate);
                                            if ($('#kacakManualForm input[name="tarih"]')[0]._flatpickr) {
                                                $('#kacakManualForm input[name="tarih"]')[0]._flatpickr.setDate(formattedDate);
                                            }
                                        }
                                    }
                                }
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                },
                error: function () {
                    $('#kacakManualSpinner').hide();
                    $('#btnKacakAnalizEt').prop('disabled', false);
                    Swal.fire('Hata', 'Analiz sırasında bağlantı hatası oluştu.', 'error');
                }
            });
        });

        $(document).on('click', '.delete-kacak', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu kayıt silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, sil!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('views/puantaj/api.php', { action: 'kacak-sil', id: id }, function (response) {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Kayıt başarıyla silindi.', 'success');
                            loadReport();
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });
        
        $(document).on('click', '.edit-kacak', function () {
            var id = $(this).data('id');
            $.get('views/puantaj/api.php', { action: 'get-kacak-record', id: id }, function (response) {
                var record = typeof response === 'object' ? response : JSON.parse(response);
                $('#kacakManualForm input[name="id"]').val(record.id);
                $('#kacakManualForm input[name="tarih"]').val(record.tarih_formatted);
                $('#kacakModalTitle').text('Kaydı Düzenle');

                var $el = $('#kacak_personel_ids');
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
                $el.select2({
                    dropdownParent: $('#kacakModal'),
                    placeholder: '',
                    allowClear: true,
                    maximumSelectionLength: 2,
                    width: '100%'
                });

                if (record.personel_ids_array && record.personel_ids_array.length > 0) {
                    $el.val(record.personel_ids_array).trigger('change');
                } else {
                    $el.val([]).trigger('change');
                }

                $('#kacakRowsContainer').empty();
                addKacakRow({
                    ilce: record.ilce,
                    tur: record.tur,
                    tutanak_no: record.tutanak_no,
                    abone_adi: record.abone_adi,
                    sayac_no: record.sayac_no,
                    endeks: record.endeks,
                    sayi: record.sayi,
                    aciklama: record.aciklama
                });

                $('#kacakModal').modal('show');
            });
        });
    });

</script>

<!-- Kaçak Kontrol Excel Modal -->
<div class="modal fade" id="importKacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kaçak Kontrol Excel Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kacakUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Excel dosyasında "Ekip", "Sayı" ve "Açıklama" sütunları bulunmalıdır. Tarih alanını aşağıdan seçiniz.
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'text',
                            name: 'upload_date',
                            value: date('d.m.Y'),
                            placeholder: '',
                            label: "Tarih",
                            icon: "calendar",
                            required: true,
                            class: "form-control flatpickr"
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFileInput(
                            name: 'excel_file',
                            label: "Excel Dosyası (.xlsx, .xls)",
                            icon: "file",
                            required: true,
                            class: "form-control"
                        ); ?>
                    </div>
                    <div id="kacakSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Yükleniyor...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnKacakUpload">Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kaçak Kontrol Manuel Modal -->
<div class="modal fade" id="kacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kacakModalTitle">Kaçak Kontrol Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kacakManualForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="kacak_id" value="0">
                <input type="hidden" name="ekip_adi" id="kacak_ekip_adi" value="">
                <div class="modal-body">
                    
                    <!-- 1. Tutanak Dosyası ve Analiz Butonu (En Üstte) -->
                    <div class="mb-3 border p-3 rounded bg-light">
                        <h6 class="fw-bold text-primary mb-2"><i class="bx bx-bot me-1"></i> Yapay Zeka ile Tutanak Oku</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9">
                                <?php echo \App\Helper\Form::FormFileInput(
                                    name: 'excel_file',
                                    label: "Tutanak Belgesi (Görsel, PDF veya Excel)",
                                    icon: "upload",
                                    required: false,
                                    class: "form-control"
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="btnKacakAnalizEt" class="btn btn-warning w-100 d-flex align-items-center justify-content-center fw-bold" style="height: 38px;">
                                    <i class="bx bx-play me-1 fs-5"></i> Analiz Et
                                </button>
                            </div>
                        </div>
                        <div class="form-text text-muted">Dosyayı seçtikten sonra "Analiz Et" butonuna basarak verileri otomatik çıkartabilirsiniz.</div>
                        
                        <div id="kacakManualSpinner" class="text-center p-2 mt-3" style="display: none;">
                            <div class="spinner-border text-warning" role="status"></div>
                            <p class="mt-2 text-warning fw-bold" id="kacakManualSpinnerText">OpenAI tutanağı analiz ediyor, lütfen bekleyin...</p>
                        </div>
                    </div>

                    <!-- 2. Tarih ve Personel Seçimi (Orta Bölüm) -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput(
                                type: 'text',
                                name: 'tarih',
                                value: date('d.m.Y'),
                                placeholder: '',
                                label: "Tarih",
                                icon: "calendar",
                                required: true,
                                class: "form-control flatpickr"
                            ); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="kacak_personel_ids" class="form-label mb-1">Personel Seçimi (En Fazla 2 Personel)</label>
                            <?php echo Form::FormMultipleSelect2(
                                name: 'kacak_personel_ids',
                                options: $personelList,
                                selectedValues: [],
                                label: 'Personel',
                                icon: 'users',
                                valueField: 'id',
                                textField: 'adi_soyadi',
                                class: 'form-select select2',
                                required: true
                            ); ?>
                        </div>
                    </div>

                    <!-- 3. Giriş Detayları / Analiz Sonuçları (En Altta) -->
                    <div class="border rounded p-3 bg-light mb-3" id="kacak_manual_rows_section">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bx bx-list-plus me-1"></i> Giriş Detayları</h6>
                            <button type="button" class="btn btn-sm btn-soft-success" id="btnAddKacakRow">
                                <i class="bx bx-plus me-1"></i> Satır Ekle
                            </button>
                        </div>
                        <div id="kacakRowsContainer">
                            <!-- JS veya AI ile doldurulacak -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Online Puantaj (Kesme/Açma) Sorgulama Modal -->
<div class="modal fade" id="importOnlinePuantajModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Kesme/Açma İşlemleri Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlinePuantajForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Ekip İş Emri Sonuç Raporu (Sonuç Tarihine Göre Sorgular) - KESME İŞEMRİ türünde online sorgulama
                        yapılacaktır.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'ilk_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "İlk Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'son_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "Son Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'baslangic_tarihi', value: date('d.m.Y'), placeholder: '', label: "Başlangıç Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'bitis_tarihi', value: date('d.m.Y'), placeholder: '', label: "Bitiş Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                    </div>
                    <div id="onlinePuantajSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlinePuantajResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlinePuantajSorgula">
                        <i class="bx bx-search me-1"></i> Sorgula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Online İcmal (Endeks Okuma) Sorgulama Modal -->
<div class="modal fade" id="importOnlineIcmalRaporuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Endeks Okuma Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlineIcmalForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Endeks Okuma İcmal Raporu - online sorgulama yapılacaktır.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'ilk_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "İlk Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'son_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "Son Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'baslangic_tarihi', value: date('d.m.Y'), placeholder: '', label: "Başlangıç Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'bitis_tarihi', value: date('d.m.Y'), placeholder: '', label: "Bitiş Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                    </div>
                    <div id="onlineIcmalSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlineIcmalResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlineIcmalSorgula">
                        <i class="bx bx-search me-1"></i> Sorgula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Online Sayaç Sorgulama Modal -->
<div class="modal fade" id="importOnlineSayacModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Sayaç Sökme Takma Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlineSayacForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Sayaç Sökme/Takma işlemleri için online sorgulama yapılacaktır.
                        (Tanımlı olan ücretli tüm iş emri sonuçları otomatik olarak çekilir)
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'ilk_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "İlk Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'son_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "Son Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'baslangic_tarihi', value: date('d.m.Y'), placeholder: '', label: "Başlangıç Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'bitis_tarihi', value: date('d.m.Y'), placeholder: '', label: "Bitiş Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                    </div>
                    <div id="onlineSayacSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlineSayacResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlineSayacSorgula">
                        <i class="bx bx-search me-1"></i> Sorgula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Online Mühürleme Sorgulama Modal -->
<div class="modal fade" id="importOnlineMuhurlemeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Mühürleme İşlemleri Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlineMuhurlemeForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Mühürleme işlemleri için online sorgulama yapılacaktır.
                        (Tanımlı olan ücretli tüm iş emri sonuçları otomatik olarak çekilir)
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'ilk_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "İlk Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'number', name: 'son_firma', value: $_SESSION['firma_kodu'] ?? '17', placeholder: '', label: "Son Firma", icon: "briefcase", required: true, class: "form-control"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'baslangic_tarihi', value: date('d.m.Y'), placeholder: '', label: "Başlangıç Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(type: 'text', name: 'bitis_tarihi', value: date('d.m.Y'), placeholder: '', label: "Bitiş Tarihi", icon: "calendar", required: true, class: "form-control flatpickr"); ?>
                            </div>
                        </div>
                    </div>
                    <div id="onlineMuhurlemeSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlineMuhurlemeResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlineMuhurlemeSorgula">
                        <i class="bx bx-search me-1"></i> Sorgula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    body {
        overflow-x: hidden;
    }

    /* Ana mod sekmeleri (Özet Raporlar / Karşılaştırma) */
    .main-mode-btn {
        padding: 7px 18px;
        border-radius: 8px;
        border: 1.5px solid var(--bs-border-color, #e2e5e9);
        background: var(--bs-card-bg, #fff);
        color: var(--bs-body-color, #6c757d);
        font-size: 13px;
        transition: all 0.25s ease;
    }

    .main-mode-btn:hover {
        border-color: #5156be;
        color: #5156be;
        background: rgba(81, 86, 190, 0.04);
    }

    .main-mode-btn.active {
        background: linear-gradient(135deg, #5156be 0%, #3f43a0 100%);
        border-color: #5156be;
        color: #fff !important;
        box-shadow: 0 3px 10px rgba(81, 86, 190, 0.3);
    }

    [data-bs-theme="dark"] .main-mode-btn {
        background: #282f36;
        border-color: #3b445e;
        color: #adb5bd;
    }

    [data-bs-theme="dark"] .main-mode-btn.active {
        background: linear-gradient(135deg, #5156be 0%, #3f43a0 100%);
        border-color: #5156be;
        color: #fff !important;
    }



    .accordion-button:not(.collapsed) {
        background-color: transparent;
        color: #556ee6;
        box-shadow: none;
    }

    .accordion-button {
        box-shadow: none !important;
    }

    /* Kacak Quick Entry Cell Selection */
    .kacak-quick-cell {
        transition: all 0.2s ease;
    }

    .kacak-quick-cell:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.15) !important;
    }

    .kacak-cell-selected {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
        border-radius: 4px;
    }

    .fullscreen-mode {
        background: #f4f5f8 !important;
        padding: 20px !important;
        display: flex !important;
        flex-direction: column !important;
        height: 100vh !important;
        width: 100% !important;
    }

    [data-bs-theme="dark"] .fullscreen-mode {
        background: #191e22 !important;
    }

    [data-bs-theme="dark"] .action-button-container {
        background-color: #282f36 !important;
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] .report-legend {
        background: #282f36 !important;
        border-color: #32394e !important;
    }

    [data-bs-theme="dark"] .legend-item {
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    [data-bs-theme="dark"] .legend-code {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #adb5bd !important;
    }

    [data-bs-theme="dark"] .filter-summary-badge {
        background: #32394e !important;
        border-color: #3b445e !important;
    }

    [data-bs-theme="dark"] .filter-summary-badge .badge-label {
        background: rgba(0, 0, 0, 0.2) !important;
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
    }

    .fullscreen-mode .table-responsive {
        max-height: none !important;
        flex: 1;
        overflow: auto !important;
    }

    .report-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        padding: 8px 12px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 10px;
        font-size: 11px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }

    .legend-code {
        font-weight: bold;
        color: var(--bs-primary, #556ee6);
        background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.15);
        padding: 2px 5px;
        border-radius: 3px;
        min-width: 25px;
        text-align: center;
    }

    .legend-item {
        border: 1px solid rgba(var(--bs-primary-rgb, 85, 110, 230), 0.3);
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .legend-item:hover {
        background: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.1);
        border-color: var(--bs-primary, #556ee6);
    }

    #raporTabs.nav-tabs-custom {
        border-bottom: none !important;
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    #raporTabs.nav-tabs-custom .nav-item {
        margin-bottom: 0 !important;
    }

    #raporTabs.nav-tabs-custom .nav-link {
        border: none !important;
        text-decoration: none !important;
        box-shadow: none !important;
        padding: 8px 16px;
        color: var(--bs-body-color, #74788d);
        font-weight: 500;
        transition: all 0.2s ease;
        border-radius: 8px !important;
        background-color: transparent;
    }

    #raporTabs.nav-tabs-custom .nav-link:hover {
        background-color: rgba(var(--bs-primary-rgb, 85, 110, 230), 0.05);
        color: var(--bs-primary);
    }

    #raporTabs.nav-tabs-custom .nav-link.active {
        color: #fff !important;
        background-color: #2a3042 !important;
        /* Dason dark style background */
        border: none !important;
    }

    [data-bs-theme="dark"] #raporTabs.nav-tabs-custom .nav-link.active {
        background-color: var(--bs-primary, #1c84ee) !important;
    }

    [data-bs-theme="dark"] #raporTabs.nav-tabs-custom .nav-link {
        color: #adb5bd !important;
    }

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

    /* Context Menu Styles */
    .kacak-context-menu {
        position: fixed;
        z-index: 10000;
        display: none;
        min-width: 150px;
        background-color: var(--bs-card-bg, #fff);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        padding: 5px 0;
    }

    .kacak-context-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        cursor: pointer;
        font-size: 13px;
        color: var(--bs-body-color, #495057);
        transition: all 0.2s ease;
    }

    .kacak-context-menu-item:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--bs-primary);
    }

    .kacak-context-menu-item.text-danger:hover {
        background-color: rgba(var(--bs-danger-rgb), 0.1);
        color: var(--bs-danger);
    }

    .kacak-context-menu-item i {
        font-size: 16px;
    }

    .kacak-context-menu-divider {
        height: 1px;
        background-color: var(--bs-border-color, #dee2e6);
        margin: 5px 0;
    }
</style>

<!-- Kaçak Kontrol Sağ Tık Menüsü -->
<div id="kacakContextMenu" class="kacak-context-menu">
    <div class="kacak-context-menu-item" onclick="handleKacakContextAction('edit')">
        <i class="bx bx-edit"></i> Düzenle / Ekle
    </div>
    <div class="kacak-context-menu-divider"></div>
    <div class="kacak-context-menu-item text-danger" onclick="handleKacakContextAction('delete')">
        <i class="bx bx-trash"></i> Bu Hücreyi Sil
    </div>
</div>

<div class="modal fade" id="reportSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Raporlama Ekip Aralığı Ayarları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportSettingsForm">
                <input type="hidden" name="action" value="report-settings-kaydet">
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bx bx-info-circle me-1"></i> Ekip aralıklarını belirleyin.
                        <br><small>Birden fazla aralık için virgül kullanın (Örn: 1-40, 101-200)</small>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3" data-settings-group="kesme">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kesme', $reportSettings['ekip_aralik_kesme'] ?? '1-40', '1-40', 'Kesme/Açma Ekibi (Genel)', 'bx bx-group') ?>
                        </div>
                        <div class="col-md-6 mb-3" data-settings-group="kesme">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kesme_merkez', $reportSettings['ekip_aralik_kesme_merkez'] ?? '1-30', '1-30', '↳ Kesme (Merkez)', 'bx bx-map-pin') ?>
                        </div>
                        <div class="col-md-6 mb-3" data-settings-group="kesme">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kesme_ilce', $reportSettings['ekip_aralik_kesme_ilce'] ?? '31-40', '31-40', '↳ Kesme (İlçe)', 'bx bx-map-alt') ?>
                        </div>
                        <div class="col-md-12 mb-3" data-settings-group="kesme">
                            <label class="form-label fw-bold" style="font-size: 13px;">Düşülecek İş Türü</label>
                            <?php echo Form::FormSelect2("dusulecek_is_turu", $kesmeIsTurleriOptions, $reportSettings['dusulecek_is_turu'] ?? 'Ödeme Yaptırıldı', "Düşülecek İş Türü Seçiniz", "bx bx-minus-circle", "key", "", "form-select select2"); ?>
                        </div>
                        <div class="col-md-12 mb-3" data-settings-group="sokme_takma">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_sayac_degisimi', $reportSettings['ekip_aralik_sayac_degisimi'] ?? '41-50', '41-50', 'Sayaç Değişimi Ekibi', 'bx bx-reset') ?>
                        </div>
                        <div class="col-md-12 mb-3" data-settings-group="kacakkontrol">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kacak_kontrol', $reportSettings['ekip_aralik_kacak_kontrol'] ?? '51-60', '51-60', 'Kaçak Kontrol Ekibi', 'bx bx-search-alt') ?>
                        </div>
                        <div class="col-md-12 mb-3" data-settings-group="okuma">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_okuma', $reportSettings['ekip_aralik_okuma'] ?? '101-200', '101-200', 'Endeks Okuma Ekibi', 'bx bx-bullseye') ?>
                        </div>
                        <div class="col-md-12 mb-3" data-settings-group="muhurleme">
                            <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_muhurleme', $reportSettings['ekip_aralik_muhurleme'] ?? '1-100', '1-100', 'Mühürleme Ekibi', 'bx bx-lock') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveReportSettings">Ayarları Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Tab özelinde ayarlar butonu tetikleyici (Dinamik butonlar için delegate)
        $(document).on('click', '.btn-tab-settings', function () {
            var tab = $(this).data('tab');
            var tabName = $(this).data('tab-name');

            $('#reportSettingsModal .modal-title').text(tabName + ' Ayarları');
            $('#reportSettingsModal [data-settings-group]').hide();
            $('#reportSettingsModal [data-settings-group="' + tab + '"]').show();

            $('#reportSettingsModal').modal('show');

            // Select2 dropdown clipping fix
            setTimeout(function () {
                $('#reportSettingsModal .select2').select2({
                    dropdownParent: $('#reportSettingsModal'),
                    width: '100%'
                });
            }, 200);
        });

        $('#reportSettingsForm').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#btnSaveReportSettings');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Başarılı', res.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'İşlem sırasında bir hata oluştu.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Hata', 'Sunucuya bağlanılamadı.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).text('Kaydet');
                }
            });
        });

        // Context Menu Logic
        let selectedCellData = null;

        $(document).on('contextmenu', '.kacak-quick-cell', function (e) {
            e.preventDefault();

            // Remove previous selection
            $('.kacak-quick-cell').removeClass('kacak-cell-selected');
            // Add selection to current cell
            $(this).addClass('kacak-cell-selected');

            selectedCellData = {
                tarih: $(this).attr('data-date'),
                pIds: $(this).attr('data-personel-ids'),
                ekipAdi: $(this).attr('data-ekip-adi'),
                sayi: $(this).text().trim(),
                el: $(this)
            };

            const menu = $('#kacakContextMenu');
            const menuWidth = menu.outerWidth();
            const menuHeight = menu.outerHeight();
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();

            let left = e.pageX;
            let top = e.pageY;

            if (left + menuWidth > windowWidth) left = left - menuWidth;
            if (top + menuHeight > windowHeight) top = top - menuHeight;

            menu.css({
                left: left,
                top: top
            }).fadeIn(100);
        });

        $(document).on('click', function (e) {
            $('#kacakContextMenu').fadeOut(100);
            if (!$(e.target).closest('.kacak-quick-cell').length) {
                // $('.kacak-quick-cell').removeClass('kacak-cell-selected');
            }
        });

        window.handleKacakContextAction = function (action) {
            if (!selectedCellData) return;

            if (action === 'edit') {
                window.openKacakModal(selectedCellData.tarih, selectedCellData.pIds, selectedCellData.sayi, selectedCellData.ekipAdi);
            } else if (action === 'delete') {
                if (!selectedCellData.sayi || selectedCellData.sayi == 0) {
                    Swal.fire('Bilgi', 'Silinecek veri bulunamadı.', 'info');
                    return;
                }

                Swal.fire({
                    title: 'Emin misiniz?',
                    text: selectedCellData.tarih + " tarihindeki " + selectedCellData.ekipAdi + " kaydı silinecek!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Evet, Sil!',
                    cancelButtonText: 'Vazgeç'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'views/puantaj/api.php',
                            type: 'POST',
                            data: {
                                action: 'kacak-hucre-sil',
                                tarih: selectedCellData.tarih,
                                personel_ids: selectedCellData.pIds,
                                ekip_adi: selectedCellData.ekipAdi
                            },
                            success: function (response) {
                                try {
                                    const res = JSON.parse(response);
                                    if (res.status === 'success') {
                                        Swal.fire('Başarılı', 'Kayıt başarıyla silindi.', 'success');
                                        loadReport(); // Refresh report
                                    } else {
                                        Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                                    }
                                } catch (e) {
                                    Swal.fire('Hata', 'İşlem sırasında hata oluştu.', 'error');
                                }
                            }
                        });
                    }
                });
            }
            $('#kacakContextMenu').fadeOut(100);
        };
    });
</script>