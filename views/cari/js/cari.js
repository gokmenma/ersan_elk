$(document).ready(function () {
    const table = $('#cariTable').DataTable({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: "views/cari/api.php",
            type: "POST",
            data: function (d) {
                d.action = "cari-ajax-list";
            },
            dataSrc: function(json) {
                renderMobileList(json.data);
                if (json.summary) {
                    $('#toplam_borc').text(json.summary.toplam_borc || '0,00');
                    $('#toplam_alacak').text(json.summary.toplam_alacak || '0,00');
                    $('#genel_bakiye').text(json.summary.genel_bakiye || '0,00');
                    
                    const bakiyeVal = parseFloat((json.summary.genel_bakiye || '0').toString().replace('.', '').replace(',', '.'));
                    $('#genel_bakiye').parent().removeClass('text-danger text-success');
                    if (bakiyeVal < 0) $('#genel_bakiye').parent().addClass('text-danger');
                    else if (bakiyeVal > 0) $('#genel_bakiye').parent().addClass('text-success');
                    
                    // Gösterimi de abs yapalım
                    $('#genel_bakiye').text(json.summary.genel_bakiye ? json.summary.genel_bakiye.replace('-', '') : '0,00');
                    const label = bakiyeVal < 0 ? '(Borçlu)' : (bakiyeVal > 0 ? '(Alacaklı)' : '');
                    if ($('#bakiye_bilgi').length === 0) {
                        $('#genel_bakiye').after(`<small id="bakiye_bilgi" style="font-size: 0.6rem; display: block;">${label}</small>`);
                    } else {
                        $('#bakiye_bilgi').text(label);
                    }
                }
                return json.data;
            }
        },
        columns: [
            { data: "id", className: "text-center" },
            { data: "CariAdi" },
            { data: "Telefon", className: "text-center" },
            { data: "Email" },
            { data: "Adres" },
            { data: "bakiye", className: "text-end" },
            { data: "actions", orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Cari Listesi',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5]
                }
            }
        ]
    });

    // Excel Aktar Butonu
    $('#btnExportExcel').on('click', function () {
        table.button('.buttons-excel').trigger();
    });

    // Yeni Cari Butonu
    $('#btnYeniCari, #btnYeniCariMobile, #btnYeniCariMobileTop').on('click', function () {
        $('#cariForm')[0].reset();
        $('#cari_id').val('');
        $('#cariModalLabel').text('Yeni Cari Ekle');
        // Feather icon logic if needed on edit:
        $('.modal-header .bg-success-subtle').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
        if (typeof feather !== 'undefined') feather.replace();
        $('#cariModal').modal('show');
    });

    // Mobil Arama
    $('#mobileSearch').on('keyup', function () {
        table.search(this.value).draw();
    });

    function renderMobileList(data) {
        const container = $('#cariMobileContainer');
        container.empty();

        if (data.length === 0) {
            container.append('<div class="text-center py-5 text-muted">Kayıt bulunamadı.</div>');
            return;
        }

        data.forEach(item => {
            const initial = item.CariAdi.charAt(0).toUpperCase();
            
            // ID'yi güvenli bir şekilde al
            const tempDiv = $('<div>').html(item.actions);
            const encId = tempDiv.find('.hesap-hareketleri').data('id');
            
            const bakiyeVal = parseFloat(item.bakiye.replace(/[^0-9,-]/g, '').replace(',', '.'));
            const isBorc = item.bakiye.indexOf('(B)') !== -1 || (bakiyeVal < 0 && item.bakiye.indexOf('(A)') === -1);
            const bakiyeCls = isBorc ? 'text-danger' : (bakiyeVal > 0 ? 'text-success' : 'text-dark');
            const bakiyeLabel = isBorc ? 'BORÇLU' : (bakiyeVal > 0 ? 'ALACAKLI' : 'BAKİYE YOK');

            const card = `
                <div class="mobile-card" onclick="location.href='index.php?p=cari/hesap-hareketleri&id=${encId}'">
                    <div class="mobile-card-icon">${initial}</div>
                    <div class="mobile-card-content">
                        <div class="mobile-card-title">${item.CariAdi}</div>
                        <div class="mobile-card-subtitle"><i class="bx bx-phone me-1"></i>${item.Telefon || '-'}</div>
                    </div>
                    <div class="mobile-card-value text-end">
                        <span class="mobile-card-amt ${bakiyeCls}">${item.bakiye}</span>
                        <span class="mobile-card-type text-muted">${bakiyeLabel}</span>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    // Cari Kaydet
    $('#cariForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: formData,
            dataType: "json",
            success: function (res) {
                if (res.status === "success") {
                    $('#cariModal').modal('hide');
                    table.ajax.reload();
                    Swal.fire("Başarılı!", res.message, "success");
                } else {
                    Swal.fire("Hata!", res.message, "error");
                }
            }
        });
    });

    // Cari Düzenle
    $('#cariTable').on('click', '.duzenle', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: { action: "cari-getir", cari_id: id },
            dataType: "json",
            success: function (res) {
                $('#cariForm')[0].reset();
                $('#cari_id').val(id);
                $('#CariAdi').val(res.CariAdi);
                $('#Telefon').val(res.Telefon);
                $('#Email').val(res.Email);
                $('#Adres').val(res.Adres);
                $('#cariModalLabel').text('Cariyi Düzenle');
                $('.modal-header .bg-success-subtle').html('<i data-feather="edit" style="width: 24px; height: 24px; color: #10b981;"></i>');
                if (typeof feather !== 'undefined') feather.replace();
                $('#cariModal').modal('show');
            }
        });
    });

    // Cari Sil
    $('#cariTable').on('click', '.cari-sil', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: "Emin misiniz?",
            text: "Bu cari kaydı silinecektir!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Evet, sil!",
            cancelButtonText: "İptal"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "views/cari/api.php",
                    type: "POST",
                    data: { action: "cari-sil", cari_id: id },
                    dataType: "json",
                    success: function (res) {
                        if (res.status === "success") {
                            table.ajax.reload();
                            Swal.fire("Silindi!", res.message, "success");
                        } else {
                            Swal.fire("Hata!", res.message, "error");
                        }
                    }
                });
            }
        });
    });
});
