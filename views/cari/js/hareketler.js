$(document).ready(function () {
    let currentFilterType = 'all';

    // Özet Kartları Açma/Kapama (AGENTS.md Standardı)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('cari-hareket-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('cari-hareket-summary-hidden');
        $('html').toggleClass('cari-hareket-summary-hidden', willHide);
        localStorage.setItem('cari_hareket_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    const formatMoney = (val) => {
        return parseFloat(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

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
                d.filter_type = currentFilterType;
            },
            dataSrc: function(json) {
                renderMobileHareketler(json.data);
                
                if (json.summary) {
                    $('#toplam_borc_kart').text(formatMoney(json.summary.toplam_borc) + ' ₺');
                    $('#toplam_alacak_kart').text(formatMoney(json.summary.toplam_alacak) + ' ₺');
                    
                    const bakiyeVal = parseFloat(json.summary.bakiye || 0);
                    $('#genel_bakiye_kart')
                        .removeClass('text-danger text-success text-dark')
                        .addClass(bakiyeVal < 0 ? 'text-danger' : (bakiyeVal > 0 ? 'text-success' : 'text-dark'))
                        .text(formatMoney(Math.abs(bakiyeVal)) + ' ₺');

                    let bakiyeLabel = '0,00 ₺ (Dengede)';
                    let bakiyeBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                    if (bakiyeVal < 0) {
                        bakiyeLabel = 'Borçluyum (Net)';
                        bakiyeBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    } else if (bakiyeVal > 0) {
                        bakiyeLabel = 'Alacaklıyım (Net)';
                        bakiyeBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    }

                    $('#bakiye_status_text')
                        .attr('class', `badge ${bakiyeBadgeClass} rounded-pill px-2 py-1 font-size-11 fw-semibold`)
                        .text(bakiyeLabel);

                    $('#toplam_islem_kart').text(json.summary.toplam_islem || 0);
                    $('#op_count, #mobile_op_count').text(`(${json.summary.toplam_islem || 0} İşlem)`);
                } else {
                    $('#op_count, #mobile_op_count').text(`(${json.recordsTotal || 0} İşlem)`);
                }
                return json.data;
            }
        },
        columns: [
            { data: "islem_tarihi", className: "text-center", width: "130px" },
            { data: "belge_no", width: "120px" },
            { data: "aciklama" },
            { data: "borc", className: "text-end", width: "140px" },
            { data: "alacak", className: "text-end", width: "140px" },
            { data: "yuruyen_bakiye", className: "text-end", width: "150px" },
            { data: "ekleyen", width: "140px" },
            { data: "actions", className: "text-center", width: "90px", orderable: false, searchable: false }
        ],
        drawCallback: function() {
            safeFeatherReplace();
        },
        order: [[0, 'desc']]
    });

    // Excel Aktar Butonları
    $('#btnExportExcel, #btnHeaderExportExcel').on('click', function () {
        const searchVal = table.search();
        const url = `views/cari/export-hareketler-excel.php?id=${encodeURIComponent(global_cari_id)}&filter_type=${currentFilterType}&search=${encodeURIComponent(searchVal)}`;
        window.open(url, '_blank');
    });

    // Yazdır Butonu
    $('#btnHeaderPrint').on('click', function () {
        window.print();
    });

    // Yenile Butonu
    $('#btnHeaderRefresh').on('click', function () {
        table.ajax.reload(null, false);
    });

    // Hızlı Filtre Butonları (Özet Kartlar İçi Butonlar)
    $('.status-quick-filter').on('click', function () {
        const filter = $(this).data('filter') || 'all';
        currentFilterType = filter;
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        table.ajax.reload();
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
            const isAldim = item.borc !== '<span class="text-muted">-</span>' && item.borc !== '-'; 
            const icon = isAldim ? 'bx bx-plus-circle text-success' : 'bx bx-minus-circle text-danger';
            const amt = isAldim ? item.borc : item.alacak;
            const typeLabel = isAldim ? 'Aldım' : 'Verdim';

            const tempDiv = $('<div>').html(item.actions);
            const id = tempDiv.find('.hareket-duzenle').data('id');

            const card = `
                <div class="op-card" data-id="${id}">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="d-flex align-items-center gap-1">
                            <i class="${icon} font-size-18"></i>
                            <span class="font-size-12 fw-bold text-dark">${typeLabel}</span>
                        </div>
                        <span class="text-muted font-size-11">${item.islem_tarihi}</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="text-muted font-size-12 text-truncate" style="max-width: 200px;">
                            ${item.belge_no !== '-' ? `<span class="badge bg-light text-secondary border me-1">${item.belge_no}</span>` : ''}
                            ${item.aciklama || '-'}
                        </div>
                        <div class="font-size-13 fw-bold">${amt}</div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-1.5 border-top border-light-subtle">
                        <div class="d-flex flex-column gap-0.5">
                            <span class="text-muted font-size-11">Yürüyen: ${item.yuruyen_bakiye}</span>
                            ${item.ekleyen ? `<div class="font-size-11 mt-0.5">${item.ekleyen}</div>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn btn-sm btn-subtle-warning table-action-btn hareket-duzenle" data-id="${id}" title="Düzenle">
                                <i class="bx bx-edit-alt font-size-14"></i>
                            </button>
                            <button class="btn btn-sm btn-subtle-danger table-action-btn hareket-sil" data-id="${id}" title="Sil">
                                <i class="bx bx-trash font-size-14"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.append(card);
        });
    }

    // Aldım / Verdim Butonları
    $('#btnAldimDesktop').on('click', function() {
        showHizliIslem('aldim');
    });

    $('#btnVerdimDesktop').on('click', function() {
        showHizliIslem('verdim');
    });

    function showHizliIslem(type) {
        $('#hizliIslemForm')[0].reset();
        $('#hizliIslemForm').find('.existing-file').remove();
        $('#hizli_hareket_id').val('');
        $('#hizli_islem_type').val(type);
        
        const fp = document.querySelector("#islem_tarihi")._flatpickr;
        if(fp) {
            fp.setDate(new Date());
        } else {
            $('#islem_tarihi').val(new Date().toISOString().slice(0, 16).replace('T', ' '));
        }
        
        if (type === 'aldim') {
            $('#hizliIslemModalLabel').text('Aldım (+)');
            $('#hizliIslemModalDesc').text('Alınan tutar ve işlem detaylarını girin.');
            $('#hizliIslemIconBg').removeClass('bg-danger-subtle').addClass('bg-success-subtle');
            $('#hizliIslemIcon').removeClass('bx-minus-circle text-danger').addClass('bx-plus-circle text-success');
            $('#hizli_islem_amt_label').text('Alınan Tutar');
        } else {
            $('#hizliIslemModalLabel').text('Verdim (-)');
            $('#hizliIslemModalDesc').text('Yapılan ödeme ve işlem detaylarını girin.');
            $('#hizliIslemIconBg').removeClass('bg-success-subtle').addClass('bg-danger-subtle');
            $('#hizliIslemIcon').removeClass('bx-plus-circle text-success').addClass('bx-minus-circle text-danger');
            $('#hizli_islem_amt_label').text('Verilen Tutar');
        }
        
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
        const submitBtn = $(this).find('button[type="submit"]');
        const origText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...').prop('disabled', true);

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
                    table.ajax.reload(null, false);
                    showToast(res.message, "success");
                } else {
                    Swal.fire("Hata!", res.message, "error");
                }
            },
            error: function () {
                Swal.fire("Hata!", "Sunucu hatası oluştu.", "error");
            },
            complete: function () {
                submitBtn.html(origText).prop('disabled', false);
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
                    $('#hizli_hareket_id').val(id);
                    $('#hizli_islem_type').val(res.type);
                    
                    const fp = document.querySelector("#islem_tarihi")._flatpickr;
                    if(fp) {
                        fp.setDate(res.islem_tarihi);
                    } else {
                        $('#islem_tarihi').val(res.islem_tarihi);
                    }
                    
                    $('#tutar').val(res.tutar_raw);
                    $('#belge_no').val(res.belge_no);
                    $('#aciklama').val(res.aciklama);
                    
                    if (res.type === 'verdim') {
                        $('#hizliIslemModalLabel').text('Verdim Düzenle');
                        $('#hizliIslemModalDesc').text('Yapılan ödeme bilgisini güncelleyin.');
                        $('#hizliIslemIconBg').removeClass('bg-success-subtle').addClass('bg-danger-subtle');
                        $('#hizliIslemIcon').removeClass('bx-plus-circle text-success').addClass('bx-minus-circle text-danger');
                        $('#hizli_islem_amt_label').text('Verilen Tutar');
                    } else {
                        $('#hizliIslemModalLabel').text('Aldım Düzenle');
                        $('#hizliIslemModalDesc').text('Alınan tutar bilgisini güncelleyin.');
                        $('#hizliIslemIconBg').removeClass('bg-danger-subtle').addClass('bg-success-subtle');
                        $('#hizliIslemIcon').removeClass('bx-minus-circle text-danger').addClass('bx-plus-circle text-success');
                        $('#hizli_islem_amt_label').text('Alınan Tutar');
                    }
                    
                    const fileInput = $('#hizliIslemForm').find('input[name="dosya"]');
                    fileInput.next('.existing-file').remove();
                    if (res.dosya) {
                        fileInput.after('<div class="existing-file mt-1 small text-muted"><i class="bx bx-paperclip"></i> <a href="uploads/cari_belgeler/' + res.dosya + '" target="_blank">Mevcut Belgeyi Görüntüle</a></div>');
                    }
                    
                    $('#hizliIslemModal').modal('show');
                }
            }
        });
    }

    // Buton ile düzenleme
    $(document).on('click', '.hareket-duzenle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data('id');
        editHareket(id);
    });

    // Satır tıklama ile düzenleme
    $('#hareketTable tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('a, button, .action-btn-group').length > 0) {
            return;
        }
        const btn = $(this).find('.hareket-duzenle');
        const id = btn.data('id');
        if (id) {
            editHareket(id);
        }
    });

    // Hareket Sil
    $(document).on('click', '.hareket-sil', function (e) {
        e.preventDefault();
        e.stopPropagation();
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
                            table.ajax.reload(null, false);
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

    // ---- PDF'ten Hareket Yükleme ----
    let pdfSatirlari = [];

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    $('#btnPdfYukle').on('click', function () {
        pdfSatirlari = [];
        $('#pdfDosya').val('');
        $('#pdfBelgeNo').val('');
        $('#pdfSatirlar').empty();
        $('#pdfUyarilar').empty();
        $('#pdfAdim1').removeClass('d-none');
        $('#pdfAdim2').addClass('d-none');
        $('#btnPdfAktar').addClass('d-none');
        $('#pdfTumunuSec').prop('checked', true);
        $('#pdfMukerrerAtla').prop('checked', true);
        $('#pdfYukleModal').modal('show');
    });

    $('#btnPdfAnaliz').on('click', function () {
        const dosya = $('#pdfDosya')[0].files[0];
        if (!dosya) {
            Swal.fire('Uyarı', 'Lütfen bir PDF dosyası seçin.', 'warning');
            return;
        }

        const btn = $(this);
        const eskiHtml = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Analiz ediliyor...');

        const formData = new FormData();
        formData.append('action', 'hareket-pdf-analiz');
        formData.append('cari_id', global_cari_id);
        formData.append('pdf_dosya', dosya);

        $.ajax({
            url: 'views/cari/api.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.status !== 'success') {
                    Swal.fire('Hata!', res.message || 'PDF okunamadı.', 'error');
                    return;
                }

                pdfSatirlari = res.rows;
                renderPdfSatirlari(res);

                $('#pdfAdim1').addClass('d-none');
                $('#pdfAdim2').removeClass('d-none');
                $('#btnPdfAktar').removeClass('d-none');
            },
            error: function () {
                Swal.fire('Hata!', 'PDF analizi sırasında bir sorun oluştu.', 'error');
            },
            complete: function () {
                btn.prop('disabled', false).html(eskiHtml);
            }
        });
    });

    function renderPdfSatirlari(res) {
        const tbody = $('#pdfSatirlar');
        tbody.empty();

        res.rows.forEach(function (row, i) {
            const secili = row.mukerrer ? '' : 'checked';
            const durum = row.mukerrer
                ? '<span class="badge bg-warning-subtle text-warning fw-bold">Mevcut</span>'
                : '<span class="badge bg-success-subtle text-success fw-bold">Yeni</span>';

            tbody.append(`
                <tr class="${row.mukerrer ? 'table-warning' : ''}">
                    <td class="text-center"><input type="checkbox" class="form-check-input pdf-satir-sec" data-index="${i}" ${secili}></td>
                    <td>${escapeHtml(row.tarih_gosterim)}</td>
                    <td>${escapeHtml(row.aciklama)}</td>
                    <td class="text-end text-success fw-bold">${escapeHtml(row.borc_gosterim)}</td>
                    <td class="text-end text-danger fw-bold">${escapeHtml(row.alacak_gosterim)}</td>
                    <td class="text-center">${durum}</td>
                </tr>
            `);
        });

        $('#pdfSatirSayisi').text(res.rows.length);
        $('#pdfToplamAldim').text(res.toplam_aldim + ' ₺');
        $('#pdfToplamVerdim').text(res.toplam_verdim + ' ₺');

        const uyarilar = $('#pdfUyarilar');
        uyarilar.empty();
        if (res.cari_adi) {
            uyarilar.append(`<div class="alert alert-info py-2 px-3 small mb-2">PDF'teki cari adı: <strong>${escapeHtml(res.cari_adi)}</strong>. Satırlar bu sayfadaki cariye aktarılacaktır.</div>`);
        }
        if (res.mukerrer_sayisi > 0) {
            uyarilar.append(`<div class="alert alert-warning py-2 px-3 small mb-2">${res.mukerrer_sayisi} satır bu caride zaten kayıtlı görünüyor ve işaretlenmedi.</div>`);
        }
        (res.uyarilar || []).forEach(function (u) {
            uyarilar.append(`<div class="alert alert-warning py-2 px-3 small mb-2">${escapeHtml(u)}</div>`);
        });

        $('#pdfTumunuSec').prop('checked', res.mukerrer_sayisi === 0);
        pdfSecimGuncelle();
    }

    function pdfSecimGuncelle() {
        $('#pdfSecilenSayisi').text($('.pdf-satir-sec:checked').length);
    }

    $('#pdfTumunuSec').on('change', function () {
        $('.pdf-satir-sec').prop('checked', $(this).is(':checked'));
        pdfSecimGuncelle();
    });

    $(document).on('change', '.pdf-satir-sec', pdfSecimGuncelle);

    $('#btnPdfAktar').on('click', function () {
        const secilenler = [];
        $('.pdf-satir-sec:checked').each(function () {
            const row = pdfSatirlari[$(this).data('index')];
            if (row) {
                secilenler.push({
                    tarih: row.tarih,
                    aciklama: row.aciklama,
                    borc: row.borc,
                    alacak: row.alacak
                });
            }
        });

        if (secilenler.length === 0) {
            Swal.fire('Uyarı', 'Aktarılacak satır seçilmedi.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Emin misiniz?',
            text: `${secilenler.length} hareket bu cariye eklenecek.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#212529',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Evet, aktar',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (!result.isConfirmed) return;

            const btn = $('#btnPdfAktar');
            const eskiHtml = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Aktarılıyor...');

            $.ajax({
                url: 'views/cari/api.php',
                type: 'POST',
                data: {
                    action: 'hareket-pdf-kaydet',
                    cari_id: global_cari_id,
                    belge_no: $('#pdfBelgeNo').val(),
                    mukerrer_atla: $('#pdfMukerrerAtla').is(':checked') ? 1 : 0,
                    rows: JSON.stringify(secilenler)
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        $('#pdfYukleModal').modal('hide');
                        table.ajax.reload(null, false);
                        showToast(res.message, 'success');
                    } else {
                        Swal.fire('Hata!', res.message || 'Aktarım yapılamadı.', 'error');
                    }
                },
                error: function () {
                    Swal.fire('Hata!', 'Aktarım sırasında bir sorun oluştu.', 'error');
                },
                complete: function () {
                    btn.prop('disabled', false).html(eskiHtml);
                }
            });
        });
    });
});
