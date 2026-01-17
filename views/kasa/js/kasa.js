$(function() {
    // Para formatlama için bir kütüphane kullanmak (örn: Cleave.js) önerilir.
    // Şimdilik basit bir kontrol yapalım:
    var $bakiyeInput = $('#baslangic_bakiyesi');
    if ($bakiyeInput.length) {
        $bakiyeInput.on('blur', function() {
            var value = $(this).val().replace(/\./g, '').replace(',', '.');
            var numberValue = parseFloat(value);
            if (!isNaN(numberValue)) {
                // $(this).val(numberValue.toFixed(2).replace('.', ',')); // İsteğe bağlı
            }
        });
    }

    var $saveButton = $('#kasaKaydetBtn');
    if ($saveButton.length) {
        $saveButton.on('click', function() {
            var $form = $('#kasaForm');
            var form = $form[0];
            var formData = new FormData(form);


            formData.append('action', "kasa_kaydet");

            // Başlangıç bakiyesindeki formatlamayı temizle
            if ($bakiyeInput.length) {
                var cleanValue = $bakiyeInput.val().replace(/\./g, '').replace(',', '.');
                formData.set('baslangic_bakiyesi', cleanValue);
            }

            // --- AJAX Gönderimi ---
            for (var pair of formData.entries()) {
                console.log(pair[0] + ':', pair[1]);
            }

            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...');
            $.ajax({
                url: '/views/kasa/api.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                    //    $btn.prop('disabled', false).html('<i class="bx bx-save label-icon me-1"></i>Kaydet ');
                        Swal.fire('Başarılı!', data.message, 'success').then(function() {

                        });
                    } else {
                        Swal.fire('Hata!', data.message || 'İşlem sırasında bir hata oluştu.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Kaydetme hatası:', error);
                    Swal.fire('Sunucu Hatası!', 'Bir ağ hatası oluştu.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="bx bx-save label-icon me-1"></i> Kaydet');
                }
            });
        });
    }
});

/** Kasa Sil */
$(document).on('click', '.kasa-sil', function() {
    var kasaId = $(this).data('id');
    Swal.fire({
        title: 'Kasa Sil',
        text: 'Bu kasayı silmek istediğinize emin misiniz?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'Hayır, iptal et'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/views/kasa/api.php',
                method: 'POST',
                data: {
                    action: 'kasa_sil',
                    enc_kasa_id: kasaId
                },
                dataType: 'json',
                success: function(data) {
                    if (data.status === 'success') {
                        Swal.fire('Başarılı!', data.message, 'success').then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Hata!', data.message || 'İşlem sırasında bir hata oluştu.', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Silme hatası:', error);
                    Swal.fire('Sunucu Hatası!', 'Bir ağ hatası oluştu.', 'error');
                }
            });
        }
    });
});
