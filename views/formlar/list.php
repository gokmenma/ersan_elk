<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\PersonelModel;
use App\Model\AracModel;

// Personel listesini al
$Personel = new PersonelModel();
$personeller = $Personel->all(false, 'personel'); 

// Araç listesini al
$Araclar = new AracModel();
$araclar = $Araclar->getAktifAraclar(); 


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
                    <div class="d-flex align-items-center">
                      
                        <button type="button" class="btn btn-success btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm me-1" data-bs-toggle="modal" data-bs-target="#yeniFormModal">
                            <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Şablon Yükle
                        </button>
                          <button type="button" class="btn btn-info btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm me-2 text-white" data-bs-toggle="modal" data-bs-target="#degiskenlerModal">
                            <i class="bx bx-info-circle fs-5"></i> 
                        </button>
                    </div>
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

<!-- Değişkenler Modalı -->
<div class="modal fade" id="degiskenlerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-info-circle text-info"></i> Şablon Değişkenleri Rehberi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-0">
                <div class="alert alert-warning mb-4">
                    <strong>Önemli Kullanım Kuralı:</strong> Word (docx) veya Excel belgelerinin içerisinde sistemin ilgili yerleri doldurabilmesi için aşağıdaki değişkenleri tam olarak yazdığı gibi şablonunuza eklemelisiniz. <br><br>
                    <strong>Word Şablonları İçin Kural:</strong> Word sisteminin algılayabilmesi için etiketlerde dolar (`$`) işaretini ve süslü parantezleri kesinlikle kullanın (Örn: <code>${PERSONEL_ADI}</code>).
                </div>
                
                <div class="row">
                    <!-- Personel Etiketleri -->
                    <div class="col-md-6 mb-4">
                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bx bx-user"></i> Personel Etiketleri</h6>
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="bg-light">
                                <tr><th style="width:50%">Ne Yazmalıyım?</th><th>Sistem Ne Getirecek?</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>${PERSONEL_ADI}</code></td><td>Personelin tam adı ve soyadı</td></tr>
                                <tr><td><code>${TC_KIMLIK}</code></td><td>T.C. Kimlik numarası</td></tr>
                                <tr><td><code>${TELEFON}</code></td><td>Cep telefonu numarası</td></tr>
                                <tr><td><code>${UNVAN}</code></td><td>Mesleği veya ünvanı (Görevi)</td></tr>
                                <tr><td><code>${ISE_GIRIS}</code></td><td>İşe giriş tarihi</td></tr>
                                <tr><td><code>${ADRES}</code></td><td>Personelin kayıtlı ev adresi</td></tr>
                                <tr><td><code>${TARIH}</code></td><td>Bugünün tarihi</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Araç Etiketleri -->
                    <div class="col-md-6 mb-4">
                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bx bx-car"></i> Araç Etiketleri</h6>
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="bg-light">
                                <tr><th style="width:50%">Ne Yazmalıyım?</th><th>Sistem Ne Getirecek?</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>${PLAKA}</code></td><td>Aracın plakası</td></tr>
                                <tr><td><code>${MARKA}</code></td><td>Aracın markası</td></tr>
                                <tr><td><code>${MODEL}</code></td><td>Aracın modeli</td></tr>
                                <tr><td><code>${BASLANGIC_KM}</code></td><td>Yazdığınız başlangıç KM'si</td></tr>
                                <tr><td><code>${BITIS_KM}</code></td><td>Yazdığınız bitiş KM'si</td></tr>
                                <tr><td><code>${ACIKLAMA}</code></td><td>Formdaki detaylı açıklamanız</td></tr>
                                <tr><td><code>${ARAC_BAKIM_KM}</code></td><td>Sistemdeki güncel KM'si</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Zimmet Etiketleri -->
                    <div class="col-md-6 mb-4">
                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bx bx-briefcase"></i> Zimmet Etiketleri</h6>
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="bg-light">
                                <tr><th style="width:50%">Ne Yazmalıyım?</th><th>Sistem Ne Getirecek?</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>${IMEI}</code></td><td>Tutanağa yazdığınız IMEI no</td></tr>
                                <tr><td><code>${SERI_NO}</code></td><td>Tutanağa yazdığınız Seri No</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- İzin Etiketleri -->
                    <div class="col-md-6 mb-4">
                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="bx bx-calendar"></i> İzin Etiketleri</h6>
                        <table class="table table-sm table-bordered table-striped">
                            <thead class="bg-light">
                                <tr><th style="width:50%">Ne Yazmalıyım?</th><th>Sistem Ne Getirecek?</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>${IZIN_BASLANGIC}</code></td><td>İzin başlangıç tarihi</td></tr>
                                <tr><td><code>${IZIN_BITIS}</code></td><td>İzin bitiş tarihi</td></tr>
                                <tr><td><code>${IZIN_ISE_BASLAMA}</code></td><td>İşe başlama tarihi</td></tr>
                                <tr><td><code>${IZIN_GUN}</code></td><td>İzin süresi (Gün sayısı)</td></tr>
                                <tr><td><code>${IZIN_NEDENI}</code></td><td>Forma yazılan talep nedeni</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light mt-0 pt-2 pb-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anladım, Kapat</button>
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

