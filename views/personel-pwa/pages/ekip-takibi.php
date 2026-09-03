<?php
/**
 * Personel PWA - Ekip Takibi Sayfası
 * Ekip şefinin bölgesindeki tüm ekiplerin endeks okuma performansını takip etmesi
 */
use App\Helper\Helper;
use App\Helper\Date;

// En son endeks okuma tarihini bul (varsayılan akıllı tarih için)
try {
    $db = (new \App\Model\Model('endeks_okuma'))->getDb();
    $sonOkumaTarihi = $db->query("SELECT MAX(tarih) FROM endeks_okuma WHERE silinme_tarihi IS NULL")->fetchColumn();
} catch (\Throwable $e) {
    $sonOkumaTarihi = null;
}

// Varsayılan tarih: Eğer bu ay veri varsa bu ayın başından bugüne; yoksa en son okuma ayının başından son okuma tarihine
if (!empty($sonOkumaTarihi) && $sonOkumaTarihi < date('Y-m-01')) {
    $defaultEndDate = $sonOkumaTarihi;
    $defaultStartDate = date('Y-m-01', strtotime($sonOkumaTarihi));
} else {
    $defaultEndDate = date('Y-m-d');
    $defaultStartDate = date('Y-m-01');
}
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
                        <span class="material-symbols-outlined text-primary text-lg" id="summary-card-1-icon">bar_chart</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium truncate" id="summary-card-1-label">Dönem Toplamı</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900 dark:text-white" id="summary-daily">-</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 truncate" id="summary-card-1-sub">Okunan Abone</p>
                </div>
                <div
                    class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center justify-center gap-1.5 mb-1">
                        <span class="material-symbols-outlined text-primary text-lg" id="summary-card-2-icon">trending_up</span>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium truncate" id="summary-card-2-label">Günlük Ort.</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900 dark:text-white" id="summary-monthly">-</p>
                    <p class="text-[10px] text-slate-400 mt-0.5 truncate" id="summary-card-2-sub">Bölge Ortalaması</p>
                </div>
            </div>

            <!-- Ekip Sayısı & Ort -->
            <div class="grid grid-cols-3 gap-2 mt-3">
                <div class="text-center">
                    <p class="text-lg font-bold text-primary" id="summary-ekip-count">-</p>
                    <p class="text-[10px] text-slate-400" id="summary-sub-1-label">Toplam Ekip</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-emerald-600" id="summary-daily-avg">-</p>
                    <p class="text-[10px] text-slate-400" id="summary-sub-2-label">Aktif Ekip</p>
                </div>
                <div class="text-center">
                    <p class="text-lg font-bold text-amber-600" id="summary-monthly-avg">-</p>
                    <p class="text-[10px] text-slate-400" id="summary-sub-3-label">Ekip Başına Ort.</p>
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
                <span class="text-xs font-bold text-amber-900 dark:text-amber-100">35+ Gündür Okunmayan Mahalleler</span>
            </div>
            <div class="flex items-center gap-1">
                <span id="delayed-count-badge"
                    class="bg-amber-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">0</span>
                <span class="material-symbols-outlined text-amber-400 text-sm">chevron_right</span>
            </div>
        </div>
    </section>

    <!-- Date Filter Section -->
    <section class="px-4 mt-4">
        <div class="card p-3.5">
            <!-- Hızlı Filtre Hapları (Pills) -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 disable-scrollbar mb-2.5">
                <button type="button" onclick="setQuickDate('today')" id="pill-today"
                    class="date-pill px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary transition-colors whitespace-nowrap">
                    Bugün
                </button>
                <button type="button" onclick="setQuickDate('yesterday')" id="pill-yesterday"
                    class="date-pill px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary transition-colors whitespace-nowrap">
                    Dün
                </button>
                <button type="button" onclick="setQuickDate('last7')" id="pill-last7"
                    class="date-pill px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary transition-colors whitespace-nowrap">
                    Son 7 Gün
                </button>
                <button type="button" onclick="setQuickDate('thisMonth')" id="pill-thisMonth"
                    class="date-pill px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-primary/10 hover:text-primary transition-colors whitespace-nowrap">
                    Bu Ay
                </button>
                <?php if (!empty($sonOkumaTarihi)): ?>
                <button type="button" onclick="setQuickDate('lastData')" id="pill-lastData"
                    class="date-pill px-3 py-1 text-xs font-semibold rounded-full bg-primary/10 text-primary hover:bg-primary/20 transition-colors whitespace-nowrap flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">event_available</span>
                    Son Okuma (<?= date('d.m', strtotime($sonOkumaTarihi)) ?>)
                </button>
                <?php endif; ?>
            </div>

            <!-- Custom Date Inputs -->
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <label class="text-[11px] text-slate-500 font-medium mb-1 block">Başlangıç</label>
                    <input type="date" id="filter-start-date" class="form-input text-sm w-full"
                        value="<?php echo $defaultStartDate; ?>" onchange="handleDateChange('start')">
                </div>
                <div class="flex-1">
                    <label class="text-[11px] text-slate-500 font-medium mb-1 block">Bitiş</label>
                    <input type="date" id="filter-end-date" class="form-input text-sm w-full"
                        value="<?php echo $defaultEndDate; ?>" onchange="handleDateChange('end')">
                </div>
            </div>
        </div>
    </section>

    <!-- Chart Section -->
    <section class="px-4 mt-4">
        <div class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider" id="chart-section-title">Ekip Performansı</h3>
                <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg" id="chart-btn-group">
                    <button onclick="setChartType('total')" id="btn-chart-total"
                        class="chart-btn px-2.5 py-1 text-[10px] font-medium rounded-md bg-white dark:bg-slate-700 text-primary shadow-sm">Toplam</button>
                    <button onclick="setChartType('avg')" id="btn-chart-avg"
                        class="chart-btn px-2.5 py-1 text-[10px] font-medium rounded-md text-slate-600 dark:text-slate-400">Günlük Ort.</button>
                </div>
            </div>
            <div id="chart-container" class="w-full" style="height: 200px; position: relative;">
                <canvas id="ekip-chart"></canvas>
            </div>
        </div>
    </section>

    <!-- Ekip List -->
    <section class="px-4 mt-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Ekipler</h3>
            <span class="text-xs text-slate-400 font-medium" id="ekip-list-period-badge"></span>
        </div>
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
    let currentChartType = 'total'; // 'total' veya 'avg'
    const lastDataDate = '<?= !empty($sonOkumaTarihi) ? $sonOkumaTarihi : date('Y-m-d') ?>';

    document.addEventListener('DOMContentLoaded', function () {
        syncPillsWithInputs();
        loadEkipTakibiData();
    });

    function handleDateChange(changedInput) {
        const startEl = document.getElementById('filter-start-date');
        const endEl = document.getElementById('filter-end-date');

        if (startEl.value && endEl.value) {
            if (changedInput === 'start' && startEl.value > endEl.value) {
                endEl.value = startEl.value;
            } else if (changedInput === 'end' && endEl.value < startEl.value) {
                startEl.value = endEl.value;
            }
        }
        syncPillsWithInputs();
        loadEkipTakibiData();
    }

    function setQuickDate(type) {
        const startEl = document.getElementById('filter-start-date');
        const endEl = document.getElementById('filter-end-date');
        const now = new Date();

        function toYMD(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        if (type === 'today') {
            const todayStr = toYMD(now);
            startEl.value = todayStr;
            endEl.value = todayStr;
        } else if (type === 'yesterday') {
            const y = new Date();
            y.setDate(y.getDate() - 1);
            const yStr = toYMD(y);
            startEl.value = yStr;
            endEl.value = yStr;
        } else if (type === 'last7') {
            const past7 = new Date();
            past7.setDate(past7.getDate() - 6);
            startEl.value = toYMD(past7);
            endEl.value = toYMD(now);
        } else if (type === 'thisMonth') {
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            startEl.value = toYMD(firstDay);
            endEl.value = toYMD(now);
        } else if (type === 'lastData') {
            if (lastDataDate) {
                startEl.value = lastDataDate;
                endEl.value = lastDataDate;
            }
        }

        highlightPill(type);
        loadEkipTakibiData();
    }

    function highlightPill(type) {
        document.querySelectorAll('.date-pill').forEach(btn => {
            btn.classList.remove('bg-primary', 'text-white');
            btn.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300');
        });
        const activeBtn = document.getElementById('pill-' + type);
        if (activeBtn) {
            activeBtn.classList.add('bg-primary', 'text-white');
            activeBtn.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-300', 'bg-primary/10', 'text-primary');
        }
    }

    function syncPillsWithInputs() {
        const start = document.getElementById('filter-start-date').value;
        const end = document.getElementById('filter-end-date').value;
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const todayStr = `${year}-${month}-${day}`;

        if (start === todayStr && end === todayStr) {
            highlightPill('today');
        } else if (lastDataDate && start === lastDataDate && end === lastDataDate) {
            highlightPill('lastData');
        } else {
            document.querySelectorAll('.date-pill').forEach(btn => {
                btn.classList.remove('bg-primary', 'text-white');
            });
        }
    }

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
                document.getElementById('bolge-adi-header').textContent = response.message || 'Bölge bilgisi bulunamadı';
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
                            <div class="flex flex-col gap-0.5 min-w-0 flex-1 pr-2">
                                <span class="text-[9px] text-primary font-black uppercase tracking-widest truncate">${item.bolge || ''}</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-slate-400 text-sm">location_on</span>
                                    <span class="text-[13px] font-bold text-slate-700 dark:text-slate-200 truncate">${item.mahalle}</span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-[9px] text-slate-400 uppercase font-black tracking-tighter mb-0.5">Son Okuma</p>
                                <p class="text-[11px] font-bold text-slate-600 dark:text-slate-400">${item.son_okuma}</p>
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
        const isSingleDay = Boolean(ekipTakibiData.is_single_day);
        const startDate = ekipTakibiData.start_date || document.getElementById('filter-start-date').value;
        const endDate = ekipTakibiData.end_date || document.getElementById('filter-end-date').value;

        let totalOkunan = 0;
        let aktifEkipSayisi = 0;
        let maxCalisilanGun = 1;

        ekipler.forEach(e => {
            const donemToplam = parseInt(e.donem_toplam) || 0;
            const gunSayisi = parseInt(e.calisilan_gun) || 0;
            totalOkunan += donemToplam;
            if (donemToplam > 0) aktifEkipSayisi++;
            if (gunSayisi > maxCalisilanGun) maxCalisilanGun = gunSayisi;
        });

        const ekipCount = ekipler.length;

        // Kart 1 ve Kart 2 başlık ve değerleri
        const card1Label = document.getElementById('summary-card-1-label');
        const card1Sub = document.getElementById('summary-card-1-sub');
        const card1Val = document.getElementById('summary-daily');

        const card2Label = document.getElementById('summary-card-2-label');
        const card2Sub = document.getElementById('summary-card-2-sub');
        const card2Val = document.getElementById('summary-monthly');

        const periodBadge = document.getElementById('ekip-list-period-badge');

        if (isSingleDay) {
            const tStr = formatDate(startDate);
            card1Label.textContent = 'Seçilen Gün';
            card1Sub.textContent = tStr;
            card1Val.textContent = totalOkunan.toLocaleString('tr-TR');

            const avgPerActiveEkip = aktifEkipSayisi > 0 ? Math.round(totalOkunan / aktifEkipSayisi) : 0;
            card2Label.textContent = 'Ekip Başına Ort.';
            card2Sub.textContent = aktifEkipSayisi + ' Aktif Ekip';
            card2Val.textContent = avgPerActiveEkip.toLocaleString('tr-TR');

            if (periodBadge) periodBadge.textContent = tStr;
        } else {
            card1Label.textContent = 'Dönem Toplamı';
            card1Sub.textContent = maxCalisilanGun + ' Günlük Okuma';
            card1Val.textContent = totalOkunan.toLocaleString('tr-TR');

            const dailyAvg = maxCalisilanGun > 0 ? Math.round(totalOkunan / maxCalisilanGun) : 0;
            card2Label.textContent = 'Günlük Ortalama';
            card2Sub.textContent = 'Bölge Ortalaması';
            card2Val.textContent = dailyAvg.toLocaleString('tr-TR');

            if (periodBadge) periodBadge.textContent = `${formatDateShort(startDate)} - ${formatDateShort(endDate)}`;
        }

        // Alt 3 metrik
        document.getElementById('summary-ekip-count').textContent = ekipCount;
        document.getElementById('summary-sub-1-label').textContent = 'Toplam Ekip';

        document.getElementById('summary-daily-avg').textContent = aktifEkipSayisi;
        document.getElementById('summary-sub-2-label').textContent = 'Aktif Ekip';

        const ekipBasinaOrt = ekipCount > 0 ? Math.round(totalOkunan / ekipCount) : 0;
        document.getElementById('summary-monthly-avg').textContent = ekipBasinaOrt.toLocaleString('tr-TR');
        document.getElementById('summary-sub-3-label').textContent = isSingleDay ? 'Ekip Ortalaması' : 'Dönem Ekip Ort.';
    }

    function renderEkipList() {
        const listContainer = document.getElementById('ekip-list');
        const emptyState = document.getElementById('empty-state');
        const ekipler = ekipTakibiData.ekipler || [];
        const isSingleDay = Boolean(ekipTakibiData.is_single_day);

        if (ekipler.length === 0) {
            listContainer.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        // Dönem toplamına göre azalan sırala
        const sorted = [...ekipler].sort((a, b) => (parseInt(b.donem_toplam) || 0) - (parseInt(a.donem_toplam) || 0));
        const maxDonem = Math.max(...sorted.map(e => parseInt(e.donem_toplam) || 0), 1);

        listContainer.innerHTML = sorted.map((ekip, index) => {
            const donemToplam = parseInt(ekip.donem_toplam) || 0;
            const gunlukOrt = parseInt(ekip.gunluk_ort) || (ekip.calisilan_gun > 0 ? Math.round(donemToplam / ekip.calisilan_gun) : donemToplam);
            const calisilanGun = parseInt(ekip.calisilan_gun) || 0;
            const rankIcon = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
            const personelAdi = ekip.personel_adi || '—';
            const progressPercent = Math.round((donemToplam / maxDonem) * 100);

            // İstatistik kutuları (Tek gün vs Tarih aralığı)
            let statsHtml = '';
            if (isSingleDay) {
                statsHtml = `
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-2.5 border border-emerald-100 dark:border-emerald-800/30 text-center mb-3">
                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase">Okunan Abone</p>
                        <p class="text-xl font-black text-emerald-700 dark:text-emerald-300">${donemToplam.toLocaleString('tr-TR')}</p>
                    </div>
                `;
            } else {
                statsHtml = `
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-2.5 border border-blue-100 dark:border-blue-800/30 text-center">
                            <p class="text-[10px] text-blue-600 dark:text-blue-400 font-bold uppercase">Dönem Toplamı</p>
                            <p class="text-lg font-black text-blue-700 dark:text-blue-300">${donemToplam.toLocaleString('tr-TR')}</p>
                        </div>
                        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-2.5 border border-emerald-100 dark:border-emerald-800/30 text-center">
                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold uppercase">Günlük Ort. (${calisilanGun} Gün)</p>
                            <p class="text-lg font-black text-emerald-700 dark:text-emerald-300">${gunlukOrt.toLocaleString('tr-TR')}</p>
                        </div>
                    </div>
                `;
            }

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
                    ${statsHtml}

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
        const isSingleDay = Boolean(ekipTakibiData?.is_single_day);
        const ekip = ekipler.find(e => String(e.ekip_kodu_id) === String(ekipKoduId));
        if (!ekip) return;

        document.getElementById('ekip-detail-title').textContent = ekip.ekip_adi || 'Ekip Detayı';

        const donemToplam = parseInt(ekip.donem_toplam) || 0;
        const gunSayisi = parseInt(ekip.calisilan_gun) || 0;
        const gunlukOrt = parseInt(ekip.gunluk_ort) || (gunSayisi > 0 ? Math.round(donemToplam / gunSayisi) : donemToplam);
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
            ${isSingleDay ? `
                <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100 dark:border-emerald-800/30 text-center">
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 uppercase font-bold">Okunan Abone Sayısı</p>
                    <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">${donemToplam.toLocaleString('tr-TR')}</p>
                </div>
            ` : `
                <div class="grid grid-cols-3 gap-2">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800/30 text-center">
                        <p class="text-[10px] text-blue-600 dark:text-blue-400 uppercase font-bold">Dönem Toplamı</p>
                        <p class="text-lg font-bold text-blue-700 dark:text-blue-300">${donemToplam.toLocaleString('tr-TR')}</p>
                    </div>
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100 dark:border-emerald-800/30 text-center">
                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase font-bold">Çalışılan Gün</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">${gunSayisi} Gün</p>
                    </div>
                    <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800/30 text-center">
                        <p class="text-[10px] text-amber-600 dark:text-amber-400 uppercase font-bold">Günlük Ort.</p>
                        <p class="text-lg font-bold text-amber-700 dark:text-amber-300">${gunlukOrt.toLocaleString('tr-TR')}</p>
                    </div>
                </div>
            `}

            <!-- Günlük Detay -->
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Günlük Okuma Detayı</p>
                <div class="flex flex-col gap-1.5 max-h-[40vh] overflow-y-auto disable-scrollbar">
                    ${detaylar.length > 0 ? detaylar.map(d => {
                        const tarihObj = new Date(d.tarih);
                        const gunAdi = tarihObj.toLocaleDateString('tr-TR', { weekday: 'short' });
                        const tarihStr = tarihObj.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
                    }).join('') : '<p class="text-sm text-slate-400 text-center py-4">Bu dönemde okuma verisi bulunamadı.</p>'}
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
        if (activeBtn) {
            activeBtn.classList.add('bg-white', 'dark:bg-slate-700', 'text-primary', 'shadow-sm');
            activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');
        }

        updateChart();
    }

    function updateChart() {
        if (!ekipTakibiData || !ekipTakibiData.ekipler) return;

        const ekipler = ekipTakibiData.ekipler || [];
        const isSingleDay = Boolean(ekipTakibiData.is_single_day);
        const chartBtnGroup = document.getElementById('chart-btn-group');

        // Tek gün ise buton grubuna gerek yok, sadece toplam gösterilir
        if (isSingleDay) {
            if (chartBtnGroup) chartBtnGroup.classList.add('hidden');
            currentChartType = 'total';
        } else {
            if (chartBtnGroup) chartBtnGroup.classList.remove('hidden');
        }

        const sorted = [...ekipler].sort((a, b) => (parseInt(b.donem_toplam) || 0) - (parseInt(a.donem_toplam) || 0));

        const labels = sorted.map(e => (e.ekip_adi || '').replace('EKİP-', '').replace('EKIP-', ''));
        const data = sorted.map(e => {
            if (currentChartType === 'avg') {
                return parseInt(e.gunluk_ort) || 0;
            }
            return parseInt(e.donem_toplam) || 0;
        });

        // Generate colors
        const colors = sorted.map((_, i) => {
            const hue = (i * 35 + 200) % 360;
            return `hsl(${hue}, 70%, 55%)`;
        });

        if (ekipChart) {
            ekipChart.destroy();
        }

        const chartLabel = isSingleDay ? 'Okunan Abone' : (currentChartType === 'avg' ? 'Günlük Ort.' : 'Dönem Toplamı');

        const ctx = document.getElementById('ekip-chart').getContext('2d');
        ekipChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabel,
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
        const [y, m, d] = dateStr.split('-');
        if (!d) return dateStr;
        return `${d}.${m}.${y}`;
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return '-';
        const [y, m, d] = dateStr.split('-');
        if (!d) return dateStr;
        return `${d}.${m}`;
    }

    function toggleDarkMode() {
        App.toggleDarkMode();
        const icon = document.getElementById('theme-icon');
        icon.textContent = App.darkMode ? 'light_mode' : 'dark_mode';
    }
</script>