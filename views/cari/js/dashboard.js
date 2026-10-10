$(document).ready(function () {
    let currentPeriod = 'all';
    let customStartDate = null;
    let customEndDate = null;

    let trendChartInstance = null;
    let distributionChartInstance = null;

    const formatMoney = (val) => {
        const num = parseFloat(val || 0);
        return num.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    };

    const formatNumber = (val) => {
        return parseInt(val || 0, 10).toLocaleString('tr-TR');
    };

    const showPreloader = () => {
        $('#dashboardPreloader').addClass('show');
    };

    const hidePreloader = () => {
        $('#dashboardPreloader').removeClass('show');
    };

    // 1. Dashboard Verilerini Yükleme
    const loadDashboardData = () => {
        showPreloader();

        $.ajax({
            url: 'views/cari/api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'dashboard-data',
                period: currentPeriod,
                start_date: customStartDate,
                end_date: customEndDate
            },
            success: function (res) {
                if (res && res.status === 'success') {
                    renderDashboard(res);
                } else {
                    console.error('Dashboard error:', res ? res.message : 'Unknown error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Dashboard Ajax error:', error);
            },
            complete: function () {
                hidePreloader();
            }
        });
    };

    // 2. Dashboard Verilerini Ekrana Çizme
    const renderDashboard = (data) => {
        const s = data.summary || {};
        const dist = data.distribution || {};

        // 2.1. KPI Kartları
        $('#kpi_toplam_alacak').text(formatMoney(s.toplam_alacak));
        $('#kpi_alacakli_sayisi_label').text(`Alacaklı: ${formatNumber(s.alacakli_sayisi)} Cari`);
        $('#kpi_toplam_alacak_bakiye').text(`Bakiye: ${formatMoney(s.toplam_alacak_bakiye)}`);

        $('#kpi_toplam_borc').text(formatMoney(s.toplam_borc));
        $('#kpi_borclu_sayisi_label').text(`Borçlu: ${formatNumber(s.borclu_sayisi)} Cari`);
        $('#kpi_toplam_borc_bakiye').text(`Bakiye: ${formatMoney(s.toplam_borc_bakiye)}`);

        // Net Bakiye
        const netBakiye = parseFloat(s.genel_net_bakiye || 0);
        $('#kpi_net_bakiye').text(formatMoney(Math.abs(netBakiye)));

        let netStatusText = 'Bakiye Dengesi';
        let netBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
        let netBadgeLabel = 'Dengede (0 ₺)';
        let netIconBoxClass = 'bg-primary-subtle text-primary border border-primary-subtle';

        if (netBakiye > 0) {
            netStatusText = 'Net Tahsil Edilecek';
            netBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
            netBadgeLabel = 'Alacaklıyız (Net)';
            netIconBoxClass = 'bg-success-subtle text-success border border-success-subtle';
            $('#kpi_net_bakiye').attr('class', 'stat-kpi-value my-1 text-success');
        } else if (netBakiye < 0) {
            netStatusText = 'Net Ödenecek';
            netBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            netBadgeLabel = 'Borçluyuz (Net)';
            netIconBoxClass = 'bg-danger-subtle text-danger border border-danger-subtle';
            $('#kpi_net_bakiye').attr('class', 'stat-kpi-value my-1 text-danger');
        } else {
            $('#kpi_net_bakiye').attr('class', 'stat-kpi-value my-1 text-dark');
        }

        $('#kpi_bakiye_status_text').text(netStatusText);
        $('#kpi_bakiye_badge').attr('class', `badge ${netBadgeClass} rounded-pill px-2 py-1 font-size-11 fw-semibold`).text(netBadgeLabel);
        $('#kpi_net_icon_box').attr('class', `stat-icon-box ${netIconBoxClass}`);

        // Portföy & İşlem
        $('#kpi_toplam_cari').text(formatNumber(s.toplam_cari));
        $('#kpi_islem_sayisi').text(`Toplam ${formatNumber(s.donem_islem_sayisi || s.toplam_islem_sayisi)} Hareket`);
        $('#kpi_dengede_sayisi').text(`${formatNumber(s.dengede_sayisi)} Dengede`);

        // 2.2. Grafikler
        renderMonthlyTrendChart(data.monthly_trend);
        renderDistributionChart(dist, s);

        // 2.3. Top Cariler
        renderTopAlacaklilar(data.top_alacaklilar || []);
        renderTopBorclular(data.top_borclular || []);

        // 2.4. Son Hareketler
        renderRecentMovements(data.recent_movements || []);
    };

    // 3. Aylık Trend Grafiği (ApexCharts)
    const renderMonthlyTrendChart = (trendData) => {
        if (!trendData || !trendData.categories) return;

        if (typeof ApexCharts === 'undefined') {
            setTimeout(() => renderMonthlyTrendChart(trendData), 100);
            return;
        }

        const options = {
            series: [
                {
                    name: 'Alacak (Verdim)',
                    data: trendData.alacak || []
                },
                {
                    name: 'Borç (Aldım)',
                    data: trendData.borc || []
                }
            ],
            chart: {
                type: 'area',
                height: 320,
                fontFamily: 'inherit',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            colors: ['#10b981', '#ef4444'],
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2.5
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: trendData.categories || [],
                labels: {
                    style: {
                        fontSize: '11.5px',
                        fontWeight: 500,
                        colors: '#64748b'
                    }
                },
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        if (Math.abs(val) >= 1000000) {
                            return (val / 1000000).toFixed(1) + 'M ₺';
                        }
                        if (Math.abs(val) >= 1000) {
                            return (val / 1000).toFixed(0) + 'K ₺';
                        }
                        return val.toFixed(0) + ' ₺';
                    },
                    style: {
                        fontSize: '11px',
                        colors: '#64748b'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatMoney(val);
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                fontWeight: 600,
                markers: {
                    radius: 4
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 4,
                padding: {
                    top: 0,
                    right: 10,
                    bottom: 0,
                    left: 10
                }
            }
        };

        const chartEl = document.querySelector('#chartMonthlyTrend');
        if (!chartEl) return;

        if (trendChartInstance) {
            trendChartInstance.updateOptions(options);
        } else {
            chartEl.innerHTML = '';
            trendChartInstance = new ApexCharts(chartEl, options);
            trendChartInstance.render();
        }
    };

    // 4. Dağılım Donut Grafiği (ApexCharts)
    const renderDistributionChart = (dist, summary = {}) => {
        if (typeof ApexCharts === 'undefined') {
            setTimeout(() => renderDistributionChart(dist, summary), 100);
            return;
        }

        const alacakVal = parseFloat(dist.alacak_tutar || 0);
        const borcVal = parseFloat(dist.borc_tutar || 0);
        const netBakiye = parseFloat(summary.genel_net_bakiye !== undefined ? summary.genel_net_bakiye : (alacakVal - borcVal));

        let centerLabel = 'Net Bakiye';
        let centerColor = '#0f172a';
        if (netBakiye > 0) {
            centerLabel = 'Net Alacağımız';
            centerColor = '#10b981';
        } else if (netBakiye < 0) {
            centerLabel = 'Net Borcumuz';
            centerColor = '#ef4444';
        }

        $('#dist_alacak_val').text(formatMoney(alacakVal));
        $('#dist_borc_val').text(formatMoney(borcVal));
        $('#dist_cari_counts').text(`${formatNumber(dist.alacakli_sayisi)} Alacaklı / ${formatNumber(dist.borclu_sayisi)} Borçlu`);

        const total = alacakVal + borcVal;
        const seriesData = total > 0 ? [alacakVal, borcVal] : [1, 1];

        const options = {
            series: seriesData,
            labels: ['Toplam Alacak Bakiyesi', 'Toplam Borç Bakiyesi'],
            chart: {
                type: 'donut',
                height: 220,
                fontFamily: 'inherit'
            },
            colors: ['#10b981', '#ef4444'],
            dataLabels: {
                enabled: false
            },
            legend: {
                show: false
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '11px',
                                fontWeight: 600,
                                color: '#64748b',
                                offsetY: -4
                            },
                            value: {
                                show: true,
                                fontSize: '14.5px',
                                fontWeight: 800,
                                color: centerColor,
                                offsetY: 4,
                                formatter: function (val) {
                                    return formatMoney(Math.abs(netBakiye));
                                }
                            },
                            total: {
                                show: true,
                                label: centerLabel,
                                fontSize: '11px',
                                fontWeight: 600,
                                color: '#64748b',
                                formatter: function (w) {
                                    return formatMoney(Math.abs(netBakiye));
                                }
                            }
                        }
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return formatMoney(val);
                    }
                }
            }
        };

        const chartEl = document.querySelector('#chartDistribution');
        if (!chartEl) return;

        if (distributionChartInstance) {
            distributionChartInstance.updateOptions(options);
        } else {
            chartEl.innerHTML = '';
            distributionChartInstance = new ApexCharts(chartEl, options);
            distributionChartInstance.render();
        }
    };

    // 5. En Çok Alacaklı Olduğumuz Cariler Listesi
    const renderTopAlacaklilar = (list) => {
        const container = $('#topAlacaklilarList');
        container.empty();

        if (!list || list.length === 0) {
            container.html('<div class="text-center py-4 text-muted font-size-12">Alacaklı cari kaydı bulunamadı.</div>');
            return;
        }

        list.forEach((item, index) => {
            const rank = index + 1;
            let rankClass = 'rank-badge-other';
            if (rank === 1) rankClass = 'rank-badge-1';
            else if (rank === 2) rankClass = 'rank-badge-2';
            else if (rank === 3) rankClass = 'rank-badge-3';

            const unvan = item.firma && item.firma !== item.CariAdi ? `<div class="text-muted font-size-11 text-truncate" style="max-width: 260px;">${item.firma}</div>` : '';
            const location = item.il_ilce ? `<span class="badge bg-light text-secondary border font-size-10 me-1"><i class="bx bx-map-pin"></i> ${item.il_ilce}</span>` : '';

            const rowHtml = `
                <div class="top-item-row d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <span class="rank-badge ${rankClass}">${rank}</span>
                        <div>
                            <div class="fw-bold text-dark font-size-13">${item.CariAdi}</div>
                            ${unvan}
                            <div class="mt-0.5">
                                ${location}
                                <span class="text-muted font-size-11">Son İşlem: ${item.son_islem_tarihi}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end ms-auto d-flex align-items-center gap-2">
                        <div>
                            <div class="fw-extrabold text-success font-size-14">${item.bakiye_fmt}</div>
                            <div class="text-muted font-size-11">${item.islem_sayisi} işlem</div>
                        </div>
                        <a href="index.php?p=cari/hesap-hareketleri&id=${item.enc_id}" class="btn btn-sm btn-subtle-primary p-1 px-2 rounded-2" title="Ekstreye Git">
                            <i class="bx bx-chevron-right font-size-16"></i>
                        </a>
                    </div>
                </div>
            `;
            container.append(rowHtml);
        });
    };

    // 6. En Çok Borçlu Olduğumuz Cariler Listesi
    const renderTopBorclular = (list) => {
        const container = $('#topBorclularList');
        container.empty();

        if (!list || list.length === 0) {
            container.html('<div class="text-center py-4 text-muted font-size-12">Borçlu cari kaydı bulunamadı.</div>');
            return;
        }

        list.forEach((item, index) => {
            const rank = index + 1;
            let rankClass = 'rank-badge-other';
            if (rank === 1) rankClass = 'rank-badge-1';
            else if (rank === 2) rankClass = 'rank-badge-2';
            else if (rank === 3) rankClass = 'rank-badge-3';

            const unvan = item.firma && item.firma !== item.CariAdi ? `<div class="text-muted font-size-11 text-truncate" style="max-width: 260px;">${item.firma}</div>` : '';
            const location = item.il_ilce ? `<span class="badge bg-light text-secondary border font-size-10 me-1"><i class="bx bx-map-pin"></i> ${item.il_ilce}</span>` : '';

            const rowHtml = `
                <div class="top-item-row d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2.5">
                        <span class="rank-badge ${rankClass}">${rank}</span>
                        <div>
                            <div class="fw-bold text-dark font-size-13">${item.CariAdi}</div>
                            ${unvan}
                            <div class="mt-0.5">
                                ${location}
                                <span class="text-muted font-size-11">Son İşlem: ${item.son_islem_tarihi}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-end ms-auto d-flex align-items-center gap-2">
                        <div>
                            <div class="fw-extrabold text-danger font-size-14">${item.bakiye_fmt}</div>
                            <div class="text-muted font-size-11">${item.islem_sayisi} işlem</div>
                        </div>
                        <a href="index.php?p=cari/hesap-hareketleri&id=${item.enc_id}" class="btn btn-sm btn-subtle-primary p-1 px-2 rounded-2" title="Ekstreye Git">
                            <i class="bx bx-chevron-right font-size-16"></i>
                        </a>
                    </div>
                </div>
            `;
            container.append(rowHtml);
        });
    };

    // 7. Son Hareketler Tablosu
    const renderRecentMovements = (movements) => {
        const tbody = $('#recentMovementsTbody');
        tbody.empty();

        if (!movements || movements.length === 0) {
            tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted font-size-12">Henüz kaydedilmiş hesap hareketi bulunmuyor.</td></tr>');
            return;
        }

        movements.forEach((m) => {
            const typeBadge = m.is_borc
                ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-minus-circle me-1"></i>Aldım</span>'
                : '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-plus-circle me-1"></i>Verdim</span>';

            const tutarColor = m.is_borc ? 'text-danger' : 'text-success';
            const tutarSign = m.is_borc ? '-' : '+';

            const unvanSub = m.firma && m.firma !== m.CariAdi ? `<div class="text-muted font-size-11">${m.firma}</div>` : '';

            const rowHtml = `
                <tr>
                    <td class="font-size-12">
                        <span class="fw-semibold text-dark">${m.islem_tarih_gun}</span>
                        <div class="text-muted font-size-11">${m.islem_tarih_saat}</div>
                    </td>
                    <td>
                        <a href="index.php?p=cari/hesap-hareketleri&id=${m.cari_id_enc}" class="fw-bold text-dark text-decoration-none font-size-13 hover-primary">
                            ${m.CariAdi}
                        </a>
                        ${unvanSub}
                    </td>
                    <td class="text-center">${typeBadge}</td>
                    <td>
                        ${m.belge_no ? `<span class="badge bg-light text-dark border font-monospace font-size-11">${m.belge_no}</span>` : '<span class="text-muted">-</span>'}
                    </td>
                    <td>
                        <span class="text-secondary font-size-12 d-inline-block text-truncate" style="max-width: 250px;">
                            ${m.aciklama || '-'}
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="fw-bold ${tutarColor} font-size-13">${tutarSign} ${m.tutar_fmt}</span>
                    </td>
                    <td class="font-size-11 text-muted">
                        <i class="bx bx-user me-0.5"></i> ${m.ekleyen_adi}
                    </td>
                    <td class="text-center">
                        <a href="index.php?p=cari/hesap-hareketleri&id=${m.cari_id_enc}" class="btn btn-sm btn-subtle-primary p-1 px-2 rounded-2" title="Ekstreye Git">
                            <i class="bx bx-window-open font-size-14"></i>
                        </a>
                    </td>
                </tr>
            `;
            tbody.append(rowHtml);
        });
    };

    // 8. Dönem Filtreleri Etkileşimi
    $('#dashboardPeriodGroup .period-btn').on('click', function () {
        const period = $(this).data('period');
        if (period === 'custom') {
            const modal = new bootstrap.Modal(document.getElementById('customRangeModal'));
            modal.show();
            return;
        }

        $('#dashboardPeriodGroup .period-btn').removeClass('active');
        $(this).addClass('active');
        currentPeriod = period;
        customStartDate = null;
        customEndDate = null;
        $('#customRangeLabel').text('Özel');

        loadDashboardData();
    });

    // Özel Tarih Aralığı Uygulama
    $('#btnApplyCustomRange').on('click', function () {
        const start = $('#customStartDate').val();
        const end = $('#customEndDate').val();

        if (!start || !end) {
            alert('Lütfen başlangıç ve bitiş tarihlerini seçiniz.');
            return;
        }

        currentPeriod = 'custom';
        customStartDate = start;
        customEndDate = end;

        $('#dashboardPeriodGroup .period-btn').removeClass('active');
        $('#btnCustomRange').addClass('active');
        $('#customRangeLabel').text(`${start} - ${end}`);

        bootstrap.Modal.getInstance(document.getElementById('customRangeModal')).hide();
        loadDashboardData();
    });

    // Yenile Butonu
    $('#btnRefreshDashboard').on('click', function () {
        loadDashboardData();
    });

    // İlk Yükleme
    loadDashboardData();
});
