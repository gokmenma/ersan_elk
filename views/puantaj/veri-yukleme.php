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
    $workResultOptions[$wr] = $wr;
}


// PHP tarafında aktif sekmeyi belirle (URL -> Storage (yok) -> Varsayılan)
$activeTab = $_GET['tab'] ?? 'okuma';
?>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "İş Takip Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="accordion" id="filterAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
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

                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                        data-bs-parent="#filterAccordion">
                        <div class="accordion-body pt-3">
                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="p" value="puantaj/veri-yukleme">
                                <input type="hidden" name="tab" id="activeTabInput"
                                    value="<?= $_GET['tab'] ?? 'okuma' ?>">
                                <div class="row g-3">
                                    <div class="col-md-2">
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
                                    <div class="col-md-2">
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
                                    <div class="col-md-3">
                                        <?php echo Form::FormSelect2('ekip_kodu', $personelOptions, $ekipKodu, 'Personel Adı Soyadı', 'grid', 'key', '', 'form-select select2'); ?>
                                    </div>
                                    <div class="col-md-2" id="workTypeFilterContainer"
                                        style="display: <?= $activeTab === 'yapilan_isler' ? 'block' : 'none' ?>;">
                                        <?php echo Form::FormSelect2(
                                            name: 'work_type',
                                            options: $workTypeOptions,
                                            selectedValue: $workType,
                                            textField: "",
                                            label: "Yapılan İş",
                                            icon: "users",
                                            valueField: "key"
                                        ); ?>
                                    </div>
                                    <div class="col-md-3" id="workResultFilterContainer"
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
        <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist" id="puantajTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'okuma' ? 'active' : '' ?>" data-bs-toggle="tab" href="#okuma"
                    role="tab" data-tab-name="okuma">
                    <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                    <span class="d-none d-sm-block">Okuma İşlemleri</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'yapilan_isler' ? 'active' : '' ?>" data-bs-toggle="tab"
                    href="#yapilan_isler" role="tab" data-tab-name="yapilan_isler">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Kesme/Açma İşlem.</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'sayac_sokme_takma' ? 'active' : '' ?>" data-bs-toggle="tab"
                    href="#sayac_sokme_takma" role="tab" data-tab-name="sayac_sokme_takma">
                    <span class="d-block d-sm-none"><i class="fas fa-exchange-alt"></i></span>
                    <span class="d-none d-sm-block">Sayaç Sökme Takma</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'muhurleme' ? 'active' : '' ?>" data-bs-toggle="tab"
                    href="#muhurleme" role="tab" data-tab-name="muhurleme">
                    <span class="d-block d-sm-none"><i class="fas fa-lock"></i></span>
                    <span class="d-none d-sm-block">Mühürleme</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'kacak_kontrol' ? 'active' : '' ?>" data-bs-toggle="tab"
                    href="#kacak_kontrol" role="tab" data-tab-name="kacak_kontrol">
                    <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                    <span class="d-none d-sm-block">Kaçak Kontrol</span>
                </a>
            </li>

        </ul>

        <div class="tab-content" id="puantajTabContent">
            <div class="tab-pane <?= $activeTab === 'okuma' ? 'active' : '' ?>" id="okuma" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Endeks Okuma Raporu</h4>
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
                    <div class="card-body">
                        <table id="endeksTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
                                    <th data-filter="date">Tarih</th>
                                    <th data-filter="string">Defter</th>
                                    <th data-filter="string">Bölgesi</th>
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
                        <h4 class="card-title">İş Listesi</h4>
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
                                        <button class="dropdown-item d-flex align-items-center text-primary fw-medium" type="button" data-bs-toggle="modal" data-bs-target="#importPuantajModal">
                                            <i class="mdi mdi-upload fs-5 me-2"></i> Excel Yükle
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="puantajTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
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
                        <h4 class="card-title">Kaçak Kontrol Listesi</h4>
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
                        <h4 class="card-title">Sayaç Sökme Takma Listesi</h4>
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
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
                        <h4 class="card-title">Mühürleme İş Listesi</h4>
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
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table id="muhurlemeTable" class="table table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr class="table-light">
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
    <div class="modal-dialog modal-lg">
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



