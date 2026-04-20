<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\EndeksOkumaModel;
use App\Model\SayacDegisimModel;

$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$ekipKodu = $_GET['ekip_kodu'] ?? '';
$workType = $_GET['work_type'] ?? '';
$region = $_GET['region'] ?? '';
$defter = $_GET['defter'] ?? '';


//Helper::dd([$startDate, $endDate, $ekipKodu, $workType]);

// $records = $Puantaj->getFiltered($startDate, $endDate, $ekipKodu, $workType);
$personeller = $Personel->all(false, 'puantaj');
$personelOptions = ['' => 'Seçiniz'];
$personelOptionsMultiple = []; // Kaçak kontrol multiple select için boş değer olmadan
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
    $personelOptionsMultiple[$p->id] = $p->adi_soyadi;
}

$workTypes = $Puantaj->getWorkTypes();
$workTypeOptions = ['' => 'Tüm İşler'];
foreach ($workTypes as $wt) {
    $workTypeOptions[$wt] = $wt;
}


$workResults = $Puantaj->getWorkResults();
$workResultOptions = ['' => 'Tüm Sonuçlar'];
foreach ($workResults as $wr) {
    if ($wr)
        $workResultOptions[$wr] = $wr;
}

$Tanimlar = new \App\Model\TanimlamalarModel();
$regionList = $Tanimlar->getEkipBolgeleri();
$regionOptions = ['' => 'Tüm Bölgeler'];
foreach ($regionList as $r) {
    $regionOptions[$r] = $r;
}

$defterList = $Tanimlar->getDefterKodlari();
$defterOptions = ['' => 'Tüm Defterler'];
foreach ($defterList as $d) {
    if ($d)
        $defterOptions[$d] = $d;
}


