$(document).ready(function() {
    
    // Kesinti Modal Aç
    $(document).on('click', '#btnOpenKesintiModal', function() {
        $('#modalPersonelKesintiEkle').modal('show');
    });

    // İcra Modal Aç
    // Bu fonksiyon icra.js dosyasına taşındı.
    /* $(document).on('click', '#btnOpenIcraModal', function() {
        $('#modalPersonelIcraEkle').modal('show');
    }); */

    // Kesinti Türü Değişince
    $(document).on('change', '#kesinti_tur', function() {
        var tur = $(this).val();
        var personel_id = $('input[name="personel_id"]').val();
        
        if (tur === 'icra') {
            $('#div_icra_secimi').removeClass('d-none');
            $('#kesinti_icra_id').prop('required', true);
            
            // İcraları getir
            $.ajax({
                url: 'views/personel/ajax/kesinti-islemleri.php',
                type: 'POST',
                data: {
                    action: 'get_icralar',
                    personel_id: personel_id
                },
                dataType: 'json',
                success: function(response) {
                    var options = '<option value="">Dosya seçiniz...</option>';
                    if (response && response.length > 0) {
                        $('#no_icra_warning').hide();
                        $.each(response, function(i, item) {
                            options += '<option value="' + item.id + '" data-tutar="' + item.aylik_kesinti_tutari + '">' + 
                                       item.icra_dairesi + ' - ' + item.dosya_no + 
                                       ' (Kesinti: ' + item.aylik_kesinti_tutari + ' TL)</option>';
                        });
                    } else {
                        $('#no_icra_warning').show();
                    }
                    $('#kesinti_icra_id').html(options);
                }
            });
        } else {
            $('#div_icra_secimi').addClass('d-none');
            $('#kesinti_icra_id').prop('required', false);
            $('#kesinti_tutar').val(''); // Clear amount if not icra
        }
    });

    // İcra Seçilince Tutarı Otomatik Doldur
    $(document).on('change', '#kesinti_icra_id', function() {
        var selected = $(this).find('option:selected');
        var tutar = selected.data('tutar');
        if (tutar) {
            $('#kesinti_tutar').val(tutar);
        }
    });
    
    // Kesinti Kaydet
    $(document).on('click', '#btnPersonelKesintiKaydet', function() {
        var form = $('#formPersonelKesintiEkle');
        if (form[0].checkValidity() === false) {
            form[0].reportValidity();
            return;
        }

        var data = form.serialize() + '&action=save_kesinti';
        
        $.ajax({
            url: 'views/personel/ajax/kesinti-islemleri.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#modalPersonelKesintiEkle').modal('hide');
                    form[0].reset();
                    refreshKesintiTab();
                    Swal.fire('Başarılı', 'Kesinti kaydedildi.', 'success');
                } else {
                    Swal.fire('Hata', response.error || 'Bir hata oluştu', 'error');
                }
            },
            error: function() {
                Swal.fire('Hata', 'Bir hata oluştu.', 'error');
            }
        });
    });

    // İcra Kaydet
    // Bu fonksiyon icra.js dosyasına taşındı.

    // Silme İşlemleri
    $(document).on('click', '.btn-personel-kesinti-sil', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu kesinti kaydı silinecek!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'views/personel/ajax/kesinti-islemleri.php',
                    type: 'POST',
                    data: { action: 'delete_kesinti', id: id, personel_id: $('input[name="personel_id"]').val() },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            refreshKesintiTab();
                            Swal.fire('Silindi!', 'Kayıt silindi.', 'success');
                        } else {
                            Swal.fire('Hata', response.error || 'Bir hata oluştu', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Hata', 'Silme işlemi başarısız.', 'error');
                    }
                });
            }
        });
    });
    
    // İcra Silme İşlemi
    // Bu fonksiyon icra.js dosyasına taşındı.

    function refreshKesintiTab() {
        var targetPane = $('#kesintiler');
        var url = targetPane.attr('data-url');
        if (url) {
            $.get(url, function(html) {
                targetPane.html(html);
            });
        }
    }
});
