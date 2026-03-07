<?php
/**
 * Personel PWA - Ekip Takibi Sayfası
 * Ekip şefinin bölgesindeki tüm ekiplerin endeks okuma performansını takip etmesi
 */
use App\Helper\Helper;
use App\Helper\Date;
?>

<div class="flex flex-col min-h-screen pb-8">
    <!-- Header -->
    <header class="bg-gradient-primary text-white px-4 pt-6 pb-16 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 mb-10"></div>
        </div>

        <div class="relative z-10 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold">Ekip Takibi</h1>
                <p class="text-white/70 text-xs mt-0.5" id="bolge-adi-header">Yükleniyor...</p>
            </div>
            <button onclick="toggleDarkMode()"
                class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
            </button>
        </div>
    </header>

    <!-- Summary Cards -->
    <section class="px-4 -mt-10 relative z-20">
        <div class="card p-5">
            <div class="grid grid-cols-2 gap-4 text-center">
                <div
                    class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-center gap-1.5 mb-1">
                        <span class="material-symbols-outlined text-primary text-lg">today</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Bugün</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900 dark:text-white" id="summary-daily">-</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Okunan Abone</p>
                </div>
                <div
                    class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-center gap-1.5 mb-1">
                        <span class="material-symbols-outlined text-primary text-lg">date_range</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Bu Ay</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900 dark:text-white" id="summary-monthly">-</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Okunan Abone</p>
                </div>
            </div>

            <!-- Ekip Sayısı & Ort -->
            <div class="grid grid-cols-3 gap-2 mt-3">
                <div class="text-center">
                    <p class="text-lg font-bold text-primary" id="summary-ekip-count">-</p>
                    <p class="text-[10px] text-slate-400">Ekip Sayısı</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-emerald-600" id="summary-daily-avg">-</p>
                    <p class="text-[10px] text-slate-400">Günlük Ort.</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-amber-600" id="summary-monthly-avg">-</p>
                    <p class="text-[10px] text-slate-400">Aylık Ort.</p>
                </div>
            </div>

            <div class="mt-3 text-center border-t border-slate-100 dark:border-slate-700 pt-2">
                <p class="text-[10px] text-slate-400 font-medium">Son Güncelleme:
                    <?php echo Helper::getLastUpdateDate('endeks_okuma'); ?>
                </p>
            </div>
        </div>
    </section>
    <!-- Delayed Readings Alert (Slim) -->
    <section class="px-4 mt-4 hidden" id="delayed-readings-section" onclick="Modal.open('delayed-readings-modal')">
        <div
            class="bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800/20 rounded-xl p-3 flex items-center justify-between cursor-pointer active:scale-95 transition-transform">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">warning</span>
                <span class="text-xs font-bold text-amber-900 dark:text-amber-100">35+ Gündür Okunmayan
                    Mahalleler</span>
            </div>
            <div class="flex items-center gap-1">
                <span id="delayed-count-badge"
                    class="bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">0</span>
                <span class="material-symbols-outlined text-amber-400 text-sm">chevron_right</span>
            </div>
        </div>
    </section>

    <!-- Date Filter -->
    <section class="px-4 mt-4">
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <label class="text-xs text-slate-500 mb-1 block">Başlangıç</label>
                <input type="date" id="filter-start-date" class="form-input text-sm"
                    value="<?php echo date('Y-m-01'); ?>" onchange="loadEkipTakibiData()">
            </div>
            <div class="flex-1">
                <label class="text-xs text-slate-500 mb-1 block">Bitiş</label>
                <input type="date" id="filter-end-date" class="form-input text-sm" value="<?php echo date('Y-m-d'); ?>"
                    onchange="loadEkipTakibiData()">
            </div>
        </div>
    </section>

    <!-- Chart Section -->
    <section class="px-4 mt-4">
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Ekip Performansı</h3>
                <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg">
                    <button onclick="setChartType('daily')" id="btn-chart-daily"
                        class="chart-btn px-2.5 py-1 text-[10px] font-medium rounded-md bg-white dark:bg-slate-700 text-primary shadow-sm">Günlük</button>
                    <button onclick="setChartType('monthly')" id="btn-chart-monthly"
                        class="chart-btn px-2.5 py-1 text-[10px] font-medium rounded-md text-slate-600 dark:text-slate-400">Aylık</button>
                </div>
            </div>
            <div id="chart-container" class="w-full" style="height: 200px; position: relative;">
                <canvas id="ekip-chart"></canvas>
            </div>
        </div>
    </section>

    <!-- Ekip List -->
    <section class="px-4 mt-4">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Ekipler</h3>
        <div id="ekip-list" class="flex flex-col gap-3">
            <!-- Skeleton -->
            <div class="card p-4 animate-pulse">
                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div>
                <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            </div>
            <div class="card p-4 animate-pulse">
                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div>
                <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            </div>
        </div>
    </section>

    <!-- Empty State -->
    <div id="empty-state" class="px-4 mt-8 hidden">
        <div class="card p-8 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">groups_off</span>
            <p class="text-slate-500">Bu dönemde ekip verisi bulunamadı.</p>
        </div>
    </div>
