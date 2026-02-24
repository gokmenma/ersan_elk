<?php
/**
 * Personel PWA - Puantaj / İş Takip Sayfası
 * Personelin yaptığı işlerin takibi
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
            <h1 class="text-xl font-bold">İş Takip</h1>
            <button onclick="toggleDarkMode()"
                class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
            </button>
        </div>
    </header>

    <!-- Stats Card -->
    <section class="px-4 -mt-10 relative z-20">
        <div class="card p-5">
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-2xl font-bold text-primary" id="toplam-is">-</p>
                    <p class="text-xs text-slate-500">Toplam İş</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-500" id="sonuclanan-is">-</p>
                    <p class="text-xs text-slate-500">Sonuçlanan</p>
                </div>
                <div>
                    <p class="text-2xl font-bold text-amber-500" id="acik-is">-</p>
                    <p class="text-xs text-slate-500">Açık</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="px-4 mt-6">
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <label class="text-xs text-slate-500 mb-1 block">Başlangıç</label>
                    <input type="date" id="filter-start-date" class="form-input text-sm"
                        value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" onchange="loadPuantajData()">
                </div>
                <div class="flex-1">
                    <label class="text-xs text-slate-500 mb-1 block">Bitiş</label>
                    <input type="date" id="filter-end-date" class="form-input text-sm"
                        value="<?php echo date('Y-m-d'); ?>" onchange="loadPuantajData()">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <select id="filter-type" class="form-select text-sm" onchange="handleWorkTypeChange()">
                    <option value="">Tüm İş Türleri</option>
                </select>
                <select id="filter-result" class="form-select text-sm" onchange="loadPuantajData()">
                    <option value="">Tüm İş Sonuçları</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Work List -->
    <section class="px-4 mt-6">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Yapılan İşler</h3>
        <div id="puantaj-list" class="flex flex-col gap-3">
            <!-- Skeleton loader -->
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
            <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">assignment_turned_in</span>
            <p class="text-slate-500">Bu dönemde kayıtlı iş bulunamadı.</p>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="puantaj-detail-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">checklist</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">İş Detayı</h3>
            </div>
            <button onclick="Modal.close('puantaj-detail-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="puantaj-detail-content"
            class="flex flex-col gap-4 overflow-y-auto max-h-[70vh] pb-6 disable-scrollbar">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
    let puantajData = [];
    let workTypes = [];

    document.addEventListener('DOMContentLoaded', function () {
        loadWorkTypes();
        loadWorkResults();
        loadPuantajData();
    });

    async function loadWorkTypes() {
        try {
            const response = await API.request('getPuantajWorkTypes');
            if (response.success && response.data) {
                const types = response.data;
                const select = document.getElementById('filter-type');
                types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('İş türleri yüklenemedi:', error);
        }
    }

    async function handleWorkTypeChange() {
        // İş türü değiştiğinde sonuçları da filtrele
        const workType = document.getElementById('filter-type').value;
        await loadWorkResults(workType);
        loadPuantajData();
    }

    async function loadWorkResults(workType = '') {
        try {
            const select = document.getElementById('filter-result');
            const currentValue = select.value;

            // Mevcut seçenekleri temizle (ilk seçenek hariç)
            select.innerHTML = '<option value="">Tüm İş Sonuçları</option>';

            const response = await API.request('getPuantajWorkResults', { work_type: workType });
            if (response.success && response.data) {
                const results = response.data;
                results.forEach(result => {
                    const option = document.createElement('option');
                    option.value = result;
                    option.textContent = result;
                    if (result === currentValue) option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('İş sonuçları yüklenemedi:', error);
        }
    }

    async function loadPuantajData() {
        const listContainer = document.getElementById('puantaj-list');
        const emptyState = document.getElementById('empty-state');

        // Show skeleton
        listContainer.innerHTML = `
            <div class="card p-4 animate-pulse">
                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div>
                <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            </div>
            <div class="card p-4 animate-pulse">
                <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-2"></div>
                <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            </div>
        `;
        emptyState.classList.add('hidden');

        const startDate = document.getElementById('filter-start-date').value;
        const endDate = document.getElementById('filter-end-date').value;
        const workType = document.getElementById('filter-type').value;
        const workResult = document.getElementById('filter-result').value;

        try {
            const response = await API.request('getPuantajData', {
                start_date: startDate,
                end_date: endDate,
                work_type: workType,
                work_result: workResult
            });

            if (response.success) {
                puantajData = response.data.items || [];
                const stats = response.data.stats || {};

                // Update stats
                document.getElementById('toplam-is').textContent = stats.toplam || 0;
                document.getElementById('sonuclanan-is').textContent = stats.sonuclanan || 0;
                document.getElementById('acik-is').textContent = stats.acik || 0;

                if (puantajData.length === 0) {
                    listContainer.innerHTML = '';
                    emptyState.classList.remove('hidden');
                } else {
                    renderPuantajList();
                }
            } else {
                listContainer.innerHTML = '';
                emptyState.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Puantaj verileri yüklenemedi:', error);
            listContainer.innerHTML = '';
            emptyState.classList.remove('hidden');
        }
    }

    function renderPuantajList() {
        const listContainer = document.getElementById('puantaj-list');
        const emptyState = document.getElementById('empty-state');

        if (puantajData.length === 0) {
            listContainer.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        // Günlere göre grupla
        const groups = puantajData.reduce((acc, item) => {
            const date = item.tarih;
            if (!acc[date]) acc[date] = {
                date: date,
                items: [],
                total: 0,
                sonuclanan: 0,
                acik: 0
            };
            acc[date].items.push(item);
            acc[date].total++;
            acc[date].sonuclanan += (int(item.sonuclanmis) || 0);
            acc[date].acik += (int(item.acik_olanlar) || 0);
            return acc;
        }, {});

        function int(val) { return parseInt(val) || 0; }

        // Tarihe göre azalan sırala
        const sortedGroups = Object.values(groups).sort((a, b) => new Date(b.date) - new Date(a.date));

        listContainer.innerHTML = sortedGroups.map(group => {
            const dateObj = new Date(group.date);
            const gunAdi = dateObj.toLocaleDateString('tr-TR', { weekday: 'long' });
            const dayNum = dateObj.getDate().toString().padStart(2, '0');

            return `
            <div class="card card-premium p-4 mb-1.5 hover:shadow-md transition-all active:scale-[0.99] cursor-pointer group relative overflow-hidden" 
                 onclick="showDailyDetail('${group.date}')">
                
                <!-- Dekatif Arkaplan Günü (Ortalanmış ve Net) -->
                <div class="absolute inset-0 pointer-events-none select-none z-0 opacity-[0.42] flex items-center justify-center">
                    <span class="text-[9rem] font-black leading-none tracking-tighter text-slate-100 dark:text-slate-800/50">
                        ${dayNum}
                    </span>
                </div>

                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[12px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">${gunAdi}</span>
                            <h4 class="font-bold text-slate-900 dark:text-white text-lg">${formatDate(group.date)}</h4>
                        </div>
                        <div class="flex flex-col items-end">
                            <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-900/50 px-2.5 py-1.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                 <span class="text-[13px] font-bold text-primary">${group.total} İŞ</span>
                            </div>
                            <div class="flex gap-3 mt-2.5">
                                <div class="flex items-center gap-1.5 text-green-600">
                                    <span class="material-symbols-outlined text-[20px] filled">check_circle</span>
                                    <span class="text-[15px] font-bold">${group.sonuclanan}</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-amber-600">
                                    <span class="material-symbols-outlined text-[20px] filled">schedule</span>
                                    <span class="text-[15px] font-bold">${group.acik}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3.5 flex items-center justify-between">
                        <div class="flex -space-x-2 overflow-hidden">
                            ${group.items.slice(0, 4).map(item => `
                                <div class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 flex items-center justify-center shadow-sm" title="${item.is_emri_tipi}">
                                    <span class="material-symbols-outlined text-sm text-primary">
                                        ${getIconForWorkType(item.is_emri_tipi)}
                                    </span>
                                </div>
                            `).join('')}
                            ${group.total > 4 ? `<div class="w-8 h-8 rounded-lg bg-slate-800 text-white text-[13px] font-bold flex items-center justify-center border border-white dark:border-slate-800 shadow-sm">+${group.total - 4}</div>` : ''}
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-400 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all">
                            <span class="material-symbols-outlined text-sm">chevron_right</span>
                        </div>
                    </div>
                </div>
            </div>
            `;
        }).join('');
    }

    function getIconForWorkType(type) {
        type = (type || '').toLowerCase();
        if (type.includes('açma')) return 'key';
        if (type.includes('kesme')) return 'content_cut';
        if (type.includes('sayaç')) return 'speed';
        if (type.includes('mühür')) return 'verified';
        return 'assignment';
    }

    function showDailyDetail(date) {
        const items = puantajData.filter(p => p.tarih === date);
        const container = document.getElementById('puantaj-detail-content');
        const modalHeader = document.querySelector('#puantaj-detail-modal h3');

        modalHeader.textContent = formatDate(date) + ' İşleri';

        container.innerHTML = `
            <div class="flex flex-col gap-3 pt-2">
                ${items.map(item => `
                    <div class="card p-4 bg-white dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700 hover:border-primary/30 transition-all cursor-pointer shadow-sm active:scale-[0.98]" 
                         onclick="showPuantajDetail('${item.id}')">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0
                                ${parseInt(item.acik_olanlar) > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-primary/10'}">
                                <span class="material-symbols-outlined text-xl ${parseInt(item.acik_olanlar) > 0 ? 'text-amber-600' : 'text-primary'}">
                                    ${getIconForWorkType(item.is_emri_tipi)}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h5 class="font-bold text-slate-900 dark:text-white text-[13px] truncate uppercase tracking-tight">${item.is_emri_tipi}</h5>
                                <p class="text-[12px] text-slate-500 mt-0.5 truncate">${item.is_emri_sonucu || '-'}</p>
                            </div>
                            <div class="flex flex-col items-end">
                                <div class="flex items-center gap-1.5 mb-1">
                                    ${parseInt(item.sonuclanmis) > 0 ? `<span class="text-[13px] font-bold text-green-600 bg-green-50 dark:bg-green-900/20 px-2 py-0.5 rounded-lg border border-green-100 dark:border-green-800/30">${item.sonuclanmis}</span>` : ''}
                                    ${parseInt(item.acik_olanlar) > 0 ? `<span class="text-[13px] font-bold text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 rounded-lg border border-amber-100 dark:border-amber-800/30">${item.acik_olanlar}</span>` : ''}
                                </div>
                                <span class="material-symbols-outlined text-slate-300 text-lg">chevron_right</span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        Modal.open('puantaj-detail-modal');
    }

    function showPuantajDetail(id) {
        // Event propagation'ı durdur (Eğer daily detail modal içinden tıklandıysa ana modalı etkilemesin)
        if (event) event.stopPropagation();

        // String karşılaştırması yap (sunucu string gönderebilir)
        const item = puantajData.find(p => String(p.id) === String(id));
        if (!item) return;

        // SweetAlert veya başka bir modal ile detayları gösterelim ki 
        // ana listeyi (günlük liste) kapatmadan detay görebilelim.
        Alert.show({
            title: item.is_emri_tipi,
            content: `
                <div class="text-left py-2">
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <span class="material-symbols-outlined text-primary">event</span>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Tarih</p>
                                <p class="font-semibold text-slate-900 dark:text-white">${formatDate(item.tarih)}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <span class="material-symbols-outlined text-primary">receipt_long</span>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Durum / Sonuç</p>
                                <p class="font-semibold text-slate-900 dark:text-white">${item.is_emri_sonucu || '-'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <span class="material-symbols-outlined text-primary">groups</span>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Ekip Kodu</p>
                                <p class="font-semibold text-slate-900 dark:text-white">${item.ekip_kodu || '-'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <span class="material-symbols-outlined text-primary">business</span>
                            <div>
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Firma</p>
                                <p class="font-semibold text-slate-900 dark:text-white">${item.firma || '-'}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-800/30 text-center">
                                <p class="text-[11px] text-green-600 dark:text-green-400 uppercase font-bold">Sonuçlanan</p>
                                <p class="text-2xl font-bold text-green-700 dark:text-green-300">${item.sonuclanmis || 0}</p>
                            </div>
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-100 dark:border-amber-800/30 text-center">
                                <p class="text-[11px] text-amber-600 dark:text-amber-400 uppercase font-bold">Açık / Bekleyen</p>
                                <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">${item.acik_olanlar || 0}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `,
            showConfirmButton: true,
            confirmButtonText: 'Kapat'
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