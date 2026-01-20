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
            <h1 class="text-xl font-bold">Puantaj / İş Takip</h1>
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
                        value="<?php echo date('Y-m-01'); ?>" onchange="loadPuantajData()">
                </div>
                <div class="flex-1">
                    <label class="text-xs text-slate-500 mb-1 block">Bitiş</label>
                    <input type="date" id="filter-end-date" class="form-input text-sm" 
                        value="<?php echo date('Y-m-d'); ?>" onchange="loadPuantajData()">
                </div>
            </div>
            <div>
                <select id="filter-type" class="form-select text-sm" onchange="loadPuantajData()">
                    <option value="">Tüm İş Türleri</option>
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

        <div id="puantaj-detail-content" class="flex flex-col gap-4">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
    let puantajData = [];
    let workTypes = [];

    document.addEventListener('DOMContentLoaded', function() {
        loadWorkTypes();
        loadPuantajData();
    });

    async function loadWorkTypes() {
        try {
            const response = await API.request('getPuantajWorkTypes');
            if (response.success && response.data) {
                workTypes = response.data;
                const select = document.getElementById('filter-type');
                workTypes.forEach(type => {
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

        try {
            const response = await API.request('getPuantajData', {
                start_date: startDate,
                end_date: endDate,
                work_type: workType
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
        listContainer.innerHTML = puantajData.map(item => `
            <div class="card p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="showPuantajDetail('${item.id}')">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0
                        ${item.acik_olanlar > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-green-100 dark:bg-green-900/30'}">
                        <span class="material-symbols-outlined text-xl
                            ${item.acik_olanlar > 0 ? 'text-amber-600' : 'text-green-600'}">
                            ${item.acik_olanlar > 0 ? 'pending_actions' : 'task_alt'}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="font-semibold text-slate-900 dark:text-white truncate">${item.is_emri_tipi || 'İş Türü'}</h4>
                            <span class="text-xs text-slate-500 whitespace-nowrap">${formatDate(item.tarih)}</span>
                        </div>
                        <p class="text-sm text-slate-500 mt-1 truncate">${item.is_emri_sonucu || '-'}</p>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="inline-flex items-center gap-1 text-xs ${item.sonuclanmis > 0 ? 'text-green-600' : 'text-slate-400'}">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                ${item.sonuclanmis || 0}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs ${item.acik_olanlar > 0 ? 'text-amber-600' : 'text-slate-400'}">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                ${item.acik_olanlar || 0}
                            </span>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-slate-400">chevron_right</span>
                </div>
            </div>
        `).join('');
    }

    function showPuantajDetail(id) {
        // String karşılaştırması yap (sunucu string gönderebilir)
        const item = puantajData.find(p => String(p.id) === String(id));
        if (!item) {
            console.error('Puantaj item bulunamadı:', id, 'Mevcut veriler:', puantajData.map(p => p.id));
            return;
        }

        const content = document.getElementById('puantaj-detail-content');
        content.innerHTML = `
            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center
                        ${item.acik_olanlar > 0 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-green-100 dark:bg-green-900/30'}">
                        <span class="material-symbols-outlined text-xl
                            ${item.acik_olanlar > 0 ? 'text-amber-600' : 'text-green-600'}">
                            ${item.acik_olanlar > 0 ? 'pending_actions' : 'task_alt'}
                        </span>
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-900 dark:text-white">${item.is_emri_tipi || 'İş Türü'}</h4>
                        <p class="text-sm text-slate-500">${formatDate(item.tarih)}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="card p-4 text-center">
                    <span class="material-symbols-outlined text-green-500 text-2xl">check_circle</span>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">${item.sonuclanmis || 0}</p>
                    <p class="text-xs text-slate-500">Sonuçlanan</p>
                </div>
                <div class="card p-4 text-center">
                    <span class="material-symbols-outlined text-amber-500 text-2xl">schedule</span>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">${item.acik_olanlar || 0}</p>
                    <p class="text-xs text-slate-500">Açık</p>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <span class="material-symbols-outlined text-slate-500">business</span>
                    <div>
                        <p class="text-xs text-slate-500">Firma</p>
                        <p class="font-semibold text-slate-900 dark:text-white">${item.firma || '-'}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <span class="material-symbols-outlined text-slate-500">receipt_long</span>
                    <div>
                        <p class="text-xs text-slate-500">İş Emri Sonucu</p>
                        <p class="font-semibold text-slate-900 dark:text-white">${item.is_emri_sonucu || '-'}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <span class="material-symbols-outlined text-slate-500">groups</span>
                    <div>
                        <p class="text-xs text-slate-500">Ekip Kodu</p>
                        <p class="font-semibold text-slate-900 dark:text-white">${item.ekip_kodu || '-'}</p>
                    </div>
                </div>
            </div>
        `;

        Modal.open('puantaj-detail-modal');
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