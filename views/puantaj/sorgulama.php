<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$dateRangeValue = $startDate . ' - ' . $endDate;

$personeller = $Personel->all(false, 'puantaj');
$personelOptions = ['' => 'Tüm Personeller'];
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}

$Puantaj = new PuantajModel('yapilan_isler_sorgu');
?>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "İşlemler Sorgulama";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-0" role="alert">
                <i class="bx bx-info-circle fs-4 me-2"></i>
                <div class="ms-2">
                    <strong>Bilgilendirme:</strong> Bu sayfadaki veriler sadece sorgulama ve önizleme amaçlıdır. Buradaki işlemler doğrudan puantaj hesabını, hakedişleri veya hiçbir resmi işlemi <strong>etkilemez!</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card overflow-hidden">
                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-puantaj" role="tab">
                                <span class="d-block d-sm-none"><i class="mdi mdi-electric-switch-closed"></i></span>
                                <span class="d-none d-sm-block"><i class="mdi mdi-electric-switch-closed me-1"></i> Kesme / Açma / Mühürleme</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-endeks" role="tab">
                                <span class="d-block d-sm-none"><i class="mdi mdi-counter"></i></span>
                                <span class="d-none d-sm-block"><i class="mdi mdi-counter me-1"></i> Endeks Okuma</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-sayac" role="tab">
                                <span class="d-block d-sm-none"><i class="mdi mdi-water-pump-off"></i></span>
                                <span class="d-none d-sm-block"><i class="mdi mdi-water-pump-off me-1"></i> Sayaç Değişimi</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <!-- Global Filters & Actions -->
                    <div class="row g-2 align-items-end border-bottom pb-4 mb-4">
                        <div class="col-lg-2">
                            <form method="GET" action="" id="filterForm">
                                <input type="hidden" name="p" value="puantaj/sorgulama">
                                <?php echo Form::FormDateRange('date_range', $dateRangeValue, 'Tarih Aralığı'); ?>
                            </form>
                        </div>
                        <div class="col-lg-3">
                            <?php echo Form::FormSelect2('ekip_kodu', $personelOptions, '', 'Personel Seçiniz', 'users', 'key', '', 'form-select select2'); ?>
                        </div>
                        <div class="col-lg-1">
                            <button type="submit" form="filterForm" class="btn btn-primary w-100 fw-bold shadow-sm" style="padding: 11px 0;">
                                <i class="mdi mdi-filter-variant me-1"></i> Filtrele
                            </button>
                        </div>
                        <div class="col-lg-6 text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-info btn-sm fw-bold shadow-sm" id="btnTriggerOnlineSorgu">
                                    <i class="mdi mdi-cloud-search-outline me-1"></i> Online Sorgula
                                </button>
                                <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm" id="btnTriggerExcel">
                                    <i class="mdi mdi-file-excel me-1"></i> Excel
                                </button>
                                <button type="button" class="btn btn-danger btn-sm fw-bold bulk-delete-main shadow-sm" style="display: none;">
                                    <i class="mdi mdi-trash-can me-1"></i> Sil
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-puantaj" role="tabpanel">
                            <table id="tblPuantajSorgu" class="table table-hover table-bordered dt-responsive nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20px;"><input class="form-check-input check-all" type="checkbox"></th>
                                        <th>Tarih</th>
                                        <th>Ekip Kodu</th>
                                        <th>Personel</th>
                                        <th>İş Emri Tipi</th>
                                        <th>İş Emri Sonucu</th>
                                        <th>Ücret Durumu</th>
                                        <th>Sonuçlanmış</th>
                                        <th>Açık Olanlar</th>
                                        <th style="width: 50px;">İşlem</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-endeks" role="tabpanel">
                            <table id="tblEndeksSorgu" class="table table-hover table-bordered dt-responsive nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20px;"><input class="form-check-input check-all" type="checkbox"></th>
                                        <th>Tarih</th>
                                        <th>Defter</th>
                                        <th>Bölge</th>
                                        <th>Ekip Adı</th>
                                        <th>Personel</th>
                                        <th>Abone Sayısı</th>
                                        <th>Sayaç Durum</th>
                                        <th style="width: 50px;">İşlem</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>

                        <div class="tab-pane" id="tab-sayac" role="tabpanel">
                            <table id="tblSayacSorgu" class="table table-hover table-bordered dt-responsive nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20px;"><input class="form-check-input check-all" type="checkbox"></th>
                                        <th>Tarih</th>
                                        <th>Ekip No</th>
                                        <th>Personel</th>
                                        <th>Bölge</th>
                                        <th>Sebep</th>
                                        <th>Sonuç</th>
                                        <th>Abone No</th>
                                        <th>Takılan Sayaç No</th>
                                        <th style="width: 50px;">İşlem</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals for Online Query -->
