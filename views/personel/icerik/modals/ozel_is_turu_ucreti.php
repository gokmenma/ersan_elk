<?php
use App\Helper\Form;
use App\Helper\Helper;
?>
<?php if ($id > 0): ?>
    <!-- Modal: Özel İş Türü Fiyatı Ekle/Düzenle -->
    <div class="modal fade" id="modalOzelIsTuruUcreti" tabindex="-1" aria-labelledby="modalOzelIsTuruUcretiLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white" id="modalOzelIsTuruUcretiLabel"><i class="bx bx-purchase-tag me-2"></i>Özel İş Türü Birim Fiyatı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <form id="formOzelIsTuruUcreti">
                    <input type="hidden" name="action" id="ozel_ucret_action" value="ozel-is-turu-ucreti-ekle">
                    <input type="hidden" name="id" id="ozel_ucret_id" value="">
                    <input type="hidden" name="personel_id" value="<?= $id ?>">
                    
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold small text-muted">İş Türü <span class="text-danger">*</span></label>
                                <?php
                                $TanimlamalarModel = new \App\Model\TanimlamalarModel();
                                $isTurleriRaw = $TanimlamalarModel->getUcretliIsTurleri();
                                $isTuruOptions = ['' => 'İş Türü Seçiniz'];
                                foreach ($isTurleriRaw as $it) {
                                    $etiket = $it->is_emri_sonucu ?: $it->tur_adi;
                                    if (!empty($it->tur_adi) && $it->tur_adi !== $etiket) {
                                        $etiket .= " (" . $it->tur_adi . ")";
                                    }
                                    $isTuruOptions[$it->id] = $etiket;
                                }
                                echo Form::FormSelect2("is_turu_id", $isTuruOptions, "", "İş Türü", "tag", "key", "", "form-select select2", true, "width:100%", "", "modal_is_turu_id");
                                ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "ucret", "0,00", "Özel Birim Ücret", "Özel Birim Ücret (Normal)", "dollar-sign", "form-control money", true); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "aracli_ucret", "0,00", "Özel Araçlı Ücret", "Özel Araçlı Ücret", "car", "form-control money", false); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "gecerlilik_baslangic", \App\Helper\Date::dmY($personel->ise_giris_tarihi ?? \App\Helper\Date::today()), "Geçerlilik Başlangıç", "Geçerlilik Başlangıç", "calendar", "form-control flatpickr", true); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput("text", "gecerlilik_bitis", "", "Geçerlilik Bitiş (Opsiyonel)", "Geçerlilik Bitiş (Opsiyonel)", "calendar", "form-control flatpickr", false); ?>
                            </div>
                            <div class="col-12">
                                <?php echo Form::FormSelect2("aktif", ["1" => "Aktif", "0" => "Pasif"], "1", "Durum", "check-circle", "key", "", "form-select select2", false, "width:100%", "", "modal_aktif"); ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnKaydetOzelUcret">
                            <i class="bx bx-save me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Modal içindeki select2'leri modal context ile bağla
        $('#modalOzelIsTuruUcreti .select2').select2({
            dropdownParent: $('#modalOzelIsTuruUcreti'),
            width: '100%'
        });

        // Tarih seçiciyi saat olmadan sadece gün-ay-yıl olarak başlat
        $('#modalOzelIsTuruUcreti .flatpickr').flatpickr({
            dateFormat: "d.m.Y",
            locale: "tr",
            allowInput: true,
            enableTime: false
        });

        // Modal Aç Butonu
        $(document).off('click', '#btnOpenOzelUcretModal').on('click', '#btnOpenOzelUcretModal', function(e) {
            e.preventDefault();
            $('#formOzelIsTuruUcreti')[0].reset();
            $('#ozel_ucret_action').val('ozel-is-turu-ucreti-ekle');
            $('#ozel_ucret_id').val('');
            $('#modal_is_turu_id').val('').trigger('change.select2');
            $('#modal_aktif').val('1').trigger('change.select2');
            $('#formOzelIsTuruUcreti input[name="ucret"]').val('0,00');
            $('#formOzelIsTuruUcreti input[name="aracli_ucret"]').val('0,00');
            
            var defaultStart = '<?= \App\Helper\Date::dmY($personel->ise_giris_tarihi ?? \App\Helper\Date::today()) ?>';
            var $baslangic = $('#formOzelIsTuruUcreti input[name="gecerlilik_baslangic"]');
            if ($baslangic.length && $baslangic[0]._flatpickr) {
                $baslangic[0]._flatpickr.setDate(defaultStart);
            } else {
                $baslangic.val(defaultStart);
            }

            var $bitis = $('#formOzelIsTuruUcreti input[name="gecerlilik_bitis"]');
            if ($bitis.length && $bitis[0]._flatpickr) {
                $bitis[0]._flatpickr.clear();
            } else {
                $bitis.val('');
            }

            $('#modalOzelIsTuruUcretiLabel').html('<i class="bx bx-purchase-tag me-2"></i>Yeni Özel İş Türü Fiyatı Tanımla');
            
            var modal = new bootstrap.Modal(document.getElementById('modalOzelIsTuruUcreti'));
            modal.show();
        });

        // Form Submit (Kaydet / Güncelle)
        $(document).off('submit', '#formOzelIsTuruUcreti').on('submit', '#formOzelIsTuruUcreti', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            var $btn = $('#btnKaydetOzelUcret');
            $btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Kaydediliyor...');

            $.ajax({
                url: 'views/personel/api.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                    if (response.status === 'success') {
                        var modalEl = document.getElementById('modalOzelIsTuruUcreti');
                        var modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        } else {
                            $('#modalOzelIsTuruUcreti').modal('hide');
                        }

                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: response.message || 'Bir hata oluştu.'
                        });
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: 'Sunucu ile bağlantı kurulamadı.'
                    });
                }
            });
        });

        // Düzenle Butonu
        $(document).off('click', '.btn-ozel-ucret-duzenle').on('click', '.btn-ozel-ucret-duzenle', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i>');

            $.ajax({
                url: 'views/personel/api.php',
                type: 'POST',
                data: { action: 'ozel-is-turu-ucreti-getir', id: id },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (response.status === 'success' && response.data) {
                        var d = response.data;
                        $('#ozel_ucret_action').val('ozel-is-turu-ucreti-guncelle');
                        $('#ozel_ucret_id').val(d.id);
                        $('#modal_is_turu_id').val(d.is_turu_id).trigger('change.select2');
                        $('#formOzelIsTuruUcreti input[name="ucret"]').val(d.ucret);
                        $('#formOzelIsTuruUcreti input[name="aracli_ucret"]').val(d.aracli_ucret);
                        
                        var $baslangic = $('#formOzelIsTuruUcreti input[name="gecerlilik_baslangic"]');
                        if ($baslangic.length && $baslangic[0]._flatpickr) {
                            if (d.gecerlilik_baslangic) {
                                $baslangic[0]._flatpickr.setDate(d.gecerlilik_baslangic);
                            } else {
                                $baslangic[0]._flatpickr.clear();
                            }
                        } else {
                            $baslangic.val(d.gecerlilik_baslangic || '');
                        }

                        var $bitis = $('#formOzelIsTuruUcreti input[name="gecerlilik_bitis"]');
                        if ($bitis.length && $bitis[0]._flatpickr) {
                            if (d.gecerlilik_bitis) {
                                $bitis[0]._flatpickr.setDate(d.gecerlilik_bitis);
                            } else {
                                $bitis[0]._flatpickr.clear();
                            }
                        } else {
                            $bitis.val(d.gecerlilik_bitis || '');
                        }

                        $('#modal_aktif').val(d.aktif).trigger('change.select2');
                        $('#modalOzelIsTuruUcretiLabel').html('<i class="bx bx-edit-alt me-2"></i>Özel İş Türü Fiyatı Düzenle');
                        
                        var modal = new bootstrap.Modal(document.getElementById('modalOzelIsTuruUcreti'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata', text: response.message || 'Kayıt getirilemedi.' });
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                    Swal.fire({ icon: 'error', title: 'Hata', text: 'Kayıt getirilirken hata oluştu.' });
                }
            });
        });

        // Sil Butonu
        $(document).off('click', '.btn-ozel-ucret-sil').on('click', '.btn-ozel-ucret-sil', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            Swal.fire({
                title: 'Silmek istediğinize emin misiniz?',
                text: 'Bu özel birim fiyat kaydı silinecektir!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'İptal'
            }).then(function(result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'views/personel/api.php',
                        type: 'POST',
                        data: { action: 'ozel-is-turu-ucreti-sil', id: id },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Silindi',
                                    text: response.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: response.message || 'Silinemedi.' });
                            }
                        }
                    });
                }
            });
        });
    });
    </script>
<?php endif; ?>
