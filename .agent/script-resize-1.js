            // Number Cou            nt                                 er F           unction
            function animateValue(obj, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    obj.innerHTML = Math.floor(progress * (end - start) + start);
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }

            var months = null;
            var totals = null;

            var options = {
                chart: { type: 'line', height: 350 },
                series: [{ name: 'Üye Sayısı', data: totals }],
                xaxis: { categories: months },
                colors: ['#556ee6']
            }
            // new ApexCharts(document.querySelector("#chart"), options).render();

            var options2 = {
                series: [{ name: 'Gelir', data: [44, 55, 57, 56, 61, 58, 63, 60, 66, 85, 96, 85] },
                { name: 'Gider', data: [76, 85, 101, 98, 87, 105, 91, 114, 94, 78, 77, 25] }],
                chart: { type: 'bar', height: 350 },
                plotOptions: { bar: { horizontal: false, columnWidth: '55%', borderRadius: 4 } },
                xaxis: { categories: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'] },
                colors: ['#34c38f', '#f46a6a']
            };
            // new ApexCharts(document.querySelector("#chart2"), options2).render();

            // var options3 = {
            //     series: [null, null, null],
            //     chart: { type: 'polarArea', height: 350 },
            //     labels: ['Gelir', 'Gider', 'Kasa'],
            //     colors: ['#34c38f', '#f46a6a', '#556ee6']
            // };
            // new ApexCharts(document.querySelector("#chart3"), options3).render();

            let workTypeChart;
            function showChartSkeleton(chartElement, mode = 'bar') {
                if (!chartElement) return;

                const skeletonHtml = mode === 'line'
                    ? `<div class="dashboard-chart-skeleton"><div class="skeleton-line w-50 mb-3"></div><div class="skeleton-chart-lines"><span class="w-100"></span><span class="w-85"></span><span class="w-70"></span><span class="w-92"></span><span class="w-60"></span></div></div>`
                    : `<div class="dashboard-chart-skeleton"><div class="skeleton-line w-40 mb-3"></div><div class="skeleton-chart-bars"><span style="height: 32%;"></span><span style="height: 46%;"></span><span style="height: 64%;"></span><span style="height: 52%;"></span><span style="height: 75%;"></span><span style="height: 58%;"></span><span style="height: 80%;"></span><span style="height: 43%;"></span></div></div>`;

                chartElement.innerHTML = skeletonHtml;
            }

            function loadWorkTypeStats(year) {
                if (typeof ApexCharts === 'undefined') {
                    console.log('ApexCharts henüz yüklenmedi, 500ms sonra tekrar denenecek...');
                    setTimeout(() => loadWorkTypeStats(year), 500);
                    return;
                }

                const chartElement = document.querySelector("#work-type-stats-chart");
                if (!chartElement) return;
                showChartSkeleton(chartElement, 'bar');

                const formData = new FormData();
                formData.append('action', 'get-work-type-stats');
                formData.append('year', year);
                // İş türü her zaman tüm yılı gösterecek

                fetch('views/home/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (!data.data.series || data.data.series.length === 0) {
                                chartElement.innerHTML = '<div class="alert alert-info text-center mt-5">Seçilen yıla ait istatistik verisi bulunamadı.</div>';
                                workTypeChart = null;
                                return;
                            }

                            const options = {
                                series: data.data.series,
                                chart: {
                                    type: 'bar',
                                    height: '100%',
                                    stacked: false,
                                    toolbar: { show: true },
                                    animations: { enabled: true }
                                },
                                plotOptions: {
                                    bar: {
                                        horizontal: false,
                                        columnWidth: '55%',
                                        borderRadius: 5
                                    },
                                },
                                dataLabels: { enabled: false },
                                stroke: {
                                    show: true,
                                    width: 2,
                                    colors: ['transparent']
                                },
                                xaxis: {
                                    categories: data.data.categories,
                                },
                                yaxis: {
                                    title: { text: 'İş Adeti' }
                                },
                                fill: { opacity: 1 },
                                colors: ['#556ee6', '#34c38f', '#f46a6a', '#f1b44c', '#50a5f1'],
                                tooltip: {
                                    y: {
                                        formatter: function (val) {
                                            return val + " adet"
                                        }
                                    }
                                }
                            };

                            chartElement.innerHTML = '';
                            if (workTypeChart) {
                                workTypeChart.destroy();
                            }

                            workTypeChart = new ApexCharts(chartElement, options);
                            workTypeChart.render();
                        }
                    })
                    .catch(err => {
                        console.error('İstatistik yükleme hatası:', err);
                        chartElement.innerHTML = '<div class="alert alert-danger text-center mt-5">Veriler yüklenirken bir hata oluştu.</div>';
                    });
            }

            let workResultChart;
            function loadWorkResultStats(year, month = "") {
                if (typeof ApexCharts === 'undefined') {
                    setTimeout(() => loadWorkResultStats(year, month), 500);
                    return;
                }

                const chartElement = document.querySelector("#work-result-stats-chart");
                if (!chartElement) return;
                showChartSkeleton(chartElement, 'line');

                const formData = new FormData();
                formData.append('action', 'get-work-result-stats');
                formData.append('year', year);
                formData.append('month', month);

                fetch('views/home/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            if (!data.data.series || data.data.series.length === 0) {
                                chartElement.innerHTML = '<div class="alert alert-info text-center mt-5">Seçilen yıla ait sonuç verisi bulunamadı.</div>';
                                return;
                            }

                            const options = {
                                series: data.data.series,
                                chart: {
                                    type: 'bar',
                                    height: '100%',
                                    stacked: false,
                                    toolbar: { show: true }
                                },
                                plotOptions: {
                                    bar: {
                                        horizontal: true,
                                        columnWidth: '55%',
                                        borderRadius: 5,
                                        dataLabels: { position: 'top' }
                                    },
                                },
                                dataLabels: {
                                    enabled: true,
                                    offsetX: -6,
                                    style: { fontSize: '12px', colors: ['#fff'] }
                                },
                                xaxis: {
                                    categories: data.data.categories,
                                },
                                title: {
                                    text: data.data.selected_month + ' Ayı Sonuç Dağılımı',
                                    align: 'center'
                                },
                                yaxis: {
                                    labels: {
                                        maxWidth: 300,
                                        style: { fontSize: '11px' }
                                    }
                                },
                                fill: { opacity: 1 },
                                tooltip: {
                                    y: {
                                        formatter: function (val) {
                                            return val + " adet"
                                        }
                                    }
                                }
                            };

                            chartElement.innerHTML = '';
                            if (workResultChart) {
                                workResultChart.destroy();
                            }

                            workResultChart = new ApexCharts(chartElement, options);
                            workResultChart.render();
                        }
                    });
            }


            document.addEventListener('DOMContentLoaded', function () {
                const pageSkeleton = document.getElementById('dashboard-page-skeleton');
                const pageContent = document.getElementById('dashboard-page-content');
                const criticalSkeletonStyle = document.getElementById('dashboard-skeleton-critical');
                const criticalWidgetIds = new Set([
                    'widget-ana-slider',
                    'widget-personel-ozet',
                    'widget-arac-ozet',
                    'widget-bekleyen-talepler',
                    'widget-gec-kalanlar',
                    'widget-nobetciler'
                ]);
                let dashboardRevealed = false;

                const prepareStagedWidgets = () => {
                    document.querySelectorAll('#dashboard-widgets .widget-item').forEach((widget) => {
                        if (!criticalWidgetIds.has(widget.id)) {
                            widget.classList.add('dashboard-phase2-hidden');
                        }
                    });
                };

                const revealPhase2Widgets = () => {
                    document.querySelectorAll('#dashboard-widgets .dashboard-phase2-hidden').forEach((widget) => {
                        widget.classList.remove('dashboard-phase2-hidden');
                    });
                };

                const revealDashboardPage = () => {
                    if (dashboardRevealed) return;
                    dashboardRevealed = true;
                    if (pageSkeleton) pageSkeleton.style.display = 'none';
                    if (pageContent) pageContent.style.display = '';
                    if (criticalSkeletonStyle) criticalSkeletonStyle.remove();

                    if ('requestIdleCallback' in window) {
                        requestIdleCallback(() => revealPhase2Widgets(), { timeout: 500 });
                    } else {
                        setTimeout(revealPhase2Widgets, 350);
                    }
                };

                prepareStagedWidgets();

                const scheduleDashboardReveal = () => {
                    window.requestAnimationFrame(() => {
                        setTimeout(revealDashboardPage, 60);
                    });
                };

                scheduleDashboardReveal();

                setTimeout(() => {
                    if (!dashboardRevealed) {
                        revealDashboardPage();
                    }
                }, 1200);

                const API_URL = 'views/talepler/api.php';

                // Load widget visibility from localStorage
                function setWidgetVisibility(widgetId, isVisible, options = {}) {
                    const widget = $(`#${widgetId}`);
                    if (!widget.length) return;

                    const animate = options.animate === true;
                    const syncCheckbox = options.syncCheckbox !== false;

                    widget.toggleClass('widget-hidden', !isVisible);

                    if (animate) {
                        if (isVisible) {
                            widget.stop(true, true).fadeIn(200);
                        } else {
                            widget.stop(true, true).fadeOut(200);
                        }
                    } else if (isVisible) {
                        widget.show();
                    } else {
                        widget.hide();
                    }

                    if (syncCheckbox) {
                        $(`input[data-widget="${widgetId}"]`).prop('checked', isVisible);
                    }
                }

                function loadWidgetVisibility() {
                    const visibility = localStorage.getItem('dashboard_widget_visibility');
                    if (visibility) {
                        const visibleWidgets = JSON.parse(visibility);
                        $('#dashboard-widgets .widget-item').each(function () {
                            const id = $(this).attr('id');
                            // Eğer localStorage'da yoksa varsayılan olarak göster (true)
                            const isVisible = visibleWidgets[id] !== false;
                            setWidgetVisibility(id, isVisible, { syncCheckbox: true });
                        });
                    }
                }

                // Save widget visibility to localStorage
                function saveWidgetVisibility() {
                    const visibility = {};
                    $('input.widget-toggle').each(function () {
                        const widgetId = $(this).data('widget');
                        visibility[widgetId] = $(this).is(':checked');
                    });
                    localStorage.setItem('dashboard_widget_visibility', JSON.stringify(visibility));
                }

                // Toggle widget visibility
                $(document).on('change', '.widget-toggle', function () {
                    const widgetId = $(this).data('widget');
                    const isChecked = $(this).is(':checked');
                    setWidgetVisibility(widgetId, isChecked, { animate: true, syncCheckbox: false });
                    saveWidgetVisibility();
                    saveDashboardConfig();
                });

                // Load visibility on page load
                loadWidgetVisibility();

                // Theme change listener for checkbox colors and button colors
                function updateThemeColors() {
                    const html = document.documentElement;
                    const isDarkMode = html.getAttribute('data-bs-theme') === 'dark';
                    const themeMode = html.getAttribute('data-theme-mode') || 'default';

                    // Color Palette Map
                    const colors = {
                        'red': '#f46a6a',
                        'orange': '#f1b44c',
                        'emerald': '#34c38f',
                        'purple': '#6f42c1',
                        'slate': '#475569',
                        'default': '#5156be'
                    };

                    // Get color based on theme
                    const color = colors[themeMode] || colors['default'];

                    // Set CSS custom property for checkboxes
                    document.documentElement.style.setProperty('--dashboard-theme-color', color);

                    // Update checkboxes
                    const checkboxes = document.querySelectorAll('.widget-toggle');
                    checkboxes.forEach(checkbox => {
                        checkbox.style.accentColor = color;
                    });

                    // Update dashboard control buttons
                    const dashboardBtns = document.querySelectorAll('#btn-reset-dashboard, .d-flex.gap-2 .dropdown > .btn');
                    dashboardBtns.forEach(btn => {
                        if (isDarkMode) {
                            btn.style.borderColor = '#334155';
                            btn.style.backgroundColor = '#1e293b';
                        } else {
                            btn.style.borderColor = '#e5e7eb';
                            btn.style.backgroundColor = '#fff';
                        }
                        btn.style.color = color;
                    });

                    // Update soft-primary buttons (like Detay)
                    const softPrimaryBtns = document.querySelectorAll('.btn-soft-primary');
                    softPrimaryBtns.forEach(btn => {
                        btn.style.backgroundColor = hexToRgba(color, 0.1);
                        btn.style.borderColor = hexToRgba(color, 0.2);
                        btn.style.color = color;
                    });
                }

                // Helper: Hex to RGBA
                function hexToRgba(hex, alpha) {
                    const r = parseInt(hex.slice(1, 3), 16);
                    const g = parseInt(hex.slice(3, 5), 16);
                    const b = parseInt(hex.slice(5, 7), 16);
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                }

                // Initial call
                updateThemeColors();

                // Watch for theme changes
                const observer = new MutationObserver(() => {
                    updateThemeColors();
                });

                observer.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-bs-theme', 'data-theme-mode']
                });

                // Start counters
                document.querySelectorAll('.main-value').forEach(el => {
                    const finalValue = parseInt(el.innerText);
                    el.innerText = '0';
                    setTimeout(() => {
                        animateValue(el, 0, finalValue, 1500);
                    }, 300);
                });

                // Log Detay Modal
                document.querySelectorAll('.btn-log-detay').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var title = this.dataset.title;
                        var user = this.dataset.user;
                        var date = this.dataset.date;
                        var content = this.dataset.content;
                        document.getElementById('logDetayTitle').textContent = title;
                        document.getElementById('logDetayUser').textContent = user;
                        document.getElementById('logDetayDate').textContent = date;

                        if (content.includes('(Güncellenen veriler: {')) {
                            try {
                                let parts = content.split(' (Güncellenen veriler: { ');
                                let mainText = parts[0];
                                let changesPart = parts[1].replace(/ ?\}\)?$/, '');
                                let changes = changesPart.split(', ');

                                let formattedContent = `<div class="d-flex align-items-start gap-2 mb-3">
                                <i class='bx bx-edit-alt text-primary mt-1' style='font-size:1.1rem;flex-shrink:0;'></i>
                                <span style='font-size:0.875rem;color:#374151;line-height:1.55;'>${mainText}</span>
                            </div>`;

                                if (changes.some(c => c.includes(': '))) {
                                    formattedContent += `<div class="change-table">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Alan</th><th>Değişim</th></tr></thead>
                                        <tbody>`;
                                    changes.forEach(change => {
                                        if (change.includes(': ')) {
                                            let sepIdx = change.indexOf(': ');
                                            let key = change.substring(0, sepIdx).trim();
                                            let val = change.substring(sepIdx + 2).trim();
                                            let displayVal = val;
                                            if (val.includes(' -> ')) {
                                                let [from, to] = val.split(' -> ');
                                                displayVal = `<span class="change-arrow">
                                                <span class="from-val">${from || 'Boş'}</span>
                                                <i class='bx bx-right-arrow-alt arrow-icon'></i>
                                                <span class="to-val">${to || 'Boş'}</span>
                                            </span>`;
                                            } else if (val.includes(' → ')) {
                                                let [from, to] = val.split(' → ');
                                                displayVal = `<span class="change-arrow">
                                                <span class="from-val">${from || 'Boş'}</span>
                                                <i class='bx bx-right-arrow-alt arrow-icon'></i>
                                                <span class="to-val">${to || 'Boş'}</span>
                                            </span>`;
                                            }
                                            formattedContent += `<tr>
                                            <td class="field-cell">${key}</td>
                                            <td>${displayVal}</td>
                                        </tr>`;
                                        }
                                    });
                                    formattedContent += `</tbody></table></div>`;
                                }
                                document.getElementById('logDetayContent').innerHTML = formattedContent;
                                document.getElementById('logDetayContent').style.whiteSpace = 'normal';
                            } catch (e) {
                                document.getElementById('logDetayContent').textContent = content;
                            }
                        } else {
                            // Düz metin — satır sonlarını <br> ile göster
                            document.getElementById('logDetayContent').innerHTML =
                                '<i class="bx bx-info-circle text-primary me-2" style="font-size:1rem;vertical-align:middle;"></i>' +
                                content.replace(/\n/g, '<br>');
                            document.getElementById('logDetayContent').style.whiteSpace = 'normal';
                        }
                        new bootstrap.Modal(document.getElementById('modalLogDetay')).show();
                    });
                });

                // Detay Modal - API'den detay çekiyor
                document.querySelectorAll('.btn-home-detay').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = this.dataset.id;
                        var tip = this.dataset.tip;
                        var headerClass = tip === 'Avans' ? 'tip-avans' : (tip === 'İzin' ? 'tip-izin' : 'tip-talep');
                        var headerIcon = tip === 'Avans' ? 'bx-money' : (tip === 'İzin' ? 'bx-calendar-check' : 'bx-message-square-detail');

                        // Header'ı ayarla
                        document.getElementById('modalHeader').className = 'modal-detay-header ' + headerClass;
                        document.getElementById('modalTalepTipi').textContent = tip;
                        document.getElementById('modalHeaderIcon').className = 'bx ' + headerIcon;

                        // Tab parametresini ayarla
                        var tabParam = tip === 'Avans' ? 'avans' : (tip === 'İzin' ? 'izin' : 'talep');
                        document.getElementById('modalGitBtn').href = 'index.php?p=talepler/list&tab=' + tabParam;

                        // Loading göster, content gizle
                        document.getElementById('modalLoading').style.display = 'block';
                        document.getElementById('modalContent').style.display = 'none';

                        // Modalı aç
                        new bootstrap.Modal(document.getElementById('modalHomeDetay')).show();

                        // API'den detay çek
                        var actionName = tip === 'Avans' ? 'get-avans-detay' : (tip === 'İzin' ? 'get-izin-detay' : 'get-talep-detay');
                        var formData = new FormData();
                        formData.append('action', actionName);
                        formData.append('id', id);

                        fetch(API_URL, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                document.getElementById('modalLoading').style.display = 'none';
                                document.getElementById('modalContent').style.display = 'flex';

                                if (data.status === 'success') {
                                    var d = data.data;

                                    // Resim
                                    var resimEl = document.getElementById('modalResim');
                                    resimEl.src = d.resim_yolu || 'assets/images/users/user-dummy-img.jpg';
                                    resimEl.onerror = function () { this.src = 'assets/images/users/user-dummy-img.jpg'; };

                                    // Personel bilgileri
                                    document.getElementById('modalPersonelAdi').textContent = d.adi_soyadi || '-';
                                    document.getElementById('modalDepartman').textContent = d.departman || '';
                                    document.getElementById('modalGorev').textContent = d.gorev || '';

                                    // Başlık satırını kontrol et (Sadece Talep tipinde gösterilir)
                                    var rowBaslik = document.getElementById('rowBaslik');
                                    if (tip === 'Talep') {
                                        rowBaslik.style.display = 'table-row';
                                        document.getElementById('modalBaslik').textContent = d.baslik || '-';
                                    } else {
                                        rowBaslik.style.display = 'none';
                                    }

                                    // Fotoğraf satırını kontrol et
                                    var rowFotograf = document.getElementById('rowFotograf');
                                    if (d.foto || d.dosya_yolu || d.fotograf_yolu) {
                                        var fotoPath = d.foto || d.dosya_yolu || d.fotograf_yolu;
                                        rowFotograf.style.display = 'table-row';
                                        document.getElementById('modalFoto').src = fotoPath;
                                        document.getElementById('modalFotoLink').href = fotoPath;
                                    } else {
                                        rowFotograf.style.display = 'none';
                                    }

                                    // Tip'e göre detay ve tarih bilgisi
                                    if (tip === 'Avans') {
                                        var tutar = parseFloat(d.tutar || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                                        document.getElementById('modalDetay').textContent = tutar;
                                        document.getElementById('modalTarih').textContent = formatTarih(d.talep_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.durum) + '</span>';
                                    } else if (tip === 'İzin') {
                                        var izinDetay = (d.izin_tipi_adi || d.izin_tipi || 'İzin');
                                        if (d.gun_sayisi) izinDetay += ' (' + d.gun_sayisi + ' gün)';
                                        document.getElementById('modalDetay').textContent = izinDetay;
                                        document.getElementById('modalTarih').textContent = formatTarih(d.baslangic_tarihi) + ' - ' + formatTarih(d.bitis_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.onay_durumu) + '</span>';
                                    } else {
                                        document.getElementById('modalDetay').textContent = d.aciklama || '-';
                                        document.getElementById('modalTarih').textContent = formatTarih(d.olusturma_tarihi);
                                        document.getElementById('modalDurum').innerHTML = '<span class="badge bg-warning text-dark px-2 py-1"><i class="bx bx-time me-1"></i>' + ucFirst(d.durum) + '</span>';
                                    }
                                } else {
                                    document.getElementById('modalContent').innerHTML = '<div class="col-12 text-center py-4"><div class="alert alert-danger">' + (data.message || 'Bir hata oluştu') + '</div></div>';
                                }
                            })
                            .catch(error => {
                                document.getElementById('modalLoading').style.display = 'none';
                                document.getElementById('modalContent').style.display = 'flex';
                                document.getElementById('modalContent').innerHTML = '<div class="col-12 text-center"><div class="alert alert-danger">Detaylar yüklenirken hata oluştu.</div></div>';
                            });
                    });
                });

                // Yardımcı fonksiyonlar
                function formatTarih(dateStr) {
                    if (!dateStr) return '-';
                    var date = new Date(dateStr);
                    return date.toLocaleDateString('tr-TR');
                }

                function ucFirst(str) {
                    if (!str) return '';
                    return str.charAt(0).toUpperCase() + str.slice(1);
                }

                // Avans Onayla/Reddet, İzin Onayla/Reddet, Talep Çözüldü
                document.querySelectorAll('.btn-avans-onayla').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('avans_onay_id').value = this.dataset.id;
                        document.getElementById('avans_onay_personel').textContent = this.dataset.personel;
                        document.getElementById('avans_onay_tutar').textContent = parseFloat(this.dataset.tutar).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
                        new bootstrap.Modal(document.getElementById('modalAvansOnay')).show();
                    });
                });
                document.querySelectorAll('.btn-avans-reddet').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('avans_red_id').value = this.dataset.id;
                        document.getElementById('avans_red_personel').textContent = this.dataset.personel;
                        new bootstrap.Modal(document.getElementById('modalAvansRed')).show();
                    });
                });
                document.querySelectorAll('.btn-izin-onayla').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('izin_onay_id').value = this.dataset.id;
                        document.getElementById('izin_onay_personel').textContent = this.dataset.personel;
                        document.getElementById('izin_onay_tur').textContent = this.dataset.tur;
                        document.getElementById('izin_onay_gun').textContent = this.dataset.gun;
                        new bootstrap.Modal(document.getElementById('modalIzinOnay')).show();
                    });
                });
                document.querySelectorAll('.btn-izin-reddet').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('izin_red_id').value = this.dataset.id;
                        document.getElementById('izin_red_personel').textContent = this.dataset.personel;
                        new bootstrap.Modal(document.getElementById('modalIzinRed')).show();
                    });
                });
                document.querySelectorAll('.btn-talep-cozuldu').forEach(btn => {
                    btn.addEventListener('click', function () {
                        document.getElementById('talep_cozuldu_id').value = this.dataset.id;
                        document.getElementById('talep_cozuldu_baslik').textContent = this.dataset.baslik;
                        new bootstrap.Modal(document.getElementById('modalTalepCozuldu')).show();
                    });
                });

                const handleFormSubmit = (formId) => {
                    const form = document.getElementById(formId);
                    if (!form) return;
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        const submitBtn = this.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> İşleniyor...';
                        fetch(API_URL, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message, timer: 1500, showConfirmButton: false })
                                        .then(() => location.reload());
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalText;
                                }
                            })
                            .catch(error => {
                                Swal.fire({ icon: 'error', title: 'Hata', text: 'Bir sorun oluştu.' });
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                    });
                };

                handleFormSubmit('formAvansOnay');
                handleFormSubmit('formAvansRed');
                handleFormSubmit('formIzinOnay');
                handleFormSubmit('formIzinRed');
                handleFormSubmit('formTalepCozuldu');

                // İş Türü İstatistikleri (Yıllık)
                const yearFilter = document.getElementById('stats-year-filter');
                if (yearFilter) {
                    yearFilter.addEventListener('change', function () {
                        loadWorkTypeStats(this.value);
                    });
                    loadWorkTypeStats(yearFilter.value);
                }

                // İş Emri Sonuçları (Aylık)
                const resultMonthFilter = document.getElementById('stats-result-month-filter');
                const resultYearFilter = document.getElementById('stats-result-year-filter');

                if (resultMonthFilter && resultYearFilter) {
                    const refreshResultStats = () => {
                        loadWorkResultStats(resultYearFilter.value, resultMonthFilter.value);
                    };
                    resultMonthFilter.addEventListener('change', refreshResultStats);
                    resultYearFilter.addEventListener('change', refreshResultStats);
                    refreshResultStats();
                }
                // Dashboard Config Persistence
                const dashboard = $("#dashboard-widgets");

                function saveDashboardConfig() {
                    const order = [];
                    $("#dashboard-widgets .widget-item").each(function() {
                        const id = $(this).attr('id');
                        if (id) order.push(id);
                    });
                    const settings = {};
                    $("#dashboard-widgets .widget-item").each(function () {
                        const id = $(this).attr('id');
                        const isResized = $(this).attr('data-resized') === 'true' || ($(this).css('width') && $(this).css('width').indexOf('px') !== -1 && $(this).attr('style') && $(this).attr('style').indexOf('width') !== -1);
                        const isHidden = $(this).is(':hidden') || $(this).hasClass('widget-hidden');

                        const s = {};
                        if (isResized) {
                            s.width = $(this).css('width');
                            let heightVal = $(this).css('height');
                            if (!heightVal || heightVal === '0px') {
                                heightVal = $(this).find('.card').css('height');
                            }
                            s.height = heightVal;
                        }
                        if (isHidden) {
                            s.hidden = 'true';
                        }
                        let positionVal = $(this).css('position');
                        if (positionVal === 'absolute') {
                            s.left = $(this).css('left');
                            s.top = $(this).css('top');
                        }

                        if (id && Object.keys(s).length > 0) {
                            settings[id] = s;
                        }
                    });

                    const cookieOptions = "; path=/; max-age=" + (60 * 60 * 24 * 30);
                    document.cookie = "dashboard_order=" + JSON.stringify(order) + cookieOptions;
                    document.cookie = "dashboard_settings=" + JSON.stringify(settings) + cookieOptions;
                    
                    // Save to localStorage too for backup & reliability
                    localStorage.setItem('dashboard_widget_settings', JSON.stringify(settings));
                }

                function applyWidgetSettings() {
                    const settings = JSON.parse(localStorage.getItem('dashboard_widget_settings') || '{}');
                    Object.keys(settings).forEach(id => {
                        const widget = $('#' + id);
                        if (widget.length && settings[id]) {
                            const s = settings[id];
                            setWidgetVisibility(id, s.hidden !== 'true', { syncCheckbox: true });
                        }
                    });

                    if (!$('#switch-free-layout').is(':checked')) return;

                    Object.keys(settings).forEach(id => {
                        const widget = $('#' + id);
                        if (widget.length && settings[id]) {
                            const s = settings[id];
                            const css = {};
                            if (s.width && s.width.indexOf('col-') === -1) {
                                css.width = s.width;
                                css.flex = 'none';
                                css.maxWidth = 'none';
                            }
                            if (s.height && s.height !== 'auto') {
                                css.height = s.height;
                                widget.find('.card, .carousel').css({
                                    'height': '100%',
                                    'min-height': '0',
                                    'max-height': 'none'
                                });
                            }
                            if (s.left && s.top) {
                                css.position = 'absolute';
                                css.left = s.left;
                                css.top = s.top;
                                css.zIndex = 100;
                            }
                            widget.css(css);
                        }
                    });
                }

                let gridSortable = null;

                function initGridSortable() {
                    if (typeof Sortable !== 'undefined') {
                        const container = document.getElementById('dashboard-widgets');
                        if (container && !gridSortable) {
                            gridSortable = new Sortable(container, {
                                animation: 150,
                                handle: '.drag-handle, .card-header, .stat-card, .card',
                                filter: '.mac-title-bar, .btn, a, input, select, textarea, .custom-resize-handle, .mac-controls',
                                preventOnFilter: true,
                                ghostClass: 'bg-light',
                                onEnd: function () {
                                    saveDashboardConfig();
                                }
                            });
                        }
                    }
                }

                function destroyGridSortable() {
                    if (gridSortable) {
                        gridSortable.destroy();
                        gridSortable = null;
                    }
                }

                applyWidgetSettings();

                if (typeof Sortable === 'undefined') {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js';
                    s.onload = function() {
                        if (!$('#switch-free-layout').is(':checked')) {
                            initGridSortable();
                        }
                    };
                    document.head.appendChild(s);
                } else {
                    if (!$('#switch-free-layout').is(':checked')) {
                        initGridSortable();
                    }
                }

                // Raise z-index on clicking/focusing any part of a card
                $(document).on('mousedown', '#dashboard-widgets .widget-item', function () {
                    if ($('#switch-free-layout').is(':checked')) {
                        maxZIndex++;
                        $(this).css('z-index', maxZIndex);
                    }
                });

                // Switch change listener
                $(document).on('change', '#switch-free-layout', function() {
                    localStorage.setItem('switch_free_layout', this.checked ? 'true' : 'false');
                    if (this.checked) {
                        destroyGridSortable();
                        $('#dashboard-widgets').addClass('free-layout-active');
                        applyWidgetSettings();
                        if (typeof initResizableWidgets === 'function') initResizableWidgets();
                    } else {
                        initGridSortable();
                        $('#dashboard-widgets').removeClass('free-layout-active');
                        // Restore all widgets to flow
                        $('#dashboard-widgets .widget-item').removeClass('resizable-widget').css({
                            position: '',
                            left: '',
                            top: '',
                            width: '',
                            height: '',
                            flex: '',
                            maxWidth: '',
                            zIndex: ''
                        });
                        $('#dashboard-widgets .card-header.mac-title-bar').each(function () {
                            $(this).removeClass('mac-title-bar').removeAttr('style');
                            $(this).find('.mac-controls, .drag-handle-indicator').remove();
                        });
                        $('#dashboard-widgets .mac-title-bar').not('.card-header').remove();
                        $('.custom-resize-handle').remove();
                        $('#dashboard-widgets .widget-item h5, #dashboard-widgets .widget-item h6, #dashboard-widgets .widget-item strong').show();
                    }
                });

                let maxZIndex = 1100;

                // Card Movement Logic (Move like a Windows Desktop Window)
                $(document).on('mousedown touchstart', '#dashboard-widgets .widget-item .mac-title-bar, #dashboard-widgets .widget-item .card-header', function (e) {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    if ($(e.target).closest('.mac-control, .btn, a, input, select, textarea, .custom-resize-handle').length) return;

                    let clientX = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientX : e.clientX;
                    let clientY = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientY : e.clientY;

                    const widget = $(this).closest('.widget-item');
                    maxZIndex++;

                    if (widget.css('position') !== 'absolute') {
                        const offset = widget.position();
                        widget.css({
                            'position': 'absolute',
                            'left': offset.left + 'px',
                            'top': offset.top + 'px',
                            'z-index': maxZIndex,
                            'flex': 'none',
                            'max-width': 'none'
                        });
                    } else {
                        widget.css('z-index', maxZIndex);
                    }

                    let startX = clientX;
                    let startY = clientY;
                    let initialLeft = parseFloat(widget.css('left')) || 0;
                    let initialTop = parseFloat(widget.css('top')) || 0;
                    let isMoving = true;

                    $(document).on('mousemove.widgetMove touchmove.widgetMove', function (e) {
                        if (!isMoving) return;
                        let moveX = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientX : e.clientX;
                        let moveY = e.type.startsWith('touch') ? e.originalEvent.touches[0].clientY : e.clientY;
                        const newLeft = initialLeft + (moveX - startX);
                        const newTop = initialTop + (moveY - startY);

                        widget.css({
                            'left': newLeft + 'px',
                            'top': newTop + 'px'
                        });

                        // Dynamically increase container height as cards go down
                        const cardBottom = newTop + widget.outerHeight();
                        const container = $('#dashboard-widgets');
                        if (cardBottom > container.height()) {
                            container.css('min-height', (cardBottom + 100) + 'px');
                            localStorage.setItem('dashboard_container_height', (cardBottom + 100) + 'px');
                        }
                    });

                    $(document).on('mouseup.widgetMove touchend.widgetMove', function () {
                        if (isMoving) {
                            isMoving = false;
                            $(document).off('.widgetMove');
                            saveDashboardConfig();
                        }
                    });
                });

                // Apple/Mac Style Controls Logic
                function initMacControls() {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    $('#dashboard-widgets .widget-item').each(function() {
                        let card = $(this).find('.card');
                        if (card.length === 0) {
                            card = $(this).find('.carousel').first();
                        }
                        if (card.length && card.find('.mac-title-bar').length === 0) {
                            const existingHeader = card.children('.card-header').first();
                            // Clear hardcoded mapping + dynamic selector fallback
                            const titles = {
                                'widget-ana-slider': 'Duyurular',
                                'widget-bekleyen-talepler': 'Bekleyen Talepler',
                                'widget-gec-kalanlar': 'Geç Kalanlar',
                                'widget-nobetciler': 'Nöbetçiler',
                                'widget-gunluk-muhurleme': 'Mühürleme',
                                'widget-gunluk-kesme-acma': 'Kesme Açma',
                                'widget-gunluk-endeks-okuma': 'Endeks Okuma',
                                'widget-gunluk-sayac-degisimi': 'Sayaç Değişimi',
                                'widget-gunluk-kacak': 'Kaçak Kontrolü',
                                'widget-izinliler': 'İzinliler',
                                'widget-yaklasan-gorevler': 'Yaklaşan Görevler'
                            };
                            let titleText = titles[$(this).attr('id')] || card.find('h1, h2, h3, h4, h5, h6, strong, .stat-label, p.fw-bold, .card-title').first().text().replace('drag_handle', '').trim();
                            if ($(this).attr('id') === 'widget-ana-slider' || card.hasClass('carousel')) {
                                titleText = 'Duyurular';
                            }
                            if (!titleText || titleText.length < 2) {
                                titleText = 'Bilgi Kartı';
                            }
                            // strip out boxicon names if extracted
                            if (titleText.startsWith('bx-')) {
                                titleText = titleText.replace(/^bx-[a-z0-9-]+/i, '').trim();
                            }
                            if (!existingHeader.length) {
                                card.find('h5, h6, strong').first().hide();
                            }

                            const controls = $(`
                                <div class="mac-title-bar d-flex justify-content-between align-items-center" style="background: #e2e8f0; padding: 6px 12px; border-bottom: 2px solid #cbd5e1; border-top-left-radius: 10px; border-top-right-radius: 10px; cursor: move; user-select: none; position: relative; z-index: 1001; height: 34px;">
                                    <div class="mac-controls d-flex align-items-center" style="gap: 6px;">
                                        <span class="mac-control mac-close" title="Kapat" style="width: 10px; height: 10px; border-radius: 50%; display: inline-block; background: #ff5f56; border: 1px solid #e0443e; cursor: pointer;"></span>
                                        <span class="mac-control mac-minimize" title="Küçült" style="width: 10px; height: 10px; border-radius: 50%; display: inline-block; background: #ffbd2e; border: 1px solid #dfa123; cursor: pointer;"></span>
                                        <span class="mac-control mac-maximize" title="Tam Ekran" style="width: 10px; height: 10px; border-radius: 50%; display: inline-block; background: #27c93f; border: 1px solid #1aab29; cursor: pointer;"></span>
                                    </div>
                                    <div class="mac-title-text fw-bold" style="font-size: 11.5px; text-align: center; flex: 1; margin: 0 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #1e293b;">
                                        ${titleText}
                                    </div>
                                    <div class="drag-handle-indicator text-muted d-flex align-items-center" style="font-size: 14px; opacity: 0.5; width: 32px; justify-content: flex-end;">
                                        <i class="bx bx-grid-horizontal" style="font-size: 20px;"></i>
                                    </div>
                                </div>
                            `);

                            controls.find('.mac-close').off('click mousedown touchstart pointerdown').on('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                const widget = $(this).closest('.widget-item');
                                const widgetId = widget.attr('id');
                                setWidgetVisibility(widgetId, false, { syncCheckbox: false });
                                $(`.widget-toggle[data-widget="${widgetId}"]`).prop('checked', false).trigger('change');
                                saveDashboardConfig();
                            });

                            controls.find('.mac-minimize').off('click mousedown touchstart pointerdown').on('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                const widget = $(this).closest('.widget-item');
                                widget.toggleClass('widget-collapsed');
                                $(this).toggleClass('is-collapsed', widget.hasClass('widget-collapsed'));
                                if (widget.hasClass('widget-collapsed')) {
                                    widget.css('height', 'auto');
                                }
                            });

                            controls.find('.mac-maximize').off('click mousedown touchstart pointerdown').on('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                const widget = $(this).closest('.widget-item');
                                if (widget.data('maximized')) {
                                    widget.css({
                                        'position': widget.data('old-pos') || 'relative',
                                        'left': widget.data('old-left') || '',
                                        'top': widget.data('old-top') || '',
                                        'width': widget.data('old-width') || '',
                                        'height': widget.data('old-height') || '',
                                        'z-index': widget.data('old-z') || ''
                                    });
                                    widget.data('maximized', false);
                                } else {
                                    widget.data('old-pos', widget.css('position'));
                                    widget.data('old-left', widget.css('left'));
                                    widget.data('old-top', widget.css('top'));
                                    widget.data('old-width', widget.css('width'));
                                    widget.data('old-height', widget.css('height'));
                                    widget.data('old-z', widget.css('z-index'));
                                    widget.css({
                                        'position': 'absolute',
                                        'left': '0px',
                                        'top': '0px',
                                        'width': '100%',
                                        'height': '100%',
                                        'z-index': 9999
                                    });
                                    widget.data('maximized', true);
                                }
                            });

                            controls.find('.mac-control').off('click mousedown touchstart pointerdown');
                            card.prepend(controls);
                            if (existingHeader.length) {
                                const insertedTitleBar = card.children('.mac-title-bar').first();
                                existingHeader.addClass('mac-title-bar').css({
                                    background: '#e2e8f0',
                                    borderBottom: '2px solid #cbd5e1',
                                    cursor: 'move',
                                    userSelect: 'none',
                                    position: 'relative',
                                    zIndex: 1001,
                                    minHeight: '34px',
                                    paddingTop: '6px',
                                    paddingBottom: '6px'
                                });
                                existingHeader.find('h5, h6, strong, .card-title').first().show();
                                insertedTitleBar.find('.mac-controls').find('.mac-control').off('click mousedown touchstart pointerdown');
                                insertedTitleBar.find('.mac-controls').prependTo(existingHeader);
                                insertedTitleBar.find('.drag-handle-indicator').appendTo(existingHeader);
                                insertedTitleBar.remove();
                            }
                        }
                    });
                }

                // Initial Restore of container height
                const containerHeight = localStorage.getItem('dashboard_container_height');
                if (containerHeight) {
                    $('#dashboard-widgets').css('min-height', containerHeight);
                }

                $(document).off('click.dashboardMac', '.mac-close').on('click.dashboardMac', '.mac-close', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widget = $(this).closest('.widget-item');
                    const widgetId = widget.attr('id');
                    
                    setWidgetVisibility(widgetId, false, { syncCheckbox: false });
                    $(`.widget-toggle[data-widget="${widgetId}"]`).prop('checked', false).trigger('change');
                    saveDashboardConfig();
                });

                $(document).off('click.dashboardMac', '.mac-minimize').on('click.dashboardMac', '.mac-minimize', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widget = $(this).closest('.widget-item');
                    widget.toggleClass('widget-collapsed');
                    $(this).toggleClass('is-collapsed', widget.hasClass('widget-collapsed'));
                    if (widget.hasClass('widget-collapsed')) {
                        widget.css('height', 'auto');
                    }
                });

                $(document).off('click.dashboardMac', '.mac-maximize').on('click.dashboardMac', '.mac-maximize', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const widget = $(this).closest('.widget-item');
                    maxZIndex++;
                    
                    if (widget.attr('data-maximized') === 'true') {
                        widget.css({
                            width: '',
                            height: '',
                            flex: '',
                            maxWidth: '',
                            position: '',
                            left: '',
                            top: '',
                            zIndex: maxZIndex
                        });
                        widget.removeAttr('data-maximized');
                    } else {
                        widget.css({
                            width: '100%',
                            height: '100%',
                            flex: 'none',
                            maxWidth: 'none',
                            position: 'absolute',
                            left: '0px',
                            top: '0px',
                            zIndex: maxZIndex
                        });
                        widget.attr('data-maximized', 'true');
                    }
                    saveDashboardConfig();
                });

                // Card Resize Logic (Width)
                $(document).on('click', '.btn-resize-width', function (e) {
                    e.preventDefault();
                    const newWidth = $(this).data('width');
                    const widget = $(this).closest('.widget-item');

                    widget.removeAttr('data-resized');
                    widget.css('width', '');

                    // Remove existing col- classes
                    const classes = widget.attr('class').split(' ');
                    const newClasses = classes.filter(c => !c.startsWith('col-'));
                    newClasses.push(newWidth);

                    widget.attr('class', newClasses.join(' '));
                    saveDashboardConfig();

                    // Trigger window resize to let charts adjust
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 100);
                });

                // Card Resize Logic (Height)
                $(document).on('click', '.btn-resize-height', function (e) {
                    e.preventDefault();
                    const newHeight = $(this).data('height');
                    const widget = $(this).closest('.widget-item');
                    
                    widget.removeAttr('data-resized');
                    widget.css('height', '');

                    const cardBody = widget.find('.card-body');
                    cardBody.css('height', newHeight);
                    saveDashboardConfig();

                    // Trigger window resize to let charts adjust
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 100);
                });

                $(document).on('mousedown', '.mac-control', function(e) {
                    e.stopPropagation();
                });

                function appendResizeHandles() {
                    return;
                }

                let nativeResizeObserver = null;
                let nativeResizeSaveTimer = null;

                function scheduleNativeResizeSave(widget) {
                    widget.attr('data-resized', 'true');
                    clearTimeout(nativeResizeSaveTimer);
                    nativeResizeSaveTimer = setTimeout(function () {
                        saveDashboardConfig();
                        window.dispatchEvent(new Event('resize'));
                    }, 150);
                }

                function initResizableWidgets() {
                    if (typeof ResizeObserver !== 'undefined' && nativeResizeObserver === null) {
                        nativeResizeObserver = new ResizeObserver(function (entries) {
                            entries.forEach(function (entry) {
                                if (!$('#switch-free-layout').is(':checked')) return;
                                const widget = $(entry.target);
                                if (!widget.hasClass('resizable-widget')) return;
                                scheduleNativeResizeSave(widget);
                            });
                        });
                    }

                    $("#dashboard-widgets .widget-item").each(function () {
                        const id = $(this).attr('id');
                        if (!id || id === 'widget-row-break') return;

                        const widget = $(this);
                        widget.addClass('resizable-widget');
                        ensureAbsoluteWidgetPosition(widget);
                        widget.css({
                            flex: 'none',
                            maxWidth: 'none'
                        });

                        let card = widget.find('.card');
                        if (!card.length) {
                            card = widget.find('.carousel').first();
                        }

                        if (card.length) {
                            card.css({
                                height: '100%',
                                minHeight: '120px'
                            });
                        }

                        if (nativeResizeObserver) {
                            try { nativeResizeObserver.observe(widget[0]); } catch (e) {}
                        }

                        if (widget.find('.dashboard-resize-grip').length === 0) {
                            widget.append('<div class="dashboard-resize-grip" title="Boyutlandır"></div>');
                        }

                        if (widget.find('.custom-resize-handle').length === 0) {
                            widget.append('<div class="custom-resize-handle handle-se" title="Boyutlandır"></div>');
                        }
                    });

                    if (typeof initMacControls === 'function') initMacControls();
                }

                function ensureAbsoluteWidgetPosition(widget) {
                    if (widget.css('position') !== 'absolute') {
                        const offset = widget.position();
                        widget.css({
                            position: 'absolute',
                            left: offset.left + 'px',
                            top: offset.top + 'px',
                            flex: 'none',
                            maxWidth: 'none'
                        });
                    }
                }

                // Free-layout resize grip
                $(document).off('mousedown.dashboardResizeGrip', '.dashboard-resize-grip').on('mousedown.dashboardResizeGrip', '.dashboard-resize-grip', function (e) {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    e.preventDefault();
                    e.stopPropagation();

                    const widget = $(this).closest('.widget-item');
                    ensureAbsoluteWidgetPosition(widget);

                    maxZIndex++;
                    widget.addClass('dashboard-resizing').css({
                        zIndex: maxZIndex,
                        width: widget.outerWidth() + 'px',
                        height: widget.outerHeight() + 'px',
                        flex: 'none',
                        maxWidth: 'none'
                    });

                    const startX = e.clientX;
                    const startY = e.clientY;
                    const startWidth = widget.outerWidth();
                    const startHeight = widget.outerHeight();
                    document.body.style.userSelect = 'none';

                    $(document)
                        .off('.dashboardResizeGripMove')
                        .on('mousemove.dashboardResizeGripMove', function (moveEvent) {
                            const newWidth = Math.max(180, startWidth + (moveEvent.clientX - startX));
                            const newHeight = Math.max(120, startHeight + (moveEvent.clientY - startY));

                            widget.css({
                                width: newWidth + 'px',
                                height: newHeight + 'px',
                                flex: 'none',
                                maxWidth: 'none'
                            });
                            widget.attr('data-resized', 'true');
                            widget.find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                                height: '100%',
                                minHeight: '0',
                                maxHeight: 'none'
                            });
                        })
                        .on('mouseup.dashboardResizeGripMove', function () {
                            $(document).off('.dashboardResizeGripMove');
                            document.body.style.userSelect = '';
                            widget.removeClass('dashboard-resizing');
                            saveDashboardConfig();
                            setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 100);
                        });
                });

                // Free-layout resize grip
                $(document).off('mousedown.dashboardResize', '.custom-resize-handle').on('mousedown.dashboardResize', '.custom-resize-handle', function (e) {
                    if (!$('#switch-free-layout').is(':checked')) return;
                    e.preventDefault();
                    e.stopPropagation();
                    let isResizing = true;
                    const widget = $(this).closest('.widget-item');
                    maxZIndex++;
                    ensureAbsoluteWidgetPosition(widget);
                    widget.css({
                        'z-index': maxZIndex,
                        'width': widget.outerWidth() + 'px',
                        'height': widget.outerHeight() + 'px',
                        'flex': 'none',
                        'max-width': 'none'
                    });
                    
                    let startX = e.clientX;
                    let startY = e.clientY;
                    let startWidth = widget.outerWidth();
                    let startHeight = widget.outerHeight();

                    document.body.style.userSelect = 'none';

                    $(document).on('mousemove.widgetResize', function (e) {
                        if (!isResizing) return;
                        let clientX = e.clientX;
                        let clientY = e.clientY;
                        const newWidth = startWidth + (clientX - startX);
                        const newHeight = startHeight + (clientY - startY);

                        if (newWidth >= 120) {
                            widget.css({
                                'width': newWidth + 'px',
                                'flex': 'none',
                                'max-width': 'none'
                            });
                            widget.attr('data-resized', 'true');
                        }
                        if (newHeight >= 100) {
                            widget.css({
                                'height': newHeight + 'px'
                            });
                            widget.find('.card, .carousel, .carousel-inner, .card-body, .tab-content').css({
                                'height': '100%',
                                'min-height': '0',
                                'max-height': 'none'
                            });
                        }
                    });

                    $(document).on('mouseup.widgetResize', function () {
                        if (isResizing) {
                            isResizing = false;
                            $(document).off('.widgetResize');
                            document.body.style.userSelect = '';
                            saveDashboardConfig();
                            setTimeout(() => { window.dispatchEvent(new Event('resize')); }, 100);
                        }
                    });
                });

                const savedFreeLayout = localStorage.getItem('switch_free_layout');
                if (savedFreeLayout === 'true' || savedFreeLayout === null) {
                    $('#switch-free-layout').prop('checked', true);
                    if (savedFreeLayout === null) {
                        localStorage.setItem('switch_free_layout', 'true');
                    }
                    destroyGridSortable();
                    $('#dashboard-widgets').addClass('free-layout-active');
                    applyWidgetSettings();
                    initResizableWidgets();
                }

                // Reset Dashboard Logic
                $('#btn-reset-dashboard').on('click', function () {
                    Swal.fire({
                        title: 'Emin misiniz?',
                        text: "Tüm kart yerleşimleri ve genişlikleri varsayılan ayarlara dönecektir.",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Evet, Sıfırla',
                        cancelButtonText: 'İptal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.cookie = "dashboard_order=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
                            document.cookie = "dashboard_settings=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC;";
                            localStorage.removeItem('dashboard_widget_visibility');
                            localStorage.removeItem('dashboard_widget_settings');
                            localStorage.removeItem('dashboard_container_height');
                            location.reload();
                        }
                    });
                });
                // Operasyonel İstatistikler Local Toggle Logic
                $(document).on('click', '.stats-local-btn', function () {
                    const mode = $(this).data('mode');
                    const cardBody = $(this).closest('.card-body');
                    const statValue = cardBody.find('.stat-value');

                    // Update local buttons state
                    cardBody.find('.stats-local-btn').removeClass('active');
                    $(this).addClass('active');

                    // Update data
                    const newValue = parseInt(statValue.data(mode)) || 0;
                    const label = statValue.data('label-' + mode);
                    const subtext = statValue.data('sub-' + mode);

                    cardBody.find('.stat-label').text(label);
                    cardBody.find('.stat-subtext').text(subtext);

                    const oldValue = parseInt(statValue.text().replace(/[^0-9]/g, '')) || 0;
                    animateValue(statValue[0], oldValue, newValue, 800);
                });

                function formatDashboardTimestamp(timestamp) {
                    if (!timestamp) return '-';
                    const normalized = String(timestamp).replace(' ', 'T');
                    const dt = new Date(normalized);
                    if (Number.isNaN(dt.getTime())) return '-';
                    return dt.toLocaleString('tr-TR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }

                function updateOperationalWidget(widgetId, dailyValue, monthlyValue, lastUpdate, updatedByUser) {
                    const cardBody = document.querySelector(`#${widgetId} .card-body`);
                    if (!cardBody) return;

                    const statValueEl = cardBody.querySelector('.stat-value');
                    if (!statValueEl) return;

                    const safeDaily = Number.isFinite(Number(dailyValue)) ? Number(dailyValue) : 0;
                    const safeMonthly = Number.isFinite(Number(monthlyValue)) ? Number(monthlyValue) : 0;

                    statValueEl.dataset.daily = safeDaily;
                    statValueEl.dataset.monthly = safeMonthly;

                    const activeMode = cardBody.querySelector('.stats-local-btn.active')?.dataset.mode === 'monthly' ? 'monthly' : 'daily';
                    const nextValue = activeMode === 'monthly' ? safeMonthly : safeDaily;
                    const currentValue = parseInt((statValueEl.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;

                    const labelEl = cardBody.querySelector('.stat-label');
                    const subtextEl = cardBody.querySelector('.stat-subtext');
                    if (labelEl) {
                        labelEl.textContent = activeMode === 'monthly'
                            ? (statValueEl.dataset.labelMonthly || labelEl.textContent)
                            : (statValueEl.dataset.labelDaily || labelEl.textContent);
                    }
                    if (subtextEl) {
                        subtextEl.textContent = activeMode === 'monthly'
                            ? (statValueEl.dataset.subMonthly || subtextEl.textContent)
                            : (statValueEl.dataset.subDaily || subtextEl.textContent);
                    }

                    animateValue(statValueEl, currentValue, nextValue, 700);

                    const updateEl = cardBody.querySelector('.last-update-value');
                    if (updateEl) {
                        updateEl.textContent = formatDashboardTimestamp(lastUpdate);
                    }

                    const userEl = cardBody.querySelector('.last-update-user-value');
                    if (userEl) {
                        userEl.textContent = updatedByUser || '-';
                    }
                }

                function initDashboard(force = false) {
                    const $operationalCards = $('.widget-item .stat-card');
                    const lazyWidgets = document.querySelectorAll('[data-lazy-load="true"]');
                    
                    // 1. Client-Side Cache (Instant Feel)
                    const cacheKey = 'dashboard_cache_null';
                    const cachedData = localStorage.getItem(cacheKey);
                    
                    if (cachedData && !force) {
                        try {
                            const data = JSON.parse(cachedData);
                            if (data.stats) renderOperationalStats(data.stats);
                            if (data.results) {
                                Object.keys(data.results).forEach(id => {
                                    const $el = $('#' + id);
                                    if ($el.length && $el.hasClass('lazy-widget')) $el.replaceWith(data.results[id]);
                                });
                                if (typeof initResizableWidgets === 'function') initResizableWidgets();
                            }
                        } catch(e) { console.error("Cache render error", e); }
                    }

                    // 2. Loading State
                    $operationalCards.addClass('is-loading');
                    $operationalCards.each(function() {
                        if (!$(this).find('.card-loading-overlay').length) {
                            $(this).append('<div class="card-loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
                        }
                    });

                    const widgetIds = [];
                    const widths = [];
                    const widgetVisibility = JSON.parse(localStorage.getItem('dashboard_widget_visibility') || '{}');

                    lazyWidgets.forEach(widget => {
                        const isVisible = widgetVisibility[widget.id] !== false;
                        if (!isVisible) return; // Kapalı olan kartların verilerini getirmesin

                        const widthStr = $(widget).attr('class') || '';
                        const width = widthStr.split(' ').find(c => c.startsWith('col-')) || 'col-md-6';
                        widgetIds.push(widget.id);
                        widths.push(width);
                    });

                    // 3. Single Combined Request
                    return $.ajax({
                        url: 'views/home/api.php',
                        type: 'POST',
                        data: { 
                            action: 'batch-load-all',
                            widgets: widgetIds,
                            widths: widths,
                            force: force
                        }
                    }).done(function (response) {
                        try {
                            const res = typeof response === 'object' ? response : JSON.parse(response);
                            if (res.status === 'success') {
                                // Save to localStorage
                                localStorage.setItem(cacheKey, JSON.stringify({
                                    stats: res.stats,
                                    results: res.results,
                                    time: Date.now()
                                }));

                                // Render Stats
                                if (res.stats) renderOperationalStats(res.stats);

                                // Render Widgets
                                if (res.results) {
                                    Object.keys(res.results).forEach(widgetId => {
                                        const $el = $('#' + widgetId);
                                        if ($el.length) $el.replaceWith(res.results[widgetId]);
                                    });
                                }

                                setTimeout(() => {
                                    window.dispatchEvent(new Event('resize'));
                                    if (window.feather) feather.replace();
                                    if (typeof applyWidgetSettings === 'function') applyWidgetSettings();
                                    if (typeof initResizableWidgets === 'function') initResizableWidgets();
                                }, 150);
                            }
                        } catch (err) { console.error('Dashboard init error:', err); }
                    }).always(function() {
                        $operationalCards.removeClass('is-loading');
                    });
                }

                function renderOperationalStats(stats) {
                    const daily = stats.daily || {};
                    const monthly = stats.monthly || {};
                    const lastUpdate = stats.last_update || {};

                    updateOperationalWidget('widget-gunluk-muhurleme', daily.muhurleme, monthly.muhurleme, lastUpdate.isler, lastUpdate.isler_user);
                    updateOperationalWidget('widget-gunluk-kesme-acma', daily.kesme_acma, monthly.kesme_acma, lastUpdate.isler, lastUpdate.isler_user);
                    updateOperationalWidget('widget-gunluk-endeks-okuma', daily.endeks_okuma, monthly.endeks_okuma, lastUpdate.endeks, lastUpdate.endeks_user);
                    updateOperationalWidget('widget-gunluk-sayac-degisimi', daily.sayac_degisimi, monthly.sayac_degisimi, lastUpdate.sayac, lastUpdate.sayac_user);
                    updateOperationalWidget('widget-kacak-sayisi', daily.kacak, monthly.kacak, null, '-');
                }

                function refreshOperationalStats() { return initDashboard(true); }

                initDashboard();

                // Online API Sync Logic
                $(document).on('click', '.btn-api-sync', function (e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const $card = $btn.closest('.card');
                    const $icon = $btn.find('i');
                    const action = $btn.data('action');
                    const today = 'null';
                    const firmaKodu = 'null';

                    if ($btn.hasClass('syncing')) return;

                    $btn.addClass('syncing');
                    $icon.addClass('bx-spin text-primary');

                    // Kartı loading moduna al
                    if ($card.length) {
                        $card.addClass('is-loading');
                        if (!$card.find('.card-loading-overlay').length) {
                            $card.append('<div class="card-loading-overlay"><div class="spinner-border text-primary" role="status"></div></div>');
                        }
                    }

                    $.ajax({
                        url: 'views/puantaj/api.php',
                        type: 'POST',
                        data: {
                            action: action,
                            active_tab: $(this).data(
                                'active-tab') || '',
                            baslangic_tarihi: today,
                            bitis_tarihi: today,
                            ilk_firma: firmaKodu,
                            son_firma: firmaKodu
                        },
                        success: function (response) {
                            $btn.removeClass('syncing');
                            $icon.removeClass('bx-spin text-primary');

                            try {
                                const res = typeof response === 'object' ? response : JSON.parse(response);
                                if (res.status === 'success') {
                                    let msg = res.message || (res.yeni_kayit || 0) + ' adet yeni kayıt eklendi.';
                                    if (res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0) {
                                        msg += '<br><br><span class="text-danger fw-bold">⚠️ Aparat Zimmeti Eksik Personeller (' + Object.keys(res.eksik_zimmetler).length + '):</span><br><small>Şu personellerin zimmetinde aparat olmadığı için tüketim düşülemedi.</small>';
                                    }
                                    refreshOperationalStats().always(function () {
                                        $card.removeClass('is-loading');
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Sorgulama Başarılı',
                                            html: msg,
                                            timer: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0 ? 5000 : 2000,
                                            showConfirmButton: res.eksik_zimmetler && Object.keys(res.eksik_zimmetler).length > 0
                                        });
                                    });
                                } else {
                                    $card.removeClass('is-loading');
                                    Swal.fire('Hata', res.message || 'Sorgulama sırasında bir hata oluştu.', 'error');
                                }
                            } catch (err) {
                                $card.removeClass('is-loading');
                                console.error("API Response Error:", err);
                                console.log("Raw Response:", response);
                                Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                            }
                        },
                        error: function () {
                            $btn.removeClass('syncing');
                            $icon.removeClass('bx-spin text-primary');
                            $card.removeClass('is-loading');
                            Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                        }
                    });
                });

                // Tekil Nöbet Hatırlatma Bildirimi
                $(document).on('click', '.btn-send-nobet-reminder', function (e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const $icon = $btn.find('i');
                    const pId = $btn.data('id');
                    const pName = $btn.data('name');

                    Swal.fire({
                        title: 'Bildirim Gönderilsin mi?',
                        text: pName + ' isimli personele bugün nöbetçi olduğuna dair hatırlatma bildirimi gönderilecek.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Gönder',
                        cancelButtonText: 'İptal',
                        confirmButtonColor: '#556ee6',
                        cancelButtonColor: '#f46a6a',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $icon.removeClass('bx-bell').addClass('bx-loader-alt bx-spin');
                            $btn.addClass('disabled');

                            $.ajax({
                                url: 'views/nobet/api.php',
                                type: 'POST',
                                data: {
                                    action: 'send-today-nobet-reminder',
                                    personel_id: pId
                                },
                                success: function (response) {
                                    $icon.removeClass('bx-loader-alt bx-spin').addClass('bx-bell');
                                    $btn.removeClass('disabled');

                                    try {
                                        const res = typeof response === 'string' ? JSON.parse(response) : response;
                                        if (res.status === 'success' || res.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Başarılı',
                                                text: res.message,
                                                timer: 1500,
                                                showConfirmButton: false
                                            });
                                        } else {
                                            Swal.fire('Hata', res.message || 'Bildirim gönderilemedi.', 'error');
                                        }
                                    } catch (err) {
                                        Swal.fire('Hata', 'Sunucudan geçersiz yanıt alındı.', 'error');
                                    }
                                },
                                error: function () {
                                    $icon.removeClass('bx-loader-alt bx-spin').addClass('bx-bell');
                                    $btn.removeClass('disabled');
                                    Swal.fire('Hata', 'Bağlantı hatası oluştu.', 'error');
                                }
                            });
                        }
                    });
                });

                // ========== ENDEKS KARŞILAŞTIRMA KART LOGIC ==========
                (function () {
                    let endeksCompData = null;
                    let currentView = 'bolge';

                    function loadEndeksComparison() {
                        $.ajax({
                            url: 'views/home/api.php',
                            type: 'POST',
                            data: { action: 'get-endeks-comparison' },
                            success: function (response) {
                                try {
                                    const res = typeof response === 'object' ? response : JSON.parse(response);
                                    if (res.status === 'success' && res.data) {
                                        endeksCompData = res.data;
                                        $('#endeksCompGunNo').text(res.data.gun);
                                        $('#endeksCompGunBadge').removeClass('d-none');
                                        renderEndeksComparison();
                                    } else {
                                        showEndeksEmpty();
                                    }
                                } catch (e) {
                                    showEndeksEmpty();
                                }
                            },
                            error: function () {
                                showEndeksEmpty();
                            }
                        });
                    }

                    function showEndeksEmpty() {
                        $('#endeksCompLoading').hide();
                        $('#endeksCompContent').hide();
                        $('#endeksCompEmpty').show();
                    }

                    function renderEndeksComparison() {
                        if (!endeksCompData) return;
                        $('#endeksCompLoading').hide();

                        const bolgeData = endeksCompData.bolge || {};
                        const personelData = endeksCompData.personel || {};
                        const periods = endeksCompData.periods || [];

                        if (Object.keys(bolgeData).length === 0) {
                            showEndeksEmpty();
                            return;
                        }

                        renderBolgeView(bolgeData, periods);
                        renderPersonelView(personelData, periods);

                        $('#endeksCompContent').show();
                        if (currentView === 'bolge') {
                            $('#endeksCompBolge').show();
                            $('#endeksCompPersonel').hide();
                        } else {
                            $('#endeksCompBolge').hide();
                            $('#endeksCompPersonel').show();
                        }
                    }

                    function getTrendInfo(current, previous) {
                        if (!previous || previous === 0) return { text: '-', class: 'text-muted', icon: '', pct: 0 };
                        const diff = current - previous;
                        const pct = ((diff / previous) * 100).toFixed(1);
                        if (diff > 0) return { text: '+' + pct + '%', class: 'text-success', icon: 'bx-trending-up', pct: parseFloat(pct) };
                        if (diff < 0) return { text: pct + '%', class: 'text-danger', icon: 'bx-trending-down', pct: parseFloat(pct) };
                        return { text: '0%', class: 'text-muted', icon: 'bx-minus', pct: 0 };
                    }

                    function formatNumber(n) {
                        return new Intl.NumberFormat('tr-TR').format(n || 0);
                    }

                    // Satır arka plan rengini trend yüzdesine göre belirle
                    function getRowBgByTrend(pct) {
                        if (pct <= -20) return 'rgba(239, 68, 68, 0.08)';
                        if (pct <= -10) return 'rgba(245, 158, 11, 0.07)';
                        if (pct < 0) return 'rgba(251, 191, 36, 0.05)';
                        if (pct > 10) return 'rgba(16, 185, 129, 0.06)';
                        return 'transparent';
                    }

                    function getLeftBorderByTrend(pct) {
                        if (pct <= -20) return '3px solid #ef4444';
                        if (pct <= -10) return '3px solid #f59e0b';
                        if (pct < 0) return '3px solid #fbbf24';
                        if (pct > 10) return '3px solid #10b981';
                        return '3px solid transparent';
                    }

                    function getTrendBadge(trend) {
                        if (!trend.icon) return '<span class="text-muted" style="font-size: 12px;">-</span>';
                        const pct = trend.pct;
                        let bgColor, textColor, borderColor;
                        if (pct <= -20) {
                            bgColor = 'rgba(239, 68, 68, 0.12)'; textColor = '#dc2626'; borderColor = 'rgba(239, 68, 68, 0.3)';
                        } else if (pct <= -10) {
                            bgColor = 'rgba(245, 158, 11, 0.12)'; textColor = '#d97706'; borderColor = 'rgba(245, 158, 11, 0.3)';
                        } else if (pct < 0) {
                            bgColor = 'rgba(251, 191, 36, 0.1)'; textColor = '#b45309'; borderColor = 'rgba(251, 191, 36, 0.3)';
                        } else if (pct > 0) {
                            bgColor = 'rgba(16, 185, 129, 0.1)'; textColor = '#059669'; borderColor = 'rgba(16, 185, 129, 0.3)';
                        } else {
                            bgColor = 'rgba(148, 163, 184, 0.1)'; textColor = '#64748b'; borderColor = 'rgba(148, 163, 184, 0.3)';
                        }
                        return `<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; background: ${bgColor}; color: ${textColor}; border: 1px solid ${borderColor}; white-space: nowrap;"><i class="bx ${trend.icon}" style="font-size: 14px;"></i>${trend.text}</span>`;
                    }

                    function getPerfDot(pct) {
                        if (pct <= -20) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:6px;box-shadow:0 0 4px rgba(239,68,68,0.4);animation:pulse-dot 2s infinite;" title="Kritik Düşüş"></span>';
                        if (pct <= -10) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:6px;" title="Düşüş"></span>';
                        if (pct < 0) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#fbbf24;margin-right:6px;" title="Hafif Düşüş"></span>';
                        if (pct > 10) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;" title="İyi Performans"></span>';
                        if (pct > 0) return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6ee7b7;margin-right:6px;" title="Hafif Artış"></span>';
                        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#cbd5e1;margin-right:6px;" title="Değişim Yok"></span>';
                    }

                    function getMiniBar(value, maxValue, color) {
                        const pctWidth = maxValue > 0 ? Math.max(2, (value / maxValue) * 100) : 0;
                        return `<div style="width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 4px; overflow: hidden;">
                        <div style="height: 100%; width: ${pctWidth}%; background: ${color}; border-radius: 2px; transition: width 0.6s ease;"></div>
                    </div>`;
                    }

                    function buildSummaryCards(items, type) {
                        if (items.length === 0) return '';
                        const sorted = [...items].sort((a, b) => a.trendPct - b.trendPct);
                        const worst = sorted.slice(0, 3);
                        const best = sorted.slice(-3).reverse();

                        let html = '<div class="d-flex flex-wrap gap-2 px-3 py-3" style="border-bottom: 1px solid #f1f5f9; background: linear-gradient(135deg, #fafbff 0%, #f8fafc 100%);">';

                        if (worst.length > 0) {
                            html += '<div class="d-flex align-items-center gap-2 flex-wrap flex-grow-1">';
                            html += '<div class="d-flex align-items-center gap-1 me-2" style="white-space: nowrap;"><i class="bx bx-down-arrow-circle" style="color: #ef4444; font-size: 16px;"></i><span style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.05em;">Düşük Performans</span></div>';
                            worst.forEach(w => {
                                html += `<div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15); font-size: 12px;">`;
                                html += `<span style="font-weight: 600; color: #334155;">${w.name}</span>`;
                                html += `<span style="font-weight: 800; color: #dc2626; font-size: 13px;">${w.trendText}</span>`;
                                html += `</div>`;
                            });
                            html += '</div>';
                        }

                        if (best.length > 0) {
                            html += '<div class="d-flex align-items-center gap-2 flex-wrap ms-auto">';
                            html += '<div class="d-flex align-items-center gap-1 me-2" style="white-space: nowrap;"><i class="bx bx-up-arrow-circle" style="color: #10b981; font-size: 16px;"></i><span style="font-size: 11px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.05em;">Yüksek Performans</span></div>';
                            best.forEach(b => {
                                html += `<div style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 8px; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.15); font-size: 12px;">`;
                                html += `<span style="font-weight: 600; color: #334155;">${b.name}</span>`;
                                html += `<span style="font-weight: 800; color: #059669; font-size: 13px;">${b.trendText}</span>`;
                                html += `</div>`;
                            });
                            html += '</div>';
                        }

                        html += '</div>';
                        return html;
                    }

                    function renderBolgeView(bolgeData, periods) {
                        const periodLabels = periods.map(p => p.label);

                        let bolgeEntries = [];
                        let firmaToplam = {};
                        let maxLastVal = 0;
                        periodLabels.forEach(label => { firmaToplam[label] = 0; });

                        Object.keys(bolgeData).forEach(bolge => {
                            const bData = bolgeData[bolge];
                            const periodValues = [];
                            periodLabels.forEach(label => {
                                const val = bData.periods[label]?.toplam || 0;
                                periodValues.push(val);
                                firmaToplam[label] += val;
                            });

                            const lastVal = periodValues[periodValues.length - 1];
                            const prevVal = periodValues.length > 1 ? periodValues[periodValues.length - 2] : 0;
                            const trend = getTrendInfo(lastVal, prevVal);
                            if (lastVal > maxLastVal) maxLastVal = lastVal;

                            bolgeEntries.push({
                                bolge, bData, periodValues, lastVal, trend,
                                trendPct: trend.pct, trendText: trend.text, name: bolge
                            });
                        });

                        // En düşük performans üstte
                        bolgeEntries.sort((a, b) => a.trendPct - b.trendPct);

                        let html = '';
                        html += '<style>@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.5;transform:scale(1.3);}}</style>';

                        // Özet kartlar
                        html += buildSummaryCards(bolgeEntries, 'bolge');

                        html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: ${isCurrent ? '#c7d2fe' : '#ffffff'} !important; font-weight: 800; min-width: 130px; border-bottom: none; ${isCurrent ? 'background: rgba(99,102,241,0.15);' : ''}">${label}</th>`;
                        });
                        html += '<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 120px; border-bottom: none;">DEĞİŞİM</th>';
                        html += '<th style="width: 40px; border-bottom: none;"></th>';
                        html += '</tr></thead>';

                        html += '<tbody>';

                        bolgeEntries.forEach((entry, bIdx) => {
                            const { bolge, bData, periodValues, trend } = entry;
                            const hasPersonel = bData.personeller && Object.keys(bData.personeller).length > 0;
                            const rowBg = getRowBgByTrend(trend.pct);
                            const leftBorder = getLeftBorderByTrend(trend.pct);
                            const pSayisi = bData.periods[periodLabels[periodLabels.length - 1]]?.personel_sayisi || 0;

                            html += `<tr class="bolge-row cursor-pointer" data-bolge="${bIdx}" style="border-bottom: 1px solid #f1f5f9; transition: all 0.25s; background: ${rowBg}; border-left: ${leftBorder};" ${hasPersonel ? `onclick="toggleBolgeDetail(${bIdx})"` : ''} onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter='none'">`;
                            html += `<td style="padding: 12px 16px;"><div class="d-flex align-items-center gap-2">`;
                            if (hasPersonel) {
                                html += `<i class="bx bx-chevron-right bolge-chevron-${bIdx} text-muted" style="font-size: 16px; transition: transform 0.2s;"></i>`;
                            } else {
                                html += `<span style="width: 16px;"></span>`;
                            }
                            html += getPerfDot(trend.pct);
                            html += `<div><span class="fw-bold" style="color: #1e293b; font-size: 13px;">${bolge}</span>`;
                            if (pSayisi > 0) html += `<br><span class="text-muted" style="font-size: 10px;">${pSayisi} personel</span>`;
                            html += `</div></div></td>`;

                            periodValues.forEach((val, pIdx) => {
                                const isCurrent = periods[pIdx].is_current;
                                const cellBg = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                const barColor = isCurrent ? '#6366f1' : '#94a3b8';
                                html += `<td class="text-center" style="padding: 10px 12px; background: ${cellBg};">`;
                                html += `<span class="fw-bold" style="color: #1e293b; font-size: 15px;">${formatNumber(val)}</span>`;
                                html += `<div class="d-flex justify-content-center">${getMiniBar(val, maxLastVal, barColor)}</div></td>`;
                            });

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}</td>`;
                            html += `<td style="padding: 10px 8px;">`;
                            if (hasPersonel) html += `<i class="bx bx-expand-vertical text-muted" style="font-size: 12px; opacity: 0.5;"></i>`;
                            html += `</td></tr>`;

                            // Personel detay satırları
                            if (hasPersonel) {
                                const personeller = bData.personeller;
                                const pEntries = Object.keys(personeller).map(pKey => {
                                    const p = personeller[pKey];
                                    const pPeriods = [];
                                    periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                                    const pLastVal = pPeriods[pPeriods.length - 1];
                                    const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                                    return { p, pPeriods, pTrend: getTrendInfo(pLastVal, pPrevVal) };
                                });
                                pEntries.sort((a, b) => a.pTrend.pct - b.pTrend.pct);

                                pEntries.forEach(({ p, pPeriods, pTrend }) => {
                                    const detailBg = getRowBgByTrend(pTrend.pct);
                                    html += `<tr class="bolge-detail-${bIdx}" style="display: none; background: ${detailBg || '#fafbfc'}; border-bottom: 1px solid #f1f5f9; border-left: 3px solid #e2e8f0; animation: fadeInDown 0.2s;">`;
                                    html += `<td style="padding: 8px 16px 8px 56px;"><div class="d-flex align-items-center">${getPerfDot(pTrend.pct)}<div>`;
                                    html += `<span style="color: #475569; font-size: 12px; font-weight: 600;">${p.personel_adi}</span>`;
                                    html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                                    html += `</div></div></td>`;

                                    pPeriods.forEach((val, ppIdx) => {
                                        const isCurrent = periods[ppIdx].is_current;
                                        const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                        html += `<td class="text-center" style="padding: 8px 12px; background: ${bgColor}; font-size: 12px;"><span class="fw-semibold" style="color: #475569;">${formatNumber(val)}</span></td>`;
                                    });

                                    html += `<td class="text-center" style="padding: 8px 12px;">${getTrendBadge(pTrend)}</td><td></td></tr>`;
                                });
                            }
                        });

                        // Firma toplam footer
                        html += '<tr style="background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); border-top: 2px solid #a5b4fc; font-weight: 700;">';
                        html += '<td style="padding: 14px 16px; color: #3730a3; font-size: 13px; border-left: 3px solid #6366f1;"><i class="bx bx-buildings me-2"></i>GENEL TOPLAM</td>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            const bgColor = isCurrent ? 'rgba(99,102,241,0.12)' : 'transparent';
                            html += `<td class="text-center" style="padding: 14px 12px; color: #312e81; font-size: 16px; font-weight: 800; background: ${bgColor};">${formatNumber(firmaToplam[label])}</td>`;
                        });
                        const fLastVal = firmaToplam[periodLabels[periodLabels.length - 1]];
                        const fPrevVal = periodLabels.length > 1 ? firmaToplam[periodLabels[periodLabels.length - 2]] : 0;
                        const fTrend = getTrendInfo(fLastVal, fPrevVal);
                        html += `<td class="text-center" style="padding: 14px 12px;">${getTrendBadge(fTrend)}</td><td></td></tr>`;

                        html += '</tbody></table></div>';

                        // Alt açıklama + lejand
                        html += '<div class="px-3 py-2 d-flex align-items-center gap-3" style="border-top: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">';
                        html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Her ayın 1\'i ile ' + endeksCompData.gun + '\'ı arası abone okuma sayıları karşılaştırılmaktadır.</span>';
                        html += '<div class="ms-auto d-flex align-items-center gap-3">';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>≤-20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>-10~20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>>+10%</div>';
                        html += '<a href="index.php?p=puantaj/raporlar&tab=karsilastirma" class="btn btn-sm btn-primary" style="font-size: 11px; border-radius: 6px; padding: 4px 14px; font-weight: 600;"><i class="bx bx-right-arrow-alt me-1"></i>Detaylı Rapor</a>';
                        html += '</div></div>';

                        $('#endeksCompBolge').html(html);
                    }

                    function renderPersonelView(personelData, periods) {
                        const periodLabels = periods.map(p => p.label);

                        let personelEntries = [];
                        let maxPersonelVal = 0;

                        Object.keys(personelData).forEach(pKey => {
                            const p = personelData[pKey];
                            const pPeriods = [];
                            periodLabels.forEach(l => pPeriods.push(p.periods[l]?.toplam || 0));
                            const pLastVal = pPeriods[pPeriods.length - 1];
                            const pPrevVal = pPeriods.length > 1 ? pPeriods[pPeriods.length - 2] : 0;
                            const pTrend = getTrendInfo(pLastVal, pPrevVal);
                            if (pLastVal > maxPersonelVal) maxPersonelVal = pLastVal;

                            personelEntries.push({
                                p, pPeriods, pLastVal, trend: pTrend,
                                trendPct: pTrend.pct, trendText: pTrend.text, name: p.personel_adi
                            });
                        });

                        // En düşük performanslılar üstte
                        personelEntries.sort((a, b) => a.trendPct - b.trendPct);

                        let html = '';
                        html += buildSummaryCards(personelEntries, 'personel');

                        html += '<div class="table-responsive" style="max-height: 500px; overflow-y: auto;">';
                        html += '<table class="table table-nowrap align-middle mb-0" style="font-size: 13px;">';

                        // Dark header
                        html += '<thead style="position: sticky; top: 0; z-index: 5;">';
                        html += '<tr style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">';
                        html += '<th style="padding: 12px 16px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 180px; border-bottom: none;">PERSONEL</th>';
                        html += '<th style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 100px; border-bottom: none;">BÖLGE</th>';
                        periodLabels.forEach((label, idx) => {
                            const isCurrent = periods[idx].is_current;
                            html += `<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: ${isCurrent ? '#c7d2fe' : '#ffffff'} !important; font-weight: 800; min-width: 130px; border-bottom: none; ${isCurrent ? 'background: rgba(99,102,241,0.15);' : ''}">${label}</th>`;
                        });
                        html += '<th class="text-center" style="padding: 12px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #ffffff !important; font-weight: 800; min-width: 120px; border-bottom: none;">DEĞİŞİM</th>';
                        html += '</tr></thead>';

                        html += '<tbody>';

                        personelEntries.forEach(entry => {
                            const { p, pPeriods, trend } = entry;
                            const rowBg = getRowBgByTrend(trend.pct);
                            const leftBorder = getLeftBorderByTrend(trend.pct);

                            html += `<tr style="border-bottom: 1px solid #f1f5f9; transition: all 0.25s; background: ${rowBg}; border-left: ${leftBorder};" onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter='none'">`;
                            html += `<td style="padding: 12px 16px;"><div class="d-flex align-items-center">${getPerfDot(trend.pct)}<div>`;
                            html += `<span class="fw-bold" style="color: #1e293b; font-size: 12px;">${p.personel_adi}</span>`;
                            html += `<br><span class="text-muted" style="font-size: 10px;">${p.ekip_adi}</span>`;
                            html += `</div></div></td>`;
                            html += `<td style="padding: 12px 12px;"><span class="badge" style="font-size: 10px; font-weight: 600; background: rgba(99,102,241,0.08); color: #4338ca; border: 1px solid rgba(99,102,241,0.15); padding: 4px 8px; border-radius: 6px;">${p.bolge}</span></td>`;

                            pPeriods.forEach((val, ppIdx) => {
                                const isCurrent = periods[ppIdx].is_current;
                                const bgColor = isCurrent ? 'rgba(99,102,241,0.04)' : 'transparent';
                                const barColor = isCurrent ? '#6366f1' : '#94a3b8';
                                html += `<td class="text-center" style="padding: 10px 12px; background: ${bgColor};">`;
                                html += `<span class="fw-bold" style="color: #1e293b; font-size: 14px;">${formatNumber(val)}</span>`;
                                html += `<div class="d-flex justify-content-center">${getMiniBar(val, maxPersonelVal, barColor)}</div></td>`;
                            });

                            html += `<td class="text-center" style="padding: 10px 12px;">${getTrendBadge(trend)}</td></tr>`;
                        });

                        html += '</tbody></table></div>';

                        html += '<div class="px-3 py-2 d-flex align-items-center" style="border-top: 1px solid #e2e8f0; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">';
                        html += '<span style="font-size: 10px; color: #94a3b8;"><i class="bx bx-info-circle me-1"></i>Personeller performans değişimine göre sıralanmıştır (en düşük performans üstte).</span>';
                        html += '<div class="ms-auto d-flex align-items-center gap-3">';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;"></span>≤-20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;"></span>-10~20%</div>';
                        html += '<div class="d-flex align-items-center gap-2" style="font-size: 10px; color: #64748b;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>>+10%</div>';
                        html += '</div></div>';

                        $('#endeksCompPersonel').html(html);
                    }

                    // Bölge detay toggle
                    window.toggleBolgeDetail = function (bIdx) {
                        const rows = $(`.bolge-detail-${bIdx}`);
                        const chevron = $(`.bolge-chevron-${bIdx}`);
                        if (rows.first().is(':visible')) {
                            rows.slideUp(200);
                            chevron.css('transform', 'rotate(0deg)');
                        } else {
                            rows.slideDown(200);
                            chevron.css('transform', 'rotate(90deg)');
                        }
                    };

                    // View toggle
                    $('#endeksCompViewToggle button').on('click', function () {
                        $('#endeksCompViewToggle button').removeClass('active');
                        $(this).addClass('active');
                        currentView = $(this).data('view');
                        if (currentView === 'bolge') {
                            $('#endeksCompBolge').fadeIn(200);
                            $('#endeksCompPersonel').hide();
                        } else {
                            $('#endeksCompBolge').hide();
                            $('#endeksCompPersonel').fadeIn(200);
                        }
                    });

                    // Sayfa yüklendiğinde veri çek
                    loadEndeksComparison();
                })();
                // ========== /ENDEKS KARŞILAŞTIRMA KART LOGIC ==========

                // Combined into initDashboard()

            });
        
