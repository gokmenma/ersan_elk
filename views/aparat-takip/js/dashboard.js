$(document).ready(function () {
    'use strict';

    const API_URL = 'views/aparat-takip/api.php';
    const config = window.aparatDashConfig || {};

    let currentPeriod = 'this_month';
    let customStartDate = '';
    let customEndDate = '';
    let lastResponse = null;

    const charts = {
        trend: null,
        pool: null,
        types: null
    };

    const esc = (val) => $('<div>').text(val === null || val === undefined ? '' : String(val)).html();

    const fmtNum = (val) => {
        const n = parseInt(val || 0, 10);
        if (isNaN(n)) return '0';
        return n.toLocaleString('tr-TR');
    };

    const fmtDateTime = (str) => {
        if (!str) return '-';
        const parts = String(str).trim().split(' ');
        const d = (parts[0] || '').split('-');
        if (d.length !== 3) return esc(str);
        const time = parts[1] ? parts[1].substring(0, 5) : '';
        return {
            date: `${d[2]}.${d[1]}.${d[0]}`,
            time: time
        };
    };

    const isDarkTheme = () => {
        const html = document.documentElement;
        return html.getAttribute('data-bs-theme') === 'dark'
            || html.getAttribute('data-theme-preset') === 'macos-dark'
            || html.getAttribute('data-theme-preset') === 'dark-pro';
    };

    // 1. Özet Kartları Aç/Kapa Standardı (AGENTS.md 3 & 8.2)
    const toggleBtn = $('#btnToggleSummaryCards');
    const syncSummaryToggleUi = () => {
        const hidden = document.documentElement.classList.contains('aparatdash-summary-hidden');
        const label = hidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle';
        toggleBtn.attr({
            'aria-expanded': hidden ? 'false' : 'true',
            'title': label,
            'aria-label': label
        });
        toggleBtn.find('i').attr('class', hidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
    };
    syncSummaryToggleUi();

    toggleBtn.on('click', function () {
        const willHide = !document.documentElement.classList.contains('aparatdash-summary-hidden');
        document.documentElement.classList.toggle('aparatdash-summary-hidden', willHide);
        try {
            localStorage.setItem('aparat_dash_summary_cards_state', willHide ? 'hidden' : 'visible');
        } catch (e) {}
        syncSummaryToggleUi();
    });

    // 2. Select2 & Flatpickr Başlatma
    const tipSelect = $('#dash_aparat_tip');
    if ($.fn.select2 && tipSelect.length) {
        tipSelect.select2({
            width: '100%',
            minimumResultsForSearch: 6
        });
    }

    if (typeof flatpickr === 'function') {
        flatpickr('#dash_custom_bas, #dash_custom_bit', {
            dateFormat: 'd.m.Y',
            locale: (flatpickr.l10ns && flatpickr.l10ns.tr) ? flatpickr.l10ns.tr : 'default',
            allowInput: true
        });
    }

    const showPreloader = () => $('#aparatDashPreloader').addClass('show');
    const hidePreloader = () => $('#aparatDashPreloader').removeClass('show');

    const getSelectedTipId = () => parseInt(tipSelect.val() || '0', 10) || 0;

    // 3. Veri Yükleme
    const loadDashboard = () => {
        const tipId = getSelectedTipId();

        if (window.aparatDashIlk && currentPeriod === 'this_month' && tipId === 0) {
            const prefetch = window.aparatDashIlk;
            window.aparatDashIlk = null;
            prefetch.then((res) => {
                if (res && res.status === 'success') {
                    lastResponse = res;
                    renderAll(res);
                } else {
                    fetchAjax();
                }
            });
            return;
        }

        fetchAjax();
    };

    const fetchAjax = () => {
        showPreloader();
        $.ajax({
            url: API_URL,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'dashboard-data',
                period: currentPeriod,
                start_date: currentPeriod === 'custom' ? customStartDate : '',
                end_date: currentPeriod === 'custom' ? customEndDate : '',
                aparat_tip_id: getSelectedTipId()
            },
            success: function (res) {
                if (res && res.status === 'success') {
                    lastResponse = res;
                    renderAll(res);
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire('Hata', (res && res.message) ? res.message : 'Dashboard verileri alınamadı.', 'error');
                }
            },
            error: function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Hata', 'Sunucu ile bağlantı kurulamadı.', 'error');
                }
            },
            complete: function () {
                hidePreloader();
            }
        });
    };

    // 4. Tüm Bileşenleri Çizme
    const renderAll = (res) => {
        const inv = res.inventory || {};
        const ps = res.period_summary || {};
        const period = res.period || {};

        $('#activePeriodBadge').text(period.label || 'Bu Ay');
        $('#trendSubtitle').text(
            period.granularity === 'monthly'
                ? `${period.label || ''} — aylık kesme, açma, ekibe çıkış ve iade seyri`
                : `${period.label || ''} — günlük kesme, açma, ekibe çıkış ve iade seyri`
        );

        renderAlertStrip(inv);
        renderKpiCards(inv, ps);
        renderTrendChart(res.trend || {});
        renderPoolDonut(res.pool_distribution || {}, inv);
        renderTypeBreakdown(res.type_breakdown || []);
        renderTeamRanking(res.team_ranking || []);
        renderAttentionPanes(res.critical_alerts || {}, res.long_standing_field || [], inv);
        renderRecentMovements(res.recent_movements || []);
    };

    // 4.1. Operasyonel Uyarı Şeridi
    const renderAlertStrip = (inv) => {
        const strip = $('#aparatAlertStrip').empty();
        const pills = [];
        const listHref = config.canViewList ? 'index.php?p=aparat-takip/list' : '#';

        if ((inv.tutarsiz_satir_sayisi || 0) > 0) {
            pills.push(`
                <a href="${listHref}" class="alert-strip-pill bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bx bx-error-circle fs-6"></i>
                    <span>Ana defter ile bakiye tablosunda <strong>${fmtNum(inv.tutarsiz_satir_sayisi)}</strong> fark var</span>
                </a>
            `);
        }

        if ((inv.kritik_toplam || 0) > 0) {
            pills.push(`
                <a href="${listHref}" class="alert-strip-pill bg-warning-subtle text-warning border border-warning-subtle">
                    <i class="bx bx-error fs-6"></i>
                    <span>Kritik stok uyarısı: <strong>${fmtNum(inv.kritik_depo_sayisi)}</strong> depo, <strong>${fmtNum(inv.kritik_ekip_sayisi)}</strong> ekip</span>
                </a>
            `);
        }

        if ((inv.negatif_ekip_sayisi || 0) > 0) {
            pills.push(`
                <a href="${listHref}" class="alert-strip-pill bg-danger-subtle text-danger border border-danger-subtle">
                    <i class="bx bx-minus-circle fs-6"></i>
                    <span>Eksi bakiyeye düşen <strong>${fmtNum(inv.negatif_ekip_sayisi)}</strong> ekip mevcut</span>
                </a>
            `);
        }

        if ((inv.bekleyen_transfer_sayisi || 0) > 0) {
            const trHref = (config.canViewList && config.canViewAdvanced) ? 'index.php?p=aparat-takip/list#pane-transferler' : listHref;
            pills.push(`
                <a href="${trHref}" class="alert-strip-pill bg-info-subtle text-info border border-info-subtle">
                    <i class="bx bx-git-compare fs-6"></i>
                    <span>Onay bekleyen <strong>${fmtNum(inv.bekleyen_transfer_sayisi)}</strong> ekip transferi var</span>
                </a>
            `);
        }

        if (inv.acik_sayim) {
            const syHref = (config.canViewList && config.canViewAdvanced) ? 'index.php?p=aparat-takip/list#pane-sayim' : listHref;
            pills.push(`
                <a href="${syHref}" class="alert-strip-pill bg-primary-subtle text-primary border border-primary-subtle">
                    <i class="bx bx-check-square fs-6"></i>
                    <span>Açık Sayım: <strong>${esc(inv.acik_sayim.baslik)}</strong> (%${esc(inv.acik_sayim.oran)} tamamlandı)</span>
                </a>
            `);
        }

        if (pills.length > 0) {
            strip.html(pills.join('')).removeClass('d-none').addClass('d-flex');
        } else {
            strip.addClass('d-none').removeClass('d-flex');
        }
    };

    // 4.2. KPI Kartları
    const renderKpiCards = (inv, ps) => {
        const toplamEnv = parseInt(inv.toplam_envanter || 0, 10);
        const depoAdet = parseInt(inv.depo_adet || 0, 10);
        const depoOran = toplamEnv > 0 ? Math.max(0, Math.round((depoAdet / toplamEnv) * 100)) : 0;

        $('#kpi_depo_adet').text(`${fmtNum(depoAdet)} Adet`);
        $('#kpi_toplam_envanter').text(`${fmtNum(toplamEnv)} Adet`);
        $('#kpi_aktif_tip_label').text(`Aktif Tip: ${fmtNum(inv.aktif_tip_sayisi)} Çeşit`);
        $('#kpi_depo_oran').text(`%${depoOran} Depoda`);

        // Kart 2: Ekipler
        $('#kpi_ekip_adet').text(`${fmtNum(inv.ekip_adet)} Adet`);
        $('#kpi_zimmetli_ekip').text(`${fmtNum(inv.zimmetli_ekip_sayisi)} / ${fmtNum(inv.toplam_ekip_sayisi)} Ekip`);
        $('#kpi_kritik_ozet').text(`Kritik Uyarı: ${fmtNum(inv.kritik_toplam)}`);

        const ekipBadge = $('#kpi_ekip_durum_badge');
        if ((inv.negatif_ekip_sayisi || 0) > 0) {
            ekipBadge
                .attr('class', 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold')
                .text(`${fmtNum(inv.negatif_ekip_sayisi)} Ekipte Eksi`);
        } else if ((inv.kritik_toplam || 0) > 0) {
            ekipBadge
                .attr('class', 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold')
                .text('Takviye Gerekli');
        } else {
            ekipBadge
                .attr('class', 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold')
                .text('Stok Dengeli');
        }

        // Kart 3: Saha & Kesme/Açma
        $('#kpi_saha_adet').text(`${fmtNum(inv.saha_adet)} Adet`);
        $('#kpi_kesme_acma_oran').html(
            `<span class="text-warning">${fmtNum(ps.kesme_adet)} Kesme</span> · <span class="text-success">${fmtNum(ps.acma_adet)} Açma</span>`
        );
        $('#kpi_uzun_saha_label').text(`30+ Gün Takılı: ${fmtNum(inv.uzun_sureli_saha_sayisi)} Abone`);
        $('#kpi_donus_badge').text(`Dönüş: %${String(ps.geri_donus_orani || 0).replace('.', ',')}`);

        // Kart 4: Dönem Depo Akışı
        $('#kpi_depo_cikis').text(`${fmtNum(ps.depo_cikis_adet)} Ekibe Verildi`);
        $('#kpi_giris_iade').html(
            `<span class="text-success">+${fmtNum(ps.depo_giris_adet)} Giriş</span> · <span class="text-info">${fmtNum(ps.depo_iade_adet)} İade</span>`
        );
        $('#kpi_hurda_kayip_label').text(`Toplam Hurda/Kayıp: ${fmtNum(inv.hurda_kayip_adet)}`);

        const trBadge = $('#kpi_transfer_badge');
        if ((inv.bekleyen_transfer_sayisi || 0) > 0) {
            trBadge
                .attr('class', 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold')
                .text(`${fmtNum(inv.bekleyen_transfer_sayisi)} Bekleyen Transfer`);
        } else {
            trBadge
                .attr('class', 'badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-0.5 font-size-11 fw-semibold')
                .text(`${fmtNum(ps.olay_sayisi)} Dönem Hareketi`);
        }
    };

    // 4.3. Hareket Trendi Grafiği (ApexCharts)
    const renderTrendChart = (trend) => {
        if (typeof ApexCharts === 'undefined') {
            setTimeout(() => renderTrendChart(trend), 100);
            return;
        }

        const el = document.querySelector('#chartMovementTrend');
        if (!el) return;

        const dark = isDarkTheme();
        const axisColor = dark ? '#94a3b8' : '#64748b';
        const gridColor = dark ? 'rgba(255,255,255,0.07)' : '#f1f5f9';
        const categories = trend.categories || [];

        const options = {
            chart: {
                type: 'area',
                height: 310,
                fontFamily: 'inherit',
                toolbar: { show: false },
                animations: { enabled: false },
                background: 'transparent'
            },
            theme: { mode: dark ? 'dark' : 'light' },
            series: [
                { name: 'Kesme (Takılan)', data: trend.kesme || [] },
                { name: 'Açma (Sökülen)', data: trend.acma || [] },
                { name: 'Ekibe Verilen', data: trend.depo_cikis || [] },
                { name: 'Depoya İade', data: trend.depo_iade || [] }
            ],
            colors: ['#f59e0b', '#10b981', '#2563eb', '#06b6d4'],
            stroke: {
                curve: 'smooth',
                width: [2.5, 2.5, 2, 2]
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.30,
                    opacityTo: 0.03,
                    stops: [0, 95, 100]
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: categories,
                tickAmount: Math.min(12, Math.max(1, categories.length)),
                labels: {
                    rotate: 0,
                    style: { colors: axisColor, fontSize: '11px' }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: (v) => fmtNum(Math.round(v)),
                    style: { colors: axisColor, fontSize: '11px' }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                labels: { colors: axisColor }
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 4
            },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                y: { formatter: (v) => `${fmtNum(v)} Adet` }
            },
            noData: { text: 'Seçili dönemde hareket kaydı yok' }
        };

        if (charts.trend) {
            charts.trend.destroy();
        }
        charts.trend = new ApexCharts(el, options);
        charts.trend.render();
    };

    // 4.4. Havuz Dağılımı Donut Grafiği
    const renderPoolDonut = (pool, inv) => {
        const raw = pool.raw || {};
        $('#leg_depo').text(fmtNum(raw.depo || 0));
        $('#leg_ekip').text(fmtNum(raw.ekip || 0));
        $('#leg_saha').text(fmtNum(raw.saha || 0));

        if (typeof ApexCharts === 'undefined') {
            setTimeout(() => renderPoolDonut(pool, inv), 100);
            return;
        }

        const el = document.querySelector('#chartPoolDonut');
        if (!el) return;

        const dark = isDarkTheme();
        const series = pool.series || [];
        const sum = series.reduce((acc, v) => acc + (parseInt(v, 10) || 0), 0);

        const options = {
            chart: {
                type: 'donut',
                height: 235,
                fontFamily: 'inherit',
                animations: { enabled: false },
                background: 'transparent'
            },
            theme: { mode: dark ? 'dark' : 'light' },
            series: sum > 0 ? series : [],
            labels: pool.labels || ['Depoda', 'Ekiplerde', 'Abonede Takılı', 'Hurda', 'Kayıp'],
            colors: ['#2563eb', '#10b981', '#0ea5e9', '#f59e0b', '#ef4444'],
            dataLabels: { enabled: false },
            stroke: {
                width: 2,
                colors: [dark ? '#1c1e26' : '#ffffff']
            },
            legend: {
                position: 'bottom',
                fontSize: '11.5px',
                labels: { colors: dark ? '#cbd5e1' : '#475569' }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '11px',
                                color: dark ? '#94a3b8' : '#64748b'
                            },
                            value: {
                                show: true,
                                fontSize: '18px',
                                fontWeight: 800,
                                color: dark ? '#f8fafc' : '#0f172a',
                                formatter: (v) => fmtNum(v)
                            },
                            total: {
                                show: true,
                                label: 'Toplam Varlık',
                                fontSize: '11px',
                                color: dark ? '#94a3b8' : '#64748b',
                                formatter: () => fmtNum(inv.toplam_envanter || 0)
                            }
                        }
                    }
                }
            },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                y: { formatter: (v) => `${fmtNum(v)} Adet` }
            },
            noData: { text: 'Envanter kaydı bulunmuyor' }
        };

        if (charts.pool) {
            charts.pool.destroy();
        }
        charts.pool = new ApexCharts(el, options);
        charts.pool.render();
    };

    // 4.5. Aparat Tipi Kırılımı Grafiği + Tablosu
    const renderTypeBreakdown = (types) => {
        const tbody = $('#typeBreakdownTbody').empty();

        if (!types || types.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-3">Tanımlı aparat tipi bulunamadı.</td></tr>');
        } else {
            types.forEach((t) => {
                let depoCell = `<span class="fw-semibold text-primary">${fmtNum(t.depo)}</span>`;
                if (t.depo_seviye === 'bitti') {
                    depoCell = `<span class="badge bg-danger-subtle text-danger border border-danger-subtle">${fmtNum(t.depo)} (Bitti)</span>`;
                } else if (t.depo_seviye === 'azaldi') {
                    depoCell = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle">${fmtNum(t.depo)} (Azaldı)</span>`;
                }

                const ekipCls = t.ekip < 0 ? 'text-danger fw-bold' : 'text-success fw-semibold';
                const pasifEtiket = parseInt(t.is_active, 10) === 0
                    ? ' <span class="badge bg-secondary-subtle text-secondary font-size-10">Pasif</span>'
                    : '';

                tbody.append(`
                    <tr>
                        <td>
                            <span class="badge bg-${esc(t.renk || 'primary')}-subtle text-${esc(t.renk || 'primary')} border border-${esc(t.renk || 'primary')}-subtle me-1 font-monospace">${esc(t.kod)}</span>
                            <span class="fw-semibold">${esc(t.ad)}</span>${pasifEtiket}
                        </td>
                        <td class="text-center">${depoCell}</td>
                        <td class="text-center ${ekipCls}">${fmtNum(t.ekip)}</td>
                        <td class="text-center text-info fw-semibold">${fmtNum(t.saha)}</td>
                        <td class="text-center text-muted">${fmtNum(t.hurda_kayip)}</td>
                        <td class="text-end fw-bold">${fmtNum(t.toplam)}</td>
                    </tr>
                `);
            });
        }

        if (typeof ApexCharts === 'undefined') {
            setTimeout(() => renderTypeBreakdown(types), 100);
            return;
        }

        const el = document.querySelector('#chartTypeBreakdown');
        if (!el) return;

        const dark = isDarkTheme();
        const axisColor = dark ? '#94a3b8' : '#64748b';
        const gridColor = dark ? 'rgba(255,255,255,0.07)' : '#f1f5f9';

        const labels = (types || []).map((t) => t.ad.length > 18 ? t.ad.substring(0, 17) + '…' : t.ad);
        const depoData = (types || []).map((t) => Math.max(0, parseInt(t.depo || 0, 10)));
        const ekipData = (types || []).map((t) => Math.max(0, parseInt(t.ekip || 0, 10)));
        const sahaData = (types || []).map((t) => Math.max(0, parseInt(t.saha || 0, 10)));
        const fireData = (types || []).map((t) => Math.max(0, parseInt(t.hurda_kayip || 0, 10)));

        const options = {
            chart: {
                type: 'bar',
                height: 250,
                stacked: true,
                fontFamily: 'inherit',
                toolbar: { show: false },
                animations: { enabled: false },
                background: 'transparent'
            },
            theme: { mode: dark ? 'dark' : 'light' },
            series: [
                { name: 'Depoda', data: depoData },
                { name: 'Ekiplerde', data: ekipData },
                { name: 'Sahada Takılı', data: sahaData },
                { name: 'Hurda / Kayıp', data: fireData }
            ],
            colors: ['#2563eb', '#10b981', '#0ea5e9', '#ef4444'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '48%',
                    borderRadius: 4
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: labels,
                labels: {
                    rotate: -15,
                    style: { colors: axisColor, fontSize: '11px', fontWeight: 600 }
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                min: 0,
                forceNiceScale: true,
                labels: {
                    formatter: (v) => fmtNum(Math.round(v)),
                    style: { colors: axisColor, fontSize: '11px' }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11.5px',
                labels: { colors: axisColor }
            },
            grid: {
                borderColor: gridColor,
                strokeDashArray: 4
            },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                y: { formatter: (v) => `${fmtNum(v)} Adet` }
            },
            noData: { text: 'Aparat tipi verisi bulunmuyor' }
        };

        if (charts.types) {
            charts.types.destroy();
        }
        charts.types = new ApexCharts(el, options);
        charts.types.render();
    };

    // 4.6. Ekip Zimmet & Saha Sıralaması
    const renderTeamRanking = (teams) => {
        const container = $('#teamRankingList').empty();

        if (!teams || teams.length === 0) {
            container.html('<div class="text-center text-muted py-4 font-size-12">Zimmetli veya aktif saha ekibi bulunamadı.</div>');
            return;
        }

        teams.forEach((ek, idx) => {
            const rank = idx + 1;
            let rankCls = 'rank-badge-other';
            if (rank === 1) rankCls = 'rank-badge-1';
            else if (rank === 2) rankCls = 'rank-badge-2';
            else if (rank === 3) rankCls = 'rank-badge-3';

            const baslik = ek.uyeler ? esc(ek.uyeler) : '<span class="text-muted fst-italic">Personel atanmamış</span>';
            const altBilgi = `${esc(ek.ekip_kodu)}${ek.bolge ? ' · ' + esc(ek.bolge) : ''}`;

            let uyariRozet = '';
            if (ek.negatif) {
                uyariRozet = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10 ms-1">Eksi Stok</span>';
            } else if ((ek.kritik_sayisi || 0) > 0) {
                uyariRozet = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-10 ms-1">${fmtNum(ek.kritik_sayisi)} Kritik</span>`;
            }

            const stokRenk = ek.toplam_stok < 0 ? 'text-danger' : (ek.toplam_stok > 0 ? 'text-success' : 'text-muted');

            container.append(`
                <div class="team-rank-row d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="rank-badge ${rankCls}">${rank}</span>
                        <div class="text-truncate">
                            <div class="fw-bold text-dark font-size-12 text-truncate">${baslik}${uyariRozet}</div>
                            <div class="text-muted font-size-11">${altBilgi}</div>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="fw-bold ${stokRenk} font-size-13">${fmtNum(ek.toplam_stok)} Adet</div>
                        <div class="text-muted font-size-10">
                            <span class="text-warning">${fmtNum(ek.donem_kesme)} Kesme</span> ·
                            <span class="text-success">${fmtNum(ek.donem_acma)} Açma</span>
                        </div>
                    </div>
                </div>
            `);
        });
    };

    // 4.7. Dikkat Gerektiren Kayıtlar (Kritik Stok + Sahada Bekleyen)
    const renderAttentionPanes = (kritik, sahaList, inv) => {
        const depoKritik = kritik.depo || [];
        const ekipKritik = kritik.ekipler || [];
        const toplamKritik = parseInt(kritik.toplam || 0, 10);

        $('#badgeKritikCount').text(fmtNum(toplamKritik));
        $('#badgeSahaCount').text(fmtNum(inv.sahada_kayit_sayisi || sahaList.length || 0));

        const pKritik = $('#attentionPaneKritik').empty();
        if (toplamKritik === 0) {
            pKritik.html(`
                <div class="text-center py-4 text-muted font-size-12">
                    <i class="bx bx-check-shield text-success fs-2 d-block mb-1"></i>
                    Depoda ve ekiplerde kritik eşik altında aparat bulunmuyor.
                </div>
            `);
        } else {
            depoKritik.forEach((d) => {
                const renk = d.seviye === 'bitti' ? 'danger' : 'warning';
                pKritik.append(`
                    <div class="team-rank-row d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark font-size-12"><i class="bx bx-buildings text-primary me-1"></i> Merkez Depo</div>
                            <div class="text-muted font-size-11">${esc(d.aparat_adi)} (Eşik: ${fmtNum(d.esik)})</div>
                        </div>
                        <span class="badge bg-${renk}-subtle text-${renk} border border-${renk}-subtle rounded-pill px-2 py-1 font-size-11 fw-bold">
                            ${fmtNum(d.adet)} Adet
                        </span>
                    </div>
                `);
            });

            ekipKritik.forEach((ek) => {
                const etiketler = (ek.eksikler || []).map((x) => {
                    const r = x.seviye === 'bitti' ? 'danger' : 'warning';
                    return `<span class="badge bg-${r}-subtle text-${r} border border-${r}-subtle font-size-10 me-1 mb-1">${esc(x.aparat_adi)}: ${fmtNum(x.adet)}/${fmtNum(x.esik)}</span>`;
                }).join('');

                pKritik.append(`
                    <div class="team-rank-row">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark font-size-12">${esc(ek.uyeler || ek.ekip_kodu)}</span>
                            <span class="text-muted font-size-11">${esc(ek.ekip_kodu)}</span>
                        </div>
                        <div>${etiketler}</div>
                    </div>
                `);
            });
        }

        const pSaha = $('#attentionPaneSaha').empty();
        if (!sahaList || sahaList.length === 0) {
            pSaha.html(`
                <div class="text-center py-4 text-muted font-size-12">
                    <i class="bx bx-map-pin text-info fs-2 d-block mb-1"></i>
                    Abone bazlı açık kesme kaydı bulunmuyor.
                </div>
            `);
        } else {
            sahaList.forEach((s) => {
                const gun = parseInt(s.gun_sayisi || 0, 10);
                const gunRenk = gun >= 30 ? 'danger' : (gun >= 14 ? 'warning' : 'info');
                const dt = fmtDateTime(s.tarih);
                const konum = [s.ilce, s.mahalle].filter(Boolean).join(' / ');
                pSaha.append(`
                    <div class="team-rank-row d-flex align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <div class="fw-bold text-dark font-size-12">
                                Abone: ${esc(s.abone_no || '-')}
                                <span class="text-muted fw-normal font-size-11">· ${esc(s.aparat_adi || '-')}</span>
                            </div>
                            <div class="text-muted font-size-11 text-truncate">
                                ${konum ? esc(konum) + ' · ' : ''}${esc(s.ekip_adi || '')} · ${dt.date || esc(s.tarih)}
                            </div>
                        </div>
                        <span class="badge bg-${gunRenk}-subtle text-${gunRenk} border border-${gunRenk}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold flex-shrink-0">
                            ${fmtNum(gun)} gün
                        </span>
                    </div>
                `);
            });
        }
    };

    // 4.8. Son Aparat Hareketleri Tablosu
    const renderRecentMovements = (rows) => {
        const tbody = $('#recentMovementsTbody').empty();

        if (!rows || rows.length === 0) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4 font-size-12">Henüz aparat hareketi bulunmuyor.</td></tr>');
            return;
        }

        rows.forEach((r) => {
            const dt = fmtDateTime(r.tarih);
            const adetSayi = parseInt(r.adet || 0, 10);
            const adetMetin = r.isaretli_mi
                ? (adetSayi > 0 ? `+${fmtNum(adetSayi)}` : fmtNum(adetSayi))
                : fmtNum(adetSayi);

            const ekipBilgi = r.ekip
                ? `<div class="fw-semibold font-size-12">${esc(r.ekip)}</div>`
                : '<div class="text-muted font-size-12">Merkez Depo</div>';

            const yapanBilgi = r.islem_yapan
                ? `<div class="text-muted font-size-11"><i class="bx bx-user me-0.5"></i>${esc(r.islem_yapan)}</div>`
                : '';

            tbody.append(`
                <tr>
                    <td class="text-nowrap">
                        <div class="fw-semibold font-size-12">${dt.date || '-'}</div>
                        <div class="text-muted font-size-11">${dt.time || ''}</div>
                    </td>
                    <td>
                        <span class="badge bg-${esc(r.hareket_renk)}-subtle text-${esc(r.hareket_renk)} border border-${esc(r.hareket_renk)}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                            <i class="bx ${esc(r.hareket_ikon)} me-0.5"></i> ${esc(r.hareket_adi)}
                        </span>
                    </td>
                    <td>
                        <span class="fw-semibold font-size-12">${esc(r.aparat_adi)}</span>
                        ${r.aparat_kod ? `<span class="text-muted font-size-11 ms-1">(${esc(r.aparat_kod)})</span>` : ''}
                    </td>
                    <td class="text-center fw-bold font-size-13">${adetMetin}</td>
                    <td>${ekipBilgi}${yapanBilgi}</td>
                    <td>
                        <span class="text-muted font-size-12 d-inline-block text-truncate" style="max-width: 210px;" title="${esc(r.aciklama)}">
                            ${r.aciklama ? esc(r.aciklama) : '-'}
                        </span>
                    </td>
                </tr>
            `);
        });
    };

    // 5. Etkileşimler
    $('#aparatPeriodGroup .period-btn').on('click', function () {
        const period = $(this).data('period');
        if (period === 'custom') {
            const modalEl = document.getElementById('aparatCustomDateModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            return;
        }

        $('#aparatPeriodGroup .period-btn').removeClass('active');
        $(this).addClass('active');
        currentPeriod = period;
        customStartDate = '';
        customEndDate = '';
        $('#customPeriodLabel').text('Özel');
        loadDashboard();
    });

    $('#btnApplyAparatCustomDate').on('click', function () {
        const bas = ($('#dash_custom_bas').val() || '').trim();
        const bit = ($('#dash_custom_bit').val() || '').trim();
        if (!bas || !bit) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('Uyarı', 'Başlangıç ve bitiş tarihlerini seçiniz.', 'warning');
            }
            return;
        }

        currentPeriod = 'custom';
        customStartDate = bas;
        customEndDate = bit;

        $('#aparatPeriodGroup .period-btn').removeClass('active');
        $('#btnCustomPeriod').addClass('active');
        $('#customPeriodLabel').text(`${bas} - ${bit}`);

        const modalEl = document.getElementById('aparatCustomDateModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        loadDashboard();
    });

    tipSelect.on('change', function () {
        loadDashboard();
    });

    $('#attentionTabGroup .btn').on('click', function () {
        const pane = $(this).data('pane');
        $('#attentionTabGroup .btn').removeClass('active');
        $(this).addClass('active');
        $('#attentionPaneKritik').toggleClass('d-none', pane !== 'kritik');
        $('#attentionPaneSaha').toggleClass('d-none', pane !== 'saha');
    });

    // Tema değişiminde grafikleri yeniden çiz
    const themeObserver = new MutationObserver(() => {
        if (lastResponse) {
            renderTrendChart(lastResponse.trend || {});
            renderPoolDonut(lastResponse.pool_distribution || {}, lastResponse.inventory || {});
            renderTypeBreakdown(lastResponse.type_breakdown || []);
        }
    });
    themeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme', 'data-theme-preset']
    });

    // İlk yükleme
    loadDashboard();
});
