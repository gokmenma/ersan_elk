/**
 * Gelen Faturalar Modülü JavaScript Yöneticisi
 * Ersan Elektrik - E-Fatura & E-Arşiv Yönetimi
 */

$(document).ready(function() {
    let currentYanitFilter = '';
    let currentStartDate = '';
    let currentEndDate = '';

    // Varsayılan: İçinde Bulunulan Ayın İlk ve Son Günü
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const formatYMD = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const formatDMY = (d) => `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}`;

    currentStartDate = formatYMD(firstDay);
    currentEndDate = formatYMD(lastDay);

    window.efaturaSetupSummary('efatura_gelen_summary_cards_state');

    // Başlangıç Tarihi Seçici Başlat
    if (typeof window.initFlatpickrWithYearSelect === 'function' && document.getElementById('filterStartDate')) {
        window.initFlatpickrWithYearSelect('#filterStartDate', {
            defaultDate: firstDay,
            onChange: function(selectedDates) {
                if (selectedDates.length > 0) {
                    currentStartDate = formatYMD(selectedDates[0]);
                    $('#btnClearStartDate').show();
                } else {
                    currentStartDate = '';
                    $('#btnClearStartDate').hide();
                }
                table.ajax.reload();
                loadStats();
            }
        });
    }

    // Bitiş Tarihi Seçici Başlat
    if (typeof window.initFlatpickrWithYearSelect === 'function' && document.getElementById('filterEndDate')) {
        window.initFlatpickrWithYearSelect('#filterEndDate', {
            defaultDate: lastDay,
            onChange: function(selectedDates) {
                if (selectedDates.length > 0) {
                    currentEndDate = formatYMD(selectedDates[0]);
                    $('#btnClearEndDate').show();
                } else {
                    currentEndDate = '';
                    $('#btnClearEndDate').hide();
                }
                table.ajax.reload();
                loadStats();
            }
        });
    }

    // Başlangıç Tarihi Temizle Butonu
    $('#btnClearStartDate').on('click', function(e) {
        e.stopPropagation();
        currentStartDate = '';
        if (document.getElementById('filterStartDate') && document.getElementById('filterStartDate')._flatpickr) {
            document.getElementById('filterStartDate')._flatpickr.clear();
        }
        $('#filterStartDate').val('');
        $(this).hide();
        table.ajax.reload();
        loadStats();
    });

    // Bitiş Tarihi Temizle Butonu
    $('#btnClearEndDate').on('click', function(e) {
        e.stopPropagation();
        currentEndDate = '';
        if (document.getElementById('filterEndDate') && document.getElementById('filterEndDate')._flatpickr) {
            document.getElementById('filterEndDate')._flatpickr.clear();
        }
        $('#filterEndDate').val('');
        $(this).hide();
        table.ajax.reload();
        loadStats();
    });

    // Ürün / Marka / Kalem Arama Dinleyicisi
    let gelenProductSearchTimer = null;
    $('#filterProductSearch').on('input keyup', function() {
        const val = $(this).val().trim();
        if (val.length > 0) {
            $('#btnClearProductSearch').show();
        } else {
            $('#btnClearProductSearch').hide();
        }
        clearTimeout(gelenProductSearchTimer);
        gelenProductSearchTimer = setTimeout(function() {
            table.ajax.reload();
        }, 350);
    });

    $('#btnClearProductSearch').on('click', function(e) {
        e.stopPropagation();
        $('#filterProductSearch').val('');
        $(this).hide();
        table.ajax.reload();
    });

    // 2. İstatistikleri Yükle
    function loadStats() {
        let url = 'api/efatura-api.php?action=summary_stats&list_type=gelen';
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
                    if (document.getElementById('stat_bu_ay_adet_text')) {
                        document.getElementById('stat_bu_ay_adet_text').textContent = `Bu Ay: ${d.bu_ay_adet || 0} Adet`;
                    }
                    if (document.getElementById('stat_kabul_adet')) {
                        document.getElementById('stat_kabul_adet').textContent = d.kabul_adet || 0;
                    }
                    if (document.getElementById('stat_bekleyen_adet')) {
                        document.getElementById('stat_bekleyen_adet').textContent = d.bekleyen_adet || 0;
                    }
                    if (document.getElementById('stat_toplam_tutar')) {
                        document.getElementById('stat_toplam_tutar').textContent = formatMoney(d.toplam_tutar);
                    }
                    if (document.getElementById('stat_bu_ay_tutar')) {
                        document.getElementById('stat_bu_ay_tutar').textContent = formatMoney(d.bu_ay_tutar);
                    }
                }
            })
            .catch(err => console.error("Gelen summary stats error:", err));
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
            url: 'api/efatura-api.php?action=list_invoices&list_type=gelen',
            type: 'GET',
            data: function(d) {
                if (currentYanitFilter) {
                    d.durum_filtre = currentYanitFilter;
                }
                if (currentStartDate) {
                    d.baslangic_tarihi = currentStartDate;
                }
                if (currentEndDate) {
                    d.bitis_tarihi = currentEndDate;
                }
                const prodSearch = $('#filterProductSearch').val();
                if (prodSearch && prodSearch.trim()) {
                    d.urun_ara = prodSearch.trim();
                }
            },
            dataSrc: function(json) {
                return (json && Array.isArray(json.data)) ? json.data : [];
            },
            error: function(xhr, error, thrown) {
                console.error("Gelen faturalar AJAX yükleme hatası:", error, thrown);
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
                    const fNo = (!data || data === 'Taslak') ? '-' : data;
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
                render: function(data, type, row) {
                    const unvan = data || '-';
                    let itemsHtml = '';
                    if (row.kalemler_ozet && row.kalemler_ozet.trim() !== '') {
                        const countBadge = (row.kalem_sayisi && row.kalem_sayisi > 1) 
                            ? `<span class="badge bg-light text-primary border me-1 font-size-10 px-1 py-0.5 flex-shrink-0">${row.kalem_sayisi} Kalem</span>` 
                            : '';
                        itemsHtml = `
                        <div class="d-flex align-items-center gap-1 mt-0.5 text-muted font-size-11" title="Kalemler: ${row.kalemler_ozet}">
                            <i class="bx bx-package text-primary font-size-12 flex-shrink-0"></i>
                            ${countBadge}
                            <span class="text-truncate" style="max-width: 260px;">${row.kalemler_ozet}</span>
                        </div>`;
                    }
                    return `
                    <div class="d-flex flex-column">
                        <div class="fw-semibold text-dark text-truncate" style="max-width: 280px;" title="${unvan}">${unvan}</div>
                        ${itemsHtml}
                    </div>`;
                }
            },
            {
                data: 'alici_vkn_tckn',
                className: 'align-middle text-muted font-monospace'
            },
            {
                data: 'belge_turu',
                className: 'align-middle text-center',
                render: function() {
                    return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-buildings me-1"></i>E-Fatura</span>';
                }
            },
            {
                data: 'fatura_profili',
                className: 'align-middle text-muted',
                render: function(data) {
                    const isTicari = (data === 'TICARIFATURA');
                    const badgeClass = isTicari ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-light text-secondary border';
                    return `<span class="badge ${badgeClass} px-2 py-1 font-size-11">${data || 'TEMELFATURA'}</span>`;
                }
            },
            {
                data: 'odenecek_tutar',
                className: 'align-middle text-end fw-bold text-dark font-monospace'
            },
            {
                data: 'ticari_yanit',
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    if (row.fatura_profili !== 'TICARIFATURA') {
                        return '<span class="badge bg-light text-muted border px-2 py-1 font-size-11">Temel Fatura</span>';
                    }
                    if (data === 'KABUL') {
                        return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-size-11"><i class="bx bx-check me-1"></i>Kabul Edildi</span>';
                    } else if (data === 'RED') {
                        return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 font-size-11"><i class="bx bx-x me-1"></i>Reddedildi</span>';
                    }
                    return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 font-size-11"><i class="bx bx-time-five me-1"></i>Yanıt Bekliyor</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    let btns = `<div class="action-btn-group justify-content-center">`;
                    
                    // 1. Önizle
                    btns += `<button type="button" class="btn btn-sm btn-subtle-primary table-action-btn btn-preview" data-id="${row.encrypted_id}" title="Önizle / İncele"><i class="bx bx-show font-size-15"></i></button>`;

                    // 2. PDF İndir
                    btns += `<a href="api/efatura-api.php?action=download_pdf&invoice_id=${row.encrypted_id}" class="btn btn-sm btn-subtle-danger table-action-btn" title="PDF İndir" target="_blank"><i class="bx bxs-file-pdf font-size-15"></i></a>`;

                    // 3. Ticari Kabul / Red Butonları
                    if (row.fatura_profili === 'TICARIFATURA' && row.ticari_yanit === 'BEKLIYOR') {
                        btns += `<button type="button" class="btn btn-sm btn-subtle-success table-action-btn btn-respond" data-id="${row.encrypted_id}" data-type="KABUL" title="Kabul Et"><i class="bx bx-check font-size-15"></i></button>`;
                        btns += `<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-respond" data-id="${row.encrypted_id}" data-type="RED" title="Reddet"><i class="bx bx-x font-size-15"></i></button>`;
                    }

                    // 4. Tarihçe
                    btns += `<button type="button" class="btn btn-sm btn-subtle-info table-action-btn btn-history efatura-history" data-id="${row.encrypted_id}" title="Geçmiş / Loglar"><i class="bx bx-history font-size-15"></i></button>`;

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
    const table = $('#tblGelenFaturalar').DataTable(finalOptions);

    // Check All Kutusu
    $('#checkAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.row-select-checkbox').prop('checked', isChecked);
    });

    // Satır Seçimi (Tıklama ile)
    $('#tblGelenFaturalar tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('a, button, input, .dropdown-menu').length) return;
        $('#tblGelenFaturalar tbody tr').removeClass('selected');
        $(this).addClass('selected');
    });

    // Header Butonları
    $('#btnHeaderRefresh').on('click', function() {
        table.ajax.reload(null, false);
        loadStats();
    });

    $('#btnHeaderExportExcel').on('click', function() {
        window.location.href = 'api/efatura-api.php?action=export_excel&list_type=gelen';
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

            const cleanFaturaNo = row.fatura_no ? row.fatura_no.replace(/<[^>]*>?/gm, '') : '-';
            const cleanAlici = row.alici_unvan ? row.alici_unvan.replace(/<[^>]*>?/gm, '') : '-';
            const cleanKalemler = row.kalemler_ozet ? row.kalemler_ozet.replace(/<[^>]*>?/gm, '') : '-';
            let yanitLabel = row.ticari_yanit || 'BEKLIYOR';
            if (row.fatura_profili !== 'TICARIFATURA') yanitLabel = 'Temel';
            else if (yanitLabel === 'KABUL') yanitLabel = 'Kabul';
            else if (yanitLabel === 'RED') yanitLabel = 'Red';
            else yanitLabel = 'Bekliyor';

            rowsHtml += `
                <tr>
                    <td style="text-align: center; color: #64748b;">${idx + 1}</td>
                    <td style="font-weight: bold; font-family: monospace; color: #0f172a;">${cleanFaturaNo}</td>
                    <td style="color: #475569;">${row.fatura_tarihi || '-'}</td>
                    <td style="font-weight: 500; color: #1e293b;">${cleanAlici}</td>
                    <td style="font-family: monospace; color: #475569;">${row.alici_vkn_tckn || '-'}</td>
                    <td style="text-align: center;">E-Fatura</td>
                    <td style="text-align: center; color: #64748b;">${row.fatura_profili || 'TEMEL'}</td>
                    <td style="color: #334155; font-size: 10px;">${cleanKalemler}</td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0f172a;">${row.odenecek_tutar || '0,00 TRY'}</td>
                    <td style="text-align: center;">${yanitLabel}</td>
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
                <title>Gelen Faturalar Listesi - Yazdır</title>
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
                        <div class="report-subtitle">GELEN E-FATURA LİSTESİ</div>
                    </div>
                    <div class="report-meta">
                        <div><strong>Yazdırma Tarihi:</strong> ${formattedDate}</div>
                        <div><strong>Toplam Kayıt:</strong> ${data.length} Adet</div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">SIRA</th>
                            <th style="width: 130px;">FATURA NO</th>
                            <th style="width: 80px;">TARİH</th>
                            <th>GÖNDERİCİ / TEDARİKÇİ</th>
                            <th style="width: 100px;">VKN / TCKN</th>
                            <th style="width: 75px; text-align: center;">TÜR</th>
                            <th style="width: 75px; text-align: center;">SENARYO</th>
                            <th style="width: 200px;">FATURA İÇERİĞİ / KALEMLER</th>
                            <th style="width: 120px; text-align: right;">ÖDENECEK TUTAR</th>
                            <th style="width: 90px; text-align: center;">DURUM / YANIT</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="10" style="text-align: center; padding: 25px; color: #94a3b8;">Listelenecek gelen fatura bulunamadı.</td></tr>'}
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="8" style="text-align: right; padding-right: 12px;">GENEL TOPLAM:</td>
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
        currentYanitFilter = $(this).data('yanit') || '';
        table.ajax.reload();
    });

    // EDM'den Yeni Faturaları Çek (Tarih Aralığı Seçimli)
    $('#btnSyncIncoming').on('click', function() {
        const todayStr = formatDMY(now);
        Swal.fire({
            title: 'EDM Gelen Faturaları Çek',
            html: `
                <div class="mb-3 text-start">
                    <label class="form-label font-size-12 fw-semibold text-muted">Hızlı Tarih Seçimi:</label>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date-gelen" data-type="today" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Bugün</button>
                        <button type="button" class="btn btn-sm btn-primary text-white shadow-sm quick-sync-date-gelen" data-type="this_month" style="color: #ffffff !important; background-color: #135bec !important; border-color: #135bec !important; font-weight: 600;">Bu Ay (${todayStr.substring(3)})</button>
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date-gelen" data-type="last_7_days" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Son 7 Gün</button>
                        <button type="button" class="btn btn-sm btn-light border quick-sync-date-gelen" data-type="last_30_days" style="color: #334155 !important; background-color: #f8fafc !important; border-color: #cbd5e1 !important; font-weight: 500;">Son 30 Gün</button>
                    </div>
                </div>
                <div class="row g-2 text-start mb-3">
                    <div class="col-6">
                        <label class="form-label font-size-12 fw-semibold text-muted mb-1"><i class="bx bx-calendar me-1 text-primary"></i>Başlangıç Tarihi</label>
                        <input type="date" id="swalSyncStartDateGelen" class="form-control form-control-sm border shadow-sm font-size-13 fw-semibold text-dark" value="${formatYMD(firstDay)}" style="border-radius: 6px; padding: 6px 10px;">
                    </div>
                    <div class="col-6">
                        <label class="form-label font-size-12 fw-semibold text-muted mb-1"><i class="bx bx-calendar me-1 text-primary"></i>Bitiş Tarihi</label>
                        <input type="date" id="swalSyncEndDateGelen" class="form-control form-control-sm border shadow-sm font-size-13 fw-semibold text-dark" value="${formatYMD(now)}" style="border-radius: 6px; padding: 6px 10px;">
                    </div>
                </div>
                <div class="text-start">
                    <label class="form-label font-size-12 fw-semibold text-muted mb-1"><i class="bx bx-filter-alt me-1 text-primary"></i>Tarih Filtreleme Türü:</label>
                    <select id="swalSyncDateTypeGelen" class="form-select form-select-sm border shadow-sm font-size-12 fw-semibold text-dark" style="border-radius: 6px; padding: 6px 10px;">
                        <option value="ISSUE" selected>Fatura Düzenleme Tarihine Göre (Önerilen)</option>
                        <option value="CREATE">EDM Sistemine Yüklenme / Geliş Tarihine Göre</option>
                    </select>
                    <small class="text-muted font-size-11 d-block mt-1">Yalnızca seçtiğiniz tarihte düzenlenen faturaları çekmek için 'Fatura Düzenleme Tarihine Göre' seçeneğini kullanın.</small>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-refresh me-1"></i> Faturaları Çek',
            cancelButtonText: 'Vazgeç',
            didOpen: () => {
                $('.quick-sync-date-gelen').on('click', function() {
                    $('.quick-sync-date-gelen')
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
                        $('#swalSyncStartDateGelen').val(formatYMD(cur));
                        $('#swalSyncEndDateGelen').val(formatYMD(cur));
                    } else if (t === 'this_month') {
                        $('#swalSyncStartDateGelen').val(formatYMD(firstDay));
                        $('#swalSyncEndDateGelen').val(formatYMD(cur));
                    } else if (t === 'last_7_days') {
                        const d7 = new Date();
                        d7.setDate(d7.getDate() - 7);
                        $('#swalSyncStartDateGelen').val(formatYMD(d7));
                        $('#swalSyncEndDateGelen').val(formatYMD(cur));
                    } else if (t === 'last_30_days') {
                        const d30 = new Date();
                        d30.setDate(d30.getDate() - 30);
                        $('#swalSyncStartDateGelen').val(formatYMD(d30));
                        $('#swalSyncEndDateGelen').val(formatYMD(cur));
                    }
                });
            },
            preConfirm: () => {
                const s = $('#swalSyncStartDateGelen').val();
                const e = $('#swalSyncEndDateGelen').val();
                const dt = $('#swalSyncDateTypeGelen').val() || 'ISSUE';
                if (!s || !e) {
                    Swal.showValidationMessage('Lütfen geçerli bir başlangıç ve bitiş tarihi seçin.');
                    return false;
                }
                return { start_date: s, end_date: e, date_type: dt };
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const syncRange = result.value;
                Swal.fire({
                    title: 'Faturalar Taranıyor...',
                    text: `${syncRange.start_date} ile ${syncRange.end_date} arasındaki gelen faturalar taranıyor, lütfen bekleyin.`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: {
                        action: 'sync_incoming_invoices',
                        start_date: syncRange.start_date,
                        end_date: syncRange.end_date,
                        date_type: syncRange.date_type
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Tamamlandı',
                                text: res.message || 'Gelen faturalar başarıyla güncellendi.'
                            }).then(() => {
                                currentStartDate = syncRange.start_date;
                                currentEndDate = syncRange.end_date;
                                if (document.getElementById('filterStartDate') && document.getElementById('filterStartDate')._flatpickr) {
                                    const sParts = syncRange.start_date.split('-');
                                    const sDate = new Date(parseInt(sParts[0], 10), parseInt(sParts[1], 10) - 1, parseInt(sParts[2], 10));
                                    document.getElementById('filterStartDate')._flatpickr.setDate(sDate, false);
                                    $('#btnClearStartDate').show();
                                }
                                if (document.getElementById('filterEndDate') && document.getElementById('filterEndDate')._flatpickr) {
                                    const eParts = syncRange.end_date.split('-');
                                    const eDate = new Date(parseInt(eParts[0], 10), parseInt(eParts[1], 10) - 1, parseInt(eParts[2], 10));
                                    document.getElementById('filterEndDate')._flatpickr.setDate(eDate, false);
                                    $('#btnClearEndDate').show();
                                }
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Bilgi',
                                text: res.message || 'Seçilen tarih aralığında yeni gelen fatura bulunamadı.'
                            });
                        }
                    },
                    error: function(xhr) {
                        const errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gelen faturalar çekilirken bağlantı hatası oluştu.';
                        Swal.fire('Bilgi', errMsg, 'warning');
                    }
                });
            }
        });
    });

    // Ticari Fatura Kabul / Red Yanıtı
    $(document).on('click', '.btn-respond', function() {
        const invoiceId = $(this).data('id');
        const responseType = $(this).data('type'); // KABUL / RED
        const isAccept = (responseType === 'KABUL');

        if (isAccept) {
            Swal.fire({
                title: 'Faturayı Kabul Et?',
                text: 'Bu ticari faturayı kabul ettiğinize dair EDM/GİB sistemine onay bildirimi gönderilecektir.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Evet, Kabul Et',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendResponse(invoiceId, 'KABUL', '');
                }
            });
        } else {
            Swal.fire({
                title: 'Faturayı Reddet?',
                text: 'Lütfen fatura red gerekçenizi yazınız:',
                input: 'textarea',
                inputPlaceholder: 'Red gerekçesi (Örn: Hatalı fiyat veya miktar bildirimi)...',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Evet, Reddet',
                cancelButtonText: 'Vazgeç',
                inputValidator: (value) => {
                    if (!value || value.trim().length === 0) {
                        return 'Lütfen bir red gerekçesi belirtin!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sendResponse(invoiceId, 'RED', result.value);
                }
            });
        }
    });

    function sendResponse(invoiceId, responseType, reason) {
        Swal.fire({
            title: 'İşleniyor...',
            text: 'Ticari fatura yanıtı EDM sistemine iletiliyor.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api/efatura-api.php',
            type: 'POST',
            data: {
                action: 'respond_commercial',
                invoice_id: invoiceId,
                response_type: responseType,
                reason: reason
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message || 'Ticari yanıt başarıyla iletildi.'
                    }).then(() => {
                        table.ajax.reload(null, false);
                        loadStats();
                    });
                } else {
                    Swal.fire('Hata', res.message || 'Ticari yanıt iletilemedi.', 'error');
                }
            },
            error: function() {
                Swal.fire('Hata', 'Sunucu ile iletişim kurulurken bir sorun oluştu.', 'error');
            }
        });
    }

    // HTML Fatura Önizleme
    $(document).on('click', '.btn-preview', function() {
        const invoiceId = $(this).data('id');

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
                <title>Gelen Fatura Yazdır</title>
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
});