</div>

<!-- Ekip Detail Modal -->
<div id="ekip-detail-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">groups</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="ekip-detail-title">Ekip Detayı</h3>
            </div>
            <button onclick="Modal.close('ekip-detail-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="ekip-detail-content" class="flex flex-col gap-4 overflow-y-auto max-h-[70vh] pb-6 disable-scrollbar">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Delayed Readings Modal -->
<div id="delayed-readings-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-amber-500 text-2xl">warning</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Geciken Okumalar</h3>
            </div>
            <button onclick="Modal.close('delayed-readings-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="delayed-readings-modal-list"
            class="flex flex-col gap-3 overflow-y-auto max-h-[70vh] pb-6 disable-scrollbar">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    let ekipTakibiData = null;
    let ekipChart = null;
    let currentChartType = 'daily';

    document.addEventListener('DOMContentLoaded', function () {
        loadEkipTakibiData();
    });

    async function loadEkipTakibiData() {
        const listContainer = document.getElementById('ekip-list');
        const emptyState = document.getElementById('empty-state');

        // Show skeleton
        listContainer.innerHTML = `
            <div class="card p-4 animate-pulse"><div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div><div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div></div>
            <div class="card p-4 animate-pulse"><div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div><div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div></div>
        `;
        emptyState.classList.add('hidden');

        const startDate = document.getElementById('filter-start-date').value;
        const endDate = document.getElementById('filter-end-date').value;

        try {
            // Load delayed readings first
            loadDelayedReadings();

            const response = await API.request('getEkipTakibiData', {
                start_date: startDate,
                end_date: endDate
            });

            if (response.success && response.data) {
                ekipTakibiData = response.data;

                // Bölge adını header'a yaz
                document.getElementById('bolge-adi-header').textContent = 'Bölge: ' + (ekipTakibiData.bolge || '—');

                // Summary kartları güncelle
                updateSummary();

                // Ekip listesini render et
                renderEkipList();

                // Chart'ı güncelle
                updateChart();
            } else {
                listContainer.innerHTML = '';
                emptyState.classList.remove('hidden');
                document.getElementById('bolge-adi-header').textContent = 'Bölge bilgisi bulunamadı';
            }
        } catch (error) {
            console.error('Ekip takibi veri yükleme hatası:', error);
            listContainer.innerHTML = '';
            emptyState.classList.remove('hidden');
        }
    }

    async function loadDelayedReadings() {
        const section = document.getElementById('delayed-readings-section');
        const badge = document.getElementById('delayed-count-badge');
        const listContainer = document.getElementById('delayed-readings-modal-list');

        try {
            const response = await API.request('getDelayedReadings');
            if (response.success && response.data && response.data.length > 0) {
                section.classList.remove('hidden');
                badge.textContent = response.data.length;

                listContainer.innerHTML = response.data.map(item => `
                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-100 dark:border-slate-700">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-900 dark:text-white">Defter: ${item.defter_kodu}</span>
                            <span class="text-[10px] bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2.5 py-1 rounded-full font-bold">${item.gun} Gün Gecikti</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-slate-400 text-sm">location_on</span>
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">${item.mahalle}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-slate-400 uppercase font-bold">Son Okuma</p>
                                <p class="text-[11px] text-slate-600 dark:text-slate-400">${item.son_okuma}</p>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                section.classList.add('hidden');
            }
        } catch (error) {
            console.error('Geciken okuma verisi yükleme hatası:', error);
            section.classList.add('hidden');
        }
    }

    function updateSummary() {
        if (!ekipTakibiData) return;

        const ekipler = ekipTakibiData.ekipler || [];
        let dailyTotal = 0;
        let monthlyTotal = 0;

        ekipler.forEach(e => {
            dailyTotal += parseInt(e.gunluk_toplam) || 0;
            monthlyTotal += parseInt(e.aylik_toplam) || 0;
        });

        const ekipCount = ekipler.length;
        const dailyAvg = ekipCount > 0 ? Math.round(dailyTotal / ekipCount) : 0;
        const monthlyAvg = ekipCount > 0 ? Math.round(monthlyTotal / ekipCount) : 0;

        document.getElementById('summary-daily').textContent = dailyTotal.toLocaleString('tr-TR');
        document.getElementById('summary-monthly').textContent = monthlyTotal.toLocaleString('tr-TR');
        document.getElementById('summary-ekip-count').textContent = ekipCount;
        document.getElementById('summary-daily-avg').textContent = dailyAvg.toLocaleString('tr-TR');
        document.getElementById('summary-monthly-avg').textContent = monthlyAvg.toLocaleString('tr-TR');
    }

    function renderEkipList() {
        const listContainer = document.getElementById('ekip-list');
        const emptyState = document.getElementById('empty-state');
        const ekipler = ekipTakibiData.ekipler || [];

        if (ekipler.length === 0) {
            listContainer.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        // Aylık toplama göre sırala (azalan)
        const sorted = [...ekipler].sort((a, b) => (parseInt(b.aylik_toplam) || 0) - (parseInt(a.aylik_toplam) || 0));

        listContainer.innerHTML = sorted.map((ekip, index) => {
            const gunluk = parseInt(ekip.gunluk_toplam) || 0;
            const aylik = parseInt(ekip.aylik_toplam) || 0;
            const rankIcon = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
            const personelAdi = ekip.personel_adi || '—';

            // Progress bar (aylık bazda max'a göre)
            const maxAylik = Math.max(...sorted.map(e => parseInt(e.aylik_toplam) || 0), 1);
            const progressPercent = Math.round((aylik / maxAylik) * 100);

            return `
            <div class="card card-premium p-4 hover:shadow-md transition-all active:scale-[0.99] cursor-pointer group relative overflow-hidden"
                 onclick="showEkipDetail('${ekip.ekip_kodu_id}')">

                <!-- Rank badge -->
                ${rankIcon ? `<div class="absolute top-2 right-3 text-xl">${rankIcon}</div>` : ''}

                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-primary text-xl">badge</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-slate-900 dark:text-white text-sm truncate">${ekip.ekip_adi || '—'}</h4>
                            <p class="text-[11px] text-slate-500 truncate">${personelAdi}</p>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-2.5 border border-emerald-100 dark:border-emerald-800/30 text-center">
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase">Bugün</p>
                            <p class="text-lg font-black text-emerald-700 dark:text-emerald-300">${gunluk.toLocaleString('tr-TR')}</p>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-2.5 border border-blue-100 dark:border-blue-800/30 text-center">
                            <p class="text-[10px] text-blue-600 dark:text-blue-400 font-bold uppercase">Bu Ay</p>
                            <p class="text-lg font-black text-blue-700 dark:text-blue-300">${aylik.toLocaleString('tr-TR')}</p>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-primary to-primary-dark rounded-full transition-all duration-700"
                                 style="width: ${progressPercent}%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400 font-medium">${progressPercent}%</span>
                        <span class="material-symbols-outlined text-slate-300 text-lg group-hover:text-primary transition-colors">chevron_right</span>
                    </div>
                </div>
            </div>
            `;
        }).join('');
    }

    function showEkipDetail(ekipKoduId) {
        const ekipler = ekipTakibiData?.ekipler || [];
        const ekip = ekipler.find(e => String(e.ekip_kodu_id) === String(ekipKoduId));
        if (!ekip) return;

        document.getElementById('ekip-detail-title').textContent = ekip.ekip_adi || 'Ekip Detayı';

        const gunluk = parseInt(ekip.gunluk_toplam) || 0;
        const aylik = parseInt(ekip.aylik_toplam) || 0;
        const gunSayisi = parseInt(ekip.calisilan_gun) || 0;
        const gunlukOrt = gunSayisi > 0 ? Math.round(aylik / gunSayisi) : 0;
        const detaylar = ekip.gunluk_detay || [];

        const container = document.getElementById('ekip-detail-content');
        container.innerHTML = `
            <!-- Personel & Ekip Bilgisi -->
            <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                <span class="material-symbols-outlined text-primary">person</span>
                <div>
                    <p class="text-[10px] text-slate-500 uppercase font-bold">Personel</p>
                    <p class="font-semibold text-slate-900 dark:text-white">${ekip.personel_adi || '—'}</p>
                </div>
            </div>

            <!-- Özet Kartlar -->
            <div class="grid grid-cols-3 gap-2">
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100 dark:border-emerald-800/30 text-center">
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase font-bold">Bugün</p>
                    <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300">${gunluk.toLocaleString('tr-TR')}</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800/30 text-center">
                    <p class="text-[10px] text-blue-600 dark:text-blue-400 uppercase font-bold">Aylık Toplam</p>
                    <p class="text-xl font-bold text-blue-700 dark:text-blue-300">${aylik.toLocaleString('tr-TR')}</p>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800/30 text-center">
                    <p class="text-[10px] text-amber-600 dark:text-amber-400 uppercase font-bold">Günlük Ort.</p>
                    <p class="text-xl font-bold text-amber-700 dark:text-amber-300">${gunlukOrt.toLocaleString('tr-TR')}</p>
                </div>
            </div>

            <!-- Günlük Detay -->
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Günlük Detay</p>
                <div class="flex flex-col gap-1.5 max-h-[40vh] overflow-y-auto disable-scrollbar">
                    ${detaylar.length > 0 ? detaylar.map(d => {
            const tarihObj = new Date(d.tarih);
            const gunAdi = tarihObj.toLocaleDateString('tr-TR', { weekday: 'short' });
            const tarihStr = tarihObj.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit' });
            const okunan = parseInt(d.toplam) || 0;
            return `
                            <div class="flex items-center justify-between p-2.5 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-slate-400 w-8 uppercase">${gunAdi}</span>
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">${tarihStr}</span>
                                </div>
                                <span class="text-sm font-bold text-primary">${okunan.toLocaleString('tr-TR')} abone</span>
                            </div>
                        `;
        }).join('') : '<p class="text-sm text-slate-400 text-center py-4">Bu dönemde veri bulunamadı.</p>'}
                </div>
            </div>
        `;

        Modal.open('ekip-detail-modal');
    }

    // ========== CHART ==========
    function setChartType(type) {
        currentChartType = type;

        // Update buttons
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            btn.classList.add('text-slate-600', 'dark:text-slate-400');
        });
        const activeBtn = document.getElementById('btn-chart-' + type);
        activeBtn.classList.add('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
        activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');

        updateChart();
    }

    function updateChart() {
        if (!ekipTakibiData || !ekipTakibiData.ekipler) return;

        const ekipler = ekipTakibiData.ekipler || [];
        const sorted = [...ekipler].sort((a, b) => (parseInt(b.aylik_toplam) || 0) - (parseInt(a.aylik_toplam) || 0));

        const labels = sorted.map(e => (e.ekip_adi || '').replace('EKİP-', '').replace('EKIP-', ''));
        const data = sorted.map(e => currentChartType === 'daily' ? (parseInt(e.gunluk_toplam) || 0) : (parseInt(e.aylik_toplam) || 0));

        // Generate colors
        const colors = sorted.map((_, i) => {
            const hue = (i * 35 + 200) % 360;
            return `hsl(${hue}, 70%, 55%)`;
        });

        if (ekipChart) {
            ekipChart.destroy();
        }

        const ctx = document.getElementById('ekip-chart').getContext('2d');
        ekipChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: currentChartType === 'daily' ? 'Bugün' : 'Bu Ay',
                    data: data,
                    backgroundColor: colors.map(c => c.replace('55%)', '55%, 0.7)')),
                    borderColor: colors,
                    borderWidth: 1,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => {
                                const idx = items[0].dataIndex;
                                return sorted[idx]?.ekip_adi || '';
                            },
                            label: (item) => ' ' + item.formattedValue + ' abone'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: 'bold' } }
                    },
                    y: {
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { font: { size: 10 } },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function toggleDarkMode() {
        App.toggleDarkMode();
        const icon = document.getElementById('theme-icon');
        icon.textContent = App.darkMode ? 'light_mode' : 'dark_mode';
    }
</script>