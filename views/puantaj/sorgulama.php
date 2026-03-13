<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$Puantaj = new PuantajModel('yapilan_isler_sorgu');
$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$ekipKodu = $_GET['ekip_kodu'] ?? '';
$workType = $_GET['work_type'] ?? '';

$personeller = $Personel->all(false, 'puantaj');
$personelOptions = ['' => 'Seçiniz'];
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}

$workTypes = $Puantaj->getWorkTypes();
$workTypeOptions = ['' => 'Tüm İşler'];
foreach ($workTypes as $wt) {
    if (!empty($wt)) $workTypeOptions[$wt] = $wt;
}

$workResults = $Puantaj->getWorkResults();
$workResultOptions = ['' => 'Tüm Sonuçlar'];
foreach ($workResults as $wr) {
    if (!empty($wr)) $workResultOptions[$wr] = $wr;
}
?>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "Kesme Açma Sorgulama";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <form method="GET" action="" id="filterForm">
                        <input type="hidden" name="p" value="puantaj/sorgulama">
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
                            <div class="col-md-2">
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
                            <div class="col-md-2">
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
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="mdi mdi-filter-variant"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Sorgu Sonuçları (yapilan_isler_sorgu)</h4>
                    <div>
                        <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importOnlineSorguModal">
                            <i class="mdi mdi-cloud-search-outline me-1"></i> Online Sorgula
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="sorguTable" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr class="table-light">
                                <th>Tarih</th>
                                <th>Ekip Kodu</th>
                                <th>Personel</th>
                                <th>İş Emri Tipi</th>
                                <th>İş Emri Sonucu</th>
                                <th>Sonuçlanmış</th>
                                <th>Açık Olanlar</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Online Sorgu Modal -->
<div class="modal fade" id="importOnlineSorguModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Online Kesme/Açma İşlemleri Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="onlineSorguForm">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i data-feather="alert-triangle" class="me-2"></i>
                        Bu işlem sadece verileri incelemek içindir. <strong>Asıl puantaj ve zimmet kayıtlarını etkilemez.</strong>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text','baslangic_tarihi', Date::today(), 'Başlangıç Tarihi','Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text','bitis_tarihi', Date::today(), 'Bitiş Tarihi','Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormSelect2('filter_personel_id', $personelOptions, '', 'Personel Filtresi', 'users', 'key', '', 'form-select select2'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php 
                            $ekipKodlari = $Puantaj->db->query("SELECT id, tur_adi FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
                            $ekipOptions = ['' => 'Tüm Ekipler'] + $ekipKodlari;
                            echo Form::FormSelect2('filter_ekip_kodu', $ekipOptions, '', 'Ekip Kodu Filtresi', 'grid', 'key', '', 'form-select select2'); 
                            ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormSelect2('filter_work_type', $workTypeOptions, '', 'İş Türü Filtresi', 'activity', 'key', '', 'form-select select2'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormSelect2('filter_work_result', $workResultOptions, '', 'İş Sonucu Filtresi', 'check-circle', 'key', '', 'form-select select2'); ?>
                        </div>
                    </div>
                    <div id="onlineSorguSpinner" class="text-center p-3" style="display: none;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Sorgulanıyor...</p>
                    </div>
                    <div id="onlineSorguResult" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnOnlineSorguSorgula">Sorgula ve Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#sorguTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'views/puantaj/api.php',
            data: function(d) {
                d.action = 'get-puantaj-sorgu-datatable';
                d.start_date = $('input[name="start_date"]').val();
                d.end_date = $('input[name="end_date"]').val();
                d.ekip_kodu = $('select[name="ekip_kodu"]').val();
                d.work_type = $('select[name="work_type"]').val();
                d.work_result = $('select[name="work_result"]').val();
            }
        },
        columns: [
            { data: 'tarih' },
            { data: 'ekip_kodu' },
            { data: 'personel_adi' },
            { data: 'is_emri_tipi' },
            { data: 'is_emri_sonucu' },
            { data: 'sonuclanmis' },
            { data: 'acik_olanlar' },
            {
                data: 'id',
                render: function(data) {
                    return '<button class="btn btn-danger btn-sm delete-sorgu" data-id="' + data + '"><i class="bx bx-trash"></i></button>';
                }
            }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json' }
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    $('#onlineSorguForm').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=online-sorgu-sorgula';
        $('#onlineSorguSpinner').show();
        $('#btnOnlineSorguSorgula').prop('disabled', true);
        $('#onlineSorguResult').hide();

        $.post('views/puantaj/api.php', formData, function(res) {
            $('#onlineSorguSpinner').hide();
            $('#btnOnlineSorguSorgula').prop('disabled', false);
            var html = '';
            if (res.status === 'success') {
                html = '<div class="alert alert-success">' + res.message + '</div>';
                table.ajax.reload();
            } else {
                html = '<div class="alert alert-danger">' + res.message + '</div>';
            }
            $('#onlineSorguResult').html(html).show();
        }, 'json');
    });

    $(document).on('click', '.delete-sorgu', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/puantaj/api.php', { action: 'sorgu-sil', id: id }, function(res) {
                    if (res.status === 'success') {
                        table.ajax.reload(null, false);
                        Swal.fire('Silindi!', '', 'success');
                    }
                }, 'json');
            }
        });
    });
});
</script>
