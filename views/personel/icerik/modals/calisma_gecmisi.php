<?php
use App\Helper\Form;
use App\Helper\Helper;
?>
<?php if ($id > 0): ?>
    <!-- Çalışma Bilgileri Geçmişi Ekle/Düzenle Modal -->
    <div class="modal fade" id="modalCalismaGecmisiEkle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3" style="width: 48px; height: 48px;">
                            <div id="modal_calisma_header_icon_box" class="avatar-title rounded-circle bg-soft-success text-success" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(52, 195, 143, 0.1); color: #34c38f;">
                                <i id="modal_calisma_header_icon" class="bx bx-plus-circle" style="font-size: 24px;"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0">Yeni Çalışma Dönemi Tanımla</h5>
                            <p id="modal_calisma_header_subtitle" class="text-muted mb-0 small">Değişiklikleri kaydetmek için formu doldurun.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formCalismaGecmisiEkle" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="calisma_gecmisi_action" value="calisma-gecmisi-ekle">
                    <input type="hidden" name="id" id="calisma_gecmisi_id">
                    <input type="hidden" name="personel_id" value="<?= $id ?>">
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "ise_giris_tarihi", date('d.m.Y'), "İşe Giriş Tarihi", "İşe Giriş Tarihi", "calendar", "form-control flatpickr", true); ?>
                            </div>
                            
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "isten_cikis_tarihi", "", "İşten Çıkış Tarihi (Opsiyonel)", "İşten Çıkış Tarihi", "calendar", "form-control flatpickr", false); ?>
                            </div>

                            <div class="col-md-4">
                                <?php echo Form::FormSelect2("personel_sinifi", ['Beyaz Yaka' => 'Beyaz Yaka', 'Mavi Yaka' => 'Mavi Yaka'], 'Beyaz Yaka', "Personel Sınıfı", "users", "key", "", "form-select select2", false, "width:100%", "", "modal_personel_sinifi"); ?>
                            </div>

                            <div class="col-md-4">
                                <?php echo Form::FormSelect2("saha_takibi", ['1' => 'Evet', '0' => 'Hayır'], '0', "Saha Takibi", "map-pin", "key", "", "form-select select2", false, "width:100%", "", "modal_saha_takibi"); ?>
                            </div>

                            <div class="col-md-4">
                                <?php echo Form::FormSelect2("arac_kullanim", ['Yok' => 'Yok', 'Kendi Aracı' => 'Kendi Aracı', 'Şirket aracı' => 'Şirket aracı'], 'Yok', "Araç Kullanım", "truck", "key", "", "form-select select2", false, "width:100%", "", "modal_arac_kullanim"); ?>
                            </div>

                            <div class="col-md-12">
                                <?php
                                if (!isset($FirmaModel)) {
                                    $FirmaModel = new \App\Model\FirmaModel();
                                }
                                $firma = $FirmaModel->find($_SESSION['firma_id']);
                                $firma_adi = $firma->firma_adi ?? 'Firma Bulunamadı';

                                $firma_option = [
                                    $firma_adi => $firma_adi,
                                    "İŞKUR" => "İŞKUR",
                                    "Dışarıdan Sigortalı" => "Dışarıdan Sigortalı"
                                ];

                                echo Form::FormSelect2("sgk_yapilan_firma", $firma_option, $firma_adi, "SGK Yapılan Firma", "book-open", "key", "", "form-select select2", false, "width:100%", "", "modal_sgk_yapilan_firma");
                                ?>
                            </div>
                            
                            <div class="col-md-12" id="modal_gorunum_modulleri_row" style="display:none;">
                                <?php
                                $modul_options = [
                                    'bordro' => 'Bordro',
                                    'personel' => 'Personel Listesi',
                                    'puantaj' => 'Puantaj',
                                    'nobet' => 'Nöbet',
                                    'demirbas' => 'Demirbaş',
                                    'arac' => 'Araç Takip',
                                    'evrak' => 'Evrak Takip',
                                    'mail' => 'Mail/SMS',
                                    'takip' => 'Personel Takip',
                                    'dashboard' => 'Dashboard'
                                ];
                                echo Form::FormMultipleSelect2("gorunum_modulleri", $modul_options, ['bordro', 'personel'], "Görüneceği Modüller", "eye", "key", "", "form-select select2", false, "modal_gorunum_modulleri");
                                ?>
                                <small class="text-muted"><i class="bx bx-info-circle"></i> Personelin hangi modüllerde görüneceğini seçebilirsiniz. <strong class="text-danger">Bordro ve Personel Listesi görünümü zorunludur.</strong></small>
                            </div>

                            <!-- İşten Ayrılış Bilgileri (Sadece Çıkış Tarihi girildiğinde açılır) -->
                            <div class="col-md-12 card border bg-light p-3" id="modal_ayrilis_bilgileri_container" style="display:none;">
                                <h6 class="fw-bold text-danger mb-3"><i class="bx bx-error-circle me-1"></i> İşten Ayrılış Bilgileri</h6>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-floating mb-3">
                                            <textarea class="form-control" name="isten_ayrilis_nedeni" id="modal_isten_ayrilis_nedeni" style="height: 100px;" placeholder="Ayrılış Nedeni"></textarea>
                                            <label for="modal_isten_ayrilis_nedeni">İşten Ayrılış Nedeni</label>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="flex-grow-1">
                                                <?php echo Form::FormFileInput("isten_ayrilis_belge_yolu", "İşten Ayrılış Belgesi", "file", "form-control", "modal_isten_ayrilis_belge"); ?>
                                            </div>
                                            <div id="modal_mevcut_belge_wrapper" style="display:none;">
                                                <a id="modal_btn_mevcut_belge" href="#" target="_blank" class="btn btn-outline-danger" title="Mevcut Belge" style="height: 58px; width: 58px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <i class="bx bx-download fs-4"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top-0 pt-0 pb-4 pe-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="button" class="btn btn-primary px-4 shadow-none" id="btnSaveCalismaGecmisi">
                            <i class="bx bx-save me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function ($) {
            "use strict";

            function formatDate(dateStr) {
                if (!dateStr) return '';
                var d = new Date(dateStr);
                var day = ("0" + d.getDate()).slice(-2);
                var month = ("0" + (d.getMonth() + 1)).slice(-2);
                var year = d.getFullYear();
                return day + "." + month + "." + year;
            }

            function refreshCalismaGecmisiTable() {
                var personelId = $('#formCalismaGecmisiEkle input[name="personel_id"]').val();

                // Sayfadaki readonly alanları güncelle
                refreshCalismaGosterimAlanlari(personelId);

                $.ajax({
                    url: 'views/personel/api.php',
                    type: 'POST',
                    data: { action: 'get-calisma-gecmisi', personel_id: personelId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            var $tbody = $('#tblCalismaGecmisi tbody');

                            if ($.fn.DataTable.isDataTable('#tblCalismaGecmisi')) {
                                $('#tblCalismaGecmisi').DataTable().destroy();
                            }

                            $tbody.empty();

                            if (response.data && response.data.length > 0) {
                                var bugun = new Date().toISOString().split('T')[0];

                                $.each(response.data, function (i, item) {
                                    var iseGirisTarihi = item.ise_giris_tarihi; // formatted
                                    var bitisTarihi = item.isten_cikis_tarihi ? item.isten_cikis_tarihi : null;
                                    
                                    // Parse for active state checking
                                    var partsGiris = iseGirisTarihi.split('.');
                                    var ymdGiris = partsGiris[2] + '-' + partsGiris[1] + '-' + partsGiris[0];
                                    
                                    var ymdCikis = null;
                                    if (bitisTarihi) {
                                        var partsCikis = bitisTarihi.split('.');
                                        ymdCikis = partsCikis[2] + '-' + partsCikis[1] + '-' + partsCikis[0];
                                    }

                                    var isAktif = (ymdGiris <= bugun && (ymdCikis === null || ymdCikis >= bugun));

                                    var statusBadge = isAktif
                                        ? '<span class="badge bg-success">Aktif</span>'
                                        : '<span class="badge bg-secondary">Pasif</span>';

                                    var bitisBadge = bitisTarihi
                                        ? bitisTarihi
                                        : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>';

                                    var classBadge = item.personel_sinifi === 'Beyaz Yaka'
                                        ? '<span class="badge bg-soft-info text-info"><i class="bx bx-user me-1"></i>Beyaz Yaka</span>'
                                        : '<span class="badge bg-soft-warning text-warning"><i class="bx bx-wrench me-1"></i>Mavi Yaka</span>';

                                    var trackingBadge = item.saha_takibi == 1
                                        ? '<span class="badge bg-soft-success text-success"><i class="bx bx-check-circle me-1"></i>Evet</span>'
                                        : '<span class="badge bg-soft-danger text-danger"><i class="bx bx-x-circle me-1"></i>Hayır</span>';

                                    var vehicleBadge = '<span class="badge bg-soft-primary text-primary"><i class="bx bx-car me-1"></i>' + item.arac_kullanim + '</span>';

                                    var documentLink = '';
                                    if (item.isten_ayrilis_belge_yolu) {
                                        documentLink = '<a href="' + item.isten_ayrilis_belge_yolu + '" target="_blank" class="btn btn-sm btn-soft-danger" title="Ayrılış Belgesi"><i class="bx bx-file"></i></a>';
                                    }

                                    var row = '<tr>' +
                                        '<td style="display:none">' + item.id + '</td>' +
                                        '<td><span class="fw-bold text-dark">' + item.sgk_yapilan_firma + '</span></td>' +
                                        '<td>' + iseGirisTarihi + '</td>' +
                                        '<td>' + bitisBadge + '</td>' +
                                        '<td>' + classBadge + '</td>' +
                                        '<td>' + trackingBadge + '</td>' +
                                        '<td>' + vehicleBadge + '</td>' +
                                        '<td>' + statusBadge + '</td>' +
                                        '<td class="text-center text-nowrap">' +
                                        documentLink + ' ' +
                                        '<button type="button" class="btn btn-sm btn-soft-primary btn-calisma-gecmisi-duzenle me-1" data-id="' + item.id + '" title="Düzenle">' +
                                        '<i class="bx bx-edit-alt"></i>' +
                                        '</button>' +
                                        '<button type="button" class="btn btn-sm btn-soft-danger btn-calisma-gecmisi-sil" data-id="' + item.id + '" title="Sil">' +
                                        '<i class="bx bx-trash"></i>' +
                                        '</button>' +
                                        '</td>' +
                                        '</tr>';
                                    $tbody.append(row);
                                });
                            }

                            if (typeof window.invalidateAllTabs === 'function') {
                                window.invalidateAllTabs();
                            }
                            initCalismaDataTable();
                        }
                    }
                });
            }

            function initCalismaDataTable() {
                if ($.fn.DataTable && $('#tblCalismaGecmisi').length) {
                    if ($.fn.DataTable.isDataTable('#tblCalismaGecmisi')) {
                        $('#tblCalismaGecmisi').DataTable().destroy();
                    }
                    var dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
                    $('#tblCalismaGecmisi').DataTable($.extend(true, {}, dtOptions, {
                        order: [[0, 'desc']],
                        pageLength: 5
                    }));
                }
            }

            function initCalismaGecmisi() {
                initCalismaDataTable();

                // Initialize Select2
                $('#modalCalismaGecmisiEkle .select2').each(function() {
                    $(this).select2({
                        dropdownParent: $('#modalCalismaGecmisiEkle'),
                        width: '100%',
                        allowClear: true
                    });
                });

                // Initialize Flatpickr
                $('#modalCalismaGecmisiEkle .flatpickr').flatpickr({
                    dateFormat: "d.m.Y",
                    locale: "tr",
                    allowInput: true
                });

                // Toggle visibility modules based on firm selection
                function toggleModalCalismaGorunum() {
                    var val = $('#modal_sgk_yapilan_firma').val();
                    if (val === 'Dışarıdan Sigortalı') {
                        $('#modal_gorunum_modulleri_row').show();
                    } else {
                        $('#modal_gorunum_modulleri_row').hide();
                    }
                }

                $('#modal_sgk_yapilan_firma').on('change', toggleModalCalismaGorunum);
                $(document).on('select2:select', '#modal_sgk_yapilan_firma', toggleModalCalismaGorunum);

                // Toggle resignation info based on end date
                function toggleAyrilisBilgileri() {
                    var cikisVal = $('#formCalismaGecmisiEkle input[name="isten_cikis_tarihi"]').val();
                    if (cikisVal && cikisVal.trim() !== '') {
                        $('#modal_ayrilis_bilgileri_container').show();
                    } else {
                        $('#modal_ayrilis_bilgileri_container').hide();
                        $('#modal_isten_ayrilis_nedeni').val('');
                        $('#modal_isten_ayrilis_belge').val('');
                    }
                }

                $('#formCalismaGecmisiEkle input[name="isten_cikis_tarihi"]').on('change keyup input', toggleAyrilisBilgileri);
                
                setInterval(toggleAyrilisBilgileri, 600);

                // Open modal for new entry
                $(document).off('click', '#btnOpenCalismaGecmisiModal').on('click', '#btnOpenCalismaGecmisiModal', function () {
                    $('#calisma_gecmisi_id').val('');
                    $('#calisma_gecmisi_action').val('calisma-gecmisi-ekle');
                    $('#modalCalismaGecmisiEkle .modal-title').text('Yeni Çalışma Dönemi Tanımla');
                    $('#modal_calisma_header_subtitle').text('Değişiklikleri kaydetmek için formu doldurun.');
                    $('#modal_calisma_header_icon').attr('class', 'bx bx-plus-circle');
                    $('#modal_calisma_header_icon_box').css({'background': 'rgba(52, 195, 143, 0.1)', 'color': '#34c38f'});
                    $('#formCalismaGecmisiEkle')[0].reset();
                    $('#modalCalismaGecmisiEkle .select2').val(null).trigger('change.select2');
                    
                    // Defaults
                    $('#modal_personel_sinifi').val('Beyaz Yaka').trigger('change.select2');
                    $('#modal_saha_takibi').val('0').trigger('change.select2');
                    $('#modal_arac_kullanim').val('Yok').trigger('change.select2');
                    $('#modal_sgk_yapilan_firma').val('<?= addslashes($firma_adi) ?>').trigger('change.select2');
                    
                    // Set default modules for Dışarıdan Sigortalı
                    $('#modal_gorunum_modulleri').val(['bordro', 'personel']).trigger('change.select2');
                    
                    // Clear document display
                    $('#modal_mevcut_belge_wrapper').hide();
                    $('#modal_btn_mevcut_belge').attr('href', '#');
                    
                    $('#modalCalismaGecmisiEkle').modal('show');
                    toggleModalCalismaGorunum();
                    toggleAyrilisBilgileri();
                });

                // Edit entry
                $(document).off('click', '.btn-calisma-gecmisi-duzenle').on('click', '.btn-calisma-gecmisi-duzenle', function () {
                    var id = $(this).data('id');
                    var $btn = $(this);
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i>');

                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: { action: 'calisma-gecmisi-get', id: id },
                        dataType: 'json',
                        success: function (response) {
                            $btn.prop('disabled', false).html(originalHtml);
                            if (response.status === 'success') {
                                var data = response.data;
                                $('#calisma_gecmisi_id').val(data.id);
                                $('#calisma_gecmisi_action').val('calisma-gecmisi-guncelle');
                                $('#modalCalismaGecmisiEkle .modal-title').text('Çalışma Dönemini Düzenle');
                                $('#modal_calisma_header_subtitle').text('Kayıt bilgilerini aşağıdan güncelleyebilirsiniz.');
                                $('#modal_calisma_header_icon').attr('class', 'bx bx-edit-alt');
                                $('#modal_calisma_header_icon_box').css({'background': 'rgba(80, 165, 241, 0.1)', 'color': '#50a5f1'});

                                $('#modal_personel_sinifi').val(data.personel_sinifi).trigger('change.select2');
                                $('#modal_saha_takibi').val(data.saha_takibi).trigger('change.select2');
                                $('#modal_arac_kullanim').val(data.arac_kullanim).trigger('change.select2');
                                $('#modal_sgk_yapilan_firma').val(data.sgk_yapilan_firma).trigger('change.select2');
                                
                                if (data.sgk_yapilan_firma === 'Dışarıdan Sigortalı' && data.gorunum_modulleri) {
                                    var mods = data.gorunum_modulleri.split(',');
                                    $('#modal_gorunum_modulleri').val(mods).trigger('change.select2');
                                } else {
                                    $('#modal_gorunum_modulleri').val(['bordro', 'personel']).trigger('change.select2');
                                }

                                // Date formatting
                                var $iseGiris = $('#formCalismaGecmisiEkle input[name="ise_giris_tarihi"]');
                                if ($iseGiris.length && $iseGiris[0]._flatpickr) {
                                    $iseGiris[0]._flatpickr.setDate(data.ise_giris_tarihi);
                                } else {
                                    $iseGiris.val(data.ise_giris_tarihi);
                                }

                                var $cikis = $('#formCalismaGecmisiEkle input[name="isten_cikis_tarihi"]');
                                if ($cikis.length && $cikis[0]._flatpickr) {
                                    if (data.isten_cikis_tarihi) {
                                        $cikis[0]._flatpickr.setDate(data.isten_cikis_tarihi);
                                    } else {
                                        $cikis[0]._flatpickr.clear();
                                    }
                                } else {
                                    $cikis.val(data.isten_cikis_tarihi || '');
                                }

                                // Resignation info
                                $('#modal_isten_ayrilis_nedeni').val(data.isten_ayrilis_nedeni || '');
                                if (data.isten_ayrilis_belge_yolu) {
                                    $('#modal_mevcut_belge_wrapper').show();
                                    $('#modal_btn_mevcut_belge').attr('href', data.isten_ayrilis_belge_yolu);
                                } else {
                                    $('#modal_mevcut_belge_wrapper').hide();
                                }

                                $('#modalCalismaGecmisiEkle').modal('show');
                                toggleModalCalismaGorunum();
                                toggleAyrilisBilgileri();
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        }
                    });
                });

                // Delete entry
                $(document).off('click', '.btn-calisma-gecmisi-sil').on('click', '.btn-calisma-gecmisi-sil', function () {
                    var id = $(this).data('id');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Bu çalışma dönemi kaydı kalıcı olarak silinecektir.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'İptal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'views/personel/api.php',
                                type: 'POST',
                                data: { action: 'calisma-gecmisi-sil', id: id },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'success') {
                                        Swal.fire('Başarılı', response.message, 'success').then(() => {
                                            refreshCalismaGecmisiTable();
                                        });
                                    } else {
                                        Swal.fire('Hata', response.message, 'error');
                                    }
                                }
                            });
                        }
                    });
                });

                // Save button
                $('#btnSaveCalismaGecmisi').on('click', function() {
                    $('#formCalismaGecmisiEkle').submit();
                });

                // Form submit (supports files!)
                $('#formCalismaGecmisiEkle').on('submit', function(e) {
                    e.preventDefault();
                    var $btn = $('#btnSaveCalismaGecmisi');
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Kaydediliyor...');

                    var formData = new FormData(this);

                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire('Başarılı', response.message, 'success').then(() => {
                                    $('#modalCalismaGecmisiEkle').modal('hide');
                                    refreshCalismaGecmisiTable();
                                });
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        },
                        complete: function() {
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    });
                });

                // Force required modules in select2
                setTimeout(function () {
                    var $gm = $('#modal_gorunum_modulleri');
                    if ($gm.length) {
                        $gm.on('select2:unselecting', function (e) {
                            if (e.params.args.data.id === 'bordro' || e.params.args.data.id === 'personel') {
                                e.preventDefault();
                                toastr.warning(e.params.args.data.text + ' modülünde görünüm zorunludur.', 'Uyarı');
                            }
                        });
                    }
                }, 500);
            }

            // Sync readonly profile view inputs
            window.refreshCalismaGosterimAlanlari = function(personelId) {
                $.ajax({
                    url: 'views/personel/api.php',
                    type: 'POST',
                    data: { action: 'get-calisma-gecmisi', personel_id: personelId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.data && response.data.length > 0) {
                            var bugun = new Date().toISOString().split('T')[0];
                            var aktif = null;
                            
                            // Find currently active working segment
                            $.each(response.data, function(i, item) {
                                var iseGiris = item.ise_giris_tarihi;
                                var partsGiris = iseGiris.split('.');
                                var ymdGiris = partsGiris[2] + '-' + partsGiris[1] + '-' + partsGiris[0];
                                
                                var ymdCikis = null;
                                if (item.isten_cikis_tarihi) {
                                    var partsCikis = item.isten_cikis_tarihi.split('.');
                                    ymdCikis = partsCikis[2] + '-' + partsCikis[1] + '-' + partsCikis[0];
                                }
                                
                                if (ymdGiris <= bugun && (ymdCikis === null || ymdCikis >= bugun)) {
                                    aktif = item;
                                    return false; // break
                                }
                            });
                            
                            // If no active segment, grab the most recent one
                            if (!aktif && response.data.length > 0) {
                                aktif = response.data[0];
                            }

                            if (aktif) {
                                // Update read-only displayed badges
                                $('#display_ise_giris_tarihi').text(aktif.ise_giris_tarihi);
                                $('#display_isten_cikis_tarihi').html(aktif.isten_cikis_tarihi ? aktif.isten_cikis_tarihi : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>');
                                $('#display_personel_sinifi').text(aktif.personel_sinifi);
                                $('#display_saha_takibi').html(aktif.saha_takibi == 1 ? '<span class="badge bg-soft-success text-success">Evet</span>' : '<span class="badge bg-soft-danger text-danger">Hayır</span>');
                                $('#display_arac_kullanim').text(aktif.arac_kullanim);
                                $('#display_sgk_yapilan_firma').text(aktif.sgk_yapilan_firma);

                                // Update hidden input fields for parent form submission
                                $('input[name="ise_giris_tarihi"]').val(aktif.ise_giris_tarihi);
                                $('input[name="isten_cikis_tarihi"]').val(aktif.isten_cikis_tarihi || '');
                                $('#personel_sinifi').val(aktif.personel_sinifi).trigger('change.select2');
                                $('#saha_takibi').val(aktif.saha_takibi).trigger('change.select2');
                                $('#arac_kullanim').val(aktif.arac_kullanim).trigger('change.select2');
                                $('#sgk_yapilan_firma').val(aktif.sgk_yapilan_firma).trigger('change.select2');

                                var $wrapper = $('#gorunum_modulleri_row');
                                var $gmMain = $('#gorunum_modulleri');
                                if ($wrapper.length) {
                                    if (aktif.sgk_yapilan_firma === 'Dışarıdan Sigortalı') {
                                        $wrapper.show();
                                        if ($gmMain.length && aktif.gorunum_modulleri) {
                                            $gmMain.val(aktif.gorunum_modulleri.split(',')).trigger('change.select2');
                                        }
                                    } else {
                                        $wrapper.hide();
                                    }
                                }

                                if (aktif.isten_cikis_tarihi) {
                                    $('#display_ayrilis_nedeni_wrapper').show();
                                    $('#display_isten_ayrilis_nedeni').text(aktif.isten_ayrilis_nedeni || 'Nedeni belirtilmedi');
                                    if (aktif.isten_ayrilis_belge_yolu) {
                                        $('#display_ayrilis_belge_wrapper').show();
                                        $('#display_btn_ayrilis_belge').attr('href', aktif.isten_ayrilis_belge_yolu);
                                    } else {
                                        $('#display_ayrilis_belge_wrapper').hide();
                                    }
                                } else {
                                    $('#display_ayrilis_nedeni_wrapper').hide();
                                    $('#display_ayrilis_belge_wrapper').hide();
                                }
                            }
                        }
                    }
                });
            }

            $(document).ready(function() {
                var waitSelect2 = setInterval(function() {
                    if (typeof $.fn.select2 !== 'undefined') {
                        clearInterval(waitSelect2);
                        initCalismaGecmisi();
                    }
                }, 100);
            });

        })(jQuery);
    </script>
<?php endif; ?>
