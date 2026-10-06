(function () {
    'use strict';
    if (window.efaturaTransportInstalled) return;
    window.efaturaTransportInstalled = true;
    const token = document.querySelector('meta[name="efatura-csrf"]')?.content || '';
    const originalFetch = window.fetch.bind(window);
    function isEndpoint(input) {
        const url = new URL(typeof input === 'string' ? input : input.url, window.location.href);
        return url.origin === window.location.origin && /\/api\/efatura-api\.php$/.test(url.pathname);
    }
    window.fetch = function (input, init = {}) {
        if (isEndpoint(input)) {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            init = {...init, headers};
        }
        return originalFetch(input, init);
    };
    function installAjaxHeader() {
        if (window.jQuery && !window.efaturaAjaxInstalled) {
            window.efaturaAjaxInstalled = true;
            jQuery.ajaxPrefilter(function (options, originalOptions, xhr) { if (isEndpoint(options.url)) xhr.setRequestHeader('X-CSRF-Token', token); });
        }
    }
    installAjaxHeader();
    document.addEventListener('DOMContentLoaded', installAjaxHeader);
    window.initFlatpickrWithYearSelect = function (selector, userOptions = {}) {
        if (typeof flatpickr === 'undefined') return null;
        const el = (typeof selector === 'string') ? document.querySelector(selector) : selector;
        if (!el) return null;

        const updateYearDropdown = function (instance) {
            if (!instance || !instance.calendarContainer) return;
            const monthNav = instance.calendarContainer.querySelector('.flatpickr-current-month');
            if (!monthNav) return;

            const numInput = monthNav.querySelector('.numInputWrapper');
            if (numInput) {
                numInput.style.display = 'none';
            }

            let yearSelect = monthNav.querySelector('.flatpickr-yearDropdown-years');
            if (!yearSelect) {
                yearSelect = document.createElement('select');
                yearSelect.className = 'flatpickr-monthDropdown-months flatpickr-yearDropdown-years';
                yearSelect.setAttribute('aria-label', 'Yıl');

                const curYear = new Date().getFullYear();
                const startY = userOptions.minYear || (curYear - 15);
                const endY = userOptions.maxYear || curYear;

                for (let y = startY; y <= endY; y++) {
                    const opt = document.createElement('option');
                    opt.value = y;
                    opt.textContent = y;
                    yearSelect.appendChild(opt);
                }

                yearSelect.addEventListener('change', function (e) {
                    e.stopPropagation();
                    const newYear = parseInt(this.value, 10);
                    if (!isNaN(newYear)) {
                        if (typeof instance.changeYear === 'function') {
                            instance.changeYear(newYear);
                        } else {
                            instance.currentYear = newYear;
                            instance.redraw();
                        }
                    }
                });

                monthNav.appendChild(yearSelect);
            }

            if (yearSelect && instance.currentYear) {
                yearSelect.value = instance.currentYear;
            }
        };

        const config = {
            locale: 'tr',
            dateFormat: 'd.m.Y',
            ...userOptions,
            onReady: function (selectedDates, dateStr, instance) {
                updateYearDropdown(instance);
                if (typeof userOptions.onReady === 'function') userOptions.onReady(selectedDates, dateStr, instance);
            },
            onOpen: function (selectedDates, dateStr, instance) {
                updateYearDropdown(instance);
                if (typeof userOptions.onOpen === 'function') userOptions.onOpen(selectedDates, dateStr, instance);
            },
            onMonthChange: function (selectedDates, dateStr, instance) {
                updateYearDropdown(instance);
                if (typeof userOptions.onMonthChange === 'function') userOptions.onMonthChange(selectedDates, dateStr, instance);
            },
            onYearChange: function (selectedDates, dateStr, instance) {
                updateYearDropdown(instance);
                if (typeof userOptions.onYearChange === 'function') userOptions.onYearChange(selectedDates, dateStr, instance);
            }
        };

        return flatpickr(el, config);
    };

    window.efaturaSetupSummary = function (key) {
        const button = document.getElementById('btnToggleSummaryCards');
        const group = document.getElementById('summaryCardsContainer');
        if (!button || !group) return;
        const root = document.documentElement;
        function update() {
            const hidden = root.classList.contains('efatura-summary-hidden');
            group.setAttribute('aria-hidden', String(hidden)); group.inert = hidden;
            button.setAttribute('aria-expanded', String(!hidden));
            button.title = hidden ? 'Özet kartlarını göster' : 'Özet kartlarını gizle';
            button.setAttribute('aria-label', button.title);
            button.querySelector('i').className = 'bx bx-chevron-' + (hidden ? 'down' : 'up') + ' font-size-18';
            if (!hidden) group.style.maxHeight = group.scrollHeight + 30 + 'px';
        }
        button.addEventListener('click', function () {
            root.classList.toggle('efatura-summary-hidden');
            try { localStorage.setItem(key, root.classList.contains('efatura-summary-hidden') ? 'hidden' : 'visible'); } catch (e) {}
            update();
        });
        window.addEventListener('resize', update); update();
    };
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery) return;
        $(document).on('click', '.efatura-history', function () { window.efaturaShowHistory($(this).data('id')); });
        $(document).on('click', '.efatura-pdf', function () { window.efaturaDownloadPdf($(this).data('id')); });
        $(document).on('draw.dt', function (event, settings) {
            if (!settings?.nTable?.id?.match(/Faturalar$/)) return;
            const table = $(settings.nTable).DataTable();
            const sources = table.columns().dataSrc().toArray();
            const statusIndex = sources.indexOf('entegrator_durum_kodu') >= 0 ? sources.indexOf('entegrator_durum_kodu') : sources.indexOf('ticari_yanit');
            table.rows({page: 'current'}).every(function () {
                const row = this.data(); const cells = $(this.node()).children('td');
                cells.find('.efatura-reports').remove();
                if (cells.last().find('.action-btn-group').length === 0 && cells.last().find('.efatura-history').length === 0) {
                    const history = $('<button type="button" class="btn btn-sm btn-subtle-info table-action-btn efatura-history" title="İşlem geçmişi"><i class="bx bx-history"></i></button>').attr('data-id', row.encrypted_id);
                    const pdf = $('<button type="button" class="btn btn-sm btn-subtle-danger table-action-btn efatura-pdf" title="PDF indir"><i class="bx bxs-file-pdf"></i></button>').attr('data-id', row.encrypted_id);
                    cells.last().append(history, pdf);
                }
                const reports = $('<div class="efatura-reports small mt-1">');
                if (row.earsiv_rapor_durum) reports.append($('<div class="badge bg-light text-muted border font-size-10 me-1">').text('Rapor: ' + row.earsiv_rapor_durum));
                if (row.earsiv_iptal_rapor_durum) reports.append($('<div class="badge bg-danger-subtle text-danger border font-size-10 me-1">').text('İptal: ' + row.earsiv_iptal_rapor_durum));
                if (row.islem_belirsiz) reports.append($('<div class="badge bg-warning-subtle text-warning border font-size-10">').text('Sorgula: ' + row.islem_belirsiz));
                if (statusIndex >= 0 && (row.earsiv_rapor_durum || row.earsiv_iptal_rapor_durum || row.islem_belirsiz)) cells.eq(statusIndex).append(reports);
            });
        });
    });
    window.efaturaDownloadPdf = async function (id) {
        try {
            const response = await fetch('api/efatura-api.php?action=download_pdf&invoice_id=' + encodeURIComponent(id));
            if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/pdf')) {
                const result = await response.json(); throw new Error(result.message || 'PDF alınamadı.');
            }
            const blob = await response.blob(); const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a'); anchor.href = url;
            anchor.download = response.headers.get('Content-Disposition')?.match(/filename="([^"]+)"/)?.[1] || 'fatura.pdf';
            document.body.appendChild(anchor); anchor.click(); anchor.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        } catch (e) { Swal.fire('PDF alınamadı', e.message, 'warning'); }
    };
    window.efaturaShowHistory = async function (id) {
        try {
            const result = await (await fetch('api/efatura-api.php?action=invoice_history&invoice_id=' + encodeURIComponent(id))).json();
            if (result.status !== 'success') throw new Error(result.message);
            const escape = value => { const node = document.createElement('span'); node.textContent = value ?? ''; return node.innerHTML; };
            let html = '<p>E-Arşiv raporu: ' + escape(result.data.report_status || 'Bilgi yok') + '<br>İptal raporu: ' + escape(result.data.cancel_report_status || 'Bilgi yok') + '</p>';
            if (result.data.pending_operation) html += '<p>Durumu sorgulanması gereken işlem: ' + escape(result.data.pending_operation) + '</p>';
            html += '<div class="text-start">' + result.data.events.map(row => '<p><strong>' + escape(row.olay_tarihi) + ' — ' + escape(row.islem) + '</strong><br>' + escape(row.sonuc) + ': ' + escape(row.aciklama) + '</p>').join('') + '</div>';
            const choice = await Swal.fire({title: 'Fatura işlem geçmişi', html, width: 750, showCancelButton: true, confirmButtonText: 'EDM yanıt tarihlerini yenile', cancelButtonText: 'Kapat'});
            if (choice.isConfirmed) {
                const refreshed = await (await fetch('api/efatura-api.php?action=refresh_history', {method: 'POST', body: new URLSearchParams({invoice_id: id})})).json();
                if (refreshed.status !== 'success') throw new Error(refreshed.message);
                await window.efaturaShowHistory(id);
            }
        } catch (e) { Swal.fire('İşlem geçmişi', e.message, 'warning'); }
    };

    window.efaturaBackgroundSync = function({listCard, buttonSelector, storageKey, listType, onRefresh}) {
        const $ = window.jQuery;
        // Progress is stored on the server, so refreshing or closing this page is safe.
        const syncPanel = $('<div class="alert alert-info mb-3 d-none" role="status" aria-live="polite">' +
            '<div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">' +
            '<div><strong class="sync-heading">EDM aktarımı</strong><div class="sync-message"></div><small class="sync-counts"></small></div>' +
            '<div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-sm btn-outline-warning sync-pause d-none">Durdur</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sync-errors d-none">Aktarılamayanlar / Döküm</button>' +
            '<button type="button" class="btn btn-sm btn-warning sync-retry d-none">Aktarılamayanları yeniden dene</button>' +
            '<button type="button" class="btn btn-sm btn-primary sync-resume d-none">Devam et</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sync-cancel d-none">Aktarımı kapat</button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary sync-dismiss d-none" title="Aktarım kartını kapat" aria-label="Aktarım kartını kapat"><i class="bx bx-x" aria-hidden="true"></i> Kapat</button></div></div></div>');
        syncPanel.insertBefore(listCard);
        const syncDismissKey = storageKey;
        const syncResultKey = job => `${job.created_at}|${job.start_date}|${job.end_date}`;
        let dismissedSyncResult = '';
        try { dismissedSyncResult = localStorage.getItem(syncDismissKey) || ''; } catch (e) {}
        let syncJob = null;
        let syncTimer = null;
        let syncPolling = false;
        let lastSyncRefresh = 0;
        function jobRequest(action, data = {}) {
            return $.ajax({url: 'api/efatura-api.php', type: 'POST', dataType: 'json', data: {action, list_type: listType, ...data}});
        }
        function renderSyncJob(job) {
            const previousStatus = syncJob && syncJob.job_status;
            syncJob = job;
            clearTimeout(syncTimer);
            if (!job) { syncPanel.addClass('d-none'); return; }
            const active = ['queued', 'running'].includes(job.job_status);
            syncPanel.removeClass('d-none alert-info alert-warning alert-success')
                .addClass(['paused', 'partial'].includes(job.job_status) ? 'alert-warning' : (job.job_status === 'completed' ? 'alert-success' : 'alert-info'));
            syncPanel.find('.sync-heading').text(`EDM aktarımı · ${job.start_date} – ${job.end_date}`);
            syncPanel.find('.sync-message').text(job.message);
            syncPanel.find('.sync-counts').text(`${job.processed_count} işlendi · ${job.added_count} yeni · ${job.updated_count} güncellendi${job.failed_count ? ` · ${job.failed_count} aktarılamadı` : ''}`);
            syncPanel.find('.sync-pause').toggleClass('d-none', !active).prop('disabled', !!job.pause_requested).text(job.pause_requested ? 'Durduruluyor…' : 'Durdur');
            syncPanel.find('.sync-errors').toggleClass('d-none', !job.failed_count);
            syncPanel.find('.sync-retry').toggleClass('d-none', !['partial', 'paused', 'cancelled'].includes(job.job_status) || !job.failed_count);
            syncPanel.find('.sync-resume, .sync-cancel').toggleClass('d-none', job.job_status !== 'paused');
            const terminal = ['completed', 'partial', 'cancelled'].includes(job.job_status);
            syncPanel.find('.sync-dismiss').toggleClass('d-none', !terminal);
            syncPanel.toggleClass('d-none', terminal && dismissedSyncResult === syncResultKey(job));
            $(buttonSelector).prop('disabled', active);
            if ((previousStatus && previousStatus !== job.job_status && !active) || (active && Date.now() - lastSyncRefresh > 30000)) {
                onRefresh();
                lastSyncRefresh = Date.now();
            }
            if (active) syncTimer = setTimeout(pollSyncJob, 4000);
        }
        function pollSyncJob() {
            if (syncPolling) return;
            syncPolling = true;
            jobRequest('sync_job_status', syncJob ? {job_token: syncJob.job_token} : {})
                .done(res => {
                    if (res.status === 'success') renderSyncJob(res.data);
                    else {
                        syncPanel.removeClass('d-none').find('.sync-message').text(res.message || 'Aktarım durumu alınamadı.');
                        syncTimer = setTimeout(pollSyncJob, 10000);
                    }
                })
                .fail(xhr => {
                    if (syncJob) {
                        syncPanel.find('.sync-message').text('Aktarım durumu alınamadı. Bağlantı yeniden kontrol edilecek; sunucudaki işlem devam edebilir.');
                        if (![401, 403].includes(xhr.status)) syncTimer = setTimeout(pollSyncJob, 10000);
                    }
                }).always(() => { syncPolling = false; });
        }
        syncPanel.on('click', '.sync-dismiss', function() {
            dismissedSyncResult = syncResultKey(syncJob);
            try { localStorage.setItem(syncDismissKey, dismissedSyncResult); } catch (e) {}
            syncPanel.addClass('d-none');
        });
        syncPanel.on('click', '.sync-resume, .sync-cancel, .sync-pause, .sync-retry', function() {
            const button = $(this);
            const action = button.hasClass('sync-pause') ? 'sync_job_pause' : (button.hasClass('sync-retry') ? 'sync_job_retry' : (button.hasClass('sync-resume') ? 'sync_job_resume' : 'sync_job_cancel'));
            syncPanel.find('button').prop('disabled', true);
            jobRequest(action, {job_token: syncJob.job_token})
                .done(res => {
                    if (res.status === 'success') renderSyncJob(res.data);
                    else Swal.fire('Aktarım', res.message || 'İşlem tamamlanamadı.', 'warning');
                })
                .fail(xhr => Swal.fire('Aktarım', xhr.responseJSON?.message || 'Sunucuya ulaşılamadı.', 'warning'))
                .always(() => { syncPanel.find('button').prop('disabled', false); if (syncJob) renderSyncJob(syncJob); });
        });
        syncPanel.on('click', '.sync-errors', async function() {
            try {
                const res = await jobRequest('sync_job_errors', {job_token: syncJob.job_token});
                if (res.status !== 'success') throw new Error(res.message || 'Döküm alınamadı.');
                const rows = res.data.rows || [];
                const escape = value => $('<span>').text(value ?? '').html();
                const result = await Swal.fire({
                    title: 'Aktarılamayan faturalar', width: 'min(1400px, calc(100vw - 32px))',
                    didOpen: popup => {
                        popup.style.setProperty('width', 'min(1400px, calc(100vw - 32px))', 'important');
                        popup.style.setProperty('max-width', 'calc(100vw - 32px)', 'important');
                    },
                    html: '<p>Bu döküm şimdiye kadar kaydedilen hataları içerir. Aktarım durduğunda veya tamamlandığında yeniden deneyebilirsiniz.</p>' +
                        `<p>${rows.length} hata gösteriliyor; toplam ${res.data.failed_count} fatura aktarılamadı.</p>` +
                        '<div class="table-responsive text-start" style="max-height:55vh"><table class="table table-bordered table-sm" style="min-width:1000px;table-layout:auto"><thead><tr><th style="min-width:150px;white-space:nowrap">Fatura No</th><th style="min-width:290px;white-space:nowrap">ETTN</th><th style="min-width:110px;white-space:nowrap">Tarih</th><th style="min-width:180px">Tedarikçi</th><th style="min-width:270px">Neden</th></tr></thead><tbody>' +
                        rows.map(row => '<tr>' + ['fatura_no', 'uuid', 'issue_date', 'supplier', 'message'].map(key => `<td>${escape(row[key])}</td>`).join('') + '</tr>').join('') + '</tbody></table></div>',
                    showCancelButton: true, confirmButtonText: 'CSV dökümünü indir', cancelButtonText: 'Kapat'
                });
                if (result.isConfirmed) {
                    const cell = value => {
                        let text = String(value ?? '');
                        if (/^[\s]*[=+@-]/.test(text)) text = "'" + text;
                        return '"' + text.replace(/"/g, '""') + '"';
                    };
                    const data = [['Fatura No', 'ETTN', 'Tarih', 'Tedarikçi', 'Neden'], ...rows.map(row => ['fatura_no', 'uuid', 'issue_date', 'supplier', 'message'].map(key => row[key]))];
                    const url = URL.createObjectURL(new Blob(['\uFEFF' + data.map(row => row.map(cell).join(';')).join('\r\n')], {type: 'text/csv;charset=utf-8'}));
                    const link = document.createElement('a'); link.href = url; link.download = 'edm-aktarilamayan-faturalar.csv'; link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                }
            } catch (error) { Swal.fire('Döküm', error.message || 'Döküm alınamadı.', 'warning'); }
        });
        pollSyncJob();

        return {start(range) {
            const syncRange = {...range, list_type: listType};
            $(buttonSelector).prop('disabled', true);
            jobRequest('sync_job_start', syncRange)
                .done(res => {
                    if (res.status === 'success') {
                        renderSyncJob(res.data);
                        Swal.fire({
                            icon: res.data.job_status === 'paused' ? 'warning' : 'info',
                            title: res.data.job_status === 'paused' ? 'Aktarım duraklatıldı' : 'Aktarım arka planda sürüyor',
                            text: res.data.job_status === 'paused' ? res.data.message : 'İlerlemeyi sayfadaki aktarım alanından izleyebilirsiniz. Sayfayı kapatsanız da işlem devam eder.'
                        });
                    } else Swal.fire('Aktarım başlatılamadı', res.message || 'Tekrar deneyin.', 'warning');
                })
                .fail(xhr => Swal.fire('Aktarım başlatılamadı', xhr.responseJSON?.message || 'Sunucuya ulaşılamadı.', 'warning'))
                .always(() => {
                    $(buttonSelector).prop('disabled', !!syncJob && ['queued', 'running'].includes(syncJob.job_status));
                });
        }};
    };

    /**
     * E-Fatura Tahsilat Modalı ve Yönetimi
     */
    let globalPaymentSavedCallback = null;
    let tahsilatDatePicker = null;

    window.efaturaOpenTahsilatModal = function (invoiceId, onSaved) {
        globalPaymentSavedCallback = onSaved || null;
        const $ = window.jQuery;
        const modalEl = document.getElementById('modalTahsilatEkle');
        if (!modalEl || !$) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        
        // Reset form
        $('#formTahsilatEkle')[0].reset();
        $('#tahsilatInvoiceId').val(invoiceId);
        $('#tahsilatMusteriUnvan').text('Yükleniyor...');
        $('#tahsilatMusteriVkn').text('-');
        $('#tahsilatFaturaNo').text('-');
        $('#tahsilatFaturaTutari').text('0,00 ₺');
        $('#tahsilatOdenenTutar').text('0,00 ₺');
        $('#tahsilatKalanTutar').text('0,00 ₺');
        $('#tblTahsilatGecmisiBody').html('<tr><td colspan="6" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Yükleniyor...</td></tr>');
        $('#tahsilatAdetBadge').text('0 Kayıt');

        // Select2 Başlat
        if ($.fn.select2) {
            $('#modalTahsilatEkle .select2').select2({
                dropdownParent: $('#modalTahsilatEkle'),
                width: '100%'
            });
        }

        // Tarih seçici başlat
        const $tarihInput = $('#islem_tarihi, #tahsilatTarih');
        if (typeof flatpickr !== 'undefined' && $tarihInput.length) {
            if (!tahsilatDatePicker) {
                tahsilatDatePicker = flatpickr($tarihInput[0], {
                    locale: 'tr',
                    dateFormat: 'd.m.Y',
                    defaultDate: new Date()
                });
            } else {
                tahsilatDatePicker.setDate(new Date());
            }
        } else {
            const today = new Date();
            const d = String(today.getDate()).padStart(2, '0');
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const y = today.getFullYear();
            $tarihInput.val(`${d}.${m}.${y}`);
        }

        modal.show();

        // API'den fatura ve kasa bilgilerini çek
        $.ajax({
            url: 'api/efatura-api.php?action=get_invoice_payment_info&invoice_id=' + encodeURIComponent(invoiceId),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (res.status === 'success' && res.data) {
                window.efaturaRenderTahsilatData(res.data);
            } else {
                Swal.fire('Hata', res.message || 'Fatura bilgileri alınamadı.', 'error');
                modal.hide();
            }
        }).fail(function (xhr) {
            Swal.fire('Hata', xhr.responseJSON?.message || 'Sunucu ile bağlantı kurulamadı.', 'error');
            modal.hide();
        });
    };

    window.efaturaRenderTahsilatData = function (data) {
        const $ = window.jQuery;
        const inv = data.invoice;
        const kasalar = data.kasalar || [];
        const payments = data.payments || [];

        $('#tahsilatMusteriUnvan').text(inv.alici_unvan || '-').attr('title', inv.alici_unvan || '');
        $('#tahsilatMusteriVkn').text(inv.alici_vkn_tckn ? ('VKN/TCKN: ' + inv.alici_vkn_tckn) : '-');
        $('#tahsilatFaturaNo').text(inv.fatura_no || 'Taslak');
        $('#tahsilatFaturaTutari').text(inv.odenecek_tutar);
        $('#tahsilatOdenenTutar').text(inv.toplam_odenen);
        $('#tahsilatKalanTutar').text(inv.kalan_tutar);
        $('#tahsilatParaBirimi').val(inv.para_birimi || 'TRY');
        $('#tahsilatPbLabel').text(inv.para_birimi || 'TRY');

        // Kalan tutarı varsayılan giriş olarak yaz
        const kalanRaw = Number(inv.kalan_tutar_raw || 0);
        const formatDecimal = (num) => num.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        $('#tutar, #tahsilatTutar').val(kalanRaw > 0 ? formatDecimal(kalanRaw) : '0,00');

        // Açıklama varsayılan
        const defaultDesc = `${inv.fatura_no} nolu fatura tahsilatı (${inv.alici_unvan})`;
        $('#aciklama, #tahsilatAciklama').val(defaultDesc);

        // Tahsilat tipi sıfırla
        $('#tahsilatTipi').val('nakit').trigger('change');

        // Kasalar Selectbox'ı doldur
        const $kasaSelect = $('#tahsilatKasaId, #kasa_id');
        $kasaSelect.empty();
        $kasaSelect.append('<option value="">-- Hesap Seçiniz --</option>');
        
        let selectedKasa = '';
        kasalar.forEach(function (k) {
            const option = $('<option></option>')
                .attr('value', k.id)
                .text(`${k.kasa_adi} (${k.para_birimi || 'TRY'})`);
            
            if (k.varsayilan && !selectedKasa) {
                selectedKasa = k.id;
            }
            $kasaSelect.append(option);
        });

        if (selectedKasa) {
            $kasaSelect.val(selectedKasa).trigger('change');
        } else if (kasalar.length > 0) {
            $kasaSelect.val(kasalar[0].id).trigger('change');
        } else {
            $kasaSelect.trigger('change');
        }

        // Tahsilat Geçmişi Tablosunu Oluştur
        const $gecmisWrapper = $('#tahsilatGecmisiWrapper');
        const $tbody = $('#tblTahsilatGecmisiBody');
        $tbody.empty();

        if (payments.length === 0) {
            $gecmisWrapper.addClass('d-none');
            $('#tahsilatAdetBadge').text('0');
        } else {
            $gecmisWrapper.removeClass('d-none');
            $('#tahsilatAdetBadge').text(payments.length);

            payments.forEach(function (p) {
                const tipBadges = {
                    nakit: '<span class="badge bg-success-subtle text-success">Nakit</span>',
                    banka: '<span class="badge bg-primary-subtle text-primary">Banka/Havale</span>',
                    kredi_karti: '<span class="badge bg-info-subtle text-info">Kredi Kartı</span>',
                    cek: '<span class="badge bg-warning-subtle text-warning">Çek</span>',
                    senet: '<span class="badge bg-dark-subtle text-dark">Senet</span>',
                    diger: '<span class="badge bg-secondary-subtle text-secondary">Diğer</span>'
                };
                const tipHtml = tipBadges[p.tahsilat_tipi] || `<span class="badge bg-light text-dark">${p.tahsilat_tipi}</span>`;

                const tr = `
                    <tr>
                        <td class="fw-semibold text-dark">${p.islem_tarihi}</td>
                        <td class="text-truncate" style="max-width: 130px;" title="${p.kasa_adi}">${p.kasa_adi}</td>
                        <td>${tipHtml}</td>
                        <td class="text-end fw-bold font-monospace text-success">${p.tutar}</td>
                        <td class="text-truncate text-muted" style="max-width: 150px;" title="${p.aciklama}">${p.aciklama}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-subtle-danger p-0 rounded btn-delete-tahsilat" data-id="${p.id}" title="Tahsilatı Sil" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="bx bx-trash font-size-12"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });
        }
    };

    // Global Dinleyicileri Kaydet
    document.addEventListener('DOMContentLoaded', function () {
        const $ = window.jQuery;
        if (!$) return;

        // Form Submit
        $(document).on('submit', '#formTahsilatEkle', function (e) {
            e.preventDefault();
            const $btn = $('#btnSubmitTahsilat');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<div class="spinner-border spinner-border-sm me-1"></div> Kaydediliyor...');

            const formData = new FormData(this);
            formData.append('action', 'save_invoice_payment');

            $.ajax({
                url: 'api/efatura-api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: res.message || 'Tahsilat başarıyla kaydedildi.',
                        timer: 1800,
                        showConfirmButton: false
                    });

                    if (res.data) {
                        window.efaturaRenderTahsilatData(res.data);
                    }

                    if (typeof globalPaymentSavedCallback === 'function') {
                        globalPaymentSavedCallback();
                    }
                } else {
                    Swal.fire('Hata', res.message || 'Tahsilat kaydedilemedi.', 'error');
                }
            }).fail(function (xhr) {
                Swal.fire('Hata', xhr.responseJSON?.message || 'İşlem sırasında bir hata oluştu.', 'error');
            }).always(function () {
                $btn.prop('disabled', false).html(originalHtml);
            });
        });

        // Tahsilat Silme
        $(document).on('click', '.btn-delete-tahsilat', function () {
            const paymentId = $(this).data('id');
            const invoiceId = $('#tahsilatInvoiceId').val();

            Swal.fire({
                title: 'Tahsilatı Sil',
                text: 'Bu tahsilat kaydını ve ilişkili kasa hareketini silmek istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'Vazgeç'
            }).then(function (result) {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api/efatura-api.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'delete_invoice_payment',
                            payment_id: paymentId,
                            invoice_id: invoiceId
                        }
                    }).done(function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: res.message || 'Tahsilat kaydı silindi.',
                                timer: 1500,
                                showConfirmButton: false
                            });

                            if (res.data) {
                                window.efaturaRenderTahsilatData(res.data);
                            }

                            if (typeof globalPaymentSavedCallback === 'function') {
                                globalPaymentSavedCallback();
                            }
                        } else {
                            Swal.fire('Hata', res.message || 'Tahsilat silinemedi.', 'error');
                        }
                    }).fail(function (xhr) {
                        Swal.fire('Hata', xhr.responseJSON?.message || 'İşlem başarısız oldu.', 'error');
                    });
                }
            });
        });
    });

    /**
     * E-Fatura Gelişmiş Sütun Yönetimi (ColReorder, ColVis & Sürükle-Bırak Gizleme - puantoryeni Standardı)
     */
    window.efaturaInitColumnManagement = function ({ table, tableId, storageKey }) {
        const $ = window.jQuery;
        const $table = $(tableId);
        const $columnList = $('#columnList');
        if (!$table.length || !table) return;

        const $tableWrapper = $table.closest('.table-responsive, .dt-container, .card-body, .card, div');
        $tableWrapper.css('position', 'relative');

        let $dropzone = $tableWrapper.find('.dt-hide-dropzone-overlay');
        if (!$dropzone.length) {
            $dropzone = $(`
                <div class="dt-hide-dropzone-overlay">
                    <i class="bx bx-hide"></i>
                    <span>Sütunu gizlemek için buraya bırakın</span>
                </div>
            `).appendTo($tableWrapper);
        }

        let columnSortable = null;
        let isInternalReorder = false;

        // 1. Sütun Görünürlük Listesini Oluştur
        function renderColumnList() {
            if (!$columnList.length) return;
            $columnList.empty();

            let currentOrder = [];
            try {
                if (table.colReorder && typeof table.colReorder.order === 'function') {
                    currentOrder = table.colReorder.order();
                }
            } catch (e) {}

            if (!currentOrder || currentOrder.length === 0) {
                currentOrder = table.columns().indexes().toArray();
            }

            currentOrder.forEach(function (colIdx) {
                const column = table.column(colIdx);
                const $header = $(column.header());
                let title = $header.clone().find('.dt-filter-mode-trigger, .dt-filter-input, svg, i, .form-check').remove().end().text().trim();
                
                // Sistem sütunları (#, Checkbox, Sıra, İşlemler) listelenmez
                if (!title || title === '#' || title === 'SIRA' || title === 'İŞLEMLER' || $header.find('#checkAll').length) {
                    return;
                }

                const isVisible = column.visible();
                const item = $(`
                    <div class="dropdown-item py-1.5 px-2 d-flex align-items-center column-order-item rounded-2 hover-bg-light" data-column="${colIdx}" style="cursor: default; gap: 8px;">
                        <div class="drag-handle text-muted cursor-move" style="width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: grab;" title="Sıralamak için sürükleyin">
                            <i class="bx bx-menu font-size-16 text-secondary"></i>
                        </div>
                        <div class="form-check flex-grow-1 mb-0 d-flex align-items-center gap-2">
                            <input class="form-check-input col-toggle-check shadow-none m-0" type="checkbox" id="colCheck_${tableId.replace('#','')}_${colIdx}" data-column="${colIdx}" ${isVisible ? 'checked' : ''} style="cursor: pointer;">
                            <label class="form-check-label w-100 cursor-pointer fw-semibold font-size-12 text-dark mb-0" for="colCheck_${tableId.replace('#','')}_${colIdx}" style="cursor: pointer;">
                                ${title}
                            </label>
                        </div>
                    </div>
                `);
                $columnList.append(item);
            });

            // Prevent dropdown from closing when dragging handle
            $columnList.off('mousedown touchstart', '.drag-handle').on('mousedown touchstart', '.drag-handle', function (e) {
                e.stopPropagation();
            });

            // SortableJS ile Liste İçi Sıralama
            if (typeof Sortable !== 'undefined') {
                if (columnSortable) columnSortable.destroy();
                columnSortable = new Sortable($columnList[0], {
                    animation: 150,
                    handle: '.drag-handle',
                    draggable: '.column-order-item',
                    forceFallback: true,
                    ghostClass: 'bg-primary-subtle',
                    onEnd: function () {
                        let newDropdownOrder = [];
                        $columnList.find('.column-order-item').each(function () {
                            const cIdx = parseInt($(this).attr('data-column'));
                            if (!isNaN(cIdx)) newDropdownOrder.push(cIdx);
                        });

                        if (table.colReorder && typeof table.colReorder.order === 'function' && newDropdownOrder.length > 0) {
                            isInternalReorder = true;
                            let currentFullOrder = table.colReorder.order();
                            let finalFullOrder = [...currentFullOrder];
                            let dropdownSet = new Set(newDropdownOrder);
                            let availableSlots = [];
                            currentFullOrder.forEach((origIdx, visIdx) => {
                                if (dropdownSet.has(origIdx)) availableSlots.push(visIdx);
                            });

                            if (availableSlots.length === newDropdownOrder.length) {
                                newDropdownOrder.forEach((origIdx, i) => {
                                    finalFullOrder[availableSlots[i]] = origIdx;
                                });
                                table.colReorder.order(finalFullOrder);
                            }
                            isInternalReorder = false;
                        }
                    }
                });
            }
        }

        // 2. Checkbox Değişimi ile Sütun Açma / Kapatma
        $columnList.off('change', '.col-toggle-check').on('change', '.col-toggle-check', function (e) {
            e.stopPropagation();
            const colIdx = parseInt($(this).data('column'));
            const isChecked = $(this).is(':checked');
            const column = table.column(colIdx);
            column.visible(isChecked);
            setupDraggableHeaders();
        });

        // 3. Sıfırla Butonu
        $('#btnResetColumns').off('click').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (table.state && typeof table.state.clear === 'function') {
                table.state.clear();
            }
            if (storageKey) {
                try { localStorage.removeItem(storageKey); } catch (ex) {}
            }
            window.location.reload();
        });

        // 4. Tablo Başlıklarını Sürükle-Bırak ile Gizleme (Drag to Hide Dropzone Overlay)
        function setupDraggableHeaders() {
            const $thead = $table.find('thead');
            $thead.find('tr:first th').each(function () {
                const $th = $(this);
                const colIdx = table.column(this).index();
                const title = $th.clone().find('.dt-filter-mode-trigger, .dt-filter-input, svg, i, .form-check').remove().end().text().trim();

                // Sistem sütunları (Checkbox, Sıra, İşlemler) sürüklenerek gizlenemez
                if (!title || title === '#' || title === 'SIRA' || title === 'İŞLEMLER' || $th.find('#checkAll').length) {
                    $th.removeClass('dt-draggable-header');
                    $th.off('.dtColHide');
                    return;
                }

                $th.addClass('dt-draggable-header');

                $th.off('mousedown.dtColHide').on('mousedown.dtColHide', function (e) {
                    if ($(e.target).closest('.dt-filter-mode-trigger, .dt-filter-control, .dt-filter-input, .select2, input, button, a').length) {
                        return;
                    }

                    const thElement = this;
                    const thTitle = title;
                    const theadOffset = $thead.offset();
                    const theadBottom = theadOffset.top + $thead.outerHeight();
                    let isBelowHeader = false;

                    function onDocMouseMove(moveEvent) {
                        if (moveEvent.pageY > theadBottom + 20) {
                            isBelowHeader = true;
                            $dropzone.addClass('active');
                        } else {
                            isBelowHeader = false;
                            $dropzone.removeClass('active');
                        }
                    }

                    function onDocMouseUp() {
                        $(document).off('mousemove.dtColHide', onDocMouseMove);
                        $(document).off('mouseup.dtColHide', onDocMouseUp);

                        $dropzone.removeClass('active');

                        if (isBelowHeader) {
                            setTimeout(function () {
                                $('.dt-colreorder-drag, .dt-colreorder-moving, .dt-colreorder-floating, .dt-colreorder-insert, .DTCR_clonedTable').remove();
                            }, 10);

                            const targetCol = table.column(thElement);
                            if (targetCol && targetCol.length) {
                                targetCol.visible(false, true);
                                table.columns.adjust().draw(false);
                                renderColumnList();
                                setupDraggableHeaders();

                                if (window.Swal) {
                                    Swal.fire({
                                        toast: true,
                                        position: 'top-end',
                                        icon: 'info',
                                        title: `"${thTitle || 'Sütun'}" gizlendi`,
                                        html: '<span class="text-secondary small">Geri getirmek için Sütunlar menüsünü kullanabilirsiniz.</span>',
                                        showConfirmButton: false,
                                        timer: 2800,
                                        timerProgressBar: true
                                    });
                                }
                            }
                        }
                    }

                    $(document).on('mousemove.dtColHide', onDocMouseMove);
                    $(document).on('mouseup.dtColHide', onDocMouseUp);
                });
            });
        }

        // DataTables Olaylarını Dinle
        table.off('column-visibility.dt.manager').on('column-visibility.dt.manager', function () {
            renderColumnList();
            setupDraggableHeaders();
        });

        table.off('column-reorder.dt.manager').on('column-reorder.dt.manager', function () {
            if (!isInternalReorder) {
                renderColumnList();
                setupDraggableHeaders();
            }
        });

        table.off('draw.dt.manager').on('draw.dt.manager', function () {
            setupDraggableHeaders();
        });

        renderColumnList();
        setupDraggableHeaders();
    };
})();
