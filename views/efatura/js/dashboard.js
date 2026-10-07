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
        renderKdvRatesChart(data.kdv_oranlari || []);

        // 4.6 Tabloları Render Et
        renderTopCarilerTable('topGidenCarilerBody', data.top_giden_cariler || [], 'Müşteri bulunamadı');
        renderTopCarilerTable('topGelenCarilerBody', data.top_gelen_cariler || [], 'Tedarikçi bulunamadı');
        renderRecentInvoicesTable('recentGidenBody', data.recent_giden || [], 'giden');
        renderRecentInvoicesTable('recentGelenBody', data.recent_gelen || [], 'gelen');
    }

    // 5. Grafik 1: Aylık Fatura Trendi & Matrah Karşılaştırması (ApexCharts)
    function renderMonthlyTrendChart(trendData) {
        const categories = trendData.map(item => item.ay_adi || item.ay);
        const gidenMatrahSeries = trendData.map(item => item.giden_matrah || 0);
        const gelenMatrahSeries = trendData.map(item => item.gelen_matrah || 0);

        const options = {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: { show: false },
                fontFamily: 'inherit'
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
                labels: { style: { fontSize: '11px', colors: '#64748b' } }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return (val / 1000).toLocaleString('tr-TR') + 'k ₺';
                    },
                    style: { fontSize: '11px', colors: '#64748b' }
                }
            },
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return formatMoney(val);
                    }
                }
            },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
            grid: { borderColor: '#f1f5f9' }
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
        const categories = trendData.map(item => item.ay_adi || item.ay);
        const gidenKdvSeries = trendData.map(item => item.giden_kdv || 0);
        const gelenKdvSeries = trendData.map(item => item.gelen_kdv || 0);
        const netKdvSeries = trendData.map(item => item.net_kdv || 0);

        const options = {
            chart: {
                type: 'line',
                height: 320,
                toolbar: { show: false },
                fontFamily: 'inherit'
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
                labels: { style: { fontSize: '11px', colors: '#64748b' } }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return (val / 1000).toLocaleString('tr-TR') + 'k ₺';
                    },
                    style: { fontSize: '11px', colors: '#64748b' }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return formatMoney(val);
                    }
                }
            },
            legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
            grid: { borderColor: '#f1f5f9' }
        };

        if (kdvTrendChartInstance) {
            kdvTrendChartInstance.destroy();
        }
        if (document.getElementById('kdvTrendChart')) {
            kdvTrendChartInstance = new ApexCharts(document.getElementById('kdvTrendChart'), options);
            kdvTrendChartInstance.render();
        }
    }

    // 7. Grafik 3: KDV Oran Dağılımı (Donut)
    function renderKdvRatesChart(ratesData) {
        // Matrah toplamlarını KDV oranına göre gruplayalım
        const rateMap = {};
        ratesData.forEach(r => {
            const rateKey = `%${Number(r.kdv_orani)} KDV`;
            rateMap[rateKey] = (rateMap[rateKey] || 0) + Number(r.matrah || 0);
        });

        let labels = Object.keys(rateMap);
        let series = Object.values(rateMap);

        if (labels.length === 0) {
            labels = ['Kayıt Yok'];
            series = [1];
        }

        const options = {
            chart: {
                type: 'donut',
                height: 260,
                fontFamily: 'inherit'
            },
            labels: labels,
            series: series,
            colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'],
            dataLabels: { enabled: true },
            legend: { position: 'bottom', fontSize: '11px' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Toplam Matrah',
                                fontSize: '11px',
                                formatter: function(w) {
                                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return (total / 1000).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + 'k ₺';
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
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
                    <div class="fw-bold font-size-12 text-primary font-monospace">${inv.fatura_no || 'Taslak'}</div>
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

    // Başlangıç: Bu Ay dönemini seç ve yükle
    setPeriodDates('this_month');
    loadDashboardData();
});