// PHP tarafında aktif sekmeyi belirle (URL -> Storage (yok) -> Varsayılan)
$activeTab = $_GET['tab'] ?? 'okuma';
?>
<style>
    /* Tab-Specific Preloader */
    .tab-loader-wrapper {
        position: relative;
        min-height: 400px;
    }

    .tab-preloader {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        z-index: 100;
        backdrop-filter: blur(2px);
        display: none; /* JS ile kontrol edilecek */
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    [data-bs-theme="dark"] .tab-preloader {
        background: rgba(25, 30, 34, 0.8);
    }

    .tab-preloader .loader-content {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        text-align: center;
        min-width: 200px;
    }

    [data-bs-theme="dark"] .tab-preloader .loader-content {
        background: #2a3042;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    }

    .table-loading #puantajTabContent {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Premium Stat Styles */
    /* Premium Mini-Card Stat Styles */
    .stat-mini-card {
        display: inline-flex;
        align-items: center;
        background: #ffffff;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-left: 4px solid #ced4da;
        padding: 8px 18px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        gap: 12px;
        vertical-align: middle;
        transition: all 0.3s ease;
    }

    [data-bs-theme="dark"] .stat-mini-card {
        background: #2a3042;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .stat-mini-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .stat-mini-card.stat-primary { border-left-color: #5156be; }
    .stat-mini-card.stat-success { border-left-color: #2ab57d; }
    .stat-mini-card.stat-info { border-left-color: #4ba6ef; }
    .stat-mini-card.stat-warning { border-left-color: #ffbf53; }
    .stat-mini-card.stat-secondary { border-left-color: #74788d; }

    .stat-mini-card .icon-wrap {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 1.1rem;
    }

    .stat-primary .icon-wrap { background: rgba(81, 86, 190, 0.1); color: #5156be; }
    .stat-success .icon-wrap { background: rgba(42, 181, 125, 0.1); color: #2ab57d; }
    .stat-info .icon-wrap { background: rgba(75, 166, 239, 0.1); color: #4ba6ef; }
    .stat-warning .icon-wrap { background: rgba(255, 191, 83, 0.1); color: #ffbf53; }
    .stat-secondary .icon-wrap { background: rgba(116, 120, 141, 0.1); color: #74788d; }

    .stat-mini-card .value {
        font-size: 1.25rem;
        font-weight: 800;
        color: #212529;
    }

    [data-bs-theme="dark"] .stat-mini-card .value {
        color: #eff2f7;
    }

    .stat-mini-card .label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        margin-left: 2px;
    }

    .summary-card-item .card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    .summary-card-item:hover .card {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.1) !important;
        border-color: var(--vz-primary) !important;
    }

    .summary-card-item .status-label {
        letter-spacing: 0.025em;
        font-weight: 700;
    }

    .summary-card-item .main-value {
        font-size: 1.4rem;
        color: #2a3042;
    }

    [data-bs-theme="dark"] .summary-card-item .main-value {
        color: #eff2f7;
    }

    .summary-card-item .sub-badge {
        font-weight: 600;
        padding: 4px 10px;
    }

    /* Animation for total counts */
    @keyframes count-pop {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .count-animate {
        animation: count-pop 0.5s ease-out;
    }
    /* Accordion Header Improvements */
    #filterAccordion .accordion-button {
        background-color: transparent !important;
        box-shadow: none !important;
        padding-right: 3rem !important; /* Space for arrow */
    }

    #filterAccordion .accordion-button::after {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        margin-top: 0;
    }

    #filterAccordion .accordion-button:not(.collapsed)::after {
        transform: translateY(-50%) rotate(-180deg);
    }

    #filterAccordion .nav-tabs-custom .nav-link {
        padding: 0.6rem 1.2rem;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        color: var(--vz-body-color);
        transition: all 0.2s ease;
    }

    #filterAccordion .nav-tabs-custom .nav-link:hover {
        color: var(--vz-primary);
    }

    #filterAccordion .nav-tabs-custom .nav-link.active {
        color: var(--vz-primary);
        background-color: rgba(var(--vz-primary-rgb), 0.1);
        border-radius: 6px;
    }

    [data-bs-theme="dark"] #filterAccordion .nav-tabs-custom .nav-link.active {
        background-color: rgba(var(--vz-primary-rgb), 0.2);
    }

    /* Ticker Tape Styles */
    .ticker-container {
        background: #fff;
        border: 1px solid rgba(240, 101, 72, 0.2);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        height: 40px;
        box-shadow: 0 4px 12px rgba(240, 101, 72, 0.08);
        border-left: 4px solid #f06548;
    }
    [data-bs-theme="dark"] .ticker-container {
        background: #2a3042;
        border-color: rgba(240, 101, 72, 0.3);
    }
    .ticker-label {
        background: #f06548;
        color: white;
        padding: 0 15px;
        height: 100%;
        display: flex;
        align-items: center;
        font-weight: 800;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        z-index: 2;
        white-space: nowrap;
    }
    .ticker-content {
        flex-grow: 1;
        overflow: hidden;
    }
    .ticker-item {
        display: inline-block;
        margin-right: 40px;
        font-weight: 600;
        color: #f06548;
        cursor: pointer;
        font-size: 13px;
    }
    .ticker-item:hover {
        text-decoration: underline;
    }
    marquee {
        vertical-align: middle;
        padding-top: 4px;
    }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "İş Takip Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Riskli İşlemler Bantı -->
    <div id="riskyOperationsTicker" class="ticker-container" style="display: none; cursor: pointer;">
        <div class="ticker-label">
            <i class="ri-error-warning-fill fs-14 me-2"></i> RİSKLİ İŞLEMLER
        </div>
        <div class="ticker-content" onclick="$('#riskyPersonnelModal').modal('show');">
            <marquee behavior="scroll" direction="left" scrollamount="6" id="riskyMarquee" onmouseover="this.stop();" onmouseout="this.start();">
                <!-- Risky personnel will be injected here -->
            </marquee>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="accordion" id="filterAccordion">
                        <div class="accordion-item border-0">
                            <div class="accordion-header" id="headingOne">
                                <div class="accordion-button collapsed py-1 d-flex align-items-center justify-content-between" 
                                    id="filterAccordionHeader" aria-expanded="false" aria-controls="collapseOne" style="cursor: pointer;">
                                    
                                    <div class="d-flex align-items-center flex-grow-1">
                                        <ul class="nav nav-tabs nav-tabs-custom nav-success border-bottom-0" role="tablist" id="puantajTabs">
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeTab === 'okuma' ? 'active' : '' ?>" data-bs-toggle="tab" href="#okuma"
                                                    role="tab" data-tab-name="okuma" onclick="event.stopPropagation();">
                                                    <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                                    <span class="d-none d-sm-block">Okuma İşlemleri</span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeTab === 'yapilan_isler' ? 'active' : '' ?>" data-bs-toggle="tab"
                                                    href="#yapilan_isler" role="tab" data-tab-name="yapilan_isler" onclick="event.stopPropagation();">
                                                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                                    <span class="d-none d-sm-block">Kesme/Açma İşlem.</span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeTab === 'sayac_sokme_takma' ? 'active' : '' ?>" data-bs-toggle="tab"
                                                    href="#sayac_sokme_takma" role="tab" data-tab-name="sayac_sokme_takma" onclick="event.stopPropagation();">
                                                    <span class="d-block d-sm-none"><i class="fas fa-exchange-alt"></i></span>
                                                    <span class="d-none d-sm-block">Sayaç Sökme Takma</span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeTab === 'muhurleme' ? 'active' : '' ?>" data-bs-toggle="tab"
                                                    href="#muhurleme" role="tab" data-tab-name="muhurleme" onclick="event.stopPropagation();">
                                                    <span class="d-block d-sm-none"><i class="fas fa-lock"></i></span>
                                                    <span class="d-none d-sm-block">Mühürleme</span>
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link <?= $activeTab === 'kacak_kontrol' ? 'active' : '' ?>" data-bs-toggle="tab"
                                                    href="#kacak_kontrol" role="tab" data-tab-name="kacak_kontrol" onclick="event.stopPropagation();">
                                                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                                    <span class="d-none d-sm-block">Kaçak Kontrol</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="d-flex align-items-center gap-3 ms-auto me-2">
                                        <!-- Tarih Aralığı / Dönem Toggle -->
                                        <div class="btn-group bg-light p-1 rounded-pill" role="group" id="dateFilterTypeGroup" style="height: 34px;">
                                            <input type="radio" class="btn-check" name="dateFilterType" id="dateFilterTypeRange" value="range" checked>
                                            <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 d-flex align-items-center fs-11 fw-bold" for="dateFilterTypeRange">Tarih Aralığı</label>
                                            
                                            <input type="radio" class="btn-check" name="dateFilterType" id="dateFilterTypePeriod" value="period">
                                            <label class="btn btn-sm btn-outline-primary border-0 rounded-pill px-3 d-flex align-items-center fs-11 fw-bold" for="dateFilterTypePeriod">Dönem</label>
                                        </div>
                                        <div id="filterSummary" class="d-none d-md-flex gap-2">
                                            <!-- JS ile doldurulacak -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                        data-bs-parent="#filterAccordion">
                        <div class="accordion-body pt-3">
                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="p" value="puantaj/veri-yukleme">
                                <input type="hidden" name="tab" id="activeTabInput"
                                    value="<?= $_GET['tab'] ?? 'okuma' ?>">
                                <div class="row g-2">
                                    <div class="col-md-2 date-range-input">
                                        <?php echo Form::FormFloatInput(
                                            type: 'text',
                                            name: 'start_date',
                                            value: $startDate,
                                            placeholder: '',
                                            label: "Başlangıç Tarihi",
                                            icon: "calendar",
                                            class: "form-control flatpickr",
                                        ); ?>
                                    </div>
                                    <div class="col-md-2 date-range-input">
                                        <?php echo Form::FormFloatInput(
                                            type: 'text',
                                            name: 'end_date',
                                            value: $endDate,
                                            placeholder: '',
                                            label: "Bitiş Tarihi",
                                            icon: "calendar",
                                            class: "form-control flatpickr",
                                        ); ?>
                                    </div>
                                    <div class="col-md-4 date-period-input d-none">
                                        <?php echo Form::FormFloatInput(
                                            type: 'text',
                                            name: 'period_month',
                                            value: '',
                                            placeholder: 'Dönem Seçiniz',
                                            label: "Dönem (Ay-Yıl)",
                                            icon: "calendar",
                                            class: "form-control",
                                            readonly: true
                                        ); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?php echo Form::FormSelect2('region', $regionOptions, $region, 'Bölge', 'globe', 'key', '', 'form-select select2'); ?>
                                    </div>
                                    <div class="col-md-2" id="defterFilterContainer" style="display: <?= $activeTab === 'okuma' ? 'block' : 'none' ?>;">
                                        <?php echo Form::FormSelect2('defter', $defterOptions, $defter, 'Defter', 'book', 'key', '', 'form-select select2'); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?php echo Form::FormSelect2('ekip_kodu', $personelOptions, $ekipKodu, 'Personel', 'user', 'key', '', 'form-select select2'); ?>
                                    </div>
                                    <div class="col-md-2" id="workTypeFilterContainer"
                                        style="display: <?= $activeTab === 'yapilan_isler' ? 'block' : 'none' ?>;">
                                        <?php echo Form::FormSelect2(
                                            name: 'work_type',
                                            options: $workTypeOptions,
                                            selectedValue: $workType,
                                            textField: "",
                                            label: "Yapılan İş",
                                            icon: "briefcase",
                                            valueField: "key"
                                        ); ?>
                                    </div>
                                    <div class="col-md-2" id="workResultFilterContainer"
                                        style="display: <?= $activeTab === 'yapilan_isler' ? 'block' : 'none' ?>;">
                                        <?php echo Form::FormSelect2(
                                            name: 'work_result',
                                            options: $workResultOptions,
                                            selectedValue: $_GET['work_result'] ?? '',
                                            textField: "",
                                            label: "İş Sonucu",
                                            icon: "check-circle",
                                            valueField: "key"
                                        ); ?>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div
                                            class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 w-100">
                                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-bold">
                                                <i class="mdi mdi-filter-variant me-1"></i> Filtrele
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
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div id="puantajMainWrapper" style="opacity: 0;">

        <div class="tab-content position-relative" id="puantajTabContent">
            <!-- Tab Preloader (Shared) -->
            <div class="tab-preloader" id="tab-loader">
                <div class="loader-content">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="sr-only">Yükleniyor...</span>
                    </div>
                    <h6 class="mb-0">Veriler Yükleniyor...</h6>
                </div>
            </div>
            <div class="tab-pane <?= $activeTab === 'okuma' ? 'active' : '' ?>" id="okuma" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title" id="endeksTableTitle">Endeks Okuma Raporu</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                id="btnShowStats">
                                <i class="mdi mdi-chart-box-outline fs-5 me-1"></i> İstatistikler
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#importOnlineIcmalRaporuModal">
                                <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Online Sorgula
                            </button>
                            <div class="dropdown ms-2">
                                <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="okumaIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                                    <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="okumaIslemlerDropdown">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportEndeksExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-primary fw-medium" type="button" data-bs-toggle="modal" data-bs-target="#importEndeksModal">
                                            <i class="mdi mdi-upload fs-5 me-2"></i> Dosya Yükle
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Sayaç Durum Özetleri -->
                    <div id="sayacDurumSummary" class="card-body bg-light-subtle border-bottom py-3" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <h6 class="fs-12 text-uppercase fw-semibold text-muted mb-0"><i class="bx bx-stats me-1"></i> Sayaç Durumu İstatistikleri</h6>
                            <button class="btn btn-sm btn-link text-decoration-none d-none summary-toggle-btn" id="btnToggleOtherStatuses" data-active-target="#sayacDurumOtherSummaryContainer">
                                <span class="show-text">Tümünü Göster</span>
                                <span class="hide-text d-none">Daralt</span>
                            </button>
                        </div>
                        <div class="row g-2" id="sayacDurumSummaryContainer"></div>
                        <div class="row g-2 mt-2 d-none" id="sayacDurumOtherSummaryContainer"></div>
                    </div>
                    <div class="card-body">
                        <table id="endeksTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="select">Defter</th>
                                    <th data-filter="select">Bölgesi</th>
                                    <th data-filter="string">Ekip No</th>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="number">Abone Sayısı</th>
                                    <th data-filter="select">Sayaç Durumu</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody id="okumaBody">
                                <!-- AJAX Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane <?= $activeTab === 'yapilan_isler' ? 'active' : '' ?>" id="yapilan_isler"
                role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title" id="puantajTableTitle">İş Listesi</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                id="btnShowPuantajStats">
                                <i class="mdi mdi-chart-box-outline fs-5 me-1"></i> İstatistikler
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#importOnlinePuantajModal">
                                <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Online Sorgula
                            </button>
                            <div class="dropdown ms-2">
                                <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="puantajIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                                    <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="puantajIslemlerDropdown">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportPuantajExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-danger fw-medium" type="button" id="btnBulkDeletePuantaj">
                                            <i class="mdi mdi-trash-can-outline fs-5 me-2"></i> Seçilenleri Sil
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-primary fw-medium" type="button" data-bs-toggle="modal" data-bs-target="#importPuantajModal">
                                            <i class="mdi mdi-upload fs-5 me-2"></i> Excel Yükle
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Kesme/Açma Özetleri -->
                    <div id="puantajSummary" class="card-body bg-light-subtle border-bottom py-3" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <h6 class="fs-12 text-uppercase fw-semibold text-muted mb-0"><i class="bx bx-stats me-1"></i> Kesme/Açma İstatistikleri</h6>
                            <button class="btn btn-sm btn-link text-decoration-none d-none summary-toggle-btn" id="btnToggleOtherPuantajStatus" data-active-target="#puantajOtherSummaryContainer">
                                <span class="show-text">Tümünü Göster</span>
                                <span class="hide-text d-none">Daralt</span>
                            </button>
                        </div>
                        <div class="row g-2" id="puantajSummaryContainer"></div>
                        <div class="row g-2 mt-2 d-none" id="puantajOtherSummaryContainer"></div>
                    </div>
                    <div class="card-body">
                        <table id="puantajTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th style="width: 20px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="checkAllPuantaj">
                                        </div>
                                    </th>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Ekip Kodu</th>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="select">İş Emri Tipi</th>
                                    <th data-filter="select">İş Emri Sonucu</th>
                                    <th data-filter="number">Sonuçlanmış</th>
                                    <th data-filter="number">Açık Olanlar</th>
                                    <th>İşlem</th>
                                </tr>

                            </thead>
                            <tbody id="yapilanIslerBody">
                                <!-- AJAX Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane <?= $activeTab === 'kacak_kontrol' ? 'active' : '' ?>" id="kacak_kontrol"
                role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title" id="kacakTableTitle">Kaçak Kontrol Listesi</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                id="btnNewKacak">
                                <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni Ekle
                            </button>
                            <div class="dropdown ms-2">
                                <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="kacakIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                                    <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="kacakIslemlerDropdown">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportKacakExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-primary fw-medium" type="button" data-bs-toggle="modal" data-bs-target="#importKacakModal">
                                            <i class="mdi mdi-upload fs-5 me-2"></i> Excel Yükle
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="kacakTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Ekip (Personel)</th>
                                    <th data-filter="number">Sayı</th>
                                    <th data-filter="string">Açıklama</th>
                                    <th>İşlem</th>
                                </tr>

                            </thead>
                            <tbody id="kacakKontrolBody">
                                <!-- AJAX Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane <?= $activeTab === 'sayac_sokme_takma' ? 'active' : '' ?>" id="sayac_sokme_takma"
                role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title" id="sayacTableTitle">Sayaç Sökme Takma Listesi</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                id="btnShowSayacStats">
                                <i class="mdi mdi-chart-box-outline fs-5 me-1"></i> İstatistikler
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button"
                                class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#importOnlineSayacDegisimModal">
                                <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Online Sorgula
                            </button>
                            <div class="dropdown ms-2">
                                <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="sayacIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                                    <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="sayacIslemlerDropdown">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportSayacExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Sayaç Değişim Özetleri -->
                    <div id="sayacDegisimSummary" class="card-body bg-light-subtle border-bottom py-3" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <h6 class="fs-12 text-uppercase fw-semibold text-muted mb-0"><i class="bx bx-stats me-1"></i> Sayaç Değişim İstatistikleri</h6>
                            <button class="btn btn-sm btn-link text-decoration-none d-none summary-toggle-btn" id="btnToggleOtherSayacStatus" data-active-target="#sayacDegisimOtherSummaryContainer">
                                <span class="show-text">Tümünü Göster</span>
                                <span class="hide-text d-none">Daralt</span>
                            </button>
                        </div>
                        <div class="row g-2" id="sayacDegisimSummaryContainer"></div>
                        <div class="row g-2 mt-2 d-none" id="sayacDegisimOtherSummaryContainer"></div>
                    </div>
                    <div class="card-body">
                        <table id="sayacDegisimTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th data-filter="date">Kayıt Tarihi</th>
                                    <th data-filter="string">Ekip</th>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="string">Bölge</th>
                                    <th data-filter="select">İş Emri Sebebi</th>
                                    <th data-filter="select">İş Emri Sonucu</th>
                                    <th data-filter="string">Abone No</th>
                                    <th data-filter="string">Takılan Sayaç No</th>
                                    <th>İşlem</th>
                                </tr>

                            </thead>
                            <tbody id="sayacDegisimBody">
                                <!-- AJAX Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane <?= $activeTab === 'muhurleme' ? 'active' : '' ?>" id="muhurleme" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title" id="muhurlemeTableTitle">Mühürleme İş Listesi</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button"
                                class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                                data-bs-toggle="modal" data-bs-target="#importOnlinePuantajModal"
                                id="btnOnlineMuhurlemeSorgula">
                                <i class="mdi mdi-cloud-search-outline fs-5 me-1"></i> Online Sorgula
                            </button>
                            <div class="dropdown ms-2">
                                <button class="btn btn-soft-primary btn-sm px-3 fw-bold dropdown-toggle d-flex align-items-center" type="button" id="muhurlemeIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 6px 12px;">
                                    <i class="bx bx-cog fs-5 me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="muhurlemeIslemlerDropdown">
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-success fw-medium" type="button" id="btnExportMuhurlemeExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-2"></i> Excel'e Aktar
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item d-flex align-items-center text-danger fw-medium" type="button" id="btnBulkDeleteMuhurleme">
                                            <i class="mdi mdi-trash-can-outline fs-5 me-2"></i> Seçilenleri Sil
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Mühürleme Özetleri -->
                    <div id="muhurlemeSummary" class="card-body bg-light-subtle border-bottom py-3" style="display: none;">
                        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                            <h6 class="fs-12 text-uppercase fw-semibold text-muted mb-0"><i class="bx bx-stats me-1"></i> Mühürleme İstatistikleri</h6>
                            <button class="btn btn-sm btn-link text-decoration-none d-none summary-toggle-btn" id="btnToggleOtherMuhurlemeStatus" data-active-target="#muhurlemeOtherSummaryContainer">
                                <span class="show-text">Tümünü Göster</span>
                                <span class="hide-text d-none">Daralt</span>
                            </button>
                        </div>
                        <div class="row g-2" id="muhurlemeSummaryContainer"></div>
                        <div class="row g-2 mt-2 d-none" id="muhurlemeOtherSummaryContainer"></div>
                    </div>
                    <div class="card-body">
                        <table id="muhurlemeTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th style="width: 20px;">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="checkAllMuhurleme">
                                        </div>
                                    </th>
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Ekip Kodu</th>
                                    <th data-filter="string">Personel</th>
                                    <th data-filter="select">İş Emri Tipi</th>
                                    <th data-filter="select">İş Emri Sonucu</th>
                                    <th data-filter="number">Sonuçlanmış</th>
                                    <th data-filter="number">Açık Olanlar</th>
                                    <th>İşlem</th>
                                </tr>

                            </thead>
                            <tbody id="muhurlemeBody">
                                <!-- AJAX Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Endeks Excel Modal -->
<div class="modal fade" id="importEndeksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Endeks Okuma Excel Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="endeksUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Endeks Okuma İşlemleri Günlük olarak yüklenmesi gerekmektedir. Yüklediğiniz dosyadaki işlemler
                        seçili tarih işlemleri olarak kaydedilecektir.
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'text',
                            name: 'upload_date',
                            value: Date::today(),
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
                            label: "Excel veya PDF Dosyası",
                            icon: "file",
                            required: true,
                            class: "form-control"
                        ); ?>
                        <div class="form-text">Desteklenen formatlar: .xlsx, .xls, .pdf</div>
                    </div>
                    <div id="endeksSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Yükleniyor...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnEndeksUpload">Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Puantaj Excel Modal -->
<div class="modal fade" id="importPuantajModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Puantaj Excel Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="puantajUploadForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Lütfen "Ekip Bazında İş Emri Sonuçları Raporu" formatındaki Excel dosyasını yükleyiniz.
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput(
                            type: 'text',
                            name: 'upload_date',
                            value: Date::today(),
                            placeholder: '',
                            label: "Tarih",
                            icon: "calendar",
                            required: true,
                            class: "form-control flatpickr"
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFileInput(
                            name: 'excel_file',
                            label: "Excel Dosyası (.xlsx, .xls)",
                            icon: "file",
                            required: true,
                            class: "form-control"
                        ); ?>
                    </div>
                    <div id="puantajSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Yükleniyor...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnPuantajUpload">Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>
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
                        Excel dosyasında "Ekip", "Sayı" ve "Açıklama" sütunları bulunmalıdır. Tarih alanını aşağıdan
                        seçiniz.
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput(
                            type: 'text',
                            name: 'upload_date',
                            value: Date::today(),
                            placeholder: '',
                            label: "Tarih",
                            icon: "calendar",
                            required: true,
                            class: "form-control flatpickr"
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFileInput(
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


<!-- Online Sayaç Değişim Sorgulama Modal -->
<div class="modal fade" id="importOnlineSayacDegisimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Sayaç Değişim Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlineSayacDegisimForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Sayaç Sökme/Takma (Değişim) verilerini API üzerinden sorgulayarak sisteme aktarır.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'baslangic_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Başlangıç Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'bitis_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Bitiş Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
                            </div>
                        </div>
                    </div>
                    <div id="onlineSayacDegisimSpinner" class="text-center p-2" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlineSayacDegisimResult" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlineSayacDegisimSorgula">
                        <i class="bx bx-search me-1"></i> Sorgula
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kaçak Kontrol Manuel Modal -->
<div class="modal fade" id="kacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kacakModalTitle">Manuel Kaçak Kontrol Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kacakManualForm">
                <input type="hidden" name="id" id="kacak_id" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'text',
                            name: 'tarih',
                            value: Date::today(),
                            placeholder: '',
                            label: "Tarih",
                            icon: "calendar",
                            required: true,
                            class: "form-control flatpickr"
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <label for="kacak_personel_ids">Personel Seçimi(En Fazla 2 Personel)</label>
                        <?php echo Form::FormMultipleSelect2(
                            name: 'kacak_personel_ids',
                            options: $personeller,
                            selectedValues: [],
                            label: 'Personel',
                            icon: 'users',
                            valueField: 'id',
                            textField: 'adi_soyadi',
                            class: 'form-select select2',
                            required: true
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput(
                            type: 'number',
                            name: 'sayi',
                            value: '',
                            placeholder: '',
                            label: "Sayı",
                            icon: "hash",
                            required: true
                        ); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput(
                            type: 'text',
                            name: 'aciklama',
                            value: '',
                            placeholder: '',
                            label: "Açıklama",
                            icon: "file-text",
                            required: false
                        ); ?>
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

<!-- Endeks İstatistik Modal -->
<div class="modal fade" id="statsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bölge Bazlı Okuma İstatistikleri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="statsModalBody">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">İstatistikler hazırlanıyor...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
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
                                <?php echo Form::FormFloatInput(
                                    type: 'number',
                                    name: 'ilk_firma',
                                    value: $_SESSION['firma_kodu'] ?? '17',
                                    placeholder: '',
                                    label: "İlk Firma",
                                    icon: "briefcase",
                                    required: true,
                                    class: "form-control"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'number',
                                    name: 'son_firma',
                                    value: $_SESSION['firma_kodu'] ?? '17',
                                    placeholder: '',
                                    label: "Son Firma",
                                    icon: "briefcase",
                                    required: true,
                                    class: "form-control"
                                ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'baslangic_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Başlangıç Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'bitis_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Bitiş Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
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
                                <?php echo Form::FormFloatInput(
                                    type: 'number',
                                    name: 'ilk_firma',
                                    value: $_SESSION['firma_kodu'] ?? '17',
                                    placeholder: '',
                                    label: "İlk Firma",
                                    icon: "briefcase",
                                    required: true,
                                    class: "form-control"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'number',
                                    name: 'son_firma',
                                    value: $_SESSION['firma_kodu'] ?? '17',
                                    placeholder: '',
                                    label: "Son Firma",
                                    icon: "briefcase",
                                    required: true,
                                    class: "form-control"
                                ); ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'baslangic_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Başlangıç Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'bitis_tarihi',
                                    value: Date::today(),
                                    placeholder: '',
                                    label: "Bitiş Tarihi",
                                    icon: "calendar",
                                    required: true,
                                    class: "form-control flatpickr"
                                ); ?>
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



<!-- Risky Personnel Modal -->
<div class="modal fade zoomIn" id="riskyPersonnelModal" tabindex="-1" aria-labelledby="riskyPersonnelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger p-3">
                <h5 class="modal-title text-white" id="riskyPersonnelModalLabel">
                    <i class="ri-alarm-warning-line align-middle me-2"></i> Riskli İşlem Yapan Personeller
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-danger-subtle text-danger border-bottom border-danger-subtle">
                    <p class="mb-0 fs-13 fw-medium">
                        <i class="ri-information-fill me-1"></i> Bu liste, <strong>Evde Yok</strong> sayısının <strong>Sayaç Normal</strong> sayısına oranı <strong>%80'in</strong> üzerinde olan personelleri göstermektedir.
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-nowrap align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Personel</th>
                                <th scope="col">Ekip</th>
                                <th scope="col" class="text-center">Sayaç Normal</th>
                                <th scope="col" class="text-center">Evde Yok</th>
                                <th scope="col" class="text-center">Oran</th>
                                <th scope="col" class="text-center">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="riskyPersonnelTableBody">
                            <!-- Data will be injected here -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // Tarih filtresi toggle mantığı
        $('input[name="dateFilterType"]').on('change', function () {
            const type = $(this).val();
            if (type === 'period') {
                $('.date-range-input').addClass('d-none');
                $('.date-period-input').removeClass('d-none');
            } else {
                $('.date-range-input').removeClass('d-none');
                $('.date-period-input').addClass('d-none');
            }
        });

 
        // Accordion Manuel Toggle (Sekmeleri Hariç Tut)
        $('#filterAccordionHeader').on('click', function(e) {
            // Eğer tıklanan yer tab linkleri, tarih buton grubu veya filtre temizleme butonları ise hiçbir şey yapma
            if ($(e.target).closest('#puantajTabs, #dateFilterTypeGroup, .btn-clear-filter').length) {
                return;
            }
            
            var collapseElement = document.getElementById('collapseOne');
            var bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseElement);
            bsCollapse.toggle();
        });

        // Accordion açılıp kapandığında header stilini ve aria özelliklerini güncelle
        $('#collapseOne').on('show.bs.collapse', function () {
            $('#filterAccordionHeader').removeClass('collapsed').attr('aria-expanded', 'true');
        }).on('hide.bs.collapse', function () {
            $('#filterAccordionHeader').addClass('collapsed').attr('aria-expanded', 'false');
        });

        // Dönem (Ay) picker instance
        var monthPicker = null;
        function initMonthPicker() {
            setTimeout(function() {
                var $el = $('#period_month');
                if ($el.length === 0) return;
                
                var pluginFunc = window.monthSelectPlugin || (typeof monthSelectPlugin !== 'undefined' ? monthSelectPlugin : null);
                
                if (typeof pluginFunc === 'function') {
                    if (monthPicker) {
                        monthPicker.destroy();
                    }
                    monthPicker = flatpickr($el[0], {
                        locale: "tr",
                        plugins: [
                            new pluginFunc({
                                shorthand: false,
                                dateFormat: "F Y",
                                altFormat: "F Y",
                                theme: "light"
                            })
                        ],
                        clickOpens: true,
                        allowInput: true,
                        onChange: function (selectedDates, dateStr) {
                            if (selectedDates.length > 0) {
                                var date = selectedDates[0];
                                var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
                                var lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);

                                var formatDate = function (d) {
                                    var dd = ("0" + d.getDate()).slice(-2);
                                    var mm = ("0" + (d.getMonth() + 1)).slice(-2);
                                    var yyyy = d.getFullYear();
                                    return dd + '.' + mm + '.' + yyyy;
                                };

                                $('input[name="start_date"]').val(formatDate(firstDay));
                                $('input[name="end_date"]').val(formatDate(lastDay));
                            }
                        }
                    });
                }
            }, 500);
        }
        initMonthPicker();

        var endeksDataTable = null;
        var puantajDataTable = null;
        var kacakDataTable = null;
        var sayacDegisimDataTable = null;
        var muhurlemeDataTable = null;

        var tabContentLoading = false;
        var initialTabLoaded = false;

        // Filtre özetini güncelle
        function updateFilterSummary() {
            let summary = '';
            const startDate = $('input[name="start_date"]').val();
            const endDate = $('input[name="end_date"]').val();
            const region = $('select[name="region"]').val();
            const regionText = $('select[name="region"] option:selected').text();
            const defter = $('select[name="defter"]').val();
            const defterText = $('select[name="defter"] option:selected').text();
            const ekipKodu = $('select[name="ekip_kodu"]').val();
            const ekipText = $('select[name="ekip_kodu"] option:selected').text();
            const workType = $('select[name="work_type"]').val();
            const workTypeText = $('select[name="work_type"] option:selected').text();
            const workResult = $('select[name="work_result"]').val();
            const workResultText = $('select[name="work_result"] option:selected').text();
            const activeTab = $('#activeTabInput').val();

            if (startDate && endDate) {
                summary += '<div class="filter-summary-badge"><span class="badge-label">Tarih:</span><span class="badge-value">' + startDate + ' - ' + endDate + '</span></div>';
            }

            if (region && region !== '') {
                summary += '<div class="filter-summary-badge"><span class="badge-label">Bölge:</span><span class="badge-value">' + regionText + '</span><button type="button" class="btn-clear-filter" data-filter="region"><i class="bx bx-x"></i></button></div>';
            }

            if (activeTab === 'okuma' && defter && defter !== '') {
                summary += '<div class="filter-summary-badge"><span class="badge-label">Defter:</span><span class="badge-value">' + defterText + '</span><button type="button" class="btn-clear-filter" data-filter="defter"><i class="bx bx-x"></i></button></div>';
            }

            if (ekipKodu && ekipKodu !== '') {
                summary += '<div class="filter-summary-badge"><span class="badge-label">Pers:</span><span class="badge-value">' + ekipText + '</span><button type="button" class="btn-clear-filter" data-filter="ekip_kodu"><i class="bx bx-x"></i></button></div>';
            }

            if (activeTab === 'yapilan_isler') {
                if (workType && workType !== '') {
                    summary += '<div class="filter-summary-badge"><span class="badge-label">İş:</span><span class="badge-value">' + workTypeText + '</span><button type="button" class="btn-clear-filter" data-filter="work_type"><i class="bx bx-x"></i></button></div>';
                }
                if (workResult && workResult !== '') {
                    summary += '<div class="filter-summary-badge"><span class="badge-label">Sonuç:</span><span class="badge-value">' + workResultText + '</span><button type="button" class="btn-clear-filter" data-filter="work_result"><i class="bx bx-x"></i></button></div>';
                }
            }

            $('#filterSummary').html(summary);
        }

        $(document).on('click', '.btn-clear-filter', function (e) {
            e.stopPropagation();
            const filterType = $(this).data('filter');
            if (filterType === 'region') {
                $('select[name="region"]').val('').trigger('change');
            } else if (filterType === 'defter') {
                $('select[name="defter"]').val('').trigger('change');
            } else if (filterType === 'ekip_kodu') {
                $('select[name="ekip_kodu"]').val('').trigger('change');
            } else if (filterType === 'work_type') {
                $('select[name="work_type"]').val('').trigger('change');
            } else if (filterType === 'work_result') {
                $('select[name="work_result"]').val('').trigger('change');
            }
            $('#filterForm').trigger('submit');
        });

        // Filtre değerlerini localStorage'a kaydet
        function saveFiltersToStorage() {
            var filters = {
                dateFilterType: $('input[name="dateFilterType"]:checked').val(),
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
                region: $('select[name="region"]').val(),
                defter: $('select[name="defter"]').val(),
                ekip_kodu: $('select[name="ekip_kodu"]').val(),
                work_type: $('select[name="work_type"]').val(),
                work_result: $('select[name="work_result"]').val(),
                tab: $('#activeTabInput').val()
            };
            localStorage.setItem('puantaj_filters', JSON.stringify(filters));
            updateFilterSummary();
        }

        // localStorage'dan filtreleri yükle (eğer URL'de yoksa)
        function loadFiltersFromStorage() {
            var urlParams = new URLSearchParams(window.location.search);
            var hasFilters = urlParams.has('ekip_kodu') || urlParams.has('work_type') || urlParams.has('work_result');

            var savedFilters = localStorage.getItem('puantaj_filters');
            var filters = {};
            try {
                filters = savedFilters ? JSON.parse(savedFilters) : {};
            } catch (e) { filters = {}; }

            // Filtre tipi (Aralık veya Dönem) - Varsayılan: range
            var dateFilterType = filters.dateFilterType || 'range';
            
            // Eğer URL'de tarih yoksa ve storage boşsa (veya ilk yüklemeyse) her zaman range gelsin
            if (!urlParams.has('start_date') && !savedFilters) {
                dateFilterType = 'range';
            }

            $(`input[name="dateFilterType"][value="${dateFilterType}"]`).prop('checked', true).trigger('change');

            var now = new Date();
            var day = ("0" + now.getDate()).slice(-2);
            var month = ("0" + (now.getMonth() + 1)).slice(-2);
            var year = now.getFullYear();

            var firstDayStr = "01." + month + "." + year;
            var todayStr = day + "." + month + "." + year;

            // Tarihler her zaman default veya storage/url'den gelsin
            var sDate = urlParams.get('start_date') || (filters ? filters.start_date : null) || firstDayStr;
            var eDate = urlParams.get('end_date') || (filters ? filters.end_date : null) || todayStr;

            $('input[name="start_date"]').val(sDate);
            $('input[name="end_date"]').val(eDate);

            // Filtre tipi varsayılanı ayarla
            var dateFilterType = 'range';
            if (urlParams.has('start_date') || (filters && filters.dateFilterType === 'period')) {
                 dateFilterType = filters.dateFilterType || 'range';
            }
            
            // UI'ı güncelle
            $('input[name="dateFilterType"][value="' + dateFilterType + '"]').prop('checked', true).trigger('change');

            // Month picker'ı da güncelle
            if (dateFilterType === 'period') {
                var parts = sDate.split('.');
                if (parts.length === 3) {
                    if (monthPicker) {
                        monthPicker.setDate(new Date(parts[2], parseInt(parts[1]) - 1, 1));
                    }
                }
            }

            if (savedFilters) {
                if (!urlParams.has('tab') && filters.tab) {
                    $('#activeTabInput').val(filters.tab);
                    $(`#puantajTabs a`).removeClass('active');
                    $(`.tab-pane`).removeClass('active show');

                    var $targetTab = $('a[data-tab-name="' + filters.tab + '"]');
                    if ($targetTab.length > 0) {
                        $targetTab.addClass('active');
                        $($targetTab.attr('href')).addClass('active show');
                    } else {
                        $(`#puantajTabs a[data-tab-name="okuma"]`).addClass('active');
                        $('#okuma').addClass('active show');
                    }
                }

                // Diğer filtreler sadece URL boşsa storage'dan
                if (!hasFilters) {
                    if (filters.region) {
                        $('select[name="region"]').val(filters.region).trigger('change');
                    }
                    if (filters.defter) {
                        $('select[name="defter"]').val(filters.defter).trigger('change');
                    }
                    if (filters.ekip_kodu) {
                        $('select[name="ekip_kodu"]').val(filters.ekip_kodu).trigger('change');
                    }
                    if (filters.work_type) {
                        $('select[name="work_type"]').val(filters.work_type).trigger('change');
                    }
                    if (filters.work_result) {
                        $('select[name="work_result"]').val(filters.work_result).trigger('change');
                    }
                }
            } else {
                // Hiçbir şey yoksa varsayılan tarihleri bas
                if (!$('input[name="start_date"]').val()) $('input[name="start_date"]').val(firstDayStr);
                if (!$('input[name="end_date"]').val()) $('input[name="end_date"]').val(todayStr);
            }
            updateFilterSummary();
        }

        // Tab değişikliğinde hidden input güncelle, URL'yi güncelle ve içeriği yükle
        $('#puantajTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            var tabName = $(e.target).attr('data-tab-name');
            $('#activeTabInput').val(tabName);

            // URL'yi güncelle (sayfa yenilemeden) - tüm filtreleri ekle
            var url = new URL(window.location.href);
            url.searchParams.set('p', 'puantaj/veri-yukleme');
            url.searchParams.set('tab', tabName);

            // Mevcut filtre değerlerini URL'ye ekle
            var startDate = $('input[name="start_date"]').val();
            var endDate = $('input[name="end_date"]').val();
            var region = $('select[name="region"]').val();
            var defter = $('select[name="defter"]').val();
            var ekipKodu = $('select[name="ekip_kodu"]').val();
            var workType = $('select[name="work_type"]').val();
            var workResult = $('select[name="work_result"]').val();

            if (startDate) url.searchParams.set('start_date', startDate);
            if (endDate) url.searchParams.set('end_date', endDate);
            if (region) url.searchParams.set('region', region);
            if (defter) url.searchParams.set('defter', defter);
            if (ekipKodu) url.searchParams.set('ekip_kodu', ekipKodu);
            if (workType) url.searchParams.set('work_type', workType);
            if (workResult) url.searchParams.set('work_result', workResult);

            window.history.replaceState({}, '', url);

            // Filtreleri kaydet
            saveFiltersToStorage();

            // Filtre panellerini göster/gizle
            if (tabName === 'yapilan_isler') {
                $('#workTypeFilterContainer').show();
                $('#workResultFilterContainer').show();
                $('#defterFilterContainer').hide();
            } else if (tabName === 'okuma') {
                $('#workTypeFilterContainer').hide();
                $('#workResultFilterContainer').hide();
                $('#defterFilterContainer').show();
            } else {
                $('#workTypeFilterContainer').hide();
                $('#workResultFilterContainer').hide();
                $('#defterFilterContainer').hide();
            }

            // İçeriği yükle
            loadTabContent(tabName);
        });

        // Form submit öncesi filtreleri kaydet
        $('#filterForm').on('submit', function () {
            saveFiltersToStorage();
        });

        // Temizle butonu mantığı
        $('#btnClearFilters').on('click', function () {
            // Sadece ekip ve iş filtrelerini temizle
            $('select[name="region"]').val('').trigger('change');
            $('select[name="defter"]').val('').trigger('change');
            $('select[name="ekip_kodu"]').val('').trigger('change');
            $('select[name="work_type"]').val('').trigger('change');
            $('select[name="work_result"]').val('').trigger('change');

            // Tarihleri varsayılana çek (Ayın ilk günü ve bugün)
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const today = now;

            const formatDate = (date) => {
                const d = date.getDate().toString().padStart(2, '0');
                const m = (date.getMonth() + 1).toString().padStart(2, '0');
                const y = date.getFullYear();
                return `${d}.${m}.${y}`;
            };

            $('input[name="start_date"]').val(formatDate(firstDay));
            $('input[name="end_date"]').val(formatDate(today));

            // Storage'ı da güncelle
            saveFiltersToStorage();

            // Formu gönder
            $('#filterForm').trigger('submit');
        });



        // Server-side DataTable için özelleştirilmiş seçenekleri oluştur
        function getServerSideOptions(customOptions) {
            var baseOptions = getDatatableOptions();
            // Server-side için gerekli ayarları ekle
            return $.extend(true, {}, baseOptions, {
                processing: false, // Varsayılan processing'i kapat, kendi loader'ımızı kullanacağız
                serverSide: true,
                language: $.extend({}, baseOptions.language, {
                    processing: ''
                }),
                preDrawCallback: function (settings) {
                    showTabLoader();
                },
                drawCallback: function (settings) {
                    hideTabLoader();
                    if (typeof feather !== "undefined") {
                        feather.replace();
                    }
                },
                buttons: []
            }, customOptions);
        }

        function showTabLoader() {
            $('#tab-loader').css('display', 'flex').hide().fadeIn(200);
        }

        function hideTabLoader() {
            $('#tab-loader').fadeOut(200);
        }

        /**
         * Sayaç durumu özetlerini ekrana basar
         */
        // Genel Özet Render Fonksiyonu
        function renderGenericSummary(summary, config) {
            var container = $(config.container);
            var otherContainer = $(config.otherContainer);
            var wrapper = $(config.wrapper);
            var toggleBtn = $(config.toggleBtn);

            if (!summary || summary.length === 0) {
                wrapper.hide();
                return;
            }

            // Büyükten küçüğe sırala (İş sayısına göre)
            summary.sort((a, b) => parseFloat(b.toplam_abone) - parseFloat(a.toplam_abone));

            wrapper.show();
            container.empty();
            otherContainer.empty();
            otherContainer.addClass('d-none');
            toggleBtn.addClass('d-none');
            toggleBtn.find('.show-text').removeClass('d-none');
            toggleBtn.find('.hide-text').addClass('d-none');

            summary.forEach(function (item, index) {
                var variant = 'primary';
                var statusText = (item.sonuc || item.sayac_durum || 'BELİRSİZ');
                var status = statusText.toUpperCase();

                // Renk Belirleme
                if (status.includes('OKUNDU') || status.includes('NORMAL') || status.includes('YAPILDI') || status.includes('AÇILDI')) variant = 'success';
                else if (status.includes('OKUNAMADI') || status.includes('BOZUK') || status.includes('KIRIK') || status.includes('PATLAK') || status.includes('HATA') || status.includes('KESİLDİ')) variant = 'danger';
                else if (status.includes('YOK') || status.includes('BULUNAMIYOR') || status.includes('KAPALI') || status.includes('GİRİLEMEDİ')) variant = 'warning';
                else if (status.includes('HESAP') || status.includes('İPTAL') || status.includes('ÜCRET')) variant = 'info';

                var label = config.label || 'Abone';
                var subLabel = config.subLabel || 'Kayıt';

                var html = `
                    <div class="col summary-card-item" data-status="${statusText}" data-target-wrapper="${config.wrapper}" style="cursor: pointer;">
                        <div class="card shadow-sm border-start border-start-width-3 border-${variant} mb-0 h-100">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="avatar-xs flex-shrink-0 me-2">
                                        <span class="avatar-title bg-${variant}-subtle text-${variant} rounded-circle fs-13">
                                            <i class="bx bx-stats"></i>
                                        </span>
                                    </div>
                                    <h6 class="text-muted text-truncate mb-0 fs-11 fw-bold text-uppercase flex-grow-1 status-label" title="${status}">${status}</h6>
                                </div>
                                <div class="d-flex align-items-center justify-content-between">
                                    <h4 class="mb-0 fw-extrabold main-value">
                                        <span class="counter-value" data-target="${item.toplam_abone}">${parseInt(item.toplam_abone).toLocaleString('tr-TR')}</span>
                                        <small class="fs-11 fw-normal text-muted ms-1">${label}</small>
                                    </h4>
                                    <div class="text-end">
                                        <span class="badge bg-${variant}-subtle text-${variant} sub-badge rounded-pill">
                                            ${parseInt(item.adet).toLocaleString('tr-TR')} ${subLabel}
                                        </span>
                                    </div>
                                </div>
                                <div class="progress animated-progress progress-sm mt-3" style="height: 4px; border-radius: 20px;">
                                    <div class="progress-bar bg-${variant} rounded" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                if (index < 6) {
                    container.append(html);
                } else {
                    otherContainer.append(html);
                    toggleBtn.removeClass('d-none');
                }
            });
        }

        // Kartlara tıklandığında filtreleme yap
        $(document).on('click', '.summary-card-item', function() {
            var status = $(this).data('status');
            var wrapperId = $(this).data('target-wrapper');
            var activeTab = $('#puantajTabs .nav-link.active').data('tab-name');
            var table = null;
            var colIdx = -1;

            if (activeTab === 'okuma') {
                table = endeksDataTable;
                colIdx = 6; // Sayaç Durumu
            } else if (activeTab === 'yapilan_isler') {
                table = puantajDataTable;
                colIdx = 5; // İş Emri Sonucu
            } else if (activeTab === 'sayac_sokme_takma') {
                table = sayacDegisimDataTable;
                colIdx = 5; // İş Emri Sonucu
            } else if (activeTab === 'muhurleme') {
                table = muhurlemeDataTable;
                colIdx = 5; // İş Emri Sonucu
            }

            if (table && colIdx !== -1) {
                var filterValue = "multi:" + status;
                var currentSearch = table.column(colIdx).search();
                
                // datatable-filters.js'ye yeni dtf:set-filter event'ı ile temiz ve senkronize bir emir gönder
                if (currentSearch === filterValue) {
                    // Eğer zaten bu filtre varsa temizle (Toggle mantığı)
                    $(table.table().node()).trigger('dtf:set-filter', [colIdx, '']);
                } else {
                    // Sadece seçilen status'u gönder (multi modu kütüphane tarafından otomatik atanacak)
                    $(table.table().node()).trigger('dtf:set-filter', [colIdx, status, 'multi']);
                }
            }
        });

        // Önceki fonksiyonu buna yönlendir (Geriye dönük uyumluluk için)
        function renderSayacDurumSummary(summary) {
            renderGenericSummary(summary, {
                container: '#sayacDurumSummaryContainer',
                otherContainer: '#sayacDurumOtherSummaryContainer',
                wrapper: '#sayacDurumSummary',
                toggleBtn: '#btnToggleOtherStatuses',
                label: 'Abone',
                subLabel: 'Kayıt'
            });
        }

        // Özetleri genişlet/daralt butonu (Genelleştirilmiş)
        $(document).on('click', '.summary-toggle-btn', function() {
            var target = $(this).data('active-target');
            var otherContainer = $(target);
            var btn = $(this);
            
            if (otherContainer.hasClass('d-none')) {
                otherContainer.removeClass('d-none');
                btn.find('.show-text').addClass('d-none');
                btn.find('.hide-text').removeClass('d-none');
            } else {
                otherContainer.addClass('d-none');
                btn.find('.show-text').removeClass('d-none');
                btn.find('.hide-text').addClass('d-none');
            }
        });


        // Endeks Okuma tablosu için Server-Side DataTable
        function initEndeksDataTable() {
            // Eğer DataTable zaten varsa, sadece reload yap
            if (endeksDataTable) {
                endeksDataTable.ajax.reload(null, false);
                return;
            }

            endeksDataTable = $('#endeksTable').DataTable(getServerSideOptions({
                ajax: {
                    url: 'views/puantaj/api.php',
                    type: 'GET',
                    data: function (d) {
                        d.action = 'endeks-datatable';
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                        d.region = $('select[name="region"]').val();
                        d.defter = $('select[name="defter"]').val();
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                    },
                    dataSrc: function (json) {
                        if (json.summary) {
                            renderSayacDurumSummary(json.summary);
                        }

                        // Riskli İşlemler Bantı ve Modal Güncelleme
                        if (json.risky_personnel && json.risky_personnel.length > 0) {
                            $('#riskyOperationsTicker').fadeIn();
                            let tickerHtml = '';
                            let modalHtml = '';
                            
                            json.risky_personnel.forEach(function(p) {
                                let ratio = (parseFloat(p.evde_yok_sayisi) / parseFloat(p.normal_sayisi) * 100).toFixed(1);
                                tickerHtml += `<span class="ticker-item">
                                    ⚠️ ${p.personel_adi} (${p.ekip_adi}): Evde Yok Oranı %${ratio} (${p.evde_yok_sayisi}/${p.normal_sayisi})
                                </span>`;

                                modalHtml += `<tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs flex-shrink-0 me-2">
                                                <div class="avatar-title bg-danger-subtle text-danger rounded-circle fs-11">
                                                    ${p.personel_adi.charAt(0)}
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="fs-13 mb-0">${p.personel_adi}</h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>${p.ekip_adi}</td>
                                    <td class="text-center fw-medium">${parseInt(p.normal_sayisi).toLocaleString('tr-TR')}</td>
                                    <td class="text-center text-danger fw-medium">${parseInt(p.evde_yok_sayisi).toLocaleString('tr-TR')}</td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <span class="badge bg-danger-subtle text-danger fs-12">%${ratio}</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">YÜKSEK RİSK</span>
                                    </td>
                                </tr>`;
                            });
                            
                            $('#riskyMarquee').html(tickerHtml);
                            $('#riskyPersonnelTableBody').html(modalHtml);
                        } else {
                            $('#riskyOperationsTicker').fadeOut();
                        }

                        if (json.recordsFiltered !== undefined) {
                            let totalAbone = 0;
                            if (json.summary) {
                                json.summary.forEach(function(item) {
                                    totalAbone += parseInt(item.toplam_abone || 0);
                                });
                            }
                            
                            let html = `Endeks Okuma Raporu 
                                <div class="stat-mini-card stat-success ms-3 count-animate">
                                    <div class="icon-wrap"><i class="bx bx-group"></i></div>
                                    <div>
                                        <span class="value">${totalAbone.toLocaleString('tr-TR')}</span>
                                        <span class="label">Abone</span>
                                    </div>
                                </div>`;
                            $('#endeksTableTitle').html(html);
                        }
                        return json.data;
                    }
                },
                columns: [
                    { data: 'tarih' },
                    { data: 'defter' },
                    { data: 'bolge' },
                    { data: 'ekip_no' },
                    { data: 'personel_adi' },
                    { data: 'okunan_abone_sayisi' },
                    { data: 'sayac_durum' },
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<button class="btn btn-sm btn-soft-danger delete-endeks" data-id="${data}"><i class="bx bx-trash"></i></button>`;
                        },
                        orderable: false
                    }
                ],
                order: [[0, 'desc']]
            }));
        }

        // Puantaj (Kesme/Açma) tablosu için Server-Side DataTable
        function initPuantajDataTable() {
            // Eğer DataTable zaten varsa, sadece reload yap
            if (puantajDataTable) {
                puantajDataTable.ajax.reload(null, false);
                return;
            }

            puantajDataTable = $('#puantajTable').DataTable(getServerSideOptions({
                ajax: {
                    url: 'views/puantaj/api.php',
                    type: 'GET',
                    data: function (d) {
                        d.action = 'puantaj-datatable';
                        d.sorgu_turu = 'KESME_ACMA';
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                        d.region = $('select[name="region"]').val();
                        d.defter = $('select[name="defter"]').val();
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                        d.work_type = $('select[name="work_type"]').val();
                        d.work_result = $('select[name="work_result"]').val();
                    },
                    dataSrc: function (json) {
                        if (json.summary) {
                            renderGenericSummary(json.summary, {
                                container: '#puantajSummaryContainer',
                                otherContainer: '#puantajOtherSummaryContainer',
                                wrapper: '#puantajSummary',
                                toggleBtn: '#btnToggleOtherPuantajStatus',
                                label: 'İş',
                                subLabel: 'Kayıt'
                            });
                        }
                        if (json.recordsFiltered !== undefined) {
                            let totalIs = 0;
                            if (json.summary) {
                                json.summary.forEach(function(item) {
                                    totalIs += parseInt(item.toplam_abone || 0);
                                });
                            }
                            $('#puantajTableTitle').html(`İş Listesi 
                                <div class="stat-mini-card stat-primary ms-3 count-animate">
                                    <div class="icon-wrap"><i class="bx bx-briefcase"></i></div>
                                    <div>
                                        <span class="value">${totalIs.toLocaleString('tr-TR')}</span>
                                        <span class="label">İş</span>
                                    </div>
                                </div>`);
                        }
                        return json.data;
                    }
                },
                columns: [
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<div class="form-check"><input class="form-check-input row-check" type="checkbox" value="${data}"></div>`;
                        },
                        orderable: false,
                        searchable: false
                    },
                    { data: 'tarih' },
                    { data: 'ekip_kodu', defaultContent: '-' },
                    { data: 'personel_adi' },
                    { data: 'is_emri_tipi' },
                    { data: 'is_emri_sonucu' },
                    { data: 'sonuclanmis' },
                    { data: 'acik_olanlar' },
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<button class="btn btn-sm btn-soft-danger delete-puantaj" data-id="${data}"><i class="bx bx-trash"></i></button>`;
                        },
                        orderable: false
                    }
                ],
                order: [[1, 'desc']]
            }));
        }

        // Kaçak Kontrol tablosu için Client-Side DataTable (az veri olduğu için)
        function loadKacakContent() {
            var formData = {
                action: 'get-tab-content',
                tab: 'kacak_kontrol',
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
                region: $('select[name="region"]').val(),
                ekip_kodu: $('select[name="ekip_kodu"]').val()
            };

            $('#kacakKontrolBody').html('<tr><td colspan="5" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: formData,
                success: function (html) {
                    if (kacakDataTable) {
                        kacakDataTable.destroy();
                        $('#kacakTable').find('thead .search-input-row').remove();
                    }
                    $('#kacakKontrolBody').html(html);
                    kacakDataTable = $('#kacakTable').DataTable($.extend(true, {}, getDatatableOptions(), {
                        drawCallback: function() {
                            let total = 0;
                            this.api().rows({filter: 'applied'}).data().each(function(row) {
                                // Extract number from row'un relevant cell if needed, but summary is better.
                                // Actually client-side table rows might not have summary easily.
                                // I'll just use the count of visible rows for Kacak as it's client-side.
                                total++; 
                            });
                            $('#kacakTableTitle').html(`Kaçak Kontrol Listesi 
                                <div class="stat-mini-card stat-info ms-3 count-animate">
                                    <div class="icon-wrap"><i class="bx bx-check-shield"></i></div>
                                    <div>
                                        <span class="value">${this.api().rows({filter: 'applied'}).count().toLocaleString('tr-TR')}</span>
                                        <span class="label">İş</span>
                                    </div>
                                </div>`);
                        }
                    }));
                }
            });
        }

        // Sayaç Değişim (Sökme Takma) tablosu için Server-Side DataTable
        function initSayacDegisimDataTable() {
            if (sayacDegisimDataTable) {
                sayacDegisimDataTable.ajax.reload(null, false);
                return;
            }

            sayacDegisimDataTable = $('#sayacDegisimTable').DataTable(getServerSideOptions({
                ajax: {
                    url: 'views/puantaj/api.php',
                    type: 'GET',
                    data: function (d) {
                        d.action = 'sayac-degisim-datatable';
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                        d.region = $('select[name="region"]').val();
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                    },
                    dataSrc: function (json) {
                        if (json.summary) {
                            renderGenericSummary(json.summary, {
                                container: '#sayacDegisimSummaryContainer',
                                otherContainer: '#sayacDegisimOtherSummaryContainer',
                                wrapper: '#sayacDegisimSummary',
                                toggleBtn: '#btnToggleOtherSayacStatus',
                                label: 'Adet',
                                subLabel: 'Kayıt'
                            });
                        }
                        if (json.recordsFiltered !== undefined) {
                            let totalAdet = 0;
                            if (json.summary) {
                                json.summary.forEach(function(item) {
                                    totalAdet += parseInt(item.toplam_abone || 0);
                                });
                            }
                            $('#sayacTableTitle').html(`Sayaç Sökme Takma Listesi 
                                <div class="stat-mini-card stat-warning ms-3 count-animate">
                                    <div class="icon-wrap"><i class="bx bxs-component"></i></div>
                                    <div>
                                        <span class="value">${totalAdet.toLocaleString('tr-TR')}</span>
                                        <span class="label">Adet</span>
                                    </div>
                                </div>`);
                        }
                        return json.data;
                    }
                },
                columns: [
                    { data: 'kayit_tarihi' },
                    { data: 'ekip' },
                    { data: 'personel_adi' },
                    { data: 'bolge' },
                    { data: 'isemri_sebep' },
                    { data: 'isemri_sonucu' },
                    { data: 'abone_no' },
                    { data: 'takilan_sayacno' },
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<button class="btn btn-sm btn-soft-danger delete-sayac-degisim" data-id="${data}"><i class="bx bx-trash"></i></button>`;
                        },
                        orderable: false
                    }
                ],
                order: [[0, 'desc']]
            }));
        }

        // Mühürleme tablosu için Server-Side DataTable (yapilan_isler'den MÜHÜRLEME olanlar)
        function initMuhurlemeDataTable() {
            if (muhurlemeDataTable) {
                muhurlemeDataTable.ajax.reload(null, false);
                return;
            }

            muhurlemeDataTable = $('#muhurlemeTable').DataTable(getServerSideOptions({
                ajax: {
                    url: 'views/puantaj/api.php',
                    type: 'GET',
                    data: function (d) {
                        d.action = 'muhurleme-datatable';
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                        d.region = $('select[name="region"]').val();
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                    },
                    dataSrc: function (json) {
                        if (json.summary) {
                            renderGenericSummary(json.summary, {
                                container: '#muhurlemeSummaryContainer',
                                otherContainer: '#muhurlemeOtherSummaryContainer',
                                wrapper: '#muhurlemeSummary',
                                toggleBtn: '#btnToggleOtherMuhurlemeStatus',
                                label: 'İş',
                                subLabel: 'Kayıt'
                            });
                        }
                        if (json.recordsFiltered !== undefined) {
                            let totalIs = 0;
                            if (json.summary) {
                                json.summary.forEach(function(item) {
                                    totalIs += parseInt(item.toplam_abone || 0);
                                });
                            }
                            $('#muhurlemeTableTitle').html(`Mühürleme İş Listesi 
                                <div class="stat-mini-card stat-secondary ms-3 count-animate">
                                    <div class="icon-wrap"><i class="bx bx-lock-alt"></i></div>
                                    <div>
                                        <span class="value">${totalIs.toLocaleString('tr-TR')}</span>
                                        <span class="label">İş</span>
                                    </div>
                                </div>`);
                        }
                        return json.data;
                    }
                },
                columns: [
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<div class="form-check"><input class="form-check-input row-check" type="checkbox" value="${data}"></div>`;
                        },
                        orderable: false,
                        searchable: false
                    },
                    { data: 'tarih' },
                    { data: 'ekip_kodu', defaultContent: '-' },
                    { data: 'personel_adi' },
                    { data: 'is_emri_tipi' },
                    { data: 'is_emri_sonucu' },
                    { data: 'sonuclanmis' },
                    { data: 'acik_olanlar' },
                    {
                        data: 'id',
                        render: function (data, type, row) {
                            return `<button class="btn btn-sm btn-soft-danger delete-puantaj" data-id="${data}"><i class="bx bx-trash"></i></button>`;
                        },
                        orderable: false
                    }
                ],
                order: [[1, 'desc']]
            }));
        }

        function loadTabContent(tabName) {
            initialTabLoaded = true;
            
            // Riskli işlemler bandını her tab geçişinde gizle, 
            // sadece okuma tabında veri varsa gösterilecek.
            $('#riskyOperationsTicker').hide();

            if (tabName === 'okuma') {
                initEndeksDataTable();
            } else if (tabName === 'yapilan_isler') {
                initPuantajDataTable();
            } else if (tabName === 'kacak_kontrol') {
                loadKacakContent();
            } else if (tabName === 'sayac_sokme_takma') {
                initSayacDegisimDataTable();
            } else if (tabName === 'muhurleme') {
                initMuhurlemeDataTable();
            }
        }

        // Tarih değiştiğinde file inputunu temizle
        $('#endeksUploadForm input[name="upload_date"]').on('change', function () {
            $('#endeksUploadForm input[name="excel_file"]').val('');
        });
        $('#puantajUploadForm input[name="upload_date"]').on('change', function () {
            $('#puantajUploadForm input[name="excel_file"]').val('');
        });
        $('#kacakUploadForm input[name="upload_date"]').on('change', function () {
            $('#kacakUploadForm input[name="excel_file"]').val('');
        });

        // Sayfa yüklendiğinde filtreleri storage'dan al
        loadFiltersFromStorage();

        // İlk yükleme: Şu an aktif olan sekmeyi bul ve içeriğini yükle
        var currentActiveTab = $('#puantajTabs .nav-link.active').data('tab-name');

        // Zarif geçiş: Doğru sekme ayarlandıktan sonra göster
        $('#puantajMainWrapper').css('opacity', '1');

        if (currentActiveTab) {
            loadTabContent(currentActiveTab);
        }

        // Filter form submit - server-side DataTable'ları yeniden yükle
        $('#filterForm').on('submit', function (e) {
            e.preventDefault();
            var activeTab = $('#puantajTabs .nav-link.active').data('tab-name');
            loadTabContent(activeTab);

            const collapseElement = document.getElementById('collapseOne');
            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement);
            if (bsCollapse) bsCollapse.hide();
            updateFilterSummary();
        });

        // Excel Export
        function getExportUrl(tab, dt = null) {
            let url = new URL('views/puantaj/export-excel.php', window.location.origin + window.location.pathname);
            url.searchParams.set('tab', tab);
            url.searchParams.set('start_date', $('input[name="start_date"]').val());
            url.searchParams.set('end_date', $('input[name="end_date"]').val());
            url.searchParams.set('ekip_kodu', $('select[name="ekip_kodu"]').val());
            url.searchParams.set('work_type', $('select[name="work_type"]').val());
            url.searchParams.set('work_result', $('select[name="work_result"]').val());

            if (dt) {
                // Main search
                url.searchParams.set('search[value]', dt.search());
                
                // Column search
                dt.columns().every(function (index) {
                    let searchVal = this.search();
                    if (searchVal) {
                        url.searchParams.set('columns[' + index + '][search][value]', searchVal);
                    }
                });
            }
            return url.toString();
        }

        $('#btnExportEndeksExcel').on('click', function () {
            if (endeksDataTable) {
                window.location.href = getExportUrl('okuma', endeksDataTable);
            }
        });

        $('#btnExportPuantajExcel').on('click', function () {
            if (puantajDataTable) {
                window.location.href = getExportUrl('yapilan_isler', puantajDataTable);
            }
        });

        $(document).on('click', '#btnExportKacakExcel', function () {
            window.location.href = getExportUrl('kacak_kontrol', kacakDataTable);
        });

        $(document).on('click', '#btnExportSayacExcel', function () {
            if (sayacDegisimDataTable) {
                window.location.href = getExportUrl('sayac_sokme_takma', sayacDegisimDataTable);
            }
        });

        $(document).on('click', '#btnExportMuhurlemeExcel', function () {
            if (muhurlemeDataTable) {
                window.location.href = getExportUrl('muhurleme', muhurlemeDataTable);
            }
        });

        // Show Statistics
        $('#btnShowStats').on('click', function () {
            var startDate = $('input[name="start_date"]').val();
            var endDate = $('input[name="end_date"]').val();
            var personelId = $('select[name="ekip_kodu"]').val();

            $('#statsModal .modal-title').text('Bölge Bazlı Okuma İstatistikleri');
            $('#statsModal').modal('show');
            $('#statsModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">İstatistikler hazırlanıyor...</p></div>');

            $.get('views/puantaj/modal_okuma_istatistik.php', {
                start_date: startDate,
                end_date: endDate,
                personel_id: personelId
            }, function (html) {
                $('#statsModalBody').html(html);
            });
        });

        $('#btnShowPuantajStats').on('click', function () {
            var startDate = $('input[name="start_date"]').val();
            var endDate = $('input[name="end_date"]').val();
            var personelId = $('select[name="ekip_kodu"]').val();
            var workType = $('select[name="work_type"]').val();

            // DataTable sütun filtrelerini DOM input'larından al
            var columnFilters = {};
            $('#puantajTable thead tr.search-input-row input').each(function () {
                var searchValue = $(this).val();
                var colIdx = $(this).attr('data-col-idx');
                if (searchValue && colIdx) {
                    columnFilters['col_' + colIdx] = searchValue;
                }
            });

            $('#statsModal .modal-title').text('İş Emri Tipi Bazlı İstatistikler');
            $('#statsModal').modal('show');
            $('#statsModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">İstatistikler hazırlanıyor...</p></div>');

            $.get('views/puantaj/modal_puantaj_istatistik.php', $.extend({
                start_date: startDate,
                end_date: endDate,
                personel_id: personelId,
                work_type: workType,
                work_result: $('select[name="work_result"]').val()
            }, columnFilters), function (html) {
                $('#statsModalBody').html(html);
            });
        });

        $('#btnShowSayacStats'). on('click', function () {
            var startDate = $('input[name="start_date"]').val();
            var endDate = $('input[name="end_date"]').val();
            var personelId = $('select[name="ekip_kodu"]').val();

            $('#statsModal .modal-title').text('Sayaç Sökme Takma İstatistikleri');
            $('#statsModal').modal('show');
            $('#statsModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">İstatistikler hazırlanıyor...</p></div>');

            $.get('views/puantaj/modal_sayac_degisim_istatistik.php', {
                start_date: startDate,
                end_date: endDate,
                personel_id: personelId
            }, function (html) {
                $('#statsModalBody').html(html);
            });
        });

        $('#endeksUploadForm').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'endeks-excel-kaydet');

            $('#endeksSpinner').show();
            $('#btnEndeksUpload').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#endeksSpinner').hide();
                    $('#btnEndeksUpload').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            var htmlMessage = res.message;
                            // Atlanan satırlar varsa detaylı göster
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
                                $('#importEndeksModal').modal('hide');
                                loadTabContent('okuma');
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                },
                error: function () {
                    $('#endeksSpinner').hide();
                    $('#btnEndeksUpload').prop('disabled', false);
                    Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                }
            });
        });

        $('#puantajUploadForm').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'puantaj-excel-kaydet');

            $('#puantajSpinner').show();
            $('#btnPuantajUpload').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    $('#puantajSpinner').hide();
                    $('#btnPuantajUpload').prop('disabled', false);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            var htmlMessage = res.message;
                            // Atlanan satırlar varsa detaylı göster
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
                                $('#importPuantajModal').modal('hide');
                                loadTabContent('yapilan_isler');
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                    }
                },
                error: function () {
                    $('#puantajSpinner').hide();
                    $('#btnPuantajUpload').prop('disabled', false);
                    Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                }
            });
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
                            // Atlanan satırlar varsa detaylı göster
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
                                loadTabContent('kacak_kontrol');
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

        $('#btnNewKacak').on('click', function () {
            $('#kacakManualForm input[name="id"]').val(0);
            $('#kacakManualForm')[0].reset();
            // Multiple select'i sıfırla
            $('#kacak_personel_ids').val([]).trigger('change');
            $('#kacakModalTitle').text('Yeni Kaçak Kontrol Kaydı');
            initPersonelSelect2();
            $('#kacakModal').modal('show');
        });

        function initPersonelSelect2() {
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
        }

        $('#kacakManualForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=kacak-kaydet';

            $.post('views/puantaj/api.php', formData, function (response) {
                var res = typeof response === 'object' ? response : JSON.parse(response);
                if (res.status === 'success') {
                    Swal.fire('Başarılı', 'Kayıt kaydedildi.', 'success');
                    $('#kacakModal').modal('hide');
                    loadTabContent('kacak_kontrol');
                } else {
                    Swal.fire('Hata', 'Kayıt edilemedi.', 'error');
                }
            });
        });

        $(document).on('click', '.edit-kacak', function () {
            var id = $(this).data('id');
            $.get('views/puantaj/api.php', { action: 'get-kacak-record', id: id }, function (response) {
                var record = typeof response === 'object' ? response : JSON.parse(response);
                $('#kacakManualForm input[name="id"]').val(record.id);
                $('#kacakManualForm input[name="tarih"]').val(record.tarih_formatted);
                $('#kacakManualForm input[name="sayi"]').val(record.sayi);
                $('#kacakManualForm input[name="aciklama"]').val(record.aciklama);
                $('#kacakModalTitle').text('Kaydı Düzenle');

                // Multiple select'i başlat ve seçili personelleri ayarla
                initPersonelSelect2();
                if (record.personel_ids_array && record.personel_ids_array.length > 0) {
                    $('#kacak_personel_ids').val(record.personel_ids_array).trigger('change');
                } else {
                    $('#kacak_personel_ids').val([]).trigger('change');
                }

                $('#kacakModal').modal('show');
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
                            loadKacakContent();
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });

        $(document).on('click', '.delete-endeks', function () {
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
                    $.post('views/puantaj/api.php', { action: 'endeks-sil', id: id }, function (response) {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Kayıt başarıyla silindi.', 'success');
                            if (endeksDataTable) {
                                endeksDataTable.ajax.reload(null, false);
                            } else {
                                loadTabContent('okuma');
                            }
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });

        $(document).on('click', '.delete-puantaj', function () {
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
                    $.post('views/puantaj/api.php', { action: 'puantaj-sil', id: id }, function (response) {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Kayıt başarıyla silindi.', 'success');
                            if (puantajDataTable) {
                                puantajDataTable.ajax.reload(null, false);
                            } else {
                                loadTabContent('yapilan_isler');
                            }
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });

        // Online Puantaj (Kesme/Açma) Sorgulama
        $('#onlinePuantajForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-puantaj-sorgula';

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

                        // API'den dönen ham veriyi konsola bas
                        if (res.api_raw_data) console.log("Kesme/Açma API Ham Veri:", res.api_raw_data);

                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı! (Toplam ' + (res.toplam_api_kayit || 0) + ' kayıt)</strong><br>';
                            resultHtml += '<span class="fs-5">' + res.yeni_kayit + ' adet yeni kayıt eklendi.</span>';
                            if (res.guncellenen_kayit > 0) {
                                resultHtml += '<br><span class="text-warning">' + res.guncellenen_kayit + ' adet kayıt güncellendi.</span>';
                            }
                            if (res.atlan_kayit_bos > 0) {
                                resultHtml += '<br><small class="text-secondary">' + res.atlan_kayit_bos + ' adet kayıt sonuçlanmadığı (0) için atlandı.</small>';
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
                                    resultHtml += '<button type="button" class="btn btn-sm btn-outline-dark fw-bold" onclick=\'exportToCsv(this, ' + JSON.stringify(res.atlanAn_kayitlar) + ', {"tarih":"Tarih","ekip_kodu":"Ekip Kodu","is_emri_tipi":"İş Emri Tipi","is_emri_sonucu":"İş Emri Sonucu"}, "eslesmeyen_ekipler")\' style="font-size: 11px;"><i class="mdi mdi-file-excel me-1"></i>Excel Olarak İndir</button>';
                                    resultHtml += '</div>';
                                } else {
                                    resultHtml += '<small>Sistemde tanımlı olmadığı için atlanan ekipler:</small><ul class="mb-0 mt-1 small" style="max-height:100px; overflow-y:auto;">';
                                    res.atlanAn_kayitlar.forEach(function (item) {
                                        resultHtml += '<li>' + item.ekip_kodu + ' (' + item.is_emri_tipi + ')</li>';
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
                            // Tabloyu güncelle
                            loadTabContent('yapilan_isler');
                        } else {
                            resultHtml = '<div class="alert alert-danger">';
                            resultHtml += '<strong><i class="bx bx-error-circle me-2"></i>Hata!</strong><br>';
                            resultHtml += res.message;
                            resultHtml += '</div>';
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
                    console.log("Raw Response:", response);
                    try {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        console.log("Online İcmal API Yanıtı:", res);
                        var resultHtml = '';
                        if (res.status === 'success') {
                            resultHtml = '<div class="alert alert-success">';
                            resultHtml += '<strong><i class="bx bx-check-circle me-2"></i>Sorgu Başarılı!</strong><br>';
                            resultHtml += '<span class="fs-5">' + res.yeni_kayit + ' adet yeni kayıt eklendi.</span>';
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
                            // Tabloyu güncelle
                            loadTabContent('okuma');
                        } else {
                            resultHtml = '<div class="alert alert-danger">';
                            resultHtml += '<strong><i class="bx bx-error-circle me-2"></i>Hata!</strong><br>';
                            resultHtml += res.message;
                            resultHtml += '</div>';
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
        });  // Modal kapanınca sonuç alanlarını temizle
        $('#importOnlinePuantajModal').on('hidden.bs.modal', function () {
            $('#onlinePuantajResult').hide().html('');
        });
        $('#importOnlineIcmalRaporuModal').on('hidden.bs.modal', function () {
            $('#onlineIcmalResult').hide().html('');
        });
        $('#importOnlineSayacDegisimModal').on('hidden.bs.modal', function () {
            $('#onlineSayacDegisimResult').hide().html('');
        });

        // Online Sayaç Değişim Sorgulama
        $('#onlineSayacDegisimForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=online-sayac-degisim-sorgula';

            $('#onlineSayacDegisimSpinner').show();
            $('#onlineSayacDegisimResult').hide();
            $('#btnOnlineSayacDegisimSorgula').prop('disabled', true);

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    $('#onlineSayacDegisimSpinner').hide();
                    $('#btnOnlineSayacDegisimSorgula').prop('disabled', false);
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
                            loadTabContent('sayac_sokme_takma');
                        } else {
                            resultHtml = '<div class="alert alert-danger">';
                            resultHtml += '<strong><i class="bx bx-error-circle me-2"></i>Hata!</strong><br>';
                            resultHtml += res.message;
                            resultHtml += '</div>';
                        }
                        $('#onlineSayacDegisimResult').html(resultHtml).show();
                    } catch (err) {
                        $('#onlineSayacDegisimResult').html('<div class="alert alert-danger">Sunucudan geçersiz yanıt alındı.</div>').show();
                    }
                },
                error: function () {
                    $('#onlineSayacDegisimSpinner').hide();
                    $('#btnOnlineSayacDegisimSorgula').prop('disabled', false);
                    $('#onlineSayacDegisimResult').html('<div class="alert alert-danger">Bağlantı hatası oluştu.</div>').show();
                }
            });
        });

        // Sayaç Değişim kaydı silme
        $(document).on('click', '.delete-sayac-degisim', function () {
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
                    $.post('views/puantaj/api.php', { action: 'sayac-degisim-sil', id: id }, function (response) {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Kayıt başarıyla silindi.', 'success');
                            if (sayacDegisimDataTable) {
                                sayacDegisimDataTable.ajax.reload(null, false);
                            } else {
                                loadTabContent('sayac_sokme_takma');
                            }
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });

        // Toplu İşlemler
        function handleBulkDelete(tableId, dataTable) {
            var selectedIds = [];
            $(`#${tableId} .row-check:checked`).each(function () {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                Swal.fire('Uyarı', 'Lütfen silinecek kayıtları seçiniz.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Emin misiniz?',
                text: `${selectedIds.length} adet kayıt silinecektir!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, toplu sil!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('views/puantaj/api.php', {
                        action: 'puantaj-sil-toplu',
                        ids: selectedIds
                    }, function (response) {
                        var res = typeof response === 'object' ? response : JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Seçili kayıtlar başarıyla silindi.', 'success');
                            $(`#checkAllPuantaj, #checkAllMuhurleme`).prop('checked', false);
                            dataTable.ajax.reload(null, false);
                        } else {
                            Swal.fire('Hata', 'Kayıtlar silinemedi: ' + (res.message || ''), 'error');
                        }
                    });
                }
            });
        }

        $('#btnBulkDeletePuantaj').on('click', function () {
            handleBulkDelete('puantajTable', puantajDataTable);
        });

        $('#btnBulkDeleteMuhurleme').on('click', function () {
            handleBulkDelete('muhurlemeTable', muhurlemeDataTable);
        });

        // Select All checkboxes
        $(document).on('change', '#checkAllPuantaj', function () {
            $('#puantajTable .row-check').prop('checked', $(this).prop('checked'));
        });

        $(document).on('change', '#checkAllMuhurleme', function () {
            $('#muhurlemeTable .row-check').prop('checked', $(this).prop('checked'));
        });
    });

    function exportToCsv(btn, data, Mapping, filename) {
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
        // Header
        csvContent += Object.values(Mapping).join(";") + "\r\n";
        // Body
        data.forEach(function (row) {
            let rowData = [];
            Object.keys(Mapping).forEach(key => {
                rowData.push(row[key] || '');
            });
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
</script>

<style>
    .accordion-button:not(.collapsed) {
        background-color: transparent;
        color: #556ee6;
        box-shadow: none;
    }

    .accordion-button {
        box-shadow: none !important;
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

    /* Tab altındaki tüm olası çizgileri kaldır */
    #puantajTabs,
    .nav-tabs-custom,
    .nav-tabs {
        border-bottom: none !important;
    }

    .nav-tabs-custom .nav-link,
    .nav-tabs-custom .nav-link.active {
        border: none !important;
        box-shadow: none !important;
    }

    .nav-tabs-custom .nav-link::after {
        display: none !important;
    }
</style>