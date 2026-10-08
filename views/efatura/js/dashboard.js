/**
 * E-Fatura & Finans Dashboard JavaScript Modülü
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let currentStartDate = '';
    let currentEndDate = '';
    let currentPeriod = 'this_month';

    // ApexCharts Değişkenleri
    let monthlyTrendChartInstance = null;
    let kdvTrendChartInstance = null;
    let kdvRatesChartInstance = null;
    let rawKdvRatesData = [];
    let rawKdvFaturalarData = [];
    let currentKdvScope = 'GIDEN';

    // Para ve Sayı Formatlayıcılar
    const formatMoney = (val) => {
        return Number(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };

    const formatNumber = (val) => {
        return Number(val || 0).toLocaleString('tr-TR');
    };

    // 1. Tarih Aralığı Hesaplayıcı
    function setPeriodDates(period) {
        const now = new Date();
        const y = now.getFullYear();
        const m = now.getMonth(); // 0-indexed

        const toIso = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        if (period === 'this_month') {
            const firstDay = new Date(y, m, 1);
            const lastDay = new Date(y, m + 1, 0);
            currentStartDate = toIso(firstDay);
            currentEndDate = toIso(lastDay);
        } else if (period === 'last_month') {
            const firstDay = new Date(y, m - 1, 1);
            const lastDay = new Date(y, m, 0);
            currentStartDate = toIso(firstDay);
            currentEndDate = toIso(lastDay);
        } else if (period === 'last_3_months') {
            const firstDay = new Date(y, m - 2, 1);
            const lastDay = new Date(y, m + 1, 0);
            currentStartDate = toIso(firstDay);
            currentEndDate = toIso(lastDay);
        } else if (period === 'this_year') {
            const firstDay = new Date(y, 0, 1);
            const lastDay = new Date(y, 11, 31);
            currentStartDate = toIso(firstDay);
            currentEndDate = toIso(lastDay);
        } else if (period === 'all') {
            currentStartDate = '';
            currentEndDate = '';
        }
    }

    // 2. Flatpickr Başlatma (Yıl Seçimi Dropdown: 2020 - İçinde Olduğumuz Yıl)
    const curYear = new Date().getFullYear();
    const flatpickrConfig = {
        locale: 'tr',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd.m.Y',
        allowInput: false,
        disableMobile: true,
        minYear: 2020,
        maxYear: curYear,
        minDate: '2020-01-01',
        maxDate: `${curYear}-12-31`,
    };

    let startPicker = null;
    let endPicker = null;

    const initPicker = (typeof window.initFlatpickrWithYearSelect === 'function')
        ? window.initFlatpickrWithYearSelect
        : flatpickr;

    if (document.getElementById('dashStartDate')) {
        startPicker = initPicker('#dashStartDate', {
            ...flatpickrConfig,
            onChange: function(selectedDates, dateStr) {
                currentStartDate = dateStr;
                if (endPicker) endPicker.set('minDate', dateStr || '2020-01-01');
                $('#quickPeriodGroup .btn').removeClass('active');
                $('#btnClearDates').show();
                loadDashboardData();
            }
        });
    }

    if (document.getElementById('dashEndDate')) {
        endPicker = initPicker('#dashEndDate', {
            ...flatpickrConfig,
            onChange: function(selectedDates, dateStr) {
                currentEndDate = dateStr;
                if (startPicker) startPicker.set('maxDate', dateStr || `${curYear}-12-31`);
                $('#quickPeriodGroup .btn').removeClass('active');
                $('#btnClearDates').show();
                loadDashboardData();
            }
        });
    }

    // Tarihleri Temizle Butonu
    $('#btnClearDates').on('click', function() {
        if (startPicker) {
            startPicker.clear();
            startPicker.set('maxDate', `${curYear}-12-31`);
        }
        if (endPicker) {
            endPicker.clear();
            endPicker.set('minDate', '2020-01-01');
        }
        currentStartDate = '';
        currentEndDate = '';
        $(this).hide();
        $('#quickPeriodGroup button[data-period="all"]').click();
    });

    // Hızlı Dönem Butonları
    $('#quickPeriodGroup .btn').on('click', function() {
        $('#quickPeriodGroup .btn').removeClass('active');
        $(this).addClass('active');
        currentPeriod = $(this).data('period');
        
        if (startPicker) {
            startPicker.clear();
            startPicker.set('maxDate', `${curYear}-12-31`);
        }
        if (endPicker) {
            endPicker.clear();
            endPicker.set('minDate', '2020-01-01');
        }
        $('#btnClearDates').hide();

        setPeriodDates(currentPeriod);
        loadDashboardData();
    });

    $('#btnRefreshDashboard').on('click', function() {
        loadDashboardData();
    });

    // KDV Yön Filtresi Butonları (Giden / Gelen / Tümü)
    $('#kdvScopeGroup button').on('click', function() {
        $('#kdvScopeGroup button').removeClass('active');
        $(this).addClass('active');
        currentKdvScope = $(this).data('scope') || 'GIDEN';
        renderKdvRatesCard();
    });

    // KDV Fatura Akordiyon İkon Dönüşü
    $('#kdvInvoicesCollapse').on('show.bs.collapse', function() {
        $('#kdvAccordionChevron').css('transform', 'rotate(180deg)');
    }).on('hide.bs.collapse', function() {
        $('#kdvAccordionChevron').css('transform', 'rotate(0deg)');
    });

    // 3. Ana Dashboard Veri Yükleme
    function loadDashboardData() {
        let url = 'api/efatura-api.php?action=dashboard_stats';
        if (currentStartDate) url += `&baslangic_tarihi=${currentStartDate}`;
        if (currentEndDate) url += `&bitis_tarihi=${currentEndDate}`;

        // Loading efekti
        $('#btnRefreshDashboard i').addClass('bx-spin');

        fetch(url)
            .then(res => res.json())
            .then(res => {
                $('#btnRefreshDashboard i').removeClass('bx-spin');
                if (res.status === 'success' && res.data) {
                    renderDashboard(res.data);
                } else {
                    console.error('Dashboard veri alma hatası:', res);
                }
            })
            .catch(err => {
                $('#btnRefreshDashboard i').removeClass('bx-spin');
                console.error('Dashboard API hatası:', err);
            });
    }

    // 4. Arayüzü Güncelle
    function renderDashboard(data) {
        const gelen = data.gelen || {};
        const giden = data.giden || {};
        const taslak = data.taslak || {};
        const kdv = data.kdv || {};
        const tahsilat = data.tahsilat || {};

        // 4.1 Kart 1: GELEN FATURALAR
        if (document.getElementById('kpi_gelen_tutar')) {
            document.getElementById('kpi_gelen_tutar').textContent = formatMoney(gelen.toplam_tutar);
        }
        if (document.getElementById('kpi_gelen_adet')) {
            document.getElementById('kpi_gelen_adet').textContent = `${formatNumber(gelen.toplam_adet)} Adet`;
        }
        if (document.getElementById('kpi_gelen_matrah')) {
            document.getElementById('kpi_gelen_matrah').textContent = formatMoney(gelen.toplam_matrah);
        }
        if (document.getElementById('kpi_gelen_kdv')) {
            document.getElementById('kpi_gelen_kdv').textContent = formatMoney(gelen.toplam_kdv);
        }
        if (document.getElementById('kpi_gelen_kabul')) {
            document.getElementById('kpi_gelen_kabul').textContent = formatNumber(gelen.kabul_adet);
        }
        if (document.getElementById('kpi_gelen_red')) {
            document.getElementById('kpi_gelen_red').textContent = formatNumber(gelen.red_adet);
        }

        // 4.2 Kart 2: GİDEN FATURALAR
        if (document.getElementById('kpi_giden_tutar')) {
            document.getElementById('kpi_giden_tutar').textContent = formatMoney(giden.toplam_tutar);
        }
        if (document.getElementById('kpi_giden_adet')) {
            document.getElementById('kpi_giden_adet').textContent = `${formatNumber(giden.toplam_adet)} Adet`;
        }
        if (document.getElementById('kpi_giden_matrah')) {
            document.getElementById('kpi_giden_matrah').textContent = formatMoney(giden.toplam_matrah);
        }
        if (document.getElementById('kpi_giden_kdv')) {
            document.getElementById('kpi_giden_kdv').textContent = formatMoney(giden.toplam_kdv);
        }
        if (document.getElementById('kpi_giden_efat')) {
            document.getElementById('kpi_giden_efat').textContent = formatNumber(giden.efatura_adet);
        }
        if (document.getElementById('kpi_giden_ears')) {
            document.getElementById('kpi_giden_ears').textContent = formatNumber(giden.earsiv_adet);
        }

        // 4.3 Kart 3: NET KDV DENGESİ (Ödenecek / Devreden)
        const netKdv = Number(kdv.net_kdv || 0);
        const kdvBox = document.getElementById('kpi_kdv_status_box');
        const kdvIcon = document.getElementById('kpi_kdv_status_icon');
        const kdvLabel = document.getElementById('kpi_kdv_status_label');
        const kdvValue = document.getElementById('kpi_kdv_status_value');

        if (kdvBox && kdvLabel && kdvValue) {
            if (netKdv > 0) {
                // Ödenecek KDV Yükü (Satış KDV > Alış KDV)
                kdvBox.className = 'kdv-hero-badge kdv-payable';
                if (kdvIcon) kdvIcon.className = 'bx bx-error-circle fs-5 text-danger';
                kdvLabel.textContent = 'ÖDENECEK KDV (MALİYE)';
                kdvLabel.className = 'font-size-11 text-uppercase fw-semibold text-danger';
                kdvValue.textContent = formatMoney(kdv.odenecek_kdv);
                kdvValue.className = 'font-size-18 fw-bolder text-danger';
            } else if (netKdv < 0) {
                // Devreden KDV (Alış KDV > Satış KDV)
                kdvBox.className = 'kdv-hero-badge kdv-carried';
                if (kdvIcon) kdvIcon.className = 'bx bx-check-circle fs-5 text-success';
                kdvLabel.textContent = 'DEVREDEN KDV (ALACAK)';
                kdvLabel.className = 'font-size-11 text-uppercase fw-semibold text-success';
                kdvValue.textContent = formatMoney(kdv.devreden_kdv);
                kdvValue.className = 'font-size-18 fw-bolder text-success';
            } else {
                kdvBox.className = 'kdv-hero-badge';
                kdvBox.style.background = '#f1f5f9';
                kdvBox.style.border = '1px solid #cbd5e1';
                kdvLabel.textContent = 'KDV FARKI YOK';
                kdvLabel.className = 'font-size-11 text-uppercase fw-semibold text-muted';
                kdvValue.textContent = '0,00 ₺';
                kdvValue.className = 'font-size-18 fw-bolder text-dark';
            }
        }

        if (document.getElementById('kpi_sub_hesaplanan_kdv')) {
            document.getElementById('kpi_sub_hesaplanan_kdv').textContent = formatMoney(kdv.hesaplanan_kdv);
        }
        if (document.getElementById('kpi_sub_indirilecek_kdv')) {
            document.getElementById('kpi_sub_indirilecek_kdv').textContent = formatMoney(kdv.indirilecek_kdv);
        }
        if (document.getElementById('kpi_net_matrah_farki')) {
            const fark = Number(kdv.net_matrah_farki || 0);
            const el = document.getElementById('kpi_net_matrah_farki');
            el.textContent = formatMoney(fark);
            el.className = 'stat-sub-val font-size-12 ' + (fark >= 0 ? 'text-success' : 'text-danger');
        }

        // 4.4 Kart 4: TAHSİLAT & FİNANS
        if (document.getElementById('kpi_tahsil_edilen')) {
            document.getElementById('kpi_tahsil_edilen').textContent = formatMoney(tahsilat.toplam_tahsilat);
        }
        if (document.getElementById('kpi_tahsilat_orani')) {
            document.getElementById('kpi_tahsilat_orani').textContent = `%${tahsilat.tahsilat_orani || 0}`;
        }
        if (document.getElementById('kpi_tahsilat_progress')) {
            document.getElementById('kpi_tahsilat_progress').style.width = `${Math.min(100, tahsilat.tahsilat_orani || 0)}%`;
        }
        if (document.getElementById('kpi_kalan_tahsilat')) {
            document.getElementById('kpi_kalan_tahsilat').textContent = formatMoney(tahsilat.kalan_tahsilat);
        }
        if (document.getElementById('kpi_taslak_ozet')) {
            document.getElementById('kpi_taslak_ozet').textContent = `${formatNumber(taslak.toplam_adet)} Adet (${formatMoney(taslak.toplam_tutar)})`;
        }

        // 4.5 Grafikleri Render Et
        renderMonthlyTrendChart(data.monthly_trend || []);
        renderKdvTrendChart(data.monthly_trend || []);
        rawKdvRatesData = data.kdv_oranlari || [];
        rawKdvFaturalarData = data.kdv_faturalari || [];
        renderKdvRatesCard();

        // 4.6 Tabloları Render Et
        renderTopCarilerTable('topGidenCarilerBody', data.top_giden_cariler || [], 'Müşteri bulunamadı');
        renderTopCarilerTable('topGelenCarilerBody', data.top_gelen_cariler || [], 'Tedarikçi bulunamadı');
        renderRecentInvoicesTable('recentGidenBody', data.recent_giden || [], 'giden');
        renderRecentInvoicesTable('recentGelenBody', data.recent_gelen || [], 'gelen');
    }

    // Tema Modu Algılayıcı (macOS Dark / Dark Theme)
    function isMacosDarkMode() {
        const doc = document.documentElement;
        return doc.getAttribute('data-theme-preset') === 'macos-dark' ||
               doc.getAttribute('data-bs-theme') === 'dark' ||
               (document.body && document.body.getAttribute('data-bs-theme') === 'dark');
    }

    // 5. Grafik 1: Aylık Fatura Trendi & Matrah Karşılaştırması (ApexCharts)
    function renderMonthlyTrendChart(trendData) {
        const isDark = isMacosDarkMode();
        const categories = trendData.map(item => item.ay_adi || item.ay);
        const gidenMatrahSeries = trendData.map(item => item.giden_matrah || 0);
        const gelenMatrahSeries = trendData.map(item => item.gelen_matrah || 0);

        const options = {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false },
                fontFamily: 'inherit',
                background: 'transparent'
            },
            theme: {
                mode: isDark ? 'dark' : 'light'
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '45%',
                    borderRadius: 4
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            colors: ['#10b981', '#0ea5e9'],
            series: [
                { name: 'Giden (Satış) Matrahı', data: gidenMatrahSeries },
                { name: 'Gelen (Alış) Matrahı', data: gelenMatrahSeries }
            ],
            xaxis: {
                categories: categories.length > 0 ? categories : ['Kayıt Yok'],
                labels: { style: { fontSize: '11px', colors: isDark ? 'rgba(224, 225, 230, 0.65)' : '#64748b' } },
                axisBorder: { color: isDark ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' },
                axisTicks: { color: isDark ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return (val / 1000).toLocaleString('tr-TR') + 'k ₺';
                    },
                    style: { fontSize: '11px', colors: isDark ? 'rgba(224, 225, 230, 0.65)' : '#64748b' }
                }
            },
            fill: { opacity: 1 },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        return formatMoney(val);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                labels: { colors: isDark ? 'rgba(230, 232, 238, 0.85)' : '#475569' }
            },
            grid: { borderColor: isDark ? 'rgba(255, 255, 255, 0.08)' : '#f1f5f9' }
        };

        if (monthlyTrendChartInstance) {
            monthlyTrendChartInstance.destroy();
        }
        if (document.getElementById('monthlyTrendChart')) {
            monthlyTrendChartInstance = new ApexCharts(document.getElementById('monthlyTrendChart'), options);
            monthlyTrendChartInstance.render();
        }
    }

    // 6. Grafik 2: Aylık KDV Akışı & Dengesi (ApexCharts)
    function renderKdvTrendChart(trendData) {
        const isDark = isMacosDarkMode();
        const categories = trendData.map(item => item.ay_adi || item.ay);
        const gidenKdvSeries = trendData.map(item => item.giden_kdv || 0);
        const gelenKdvSeries = trendData.map(item => item.gelen_kdv || 0);
        const netKdvSeries = trendData.map(item => item.net_kdv || 0);

        const options = {
            chart: {
                type: 'line',
                height: 320,
                toolbar: { show: false },
                fontFamily: 'inherit',
                background: 'transparent'
            },
            theme: {
                mode: isDark ? 'dark' : 'light'
            },
            stroke: {
                curve: 'smooth',
                width: [2.5, 2.5, 3],
                dashArray: [0, 0, 4]
            },
            colors: ['#10b981', '#0ea5e9', '#ef4444'],
            series: [
                { name: 'Hesaplanan KDV', type: 'line', data: gidenKdvSeries },
                { name: 'İndirilecek KDV', type: 'line', data: gelenKdvSeries },
                { name: 'Net Ödenecek / Devreden', type: 'line', data: netKdvSeries }
            ],
            xaxis: {
                categories: categories.length > 0 ? categories : ['Kayıt Yok'],
                labels: { style: { fontSize: '11px', colors: isDark ? 'rgba(224, 225, 230, 0.65)' : '#64748b' } },
                axisBorder: { color: isDark ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' },
                axisTicks: { color: isDark ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return (val / 1000).toLocaleString('tr-TR') + 'k ₺';
                    },
                    style: { fontSize: '11px', colors: isDark ? 'rgba(224, 225, 230, 0.65)' : '#64748b' }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        return formatMoney(val);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '11px',
                labels: { colors: isDark ? 'rgba(230, 232, 238, 0.85)' : '#475569' }
            },
            grid: { borderColor: isDark ? 'rgba(255, 255, 255, 0.08)' : '#f1f5f9' }
        };

        if (kdvTrendChartInstance) {
            kdvTrendChartInstance.destroy();
        }
        if (document.getElementById('kdvTrendChart')) {
            kdvTrendChartInstance = new ApexCharts(document.getElementById('kdvTrendChart'), options);
            kdvTrendChartInstance.render();
        }
    }

    // 7. Grafik 3: KDV Oran Dağılımı & Detaylı Matrah Analizi
    const kdvRateColorMap = {
        '20': '#3b82f6',
        '10': '#10b981',
        '1': '#8b5cf6',
        '0': '#f59e0b'
    };

    function getKdvColor(rate) {
        const key = String(Math.round(rate));
        return kdvRateColorMap[key] || '#06b6d4';
    }

    function renderKdvRatesCard() {
        const isDark = isMacosDarkMode();

        // 1. Seçili kapsama göre filtreleme (Giden / Gelen / Tümü)
        let filtered = [];
        if (currentKdvScope === 'ALL') {
            filtered = rawKdvRatesData;
        } else {
            filtered = rawKdvRatesData.filter(r => (r.yon || '').toUpperCase() === currentKdvScope);
        }

        // 2. KDV Oranlarına göre gruplama ve toplama
        const ratesMap = {};
        let totalMatrah = 0;
        let totalKdv = 0;
        let totalLines = 0;

        filtered.forEach(r => {
            const rate = Number(r.kdv_orani || 0);
            const rateKey = rate.toString();
            const matrah = Number(r.matrah || 0);
            const kdv = Number(r.kdv_tutari || 0);
            const lines = Number(r.satir_sayisi || 0);

            if (!ratesMap[rateKey]) {
                ratesMap[rateKey] = {
                    rate: rate,
                    rateLabel: `%${rate} KDV`,
                    matrah: 0,
                    kdv: 0,
                    lines: 0
                };
            }
            ratesMap[rateKey].matrah += matrah;
            ratesMap[rateKey].kdv += kdv;
            ratesMap[rateKey].lines += lines;

            totalMatrah += matrah;
            totalKdv += kdv;
            totalLines += lines;
        });

        // Oranları azalan sırayla sırala (%20, %10, %1, %0)
        const sortedRates = Object.values(ratesMap).sort((a, b) => b.rate - a.rate);

        // 3. Başlık Rozeti ve Açıklamalarını Güncelle
        const badgeEl = document.getElementById('kdvScopeBadge');
        const subTitleEl = document.getElementById('kdvScopeSubtitle');
        const matrahLabelEl = document.getElementById('kdvScopeMatrahLabel');
        const matrahValEl = document.getElementById('kdvScopeMatrahValue');
        const kdvLabelEl = document.getElementById('kdvScopeKdvLabel');
        const kdvValEl = document.getElementById('kdvScopeKdvValue');

        let scopeCenterLabel = 'Toplam Matrah';
        let kdvValClass = 'text-primary';

        if (currentKdvScope === 'GIDEN') {
            if (badgeEl) {
                badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle font-size-10 fw-semibold px-2 py-0.5 rounded-pill';
                badgeEl.innerHTML = '<i class="bx bx-up-arrow-alt me-0.5"></i>Giden (Satış)';
            }
            if (subTitleEl) subTitleEl.textContent = 'Satış Faturaları Matrah & Hesaplanan KDV';
            if (matrahLabelEl) matrahLabelEl.textContent = 'GİDEN MATRAH';
            if (kdvLabelEl) kdvLabelEl.textContent = 'HESAPLANAN KDV';
            kdvValClass = 'text-success';
            scopeCenterLabel = 'Giden Matrah';
        } else if (currentKdvScope === 'GELEN') {
            if (badgeEl) {
                badgeEl.className = 'badge bg-info-subtle text-info border border-info-subtle font-size-10 fw-semibold px-2 py-0.5 rounded-pill';
                badgeEl.innerHTML = '<i class="bx bx-down-arrow-alt me-0.5"></i>Gelen (Alış)';
            }
            if (subTitleEl) subTitleEl.textContent = 'Alış Faturaları Matrah & İndirilecek KDV';
            if (matrahLabelEl) matrahLabelEl.textContent = 'GELEN MATRAH';
            if (kdvLabelEl) kdvLabelEl.textContent = 'İNDİRİLECEK KDV';
            kdvValClass = 'text-info';
            scopeCenterLabel = 'Gelen Matrah';
        } else {
            if (badgeEl) {
                badgeEl.className = 'badge bg-primary-subtle text-primary border border-primary-subtle font-size-10 fw-semibold px-2 py-0.5 rounded-pill';
                badgeEl.innerHTML = '<i class="bx bx-shuffle me-0.5"></i>Tüm Faturalar';
            }
            if (subTitleEl) subTitleEl.textContent = 'Toplam Matrah & KDV Dağılımı';
            if (matrahLabelEl) matrahLabelEl.textContent = 'TOPLAM MATRAH';
            if (kdvLabelEl) kdvLabelEl.textContent = 'TOPLAM KDV';
            kdvValClass = 'text-primary';
            scopeCenterLabel = 'Toplam Matrah';
        }

        if (matrahValEl) matrahValEl.textContent = formatMoney(totalMatrah);
        if (kdvValEl) {
            kdvValEl.textContent = formatMoney(totalKdv);
            kdvValEl.className = `font-size-13 fw-bold ${kdvValClass} text-truncate`;
        }

        // 4. Detay Tablosunu Render Et
        const tbody = document.getElementById('kdvBreakdownTableBody');
        if (tbody) {
            if (sortedRates.length === 0 || totalMatrah === 0) {
                const scopeName = currentKdvScope === 'GIDEN' ? 'giden (satış)' : (currentKdvScope === 'GELEN' ? 'gelen (alış)' : '');
                tbody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-muted font-size-11"><i class="bx bx-info-circle me-1"></i> Bu dönemde ${scopeName} fatura satırı bulunmuyor</td></tr>`;
            } else {
                let rowsHtml = '';
                sortedRates.forEach(item => {
                    const color = getKdvColor(item.rate);
                    const pct = totalMatrah > 0 ? ((item.matrah / totalMatrah) * 100).toFixed(1) : '0.0';
                    rowsHtml += `
                    <tr>
                        <td class="ps-2">
                            <span class="d-inline-flex align-items-center gap-1.5">
                                <span class="rounded-circle d-inline-block" style="width: 8px; height: 8px; background: ${color};"></span>
                                <span class="fw-bold font-size-11">%${item.rate}</span>
                            </span>
                        </td>
                        <td class="text-end fw-semibold font-size-11">${formatMoney(item.matrah)}</td>
                        <td class="text-end fw-semibold ${kdvValClass} font-size-11">${formatMoney(item.kdv)}</td>
                        <td class="text-center pe-2"><span class="badge bg-light text-muted font-size-10 px-1 py-0.5 border">%${pct}</span></td>
                    </tr>`;
                });

                rowsHtml += `
                <tr class="fw-bold border-top" style="background-color: ${isDark ? 'rgba(255,255,255,0.04)' : '#f8fafc'};">
                    <td class="ps-2 text-uppercase font-size-10 text-muted">Toplam</td>
                    <td class="text-end font-size-11">${formatMoney(totalMatrah)}</td>
                    <td class="text-end font-size-11 ${kdvValClass}">${formatMoney(totalKdv)}</td>
                    <td class="text-center pe-2 font-size-10 text-muted">%100</td>
                </tr>`;

                tbody.innerHTML = rowsHtml;
            }
        }

        // 5. ApexCharts Donut Grafiğini Render Et
        let labels = sortedRates.map(r => r.rateLabel);
        let series = sortedRates.map(r => r.matrah);
        let colors = sortedRates.map(r => getKdvColor(r.rate));

        if (series.length === 0 || totalMatrah === 0) {
            labels = ['Kayıt Yok'];
            series = [1];
            colors = [isDark ? '#374151' : '#e2e8f0'];
        }

        const options = {
            chart: {
                type: 'donut',
                height: 210,
                fontFamily: 'inherit',
                background: 'transparent'
            },
            theme: {
                mode: isDark ? 'dark' : 'light'
            },
            stroke: {
                colors: [isDark ? 'rgba(28, 30, 38, 0.95)' : '#ffffff'],
                width: 2
            },
            labels: labels,
            series: series,
            colors: colors,
            dataLabels: {
                enabled: series.length > 0 && totalMatrah > 0,
                formatter: function (val) {
                    return val > 5 ? val.toFixed(1) + '%' : '';
                },
                style: {
                    fontSize: '10px',
                    fontWeight: 600
                },
                dropShadow: { enabled: false }
            },
            legend: {
                show: true,
                position: 'bottom',
                fontSize: '11px',
                horizontalAlign: 'center',
                itemMargin: { horizontal: 6, vertical: 2 },
                labels: { colors: isDark ? 'rgba(230, 232, 238, 0.85)' : '#475569' }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: scopeCenterLabel,
                                fontSize: '10px',
                                color: isDark ? 'rgba(220, 225, 235, 0.65)' : '#64748b',
                                formatter: function(w) {
                                    if (totalMatrah === 0) return '0 ₺';
                                    if (totalMatrah >= 1000000) {
                                        return (totalMatrah / 1000000).toLocaleString('tr-TR', { maximumFractionDigits: 2 }) + 'M ₺';
                                    }
                                    return (totalMatrah / 1000).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + 'k ₺';
                                }
                            },
                            value: {
                                color: isDark ? 'rgba(240, 242, 246, 0.95)' : '#0f172a',
                                fontSize: '13px',
                                fontWeight: 700
                            }
                        }
                    }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        return formatMoney(val);
                    }
                }
            }
        };

        if (kdvRatesChartInstance) {
            kdvRatesChartInstance.destroy();
        }
        if (document.getElementById('kdvRatesChart')) {
            kdvRatesChartInstance = new ApexCharts(document.getElementById('kdvRatesChart'), options);
            kdvRatesChartInstance.render();
        }

        // 6. Dahil Olan Faturalar Akordiyonunu Doldur
        renderKdvRateInvoicesAccordion();

        function renderKdvRateInvoicesAccordion() {
            let filteredInvoices = [];
            if (currentKdvScope === 'ALL') {
                filteredInvoices = rawKdvFaturalarData;
            } else {
                filteredInvoices = rawKdvFaturalarData.filter(inv => (inv.yon || '').toUpperCase() === currentKdvScope);
            }

        // Benzersiz fatura sayısını hesapla
        const uniqueInvoiceIds = new Set(filteredInvoices.map(inv => inv.fatura_id));
        const invoiceCountEl = document.getElementById('kdvInvoiceCountBadge');
        if (invoiceCountEl) {
            invoiceCountEl.textContent = uniqueInvoiceIds.size.toString();
        }

        // Faturaları KDV oranına göre gruplayalım
        const invoiceByRateMap = {};
        filteredInvoices.forEach(inv => {
            const rKey = Number(inv.kdv_orani || 0).toString();
            if (!invoiceByRateMap[rKey]) {
                invoiceByRateMap[rKey] = [];
            }
            invoiceByRateMap[rKey].push(inv);
        });

        const accordionEl = document.getElementById('kdvRateInvoicesAccordion');
        if (accordionEl) {
            if (filteredInvoices.length === 0) {
                const scopeName = currentKdvScope === 'GIDEN' ? 'giden (satış)' : (currentKdvScope === 'GELEN' ? 'gelen (alış)' : '');
                accordionEl.innerHTML = `<div class="p-3 text-center text-muted font-size-11"><i class="bx bx-info-circle me-1"></i> Bu dönemde ${scopeName} fatura kaydı bulunmuyor</div>`;
            } else {
                let accHtml = '';
                sortedRates.forEach((item, index) => {
                    const rKey = item.rate.toString();
                    const rateInvoices = invoiceByRateMap[rKey] || [];
                    if (rateInvoices.length === 0) return;

                    const color = getKdvColor(item.rate);
                    const rateClean = String(item.rate).replace(/[^a-zA-Z0-9]/g, '_');
                    const isFirst = index === 0;

                    accHtml += `
                    <div class="accordion-item border-bottom">
                        <h2 class="accordion-header" id="heading_kdv_${rateClean}">
                            <button class="accordion-button ${isFirst ? '' : 'collapsed'} py-2 px-2.5 font-size-11 fw-bold bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_kdv_${rateClean}" aria-expanded="${isFirst ? 'true' : 'false'}" aria-controls="collapse_kdv_${rateClean}">
                                <div class="d-flex align-items-center justify-content-between w-100 me-2 flex-wrap gap-1">
                                    <span class="d-flex align-items-center gap-1.5">
                                        <span class="rounded-circle d-inline-block" style="width:8px;height:8px;background:${color};"></span>
                                        <span>%${item.rate} KDV (${rateInvoices.length} Fatura)</span>
                                    </span>
                                    <span class="font-size-10 text-muted">
                                        Matrah: <strong class="text-dark">${formatMoney(item.matrah)}</strong> | KDV: <strong class="${kdvValClass}">${formatMoney(item.kdv)}</strong>
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse_kdv_${rateClean}" class="accordion-collapse collapse ${isFirst ? 'show' : ''}" aria-labelledby="heading_kdv_${rateClean}">
                            <div class="accordion-body p-0">
                                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                                    <table class="table table-dashboard table-sm table-hover mb-0 font-size-10 align-middle">
                                        <thead>
                                            <tr>
                                                <th class="ps-2" style="min-width: 110px;">Fatura No & Tarih</th>
                                                <th style="min-width: 120px;">Cari Ünvan</th>
                                                <th class="text-end" style="min-width: 80px;">Matrah</th>
                                                <th class="text-end" style="min-width: 75px;">KDV</th>
                                                <th class="text-center pe-2" style="width: 32px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${rateInvoices.map(inv => `
                                                <tr>
                                                    <td class="ps-2">
                                                        <div class="fw-bold font-monospace text-truncate" style="max-width: 115px;">
                                                            <a href="javascript:void(0)" class="text-primary text-decoration-none btn-preview-invoice" data-id="${inv.encrypted_id}" title="Fatura Önizle">${inv.fatura_no || 'Taslak'}</a>
                                                        </div>
                                                        <div class="text-muted font-size-9">${inv.fatura_tarihi_fmt}</div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 130px;" title="${inv.alici_unvan || ''}">${inv.alici_unvan || '-'}</div>
                                                        <div class="text-muted font-size-9">${inv.belge_turu || 'EFATURA'}</div>
                                                    </td>
                                                    <td class="text-end fw-semibold text-dark font-size-10">${formatMoney(inv.matrah)}</td>
                                                    <td class="text-end fw-semibold ${kdvValClass} font-size-10">${formatMoney(inv.kdv_tutari)}</td>
                                                    <td class="text-center pe-2">
                                                        <button type="button" class="btn btn-xs btn-subtle-primary p-1 btn-preview-invoice" data-id="${inv.encrypted_id}" title="Fatura Önizle">
                                                            <i class="bx bx-show font-size-12"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>`;
                });

                accordionEl.innerHTML = accHtml || `<div class="p-3 text-center text-muted font-size-11"><i class="bx bx-info-circle me-1"></i> Fatura detayı bulunamadı</div>`;
            }
        }
    }
}

    // 8. Tablo: Top Cariler
    function renderTopCarilerTable(elementId, cariler, emptyText) {
        const tbody = document.getElementById(elementId);
        if (!tbody) return;

        if (!cariler || cariler.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center py-4 text-muted font-size-12">${emptyText}</td></tr>`;
            return;
        }

        let html = '';
        cariler.forEach(c => {
            html += `
            <tr>
                <td>
                    <div class="fw-bold text-dark font-size-12 text-truncate" style="max-width: 170px;" title="${c.alici_unvan || ''}">${c.alici_unvan || 'Tanımsız Cari'}</div>
                    <div class="font-size-11 text-muted">${c.alici_vkn_tckn || '-'}</div>
                </td>
                <td class="text-center font-size-12 fw-semibold">${formatNumber(c.adet)}</td>
                <td class="text-end fw-bold font-size-12 text-dark">${formatMoney(c.toplam)}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // 9. Tablo: Son Faturalar
    function renderRecentInvoicesTable(elementId, invoices, type) {
        const tbody = document.getElementById(elementId);
        if (!tbody) return;

        if (!invoices || invoices.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted font-size-12">Fatura kaydı bulunamadı</td></tr>`;
            return;
        }

        let html = '';
        invoices.forEach(inv => {
            let statusBadge = '';
            if (type === 'giden') {
                if (inv.entegrator_durum_kodu === 'ONAYLANDI') {
                    statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10">Onaylı</span>';
                } else if (inv.entegrator_durum_kodu === 'TASLAK') {
                    statusBadge = '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill font-size-10">Taslak</span>';
                } else {
                    statusBadge = `<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-size-10">${inv.entegrator_durum_kodu || 'İletildi'}</span>`;
                }
            } else {
                if (inv.ticari_yanit === 'KABUL') {
                    statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10">Kabul</span>';
                } else if (inv.ticari_yanit === 'RED') {
                    statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10">Red</span>';
                } else {
                    statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-10">Bekliyor</span>';
                }
            }

            html += `
            <tr>
                <td>
                    <div class="fw-bold font-size-12 text-primary font-monospace">
                        <a href="javascript:void(0)" class="text-primary text-decoration-none btn-preview-invoice" data-id="${inv.encrypted_id}" title="Faturayı Önizle">${inv.fatura_no || 'Taslak'}</a>
                    </div>
                    <div class="font-size-11 text-muted">${inv.fatura_tarihi_fmt}</div>
                </td>
                <td>
                    <div class="fw-semibold text-dark font-size-12 text-truncate" style="max-width: 190px;" title="${inv.alici_unvan || ''}">${inv.alici_unvan || '-'}</div>
                    <div class="font-size-10 text-muted">${inv.belge_turu || 'EFATURA'}</div>
                </td>
                <td class="text-end fw-bold font-size-12 text-dark">${inv.odenecek_tutar_fmt}</td>
                <td class="text-center">${statusBadge}</td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // 10. Fatura HTML Önizleme Fonksiyonları
    function decodeInvoiceHtml(html) {
        if (!html) return '';
        let txt = document.createElement('textarea');
        txt.innerHTML = html;
        let decoded = txt.value;
        if (decoded.includes('&lt;') || decoded.includes('&gt;')) {
            txt.innerHTML = decoded;
            decoded = txt.value;
        }
        return decoded;
    }

    let lastInvoicePreviewHtml = '';
    function openInvoicePreview(id, autoPrint = false) {
        const modalEl = document.getElementById('modalFaturaOnizleme');
        if (!modalEl) return;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        lastInvoicePreviewHtml = '';
        
        $('#onizlemeModalTitle').text('Fatura Önizleme');
        $('#btnModalYeniSekme').attr('href', `api/efatura-api.php?action=view_invoice&invoice_id=${id}`);
        $('#btnModalPdfIndir').attr('href', `api/efatura-api.php?action=download_pdf&invoice_id=${id}`);
        $('#onizlemeModalContent').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted font-size-12">Fatura yükleniyor...</div></div>');

        fetch(`api/efatura-api.php?action=preview_html&invoice_id=${id}`)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const cleanedHtml = decodeInvoiceHtml(res.html);
                    lastInvoicePreviewHtml = cleanedHtml;
                    let docHtml = cleanedHtml;
                    if (!docHtml.includes('<html') && !docHtml.includes('<!DOCTYPE')) {
                        docHtml = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body { margin: 0; padding: 15px; background: #fff; font-family: Arial, sans-serif; }</style></head><body>${docHtml}</body></html>`;
                    }
                    $('#onizlemeModalContent').html(`
                        <iframe id="dashInvoiceIframe" style="width: 100%; height: 74vh; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;" frameborder="0"></iframe>
                    `);
                    const iframe = document.getElementById('dashInvoiceIframe');
                    if (iframe) {
                        iframe.srcdoc = docHtml;
                    }
                    if (autoPrint) {
                        setTimeout(() => {
                            printInvoiceHtml(cleanedHtml);
                        }, 300);
                    }
                } else {
                    $('#onizlemeModalContent').html(`<div class="alert alert-danger m-3">${res.message || 'Fatura görüntülenemedi.'}</div>`);
                }
            })
            .catch(err => {
                $('#onizlemeModalContent').html(`<div class="alert alert-danger m-3">Önizleme yüklenirken hata oluştu.</div>`);
            });
    }

    function printInvoiceHtml(html) {
        const printWindow = window.open('', '_blank');
        if (printWindow) {
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }
    }

    // Önizleme Butonu ve Yazdır Olayları
    $(document).on('click', '.btn-preview-invoice', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        if (id) {
            openInvoicePreview(id);
        }
    });

    $('#btnModalYazdir').on('click', function() {
        if (lastInvoicePreviewHtml) {
            printInvoiceHtml(lastInvoicePreviewHtml);
        } else {
            const iframe = document.getElementById('dashInvoiceIframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
        }
    });

    // Başlangıç: Bu Ay dönemini seç ve yükle
    setPeriodDates('this_month');
    loadDashboardData();
});
