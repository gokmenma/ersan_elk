/**
 * Araç Filo & Operasyon Dashboard JavaScript Modülü
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let currentStartDate = '';
    let currentEndDate = '';
    let currentPeriod = 'this_month';
    let currentDepartment = '';

    // ApexCharts Değişkenleri
    let monthlyFuelKmChartInstance = null;
    let deptDonutChartInstance = null;
    let ownershipChartInstance = null;
    let fuelTypeChartInstance = null;

    let rawDeptData = [];
    let currentDeptMetric = 'km';

    // Para ve Sayı Formatlayıcılar
    const formatMoney = (val) => {
        return Number(val || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };

    const formatNumber = (val) => {
        return Number(val || 0).toLocaleString('tr-TR');
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}.${parts[1]}.${parts[0]}`;
        }
        return dateStr;
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

    // 2. Flatpickr Başlatma
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

    if (document.getElementById('dashStartDate')) {
        startPicker = flatpickr('#dashStartDate', {
            ...flatpickrConfig,
            onChange: function(selectedDates, dateStr) {
                if (!dateStr) return;
                currentStartDate = dateStr;
                if (endPicker) endPicker.set('minDate', dateStr || '2020-01-01');
                $('#quickPeriodGroup .btn').removeClass('active');
                $('#btnClearDates').show();
                loadDashboardData();
            }
        });
    }

    if (document.getElementById('dashEndDate')) {
        endPicker = flatpickr('#dashEndDate', {
            ...flatpickrConfig,
            onChange: function(selectedDates, dateStr) {
                if (!dateStr) return;
                currentEndDate = dateStr;
                if (startPicker) startPicker.set('maxDate', dateStr || `${curYear}-12-31`);
                $('#quickPeriodGroup .btn').removeClass('active');
                $('#btnClearDates').show();
                loadDashboardData();
            }
        });
    }

    // Tarih Sıfırlama Butonu
    $('#btnClearDates').on('click', function() {
        if (startPicker) {
            startPicker.clear(false);
            startPicker.set('maxDate', `${curYear}-12-31`);
        }
        if (endPicker) {
            endPicker.clear(false);
            endPicker.set('minDate', '2020-01-01');
        }
        currentStartDate = '';
        currentEndDate = '';
        $(this).hide();
        $('#quickPeriodGroup button[data-period="all"]').trigger('click');
    });

    // Hızlı Dönem Butonları
    $('#quickPeriodGroup .btn').on('click', function(e) {
        e.preventDefault();
        $('#quickPeriodGroup .btn').removeClass('active');
        $(this).addClass('active');
        currentPeriod = $(this).data('period');

        if (startPicker) {
            startPicker.clear(false);
            startPicker.set('maxDate', `${curYear}-12-31`);
        }
        if (endPicker) {
            endPicker.clear(false);
            endPicker.set('minDate', '2020-01-01');
        }
        $('#btnClearDates').hide();

        setPeriodDates(currentPeriod);
        loadDashboardData();
    });

    // Departman Filtresi
    $('#dashDepartmentSelect').on('change', function() {
        currentDepartment = $(this).val();
        loadDashboardData();
    });

    // Yenile Butonu
    $('#btnRefreshDashboard').on('click', function() {
        loadDashboardData();
    });

    // Departman Metrik Değiştirici Butonlar (KM / Yakıt / Araç)
    $('#deptMetricGroup button').on('click', function() {
        $('#deptMetricGroup button').removeClass('active');
        $(this).addClass('active');
        currentDeptMetric = $(this).data('metric') || 'km';
        renderDeptChart(rawDeptData);
    });

    // Preloader Kontrolleri
    function showDashboardPreloader() {
        $('#dashboard-loader').addClass('show');
    }

    function hideDashboardPreloader() {
        $('#dashboard-loader').removeClass('show');
    }

    // 3. Ana Dashboard Veri Yükleme
    function loadDashboardData() {
        showDashboardPreloader();
        $('#btnRefreshDashboard i').addClass('bx-spin');

        const postData = {
            action: 'get-dashboard-stats',
            baslangic_tarihi: currentStartDate,
            bitis_tarihi: currentEndDate,
            departman: currentDepartment,
            yil: new Date().getFullYear()
        };

        $.ajax({
            url: 'views/arac-takip/api.php',
            type: 'POST',
            data: postData,
            dataType: 'json'
        })
        .done(function(res) {
            $('#btnRefreshDashboard i').removeClass('bx-spin');
            if (res.status === 'success' && res.data) {
                renderDashboard(res.data);
            } else {
                console.error('Dashboard veri alma hatası:', res);
                if (typeof toastr !== 'undefined') {
                    toastr.error(res.message || 'Veriler yüklenirken bir sorun oluştu.');
                }
            }
        })
        .fail(function(xhr, status, err) {
            $('#btnRefreshDashboard i').removeClass('bx-spin');
            console.error('Dashboard AJAX hatası:', err);
            if (typeof toastr !== 'undefined') {
                toastr.error('Sunucuyla bağlantı kurulamadı.');
            }
        })
        .always(function() {
            hideDashboardPreloader();
        });
    }

    // 4. Dashboard Arayüzünü Çizme (Render)
    function renderDashboard(data) {
        // Departman Dropdown Doldurma (İlk yüklemede)
        if ($('#dashDepartmentSelect option').length <= 1 && data.departmanlar && data.departmanlar.length > 0) {
            data.departmanlar.forEach(function(dep) {
                if (dep) {
                    $('#dashDepartmentSelect').append(new Option(dep, dep));
                }
            });
            if (currentDepartment) {
                $('#dashDepartmentSelect').val(currentDepartment);
            }
        }

        // A) 4 ANA KPI KARTI
        renderKpiCards(data.kpi);

        // B) AYLIK YAKIT & KM TRENDİ (Combo ApexChart)
        renderMonthlyFuelKmChart(data.trends);

        // C) DEPARTMAN DAĞILIMI
        rawDeptData = data.dept_distribution || [];
        renderDeptChart(rawDeptData);

        // D) MÜLKİYET & YAKIT TİPİ
        renderOwnershipChart(data.vehicle_types ? data.vehicle_types.mulkiyet : []);
        renderFuelTypeChart(data.vehicle_types ? data.vehicle_types.yakit_tipi : []);

        // E) SIRALAMALAR & TABLOLAR
        renderTopKmTable(data.top_km || []);
        renderTopFuelTable(data.top_fuel || []);
        renderExpiringDocsTable(data.expiring_docs || []);
        renderRecentServicesTable(data.recent_services || []);
    }

    // A) 4 Ana KPI Kartı Render
    function renderKpiCards(kpi) {
        if (!kpi) return;

        // Kart 1: Filo
        const f = kpi.filo || {};
        $('#kpi_filo_toplam').text((f.toplam_arac || 0) + ' Araç');
        $('#kpi_filo_aktif').text((f.aktif_arac || 0) + ' Aktif');
        $('#kpi_filo_zimmetli').text((f.zimmetli_arac || 0) + ' Adet');
        $('#kpi_filo_bosta').text((f.bosta_arac || 0) + ' Adet');
        $('#kpi_filo_serviste').text(f.servisteki_arac || 0);
        $('#kpi_filo_ikame').text(f.ikame_arac || 0);

        // Kart 2: Kilometre
        const k = kpi.km || {};
        $('#kpi_km_toplam').text(formatNumber(Math.round(k.toplam_km || 0)) + ' km');
        $('#kpi_km_aktif_arac').text((k.aktif_kullanilan_arac || 0) + ' Araç');
        $('#kpi_km_gunluk_ort').text(formatNumber(Math.round(k.gunluk_ortalama_km || 0)) + ' km');
        $('#kpi_km_arac_basi').text(formatNumber(Math.round(k.arac_basi_ortalama_km || 0)) + ' km');
        if (k.en_cok_km_yapan && k.en_cok_km_yapan.plaka) {
            $('#kpi_km_lider').text(k.en_cok_km_yapan.plaka + ' (' + formatNumber(Math.round(k.en_cok_km_yapan.yapilan_km || 0)) + ' km)');
        } else {
            $('#kpi_km_lider').text('-');
        }

        // Kart 3: Yakıt
        const y = kpi.yakit || {};
        $('#kpi_yakit_toplam_tutar').text(formatMoney(y.toplam_tutar || 0));
        $('#kpi_yakit_toplam_litre').text(formatNumber(Math.round(y.toplam_litre || 0)) + ' Lt');
        $('#kpi_yakit_ort_litre').text(formatMoney(y.ortalama_litre_fiyat || 0) + ' / Lt');
        $('#kpi_yakit_ort_100km').text(Number(y.ortalama_tuketim_100km || 0).toFixed(2) + ' Lt / 100km');
        $('#kpi_yakit_km_basi').text(formatMoney(y.km_basi_maliyet || 0) + ' / km');

        // Kart 4: Bakım & Evrak
        const s = kpi.servis || {};
        const e = kpi.evrak || {};
        $('#kpi_servis_maliyet').text(formatMoney(s.toplam_maliyet || 0));
        $('#kpi_servis_adet').text((s.islem_adedi || 0) + ' İşlem');
        $('#kpi_evrak_gecen').text((e.toplam_gecen || 0) + ' Evrak');
        $('#kpi_evrak_yaklasan').text((e.toplam_yaklasan || 0) + ' Evrak');
        $('#kpi_servis_aktif_servisteki').text(s.servisteki_arac || 0);
    }

    // B) Aylık Yakıt & KM Trendi Grafiği (ApexCharts)
    function renderMonthlyFuelKmChart(trends) {
        if (!trends || !document.getElementById('monthlyFuelKmChart')) return;

        const isDark = document.documentElement.getAttribute('data-theme-preset') === 'macos-dark';
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.06)' : '#f1f5f9';

        const options = {
            series: [
                {
                    name: 'Yakıt Tutarı (₺)',
                    type: 'column',
                    data: trends.yakit_tutar || []
                },
                {
                    name: 'Yapılan KM',
                    type: 'line',
                    data: trends.km || []
                }
            ],
            chart: {
                height: 330,
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            stroke: {
                width: [0, 3],
                curve: 'smooth'
            },
            plotOptions: {
                bar: {
                    columnWidth: '40%',
                    borderRadius: 4
                }
            },
            colors: ['#ef4444', '#10b981'],
            labels: trends.categories || [],
            grid: {
                borderColor: gridColor,
                strokeDashArray: 4
            },
            xaxis: {
                labels: { style: { colors: textColor, fontSize: '11px' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: [
                {
                    title: {
                        text: 'Yakıt Tutarı (₺)',
                        style: { color: '#ef4444', fontSize: '11px', fontWeight: 600 }
                    },
                    labels: {
                        style: { colors: textColor, fontSize: '11px' },
                        formatter: function(val) {
                            return Number(val || 0).toLocaleString('tr-TR', { maximumFractionDigits: 0 }) + ' ₺';
                        }
                    }
                },
                {
                    opposite: true,
                    title: {
                        text: 'Yapılan KM',
                        style: { color: '#10b981', fontSize: '11px', fontWeight: 600 }
                    },
                    labels: {
                        style: { colors: textColor, fontSize: '11px' },
                        formatter: function(val) {
                            return Number(val || 0).toLocaleString('tr-TR', { maximumFractionDigits: 0 }) + ' km';
                        }
                    }
                }
            ],
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                shared: true,
                intersect: false,
                y: {
                    formatter: function(val, { seriesIndex }) {
                        if (seriesIndex === 0) {
                            return formatMoney(val);
                        }
                        return formatNumber(val) + ' km';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                labels: { colors: textColor }
            }
        };

        if (monthlyFuelKmChartInstance) {
            monthlyFuelKmChartInstance.destroy();
        }
        monthlyFuelKmChartInstance = new ApexCharts(document.querySelector("#monthlyFuelKmChart"), options);
        monthlyFuelKmChartInstance.render();
    }

    // C) Departman Dağılımı Donut Grafiği
    function renderDeptChart(deptData) {
        if (!document.getElementById('deptDonutChart')) return;

        const isDark = document.documentElement.getAttribute('data-theme-preset') === 'macos-dark';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        if (!deptData || deptData.length === 0) {
            $('#deptDonutChart').html('<div class="text-center py-5 text-muted font-size-12">Bu dönem için departman verisi bulunamadı.</div>');
            return;
        }

        const labels = [];
        const series = [];

        deptData.forEach(function(item) {
            labels.push(item.departman || 'Diğer');
            if (currentDeptMetric === 'km') {
                series.push(Math.round(Number(item.toplam_km || 0)));
            } else if (currentDeptMetric === 'fuel') {
                series.push(Math.round(Number(item.toplam_yakit_tutar || 0)));
            } else {
                series.push(Number(item.arac_sayisi || 0));
            }
        });

        const options = {
            series: series,
            labels: labels,
            chart: {
                type: 'donut',
                height: 290,
                fontFamily: 'inherit'
            },
            colors: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#64748b'],
            legend: {
                position: 'bottom',
                fontSize: '11px',
                labels: { colors: textColor }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(1) + "%";
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: currentDeptMetric === 'km' ? 'Toplam KM' : (currentDeptMetric === 'fuel' ? 'Toplam Yakıt' : 'Toplam Araç'),
                                fontSize: '12px',
                                color: textColor,
                                formatter: function (w) {
                                    const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    if (currentDeptMetric === 'km') {
                                        return formatNumber(total) + ' km';
                                    } else if (currentDeptMetric === 'fuel') {
                                        return formatMoney(total);
                                    }
                                    return formatNumber(total) + ' Araç';
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        if (currentDeptMetric === 'km') return formatNumber(val) + ' km';
                        if (currentDeptMetric === 'fuel') return formatMoney(val);
                        return formatNumber(val) + ' Araç';
                    }
                }
            }
        };

        if (deptDonutChartInstance) {
            deptDonutChartInstance.destroy();
        }
        deptDonutChartInstance = new ApexCharts(document.querySelector("#deptDonutChart"), options);
        deptDonutChartInstance.render();
    }

    // D) Mülkiyet Dağılımı Grafiği
    function renderOwnershipChart(mulkiyetData) {
        if (!document.getElementById('ownershipChart')) return;

        const isDark = document.documentElement.getAttribute('data-theme-preset') === 'macos-dark';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        const labels = (mulkiyetData || []).map(m => m.mulkiyet || 'Belirtilmemiş');
        const series = (mulkiyetData || []).map(m => Number(m.adet || 0));

        const options = {
            series: series,
            labels: labels,
            chart: {
                type: 'pie',
                height: 220,
                fontFamily: 'inherit'
            },
            colors: ['#0ea5e9', '#3b82f6', '#6366f1', '#a855f7'],
            legend: {
                position: 'right',
                fontSize: '11px',
                labels: { colors: textColor }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(0) + "%";
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        return val + ' Araç';
                    }
                }
            }
        };

        if (ownershipChartInstance) {
            ownershipChartInstance.destroy();
        }
        ownershipChartInstance = new ApexCharts(document.querySelector("#ownershipChart"), options);
        ownershipChartInstance.render();
    }

    // E) Yakıt Tipi Dağılımı Grafiği
    function renderFuelTypeChart(yakitTipiData) {
        if (!document.getElementById('fuelTypeChart')) return;

        const isDark = document.documentElement.getAttribute('data-theme-preset') === 'macos-dark';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        const labels = (yakitTipiData || []).map(y => y.yakit_tipi || 'Belirtilmemiş');
        const series = (yakitTipiData || []).map(y => Number(y.adet || 0));

        const options = {
            series: series,
            labels: labels,
            chart: {
                type: 'donut',
                height: 220,
                fontFamily: 'inherit'
            },
            colors: ['#10b981', '#f59e0b', '#ef4444', '#06b6d4'],
            legend: {
                position: 'right',
                fontSize: '11px',
                labels: { colors: textColor }
            },
            dataLabels: {
                enabled: true,
                formatter: function (val) {
                    return val.toFixed(0) + "%";
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '60%'
                    }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function(val) {
                        return val + ' Araç';
                    }
                }
            }
        };

        if (fuelTypeChartInstance) {
            fuelTypeChartInstance.destroy();
        }
        fuelTypeChartInstance = new ApexCharts(document.querySelector("#fuelTypeChart"), options);
        fuelTypeChartInstance.render();
    }

    // F) En Çok KM Yapan İlk 5 Araç Tablosu
    function renderTopKmTable(items) {
        const tbody = $('#topKmTableBody');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted font-size-12">Kayıt bulunamadı.</td></tr>');
            return;
        }

        items.forEach(function(item, idx) {
            const rowClass = idx === 0 ? 'fw-semibold' : '';
            const rankBadge = idx === 0 ? '<span class="badge bg-warning text-dark rounded-circle px-1.5 py-0.5">1</span>' :
                             (idx === 1 ? '<span class="badge bg-secondary text-white rounded-circle px-1.5 py-0.5">2</span>' :
                             (idx === 2 ? '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-circle px-1.5 py-0.5">3</span>' :
                             `<span class="text-muted font-size-11">${idx + 1}</span>`));

            const html = `
                <tr class="${rowClass}">
                    <td class="text-center">${rankBadge}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-size-11 px-2 py-1">${item.plaka}</span>
                            <span class="text-muted font-size-12">${item.marka || ''} ${item.model || ''}</span>
                        </div>
                    </td>
                    <td>
                        <div class="font-size-12 fw-medium text-dark">${item.surucu_adi || '<span class="text-muted">Atanmamış</span>'}</div>
                        <div class="font-size-11 text-muted">${item.departmani || '-'}</div>
                    </td>
                    <td class="text-end">
                        <span class="text-success fw-bold font-size-13">${formatNumber(Math.round(item.toplam_yapilan_km || 0))} km</span>
                    </td>
                    <td class="text-end font-size-12 text-muted">
                        ${formatNumber(Math.round(item.guncel_km || 0))} km
                    </td>
                </tr>
            `;
            tbody.append(html);
        });
    }

    // G) En Çok Yakıt Tüketen İlk 5 Araç Tablosu
    function renderTopFuelTable(items) {
        const tbody = $('#topFuelTableBody');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center py-4 text-muted font-size-12">Kayıt bulunamadı.</td></tr>');
            return;
        }

        items.forEach(function(item, idx) {
            const rankBadge = idx === 0 ? '<span class="badge bg-danger text-white rounded-circle px-1.5 py-0.5">1</span>' :
                             (idx === 1 ? '<span class="badge bg-warning text-dark rounded-circle px-1.5 py-0.5">2</span>' :
                             (idx === 2 ? '<span class="badge bg-secondary text-white rounded-circle px-1.5 py-0.5">3</span>' :
                             `<span class="text-muted font-size-11">${idx + 1}</span>`));

            const html = `
                <tr>
                    <td class="text-center">${rankBadge}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-size-11 px-2 py-1">${item.plaka}</span>
                            <span class="text-muted font-size-12">${item.marka || ''} ${item.model || ''}</span>
                        </div>
                    </td>
                    <td>
                        <div class="font-size-12 fw-medium text-dark">${item.surucu_adi || '<span class="text-muted">Atanmamış</span>'}</div>
                        <div class="font-size-11 text-muted">${item.departmani || '-'}</div>
                    </td>
                    <td class="text-end">
                        <span class="fw-semibold font-size-12 text-dark">${formatNumber(Math.round(item.toplam_litre || 0))} Lt</span>
                    </td>
                    <td class="text-end">
                        <span class="text-danger fw-bold font-size-13">${formatMoney(item.toplam_tutar || 0)}</span>
                    </td>
                </tr>
            `;
            tbody.append(html);
        });
    }

    // H) Muayene, Sigorta & Kasko Uyarıları Tablosu
    function renderExpiringDocsTable(items) {
        const tbody = $('#expiringDocsTableBody');
        tbody.empty();
        $('#expiringBadgeCount').text((items ? items.length : 0) + ' Uyarı');

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center py-4 text-success font-size-12"><i class="bx bx-check-circle me-1"></i> Süresi dolan veya yaklaşan evrak bulunmuyor.</td></tr>');
            return;
        }

        items.forEach(function(item) {
            let statusBadge = '';
            if (item.kalan_gun < 0) {
                statusBadge = `<span class="badge bg-danger text-white rounded-pill font-size-11 px-2 py-1"><i class="bx bx-error me-0.5"></i> ${Math.abs(item.kalan_gun)} gün gecikti</span>`;
            } else if (item.kalan_gun === 0) {
                statusBadge = `<span class="badge bg-danger text-white rounded-pill font-size-11 px-2 py-1">Bugün son gün!</span>`;
            } else {
                statusBadge = `<span class="badge bg-warning text-dark rounded-pill font-size-11 px-2 py-1">${item.kalan_gun} gün kaldı</span>`;
            }

            const docBadge = item.evrak_tipi === 'Muayene' ? 'bg-primary-subtle text-primary border border-primary-subtle' :
                            (item.evrak_tipi === 'Trafik Sigortası' ? 'bg-info-subtle text-info border border-info-subtle' :
                            'bg-purple-subtle text-purple border border-purple-subtle');

            const html = `
                <tr>
                    <td>
                        <span class="badge bg-dark-subtle text-dark border font-size-11 px-2 py-1">${item.plaka}</span>
                        <span class="text-muted font-size-11 ms-1">${item.marka || ''} ${item.model || ''}</span>
                    </td>
                    <td>
                        <span class="badge ${docBadge} font-size-11 px-2 py-0.5 rounded-pill">${item.evrak_tipi}</span>
                    </td>
                    <td class="font-size-12 fw-medium">
                        ${formatDate(item.bitis_tarihi)}
                    </td>
                    <td class="text-center">
                        ${statusBadge}
                    </td>
                    <td class="font-size-12 text-muted">
                        ${item.surucu_adi || '-'}
                    </td>
                </tr>
            `;
            tbody.append(html);
        });
    }

    // I) Son Servis & Bakım İşlemleri Tablosu
    function renderRecentServicesTable(items) {
        const tbody = $('#recentServicesTableBody');
        tbody.empty();

        if (!items || items.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center py-4 text-muted font-size-12">Servis kaydı bulunamadı.</td></tr>');
            return;
        }

        items.forEach(function(item) {
            const html = `
                <tr>
                    <td>
                        <div class="fw-semibold font-size-12 text-dark">${item.plaka}</div>
                        <div class="font-size-11 text-muted">${item.servis_adi || 'Servis'}</div>
                    </td>
                    <td class="font-size-11 text-muted">
                        ${formatDate(item.servis_tarihi)}
                    </td>
                    <td>
                        <div class="font-size-12 text-truncate" style="max-width: 180px;" title="${item.servis_nedeni || item.yapilan_islemler || '-'}">
                            ${item.servis_nedeni || item.yapilan_islemler || '-'}
                        </div>
                    </td>
                    <td class="text-end">
                        <span class="fw-bold font-size-12 text-primary">${formatMoney(item.tutar || 0)}</span>
                    </td>
                </tr>
            `;
            tbody.append(html);
        });
    }

    // İlk Yüklemede Bu Ay Verilerini Getir
    setPeriodDates(currentPeriod);
    loadDashboardData();
});
