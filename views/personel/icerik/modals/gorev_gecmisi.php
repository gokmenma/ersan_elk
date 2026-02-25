<?php
use App\Helper\Form;
use App\Helper\Helper;
?>
<?php if ($id > 0): ?>
    <!-- Görev Geçmişi Ekle Modal -->
    <div class="modal fade" id="modalGorevGecmisiEkle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-briefcase me-2"></i>Yeni Maaş Tipi Tanımla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formGorevGecmisiEkle" method="post">
                    <input type="hidden" name="action" id="gorev_gecmisi_action" value="gorev-gecmisi-ekle">
                    <input type="hidden" name="id" id="gorev_gecmisi_id">
                    <input type="hidden" name="personel_id" value="<?= $id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <?php echo Form::FormSelect2("maas_durumu", Helper::MAAS_HESAPLAMA_TIPI, "Brüt", "Maaş Tipi", "dollar-sign"); ?>
                        </div>

                        <div class="mb-3">
                            <?php echo Form::FormFloatInput("text", "maas_tutari", "0", "Maaş Tutarı", "Maaş Tutarı", "dollar-sign", "form-control money"); ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <?php echo Form::FormFloatInput("text", "gorev_baslangic", date('d.m.Y'), "Başlangıç Tarihi", "Başlangıç Tarihi", "calendar", "form-control flatpickr", true); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <?php echo Form::FormFloatInput("text", "gorev_bitis", "", "Bitiş Tarihi", "Bitiş Tarihi", "calendar", "form-control flatpickr"); ?>
                                <small class="text-muted">Boş bırakılırsa "Aktif" kabul edilir.</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <?php echo Form::FormFloatTextarea("aciklama", "", "Açıklama", "Açıklama girebilirsiniz...", "file-text"); ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary" id="btnGorevGecmisiKaydet">
                            <i class="bx bx-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function ($) {
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

            function formatDate(dateStr) {
                if (!dateStr) return '';
                var d = new Date(dateStr);
                var day = ("0" + d.getDate()).slice(-2);
                var month = ("0" + (d.getMonth() + 1)).slice(-2);
                var year = d.getFullYear();
                return day + "." + month + "." + year;
            }

            function initGorevDataTable() {
                if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#tblGorevGecmisi')) {
                    var dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};

                    var languageOptions = Object.assign({}, dtOptions.language || {}, {
                        url: 'assets/js/tr.json'
                    });

                    var customOptions = {
                        language: languageOptions,
                        order: [[2, 'desc']],
                        pageLength: 5
                    };

                    $('#tblGorevGecmisi').DataTable(Object.assign({}, dtOptions, customOptions));
                }
            }

            function initGorevGecmisi() {
                initGorevDataTable();

                $(document).off('click', '#btnOpenGorevGecmisiModal').on('click', '#btnOpenGorevGecmisiModal', function () {
                    $('#gorev_gecmisi_id').val('');
                    $('#gorev_gecmisi_action').val('gorev-gecmisi-ekle');
                    $('#modalGorevGecmisiEkle .modal-title').html('<i class="bx bx-briefcase me-2"></i>Yeni Maaş Tipi Tanımla');
                    $('#formGorevGecmisiEkle')[0].reset();
                    $('#modalGorevGecmisiEkle .select2').trigger('change.select2');
                    $('#modalGorevGecmisiEkle').modal('show');
                });

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
                                $('#modalGorevGecmisiEkle .modal-title').html('<i class="bx bx-edit me-2"></i>Maaş Tipini Düzenle');

                                $('#formGorevGecmisiEkle [name="maas_durumu"]').val(data.maas_durumu).trigger('change.select2');

                                // Format money
                                var formattedMoney = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(data.maas_tutari);
                                $('#formGorevGecmisiEkle [name="maas_tutari"]').val(formattedMoney);

                                $('#formGorevGecmisiEkle [name="aciklama"]').val(data.aciklama);

                                var $modal = $('#modalGorevGecmisiEkle');
                                var $baslangic = $modal.find('[name="gorev_baslangic"]');
                                var $bitis = $modal.find('[name="gorev_bitis"]');

                                if ($baslangic[0] && $baslangic[0]._flatpickr) {
                                    $baslangic[0]._flatpickr.setDate(data.baslangic_tarihi);
                                } else {
                                    $baslangic.val(data.baslangic_tarihi);
                                }

                                if ($bitis[0] && $bitis[0]._flatpickr) {
                                    if (data.bitis_tarihi) {
                                        $bitis[0]._flatpickr.setDate(data.bitis_tarihi);
                                    } else {
                                        $bitis[0]._flatpickr.clear();
                                    }
                                } else {
                                    $bitis.val(data.bitis_tarihi || '');
                                }

                                $modal.modal('show');
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                            }
                        }
                    });
                });

                $(document).off('submit', '#formGorevGecmisiEkle').on('submit', '#formGorevGecmisiEkle', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var $form = $(this);
                    var formData = $form.serialize();
                    var $btn = $('#btnGorevGecmisiKaydet');

                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Kaydediliyor...');

                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    title: 'Başarılı',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'Tamam'
                                }).then(() => {
                                    $('#modalGorevGecmisiEkle').modal('hide');
                                    refreshGorevGecmisiTable();
                                    $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i>Kaydet');
                                });
                            } else {
                                Swal.fire('Hata', response.message, 'error');
                                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i>Kaydet');
                            }
                        },
                        error: function () {
                            Swal.fire('Hata', 'Bir ağ hatası oluştu.', 'error');
                            $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i>Kaydet');
                        }
                    });

                    return false;
                });

                $(document).off('click', '.btn-gorev-gecmisi-sil').on('click', '.btn-gorev-gecmisi-sil', function () {
                    var id = $(this).data('id');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Bu maaş tipi geçmişi kaydı kalıcı olarak silinecektir.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
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
                                        Swal.fire('Silindi', response.message, 'success').then(() => {
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

                $('#modalGorevGecmisiEkle .select2').select2({
                    dropdownParent: $('#modalGorevGecmisiEkle')
                });

                $('#modalGorevGecmisiEkle').on('hidden.bs.modal', function () {
                    $('#gorev_gecmisi_id').val('');
                    $('#gorev_gecmisi_action').val('gorev-gecmisi-ekle');
                    $('#formGorevGecmisiEkle')[0].reset();

                    $(this).find('.flatpickr').each(function () {
                        if (this._flatpickr) {
                            this._flatpickr.clear();
                        }
                    });
                });
            }

            $(document).ready(function () {
                initGorevGecmisi();
            });
        })(jQuery);
    </script>
<?php endif; ?>