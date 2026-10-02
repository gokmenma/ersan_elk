/**
 * Gelen Faturalar Modülü JavaScript Yöneticisi
 * Ersan Elektrik - E-Fatura & E-Arşiv Yönetimi
 */

$(document).ready(function() {
    let currentYanitFilter = '';

    // 1. Özet Kartları Açma/Kapatma Mantığı (AGENTS.md)
    const toggleBtn = document.getElementById('btnToggleSummaryCards');
    const container = document.getElementById('summaryCardsContainer');
    const storageKey = 'efatura_gelen_summary_cards_state';

    if (toggleBtn && container) {
        const savedState = localStorage.getItem(storageKey);
        const icon = toggleBtn.querySelector('i');
        if (savedState === 'hidden') {
            container.style.display = 'none';
            toggleBtn.setAttribute('aria-expanded', 'false');
            if (icon) icon.className = 'bx bx-chevron-down font-size-18';
        }

        toggleBtn.addEventListener('click', function() {
            const currentIcon = toggleBtn.querySelector('i');
            if (container.style.display === 'none') {
                container.style.display = 'flex';
                toggleBtn.setAttribute('aria-expanded', 'true');
                if (currentIcon) currentIcon.className = 'bx bx-chevron-up font-size-18';
                localStorage.setItem(storageKey, 'visible');
            } else {
                container.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
                if (currentIcon) currentIcon.className = 'bx bx-chevron-down font-size-18';
                localStorage.setItem(storageKey, 'hidden');
            }
        });
    }

    // 2. İstatistikleri Yükle
    function loadStats() {
        fetch('api/efatura-api.php?action=summary_stats&list_type=gelen')
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
                    let btns = `<div class="d-flex align-items-center justify-content-center gap-1">`;
                    
                    btns += `<button type="button" class="btn btn-light border text-primary table-action-btn btn-preview" data-id="${row.encrypted_id}" title="Önizle / İncele"><i class="bx bx-show font-size-15"></i></button>`;

                    if (row.fatura_profili === 'TICARIFATURA' && row.ticari_yanit === 'BEKLIYOR') {
                        btns += `<button type="button" class="btn btn-success text-white table-action-btn btn-respond" data-id="${row.encrypted_id}" data-type="KABUL" title="Kabul Et"><i class="bx bx-check font-size-14"></i></button>`;
                        btns += `<button type="button" class="btn btn-danger text-white table-action-btn btn-respond" data-id="${row.encrypted_id}" data-type="RED" title="Reddet"><i class="bx bx-x font-size-14"></i></button>`;
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
    const table = $('#tblGelenFaturalar').DataTable(finalOptions);

    // Tablo yüklendiğinde ve çizildiğinde toplam kayıt sayacını güncelle
    table.on('xhr.dt', function(e, settings, json) {
        if (json) {
            const total = json.recordsTotal !== undefined ? json.recordsTotal : (json.data ? json.data.length : 0);
            $('#badgeTotalRecords').text(total);
        }
    });

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
                            <th style="width: 40px; text-align: center;">SIRA</th>
                            <th style="width: 140px;">FATURA NO</th>
                            <th style="width: 85px;">TARİH</th>
                            <th>GÖNDERİCİ / TEDARİKÇİ</th>
                            <th style="width: 110px;">VKN / TCKN</th>
                            <th style="width: 80px; text-align: center;">TÜR</th>
                            <th style="width: 80px; text-align: center;">SENARYO</th>
                            <th style="width: 130px; text-align: right;">ÖDENECEK TUTAR</th>
                            <th style="width: 95px; text-align: center;">DURUM / YANIT</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="9" style="text-align: center; padding: 25px; color: #94a3b8;">Listelenecek gelen fatura bulunamadı.</td></tr>'}
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
        currentYanitFilter = $(this).data('yanit') || '';
        table.ajax.reload();
    });

    // EDM'den Yeni Faturaları Çek
    $('#btnSyncIncoming').on('click', function() {
        Swal.fire({
            title: 'Gelen Faturalar Çekilsin mi?',
            text: 'EDM Bilişim e-Fatura gelen kutusu taranacak ve firmanıza kesilen yeni faturalar sisteme aktarılacaktır.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-refresh me-1"></i> Evet, Çek',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Faturalar Taranıyor...',
                    text: 'EDM servisi ile senkronizasyon yapılıyor, lütfen bekleyin.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: { action: 'sync_incoming_invoices' },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Tamamlandı',
                                text: res.message || 'Gelen faturalar başarıyla güncellendi.'
                            }).then(() => {
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Bilgi',
                                text: res.message || 'Yeni gelen fatura bulunamadı veya işlem tamamlandı.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire('Hata', 'Gelen faturalar çekilirken bağlantı hatası oluştu.', 'error');
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
