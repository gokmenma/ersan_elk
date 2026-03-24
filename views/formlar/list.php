<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\PersonelModel;

// Personel listesini al
$Personel = new PersonelModel();
$personeller = $Personel->all(false, 'personel'); 

?>
<div class="container-fluid">
    <?php
    $maintitle = "İnsan Kaynakları";
    $title = "Formlar ve Tutanaklar";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Form ve Tutanak Şablonları</h4>
                    <button type="button" class="btn btn-success btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm" data-bs-toggle="modal" data-bs-target="#yeniFormModal">
                        <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Şablon Yükle
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="formlarTable" class="table table-hover table-bordered nowrap w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:5%">ID</th>
                                    <th style="width:35%">Form/Tutanak Adı</th>
                                    <th style="width:25%">Dosya Adı</th>
                                    <th style="width:15%">Ekleyen</th>
                                    <th style="width:10%">Tarih</th>
                                    <th style="width:10%" class="text-center">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Form Ekleme Modalı -->
<div class="modal fade" id="yeniFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Şablon Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEkleForm">
                    <div class="mb-3">
                        <label for="baslik" class="form-label">Başlık (Örn: Teslim Tutanağı)</label>
                        <input type="text" class="form-control" id="baslik" name="baslik" required>
                    </div>
                    <div class="mb-3">
                        <label for="dosya" class="form-label">Şablon Dosyası (.doc, .docx, .pdf, .xls, .xlsx)</label>
                        <input type="file" class="form-control" id="dosya" name="dosya" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="btnKaydet">Yükle</button>
            </div>
        </div>
    </div>
</div>

<!-- Şablon İndirme Personel Seçim Modalı -->
<div class="modal fade" id="personelSecModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Personel Seçin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>İndirilecek şablona otomatik yazılacak personeli seçiniz.</p>
                <input type="hidden" id="indirmeFormId" value="">
                <div class="mb-3">
                    <label class="form-label">Personel</label>
                    <select id="indirmePersonelId" class="form-select select2" style="width:100%;">
                        <option value="">Seçiniz...</option>
                        <?php foreach($personeller as $p): ?>
                            <option value="<?php echo Security::encrypt($p->id); ?>">
                                <?php echo htmlspecialchars($p->adi_soyadi); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" id="btnSablounuIndir">
                    <i class="bx bx-download"></i> İndir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS -->
<style>
.select2-container .select2-selection--single { height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
</style>

<!-- JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    if ($.fn.select2) {
        $('#indirmePersonelId').select2({
            dropdownParent: $('#personelSecModal')
        });
    }

    var opts = {
        ajax: {
            url: 'api/formlar/islem.php',
            type: 'POST',
            data: { action: 'list' }
        },
        columns: [
            { data: 'id', render: function(data, type, row, meta) { return meta.row + 1; } },
            { data: 'baslik', render: function(data, type, row) { 
                return '<h5 class="font-size-14 mb-1">'+data+'</h5><p class="text-muted mb-0"><small><i class="bx bx-file mx-1"></i> '+row.dosya_adi+'</small></p>';
            }},
            { data: 'dosya_adi', visible: false },
            { data: 'ekleyen_adi' },
            { data: 'eklenme_tarihi' },
            { data: 'id', orderable: false, render: function(data, type, row) {
                var ext = row.dosya_adi.split('.').pop().toLowerCase();
                var indirBtn = '';
                
                // Eğer excel/word ise personel seçerek indirilebilir
                if(['doc','docx','xls','xlsx'].includes(ext)) {
                    indirBtn = '<button class="btn btn-sm btn-info btn-personelli-indir" data-id="'+data+'" title="Personel Bilgisiyle İndir"><i class="bx bx-user-pin"></i> Doldur ve İndir</button> ';
                } else {
                    indirBtn = '<a href="'+row.dosya_yolu+'" target="_blank" class="btn btn-sm btn-info" title="İndir"><i class="bx bx-download"></i> İndir</a> ';
                }
                
                return indirBtn + '<button class="btn btn-sm btn-danger btn-sil" data-id="'+data+'" title="Sil"><i class="bx bx-trash"></i></button>';
            }}
        ],
        order: [[0, 'asc']]
    };

    if (typeof getDatatableOptions === 'function') {
        opts = $.extend(true, {}, getDatatableOptions(), opts);
    } else {
        // Fallback initComplete fallback if getDatatableOptions is not available
        opts.initComplete = function(settings, json) {
            var api = this.api();
            var tableId = settings.sTableId;
            $("#" + tableId + " thead").append('<tr class="search-input-row"></tr>');
            api.columns().every(function () {
                let column = this;
                let title = column.header().textContent;
                if (title != "İşlemler" && title != "İşlem" && title.trim() !== "") {
                    let input = document.createElement("input");
                    input.placeholder = title;
                    input.classList.add("form-control", "form-control-sm");
                    let th = $('<th class="search">').append(input);
                    $("#" + tableId + " .search-input-row").append(th);
                    let timeout;
                    $(input).on("input change", function () {
                        let val = $(this).val();
                        clearTimeout(timeout);
                        timeout = setTimeout(() => { column.search(val).draw(); }, 300);
                    });
                } else {
                    $("#" + tableId + " .search-input-row").append("<th></th>");
                }
            });
        };
    }

    var table = $('#formlarTable').DataTable(opts);

    $('#btnKaydet').click(function() {
        var formData = new FormData($('#formEkleForm')[0]);
        formData.append('action', 'ekle');
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> Bekleyin...');
        
        $.ajax({
            url: 'api/formlar/islem.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                $('#yeniFormModal').modal('hide');
                $('#formEkleForm')[0].reset();
                table.ajax.reload();
                Swal.fire({ icon: 'success', title: 'Başarılı', text: res.message, timer: 2000, showConfirmButton: false });
            },
            error: function(xhr) {
                var res = xhr.responseJSON;
                Swal.fire({ icon: 'error', title: 'Hata', text: res ? res.message : 'Bir hata oluştu' });
            },
            complete: function() {
                $btn.prop('disabled', false).html('Yükle');
            }
        });
    });

    $(document).on('click', '.btn-sil', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu şablon tamamen silinecektir!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, Sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('api/formlar/islem.php', { action: 'sil', id: id }, function(res) {
                    Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, timer: 2000, showConfirmButton: false });
                    table.ajax.reload();
                }).fail(function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Hata', text: xhr.responseJSON ? xhr.responseJSON.message : 'Silinemedi' });
                });
            }
        });
    });
    
    $(document).on('click', '.btn-personelli-indir', function() {
        var id = $(this).data('id');
        $('#indirmeFormId').val(id);
        
        if ($.fn.select2) {
             $('#indirmePersonelId').val(null).trigger('change');
        } else {
             $('#indirmePersonelId').val('');
        }
        
        $('#personelSecModal').modal('show');
    });

    $('#btnSablounuIndir').click(function() {
        var formId = $('#indirmeFormId').val();
        var personelId = $('#indirmePersonelId').val();
        
        if(!personelId) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen bir personel seçin.' });
            return;
        }
        
        var url = 'api/formlar/indir.php?id=' + encodeURIComponent(formId) + '&personel_id=' + encodeURIComponent(personelId);
        
        // Modal kapatılsın, indirme başlatılsın
        $('#personelSecModal').modal('hide');
        window.open(url, '_blank');
    });
});
</script>
