/**
 * Taslak Faturalar Modülü JavaScript Yöneticisi
 * Ersan Elektrik - E-Fatura & E-Arşiv Yönetimi
 */

$(document).ready(function() {
    let currentBelgeFilter = '';
    let currentPreviewInvoiceId = null;

    window.efaturaSetupSummary('efatura_taslak_summary_cards_state');

    let currentStartDate = '';
    let currentEndDate = '';

    // Varsayılan: İçinde Bulunulan Ayın Başı - Sonu (Hızlı Yükleme)
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const formatYMD = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const formatDMY = (d) => `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}`;

    currentStartDate = formatYMD(firstDay);
    currentEndDate = formatYMD(lastDay);

    // Flatpickr Tarih Aralığı Seçici Başlat
    if (typeof flatpickr !== 'undefined' && document.getElementById('filterDateRange')) {
        flatpickr('#filterDateRange', {
            mode: 'range',
            locale: 'tr',
            dateFormat: 'd.m.Y',
            defaultDate: [firstDay, lastDay],
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    currentStartDate = formatYMD(selectedDates[0]);
                    currentEndDate = formatYMD(selectedDates[1]);
                    table.ajax.reload();
                    loadStats();
                }
            }
        });
    }

    // Tarih Filtresi Temizle Butonu
    $('#btnClearDateRange').on('click', function(e) {
        e.stopPropagation();
        currentStartDate = '';
        currentEndDate = '';
        if (document.getElementById('filterDateRange') && document.getElementById('filterDateRange')._flatpickr) {
            document.getElementById('filterDateRange')._flatpickr.clear();
        }
        $('#filterDateRange').val('');
        table.ajax.reload();
        loadStats();
    });

    // 2. İstatistikleri Yükle
    function loadStats() {
        let url = 'api/efatura-api.php?action=summary_stats&list_type=taslak';
        if (currentStartDate) url += `&baslangic_tarihi=${currentStartDate}`;
        if (currentEndDate) url += `&bitis_tarihi=${currentEndDate}`;
        fetch(url)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    const formatMoney = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';

                    if (document.getElementById('stat_toplam_adet')) {
                        document.getElementById('stat_toplam_adet').textContent = d.toplam_adet || 0;
                    }
                    if (document.getElementById('stat_sub_efatura_earsiv')) {
                        document.getElementById('stat_sub_efatura_earsiv').textContent = `E-Fatura: ${d.efatura_adet || 0} | E-Arşiv: ${d.earsiv_adet || 0}`;
                    }
                    if (document.getElementById('stat_efatura_adet')) {
                        document.getElementById('stat_efatura_adet').textContent = d.efatura_adet || 0;
                    }
                    if (document.getElementById('stat_earsiv_adet')) {
                        document.getElementById('stat_earsiv_adet').textContent = d.earsiv_adet || 0;
                    }
                    if (document.getElementById('stat_toplam_tutar')) {
                        document.getElementById('stat_toplam_tutar').textContent = formatMoney(d.toplam_tutar);
                    }
                    if (document.getElementById('stat_bu_ay_tutar')) {
                        document.getElementById('stat_bu_ay_tutar').textContent = formatMoney(d.bu_ay_tutar);
                    }
                }
            })
            .catch(err => console.error("Taslak summary stats error:", err));
    }
    loadStats();

    // 3. DataTables Tablosunu Başlat (AGENTS.md getDatatableOptions() ve applyLengthStateSave())
    const baseOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};

    const tableOptions = {
        ...baseOptions,
        serverSide: true,
        processing: true,
        searchDelay: 400,
        responsive: false,
        colReorder: true,
        order: [[3, 'desc']], // Tarihe göre sıralı
        language: $.extend(true, {}, (baseOptions.language || {}), {
            info: "Gösterilen _START_ - _END_ / _TOTAL_ kayıt",
            infoEmpty: "Kayıt bulunamadı",
            lengthMenu: "Sayfada _MENU_ kayıt göster",
            paginate: {
                previous: '<i class="bx bx-chevron-left font-size-14"></i>',
                next: '<i class="bx bx-chevron-right font-size-14"></i>'
            }
        }),
        ajax: {
            url: 'api/efatura-api.php?action=list_invoices&list_type=taslak',
            type: 'GET',
            data: function(d) {
                if (currentBelgeFilter) {
                    d.belge_turu_filtre = currentBelgeFilter;
                }
                if (currentStartDate) {
                    d.baslangic_tarihi = currentStartDate;
                }
                if (currentEndDate) {
                    d.bitis_tarihi = currentEndDate;
                }
            },
            dataSrc: function(json) {
                return (json && Array.isArray(json.data)) ? json.data : [];
            },
            error: function(xhr, error, thrown) {
                console.error("Taslak faturalar AJAX yükleme hatası:", error, thrown);
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle',
                render: function(data, type, row, meta) {
                    return `
                    <div class="form-check font-size-16">
                        <input class="form-check-input row-select-checkbox" type="checkbox" data-id="${row.encrypted_id}" id="chk_${meta.row}">
                        <label class="form-check-label" for="chk_${meta.row}"></label>
                    </div>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center align-middle fw-semibold text-muted font-size-12',
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            {
                data: 'fatura_no',
                className: 'align-middle fw-bold text-dark font-monospace',
                render: function(data, type, row) {
                    const fNo = (!data || data === 'Taslak' || data === '<span class="text-muted fst-italic">Taslak</span>') ? 'Taslak' : data;
                    return `
                    <div class="d-flex flex-column">
                        <a href="javascript:void(0)" class="text-primary fw-bold btn-preview text-decoration-none" data-id="${row.encrypted_id}">
                            <i class="bx bx-file me-1"></i>${fNo}
                        </a>
                        <small class="text-muted font-monospace" style="font-size: 10px;">${row.ettn || ''}</small>
                    </div>`;
                }
            },
            {
                data: 'fatura_tarihi',
                className: 'align-middle',
                render: function(data, type, row) {
                    const saat = row.duzenleme_saati ? `<span class="text-muted font-monospace" style="font-size: 11px;"><i class="bx bx-time-five me-1 font-size-11 align-middle"></i>${row.duzenleme_saati}</span>` : '';
                    return `
                    <div class="d-flex flex-column">
                        <span class="fw-semibold text-dark font-size-12">${data || '-'}</span>
                        ${saat}
                    </div>`;
                }
            },
            {
                data: 'alici_unvan',
                className: 'align-middle',
                render: function(data) {
                    return `<div class="fw-semibold text-dark text-truncate" style="max-width: 240px;" title="${data}">${data}</div>`;
                }
            },
            {
                data: 'alici_vkn_tckn',
                className: 'align-middle text-muted font-monospace'
            },
            {
                data: 'belge_turu',
                className: 'align-middle text-center',
                render: function(data) {
                    if (data === 'EFATURA') {
                        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-buildings me-1"></i>E-Fatura</span>';
                    }
                    return '<span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-user me-1"></i>E-Arşiv</span>';
                }
            },
            {
                data: 'fatura_profili',
                className: 'align-middle text-muted',
                render: function(data) {
                    return `<span class="badge bg-light text-secondary border px-2 py-1 font-size-11">${data || 'TICARIFATURA'}</span>`;
                }
            },
            {
                data: 'odenecek_tutar',
                className: 'align-middle text-end fw-bold text-dark font-monospace'
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    return `<a href="javascript:void(0)" class="btn-tahsilat-ekle text-primary fw-semibold text-decoration-none d-inline-flex align-items-center gap-1 font-size-12 px-2 py-1 rounded-2 hover-bg-light" data-id="${row.encrypted_id}"><i class="bx bx-plus-circle font-size-14 text-success"></i> Tahsilat Ekle</a>`;
                }
            },
            {
                data: 'tahsil_edilen_tutar',
                className: 'align-middle text-end font-monospace',
                render: function(data, type, row) {
                    const durum = row.tahsilat_durumu;
                    const odenen = data || '0,00 TRY';
                    const rawOdenen = Number(row.tahsil_edilen_tutar_raw || 0);

                    if (rawOdenen <= 0.0001) {
                        return `<span class="text-muted font-size-12">${odenen}</span>`;
                    } else if (durum === 'ODENDI') {
                        return `
                        <div class="d-flex flex-column align-items-end">
                            <span class="fw-bold text-success font-size-12">${odenen}</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle font-size-10 px-1.5 py-0.5 mt-0.5">Tamamı Ödendi</span>
                        </div>`;
                    } else {
                        return `
                        <div class="d-flex flex-column align-items-end">
                            <span class="fw-bold text-primary font-size-12">${odenen}</span>
                            <small class="text-danger font-size-10">Kalan: ${row.kalan_tutar || ''}</small>
                        </div>`;
                    }
                }
            },
            {
                data: 'entegrator_durum_kodu',
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    if (data === 'GONDERILDI') {
                        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-size-11"><i class="bx bx-loader-circle me-1"></i>İşleniyor</span>';
                    }
                    return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 font-size-11"><i class="bx bx-time-five me-1"></i>Taslak</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    let btns = `<div class="d-flex align-items-center justify-content-center gap-1 action-btn-group">`;

                    if (row.entegrator_durum_kodu === 'TASLAK') {
                        btns += `
                        <button type="button" class="btn btn-subtle-success table-action-btn btn-send-edm" data-id="${row.encrypted_id}" title="EDM / GİB'e Gönder">
                            <i class="bx bx-send font-size-15"></i>
                        </button>
                        <a href="index.php?p=efatura/olustur&id=${encodeURIComponent(row.encrypted_id)}" class="btn btn-subtle-warning table-action-btn" title="Taslağı Düzenle">
                            <i class="bx bx-edit font-size-15"></i>
                        </a>`;
                    } else {
                        btns += `
                        <button type="button" class="btn btn-subtle-info table-action-btn btn-sync-single" data-id="${row.encrypted_id}" title="GİB Durumunu Sorgula">
                            <i class="bx bx-refresh font-size-15"></i>
                        </button>`;
                    }

                    btns += `
                        <button type="button" class="btn btn-subtle-primary table-action-btn btn-preview" data-id="${row.encrypted_id}" title="Önizle">
                            <i class="bx bx-show font-size-15"></i>
                        </button>`;

                    if (row.entegrator_durum_kodu === 'TASLAK') {
                        btns += `
                        <button type="button" class="btn btn-subtle-danger table-action-btn btn-delete-draft" data-id="${row.encrypted_id}" title="Taslağı Sil">
                            <i class="bx bx-trash font-size-15"></i>
                        </button>`;
                    }

                    btns += `</div>`;
                    return btns;
                }
            }
        ],
        pageLength: 25,
        createdRow: function(row, data) {
            $(row).attr('data-id', data.encrypted_id);
            $(row).data('row-info', data);
        }
    };

    const finalOptions = typeof applyLengthStateSave === 'function' ? applyLengthStateSave(tableOptions) : tableOptions;
    const table = $('#tblTaslakFaturalar').DataTable(finalOptions);

    // Gelişmiş Sütun Yönetimi (ColReorder, ColVis & Sürükle-Bırak Gizleme)
    if (typeof window.efaturaInitColumnManagement === 'function') {
        window.efaturaInitColumnManagement({
            table: table,
            tableId: '#tblTaslakFaturalar',
            storageKey: 'efatura_taslak_col_state'
        });
    }

    // Check All Kutusu
    $('#checkAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.row-select-checkbox').prop('checked', isChecked);
        updateSelectedBulkCount();
    });

    $(document).on('change', '.row-select-checkbox', function() {
        updateSelectedBulkCount();
    });

    // Satır Seçimi (Tıklama ile)
    $('#tblTaslakFaturalar tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('a, button, input, .dropdown-menu').length) return;
        $('#tblTaslakFaturalar tbody tr').removeClass('selected');
        $(this).addClass('selected');
    });

    function updateSelectedBulkCount() {
        const selectedCount = $('.row-select-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);
        if (selectedCount > 0) {
            $('#btnBulkSend').removeClass('d-none');
        } else {
            $('#btnBulkSend').addClass('d-none');
        }
    }

    // Header Butonları
    $('#btnHeaderRefresh').on('click', function() {
        table.ajax.reload(null, false);
        loadStats();
    });

    // EDM'den Taslakları / Faturaları Çek
    const backgroundSync = window.efaturaBackgroundSync({
        listCard: '#faturaListCard', buttonSelector: '#btnSyncDrafts, #btnDropdownSyncDrafts',
        storageKey: 'efatura_taslak_sync_dismissed_result', listType: 'taslak',
        onRefresh: () => { table.ajax.reload(null, false); loadStats(); }
    });

    $('#btnSyncDrafts, #btnDropdownSyncDrafts').on('click', function() {
        const todayStr = formatDMY(now);
        const thisMonthStartStr = formatDMY(firstDay);
        const thisMonthEndStr = formatDMY(lastDay);

        Swal.fire({
            title: '<i class="bx bx-cloud-download text-primary me-1"></i> EDM Faturalarını Çek',
            html: `
                <div class="text-start font-size-13 text-muted mb-3">
                    EDM portalında bulunan faturaları çekmek istediğiniz tarih aralığını belirleyin:
                </div>
                <div class="text-start mb-3">
                    <label class="form-label font-size-12 fw-bold text-dark">Hızlı Tarih Seçimi:</label>
                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date" data-type="today" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Bugün</button>
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date" data-type="last_7_days" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Son 7 Gün</button>
                        <button type="button" class="btn btn-sm btn-primary text-white shadow-sm quick-sync-date" data-type="this_month" style="color: #ffffff !important; background-color: #135bec !important; border-color: #135bec !important; font-weight: 600;">Bu Ay (${todayStr.substring(3)})</button>
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date" data-type="last_30_days" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Son 30 Gün</button>
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date" data-type="this_year" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Bu Yıl (${now.getFullYear()})</button>
                    </div>
                </div>
                <div class="row g-2 text-start">
                    <div class="col-6">
                        <label class="form-label font-size-12 fw-semibold text-muted mb-1"><i class="bx bx-calendar me-1 text-primary"></i>Başlangıç Tarihi</label>
                        <input type="date" id="swalSyncStartDate" class="form-control form-control-sm border shadow-sm font-size-13 fw-semibold text-dark" value="${formatYMD(firstDay)}" style="border-radius: 6px; padding: 6px 10px;">
                    </div>
                    <div class="col-6">
                        <label class="form-label font-size-12 fw-semibold text-muted mb-1"><i class="bx bx-calendar me-1 text-primary"></i>Bitiş Tarihi</label>
                        <input type="date" id="swalSyncEndDate" class="form-control form-control-sm border shadow-sm font-size-13 fw-semibold text-dark" value="${formatYMD(now)}" style="border-radius: 6px; padding: 6px 10px;">
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-refresh me-1"></i> Faturaları Çek',
            cancelButtonText: 'Vazgeç',
            didOpen: () => {
                $('.quick-sync-date').on('click', function() {
                    $('.quick-sync-date')
                        .removeClass('btn-primary text-white shadow-sm')
                        .addClass('btn-light border')
                        .attr('style', 'color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;');
                    $(this)
                        .removeClass('btn-light border')
                        .addClass('btn-primary text-white shadow-sm')
                        .attr('style', 'color: #ffffff !important; background-color: #135bec !important; border-color: #135bec !important; font-weight: 600;');

                    const t = $(this).data('type');
                    const cur = new Date();
                    if (t === 'today') {
                        $('#swalSyncStartDate').val(formatYMD(cur));
                        $('#swalSyncEndDate').val(formatYMD(cur));
                    } else if (t === 'last_7_days') {
                        const d7 = new Date();
                        d7.setDate(d7.getDate() - 7);
                        $('#swalSyncStartDate').val(formatYMD(d7));
                        $('#swalSyncEndDate').val(formatYMD(cur));
                    } else if (t === 'this_month') {
                        $('#swalSyncStartDate').val(formatYMD(firstDay));
                        $('#swalSyncEndDate').val(formatYMD(cur));
                    } else if (t === 'last_30_days') {
                        const d30 = new Date();
                        d30.setDate(d30.getDate() - 30);
                        $('#swalSyncStartDate').val(formatYMD(d30));
                        $('#swalSyncEndDate').val(formatYMD(cur));
                    } else if (t === 'this_year') {
                        const dYear = new Date(cur.getFullYear(), 0, 1);
                        $('#swalSyncStartDate').val(formatYMD(dYear));
                        $('#swalSyncEndDate').val(formatYMD(cur));
                    }
                });
            },
            preConfirm: () => {
                const s = $('#swalSyncStartDate').val();
                const e = $('#swalSyncEndDate').val();
                if (!s || !e) {
                    Swal.showValidationMessage('Lütfen geçerli bir başlangıç ve bitiş tarihi seçin.');
                    return false;
                }
                return { start_date: s, end_date: e };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                backgroundSync.start(result.value);
            }
        });
    });

    $('#btnHeaderExportExcel').on('click', function() {
        window.location.href = 'api/efatura-api.php?action=export_excel&list_type=taslak';
    });

    $('#btnHeaderPrint').on('click', function() {
        printInvoiceListReport();
    });

    function printInvoiceListReport() {
        const data = table.rows({ filter: 'applied' }).data().toArray();
        const now = new Date();
        const day = String(now.getDate()).padStart(2, '0');
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const year = now.getFullYear();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const formattedDate = `${day}.${month}.${year} ${hours}:${minutes}`;

        let totalAmount = 0;
        let rowsHtml = '';

        data.forEach((row, idx) => {
            let numVal = 0;
            if (row.odenecek_tutar) {
                const cleaned = row.odenecek_tutar.toString().replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
                numVal = parseFloat(cleaned) || 0;
            }
            totalAmount += numVal;

            const cleanFaturaNo = row.fatura_no ? row.fatura_no.replace(/<[^>]*>?/gm, '') : 'Taslak';
            const cleanAlici = row.alici_unvan ? row.alici_unvan.replace(/<[^>]*>?/gm, '') : '-';

            rowsHtml += `
                <tr>
                    <td style="text-align: center; color: #64748b;">${idx + 1}</td>
                    <td style="font-weight: bold; font-family: monospace; color: #0f172a;">${cleanFaturaNo}</td>
                    <td style="color: #475569;">${row.fatura_tarihi || '-'}</td>
                    <td style="font-weight: 500; color: #1e293b;">${cleanAlici}</td>
                    <td style="font-family: monospace; color: #475569;">${row.alici_vkn_tckn || '-'}</td>
                    <td style="text-align: center;">${row.belge_turu === 'EFATURA' ? 'E-Fatura' : 'E-Arşiv'}</td>
                    <td style="text-align: center; color: #64748b;">${row.fatura_profili || 'TICARIFATURA'}</td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0f172a;">${row.odenecek_tutar || '0,00 TRY'}</td>
                    <td style="text-align: center;">Taslak</td>
                </tr>
            `;
        });

        const totalFormatted = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(totalAmount) + ' TRY';

        const printWindow = window.open('', '_blank', 'width=1150,height=800');
        if (!printWindow) {
            window.print();
            return;
        }

        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="UTF-8">
                <title>Taslak Faturalar Listesi - Yazdır</title>
                <style>
                    @page { size: A4 landscape; margin: 10mm 12mm; }
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
                    .header-box { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 14px; }
                    .brand-title { font-size: 16px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
                    .report-subtitle { font-size: 13px; font-weight: 600; color: #475569; margin-top: 3px; }
                    .report-meta { text-align: right; font-size: 11px; color: #64748b; line-height: 1.5; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th { background-color: #f1f5f9; color: #334155; font-size: 10px; font-weight: 700; text-transform: uppercase; border: 1px solid #cbd5e1; padding: 8px 6px; text-align: left; }
                    td { border: 1px solid #e2e8f0; padding: 6px 6px; font-size: 10.5px; vertical-align: middle; }
                    tr:nth-child(even) { background-color: #f8fafc; }
                    .total-row td { background-color: #f1f5f9; font-weight: 800; font-size: 11px; border-top: 2px solid #0f172a; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <div class="header-box">
                    <div>
                        <div class="brand-title">ERSAN ELEKTRİK MÜHENDİSLİK</div>
                        <div class="report-subtitle">TASLAK FATURALAR LİSTESİ</div>
                    </div>
                    <div class="report-meta">
                        <div><strong>Yazdırma Tarihi:</strong> ${formattedDate}</div>
                        <div><strong>Toplam Kayıt:</strong> ${data.length} Adet</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">SIRA</th>
                            <th style="width: 140px;">FATURA NO</th>
                            <th style="width: 85px;">TARİH</th>
                            <th>MÜŞTERİ / ALICI</th>
                            <th style="width: 110px;">VKN / TCKN</th>
                            <th style="width: 80px; text-align: center;">TÜR</th>
                            <th style="width: 80px; text-align: center;">SENARYO</th>
                            <th style="width: 130px; text-align: right;">ÖDENECEK TUTAR</th>
                            <th style="width: 95px; text-align: center;">DURUM</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="9" style="text-align: center; padding: 25px; color: #94a3b8;">Listelenecek taslak fatura bulunamadı.</td></tr>'}
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="7" style="text-align: right; padding-right: 12px;">GENEL TOPLAM:</td>
                            <td style="text-align: right; font-family: monospace; font-size: 11.5px;">${totalFormatted}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <script>
                    window.onload = function() {
                        window.focus();
                        window.print();
                    };
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Hızlı Filtre Butonları (Kartlardaki rozetler)
    $('.status-quick-filter').on('click', function(e) {
        e.preventDefault();
        const belge = $(this).data('belge') || '';
        currentBelgeFilter = belge;
        $('.status-quick-filter').removeClass('active');
        $(`.status-quick-filter[data-belge="${belge}"]`).addClass('active');
        table.ajax.reload();
    });

    // Tekil EDM'ye Gönder Butonu
    $(document).on('click', '.btn-send-edm', function() {
        const invoiceId = $(this).data('id');

        Swal.fire({
            title: 'Fatura Gönderilsin mi?',
            text: 'Taslak fatura EDM Bilişim (GİB) sistemine iletilecektir. Onay sonrası fatura "Giden Faturalar" modülünde listelenecektir.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-send me-1"></i> Evet, Gönder',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Gönderiliyor...',
                    text: 'EDM servisi ile iletişim kuruluyor, lütfen bekleyin.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: {
                        action: 'send_invoice',
                        invoice_id: invoiceId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarıyla Gönderildi!',
                                html: `Fatura başarıyla EDM sistemine iletildi.<br><strong>Fatura No:</strong> ${res.fatura_no || '-'}<br><br><span class="text-muted font-size-12">Fatura artık <strong>Giden Faturalar</strong> ekranında listelenmektedir.</span>`,
                                confirmButtonText: 'Tamam'
                            }).then(() => {
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gönderim Başarısız',
                                text: res.message || 'Fatura gönderilirken bir hata oluştu.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Bağlantı Hatası',
                            text: 'Sunucu ile iletişim kurulurken bir sorun oluştu.'
                        });
                    }
                });
            }
        });
    });

    // Toplu EDM'ye Gönder
    $('#btnBulkSend').on('click', function() {
        const checkedBoxes = $('.row-select-checkbox:checked');
        const ids = [];
        checkedBoxes.each(function() {
            ids.push($(this).data('id'));
        });

        if (ids.length === 0) return;

        Swal.fire({
            title: `${ids.length} Adet Fatura Gönderilsin mi?`,
            text: 'Seçilen tüm taslak faturalar EDM sistemine iletilecek ve Giden Faturalar modülüne aktarılacaktır.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-send me-1"></i> Evet, Toplu Gönder',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Toplu Gönderim Yapılıyor...',
                    text: 'Faturalar işleniyor, lütfen bekleyin.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: {
                        action: 'bulk_send_invoices',
                        invoice_ids: ids
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'İşlem Tamamlandı',
                                text: res.message
                            }).then(() => {
                                $('#checkAll').prop('checked', false);
                                updateSelectedBulkCount();
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Bazı Faturalar Gönderilemedi',
                                html: `${res.message}<br><small class="text-danger">${(res.errors || []).join('<br>')}</small>`
                            }).then(() => {
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('Hata', 'Toplu gönderim sırasında bir hata oluştu.', 'error');
                    }
                });
            }
        });
    });

    // Taslak Sil
    $(document).on('click', '.btn-delete-draft', function() {
        const invoiceId = $(this).data('id');

        Swal.fire({
            title: 'Taslak Faturayı Sil?',
            text: 'Bu taslak fatura kalıcı olarak silinecektir.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: {
                        action: 'delete_draft',
                        invoice_id: invoiceId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: 'Taslak fatura başarıyla silindi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                            loadStats();
                        } else {
                            Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                        }
                    }
                });
            }
        });
    });

    // Tekil GİB Durumunu Sorgula
    $(document).on('click', '.btn-sync-single', function() {
        const invoiceId = $(this).data('id');
        const $btn = $(this);
        $btn.prop('disabled', true).find('i').addClass('bx-spin');

        $.ajax({
            url: 'api/efatura-api.php',
            type: 'POST',
            data: {
                action: 'sync_status',
                invoice_id: invoiceId
            },
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).find('i').removeClass('bx-spin');
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'info',
                        title: 'GİB Durumu',
                        text: res.data.aciklama || 'Durum güncellendi.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    table.ajax.reload(null, false);
                    loadStats();
                } else {
                    Swal.fire('Hata', res.message || 'Durum sorgulanamadı.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).find('i').removeClass('bx-spin');
                Swal.fire('Hata', 'GİB durum sorgulama sırasında hata oluştu.', 'error');
            }
        });
    });

    // HTML Fatura Önizleme
    $(document).on('click', '.btn-preview', function() {
        const invoiceId = $(this).data('id');
        currentPreviewInvoiceId = invoiceId;

        $('#invoicePreviewContainer').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2 text-muted">Fatura yükleniyor...</div>
            </div>
        `);
        $('#modalInvoicePreview').modal('show');

        $.ajax({
            url: 'api/efatura-api.php',
            type: 'GET',
            data: {
                action: 'preview_html',
                invoice_id: invoiceId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.html) {
                    $('#invoicePreviewContainer').html(res.html);
                } else {
                    $('#invoicePreviewContainer').html(`
                        <div class="alert alert-danger m-3">
                            <i class="bx bx-error me-1"></i> Fatura önizlemesi yüklenemedi: ${res.message || 'Bilinmeyen hata'}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#invoicePreviewContainer').html(`
                    <div class="alert alert-danger m-3">
                        <i class="bx bx-error me-1"></i> Sunucu hatası. Önizleme alınamadı.
                    </div>
                `);
            }
        });
    });

    // Modaldan EDM'ye Gönder
    $('#btnModalSendEdm').on('click', function() {
        if (!currentPreviewInvoiceId) return;
        $('#modalInvoicePreview').modal('hide');
        $(`.btn-send-edm[data-id="${currentPreviewInvoiceId}"]`).trigger('click');
    });

    // Önizleme Yazdır
    $('#btnPrintPreview').on('click', function() {
        const printContent = document.getElementById('invoicePreviewContainer').innerHTML;
        const win = window.open('', '_blank');
        if (!win) {
            Swal.fire('Uyarı', 'Pop-up engelleyici yazdırma sayfasını engelledi.', 'warning');
            return;
        }
        win.document.open();
        win.document.write(`
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="utf-8">
                <title>Taslak Fatura Yazdır</title>
                <style>
                    @page { size: A4 portrait; margin: 6mm 10mm; }
                    body { margin: 0; padding: 0; background: #fff; font-family: Arial, sans-serif; }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
            </html>
        `);
        win.document.close();
        win.focus();
        setTimeout(() => {
            win.print();
        }, 400);
    });

    // Doğrudan Tablodaki "Tahsilat Ekle" Linkine Tıklanınca
    $(document).on('click', '.btn-tahsilat-ekle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data('id');
        if (id) {
            window.efaturaOpenTahsilatModal(id, () => {
                table.ajax.reload(null, false);
                loadStats();
            });
        }
    });
});