<div class="modal fade" id="modalOnlinePuantaj" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg text-start">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white">Online Kesme / Açma Sorgula</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="online-sorgu-form" data-type="KESME_ACMA">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <?php echo Form::FormDateRange('modal_date_range', $dateRangeValue, 'Sorgulama Tarih Aralığı'); ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold btn-sorgula">Sorgula</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOnlineEndeks" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg text-start">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title text-white">Online Endeks Okuma Sorgula</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="online-sorgu-form" data-type="ENDEKS_OKUMA">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <?php echo Form::FormDateRange('modal_date_range', $dateRangeValue, 'Sorgulama Tarih Aralığı'); ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info text-white px-4 fw-bold btn-sorgula">Sorgula</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOnlineSayac" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg text-start">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title text-white">Online Sayaç Değişimi Sorgula</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="online-sorgu-form" data-type="SAYAC_DEGISIM">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <?php echo Form::FormDateRange('modal_date_range', $dateRangeValue, 'Sorgulama Tarih Aralığı'); ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning text-white px-4 fw-bold btn-sorgula">Sorgula</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var tables = {};

    $(".flatpickr-range").flatpickr({
        mode: "range",
        locale: "tr",
        dateFormat: "d.m.Y",
        allowInput: true
    });

    function getDatesFromRange(rangeStr) {
        if (!rangeStr) return { start: '', end: '' };
        var parts = rangeStr.split(' - ');
        return {
            start: parts[0] || '',
            end: parts[1] || parts[0] || ''
        };
    }

    tables.puantaj = $('#tblPuantajSorgu').DataTable($.extend(true, {}, getDatatableOptions(), {
        ajax: {
            url: 'views/puantaj/api.php',
            data: function(d) {
                var range = getDatesFromRange($('input[name="date_range"]').val());
                d.action = 'get-puantaj-sorgu-datatable';
                d.start_date = range.start;
                d.end_date = range.end;
                d.ekip_kodu = $('select[name="ekip_kodu"]').val();
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false },
            { data: 'tarih' },
            { data: 'ekip_kodu' },
            { data: 'personel_adi' },
            { data: 'is_emri_tipi' },
            { data: 'is_emri_sonucu' },
            { data: 'ucret_durumu' },
            { data: 'sonuclanmis' },
            { data: 'acik_olanlar' },
            {
                data: 'id',
                render: function(data) {
                    return '<button class="btn btn-outline-danger btn-sm delete-sorgu" data-table="puantaj" data-id="' + data + '"><i class="bx bx-trash"></i></button>';
                },
                orderable: false
            }
        ],
        order: [[1, 'desc']]
    }));

    tables.endeks = $('#tblEndeksSorgu').DataTable($.extend(true, {}, getDatatableOptions(), {
        ajax: {
            url: 'views/puantaj/api.php',
            data: function(d) {
                var range = getDatesFromRange($('input[name="date_range"]').val());
                d.action = 'get-endeks-sorgu-datatable';
                d.start_date = range.start;
                d.end_date = range.end;
                d.ekip_kodu = $('select[name="ekip_kodu"]').val();
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false },
            { data: 'tarih' },
            { data: 'defter' },
            { data: 'bolge' },
            { data: 'ekip_kodu_adi' },
            { data: 'personel_adi' },
            { data: 'okunan_abone_sayisi' },
            { data: 'sayac_durum' },
            {
                data: 'id',
                render: function(data) {
                    return '<button class="btn btn-outline-danger btn-sm delete-sorgu" data-table="endeks" data-id="' + data + '"><i class="bx bx-trash"></i></button>';
                },
                orderable: false
            }
        ],
        order: [[1, 'desc']]
    }));

    tables.sayac = $('#tblSayacSorgu').DataTable($.extend(true, {}, getDatatableOptions(), {
        ajax: {
            url: 'views/puantaj/api.php',
            data: function(d) {
                var range = getDatesFromRange($('input[name="date_range"]').val());
                d.action = 'get-sayac-sorgu-datatable';
                d.start_date = range.start;
                d.end_date = range.end;
                d.ekip_kodu = $('select[name="ekip_kodu"]').val();
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false },
            { data: 'kayit_tarihi' },
            { data: 'ekip_kodu_adi' },
            { data: 'personel_adi' },
            { data: 'bolge' },
            { data: 'isemri_sebep' },
            { data: 'isemri_sonucu' },
            { data: 'abone_no' },
            { data: 'takilan_sayacno' },
            {
                data: 'id',
                render: function(data) {
                    return '<button class="btn btn-outline-danger btn-sm delete-sorgu" data-table="sayac" data-id="' + data + '"><i class="bx bx-trash"></i></button>';
                },
                orderable: false
            }
        ],
        order: [[1, 'desc']]
    }));

    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
        toggleBulkDeleteButton(getActiveTabType());
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        Object.values(tables).forEach(t => t.ajax.reload());
    });

    $('#btnTriggerOnlineSorgu').on('click', function() {
        var type = getActiveTabType();
        if (type === 'puantaj') $('#modalOnlinePuantaj').modal('show');
        else if (type === 'endeks') $('#modalOnlineEndeks').modal('show');
        else if (type === 'sayac') $('#modalOnlineSayac').modal('show');
    });

    $('#btnTriggerExcel').on('click', function() {
        var type = getActiveTabType();
        var range = getDatesFromRange($('input[name="date_range"]').val());
        var catMap = { 'puantaj':'KESME_ACMA', 'endeks':'ENDEKS_OKUMA', 'sayac':'SAYAC_DEGISIM' };
        var params = $.param({
            action: 'export-excel-sorgu-generic',
            category: catMap[type],
            start_date: range.start,
            end_date: range.end,
            ekip_kodu: $('select[name="ekip_kodu"]').val()
        });
        window.location.href = 'views/puantaj/api.php?' + params;
    });

    $('.online-sorgu-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var type = form.data('type');
        var range = getDatesFromRange(form.find('input[name="modal_date_range"]').val());
        var btn = form.find('.btn-sorgula');
        var originalText = btn.text();
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sorgulanıyor...');

        var actionMap = { 'KESME_ACMA': 'online-sorgu-kesme-acma', 'ENDEKS_OKUMA': 'online-endeks-sorgula', 'SAYAC_DEGISIM': 'online-sayac-sorgula' };

        var data = {
            action: actionMap[type],
            baslangic_tarihi: range.start,
            bitis_tarihi: range.end
        };

        $.post('views/puantaj/api.php', data, function(res) {
            btn.prop('disabled', false).text(originalText);
            if (res.status === 'success') {
                Swal.fire({ icon:'success', title:'Başarılı', text:res.message, timer:2000, showConfirmButton:false });
                form.closest('.modal').modal('hide');
                tables[getActiveTabType()].ajax.reload();
            } else { Swal.fire('Hata', res.message, 'error'); }
        }, 'json');
    });

    $('.bulk-delete-main').on('click', function() {
        var tableType = getActiveTabType();
        var tableObj = tables[tableType];
        var ids = [];
        $(tableObj.table().container()).find('.row-check:checked').each(function() { ids.push($(this).val()); });

        if (ids.length === 0) return;

        Swal.fire({
            title: ids.length + ' adet kaydı silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/puantaj/api.php', { action: 'sorgu-sil-toplu-generic', ids: ids, type: tableType }, function(res) {
                    if (res.status === 'success') {
                        tableObj.ajax.reload(null, false);
                        $(tableObj.table().header()).find('.check-all').prop('checked', false);
                        toggleBulkDeleteButton(tableType);
                        Swal.fire('Silindi!', '', 'success');
                    }
                }, 'json');
            }
        });
    });

    $(document).on('click', '.delete-sorgu', function() {
        var id = $(this).data('id');
        var tableType = $(this).data('table');
        Swal.fire({ title: 'Silmek istediğinize emin misiniz?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Evet, sil', cancelButtonText: 'İptal' }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/puantaj/api.php', { action: 'sorgu-sil-generic', id: id, type: tableType }, function(res) {
                    if (res.status === 'success') { tables[tableType].ajax.reload(null, false); Swal.fire('Silindi!', '', 'success'); }
                }, 'json');
            }
        });
    });

    $(document).on('click', '.check-all', function() {
        $(this).closest('table').find('.row-check').prop('checked', this.checked);
        toggleBulkDeleteButton(getActiveTabType());
    });

    $(document).on('change', '.row-check', function() {
        var table = $(this).closest('table');
        if (!this.checked) table.find('.check-all').prop('checked', false);
        toggleBulkDeleteButton(getActiveTabType());
    });

    function getActiveTabType() {
        var activeTab = $('.nav-link.active').attr('href');
        return activeTab.replace('#tab-', '');
    }

    function toggleBulkDeleteButton(type) {
        var tableId = '#tbl' + type.charAt(0).toUpperCase() + type.slice(1) + 'Sorgu';
        var count = $(tableId + ' .row-check:checked').length;
        if (count > 0) $('.bulk-delete-main').fadeIn();
        else $('.bulk-delete-main').fadeOut();
    }
});
</script>
<style>
.nav-tabs-custom .nav-link { border: none; border-bottom: 2px solid transparent; font-weight: 600; color: #6c757d; padding: 1rem 1.5rem; transition: all 0.3s; }
.nav-tabs-custom .nav-link.active { color: var(--bs-primary); border-bottom-color: var(--bs-primary); background-color: transparent; }
.nav-tabs-custom .nav-link:hover { color: var(--bs-primary); }
.uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
.flatpickr-input { background-color: #fff !important; }
</style>
