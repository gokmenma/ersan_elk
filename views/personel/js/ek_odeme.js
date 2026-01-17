$(document).ready(function() {
    
    // Ek Ödeme Modal Aç
    $(document).on('click', '#btnOpenEkOdemeModal', function() {
        $('#modalPersonelEkOdemeEkle').modal('show');
    });

    // Ek Ödeme Kaydet
    $(document).on('click', '#btnPersonelEkOdemeKaydet', function() {
        var form = $('#formPersonelEkOdemeEkle');
        if (form[0].checkValidity() === false) {
            form[0].reportValidity();
            return;
        }

        var data = form.serialize() + '&action=save_ek_odeme';
        
        $.ajax({
            url: 'views/personel/ajax/ek-odeme-islemleri.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#modalPersonelEkOdemeEkle').modal('hide');
                    form[0].reset();
                    refreshEkOdemeTab();
                    Swal.fire('Başarılı', 'Ek ödeme kaydedildi.', 'success');
                } else {
                    Swal.fire('Hata', response.error || 'Bir hata oluştu', 'error');
                }
            },
            error: function() {
                Swal.fire('Hata', 'Bir hata oluştu.', 'error');
            }
        });
    });

    // Silme İşlemleri
    $(document).on('click', '.btn-personel-ek-odeme-sil', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu ek ödeme kaydı silinecek!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, sil',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'views/personel/ajax/ek-odeme-islemleri.php',
                    type: 'POST',
                    data: { action: 'delete_ek_odeme', id: id, personel_id: $('input[name="personel_id"]').val() },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            refreshEkOdemeTab();
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
    
    function refreshEkOdemeTab() {
        var targetPane = $('#ek_odemeler');
        var url = targetPane.attr('data-url');
        if (url) {
            $.get(url, function(html) {
                targetPane.html(html);
                
                // Re-init plugins if needed (select2 etc inside the loaded content)
                if ($(".select2").length > 0) {
                     $(".select2").each(function() {
                        $(this).select2({
                            dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body)
                        });
                    });
                }
            });
        }
    }
});
