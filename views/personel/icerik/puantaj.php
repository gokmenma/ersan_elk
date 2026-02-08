<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$PuantajModel = new PuantajModel();
$PersonelModel = new PersonelModel();

$startDate = Date::dmY(date('Y-m-d', strtotime('-30 days')));
$endDate = Date::today();
$ekip_no = $personel->ekip_no ?? '';

$workTypes = $PuantajModel->getWorkTypes();
$workTypeOptions = ['' => 'Tüm İşler'];
foreach ($workTypes as $wt) {
    $workTypeOptions[$wt] = $wt;
}

$workResults = $PuantajModel->getWorkResults($id);
$workResultOptions = ['' => 'Tüm Sonuçlar'];
foreach ($workResults as $wr) {
    $workResultOptions[$wr] = $wr;
}
?>

<div class="accordion accordion-flush mb-3" id="puantajFilterAccordion">
    <div class="accordion-item border-0 shadow-sm rounded">
        <h2 class="accordion-header" id="headingFilter">
            <button class="accordion-button fw-semibold rounded" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapseFilter" aria-expanded="true" aria-controls="collapseFilter">
                <i class="bx bx-filter-alt me-2 text-primary"></i> Filtreleme Seçenekleri
            </button>
        </h2>
        <div id="collapseFilter" class="accordion-collapse collapse show" aria-labelledby="headingFilter"
            data-bs-parent="#puantajFilterAccordion">
            <div class="accordion-body bg-light bg-opacity-10">
                <form id="puantajFilterForm">
                    <input type="hidden" name="ekip_kodu" value="<?php echo $id; ?>">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <?php echo Form::FormFloatInput(
                                type: 'text',
                                name: 'start_date',
                                value: $startDate,
                                placeholder: '',
                                label: "Başlangıç Tarihi",
                                icon: "calendar",
                                class: "form-control flatpickr"
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
                                class: "form-control flatpickr"
                            ); ?>
                        </div>
                        <div class="col-md-3" id="workTypeFilterContainer" style="display: none;">
                            <?php echo Form::FormSelect2(
                                name: 'work_type',
                                options: $workTypeOptions,
                                selectedValue: '',
                                textField: "",
                                label: "Yapılan İş",
                                icon: "briefcase",
                                valueField: "key"
                            ); ?>
                        </div>
                        <div class="col-md-3" id="workResultFilterContainer" style="display: none;">
                            <?php echo Form::FormSelect2(
                                name: 'work_result',
                                options: $workResultOptions,
                                selectedValue: '',
                                textField: "",
                                label: "İş Sonucu",
                                icon: "check-circle",
                                valueField: "key"
                            ); ?>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" style="height: 50px;">
                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist" id="puantajTabs">
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#okuma" role="tab" data-tab-name="okuma"
            data-no-url-update="true">
            Okuma İşlemleri
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#yapilan_isler" role="tab" data-tab-name="yapilan_isler"
            data-no-url-update="true">
            Yapılan İşler
        </a>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane" id="okuma" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Endeks Okuma Raporu</h4>
                <button type="button" class="btn btn-success btn-sm" id="btnExportEndeksExcel">
                    <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
                </button>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="endeksTable"
                        class="table table-bordered table-hover dt-responsive nowrap w-100 datatable">
                        <thead>
                            <tr class="table-light">
                                <th>Bölgesi</th>
                                <th>Personel Adı</th>
                                <th>Sarfiyat</th>
                                <th>Ort. Sarfiyat</th>
                                <th>Tahakkuk</th>
                                <th>Ort. Tahakkuk</th>
                                <th>Okunan Gün</th>
                                <th>Okunan Abone</th>
                                <th>Ort. Abone</th>
                                <th>Perf. (%)</th>
                                <th>Tarih</th>
                                <th class="no-export">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="okumaBody">
                            <!-- AJAX Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane active" id="yapilan_isler" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">İş Listesi</h4>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#modalPuantajEkle">
                        <i class="bx bx-plus me-1"></i> Yeni İş Ekle
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="btnExportPuantajExcel">
                        <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="puantajTable"
                        class="table table-bordered table-hover dt-responsive nowrap w-100 datatable">
                        <thead>
                            <tr class="table-light">
                                <th>Firma</th>
                                <th>İş Emri Tipi</th>
                                <th>Ekip (Personel)</th>
                                <th>İş Emri Sonucu</th>
                                <th>Sonuçlanmış</th>
                                <th>Açık Olanlar</th>
                                <th>Tarih</th>
                                <th class="no-export">İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="yapilanIslerBody">
                            <!-- AJAX Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manuel İş Ekleme Modalı -->
