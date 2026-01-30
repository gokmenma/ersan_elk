<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Model\EndeksOkumaModel;

$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$ekipKodu = $_GET['ekip_kodu'] ?? '';
$workType = $_GET['work_type'] ?? '';


//Helper::dd([$startDate, $endDate, $ekipKodu, $workType]);

// $records = $Puantaj->getFiltered($startDate, $endDate, $ekipKodu, $workType);
$personeller = $Personel->all();
$personelOptions = ['' => 'Seçiniz'];
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
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



//Helper::dd($personelOptions);

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
                <div class="card-header">
                    <h4 class="card-title">Filtreleme</h4>
                    <form method="GET" action="">
                        <input type="hidden" name="p" value="puantaj/list">
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
                            <div class="col-md-2" id="workTypeFilterContainer" style="display: none;">
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
                            <div class="col-md-3" id="workResultFilterContainer" style="display: none;">
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
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="index.php?p=puantaj/list" class="btn btn-secondary">Temizle</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    $activeTab = $_GET['tab'] ?? 'okuma';
    ?>

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
            <a class="nav-link <?= $activeTab === 'kacak_kontrol' ? 'active' : '' ?>" data-bs-toggle="tab"
                href="#kacak_kontrol" role="tab" data-tab-name="kacak_kontrol">
                <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                <span class="d-none d-sm-block">Kaçak Kontrol</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane <?= $activeTab === 'okuma' ? 'active' : '' ?>" id="okuma" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Endeks Okuma Raporu</h4>
                    <div class="d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-dots-vertical-rounded"></i> İşlemler
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" id="btnExportEndeksExcel">
                                        <i class="bx bx-spreadsheet me-2"></i> Excele Aktar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" id="btnShowStats">
                                        <i class="bx bx-pie-chart-alt-2 me-2"></i> İstatistikler
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                            data-bs-target="#importEndeksModal">
                            <i class="bx bxs-file-import"></i> Dosya Yükle
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="endeksTable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr class="table-light">
                                <th>Bölgesi</th>
                                <th>Personel Adı</th>
                                <th>Sarfiyat</th>
                                <th>Ort. Sarfiyat (Günlük)</th>
                                <th>Tahakkuk</th>
                                <th>Ort. Tahakkuk (Günlük)</th>
                                <th>Okunan Gün</th>
                                <th>Okunan Abone</th>
                                <th>Ort. Okunan Abone</th>
                                <th>Okuma Perf. (%)</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody id="okumaBody">
                            <!-- AJAX Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane <?= $activeTab === 'yapilan_isler' ? 'active' : '' ?>" id="yapilan_isler" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">İş Listesi</h4>
                    <div class="d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-soft-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-dots-vertical-rounded"></i> İşlemler
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" id="btnExportPuantajExcel">
                                        <i class="bx bx-spreadsheet me-2"></i> Excele Aktar
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="javascript:void(0);" id="btnShowPuantajStats">
                                        <i class="bx bx-pie-chart-alt-2 me-2"></i> İstatistikler
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                            data-bs-target="#importPuantajModal">
                            <i class="bx bxs-file-import"></i> Excel Yükle
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="puantajTable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr class="table-light">
                                <th>Firma</th>
                                <th>İş Emri Tipi</th>
                                <th>Ekip (Personel)</th>
                                <th>İş Emri Sonucu</th>
                                <th>Sonuçlanmış</th>
                                <th>Açık Olanlar</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody id="yapilanIslerBody">
                            <!-- AJAX Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane <?= $activeTab === 'kacak_kontrol' ? 'active' : '' ?>" id="kacak_kontrol" role="tabpanel">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Kaçak Kontrol Listesi</h4>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" id="btnNewKacak">
                            <i class="bx bx-plus"></i> Yeni Ekle
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal"
                            data-bs-target="#importKacakModal">
                            <i class="bx bxs-file-import"></i> Excel Yükle
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="kacakTable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr class="table-light">
                                <th>Tarih</th>
                                <th>Ekip (Personel)</th>
                                <th>Sayı</th>
                                <th>Açıklama</th>
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
                        <?php echo Form::FormSelect2(
                            name: 'kacak_ekip_adi',
                            options: [],
                            selectedValue: '',
                            label: 'Ekip Adı',
                            icon: 'users',
                            class: 'form-select select2-tags',
                            required: true
                        ); ?>
                        <small class="text-muted">Listeden seçebilir veya yeni bir isim yazarak Enter'a
                            basabilirsiniz.</small>
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

