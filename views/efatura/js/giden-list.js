$(document).ready(function() {
    let currentStatusFilter = '';
    let selectedRowData = null;

    // 1. Özet Kartları Açma/Kapatma Mantığı (AGENTS.md)
    const toggleBtn = document.getElementById('btnToggleSummaryCards');
    const container = document.getElementById('summaryCardsContainer');
    const storageKey = 'efatura_summary_cards_state';

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
        fetch('api/efatura-api.php?action=summary_stats&list_type=giden')
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
                    if (document.getElementById('stat_onaylanan_adet')) {
                        document.getElementById('stat_onaylanan_adet').textContent = d.onaylanan_adet || 0;
                    }
                    if (document.getElementById('stat_onaylanan_tutar')) {
                        document.getElementById('stat_onaylanan_tutar').textContent = formatMoney(d.onaylanan_tutar);
                    }
                    if (document.getElementById('stat_bekleyen_adet')) {
                        document.getElementById('stat_bekleyen_adet').textContent = d.bekleyen_adet || 0;
                    }
                    if (document.getElementById('stat_bekleyen_tutar')) {
                        document.getElementById('stat_bekleyen_tutar').textContent = formatMoney(d.bekleyen_tutar);
                    }
                    if (document.getElementById('stat_bu_ay_adet')) {
                        document.getElementById('stat_bu_ay_adet').textContent = d.bu_ay_adet || 0;
                    }
                    if (document.getElementById('stat_bu_ay_tutar')) {
                        document.getElementById('stat_bu_ay_tutar').textContent = formatMoney(d.bu_ay_tutar);
                    }
                }
            })
            .catch(err => console.error("Summary stats error:", err));
    }
    loadStats();

    // 3. DataTables Tablosunu Başlat
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
            url: 'api/efatura-api.php?action=list_giden&list_type=giden',
            type: 'GET',
            data: function(d) {
                if (currentStatusFilter) {
                    d.durum_filtre = currentStatusFilter;
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
                    if (!data || data === 'Taslak' || data === '<span class="text-muted fst-italic">Taslak</span>') {
                        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 font-size-11">Taslak</span>';
                    }
                    return `<a href="javascript:void(0)" class="text-primary fw-bold btn-onizle text-decoration-none" data-id="${row.encrypted_id}"><i class="bx bx-file me-1"></i>${data}</a>`;
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
                        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-size-11 fw-semibold">E-Fatura</span>';
                    }
                    return '<span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1 font-size-11 fw-semibold">E-Arşiv</span>';
                }
            },
            {
                data: 'fatura_profili',
                className: 'align-middle text-muted',
                render: function(data, type, row) {
                    return `<span class="badge bg-light text-secondary border px-2 py-1 font-size-11">${data || 'TEMEL'}</span>`;
                }
            },
            {
                data: 'odenecek_tutar',
                className: 'align-middle text-end fw-bold text-dark font-monospace'
            },
            {
                data: 'entegrator_durum_kodu',
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    if (data === 'ONAYLANDI') {
                        return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-size-11"><i class="bx bx-check-double me-1"></i>GİB Onaylı</span>';
                    } else if (data === 'GONDERILDI') {
                        return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-size-11"><i class="bx bx-send me-1"></i>İletildi</span>';
                    } else if (data === 'TASLAK') {
                        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 font-size-11"><i class="bx bx-edit me-1"></i>Taslak</span>';
                    } else if (data === 'HATALI') {
                        return `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 font-size-11" title="${row.gib_durum_aciklamasi || ''}"><i class="bx bx-error me-1"></i>Hatalı</span>`;
                    } else if (data === 'IPTAL') {
                        return '<span class="badge bg-dark-subtle text-dark border border-dark-subtle px-2 py-1 font-size-11"><i class="bx bx-x me-1"></i>İptal</span>';
                    }
                    return `<span class="badge bg-light text-muted border px-2 py-1 font-size-11">${data}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'align-middle text-center',
                render: function(data, type, row) {
                    let btns = `<div class="d-flex align-items-center justify-content-center gap-1">`;
                    
                    // 1. Düzenle Butonu (Sadece Taslak ise)
                    if (row.entegrator_durum_kodu === 'TASLAK') {
                        btns += `<a href="index.php?p=efatura/olustur&id=${encodeURIComponent(row.encrypted_id)}" class="btn btn-light border text-warning table-action-btn" title="Taslak Faturayı Düzenle"><i class="bx bx-edit font-size-15"></i></a>`;
                    }
                    
                    // 2. GİB'e Gönder (Taslak ise) veya GİB Durumunu Sorgula (Gönderildi ise)
                    if (row.entegrator_durum_kodu === 'TASLAK') {
                        btns += `<button type="button" class="btn btn-primary text-white table-action-btn btn-gonder" data-id="${row.encrypted_id}" title="EDM / GİB'e Gönder"><i class="bx bx-send font-size-14"></i></button>`;
                    } else if (row.entegrator_durum_kodu === 'GONDERILDI') {
                        btns += `<button type="button" class="btn btn-warning text-white table-action-btn btn-senkronize" data-id="${row.encrypted_id}" title="GİB Durumu Sorgula"><i class="bx bx-refresh font-size-14"></i></button>`;
                    }
                    
                    // 3. Diğer İşlemler Açılır Menü Butonu (3 Nokta)
                    btns += `<button type="button" class="btn btn-light border text-dark table-action-btn btn-row-menu" title="Diğer İşlemler"><i class="bx bx-dots-vertical-rounded font-size-15"></i></button>`;
                    
                    btns += `</div>`;
                    return btns;
                }
            }
        ],
        pageLength: 25,
        createdRow: function(row, data, dataIndex) {
            $(row).attr('data-id', data.encrypted_id);
            $(row).data('row-info', data);
        }
    };

    const finalOptions = typeof applyLengthStateSave === 'function' ? applyLengthStateSave(tableOptions) : tableOptions;
    const table = $('#tblFaturalar').DataTable(finalOptions);

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
    $('#tblFaturalar tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('a, button, input, .dropdown-menu').length) return;
        $('#tblFaturalar tbody tr').removeClass('selected');
        $(this).addClass('selected');
    });

    // 4. Hızlı Durum Filtreleme Rozetleri (Kartlardaki Tümü, Onaylı, İletilen)
    $('.status-quick-filter').on('click', function(e) {
        e.preventDefault();
        const status = $(this).data('status');
        currentStatusFilter = status;
        table.ajax.reload();
    });

    // Header Dışa Aktarma / Yazdırma / Yenileme Butonları
    $('#btnHeaderExportExcel, #exportExcel').on('click', function() {
        window.location.href = 'api/efatura-api.php?action=export_excel';
    });

    $('#btnHeaderRefresh').on('click', function() {
        table.ajax.reload(null, false);
        loadStats();
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

            let durumLabel = row.entegrator_durum_kodu || 'TASLAK';
            if (durumLabel === 'ONAYLANDI') durumLabel = 'GİB Onaylı';
            else if (durumLabel === 'GONDERILDI') durumLabel = 'İletildi';
            else if (durumLabel === 'TASLAK') durumLabel = 'Taslak';
            else if (durumLabel === 'IPTAL') durumLabel = 'İptal';
            else if (durumLabel === 'HATALI') durumLabel = 'Hatalı';

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
                    <td style="text-align: center; color: #64748b;">${row.fatura_profili || 'TEMEL'}</td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace; color: #0f172a;">${row.odenecek_tutar || '0,00 TRY'}</td>
                    <td style="text-align: center;">${durumLabel}</td>
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
                <title>Giden Faturalar Listesi - Yazdır</title>
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
                        <div class="report-subtitle">GİDEN E-FATURA & E-ARŞİV LİSTESİ</div>
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
                        ${rowsHtml || '<tr><td colspan="9" style="text-align: center; padding: 25px; color: #94a3b8;">Listelenecek fatura kaydı bulunamadı.</td></tr>'}
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

    // 5. Önizleme Modalı
    $(document).on('click', '.btn-onizle', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        openInvoicePreview(id);
    });

    function openInvoicePreview(id, autoPrint = false) {
        const modalEl = document.getElementById('modalFaturaOnizleme');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        $('#onizlemeModalContent').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted">Fatura yükleniyor...</div></div>');

        fetch(`api/efatura-api.php?action=preview_html&invoice_id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    $('#onizlemeModalContent').html(res.html);
                    if (autoPrint) {
                        setTimeout(() => {
                            printInvoiceHtml(res.html);
                        }, 300);
                    }
                } else {
                    $('#onizlemeModalContent').html(`<div class="alert alert-danger m-3">${res.message}</div>`);
                }
            })
            .catch(err => {
                $('#onizlemeModalContent').html(`<div class="alert alert-danger m-3">Önizleme yüklenirken hata oluştu.</div>`);
            });
    }

    // 6. EDM / GİB Gönderim Butonu
    $(document).on('click', '.btn-gonder', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        sendInvoiceToGib(id);
    });

    function sendInvoiceToGib(id) {
        Swal.fire({
            title: 'Fatura Gönderilsin mi?',
            text: 'Fatura EDM Bilişim ve GİB sistemine iletilecektir. Bu işlem geri alınamaz.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#135bec'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.showLoading();
                fetch('api/efatura-api.php?action=send_invoice', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `invoice_id=${encodeURIComponent(id)}`
                })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        Swal.fire('Başarılı', res.message + (res.fatura_no ? ' (No: ' + res.fatura_no + ')' : ''), 'success');
                        table.ajax.reload(null, false);
                        loadStats();
                    } else {
                        Swal.fire('Hata', res.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Hata', 'Gönderim sırasında bağlantı hatası oluştu.', 'error');
                });
            }
        });
    }

    // 7. GİB Durum Senkronizasyonu
    $(document).on('click', '.btn-senkronize', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        syncInvoiceStatus(id);
    });

    function syncInvoiceStatus(id) {
        fetch('api/efatura-api.php?action=sync_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `invoice_id=${encodeURIComponent(id)}`
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Güncellendi', 'Fatura durumu senkronize edildi: ' + (res.data.durum_kodu || ''), 'success');
                table.ajax.reload(null, false);
                loadStats();
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        })
        .catch(err => {
            Swal.fire('Hata', 'Senkronizasyon hatası oluştu.', 'error');
        });
    }

    // 8. Fatura İptal Butonu (Standart Bootstrap Modal)
    $(document).on('click', '.btn-iptal', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        cancelInvoice(id);
    });

    function cancelInvoice(id) {
        $('#iptalInvoiceId').val(id);
        $('#iptalNedeni').val('');
        const iptalModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFaturaIptal'));
        iptalModal.show();
    }

    // İptal Formu Gönderimi
    $('#formFaturaIptal').on('submit', function(e) {
        e.preventDefault();
        const id = $('#iptalInvoiceId').val();
        const reason = $('#iptalNedeni').val().trim();

        if (!reason) {
            $('#iptalNedeni').focus();
            return;
        }

        const $btn = $('#btnSubmitIptal');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> İptal Ediliyor...');

        fetch('api/efatura-api.php?action=cancel_invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `invoice_id=${encodeURIComponent(id)}&reason=${encodeURIComponent(reason)}`
        })
        .then(res => res.json())
        .then(res => {
            $btn.prop('disabled', false).html('<i class="bx bx-check"></i> İptal Et');
            const iptalModal = bootstrap.Modal.getInstance(document.getElementById('modalFaturaIptal'));
            if (iptalModal) iptalModal.hide();

            if (res.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'İptal Edildi',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                table.ajax.reload(null, false);
                loadStats();
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        })
        .catch(err => {
            $btn.prop('disabled', false).html('<i class="bx bx-check"></i> İptal Et');
            Swal.fire('Hata', 'İptal işlemi sırasında hata oluştu.', 'error');
        });
    });

    // Kopyalama Butonları
    $(document).on('click', '.btn-copy-no', function() {
        const no = $(this).data('no');
        if (no) {
            navigator.clipboard.writeText(no);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Fatura No kopyalandı: ' + no, showConfirmButton: false, timer: 2000 });
        }
    });

    $(document).on('click', '.btn-copy-ettn', function() {
        const ettn = $(this).data('ettn');
        if (ettn) {
            navigator.clipboard.writeText(ettn);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ETTN kopyalandı: ' + ettn, showConfirmButton: false, timer: 2000 });
        }
    });

    function printInvoiceHtml(content) {
        const win = window.open('', '_blank');
        if (!win) {
            Swal.fire('Uyarı', 'Açılır pencere engelleyici (pop-up) yazdırma sayfasını engelledi. Lütfen tarayıcı ayarlarından izin verin.', 'warning');
            return;
        }
        win.document.open();
        win.document.write(`
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="utf-8">
                <title>E-Fatura / E-Arşiv Yazdır</title>
                <style>
                    @page {
                        size: A4 portrait;
                        margin: 6mm 10mm;
                    }
                    * {
                        box-sizing: border-box;
                    }
                    html, body {
                        margin: 0;
                        padding: 0;
                        background: #fff;
                        color: #000;
                        font-family: Arial, Helvetica, sans-serif;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    @media print {
                        body {
                            margin: 0 !important;
                            padding: 0 !important;
                        }
                    }
                </style>
            </head>
            <body>
                ${content}
            </body>
            </html>
        `);
        win.document.close();
        win.focus();
        setTimeout(() => {
            win.print();
        }, 400);
    }

    // 9. Yazdır Butonu
    $('#btnModalYazdir').on('click', function() {
        const printContent = document.getElementById('onizlemeModalContent').innerHTML;
        printInvoiceHtml(printContent);
    });

    // 10. SAĞ TIK & 3 NOKTA MENÜSÜ (CONTEXT MENU) ENTEGRASYONU
    const $contextMenu = $('#faturaContextMenu');

    function showContextMenu(targetElement, data, e) {
        selectedRowData = data;
        const status = (data.entegrator_durum_kodu || '').toUpperCase();
        const isRightClick = e && e.type === 'contextmenu';

        // Menü Başlığı
        $contextMenu.find('.dropdown-header').text(isRightClick ? 'Tüm Fatura İşlemleri' : 'Diğer İşlemler');

        if (isRightClick) {
            // SAĞ TIK: HEPSİ (Tüm işlemler) listelenir
            $('.cm-preview-action').removeClass('d-none').show();
            $('.cm-print-action').removeClass('d-none').show();
            $('.cm-pdf-action').removeClass('d-none').show();
            $('.cm-xml-action').removeClass('d-none').show();
            $('.cm-copy-no').removeClass('d-none').show();
            $('.cm-copy-ettn').removeClass('d-none').show();
            $('.cm-div-1').removeClass('d-none').show();
            $('.cm-div-2').removeClass('d-none').show();

            if (status === 'TASLAK') {
                $('.cm-edit-action').removeClass('d-none').show();
                $('.cm-send-action').removeClass('d-none').show();
                $('.cm-sync-action').addClass('d-none').hide();
                $('.cm-delete-action').removeClass('d-none').show();
                $('.cm-cancel-action').addClass('d-none').hide();
                $('.cm-div-3').removeClass('d-none').show();
            } else if (status === 'IPTAL') {
                $('.cm-edit-action').addClass('d-none').hide();
                $('.cm-send-action').addClass('d-none').hide();
                $('.cm-sync-action').addClass('d-none').hide();
                $('.cm-delete-action').addClass('d-none').hide();
                $('.cm-cancel-action').addClass('d-none').hide();
                $('.cm-div-3').addClass('d-none').hide();
            } else {
                // GONDERILDI, ONAYLANDI, HATALI
                $('.cm-edit-action').addClass('d-none').hide();
                $('.cm-send-action').addClass('d-none').hide();
                if (status === 'GONDERILDI' || status === 'KUYRUKTA') {
                    $('.cm-sync-action').removeClass('d-none').show();
                } else {
                    $('.cm-sync-action').addClass('d-none').hide();
                }
                $('.cm-delete-action').addClass('d-none').hide();
                $('.cm-cancel-action').removeClass('d-none').show();
                $('.cm-div-3').removeClass('d-none').show();
            }
        } else {
            // 3 NOKTA (⋮) BUTONU: Satırda olan Düzenle ve GİB'e Gönder hariç diğer TÜM işlemler açılır listede olur
            $('.cm-edit-action').addClass('d-none').hide();
            $('.cm-send-action').addClass('d-none').hide();
            $('.cm-sync-action').addClass('d-none').hide();

            // Diğer tüm işlemler açılır listede mevcuttur:
            $('.cm-preview-action').removeClass('d-none').show();
            $('.cm-print-action').removeClass('d-none').show();
            $('.cm-pdf-action').removeClass('d-none').show();
            $('.cm-xml-action').removeClass('d-none').show();
            $('.cm-div-1').removeClass('d-none').show();

            $('.cm-copy-no').removeClass('d-none').show();
            $('.cm-copy-ettn').removeClass('d-none').show();
            $('.cm-div-2').removeClass('d-none').show();

            // Taslak ise Sil, GİB'e iletildiyse İptal
            if (status === 'TASLAK') {
                $('.cm-delete-action').removeClass('d-none').show();
                $('.cm-cancel-action').addClass('d-none').hide();
                $('.cm-div-3').removeClass('d-none').show();
            } else if (status === 'IPTAL') {
                $('.cm-delete-action').addClass('d-none').hide();
                $('.cm-cancel-action').addClass('d-none').hide();
                $('.cm-div-3').addClass('d-none').hide();
            } else {
                $('.cm-delete-action').addClass('d-none').hide();
                $('.cm-cancel-action').removeClass('d-none').show();
                $('.cm-div-3').removeClass('d-none').show();
            }
        }

        // Body'ye taşı
        if (!$contextMenu.parent().is('body')) {
            $contextMenu.appendTo('body');
        }

        let posX = 0;
        let posY = 0;

        if (e && e.type === 'contextmenu') {
            posX = e.clientX;
            posY = e.clientY;
        } else if (targetElement) {
            const rect = targetElement.getBoundingClientRect();
            posX = rect.right - 220;
            posY = rect.bottom + 4;
        }

        const menuWidth = $contextMenu.outerWidth() || 220;
        const menuHeight = $contextMenu.outerHeight() || 320;

        // Viewport sınır kontrolleri
        if (posX + menuWidth > $(window).width()) {
            posX = $(window).width() - menuWidth - 10;
        }
        if (posX < 10) posX = 10;

        if (posY + menuHeight > $(window).height()) {
            if (targetElement && e && e.type !== 'contextmenu') {
                const rect = targetElement.getBoundingClientRect();
                posY = rect.top - menuHeight - 4;
            } else {
                posY = posY - menuHeight;
            }
        }
        if (posY < 10) posY = 10;

        $contextMenu.css({
            position: 'fixed',
            top: posY + 'px',
            left: posX + 'px',
            zIndex: 99999
        }).fadeIn(120);
    }

    // Doğrudan Satırdaki Sil Butonuna Tıklanınca
    $('#tblFaturalar tbody').on('click', '.btn-direct-delete', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data('id');
        if (id) {
            deleteDraftInvoice(id);
        }
    });

    // 3 Nokta Butonuna Tıklanınca Menüyü Aç
    $('#tblFaturalar tbody').on('click', '.btn-row-menu', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const tr = $(this).closest('tr');
        const data = table.row(tr).data();
        if (!data) return;

        $('#tblFaturalar tbody tr').removeClass('selected');
        tr.addClass('selected');
        showContextMenu(this, data, e);
    });

    // Satıra Sağ Tıklanınca Menüyü Aç
    $('#tblFaturalar tbody').on('contextmenu', 'tr', function(e) {
        e.preventDefault();
        const row = table.row(this);
        const data = row.data();
        if (!data) return;

        $('#tblFaturalar tbody tr').removeClass('selected');
        $(this).addClass('selected');
        showContextMenu(this, data, e);
    });

    // Context Menu Dışına Tıklanınca Kapat
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#faturaContextMenu, .btn-row-menu').length) {
            $contextMenu.fadeOut(100);
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $contextMenu.fadeOut(100);
        }
    });

    // Taslak Faturayı Sil Fonksiyonu
    function deleteDraftInvoice(id) {
        Swal.fire({
            title: 'Taslak Faturayı Sil?',
            text: 'Bu taslak fatura sistemden silinecektir. Bu işlem geri alınamaz.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Siliniyor...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: 'api/efatura-api.php',
                    type: 'POST',
                    data: { action: 'delete_draft', invoice_id: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi!',
                                text: res.message || 'Taslak fatura başarıyla silindi.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                            loadStats();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata!', text: res.message || 'Fatura silinemedi.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: 'İşlem sırasında bir hata oluştu.' });
                    }
                });
            }
        });
    }

    // EDM'den Giden Faturaları Çek
    $('#btnSyncOutgoing').on('click', function() {
        Swal.fire({
            title: 'EDM Faturaları Çekilsin mi?',
            text: 'EDM Bilişim portalında bulunan giden faturalar taranarak sisteme aktarılacaktır.',
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
                    data: { action: 'sync_outgoing_invoices' },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Senkronizasyon Tamamlandı',
                                text: res.message
                            }).then(() => {
                                table.ajax.reload(null, false);
                                loadStats();
                            });
                        } else {
                            Swal.fire('Bilgi', res.message || 'Yeni fatura bulunamadı.', 'info');
                        }
                    },
                    error: function() {
                        Swal.fire('Hata', 'EDM servisinden faturalar çekilirken hata oluştu.', 'error');
                    }
                });
            }
        });
    });

    // Context Menu İşlem Tetikleyicileri
    $('.cm-action').on('click', function() {
        $contextMenu.fadeOut(100);
        if (!selectedRowData) return;

        const action = $(this).data('action');
        const id = selectedRowData.encrypted_id;

        if (action === 'edit') {
            window.location.href = `index.php?p=efatura/olustur&id=${encodeURIComponent(id)}`;
        } else if (action === 'preview') {
            openInvoicePreview(id);
        } else if (action === 'print') {
            openInvoicePreview(id, true);
        } else if (action === 'download-pdf') {
            openInvoicePreview(id);
        } else if (action === 'download-xml') {
            window.location.href = `api/efatura-api.php?action=download_xml&invoice_id=${encodeURIComponent(id)}`;
        } else if (action === 'send') {
            sendInvoiceToGib(id);
        } else if (action === 'sync') {
            syncInvoiceStatus(id);
        } else if (action === 'copy-no') {
            const no = selectedRowData.fatura_no;
            navigator.clipboard.writeText(no);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Fatura No kopyalandı: ' + no, showConfirmButton: false, timer: 2000 });
        } else if (action === 'copy-ettn') {
            const ettn = selectedRowData.ettn;
            navigator.clipboard.writeText(ettn);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ETTN kopyalandı: ' + ettn, showConfirmButton: false, timer: 2000 });
        } else if (action === 'delete') {
            deleteDraftInvoice(id);
        } else if (action === 'cancel') {
            cancelInvoice(id);
        }
    });
});
