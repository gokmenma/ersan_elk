$(document).ready(function () {
    let currentBalanceFilter = 'all';

    // Özet Kartları Açma/Kapama (AGENTS.md Standardı)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('cari-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('cari-summary-hidden');
        $('html').toggleClass('cari-summary-hidden', willHide);
        localStorage.setItem('cari_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    const table = $('#cariTable').DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
            url: "views/cari/api.php",
            type: "POST",
            data: function (d) {
                d.action = "cari-ajax-list";
                d.balance_filter = currentBalanceFilter;
            },
            dataSrc: function(json) {
                renderMobileList(json.data);
                if (json.summary) {
                    const formatMoney = (val) => {
                        return parseFloat(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };

                    // 1. Kart: Toplam Cari
                    $('#stat_toplam_cari').text(json.summary.toplam_cari || 0);
                    $('#stat_sub_borclu_alacakli').text(`Borçlu: ${json.summary.borclu_sayisi || 0} | Alacaklı: ${json.summary.alacakli_sayisi || 0}`);

                    // 2. Kart: Toplam Alacak (Verdim)
                    $('#toplam_alacak').text(formatMoney(json.summary.toplam_alacak) + ' ₺');
                    $('#stat_sub_alacakli_sayi').text(`${json.summary.alacakli_sayisi || 0} Alacaklı Hesap`);

                    // 3. Kart: Toplam Borç (Aldım)
                    $('#toplam_borc').text(formatMoney(json.summary.toplam_borc) + ' ₺');
                    $('#stat_sub_borclu_sayi').text(`${json.summary.borclu_sayisi || 0} Borçlu Hesap`);
                    
                    // 4. Kart: Genel Bakiye (Net)
                    const bakiyeVal = parseFloat(json.summary.genel_bakiye || 0);
                    $('#genel_bakiye').parent().removeClass('text-danger text-success text-dark');
                    if (bakiyeVal < 0) $('#genel_bakiye').parent().addClass('text-danger');
                    else if (bakiyeVal > 0) $('#genel_bakiye').parent().addClass('text-success');
                    
                    $('#genel_bakiye').text(formatMoney(Math.abs(bakiyeVal)) + ' ₺');
                    
                    let bakiyeLabel = '0,00 ₺ (Dengede)';
                    let bakiyeBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                    if (bakiyeVal < 0) {
                        bakiyeLabel = 'Borçluyuz (Net)';
                        bakiyeBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    } else if (bakiyeVal > 0) {
                        bakiyeLabel = 'Alacaklıyız (Net)';
                        bakiyeBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    }

                    $('#bakiye_bilgi')
                        .attr('class', `badge ${bakiyeBadgeClass} rounded-pill px-2 py-1 font-size-11 fw-semibold`)
                        .text(bakiyeLabel);
                }
                return json.data;
            }
        },
        columns: [
            { data: "id", className: "text-center", width: "50px" },
            { data: "CariAdi" },
            { data: "firma" },
            { data: "vkn_tckn", className: "text-center", width: "120px" },
            { data: "Telefon", className: "text-center", width: "120px" },
            { data: "il_ilce", width: "140px" },
            { data: "bakiye", className: "text-end", width: "130px" },
            { data: "actions", className: "text-center", width: "130px", orderable: false, searchable: false }
        ],
        createdRow: function(row, data, dataIndex) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[1, 'asc']]
    }));
 
    // Satır Tıklama (Hareketlere Git) - Komple Satır (İşlem Sütunu Hariç)
    $('#cariTable tbody').on('click', 'tr td:not(:last-child)', function (e) {
        if ($(e.target).closest('a, button, .dropdown-menu').length > 0) return;
        const href = $(this).closest('tr').find('a.hesap-hareketleri').attr('href');
        if (href) window.location.href = href;
    });

    // Excel Aktar Butonları
    $('#btnExportExcel, #btnHeaderExportExcel, #btnDropdownExportExcel').on('click', function () {
        const searchVal = table.search();
        const url = `views/cari/export-excel.php?balance_filter=${currentBalanceFilter}&search=${encodeURIComponent(searchVal)}`;
        window.open(url, '_blank');
    });

    // Yenile Butonu
    $('#btnHeaderRefresh').on('click', function () {
        table.ajax.reload(null, false);
    });

    // Yazdır Butonu
    $('#btnHeaderPrint').on('click', function () {
        window.print();
    });

    // Hızlı Filtre Butonları (Özet Kartlar İçi Butonlar)
    $('.status-quick-filter').on('click', function () {
        const filter = $(this).data('balance') || 'all';
        currentBalanceFilter = filter;
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        table.ajax.reload();
    });

    // Select2 Başlatma (Modal içi)
    function initCariModalSelect2() {
        $('#cariModal .select2').select2({
            dropdownParent: $('#cariModal'),
            width: '100%'
        });
    }

    // İl değiştiğinde ilçeleri yükle
    $('#il').on('change', function () {
        const selectedIl = $(this).val();
        const ilceSelect = $('#ilce');
        ilceSelect.empty().append('<option value="">İlçe Seçiniz...</option>');

        if (selectedIl && typeof EDM_DISTRICTS !== 'undefined' && EDM_DISTRICTS[selectedIl]) {
            EDM_DISTRICTS[selectedIl].forEach(function (ilce) {
                ilceSelect.append(new Option(ilce, ilce));
            });
        }
        ilceSelect.trigger('change.select2');
    });

    // Yeni Cari Butonu
    $('#btnYeniCari, #btnYeniCariMobile, #btnYeniCariMobileTop').on('click', function () {
        $('#cariForm')[0].reset();
        $('#cari_id').val('');
        $('#alici_turu').val('KURUMSAL').trigger('change.select2');
        $('#belge_turu').val('OTOMATIK').trigger('change.select2');
        $('#ulke').val('Türkiye').trigger('change.select2');
        $('#il').val('').trigger('change.select2');
        $('#ilce').empty().append('<option value="">İlçe Seçiniz...</option>').trigger('change.select2');
        $('#cariMukellefBadge').html(`
            <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                <i class="bx bx-info-circle"></i> VKN sorgulayabilirsiniz
            </span>
        `);
        $('#tab-genel-btn').tab('show');
        $('#cariModalLabel').text('Yeni Cari Ekle');
        $('.modal-header .bg-success-subtle').html('<i data-feather="plus-circle" style="width: 24px; height: 24px; color: #10b981;"></i>');
        if (typeof feather !== 'undefined') feather.replace();
        initCariModalSelect2();
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
                        ${item.firma && item.firma !== '-' ? `<div class="text-muted small mb-1" style="font-size: 10px;">${item.firma}</div>` : ''}
                        <div class="mobile-card-subtitle"><i class="bx bx-phone me-1"></i>${item.Telefon || '-'}</div>
                    </div>
                    <div class="mobile-card-right">
                        <div class="mobile-card-value">
                            <span class="mobile-card-amt ${bakiyeCls}">${item.bakiye}</span>
                            <span class="mobile-card-type text-muted">${bakiyeLabel}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-sm btn-soft-success p-1 hareket-ekle mobile-quick-add" data-id="${encId}" onclick="event.stopPropagation();">
                                <i class="bx bx-plus-circle fs-5"></i>
                            </button>
                            <i class="bx bx-chevron-right mobile-card-chevron fs-4"></i>
                        </div>
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
                    showToast(res.message, "success");
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
                $('#CariAdi').val(res.CariAdi || '');
                $('#firma').val(res.firma || '');
                $('#alici_turu').val(res.alici_turu || 'KURUMSAL').trigger('change.select2');
                $('#notlar').val(res.notlar || '');
                
                $('#vkn_tckn').val(res.vkn_tckn || '');
                $('#vergi_dairesi').val(res.vergi_dairesi || '');
                $('#belge_turu').val(res.belge_turu || 'OTOMATIK').trigger('change.select2');
                $('#posta_kutusu').val(res.posta_kutusu || '');
                $('#ticaret_sicil_no').val(res.ticaret_sicil_no || '');
                $('#mersis_no').val(res.mersis_no || '');

                $('#Telefon').val(res.Telefon || '');
                $('#Email').val(res.Email || '');
                $('#web_sitesi').val(res.web_sitesi || '');
                $('#ulke').val(res.ulke || 'Türkiye').trigger('change.select2');
                
                // İl & İlçe Doldurma
                const ilVal = res.il || '';
                $('#il').val(ilVal).trigger('change.select2');
                
                const ilceSelect = $('#ilce');
                ilceSelect.empty().append('<option value="">İlçe Seçiniz...</option>');
                if (ilVal && typeof EDM_DISTRICTS !== 'undefined' && EDM_DISTRICTS[ilVal]) {
                    EDM_DISTRICTS[ilVal].forEach(function (ilce) {
                        ilceSelect.append(new Option(ilce, ilce));
                    });
                }
                ilceSelect.val(res.ilce || '').trigger('change.select2');

                $('#posta_kodu').val(res.posta_kodu || '');
                $('#Adres').val(res.Adres || '');

                if (res.vkn_tckn) {
                    checkCariTaxpayer(res.vkn_tckn);
                } else {
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-info-circle"></i> VKN sorgulayabilirsiniz
                        </span>
                    `);
                }

                $('#tab-genel-btn').tab('show');
                $('#cariModalLabel').text('Cariyi Düzenle');
                $('.modal-header .bg-success-subtle').html('<i data-feather="edit" style="width: 24px; height: 24px; color: #10b981;"></i>');
                if (typeof feather !== 'undefined') feather.replace();
                initCariModalSelect2();
                $('#cariModal').modal('show');
            }
        });
    });

    // VKN Mükellef Sorgula Butonu
    $('#btnCariVknSorgula').on('click', function () {
        const vkn = $('#vkn_tckn').val().trim();
        if (!vkn) {
            Swal.fire('Uyarı', 'Lütfen 10 haneli VKN veya 11 haneli TCKN girin.', 'warning');
            return;
        }
        checkCariTaxpayer(vkn, true);
    });

    // GİB / EDM Mükellef Kontrol Fonksiyonu
    function checkCariTaxpayer(vkn, autoFill = false) {
        if (!vkn || (vkn.length !== 10 && vkn.length !== 11)) return;

        $('#cariMukellefBadge').html(`
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                <i class="bx bx-loader-alt bx-spin"></i> GİB Sorgulanıyor...
            </span>
        `);

        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: { action: "vkn-sorgula", vkn_tckn: vkn },
            dataType: "json",
            success: function (res) {
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    if (d.is_taxpayer) {
                        $('#cariMukellefBadge').html(`
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                                <i class="bx bx-check-circle"></i> GİB E-Fatura Mükellefi
                            </span>
                        `);
                        $('#belge_turu').val('EFATURA').trigger('change.select2');
                        
                        if (d.alias && !$('#posta_kutusu').val()) {
                            $('#posta_kutusu').val(d.alias);
                        }
                    } else {
                        $('#cariMukellefBadge').html(`
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                                <i class="bx bx-user-check"></i> E-Arşiv Mükellefi
                            </span>
                        `);
                        $('#belge_turu').val('EARSIV').trigger('change.select2');
                    }

                    if (autoFill && d.title) {
                        if (!$('#firma').val()) $('#firma').val(d.title);
                        if (!$('#CariAdi').val()) $('#CariAdi').val(d.title);
                        if (d.tax_office && !$('#vergi_dairesi').val()) $('#vergi_dairesi').val(d.tax_office);
                        if (d.city && !$('#il').val()) $('#il').val(d.city).trigger('change');
                    }
                } else {
                    $('#cariMukellefBadge').html(`
                        <span class="badge bg-secondary-subtle text-muted border px-3 py-2 rounded-pill font-size-12 w-100 d-flex align-items-center justify-content-center gap-1">
                            <i class="bx bx-help-circle"></i> E-Arşiv (GİB Kaydı Yok)
                        </span>
                    `);
                }
            },
            error: function () {
                $('#cariMukellefBadge').html(`
                    <span class="badge bg-light text-muted border px-3 py-2 rounded-pill font-size-12 w-100">
                        Sorgulanamadı
                    </span>
                `);
            }
        });
    }

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
                            updateSummaryCards();
                            showToast(res.message, "success");
                        } else {
                            Swal.fire("Hata!", res.message, "error");
                        }
                    }
                });
            }
        });
    });

    function updateSummaryCards() {
        table.ajax.reload(null, false); // Sayfayı kaydırmadan yenile
        if ($('#sonHareketlerModal').hasClass('show')) {
            loadSonHareketlerModal();
        }
    }

    // Hızlı Hareket Ekle (Cari Listesi - Desktop & Mobile)
    $(document).on('click', '.hareket-ekle', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        $('#hizliIslemForm')[0].reset();
        
        // Reset radio buttons to default 'aldim'
        $('#type_aldim').prop('checked', true);
        
        $('#hizli_islem_cari_id').val(id);
        $('#hizliIslemModal').modal('show');
        
        // Maskeleri yenile ve focus yap
        setTimeout(() => {
            if(typeof applyMoneyMask === 'function') applyMoneyMask();
            $('#hizliIslemForm input[name="tutar"]').focus();
        }, 500);
    });

    // Kaydet - Hızlı İşlem
    $('#hizliIslemForm').on('submit', function (e) {
        e.preventDefault();
        let submitBtn = $(this).find('button[type="submit"]');
        let originalText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Kaydediliyor...').prop('disabled', true);

        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: new FormData(this),
            dataType: "json",
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.status === "success" || res.status === "success_alert") {
                    $('#hizliIslemModal').modal('hide');
                    table.ajax.reload();
                    updateSummaryCards(); // Bakiyeleri ve son hareketleri güncellemek için
                    showToast(res.message || 'İşlem başarıyla eklendi.', 'success');
                } else {
                    Swal.fire("Hata!", res.message || "İşlem kaydedilemedi.", "error");
                }
            },
            error: function () {
                Swal.fire("Hata!", "Sunucu hatası oluştu.", "error");
            },
            complete: function () {
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // --- SON HESAP HAREKETLERİ MODALI BÖLÜMÜ ---
    let currentSonHareketType = 'all';

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function loadSonHareketlerModal() {
        const tbody = $('#sonHareketlerModalTbody');
        const searchVal = $('#sonHareketlerModalSearch').val() || '';

        tbody.html(`
            <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Hareketler yükleniyor...
                </td>
            </tr>
        `);

        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: {
                action: "son-hareketler-getir",
                type: currentSonHareketType,
                search: searchVal,
                limit: 25
            },
            dataType: "json",
            success: function (res) {
                if (res.status === "success" && Array.isArray(res.data)) {
                    if (res.data.length === 0) {
                        tbody.html(`
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bx bx-info-circle fs-3 d-block mb-1 text-secondary"></i>
                                    Kayıtlı hesap hareketi bulunamadı.
                                </td>
                            </tr>
                        `);
                        $('#sonHareketlerModalStatusText').text('0 işlem bulundu');
                        return;
                    }

                    $('#sonHareketlerModalStatusText').text(`En son ${res.data.length} işlem listelendi`);

                    let html = '';
                    res.data.forEach(item => {
                        const cariName = escapeHtml(item.CariAdi || '-');
                        const firmaName = item.firma ? `<div class="text-muted font-size-11 text-truncate" style="max-width: 240px;">${escapeHtml(item.firma)}</div>` : '';
                        const belgeNo = item.belge_no 
                            ? `<span class="badge bg-light text-dark border font-monospace font-size-11 px-2 py-1">${escapeHtml(item.belge_no)}</span>` 
                            : '<span class="text-muted">-</span>';
                        const aciklama = item.aciklama 
                            ? `<span class="text-secondary font-size-12 d-inline-block text-wrap" style="max-width: 300px; line-height: 1.3;">${escapeHtml(item.aciklama)}</span>` 
                            : '<span class="text-muted">-</span>';
                        const ekleyenBadge = item.ekleyen && item.ekleyen !== '-'
                            ? `<span class="badge bg-light text-secondary border font-size-11"><i class="bx bx-user me-1"></i>${escapeHtml(item.ekleyen)}</span>`
                            : '<span class="text-muted">-</span>';

                        html += `
                            <tr class="align-middle" style="cursor: pointer;" onclick="if (!event.target.closest('a, button')) { window.location.href='${item.hareket_link}'; }">
                                <td>
                                    <div class="fw-semibold text-dark font-size-12">${item.islem_tarih_gun}</div>
                                    <div class="text-muted font-size-11"><i class="bx bx-time-five me-1"></i>${item.islem_tarih_saat}</div>
                                </td>
                                <td>
                                    <div><a href="${item.hareket_link}" class="fw-bold text-dark font-size-13 text-decoration-none">${cariName}</a></div>
                                    ${firmaName}
                                </td>
                                <td class="text-center">
                                    ${item.type_badge}
                                </td>
                                <td>
                                    ${belgeNo}
                                </td>
                                <td>
                                    ${aciklama}
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold font-size-13 ${item.tutar_color}">${item.tutar_fmt}</span>
                                </td>
                                <td>
                                    ${ekleyenBadge}
                                </td>
                                <td class="text-center">
                                    <a href="${item.hareket_link}" class="btn btn-subtle-primary btn-sm table-action-btn" title="Hesap Hareketlerine Git">
                                        <i class="bx bx-right-arrow-alt font-size-15"></i>
                                    </a>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.html(html);
                } else {
                    tbody.html(`
                        <tr>
                            <td colspan="8" class="text-center py-5 text-danger">
                                <i class="bx bx-error-circle fs-3 d-block mb-1"></i>
                                ${res.message || 'Veriler yüklenirken bir hata oluştu.'}
                            </td>
                        </tr>
                    `);
                }
            },
            error: function () {
                tbody.html(`
                    <tr>
                        <td colspan="8" class="text-center py-5 text-danger">
                            <i class="bx bx-error-circle fs-3 d-block mb-1"></i>
                            Sunucu bağlantısında hata oluştu.
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Modal Aç Butonu
    $('#btnSonHareketlerModal').on('click', function () {
        $('#sonHareketlerModal').modal('show');
        loadSonHareketlerModal();
    });

    // Modal İçi Filtre Butonları
    $('#sonHareketlerModalFilterGroup').on('click', '.son-modal-filter-btn', function () {
        $('.son-modal-filter-btn').removeClass('active btn-subtle-primary').addClass('btn-light');
        $(this).addClass('active btn-subtle-primary').removeClass('btn-light');
        currentSonHareketType = $(this).data('type') || 'all';
        loadSonHareketlerModal();
    });

    // Modal İçi Arama
    let modalSearchTimeout;
    $('#sonHareketlerModalSearch').on('keyup', function () {
        clearTimeout(modalSearchTimeout);
        modalSearchTimeout = setTimeout(() => {
            loadSonHareketlerModal();
        }, 300);
    });

    // Modal İçi Yenile Butonu
    $('#btnSonHareketlerModalRefresh').on('click', function () {
        loadSonHareketlerModal();
    });
});

