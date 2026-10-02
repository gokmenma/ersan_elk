/**
 * Taslak Faturalar Modülü JavaScript Yöneticisi
 * Ersan Elektrik - E-Fatura & E-Arşiv Yönetimi
 */

$(document).ready(function () {
    const API_URL = 'api/efatura-api.php';
    let dataTable = null;
    let selectedInvoices = new Set();
    let currentPreviewInvoiceId = null;

    // --- 1. Özet Kartları Açma/Kapama Standardı ---
    const STORAGE_KEY = 'taslak_faturalar_summary_collapsed';
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
            data: { action: 'summary_stats', list_type: 'taslak' },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success' && res.data) {
                    const d = res.data;
                    $('#stat_toplam_adet').text(d.toplam_adet || '0');
                    $('#stat_efatura_adet').text(d.efatura_adet || '0');
                    $('#stat_earsiv_adet').text(d.earsiv_adet || '0');
                    $('#stat_sub_efatura_earsiv').text(`E-Fatura: ${d.efatura_adet || 0} | E-Arşiv: ${d.earsiv_adet || 0}`);
                    
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
        if ($.fn.DataTable.isDataTable('#tblTaslakFaturalar')) {
            $('#tblTaslakFaturalar').DataTable().destroy();
        }

        dataTable = $('#tblTaslakFaturalar').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            ajax: {
                url: API_URL,
                type: 'GET',
                data: function (d) {
                    d.action = 'list_invoices';
                    d.list_type = 'taslak';
                    d.belge_turu_filtre = $('#selectBelgeTuruFiltre').val();
                }
            },
            order: [[3, 'desc']], // Tarihe göre azalan
            columns: [
                {
                    data: 'encrypted_id',
                    orderable: false,
                    className: 'text-center',
                    render: function (data) {
                        const checked = selectedInvoices.has(data) ? 'checked' : '';
                        return `<input type="checkbox" class="form-check-input invoice-checkbox" value="${data}" ${checked}>`;
                    }
                },
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
                            <span class="fw-bold text-dark font-size-13">${data || 'Taslak'}</span>
                            <small class="text-muted font-monospace" style="font-size: 10px;">${row.ettn || ''}</small>
                        </div>`;
                    }
                },
                {
                    data: 'fatura_tarihi',
                    className: 'fw-semibold text-secondary'
                },
                {
                    data: 'alici_unvan',
                    render: function (data) {
                        return `<span class="fw-semibold text-dark text-truncate d-inline-block" style="max-width: 240px;" title="${data}">${data}</span>`;
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
                        if (data === 'EFATURA') {
                            return `<span class="badge bg-soft-primary text-primary px-2 py-1"><i class="bx bx-buildings me-1"></i>E-Fatura</span>`;
                        }
                        return `<span class="badge bg-soft-info text-info px-2 py-1"><i class="bx bx-user me-1"></i>E-Arşiv</span>`;
                    }
                },
                {
                    data: 'fatura_profili',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-light text-secondary border px-2 py-1">${data || 'TICARIFATURA'}</span>`;
                    }
                },
                {
                    data: 'odenecek_tutar',
                    className: 'text-end fw-bold text-dark'
                },
                {
                    data: 'entegrator_durum_kodu',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-soft-warning text-warning border border-warning border-opacity-25 px-2 py-1"><i class="bx bx-time-five me-1"></i>Taslak</span>`;
                    }
                },
                {
                    data: 'encrypted_id',
                    orderable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <button type="button" class="btn btn-sm btn-success py-0 px-2 btn-send-edm" data-id="${data}" title="EDM Sistemine Gönder">
                                <i class="bx bx-send"></i> Gönder
                            </button>
                            <a href="index.php?p=efatura/olustur&id=${data}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Düzenle">
                                <i class="bx bx-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-preview" data-id="${data}" title="Önizle">
                                <i class="bx bx-show"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-delete-draft" data-id="${data}" title="Taslağı Sil">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
            },
            drawCallback: function () {
                updateSelectedCount();
            }
        });
    }

    // --- 4. Filtre Değişimleri ---
    $('#selectBelgeTuruFiltre').on('change', function () {
        dataTable.ajax.reload();
    });

    // --- 5. Checkbox ve Toplu Seçim Yönetimi ---
    $('#checkAllInvoices').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.invoice-checkbox').each(function () {
            $(this).prop('checked', isChecked);
            const id = $(this).val();
            if (isChecked) {
                selectedInvoices.add(id);
            } else {
                selectedInvoices.delete(id);
            }
        });
        updateSelectedCount();
    });

    $(document).on('change', '.invoice-checkbox', function () {
        const id = $(this).val();
        if ($(this).is(':checked')) {
            selectedInvoices.add(id);
        } else {
            selectedInvoices.delete(id);
        }
        updateSelectedCount();
    });

    function updateSelectedCount() {
        const count = selectedInvoices.size;
        $('#selectedCount').text(count);
        if (count > 0) {
            $('#btnBulkSend').removeClass('d-none');
        } else {
            $('#btnBulkSend').addClass('d-none');
        }
    }

    // --- 6. Tekil EDM'ye Gönder Butonu ---
    $(document).on('click', '.btn-send-edm', function () {
        const invoiceId = $(this).data('id');
        
        Swal.fire({
            title: 'Fatura Gönderilsin mi?',
            text: 'Taslak fatura EDM sistemine (GİB) iletilecektir. Gönderim sonrası fatura "Giden Faturalar" modülüne aktarılacaktır.',
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
                    text: 'EDM Bilişim servisi ile iletişim kuruluyor, lütfen bekleyin.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    data: {
                        action: 'send_invoice',
                        invoice_id: invoiceId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarıyla Gönderildi!',
                                html: `Fatura başarıyla EDM sistemine iletildi.<br><strong>Fatura No:</strong> ${res.fatura_no || '-'}<br><br><span class="text-muted font-size-12">Fatura artık <strong>Giden Faturalar</strong> ekranında listelenmektedir.</span>`,
                                confirmButtonText: 'Tamam'
                            }).then(() => {
                                selectedInvoices.delete(invoiceId);
                                dataTable.ajax.reload();
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
                    error: function () {
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

    // --- 7. Toplu EDM'ye Gönder Butonu ---
    $('#btnBulkSend').on('click', function () {
        const ids = Array.from(selectedInvoices);
        if (ids.length === 0) return;

        Swal.fire({
            title: `${ids.length} Adet Fatura Gönderilsin mi?`,
            text: 'Seçilen tüm taslak faturalar sırayla EDM sistemine iletilecek ve Giden Faturalar modülüne aktarılacaktır.',
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
                    text: 'Faturalar işleniyor, lütfen sayfayı kapatmayın.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    data: {
                        action: 'bulk_send_invoices',
                        invoice_ids: ids
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'İşlem Tamamlandı',
                                text: res.message
                            }).then(() => {
                                selectedInvoices.clear();
                                dataTable.ajax.reload();
                                loadStats();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Bazı Faturalar Gönderilemedi',
                                html: `${res.message}<br><small class="text-danger">${(res.errors || []).join('<br>')}</small>`
                            }).then(() => {
                                dataTable.ajax.reload();
                                loadStats();
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: 'Toplu gönderim sırasında bir hata oluştu.'
                        });
                    }
                });
            }
        });
    });

    // --- 8. Taslak Sil Butonu ---
    $(document).on('click', '.btn-delete-draft', function () {
        const invoiceId = $(this).data('id');

        Swal.fire({
            title: 'Taslak Faturayı Sil?',
            text: 'Bu taslak fatura kalıcı olarak silinecektir. Bu işlem geri alınamaz.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: API_URL,
                    type: 'POST',
                    data: {
                        action: 'delete_draft',
                        invoice_id: invoiceId
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: 'Taslak fatura başarıyla silindi.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            selectedInvoices.delete(invoiceId);
                            dataTable.ajax.reload();
                            loadStats();
                        } else {
                            Swal.fire('Hata', res.message || 'Silme işlemi başarısız.', 'error');
                        }
                    }
                });
            }
        });
    });

    // --- 9. Fatura HTML Önizleme ---
    $(document).on('click', '.btn-preview', function () {
        const invoiceId = $(this).data('id');
        currentPreviewInvoiceId = invoiceId;

        $('#invoicePreviewContainer').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Fatura yükleniyor...</p>
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

    // Modaldan EDM'ye Gönder
    $('#btnModalSendEdm').on('click', function () {
        if (!currentPreviewInvoiceId) return;
        $('#modalInvoicePreview').modal('hide');
        $(`.btn-send-edm[data-id="${currentPreviewInvoiceId}"]`).trigger('click');
    });

    // Önizleme Yazdır
    $('#btnPrintPreview').on('click', function () {
        const printContent = document.getElementById('invoicePreviewContainer').innerHTML;
        const printWindow = window.open('', '', 'height=800,width=900');
        printWindow.document.write('<html><head><title>Fatura Yazdır</title>');
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
