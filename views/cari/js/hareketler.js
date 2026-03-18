$(document).ready(function () {
    const table = $('#hareketTable').DataTable({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: "views/cari/api.php",
            type: "POST",
            data: function (d) {
                d.action = "hesap-hareketleri-ajax-list";
                d.cari_id = global_cari_id;
            },
            dataSrc: function(json) {
                renderMobileHareketler(json.data);
                $('#op_count').text(`(${json.recordsTotal} İşlem)`);
                return json.data;
            }
        },
        columns: [
            { data: "islem_tarihi", className: "text-center" },
            { data: "belge_no", className: "text-center" },
            { data: "aciklama" },
            { data: "borc", className: "text-end text-danger" },
            { data: "alacak", className: "text-end text-success" },
            { data: "yuruyen_bakiye", className: "text-end" },
            { data: "actions", className: "text-center", orderable: false, searchable: false }
        ],
        order: [[0, 'desc'], [1, 'desc']], // SQL tarafında da desc gelmeli
        pageLength: 50,
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Hesap Hareketleri',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ]
    });

    // Excel Aktar Butonu
    $('#btnExportExcel, #btnExportExcelMobile, #btnExportExcelMobileTop').on('click', function () {
        table.button('.buttons-excel').trigger();
    });

    function renderMobileHareketler(data) {
        const container = $('#hareketMobileContainer');
        container.empty();

        if (data.length === 0) {
            container.append('<div class="text-center py-5 text-muted fst-italic">İşlem kaydı bulunamadı.</div>');
            return;
        }

        data.forEach(item => {
            const isAldim = item.borc !== '-'; 
            const icon = isAldim ? 'bx-minus-circle' : 'bx-plus-circle';
            const cls = isAldim ? 'up' : 'down';
            const amt = isAldim ? item.borc : item.alacak;
            const typeLabel = isAldim ? 'Aldım' : 'Verdim';

            const card = `
                <div class="op-card flex-wrap">
                    <div class="d-flex align-items-center w-100">
                        <div class="op-icon ${cls}"><i class="bx ${icon}"></i></div>
                        <div class="op-info">
                            <div class="op-date">${item.islem_tarihi}</div>
                            <div class="op-desc">${item.aciklama || 'Açıklama girilmemiş'}</div>
                        </div>
                        <div class="op-value">
                            <span class="op-amt ${isAldim ? 'text-danger' : 'text-success'}">${amt}</span>
                            <span class="op-type text-muted">${typeLabel}</span>
                        </div>
                    </div>
                    <div class="w-100 d-flex justify-content-end gap-2 mt-2 pt-2 border-top border-light-subtle">
                        <button class="btn btn-sm btn-light-primary px-2 py-1 hareket-duzenle" data-id="${item.actions.match(/data-id="([^"]+)"/)[1]}" style="font-size: 10px;">
                            <i class="bx bx-edit-alt me-1"></i> Düzenle
                        </button>
                        <button class="btn btn-sm btn-light-danger px-2 py-1 hareket-sil" data-id="${item.actions.match(/data-id="([^"]+)"/)[1]}" style="font-size: 10px;">
                            <i class="bx bx-trash me-1"></i> Sil
                        </button>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    // Aldım / Verdim Butonları (Mobil ve Masaüstü)
    $('#btnAldimMobile, #btnAldimDesktop').on('click', function() {
        showHizliIslem('aldim');
    });

    $('#btnVerdimMobile, #btnVerdimDesktop').on('click', function() {
        showHizliIslem('verdim');
    });

    function showHizliIslem(type) {
        $('#hizliIslemForm')[0].reset();
        $('#hizliIslemForm').find('input[name="hareket_id"]').remove();
        $('#hizli_islem_type').val(type);
        
        const fp = document.querySelector("#islem_tarihi")._flatpickr;
        if(fp) {
            fp.setDate(new Date());
        } else {
            $('#islem_tarihi').val(new Date().toISOString().slice(0, 16).replace('T', ' '));
        }
        
        if (type === 'aldim') {
            $('#hizliIslemModalLabel').text('Aldım');
            $('#hizliIslemModalDesc').text('Alınan tutar bilgisini giriniz.');
            $('.modal-header .bg-primary-subtle').removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger');
            $('#hizliIslemModalIcon').html('<i data-feather="minus-circle" style="width: 24px; height: 24px; color: #ef4444;"></i>');
            $('#hizli_islem_amt_label').text('Alınan Tutar');
        } else {
            $('#hizliIslemModalLabel').text('Verdim');
            $('#hizliIslemModalDesc').text('Yapılan ödeme bilgisini giriniz.');
            $('.modal-header .bg-primary-subtle').removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
            $('#hizliIslemModalIcon').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
            $('#hizli_islem_amt_label').text('Verilen Tutar');
        }
        
        if (typeof feather !== 'undefined') feather.replace();
        
        $('#hizliIslemModal').modal('show');
    }

    $(".flatpickr-time-input").flatpickr({
        enableTime: true,
        dateFormat: "d.m.Y H:i",
        time_24hr: true,
        allowInput: true,
        disableMobile: "true",
        locale: "tr"
    });

    $('#hizliIslemForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: formData,
            dataType: "json",
            success: function(res) {
                if (res.status === "success") {
                    $('#hizliIslemModal').modal('hide');
                    table.ajax.reload();
                    // Bakiyeyi güncelle (sayfa yenilemeden)
                    if (res.new_bakiye_raw !== undefined) {
                        const bakiye = parseFloat(res.new_bakiye_raw);
                        const isBorc = bakiye < 0;
                        const statusText = isBorc ? '(Borç)' : (bakiye > 0 ? '(Alacak)' : '');
                        const color = isBorc ? '#f43f5e' : '#2a9d8f'; // text-danger / text-success matches
                        const colorClass = isBorc ? 'text-danger' : 'text-success';
                        
                        $('#genel_bakiye_kart').text(res.new_bakiye);
                        $('#bakiye_status_text').text(statusText);
                        $('#bakiye_label_container, #bakiye_icon_color').removeClass('text-danger text-success').addClass(colorClass);
                        $('#mobile_bakiye_title').text(isBorc ? 'GÜNCEL BORÇ' : 'GÜNCEL ALACAK').css('color', color);
                        
                        $('#toplam_borc_kart').text(res.new_borc);
                        $('#toplam_alacak_kart').text(res.new_alacak);
                    }
                    showToast(res.message, "success");
                } else {
                    Swal.fire("Hata!", res.message, "error");
                }
            }
        });
    });

    // Hareket Düzenle
    $(document).on('click', '.hareket-duzenle', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: { action: "hareket-getir", hareket_id: id },
            dataType: "json",
            success: function (res) {
                if(res) {
                    $('#hizliIslemForm')[0].reset();
                    
                    // Inputları doldur
                    if ($('#hizliIslemForm').find('input[name="hareket_id"]').length === 0) {
                        $('#hizliIslemForm').append('<input type="hidden" name="hareket_id" value="' + id + '">');
                    } else {
                        $('#hizliIslemForm').find('input[name="hareket_id"]').val(id);
                    }
                    
                    $('#hizli_islem_type').val(res.type);
                    
                    // Flatpickr değerini ayarla
                    const fp = document.querySelector("#islem_tarihi")._flatpickr;
                    if(fp) {
                        fp.setDate(res.islem_tarihi);
                    } else {
                        $('#islem_tarihi').val(res.islem_tarihi);
                    }
                    
                    $('#tutar').val(res.tutar);
                    $('#belge_no').val(res.belge_no);
                    $('#aciklama').val(res.aciklama);
                    
                    // Modal tiplerini ayarla
                    if (res.type === 'verdim') {
                        $('#hizliIslemModalLabel').text('Verdim Düzenle');
                        $('#hizliIslemModalDesc').text('Yapılan ödeme bilgisini güncelleyin.');
                        $('.modal-header .bg-primary-subtle').removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
                        $('#hizliIslemModalIcon').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
                        $('#hizli_islem_amt_label').text('Verilen Tutar');
                    } else {
                        $('#hizliIslemModalLabel').text('Aldım Düzenle');
                        $('#hizliIslemModalDesc').text('Alınan tutar bilgisini güncelleyin.');
                        $('.modal-header .bg-primary-subtle').removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger');
                        $('#hizliIslemModalIcon').html('<i data-feather="minus-circle" style="width: 24px; height: 24px; color: #ef4444;"></i>');
                        $('#hizli_islem_amt_label').text('Alınan Tutar');
                    }
                    
                    if (typeof feather !== 'undefined') feather.replace();
                    $('#hizliIslemModal').modal('show');
                }
            }
        });
    });

    // Hareket Sil
    $(document).on('click', '.hareket-sil', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu hesap hareketini silmek istediğinize emin misiniz? Bakiye yeniden hesaplanacaktır.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "views/cari/api.php",
                    type: "POST",
                    data: { action: "hareket-sil", hareket_id: id },
                    dataType: "json",
                    success: function (res) {
                        if (res.status === "success") {
                            table.ajax.reload();
                            if (res.new_bakiye_raw !== undefined) {
                                const bakiye = parseFloat(res.new_bakiye_raw);
                                const isBorc = bakiye < 0;
                                const statusText = isBorc ? '(Borç)' : (bakiye > 0 ? '(Alacak)' : '');
                                const color = isBorc ? '#f43f5e' : '#2a9d8f';
                                const colorClass = isBorc ? 'text-danger' : 'text-success';
                                
                                $('#genel_bakiye_kart').text(res.new_bakiye);
                                $('#bakiye_status_text').text(statusText);
                                $('#bakiye_label_container, #bakiye_icon_color').removeClass('text-danger text-success').addClass(colorClass);
                                $('#mobile_bakiye_title').text(isBorc ? 'GÜNCEL BORÇ' : 'GÜNCEL ALACAK').css('color', color);
                                
                                $('#toplam_borc_kart').text(res.new_borc);
                                $('#toplam_alacak_kart').text(res.new_alacak);
                            }
                            showToast(res.message, "success");
                        } else {
                            Swal.fire("Hata!", res.message, "error");
                        }
                    }
                });
            }
        });
    });
});