<script>
    $(document).ready(function () {
        function loadTabContent(tabName) {
            var formData = {
                action: 'get-tab-content',
                tab: tabName,
                start_date: $('input[name="start_date"]').val(),
                end_date: $('input[name="end_date"]').val(),
                ekip_kodu: $('select[name="ekip_kodu"]').val(),
                work_type: $('select[name="work_type"]').val(),
                work_result: $('select[name="work_result"]').val()
            };

            var targetBody = '#okumaBody';
            var targetTable = '#endeksTable';

            if (tabName === 'yapilan_isler') {
                targetBody = '#yapilanIslerBody';
                targetTable = '#puantajTable';
            } else if (tabName === 'kacak_kontrol') {
                targetBody = '#kacakKontrolBody';
                targetTable = '#kacakTable';
            }

            $(targetBody).html('<tr><td colspan="11" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'GET',
                data: formData,
                success: function (html) {
                    if ($.fn.DataTable.isDataTable(targetTable)) {
                        $(targetTable).DataTable().destroy();
                        $(targetTable).find('thead .search-input-row').remove();
                    }
                    $(targetBody).html(html);
                    $(targetTable).DataTable(getDatatableOptions());
                }
            });
        }

        // Initial load
        var activeTab = '<?= $activeTab ?>';
        loadTabContent(activeTab);
        if (activeTab === 'yapilan_isler') {
            $('#workTypeFilterContainer').show();
            $('#workResultFilterContainer').show();
        } else {
            $('#workTypeFilterContainer').hide();
            $('#workResultFilterContainer').hide();
        }

        // Tab click event
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            var tabName = $(e.target).data('tab-name');
            if (tabName === 'yapilan_isler') {
                $('#workTypeFilterContainer').show();
                $('#workResultFilterContainer').show();
            } else {
                $('#workTypeFilterContainer').hide();
                $('#workResultFilterContainer').hide();
            }
            loadTabContent(tabName);
        });

        // Filter form submit
        $('form').on('submit', function (e) {
            e.preventDefault();
            var activeTab = $('#puantajTabs .nav-link.active').data('tab-name');
            loadTabContent(activeTab);
        });

        // Excel Export
        $('#btnExportEndeksExcel').on('click', function () {
            var table = $('#endeksTable').DataTable();
            table.button('.buttons-excel').trigger();
        });

        $('#btnExportPuantajExcel').on('click', function () {
            var table = $('#puantajTable').DataTable();
            table.button('.buttons-excel').trigger();
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

            $('#statsModal .modal-title').text('İş Emri Tipi Bazlı İstatistikler');
            $('#statsModal').modal('show');
            $('#statsModalBody').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">İstatistikler hazırlanıyor...</p></div>');

            $.get('views/puantaj/modal_puantaj_istatistik.php', {
                start_date: startDate,
                end_date: endDate,
                personel_id: personelId,
                work_type: workType,
                work_result: $('select[name="work_result"]').val()
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
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Başarılı', res.message, 'success').then(() => {
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
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Başarılı', res.message, 'success').then(() => {
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
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Başarılı', res.message, 'success').then(() => {
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
            $('#kacak_ekip_adi').val('').trigger('change');
            $('#kacakModalTitle').text('Yeni Kaçak Kontrol Kaydı');
            loadKacakTeams();
            $('#kacakModal').modal('show');
        });

        function loadKacakTeams(selectedTeam = '') {
            $.get('views/puantaj/api.php', { action: 'get-kacak-teams' }, function (response) {
                var teams = JSON.parse(response);
                var options = '<option value="">Seçiniz</option>';
                teams.forEach(function (team) {
                    options += '<option value="' + team + '">' + team + '</option>';
                });
                $('#kacak_ekip_adi').html(options);

                if (!$('#kacak_ekip_adi').hasClass('select2-hidden-accessible')) {
                    $('#kacak_ekip_adi').select2({
                        dropdownParent: $('#kacakModal'),
                        tags: true
                    });
                }

                if (selectedTeam) {
                    if ($('#kacak_ekip_adi').find("option[value='" + selectedTeam + "']").length) {
                        $('#kacak_ekip_adi').val(selectedTeam).trigger('change');
                    } else {
                        var newOption = new Option(selectedTeam, selectedTeam, true, true);
                        $('#kacak_ekip_adi').append(newOption).trigger('change');
                    }
                }
            });
        }

        $('#kacakManualForm').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=kacak-kaydet';

            $.post('views/puantaj/api.php', formData, function (response) {
                var res = JSON.parse(response);
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
                var record = JSON.parse(response);
                $('#kacakManualForm input[name="id"]').val(record.id);
                $('#kacakManualForm input[name="tarih"]').val(record.tarih_formatted);
                $('#kacakManualForm input[name="sayi"]').val(record.sayi);
                $('#kacakManualForm input[name="aciklama"]').val(record.aciklama);
                $('#kacakModalTitle').text('Kaydı Düzenle');
                loadKacakTeams(record.ekip_adi);
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
                        var res = JSON.parse(response);
                        if (res.status === 'success') {
                            Swal.fire('Silindi!', 'Kayıt başarıyla silindi.', 'success');
                            loadTabContent('kacak_kontrol');
                        } else {
                            Swal.fire('Hata', 'Kayıt silinemedi.', 'error');
                        }
                    });
                }
            });
        });
    });
</script>