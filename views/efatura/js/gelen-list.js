/**
 * Gelen Faturalar Modülü JavaScript Yöneticisi
 * Ersan Elektrik - E-Fatura & E-Arşiv Yönetimi
 */

$(document).ready(function () {
    const API_URL = 'api/efatura-api.php';
    let dataTable = null;

    // --- 1. Özet Kartları Açma/Kapama Standardı ---
    const STORAGE_KEY = 'gelen_faturalar_summary_collapsed';
    const summaryContainer = $('#summaryCardsContainer');
    const toggleBtn = $('#btnToggleSummaryCards');

    const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
    if (isCollapsed) {
        summaryContainer.addClass('cards-collapsed');
        toggleBtn.find('i').removeClass('bx-chevron-up').addClass('bx-chevron-down');
        toggleBtn.attr('aria-expanded', 'false');
    }

    toggleBtn.on('click', function () {
        summaryContainer.toggleClass('cards-collapsed');
        const collapsed = summaryContainer.hasClass('cards-collapsed');
        localStorage.setItem(STORAGE_KEY, collapsed);
        toggleBtn.find('i').toggleClass('bx-chevron-up', !collapsed).toggleClass('bx-chevron-down', collapsed);
        toggleBtn.attr('aria-expanded', !collapsed);
    });

    // --- 2. KPI İstatistiklerini Yükle ---
    function loadStats() {
        $.ajax({
            url: API_URL,
            type: 'GET',
            data: { action: 'summary_stats', list_type: 'gelen' },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    $('#stat_toplam_adet').text(d.toplam_adet || '0');
                    $('#stat_kabul_adet').text(d.kabul_adet || '0');
                    $('#stat_bekleyen_adet').text(d.bekleyen_adet || '0');
                    $('#stat_bu_ay_adet_text').text(`Bu Ay: ${d.bu_ay_adet || 0} Adet`);
                    
                    const tutar = parseFloat(d.toplam_tutar || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    $('#stat_toplam_tutar').text(`${tutar} ₺`);

                    const buAyTutar = parseFloat(d.bu_ay_tutar || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    $('#stat_bu_ay_tutar').text(`${buAyTutar} ₺`);
                }
            }
        });
    }

    // --- 3. DataTables Başlatma ---
    function initDataTable() {
        if ($.fn.DataTable.isDataTable('#tblGelenFaturalar')) {
            $('#tblGelenFaturalar').DataTable().destroy();
        }

        dataTable = $('#tblGelenFaturalar').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            ajax: {
                url: API_URL,
                type: 'GET',
                data: function (d) {
                    d.action = 'list_invoices';
                    d.list_type = 'gelen';
                }
            },
            order: [[2, 'desc']], // Fatura Tarihine göre azalan
            columns: [
                {
                    data: 'id',
                    orderable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                {
                    data: 'fatura_no',
                    render: function (data, type, row) {
                        return `<div class="d-flex flex-column">
                            <span class="fw-bold text-dark font-size-13">${data || '-'}</span>
                            <small class="text-muted font-monospace" style="font-size: 10px;">${row.ettn || ''}</small>
                        </div>`;
                    }
                },
                {
                    data: 'fatura_tarihi',
                    className: 'fw-semibold text-secondary'
                },
                {
                    data: 'alici_unvan', // Gelen faturada bu gönderici firma ünvanıdır
                    render: function (data) {
                        return `<span class="fw-semibold text-dark text-truncate d-inline-block" style="max-width: 260px;" title="${data}">${data}</span>`;
                    }
                },
                {
                    data: 'alici_vkn_tckn',
                    className: 'font-monospace'
                },
                {
                    data: 'belge_turu',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-soft-primary text-primary px-2 py-1"><i class="bx bx-buildings me-1"></i>E-Fatura</span>`;
                    }
                },
                {
                    data: 'fatura_profili',
                    className: 'text-center',
                    render: function (data) {
                        const isTicari = (data === 'TICARIFATURA');
                        const badgeClass = isTicari ? 'bg-soft-warning text-warning border border-warning border-opacity-25' : 'bg-light text-secondary border';
                        return `<span class="badge ${badgeClass} px-2 py-1">${data || 'TEMELFATURA'}</span>`;
                    }
                },
                {
                    data: 'odenecek_tutar',
                    className: 'text-end fw-bold text-dark'
                },
                {
                    data: 'ticari_yanit',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (row.fatura_profili !== 'TICARIFATURA') {
                            return `<span class="badge bg-light text-muted border px-2 py-1 font-size-11">Temel Fatura</span>`;
                        }
                        if (data === 'KABUL') {
                            return `<span class="badge bg-soft-success text-success px-2 py-1"><i class="bx bx-check me-1"></i>Kabul Edildi</span>`;
                        } else if (data === 'RED') {
                            return `<span class="badge bg-soft-danger text-danger px-2 py-1"><i class="bx bx-x me-1"></i>Reddedildi</span>`;
                        }
                        return `<span class="badge bg-soft-warning text-warning px-2 py-1"><i class="bx bx-time-five me-1"></i>Yanıt Bekliyor</span>`;
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        let actions = `
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 btn-preview" data-id="${data}" title="Faturayı Görüntüle">
                                <i class="bx bx-show"></i> İncele
                            </button>`;

                        if (row.fatura_profili === 'TICARIFATURA' && row.ticari_yanit === 'BEKLIYOR') {
                            actions += `
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-1 btn-respond" data-id="${data}" data-type="KABUL" title="Kabul Et">
                                <i class="bx bx-check"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 btn-respond" data-id="${data}" data-type="RED" title="Reddet">
                                <i class="bx bx-x"></i>
                            </button>`;
                        }

                        actions += `</div>`;
                        return actions;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
            }
        });
    }

    // --- 4. EDM'den Yeni Gelen Faturaları Çekme ---
    $('#btnSyncIncoming').on('click', function () {
        Swal.fire({
            title: 'Gelen Faturalar Çekilsin mi?',
            text: 'EDM Bilişim e-Fatura gelen kutusu taranacak ve yeni gelen faturalar sisteme aktarılacaktır.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-refresh me-1"></i> Evet, Çek',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Gelen Faturalar Taranıyor...',
                    text: 'EDM servisi ile senkronizasyon yapılıyor, lütfen bekleyin.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    data: { action: 'sync_incoming_invoices' },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Senkronizasyon Tamamlandı',
                                text: res.message || 'Gelen faturalar başarıyla güncellendi.'
                            }).then(() => {
                                dataTable.ajax.reload();
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
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: 'Gelen faturalar çekilirken bir hata oluştu.'
                        });
                    }
                });
            }
        });
    });

    // --- 5. Ticari Fatura Kabul / Red Yanıtı ---
    $(document).on('click', '.btn-respond', function () {
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
            url: API_URL,
            type: 'POST',
            data: {
                action: 'respond_commercial',
                invoice_id: invoiceId,
                response_type: responseType,
                reason: reason
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message || 'Ticari yanıt başarıyla iletildi.'
                    }).then(() => {
                        dataTable.ajax.reload();
                        loadStats();
                    });
                } else {
                    Swal.fire('Hata', res.message || 'Ticari yanıt iletilemedi.', 'error');
                }
            },
            error: function () {
                Swal.fire('Hata', 'Sunucu ile iletişim kurulurken bir sorun oluştu.', 'error');
            }
        });
    }

    // --- 6. HTML Önizleme ---
    $(document).on('click', '.btn-preview', function () {
        const invoiceId = $(this).data('id');

        $('#invoicePreviewContainer').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Gelen fatura yükleniyor...</p>
            </div>
        `);
        $('#modalInvoicePreview').modal('show');

        $.ajax({
            url: API_URL,
            type: 'GET',
            data: {
                action: 'preview_html',
                invoice_id: invoiceId
            },
            dataType: 'json',
            success: function (res) {
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
            error: function () {
                $('#invoicePreviewContainer').html(`
                    <div class="alert alert-danger m-3">
                        <i class="bx bx-error me-1"></i> Sunucu hatası. Önizleme alınamadı.
                    </div>
                `);
            }
        });
    });

    // Önizleme Yazdır
    $('#btnPrintPreview').on('click', function () {
        const printContent = document.getElementById('invoicePreviewContainer').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=900');
        printWindow.document.write('<html><head><title>Gelen Fatura Yazdır</title>');
        printWindow.document.write('<link rel="stylesheet" href="assets/css/bootstrap.min.css">');
        printWindow.document.write('</head><body style="background:#fff; padding:20px;">');
        printWindow.document.write(printContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function () {
            printWindow.print();
            printWindow.close();
        }, 500);
    });

    // Sayfa Yüklendiğinde
    initDataTable();
    loadStats();
});