<script>
    $(document).ready(function () {
        // Server-side DataTable instances
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
            const ekipKodu = $('select[name="ekip_kodu"]').val();
            const ekipText = $('select[name="ekip_kodu"] option:selected').text();
            const workType = $('select[name="work_type"]').val();
            const workTypeText = $('select[name="work_type"] option:selected').text();
            const workResult = $('select[name="work_result"]').val();
            const workResultText = $('select[name="work_result"] option:selected').text();
            const activeTab = $('#activeTabInput').val();

            if (startDate && endDate) {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Tarih:</span><span class="badge-value">${startDate} - ${endDate}</span></div>`;
            }

            if (ekipKodu && ekipKodu !== '') {
                summary += `<div class="filter-summary-badge"><span class="badge-label">Pers:</span><span class="badge-value">${ekipText}</span><button type="button" class="btn-clear-filter" data-filter="ekip_kodu"><i class="bx bx-x"></i></button></div>`;
            }

            if (activeTab === 'yapilan_isler') {
                if (workType && workType !== '') {
                    summary += `<div class="filter-summary-badge"><span class="badge-label">İş:</span><span class="badge-value">${workTypeText}</span><button type="button" class="btn-clear-filter" data-filter="work_type"><i class="bx bx-x"></i></button></div>`;
                }
                if (workResult && workResult !== '') {
                    summary += `<div class="filter-summary-badge"><span class="badge-label">Sonuç:</span><span class="badge-value">${workResultText}</span><button type="button" class="btn-clear-filter" data-filter="work_result"><i class="bx bx-x"></i></button></div>`;
                }
            }

            $('#filterSummary').html(summary);
        }

        $(document).on('click', '.btn-clear-filter', function (e) {
            e.stopPropagation();
            const filterType = $(this).data('filter');
            if (filterType === 'ekip_kodu') {
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
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
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
            if (savedFilters) {
                var filters = JSON.parse(savedFilters);

                // Tarihler ve Tab her zaman storage'dan veya URL'den gelmeli
                if (!urlParams.has('start_date') && filters.start_date) $('input[name="start_date"]').val(filters.start_date);
                if (!urlParams.has('end_date') && filters.end_date) $('input[name="end_date"]').val(filters.end_date);
                if (!urlParams.has('tab') && filters.tab) {
                    $('#activeTabInput').val(filters.tab);
                    // Manuel olarak sınıfları değiştiriyoruz ki 'jump' (animasyon) olmasın
                    $(`#puantajTabs a`).removeClass('active');
                    $(`.tab-pane`).removeClass('active show');

                    var $targetTab = $(`#puantajTabs a[data-tab-name="${filters.tab}"]`);
                    if ($targetTab.length > 0) {
                        $targetTab.addClass('active');
                        $($targetTab.attr('href')).addClass('active show');
                    } else {
                        // Eğer storage'daki tab artık yoksa varsayılana dön
                        $(`#puantajTabs a[data-tab-name="okuma"]`).addClass('active');
                        $('#okuma').addClass('active show');
                    }
                }

                // Diğer filtreler sadece URL boşsa storage'dan
                if (!hasFilters) {
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
                updateFilterSummary();
            }
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
            var ekipKodu = $('select[name="ekip_kodu"]').val();
            var workType = $('select[name="work_type"]').val();
            var workResult = $('select[name="work_result"]').val();

            if (startDate) url.searchParams.set('start_date', startDate);
            if (endDate) url.searchParams.set('end_date', endDate);
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
            } else {
                $('#workTypeFilterContainer').hide();
                $('#workResultFilterContainer').hide();
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
                processing: true,
                serverSide: true,
                language: $.extend({}, baseOptions.language, {
                    processing: '<div class="spinner-border text-primary" role="status"></div>'
                }),
                buttons: []
            }, customOptions);
        }


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
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
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
                        d.start_date = $('input[name="start_date"]').val();
                        d.end_date = $('input[name="end_date"]').val();
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                        d.work_type = $('select[name="work_type"]').val();
                        d.work_result = $('select[name="work_result"]').val();
                    }
                },
                columns: [
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
                order: [[0, 'desc']]
            }));
        }

        // Kaçak Kontrol tablosu için Client-Side DataTable (az veri olduğu için)
        function loadKacakContent() {
            var formData = {
                action: 'get-tab-content',
                tab: 'kacak_kontrol',
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
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
                    kacakDataTable = $('#kacakTable').DataTable(getDatatableOptions());
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
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
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
                        d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                    }
                },
                columns: [
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
                order: [[0, 'desc']]
            }));
        }

        function loadTabContent(tabName) {
            initialTabLoaded = true;
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