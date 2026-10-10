$(document).ready(function () {
    let currentFilterType = 'all';

    // Özet Kartları Açma/Kapama (AGENTS.md Standardı)
    const toggleBtn = $('#btnToggleSummaryCards');
    const updateToggleState = () => {
        const isHidden = $('html').hasClass('tum-hareketler-summary-hidden');
        if (toggleBtn.length) {
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
        }
    };
    updateToggleState();

    toggleBtn.on('click', function () {
        const willHide = !$('html').hasClass('tum-hareketler-summary-hidden');
        $('html').toggleClass('tum-hareketler-summary-hidden', willHide);
        localStorage.setItem('tum_hareketler_summary_cards_state', willHide ? 'hidden' : 'visible');
        updateToggleState();
    });

    // Select2 Başlat
    if ($.fn.select2) {
        $('#filter_cari_id').select2({ width: '100%' });
    }

    // Flatpickr başlat
    let fpStart, fpEnd;
    if (typeof flatpickr !== 'undefined') {
        const fpDateOptions = {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y",
            allowInput: true,
            locale: (typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : "tr"
        };

        if ($('#filter_start_date').length) {
            fpStart = $('#filter_start_date').flatpickr(fpDateOptions);
        }
        if ($('#filter_end_date').length) {
            fpEnd = $('#filter_end_date').flatpickr(fpDateOptions);
        }

        $('.flatpickr-time-input').flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "d.m.Y H:i",
            time_24hr: true,
            allowInput: true,
            locale: (typeof flatpickr.l10ns !== 'undefined' && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : "tr"
        });
    }

    const baseDtOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
    const dtConfig = {
        ...baseDtOptions,
        processing: true,
        serverSide: true,
        ajax: {
            url: "views/cari/api.php",
            type: "POST",
            data: function (d) {
                d.action = "tum-hareketler-ajax-list";
                d.filter_type = currentFilterType;
                d.cari_id = $('#filter_cari_id').val() || '';
                d.start_date = $('#filter_start_date').val() || '';
                d.end_date = $('#filter_end_date').val() || '';
            },
            dataSrc: function (json) {
                if (json.summary) {
                    const formatMoney = (val) => {
                        return parseFloat(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };

                    // 1. Kart: Toplam İşlem
                    $('#stat_toplam_islem').text(json.summary.toplam_hareket || 0);
                    $('#stat_sub_islem_dagilimi').text(`Giriş: ${json.summary.aldim_sayisi || 0} | Çıkış: ${json.summary.verdim_sayisi || 0}`);

                    // 2. Kart: Toplam Verdim (Alacak)
                    $('#stat_toplam_alacak').text(formatMoney(json.summary.toplam_alacak) + ' ₺');
                    $('#stat_sub_verdim_sayi').text(`${json.summary.verdim_sayisi || 0} İşlem`);

                    // 3. Kart: Toplam Aldım (Borç)
                    $('#stat_toplam_borc').text(formatMoney(json.summary.toplam_borc) + ' ₺');
                    $('#stat_sub_aldim_sayi').text(`${json.summary.aldim_sayisi || 0} İşlem`);

                    // 4. Kart: Genel Bakiye
                    const bakiyeVal = parseFloat(json.summary.genel_bakiye || 0);
                    $('#stat_genel_bakiye').parent().removeClass('text-danger text-success text-dark');
                    if (bakiyeVal < 0) $('#stat_genel_bakiye').parent().addClass('text-danger');
                    else if (bakiyeVal > 0) $('#stat_genel_bakiye').parent().addClass('text-success');

                    $('#stat_genel_bakiye').text(formatMoney(Math.abs(bakiyeVal)) + ' ₺');

                    let bakiyeLabel = '0,00 ₺ (Dengede)';
                    let bakiyeBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                    if (bakiyeVal < 0) {
                        bakiyeLabel = 'Borçluyuz (Net)';
                        bakiyeBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    } else if (bakiyeVal > 0) {
                        bakiyeLabel = 'Alacaklıyız (Net)';
                        bakiyeBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    }

                    $('#stat_bakiye_badge')
                        .attr('class', `badge ${bakiyeBadgeClass} rounded-pill px-2 py-1 font-size-11 fw-semibold`)
                        .text(bakiyeLabel);
                }
                return json.data || [];
            }
        },
        columns: [
            { data: "id", className: "text-center", width: "50px" },
            { data: "islem_tarihi", width: "130px" },
            { data: "CariAdi" },
            { data: "islem_turu", className: "text-center", width: "100px" },
            { data: "belge_no", width: "120px" },
            { data: "aciklama" },
            { data: "borc", className: "text-end", width: "120px" },
            { data: "alacak", className: "text-end", width: "120px" },
            { data: "ekleyen", width: "130px" },
            { data: "actions", className: "text-center", width: "110px", orderable: false, searchable: false }
        ],
        createdRow: function (row, data, dataIndex) {
            $(row).find('td:not(:last-child)').attr('style', 'cursor: pointer !important');
        },
        order: [[1, 'desc']]
    };

    const finalConfig = typeof applyLengthStateSave === 'function' ? applyLengthStateSave(dtConfig) : dtConfig;
    const table = $('#tumHareketlerTable').DataTable(finalConfig);

    // Satır Tıklama
    $('#tumHareketlerTable tbody').on('click', 'tr td:not(:last-child)', function (e) {
        if ($(e.target).closest('a, button, .dropdown-menu').length > 0) return;
        const href = $(this).closest('tr').find('a.table-action-btn').attr('href');
        if (href) window.location.href = href;
    });

    // Filtre Uygula & Sıfırla
    $('#btnFilterApply').on('click', function () {
        table.ajax.reload();
    });

    $('#btnFilterReset').on('click', function () {
        $('#filter_cari_id').val('').trigger('change.select2');
        
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (fpStart) fpStart.setDate(formatDate(firstDay), true);
        else $('#filter_start_date').val(formatDate(firstDay));

        if (fpEnd) fpEnd.setDate(formatDate(lastDay), true);
        else $('#filter_end_date').val(formatDate(lastDay));

        currentFilterType = 'all';
        $('.status-quick-filter').removeClass('active');
        $('.status-quick-filter[data-type="all"]').addClass('active');
        table.ajax.reload();
    });

    // Hızlı Filtre Butonları (Özet Kartlar İçi)
    $('.status-quick-filter').on('click', function () {
        const type = $(this).data('type') || 'all';
        currentFilterType = type;
        $('.status-quick-filter').removeClass('active');
        $(this).addClass('active');
        table.ajax.reload();
    });

    // Yenile
    $('#btnTableRefresh').on('click', function () {
        table.ajax.reload(null, false);
    });

    // Yazdır
    $('#btnHeaderPrint').on('click', function () {
        window.print();
    });

    // Excel Dışa Aktarma
    $('#btnHeaderExportExcel').on('click', function () {
        const cariId = $('#filter_cari_id').val() || '';
        const search = table.search() || '';
        const startDate = $('#filter_start_date').val() || '';
        const endDate = $('#filter_end_date').val() || '';
        const url = `views/cari/export-hareketler-excel.php?filter_type=${currentFilterType}&cari_id=${cariId}&search=${encodeURIComponent(search)}&start_date=${startDate}&end_date=${endDate}`;
        window.open(url, '_blank');
    });

    // Hareket Düzenle
    $('#tumHareketlerTable').on('click', '.hareket-duzenle', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: { action: "hareket-getir", hareket_id: id },
            dataType: "json",
            success: function (res) {
                if (res) {
                    $('#edit_hareket_id').val(id);
                    $('#edit_cari_id').val(res.cari_id_enc || '');
                    if (res.type === 'aldim') {
                        $('#edit_type_aldim').prop('checked', true);
                    } else {
                        $('#edit_type_verdim').prop('checked', true);
                    }
                    $('#hareketDuzenleForm input[name="islem_tarihi"]').val(res.islem_tarihi || '');
                    $('#hareketDuzenleForm input[name="tutar"]').val(res.tutar_raw || '');
                    $('#hareketDuzenleForm input[name="belge_no"]').val(res.belge_no || '');
                    $('#hareketDuzenleForm textarea[name="aciklama"]').val(res.aciklama || '');
                    $('#hareketDuzenleModal').modal('show');
                }
            }
        });
    });

    // Hareket Düzenle Form Kaydet
    $('#hareketDuzenleForm').on('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $.ajax({
            url: "views/cari/api.php",
            type: "POST",
            data: formData,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function (res) {
                if (res.status === "success") {
                    $('#hareketDuzenleModal').modal('hide');
                    table.ajax.reload(null, false);
                    showToast(res.message || 'İşlem güncellendi.', 'success');
                } else {
                    Swal.fire("Hata!", res.message || "İşlem güncellenemedi.", "error");
                }
            }
        });
    });

    // Hareket Sil
    $('#tumHareketlerTable').on('click', '.hareket-sil', function (e) {
        e.preventDefault();
        const id = $(this).data('id');
        Swal.fire({
            title: "Emin misiniz?",
            text: "Bu hareket kaydı silinecektir!",
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
                    data: { action: "hareket-sil", hareket_id: id },
                    dataType: "json",
                    success: function (res) {
                        if (res.status === "success") {
                            table.ajax.reload(null, false);
                            showToast(res.message || 'İşlem silindi.', 'success');
                        } else {
                            Swal.fire("Hata!", res.message, "error");
                        }
                    }
                });
            }
        });
    });
});
