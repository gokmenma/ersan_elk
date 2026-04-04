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
                d.filter_type = $('input[name="filter_type"]:checked').val();
            },
            dataSrc: function(json) {
                renderMobileHareketler(json.data);
                $('#op_count').text(`(${json.recordsTotal} İşlem)`);
                return json.data;
            }
        },
        columns: [
            { data: "islem_tarihi", className: "text-center" },
            { 
                data: "belge_no", 
                className: "text-center",
                render: function(data, type, row) {
                    let html = data || '-';
                    if (row.dosya) {
                        html += ' <a href="uploads/cari_belgeler/' + row.dosya + '" target="_blank" class="ms-1 text-primary"><i data-feather="paperclip" style="width: 14px; height: 14px;"></i></a>';
                    }
                    return html;
                }
            },
            { data: "aciklama" },
            { data: "borc", className: "text-end text-success" },
            { data: "alacak", className: "text-end text-danger" },
            { data: "yuruyen_bakiye", className: "text-end" },
            { data: "actions", className: "text-center", orderable: false, searchable: false }
        ],
        drawCallback: function() {
            safeFeatherReplace();
        },
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

    $('input[name="filter_type"]').on('change', function() {
        table.ajax.reload();
    });

    $('#card_toplam_aldim').on('click', function() {
        $('#filter_in').prop('checked', true).trigger('change');
        // Scroll to filters if mobile
        if(window.innerWidth < 768) {
            $('html, body').animate({
                scrollTop: $(".btn-group").offset().top - 20
            }, 500);
        }
    });

    $('#card_toplam_verdim').on('click', function() {
        $('#filter_out').prop('checked', true).trigger('change');
        if(window.innerWidth < 768) {
            $('html, body').animate({
                scrollTop: $(".btn-group").offset().top - 20
            }, 500);
        }
    });

    $('#card_bakiye').on('click', function() {
        $('#filter_all').prop('checked', true).trigger('change');
        if(window.innerWidth < 768) {
            $('html, body').animate({
                scrollTop: $(".btn-group").offset().top - 20
            }, 500);
        }
    });

    function safeFeatherReplace() {
        if (typeof feather !== 'undefined') {
            try {
                feather.replace();
            } catch (e) {
                console.error("Feather Icons Error:", e);
            }
        }
    }

    function renderMobileHareketler(data) {
        const container = $('#hareketMobileContainer');
        container.empty();

        if (data.length === 0) {
            container.append('<div class="text-center py-5 text-muted fst-italic">İşlem kaydı bulunamadı.</div>');
            return;
        }

        data.forEach(item => {
            const isAldim = item.borc !== '-'; 
            const icon = isAldim ? 'plus-circle' : 'minus-circle';
            const cls = isAldim ? 'down' : 'up';
            const amt = isAldim ? item.borc : item.alacak;
            const typeLabel = isAldim ? 'Aldım' : 'Verdim';

            const card = `
                <div class="op-card flex-wrap">
                    <div class="d-flex align-items-center w-100">
                        <div class="op-icon ${cls}"><i data-feather="${icon}" style="width: 14px; height: 14px;"></i></div>
                        <div class="op-info">
                            <div class="op-date">${item.islem_tarihi}</div>
                            <div class="op-desc">${item.aciklama || 'Açıklama girilmemiş'}</div>
                        </div>
                        <div class="op-value">
                            <span class="op-amt ${isAldim ? 'text-success' : 'text-danger'}">${amt}</span>
                            <span class="op-type text-muted">${typeLabel}</span>
                            ${item.dosya ? `<a href="uploads/cari_belgeler/${item.dosya}" target="_blank" class="d-block mt-1 text-primary"><i data-feather="paperclip" style="width: 12px; height: 12px;"></i> Dosya</a>` : ''}
                        </div>
                    </div>
                    <div class="w-100 d-flex justify-content-end gap-2 mt-2 pt-2 border-top border-light-subtle">
                        <button class="btn btn-sm btn-light-primary px-2 py-1 hareket-duzenle" data-id="${item.actions.match(/data-id="([^"]+)"/)[1]}" style="font-size: 10px;">
                            <i data-feather="edit" style="width: 12px; height: 12px; margin-right: 2px;"></i> Düzenle
                        </button>
                        <button class="btn btn-sm btn-light-danger px-2 py-1 hareket-sil" data-id="${item.actions.match(/data-id="([^"]+)"/)[1]}" style="font-size: 10px;">
                            <i data-feather="trash" style="width: 12px; height: 12px; margin-right: 2px;"></i> Sil
                        </button>
                    </div>
                </div>
            `;
            container.append(card);
        });
        safeFeatherReplace();
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
        $('#hizliIslemForm').find('.existing-file').remove();
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
            $('.modal-header .bg-primary-subtle').removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
            $('#hizliIslemModalIcon').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
            $('#hizli_islem_amt_label').text('Alınan Tutar');
        } else {
            $('#hizliIslemModalLabel').text('Verdim');
            $('#hizliIslemModalDesc').text('Yapılan ödeme bilgisini giriniz.');
            $('.modal-header .bg-primary-subtle').removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger');
            $('#hizliIslemModalIcon').html('<i data-feather="minus-circle" style="width: 24px; height: 24px; color: #ef4444;"></i>');
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
        const formData = new FormData(this);
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: formData,
            dataType: "json",
            processData: false,
            contentType: false,
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

    // Hareket Düzenle Fonksiyonu
    function editHareket(id) {
        if(!id) return;
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
                    
                    $('#tutar').val(res.tutar_raw);
                    $('#belge_no').val(res.belge_no);
                    $('#aciklama').val(res.aciklama);
                    
                    // Modal tiplerini ayarla
                    if (res.type === 'verdim') {
                        $('#hizliIslemModalLabel').text('Verdim Düzenle');
                        $('#hizliIslemModalDesc').text('Yapılan ödeme bilgisini güncelleyin.');
                        $('.modal-header .bg-primary-subtle').removeClass('bg-success-subtle text-success').addClass('bg-danger-subtle text-danger');
                        $('#hizliIslemModalIcon').html('<i data-feather="minus-circle" style="width: 24px; height: 24px; color: #ef4444;"></i>');
                        $('#hizli_islem_amt_label').text('Verilen Tutar');
                    } else {
                        $('#hizliIslemModalLabel').text('Aldım Düzenle');
                        $('#hizliIslemModalDesc').text('Alınan tutar bilgisini güncelleyin.');
                        $('.modal-header .bg-primary-subtle').removeClass('bg-danger-subtle text-danger').addClass('bg-success-subtle text-success');
                        $('#hizliIslemModalIcon').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
                        $('#hizli_islem_amt_label').text('Alınan Tutar');
                    }
                    
                    const fileInput = $('#hizliIslemForm').find('input[name="dosya"]');
                    fileInput.next('.existing-file').remove();
                    if (res.dosya) {
                        fileInput.after('<div class="existing-file mt-1 small text-muted"><i data-feather="file" style="width: 14px; height: 14px;"></i> <a href="uploads/cari_belgeler/' + res.dosya + '" target="_blank">Mevcut Belge</a></div>');
                    }
                    
                    if (typeof feather !== 'undefined') feather.replace();
                    $('#hizliIslemModal').modal('show');
                }
            }
        });
    }

    // Buton ile düzenleme
    $(document).on('click', '.hareket-duzenle', function (e) {
        e.preventDefault();
        e.stopPropagation(); // Satır tıklamasını engelle
        const id = $(this).data('id');
        editHareket(id);
    });

    // Satır tıklama ile düzenleme
    $('#hareketTable tbody').on('click', 'tr', function (e) {
        // Eğer tıklanan element bir link, buton veya dropdown ise düzenleme açma
        if ($(e.target).closest('a, button, .dropdown, .existing-file').length > 0) {
            return;
        }
        
        const rowData = table.row(this).data();
        if (rowData && rowData.actions) {
            // ID'yi actions stringinden çek
            const match = rowData.actions.match(/data-id="([^"]+)"/);
            if (match && match[1]) {
                editHareket(match[1]);
            }
        }
    });

    // Mobilde karta tıklama ile düzenleme
    $(document).on('click', '.op-card', function(e) {
        if ($(e.target).closest('button, a').length > 0) return;
        
        const btn = $(this).find('.hareket-duzenle');
        const id = btn.data('id');
        if (id) editHareket(id);
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

    // Cari Notu Düzenle (Global Fonksiyon)
    window.editCariNoteDesktop = function() {
        $('#cariNotuModal').modal('show');
        safeFeatherReplace();
    };

    // Cari Notu Kaydet
    $('#cariNotuForm').on('submit', function(e) {
        e.preventDefault();
        const notlar = $(this).find('textarea[name="notlar"]').val();
        $.post('views/cari/api.php', {
            action: 'cari-not-kaydet',
            cari_id: global_cari_id,
            notlar: notlar
        }, function(res) {
            if(res.status === 'success') {
                location.reload();
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        }, 'json');
    });
});
