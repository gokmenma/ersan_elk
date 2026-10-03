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
})();