<div class="modal fade" id="modalPuantajEkle" tabindex="-1" aria-labelledby="modalPuantajEkleLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPuantajEkleLabel">Yeni İş Ekle (Manuel)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formPuantajEkle">
                <div class="modal-body">
                    <input type="hidden" name="action" value="puantaj-manuel-kaydet">
                    <input type="hidden" name="personel_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="ekip_kodu" value="<?php echo $ekip_no; ?>">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput("text", "tarih", date('d.m.Y'), "", "Tarih", "calendar", "form-control flatpickr"); ?>
                        </div>
                        <div class="col-md-6">
                            <?php
                            echo Form::FormSelect2("is_emri_tipi", $workTypeOptions, "", "İş Tipi", "briefcase");
                            ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php
                            echo Form::FormSelect2("is_emri_sonucu", $workResultOptions, "", "İş Emri Sonucu (Seçebilir veya Yazabilirsiniz)", "check-circle", "key", "", "form-select select2-tags");
                            ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput("number", "sonuclanmis", "0", "0", "Sonuçlanmış", "check", "form-control", true, null, "on", false, 'step="1"'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput("number", "acik_olanlar", "0", "0", "Açık Olanlar", "play", "form-control", true, null, "on", false, 'step="1"'); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea("aciklama", "", "Açıklama giriniz", "Açıklama", "file-text", "form-control", false, "80px"); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary" id="btnPuantajKaydet">Kaydet</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        function loadTabContent(tabName) {
            var formData = {
                action: 'get-tab-content',
                tab: tabName,
                start_date: $('#puantajFilterForm input[name="start_date"]').val(),
                end_date: $('#puantajFilterForm input[name="end_date"]').val(),
                ekip_kodu: $('#puantajFilterForm input[name="ekip_kodu"]').val(),
                work_type: $('#puantajFilterForm select[name="work_type"]').val(),
                work_result: $('#puantajFilterForm select[name="work_result"]').val()
            };

            var targetBody = tabName === 'okuma' ? '#okumaBody' : '#yapilanIslerBody';
            var targetTable = tabName === 'okuma' ? '#endeksTable' : '#puantajTable';
            var colCount = tabName === 'okuma' ? 12 : 8;
            $(targetBody).html('<tr><td colspan="' + colCount + '" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>');

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

                    var dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
                    dtOptions.order = [[tabName === 'okuma' ? 10 : 6, "desc"]];

                    $(targetTable).DataTable(dtOptions);
                }
            });
        }

        // Initial load
        loadTabContent('yapilan_isler');
        $('#workTypeFilterContainer').show();
        $('#workResultFilterContainer').show();

        // T ab click event
        $('#puantajTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
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
        $('#puantajFilterForm').on('submit', function (e) {
            e.preventDefault();
            var activeTab = $('#puantajTabs .nav-link.active').data('tab-name');
            loadTabContent(activeTab);
        });

        // Excel Exports
        $('#btnExportEndeksExcel').on('click', function () {
            window.location.href = `views/personel/api.php?action=export-puantaj&id=<?php echo $id; ?>&type=okuma&start_date=${$('#puantajFilterForm input[name="start_date"]').val()}&end_date=${$('#puantajFilterForm input[name="end_date"]').val()}`;
        });

        $('#btnExportPuantajExcel').on('click', function () {
            window.location.href = `views/personel/api.php?action=export-puantaj&id=<?php echo $id; ?>&type=puantaj&start_date=${$('#puantajFilterForm input[name="start_date"]').val()}&end_date=${$('#puantajFilterForm input[name="end_date"]').val()}`;
        });

        // Manuel Ka yıt İşlemi
        $('#formPuantajEkle').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btnPuantajKaydet');
            const originalText = btn.html();

            btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Kaydediliyor...');

            $.ajax({
                url: 'views/personel/api.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            $('#modalPuantajEkle').modal('hide');
                            loadTabContent('yapilan_isler');
                        });
                    } else {
                        Swal.fire('Hata', response.message, 'error');
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function () {
                    Swal.fire('Hata', 'Bir hata oluştu.', 'error');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Plugins init
        if (typeof flatpickr !== 'undefined') {
            $(".flatpickr").flatpickr({
                dateFormat: "d.m.Y",
                locale: "tr"
            });
        }

        if ($.fn.select2) {
            $('.select2-tags').select2({
                tags: true,
                dropdownParent: $('#modalPuantajEkle'),
                width: '100%'
            });
        }
    });
</script>