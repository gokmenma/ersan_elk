<?php
use App\Helper\Form;
?>
<?php if ($id > 0): ?>
    <!-- Ekip Geçmişi Ekle Modal -->
    <div class="modal fade" id="modalEkipGecmisiEkle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-group me-2"></i>Yeni Ekip Tanımla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEkipGecmisiEkle" method="post">
                    <input type="hidden" name="action" id="ekip_gecmisi_action" value="ekip-gecmisi-ekle">
                    <input type="hidden" name="id" id="ekip_gecmisi_id">
                    <input type="hidden" name="personel_id" value="<?= $id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <?php echo Form::FormSelect2("modal_ekip_bolge", $ekip_bolge_options, "", "Bölge Seçimi", "map-pin", "key", "", "form-select select2"); ?>
                        </div>

                        <div class="mb-3">
                            <div class="form-floating form-floating-custom">
                                <select class="form-select select2" name="ekip_kodu_id" id="ekip_kodu_id" required
                                    style="width: 100%">
                                    <option value="">Ekip seçiniz...</option>
                                    <?php foreach ($ekip_kodlari_raw as $ekip): ?>
                                        <option value="<?= $ekip->id ?>" data-bolge="<?= $ekip->ekip_bolge ?>">
                                            <?= htmlspecialchars($ekip->tur_adi) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="ekip_kodu_id">Ekip Kodu</label>
                                <div class="form-floating-icon">
                                    <i class="bx bx-group"></i>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <?php echo Form::FormFloatInput("text", "baslangic_tarihi", date('d.m.Y'), "Başlangıç Tarihi", "Başlangıç Tarihi", "calendar", "form-control flatpickr", true); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <?php echo Form::FormFloatInput("text", "bitis_tarihi", "", "Bitiş Tarihi", "Bitiş Tarihi", "calendar", "form-control flatpickr"); ?>
                                <small class="text-muted">Boş bırakılırsa "Aktif" kabul edilir.</small>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-1"></i> Personel aynı anda birden fazla ekipte aktif olarak görev
                            alabilir.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary" id="btnEkipGecmisiKaydet">
                            <i class="bx bx-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function ($) {
            function refreshEkipGecmisiTable() {
                var personelId = $('#formEkipGecmisiEkle input[name="personel_id"]').val();
                console.log("Ekip geçmişi yenileniyor, Personel ID:", personelId);

                $.ajax({
                    url: 'views/personel/api.php',
                    type: 'POST',
                    data: { action: 'get-ekip-gecmisi', personel_id: personelId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            var $tbody = $('#tblEkipGecmisi tbody');

                            if ($.fn.DataTable.isDataTable('#tblEkipGecmisi')) {
                                $('#tblEkipGecmisi').DataTable().destroy();
                                $('#tblEkipGecmisi thead .search-input-row').remove();
                            }

                            $tbody.empty();

                            if (response.data && response.data.length > 0) {
                                var bugun = new Date().toISOString().split('T')[0];
                                var newestActiveEkip = null;

                                $.each(response.data, function (i, item) {
                                    var bitisTarihi = item.bitis_tarihi ? item.bitis_tarihi : null;
                                    var isAktif = (item.baslangic_tarihi <= bugun && (bitisTarihi === null || bitisTarihi >= bugun));

                                    if (isAktif && !newestActiveEkip) {
                                        newestActiveEkip = item.ekip_adi;
                                    }

                                    var statusBadge = isAktif
                                        ? '<span class="badge bg-success">Aktif</span>'
                                        : '<span class="badge bg-secondary">Pasif</span>';

                                    var bitisBadge = bitisTarihi
                                        ? formatDate(bitisTarihi)
                                        : '<span class="badge bg-soft-success text-success">Devam Ediyor</span>';

                                    var row = '<tr>' +
                                        '<td><span class="fw-bold text-dark">' + item.ekip_adi + '</span></td>' +
                                        '<td>' + formatDate(item.baslangic_tarihi) + '</td>' +
                                        '<td>' + bitisBadge + '</td>' +
                                        '<td>' + statusBadge + '</td>' +
                                        '<td class="text-center text-nowrap">' +
                                        '<button type="button" class="btn btn-sm btn-soft-primary btn-ekip-gecmisi-duzenle me-1" data-id="' + item.id + '" title="Düzenle">' +
                                        '<i class="bx bx-edit-alt"></i>' +
                                        '</button>' +
                                        '<button type="button" class="btn btn-sm btn-soft-danger btn-ekip-gecmisi-sil" data-id="' + item.id + '" title="Sil">' +
                                        '<i class="bx bx-trash"></i>' +
                                        '</button>' +
                                        '</td>' +
                                        '</tr>';
                                    $tbody.append(row);
                                });

                                if (newestActiveEkip) {
                                    $('small.text-muted:contains("Ekip No:") b').text(newestActiveEkip);
                                }
                            }

                            if (typeof window.invalidateAllTabs === 'function') {
                                window.invalidateAllTabs();
                            }
                            initDataTable();
                            console.log("Ekip geçmişi tablosu güncellendi.");
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

            function initDataTable() {
                if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#tblEkipGecmisi')) {
                    var dtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};

                    // Derin birleştirme yapalım ki language ayarları kaybolmasın
                    var languageOptions = Object.assign({}, dtOptions.language || {}, {
                        url: 'assets/js/tr.json'
                    });

                    var customOptions = {
                        language: languageOptions,
                        order: [[1, 'desc']],
                        pageLength: 5
                    };

                    $('#tblEkipGecmisi').DataTable(Object.assign({}, dtOptions, customOptions));
                }
            }

            function initEkipGecmisi() {
                // DataTable init
                initDataTable();

                var teamData = [];

                function loadAvailableTeams(callback) {
                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: { action: 'get-musait-ekipler' },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                teamData = response.data.map(function (item) {
                                    return {
                                        id: item.id,
                                        text: item.tur_adi,
                                        bolge: item.ekip_bolge
                                    };
                                });
                                if (typeof callback === 'function') callback();
                            }
                        }
                    });
                }

                // İlk yükleme
                loadAvailableTeams();

                // Bölgeye göre ekip kodu filtreleme
                $(document).off('change', '#modal_ekip_bolge').on('change', '#modal_ekip_bolge', function () {
                    var bolge = $(this).val();
                    var $ekipSelect = $('#ekip_kodu_id');
                    $ekipSelect.find('option:not([value=""])').remove();
                    $.each(teamData, function (i, item) {
                        if (bolge === '' || String(item.bolge) === String(bolge)) {
                            $ekipSelect.append($('<option>', {
                                value: item.id,
                                text: item.text
                            }).attr('data-bolge', item.bolge));
                        }
                    });
                    $ekipSelect.trigger('change');
                });

                $(document).off('click', '#btnOpenEkipGecmisiModal').on('click', '#btnOpenEkipGecmisiModal', function () {
                    var $btn = $(this);
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Yükleniyor...');

                    loadAvailableTeams(function () {
                        $btn.prop('disabled', false).html(originalHtml);
                        $('#ekip_gecmisi_id').val('');
                        $('#ekip_gecmisi_action').val('ekip-gecmisi-ekle');
                        $('#modalEkipGecmisiEkle .modal-title').html('<i class="bx bx-group me-2"></i>Yeni Ekip Tanımla');
                        $('#formEkipGecmisiEkle')[0].reset();
                        $('#modal_ekip_bolge').val('').trigger('change');
                        $('#modalEkipGecmisiEkle').modal('show');
                    });
                });

                // Düzenleme işlemi (Event Delegation)
                $(document).off('click', '.btn-ekip-gecmisi-duzenle').on('click', '.btn-ekip-gecmisi-duzenle', function () {
                    var id = $(this).data('id');
                    var $btn = $(this);
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i>');

                    loadAvailableTeams(function () {
                        $btn.prop('disabled', false).html(originalHtml);
                        $.ajax({
                            url: 'views/personel/api.php',
                            type: 'POST',
                            data: { action: 'ekip-gecmisi-get', id: id },
                            dataType: 'json',
                            success: function (response) {
                                if (response.status === 'success') {
                                    var data = response.data;
                                    $('#ekip_gecmisi_id').val(data.id);
                                    $('#ekip_gecmisi_action').val('ekip-gecmisi-guncelle');
                                    $('#modalEkipGecmisiEkle .modal-title').html('<i class="bx bx-edit me-2"></i>Ekip Geçmişini Düzenle');

                                    // Bölgeyi bulmak için teamData kullan
                                    var item = teamData.find(x => x.id == data.ekip_kodu_id);
                                    if (item && item.bolge) {
                                        $('#modal_ekip_bolge').val(item.bolge).trigger('change');
                                    }

                                    // Ekip kodunu seç
                                    if ($('#ekip_kodu_id option[value="' + data.ekip_kodu_id + '"]').length === 0) {
                                        $('#ekip_kodu_id').append($('<option>', {
                                            value: data.ekip_kodu_id,
                                            text: data.ekip_adi
                                        })).trigger('change');
                                    }
                                    $('#ekip_kodu_id').val(data.ekip_kodu_id).trigger('change');

                                    // Tarihleri modal kapsamındaki inputlara set et
                                    var $modal = $('#modalEkipGecmisiEkle');
                                    var $baslangic = $modal.find('[name="baslangic_tarihi"]');
                                    var $bitis = $modal.find('[name="bitis_tarihi"]');

                                    // Flatpickr değerlerini güncelle
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
                });

                // Kaydetme işlemi (Event Delegation)
                $(document).off('submit', '#formEkipGecmisiEkle').on('submit', '#formEkipGecmisiEkle', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var $form = $(this);
                    var formData = $form.serialize();
                    var $btn = $('#btnEkipGecmisiKaydet');

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
                                    $('#modalEkipGecmisiEkle').modal('hide');
                                    refreshEkipGecmisiTable();
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

                $(document).off('click', '.btn-ekip-gecmisi-sil').on('click', '.btn-ekip-gecmisi-sil', function () {
                    var id = $(this).data('id');
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Bu ekip geçmişi kaydı kalıcı olarak silinecektir.",
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
                                data: { action: 'ekip-gecmisi-sil', id: id },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === 'success') {
                                        Swal.fire('Silindi', response.message, 'success').then(() => {
                                            refreshEkipGecmisiTable();
                                        });
                                    } else {
                                        Swal.fire('Hata', response.message, 'error');
                                    }
                                }
                            });
                        }
                    });
                });

                $('#modalEkipGecmisiEkle .select2').select2({
                    dropdownParent: $('#modalEkipGecmisiEkle')
                });

                // Modal kapandığında formu sıfırla
                $('#modalEkipGecmisiEkle').on('hidden.bs.modal', function () {
                    $('#ekip_gecmisi_id').val('');
                    $('#ekip_gecmisi_action').val('ekip-gecmisi-ekle');
                    $('#formEkipGecmisiEkle')[0].reset();

                    // Flatpickr alanlarını temizle
                    $(this).find('.flatpickr').each(function () {
                        if (this._flatpickr) {
                            this._flatpickr.clear();
                        }
                    });
                });
            }

            $(document).ready(function () {
                initEkipGecmisi();
            });
        })(jQuery);
    </script>
<?php endif; ?>