<!-- Yeni Şablon Doldurma Seçim Modalı -->
<div class="modal fade" id="personelSecModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-pencil text-info"></i> Şablonu Doldurarak İndir</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-0">
                <input type="hidden" id="indirmeFormId" value="">
                
                <p class="text-muted mb-3 small">
                    <i class="bx bx-info-circle text-primary"></i> Belgenizi indirirken içine eklenecek bilgileri ilgili sekmelerden doldurabilirsiniz. Gerekmeyen alanları boş bırakabilirsiniz.
                </p>

                <ul class="nav nav-tabs nav-tabs-custom mb-4" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active tab-manuel fw-medium" data-target="#tab-personel" style="background:transparent; border:none; border-bottom: 2px solid transparent;"><i class="bx bx-user me-1"></i> Personel</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel fw-medium" data-target="#tab-arac" style="background:transparent; border:none; border-bottom: 2px solid transparent;"><i class="bx bx-car me-1"></i> Araç</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel fw-medium" data-target="#tab-zimmet" style="background:transparent; border:none; border-bottom: 2px solid transparent;"><i class="bx bx-briefcase me-1"></i> Zimmet</button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link tab-manuel fw-medium" data-target="#tab-izin" style="background:transparent; border:none; border-bottom: 2px solid transparent;"><i class="bx bx-calendar me-1"></i> İzin</button>
                    </li>
                </ul>

                <style>
                    /* Custom tab styling for manual tabs */
                    .nav-tabs-custom .tab-manuel.active {
                        color: #fff !important;
                        border-bottom-color: #556ee6 !important;
                    }
                </style>

                <div class="tab-content">
                    <!-- Personel Sekmesi -->
                    <div class="tab-pane active" id="tab-personel">
                        <div class="mb-3">
                            <?php
                            $personelOptions = ['' => 'Personel Seçiniz (İsteğe Bağlı)'];
                            foreach($personeller as $p) {
                                $personelOptions[\App\Helper\Security::encrypt($p->id)] = $p->adi_soyadi;
                            }
                            echo \App\Helper\Form::FormSelect2(
                                'indirmePersonelId',
                                $personelOptions,
                                '',
                                'Personel Seçimi',
                                'bx bx-user'
                            );
                            ?>
                        </div>
                    </div>

                    <!-- Araç Sekmesi -->
                    <div class="tab-pane" id="tab-arac">
                        <?php
                        $aracOptions = ['' => 'Araç Seçiniz (İsteğe Bağlı)'];
                        foreach($araclar as $a) {
                            $aracOptions[\App\Helper\Security::encrypt($a->id)] = $a->plaka . ' - ' . $a->marka . ' ' . $a->model;
                        }
                        echo \App\Helper\Form::FormSelect2(
                            'indirmeAracId',
                            $aracOptions,
                            '',
                            'Araç Seçimi',
                            'bx bx-car'
                        );
                        ?>
                        <div class="row mt-3">
                            <div class="col-6 mb-3">
                                <?php echo \App\Helper\Form::FormFloatInput('number', 'indirmeAracBasKm', '', 'Örn: 154000', 'Başlangıç KM', 'bx bx-tachometer'); ?>
                            </div>
                            <div class="col-6 mb-3">
                                <?php echo \App\Helper\Form::FormFloatInput('number', 'indirmeAracBitKm', '', 'Örn: 155000', 'Bitiş KM', 'bx bx-tachometer'); ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <?php echo \App\Helper\Form::FormFloatTextarea('indirmeAracAciklama', '', 'Sadece ihtiyaç varsa doldurun', 'Açıklama / Sefer Bilgisi', 'bx bx-detail', 'form-control', false, '80px', 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Zimmet Sekmesi -->
                    <div class="tab-pane" id="tab-zimmet">
                        <div class="row">
                            <div class="col-12 mb-4 mt-2">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'indirmeZimmetImei', '', 'Örn: 123456789012345', 'IMEI Numarası (Varsa)', 'bx bx-barcode'); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'indirmeZimmetSeriNo', '', 'Örn: SN-987654', 'Seri Numarası (Varsa)', 'bx bx-hash'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- İzin Sekmesi -->
                    <div class="tab-pane" id="tab-izin">
                        <div class="row">
                            <div class="col-6 mb-4 mt-3">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'indirmeIzinBaslangic', '', '', 'Başlangıç Tarihi', 'bx bx-calendar', 'form-control flatpickr-date'); ?>
                            </div>
                            <div class="col-6 mb-4 mt-3">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'indirmeIzinBitis', '', '', 'Bitiş Tarihi', 'bx bx-calendar', 'form-control flatpickr-date'); ?>
                            </div>
                            <div class="col-12 mb-4">
                                <?php echo \App\Helper\Form::FormFloatInput('number', 'indirmeIzinGun', '', 'Örn: 5', 'İzin Gün Sayısı', 'bx bx-time'); ?>
                            </div>
                            <div class="col-12 mb-2">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'indirmeIzinNedeni', '', 'Örn: Yıllık İzin', 'İzin Nedeni', 'bx bx-info-circle'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light pt-3">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-info px-4 shadow-sm" id="btnSablounuIndir">
                    <i class="bx bx-download me-1"></i> Şablonu İndir
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
        $('#indirmeAracId').select2({
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
                var iconHtml = '<a href="' + row.dosya_yolu + '" target="_blank" class="btn btn-sm btn-soft-secondary me-3 shadow-none d-flex align-items-center justify-content-center" title="Boş Şablonu İndir" style="width: 36px; height: 36px; border-radius: 8px; font-size: 1.3rem; background: #eef2f7;"><i class="bx bx-download text-secondary"></i></a>';
                var textHtml = '<div><h5 class="font-size-14 mb-1">'+data+'</h5><p class="text-muted mb-0" style="font-size: 11px;"><i class="bx bx-file"></i> '+row.dosya_adi+'</small></p></div>';
                return '<div class="d-flex align-items-center">' + iconHtml + textHtml + '</div>';
            }},
            { data: 'dosya_adi', visible: false },
            { data: 'ekleyen_adi' },
            { data: 'eklenme_tarihi' },
            { data: 'id', orderable: false, render: function(data, type, row) {
                var ext = row.dosya_adi.split('.').pop().toLowerCase();
                var btn = '';
                
                // Eğer excel/word ise personel seçerek doldurma butonu kalacak
                if(['docx','xls','xlsx'].includes(ext)) {
                    btn = '<button class="btn btn-sm btn-info btn-personelli-indir me-1 shadow-sm" data-id="'+data+'" title="Belgeyi Özel Bilgilerle Doldur"><i class="bx bx-pencil me-1"></i> Doldur</button>';
                } else if(ext === 'doc') {
                    btn = '<button class="btn btn-sm btn-warning me-1 shadow-sm" onclick="Swal.fire({icon:\'warning\', title:\'Geçersiz Format\', text:\'Eski .doc uzantılı dosyalar otomatik şablon doldurmayı desteklemez. Lütfen dosyayı bilgisayarınızda Farklı Kaydet diyerek .docx formatına çevirip sisteme yeniden yükleyin.\'})" title="Sistem bu formatı dolduramaz"><i class="bx bx-error-circle me-1"></i> Desteklenmiyor</button>';
                }
                
                return btn + '<button class="btn btn-sm btn-danger btn-sil shadow-sm" data-id="'+data+'" title="Şablonu Sil"><i class="bx bx-trash"></i></button>';
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

    $('.tab-manuel').click(function(e) {
        e.preventDefault();
        var target = $(this).data('target');
        $(this).closest('ul').find('.nav-link').removeClass('active');
        $(this).addClass('active');
        $(target).closest('.tab-content').find('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });    

    if ($.fn.flatpickr) {
        $('.flatpickr-date').flatpickr({
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y"
        });
    }

    // Şablon değişkenlerini kopyalama özelliği
    $('#degiskenlerModal table tbody tr td:first-child code').each(function() {
        var tag = $(this).text();
        $(this).css('cursor', 'copy').attr('title', 'Kopyalamak için tıklayın').addClass('copy-tag-text');
        $(this).after(' <i class="bx bx-copy text-primary ms-1 cursor-pointer copy-tag-icon" title="Kopyalamak için tıklayın" data-tag="'+tag+'" style="font-size: 1.1em; vertical-align: middle;"></i>');
    });

    $(document).on('click', '.copy-tag-text, .copy-tag-icon', function() {
        var tag = $(this).hasClass('copy-tag-icon') ? $(this).data('tag') : $(this).text();
        
        var temp = $("<input>");
        $("body").append(temp);
        temp.val(tag).select();
        document.execCommand("copy");
        temp.remove();
        
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: "Kopyalandı: " + tag,
                duration: 2500,
                close: true,
                gravity: "top",
                position: "center",
                style: {
                    background: "#000",
                    color: "#fff",
                    borderRadius: "6px"
                }
            }).showToast();
        } else {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '<b style="font-family:monospace; color:#556ee6;">'+tag+'</b><br>panoya kopyalandı!',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });

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
             $('#indirmePersonelId, #indirmeAracId').val(null).trigger('change');
        } else {
             $('#indirmePersonelId, #indirmeAracId').val('');
        }
        $('#indirmeAracBasKm, #indirmeAracBitKm, #indirmeAracAciklama').val('');
        
        $('#personelSecModal').modal('show');
    });

    $('#btnSablounuIndir').click(function() {
        var formId = $('#indirmeFormId').val();
        var url = 'api/formlar/indir.php?id=' + encodeURIComponent(formId);
        
        var personelId = $('#indirmePersonelId').val();
        var aracId = $('#indirmeAracId').val();
        var imei = $('#indirmeZimmetImei').val();
        var seriNo = $('#indirmeZimmetSeriNo').val();
        var izinBaslangic = $('#indirmeIzinBaslangic').val();
        var izinBitis = $('#indirmeIzinBitis').val();
        var izinGun = $('#indirmeIzinGun').val();
        var izinNedeni = $('#indirmeIzinNedeni').val();
        
        if (!personelId && !aracId && !imei && !seriNo && !izinBaslangic && !izinBitis && !izinGun && !izinNedeni) {
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen indirme için en az bir bilgi doldurunuz.' });
            return;
        }

        // İzin bilgileri dolu ancak personel seçilmemişse engelle
        if ((izinBaslangic || izinBitis || izinGun || izinNedeni) && !personelId) {
            Swal.fire({ icon: 'warning', title: 'Personel Seçimi', text: 'İzin bilgisi girebilmek için lütfen Personel sekmesinden formu dolduran personeli seçiniz.' });
            return;
        }
        
        if (personelId) {
            url += '&personel_id=' + encodeURIComponent(personelId);
        }
        
        if (aracId) {
            url += '&arac_id=' + encodeURIComponent(aracId);
            url += '&bas_km=' + encodeURIComponent($('#indirmeAracBasKm').val() || '');
            url += '&bit_km=' + encodeURIComponent($('#indirmeAracBitKm').val() || '');
            url += '&aciklama=' + encodeURIComponent($('#indirmeAracAciklama').val() || '');
        }

        if (imei) {
            url += '&imei=' + encodeURIComponent(imei);
        }
        
        if (seriNo) {
            url += '&seri_no=' + encodeURIComponent(seriNo);
        }

        if (izinBaslangic) url += '&izin_baslangic=' + encodeURIComponent(izinBaslangic);
        if (izinBitis) url += '&izin_bitis=' + encodeURIComponent(izinBitis);
        if (izinGun) url += '&izin_gun=' + encodeURIComponent(izinGun);
        if (izinNedeni) url += '&izin_nedeni=' + encodeURIComponent(izinNedeni);
        
        // Modal kapatılsın, indirme başlatılsın
        $('#personelSecModal').modal('hide');
        window.open(url, '_blank');
    });
});
</script>
