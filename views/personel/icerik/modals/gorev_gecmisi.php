<?php
use App\Helper\Form;
use App\Helper\Helper;
?>
<?php if ($id > 0): ?>
    <style>
        /* #modalGorevGecmisiEkle .form-floating-custom > .form-control,
        #modalGorevGecmisiEkle .form-floating-custom > .form-select {
            padding-left: 45px !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--multiple,
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--single {
            min-height: 58px !important;
            padding-top: 18px !important;
            border: 1px solid #ced4da !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 35px !important;
            line-height: 38px !important;
            color: #495057 !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding-left: 35px !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 56px !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-top: 14px !important;
            margin-right: 25px !important;
            z-index: 10;
        }
        #modalGorevGecmisiEkle .form-floating-custom .form-floating-icon {
            z-index: 5;
            top: 0;
            left: 0;
            width: 45px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        #modalGorevGecmisiEkle .form-floating-custom label {
            left: 45px !important;
            transform: scale(0.85) translateY(-0.75rem) translateX(0.15rem) !important;
            opacity: 0.65 !important;
        }
        #modalGorevGecmisiEkle .form-floating-custom select:placeholder-shown + label,
        #modalGorevGecmisiEkle .form-floating-custom select:empty + label {
             transform: none !important;
             opacity: 1 !important;
        }
        #modalGorevGecmisiEkle .select2-container {
            z-index: 1060 !important;
        } */
    </style>

    <!-- Görev Geçmişi Ekle Modal -->
    <div class="modal fade" id="modalGorevGecmisiEkle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <div class="d-flex align-items-center">
                        <div class="avatar-md me-3" style="width: 48px; height: 48px;">
                            <div id="modal_header_icon_box" class="avatar-title rounded-circle bg-soft-success text-success" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(52, 195, 143, 0.1); color: #34c38f;">
                                <i id="modal_header_icon" class="bx bx-plus-circle" style="font-size: 24px;"></i>
                            </div>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0">Yeni Maaş Tipi Tanımla</h5>
                            <p id="modal_header_subtitle" class="text-muted mb-0 small">Değişiklikleri kaydetmek için formu doldurun.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="formGorevGecmisiEkle" method="post">
                    <input type="hidden" name="action" id="gorev_gecmisi_action" value="gorev-gecmisi-ekle">
                    <input type="hidden" name="id" id="gorev_gecmisi_id">
                    <input type="hidden" name="personel_id" value="<?= $id ?>">
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <?php echo Form::FormMultipleSelect2("departman", Helper::DEPARTMAN, [], "Departman", "grid", "key", "", "form-select select2", false, "modal_departman"); ?>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-floating form-floating-custom">
                                    <select style="width:100%" class="form-select select2" id="modal_gorev" name="gorev"
                                        data-placeholder="Görev Seçiniz">
                                        <option value="">Görev Seçiniz</option>
                                    </select>
                                    <label for="modal_gorev">Görev / Unvan</label>
                                    <div class="form-floating-icon">
                                        <i data-feather="award"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <?php echo Form::FormSelect2("maas_durumu", Helper::MAAS_HESAPLAMA_TIPI, "Brüt", "Maaş Tipi", "dollar-sign", "key", "", "form-select select2", false, "width:100%", "", "modal_maas_durumu"); ?>
                            </div>

                            <div class="col-md-12">
                                <?php echo Form::FormFloatInput("text", "maas_tutari", "0", "Maaş Tutarı", "Maaş Tutarı", "dollar-sign", "form-control money"); ?>
                            </div>

                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "gorev_baslangic", date('d.m.Y'), "Başlangıç Tarihi", "Başlangıç Tarihi", "calendar", "form-control flatpickr", true); ?>
                            </div>
                            
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "gorev_bitis", "", "Bitiş Tarihi", "Bitiş Tarihi", "calendar", "form-control flatpickr", false); ?>
                            </div>

                            <div class="col-md-12">
                                <?php echo Form::FormFloatTextarea("aciklama", "", "Açıklama girebilirsiniz...", "Açıklama", "edit", "form-control", false, "100px", 3); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top-0 pt-0 pb-4 pe-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">İptal</button>
                        <button type="button" class="btn btn-primary px-4 shadow-none" id="btnSaveGorevGecmisi">
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

            function loadModalGorevOptions(selectedDepartmanlar, callback) {
                var $gorev = $("#modal_gorev");
                $gorev.find("option").not(":first").remove();

                if (!selectedDepartmanlar || selectedDepartmanlar.length === 0) {
                    $gorev.val("").trigger("change.select2");
                    if (callback) callback();
                    return;
                }

                var allOptions = [];
                var promises = [];

                selectedDepartmanlar.forEach(function (departman) {
                    var formData = new FormData();
                    formData.append("action", "unvan-ucretleri-getir");
                    formData.append("departman", departman);

                    var promise = fetch("views/tanimlamalar/api.php", {
                        method: "POST",
                        body: formData,
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success" && data.data) {
                            data.data.forEach(item => {
                                allOptions.push({ id: item.tur_adi, text: item.tur_adi, ucret: item.unvan_ucret });
                            });
                        }
                    });
                    promises.push(promise);
                });

                Promise.all(promises).then(function () {
                    var addedValues = [];
                    allOptions.forEach(opt => {
                        if (addedValues.indexOf(opt.id) === -1) {
                            var option = new Option(opt.text, opt.id, false, false);
                            $(option).attr("data-ucret", opt.ucret);
                            $(option).data("ucret", opt.ucret);
                            $gorev.append(option);
                            addedValues.push(opt.id);
                        }
                    });
                    $gorev.trigger("change.select2");
                    if (callback) callback();
                });
            }

            function refreshGorevGecmisiTable() {
                var personelId = $('#formGorevGecmisiEkle input[name="personel_id"]').val();

                $.ajax({
                    url: 'views/personel/api.php',
                    type: 'POST',
                    data: { action: 'get-gorev-gecmisi', personel_id: personelId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            var $tbody = $('#tblGorevGecmisi tbody');

                            if ($.fn.DataTable.isDataTable('#tblGorevGecmisi')) {
                                $('#tblGorevGecmisi').DataTable().destroy();
                            }

                            $tbody.empty();

                            if (response.data && response.data.length > 0) {
                                var bugun = new Date().toISOString().split('T')[0];

                                $.each(response.data, function (i, item) {
                                    var bitisTarihi = item.bitis_tarihi ? item.bitis_tarihi : null;
                                    var isAktif = (item.baslangic_tarihi <= bugun && (bitisTarihi === null || bitisTarihi >= bugun));

                                    var statusBadge = isAktif
                                        ? '<span class="badge bg-success">Aktif</span>'
                                        : '<span class="badge bg-secondary">Pasif</span>';

                                    var bitisBadge = bitisTarihi
                                        ? formatDate(bitisTarihi)
                                        : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>';

                                    var maasTutariFormatted = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(item.maas_tutari);

                                    var row = '<tr>' +
                                        '<td>' +
                                        '<div class="d-flex flex-column">' +
                                        '<span class="fw-bold text-dark">' + (item.gorev ? item.gorev : 'Belirtilmemiş') + '</span>' +
                                        '<small class="text-muted">' + (item.departman ? item.departman : '') + '</small>' +
                                        '</div>' +
                                        '</td>' +
                                        '<td><span class="fw-bold text-dark">' + item.maas_durumu + '</span></td>' +
                                        '<td>' + maasTutariFormatted + '</td>' +
                                        '<td>' + formatDate(item.baslangic_tarihi) + '</td>' +
                                        '<td>' + bitisBadge + '</td>' +
                                        '<td>' + statusBadge + '</td>' +
                                        '<td class="text-center text-nowrap">' +
                                        '<button type="button" class="btn btn-sm btn-soft-primary btn-gorev-gecmisi-duzenle me-1" data-id="' + item.id + '" title="Düzenle">' +
                                        '<i class="bx bx-edit-alt"></i>' +
                                        '</button>' +
                                        '<button type="button" class="btn btn-sm btn-soft-danger btn-gorev-gecmisi-sil" data-id="' + item.id + '" title="Sil">' +
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
                            initGorevDataTable();
                        }
                    }
                });
            }

            function initGorevDataTable() {
                if ($.fn.DataTable && $('#tblGorevGecmisi').length) {
                    if ($.fn.DataTable.isDataTable('#tblGorevGecmisi')) {
                        $('#tblGorevGecmisi').DataTable().destroy();
                    }
                    var dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
                    $('#tblGorevGecmisi').DataTable($.extend(true, {}, dtOptions, {
                        order: [[3, 'desc']],
                        pageLength: 5
                    }));
                }
            }

            function initGorevGecmisi() {
                initGorevDataTable();

                // Initialize Select2 once for the modal
                $('#modalGorevGecmisiEkle .select2').each(function() {
                    $(this).select2({
                        dropdownParent: $('#modalGorevGecmisiEkle'),
                        width: '100%',
                        allowClear: true
                    });
                });

                // Initialize Flatpickr once for the modal
                $('#modalGorevGecmisiEkle .flatpickr').flatpickr({
                    dateFormat: "d.m.Y",
                    locale: "tr",
                    allowInput: true
                });

                // Open modal for new entry
                $(document).off('click', '#btnOpenGorevGecmisiModal').on('click', '#btnOpenGorevGecmisiModal', function () {
                    $('#gorev_gecmisi_id').val('');
                    $('#gorev_gecmisi_action').val('gorev-gecmisi-ekle');
                    $('#modalGorevGecmisiEkle .modal-title').text('Yeni Maaş Tipi Tanımla');
                    $('#modal_header_subtitle').text('Değişiklikleri kaydetmek için formu doldurun.');
                    $('#modal_header_icon').attr('class', 'bx bx-plus-circle');
                    $('#modal_header_icon_box').css({'background': 'rgba(52, 195, 143, 0.1)', 'color': '#34c38f'});
                    $('#formGorevGecmisiEkle')[0].reset();
                    $('#modalGorevGecmisiEkle .select2').val(null).trigger('change.select2');
                    $('#modalGorevGecmisiEkle').modal('show');
                });

                // Modal Show Event (for icons)
                $('#modalGorevGecmisiEkle').on('shown.bs.modal', function() {
                    if (typeof feather !== 'undefined') {
                        feather.replace();
                    }
                });

                // Edit entry
                $(document).off('click', '.btn-gorev-gecmisi-duzenle').on('click', '.btn-gorev-gecmisi-duzenle', function () {
                    var id = $(this).data('id');
                    var $btn = $(this);
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i>');

                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: { action: 'gorev-gecmisi-get', id: id },
                        dataType: 'json',
                        success: function (response) {
                            $btn.prop('disabled', false).html(originalHtml);
                            if (response.status === 'success') {
                                var data = response.data;
                                $('#gorev_gecmisi_id').val(data.id);
                                $('#gorev_gecmisi_action').val('gorev-gecmisi-guncelle');
                                $('#modalGorevGecmisiEkle .modal-title').text('Maaş Tipini Düzenle');
                                $('#modal_header_subtitle').text('Kayıt bilgilerini aşağıdan güncelleyebilirsiniz.');
                                $('#modal_header_icon').attr('class', 'bx bx-edit-alt');
                                $('#modal_header_icon_box').css({'background': 'rgba(80, 165, 241, 0.1)', 'color': '#50a5f1'});

                                $('#modal_maas_durumu').val(data.maas_durumu).trigger('change.select2');
                                
                                if (data.departman) {
                                    var deps = data.departman.split(',');
                                    $('#modal_departman').val(deps).trigger('change.select2');
                                    loadModalGorevOptions(deps, function() {
                                        if (data.gorev) {
                                            $('#modal_gorev').val(data.gorev).trigger('change.select2');
                                        }
                                    });
                                }

                                var formattedMoney = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(data.maas_tutari);
                                $('#formGorevGecmisiEkle input[name="maas_tutari"]').val(formattedMoney);
                                
                                // Date formatting for Flatpickr
                                var $baslangic = $('#formGorevGecmisiEkle input[name="gorev_baslangic"]');
                                if ($baslangic.length && $baslangic[0]._flatpickr) {
                                    $baslangic[0]._flatpickr.setDate(data.baslangic_tarihi);
                                } else {
                                    $baslangic.val(data.baslangic_tarihi);
                                }

                                var $bitis = $('#formGorevGecmisiEkle input[name="gorev_bitis"]');
                                if ($bitis.length && $bitis[0]._flatpickr) {
                                    if (data.bitis_tarihi) {
                                        $bitis[0]._flatpickr.setDate(data.bitis_tarihi);
                                    } else {
                                        $bitis[0]._flatpickr.clear();
                                    }
                                } else {
                                    $bitis.val(data.bitis_tarihi || '');
                                }

                                $('#formGorevGecmisiEkle textarea[name="aciklama"]').val(data.aciklama);

                                $('#modalGorevGecmisiEkle').modal('show');
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        }
                    });
                });

                // Delete entry
                $(document).off('click', '.btn-gorev-gecmisi-sil').on('click', '.btn-gorev-gecmisi-sil', function () {
                    var id = $(this).data('id');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Bu kayıt kalıcı olarak silinecektir.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Sil!',
                        cancelButtonText: 'İptal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: 'views/personel/api.php',
                                type: 'POST',
                                data: { action: 'gorev-gecmisi-sil', id: id },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'success') {
                                        Swal.fire('Başarılı', response.message, 'success').then(() => {
                                            refreshGorevGecmisiTable();
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
                $('#btnSaveGorevGecmisi').on('click', function() {
                    $('#formGorevGecmisiEkle').submit();
                });

                // Form submit
                $('#formGorevGecmisiEkle').on('submit', function(e) {
                    e.preventDefault();
                    var $btn = $('#btnSaveGorevGecmisi');
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Kaydediliyor...');

                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire('Başarılı', response.message, 'success').then(() => {
                                    $('#modalGorevGecmisiEkle').modal('hide');
                                    refreshGorevGecmisiTable();
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

                // Local events for modal fields
                $('#modal_departman').on('change', function() {
                    loadModalGorevOptions($(this).val());
                });

                $('#modal_gorev').on('change', function() {
                    var $selected = $(this).find(':selected');
                    var ucret = $selected.data('ucret') || $selected.attr('data-ucret') || 0;
                    var numericUcret = parseFloat(ucret) || 0;
                    
                    var $maasTutari = $('#formGorevGecmisiEkle input[name="maas_tutari"]');
                    if ($maasTutari.length && numericUcret > 0) {
                        var formattedUcret = numericUcret
                            .toFixed(2)
                            .replace(".", ",")
                            .replace(/\B(?=(\d{3})+(?!\d))/g, ".");

                        // IMask desteği için kontrol
                        if ($maasTutari[0].status && typeof IMask !== 'undefined') {
                            // Eğer IMask instance'ı varsa typedValue üzerinden güncellemek en sağlıklısıdır
                            // Ancak instance'a erişimimiz her zaman olmayabilir.
                            // Bu yüzden value set edip input eventini tetikleyeceğiz.
                        }
                        
                        $maasTutari.val("₺" + formattedUcret);
                        // IMask veya diğer dinleyiciler için input event'ini tetikle
                        $maasTutari.trigger('input');
                    }
                });
            }

            $(document).ready(function() {
                var waitSelect2 = setInterval(function() {
                    if (typeof $.fn.select2 !== 'undefined') {
                        clearInterval(waitSelect2);
                        initGorevGecmisi();
                    }
                }, 100);
            });

        })(jQuery);
    </script>
<?php endif; ?>
