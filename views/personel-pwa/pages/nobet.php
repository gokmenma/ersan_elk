<?php
/**
 * Personel PWA - Nöbet Sayfası
 * Nöbet listesi, takvim görünümü ve değişim talepleri
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header
        class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Nöbet Takibi</h1>
                <p class="text-sm text-slate-500">Nöbetlerinizi görüntüleyin ve yönetin</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openYeniTalepModal()"
                    class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">add</span>
                </button>
                <button onclick="openTaleplerModal()"
                    class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center relative">
                    <span class="material-symbols-outlined text-amber-600">swap_horiz</span>
                    <span id="talep-badge"
                        class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center hidden">0</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Stats Summary -->
    <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="grid grid-cols-3 gap-3">
            <div class="card p-3 text-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full -mr-4 -mt-4">
                </div>
                <div
                    class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-blue-600 text-lg">calendar_month</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="stat-toplam">0</p>
                <p class="text-[10px] text-slate-500">Bu Ay</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-purple-600 text-lg">weekend</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="stat-haftasonu">0</p>
                <p class="text-[10px] text-slate-500">Hafta Sonu</p>
            </div>

            <div class="card p-3 text-center relative overflow-hidden">
                <div
                    class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-2">
                    <span class="material-symbols-outlined text-green-600 text-lg">event_upcoming</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="stat-yaklasan">0</p>
                <p class="text-[10px] text-slate-500">Yaklaşan</p>
            </div>
        </div>
    </section>

    <!-- View Toggle & Month Selector -->
    <div
        class="px-4 py-3 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 sticky top-[73px] z-20">
        <div class="flex items-center justify-between gap-3">
            <!-- Month/Week Selector -->
            <div class="flex items-center gap-2">
                <button onclick="prevPeriod()"
                    class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">chevron_left</span>
                </button>
                <span id="period-label"
                    class="text-sm font-semibold text-slate-900 dark:text-white min-w-[120px] text-center">Şubat
                    2026</span>
                <button onclick="nextPeriod()"
                    class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">chevron_right</span>
                </button>
            </div>

            <!-- View Toggle -->
            <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                <button onclick="setView('calendar')" id="btn-calendar"
                    class="view-btn px-3 py-1.5 text-xs font-semibold rounded-md bg-white dark:bg-card-dark shadow-sm">
                    Takvim
                </button>
                <button onclick="setView('list')" id="btn-list"
                    class="view-btn px-3 py-1.5 text-xs font-medium rounded-md text-slate-600 dark:text-slate-400">
                    Liste
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view" class="px-4 py-4">
        <!-- Week Days Header -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Pzt</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Sal</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Çar</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Per</div>
            <div class="text-center text-[10px] font-semibold text-slate-500 py-1">Cum</div>
            <div class="text-center text-[10px] font-semibold text-red-500 py-1">Cmt</div>
            <div class="text-center text-[10px] font-semibold text-red-500 py-1">Paz</div>
        </div>
        <!-- Calendar Grid -->
        <div id="calendar-grid" class="grid grid-cols-7 gap-1">
            <!-- Days will be rendered here -->
        </div>
    </div>

    <!-- List View -->
    <div id="list-view" class="flex-1 px-4 py-4 hidden">
        <div class="flex flex-col gap-3" id="nobet-list">
            <!-- Shimmer loading -->
            <div class="shimmer h-20 rounded-xl"></div>
            <div class="shimmer h-20 rounded-xl"></div>
        </div>
    </div>
</div>

<!-- Nöbet Detay Modal -->
<div id="nobet-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Nöbet Detayı</h3>
            <button onclick="Modal.close('nobet-detay-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div id="nobet-detay-content">
            <!-- Content will be loaded dynamically -->
        </div>

        <div class="flex flex-col gap-2 mt-6" id="nobet-detay-actions">
            <!-- Action buttons -->
        </div>
    </div>
</div>

<!-- Değişim Talebi Modal -->
<div id="degisim-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 max-h-[85vh] overflow-y-auto">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">swap_horiz</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Nöbet Değişim Talebi</h3>
            </div>
            <button onclick="Modal.close('degisim-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="degisim-form" class="flex flex-col gap-4">
            <input type="hidden" name="nobet_id" id="degisim-nobet-id">

            <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-xl">
                <p class="text-xs text-slate-500 mb-1">Değiştirilecek Nöbet</p>
                <p class="font-bold text-slate-900 dark:text-white" id="degisim-nobet-info">-</p>
            </div>

            <div>
                <label class="form-label">Değişmek İstediğiniz Personel</label>
                <select name="talep_edilen_id" class="form-input form-select" required>
                    <option value="">Personel Seçiniz...</option>
                </select>
            </div>

            <div>
                <label class="form-label">Açıklama (Opsiyonel)</label>
                <textarea name="aciklama" class="form-input min-h-[80px]"
                    placeholder="Neden değişim talep ediyorsunuz?"></textarea>
            </div>

            <!-- Info Box -->
            <div
                class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-900/30 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-600">info</span>
                    <div>
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Bilgilendirme</p>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                            Talebiniz önce seçtiğiniz personele, ardından yöneticiye iletilecektir.
                            Nöbet başlangıcına 3 gün kala onaylanmayan talepler otomatik onaylanır.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('degisim-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    İptal
                </button>
                <button type="submit" class="flex-1 btn-primary py-3">
                    Talep Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Mazeret Bildirimi Modal -->
<div id="mazeret-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-red-500 text-2xl">warning</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Nöbeti Reddet</h3>
            </div>
            <button onclick="Modal.close('mazeret-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="mazeret-form" class="flex flex-col gap-4">
            <input type="hidden" name="nobet_id" id="mazeret-nobet-id">

            <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-xl">
                <p class="text-xs text-red-500 mb-1">Reddedilecek Nöbet</p>
                <p class="font-bold text-red-700 dark:text-red-300" id="mazeret-nobet-info">-</p>
            </div>

            <div>
                <label class="form-label">Mazeret Açıklaması <span class="text-red-500">*</span></label>
                <textarea name="aciklama" class="form-input min-h-[100px]" required
                    placeholder="Nöbete neden katılamayacağınızı açıklayınız..."></textarea>
            </div>

            <!-- Warning Box -->
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/30 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <div>
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">Dikkat</p>
                        <p class="text-xs text-red-700 dark:text-red-400 mt-1">
                            Mazeret bildirimi yöneticiye iletilecektir. Yönetici nöbeti başka bir personele
                            atayabilir veya sizinle iletişime geçebilir.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('mazeret-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    Vazgeç
                </button>
                <button type="submit" class="flex-1 py-3 bg-red-500 text-white font-semibold rounded-xl">
                    Mazeret Bildir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Gelen/Giden Talepler Modal -->
<div id="talepler-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 max-h-[85vh] overflow-y-auto">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Değişim Talepleri</h3>
            <button onclick="Modal.close('talepler-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-4">
            <button onclick="filterTalepler('gelen')" id="tab-gelen"
                class="talep-tab flex-1 py-2 text-sm font-semibold rounded-lg bg-primary text-white">
                Gelen
            </button>
            <button onclick="filterTalepler('giden')" id="tab-giden"
                class="talep-tab flex-1 py-2 text-sm font-medium rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                Giden
            </button>
        </div>

        <div id="talepler-list" class="flex flex-col gap-3">
            <!-- Talepler will be loaded here -->
        </div>
    </div>
</div>

<!-- Yeni Nöbet Talebi Modal -->
<div id="yeni-talep-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 max-h-[85vh] overflow-y-auto">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-2xl">event_upcoming</span>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Nöbet Talep Et</h3>
            </div>
            <button onclick="Modal.close('yeni-talep-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <form id="yeni-talep-form" class="flex flex-col gap-4">
            <div>
                <label class="form-label">Müsait Günler</label>
                <div id="musait-gunler-container" class="grid grid-cols-4 gap-2 max-h-[200px] overflow-y-auto p-1">
                    <!-- Günler buraya yüklenecek -->
                </div>
                <input type="hidden" name="tarih" id="selected-talep-tarih" required>
            </div>

            <div>
                <label class="form-label">Not (Opsiyonel)</label>
                <textarea name="aciklama" class="form-input min-h-[80px]"
                    placeholder="Eklemek istediğiniz bir not var mı?"></textarea>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-900/30 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-blue-600">info</span>
                    <div>
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Bilgi</p>
                        <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">
                            Seçtiğiniz tarihte boşta olan nöbet için talep oluşturulacaktır. Yönetici onayından sonra
                            nöbet size atanır.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-2">
                <button type="button" onclick="Modal.close('yeni-talep-modal')"
                    class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    İptal
                </button>
                <button type="submit" class="flex-1 btn-primary py-3">
                    Talebi Gönder
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // State
    let currentView = 'calendar';
    let currentDate = new Date();
    let nobetlerData = [];
    let taleplerData = [];
    let personelListesi = [];
    let currentTalepFilter = 'gelen';

    const aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
        'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    const gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

    document.addEventListener('DOMContentLoaded', function () {
        loadNobetler();
        loadTalepler();
        loadPersonelListesi();
        updatePeriodLabel();

        // Form submit handlers
        document.getElementById('degisim-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitDegisimTalebi(this);
        });

        document.getElementById('mazeret-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitMazeretBildirimi(this);
        });

        document.getElementById('yeni-talep-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitYeniNobetTalebi(this);
        });
    });

    // ============ VERİ YÜKLEMELERİ ============
    async function loadNobetler() {
        try {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth() + 1;

            const response = await API.request('getNobetler', {
                yil: year,
                ay: month
            });

            if (response.success) {
                nobetlerData = response.data || [];
                updateStats();
                renderView();
            }
        } catch (error) {
            console.error('Nöbetler yüklenemedi:', error);
        }
    }

    async function loadTalepler() {
        try {
            const response = await API.request('getNobetTalepleri');

            if (response.success) {
                taleplerData = response.data || [];
                updateTalepBadge();
            }
        } catch (error) {
            console.error('Talepler yüklenemedi:', error);
        }
    }

    async function loadPersonelListesi() {
        try {
            const response = await API.request('getNobetPersonelleri');

            if (response.success) {
                personelListesi = response.data || [];
                populatePersonelSelect();
            }
        } catch (error) {
            console.error('Personel listesi yüklenemedi:', error);
        }
    }

    function populatePersonelSelect() {
        const select = document.querySelector('#degisim-form select[name="talep_edilen_id"]');
        select.innerHTML = '<option value="">Personel Seçiniz...</option>';

        personelListesi.forEach(p => {
            const option = document.createElement('option');
            option.value = p.id;
            option.textContent = p.adi_soyadi;
            select.appendChild(option);
        });
    }

    // ============ İSTATİSTİKLER ============
    function updateStats() {
        const now = new Date();
        const toplam = nobetlerData.length;
        const haftaSonu = nobetlerData.filter(n => {
            const gun = new Date(n.nobet_tarihi).getDay();
            return gun === 0 || gun === 6;
        }).length;
        const yaklasan = nobetlerData.filter(n => {
            const nobetTarihi = new Date(n.nobet_tarihi);
            return nobetTarihi >= now;
        }).length;

        document.getElementById('stat-toplam').textContent = toplam;
        document.getElementById('stat-haftasonu').textContent = haftaSonu;
        document.getElementById('stat-yaklasan').textContent = yaklasan;
    }

    function updateTalepBadge() {
        const gelenBekleyen = taleplerData.filter(t =>
            t.talep_tipi === 'gelen' && t.durum === 'beklemede'
        ).length;

        const badge = document.getElementById('talep-badge');
        if (gelenBekleyen > 0) {
            badge.textContent = gelenBekleyen;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    // ============ GÖRÜNÜM YÖNETİMİ ============
    function setView(view) {
        currentView = view;

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'dark:bg-card-dark', 'shadow-sm');
            btn.classList.add('text-slate-600', 'dark:text-slate-400');
        });

        const activeBtn = document.getElementById(`btn-${view}`);
        activeBtn.classList.add('bg-white', 'dark:bg-card-dark', 'shadow-sm');
        activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');

        document.getElementById('calendar-view').classList.toggle('hidden', view !== 'calendar');
        document.getElementById('list-view').classList.toggle('hidden', view !== 'list');

        renderView();
    }

    function renderView() {
        if (currentView === 'calendar') {
            renderCalendar();
        } else {
            renderList();
        }
    }

    function updatePeriodLabel() {
        const label = `${aylar[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        document.getElementById('period-label').textContent = label;
    }

    function prevPeriod() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        updatePeriodLabel();
        loadNobetler();
    }

    function nextPeriod() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        updatePeriodLabel();
        loadNobetler();
    }

    // ============ TAKVİM RENDER ============
    function renderCalendar() {
        const grid = document.getElementById('calendar-grid');
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startOffset = (firstDay.getDay() + 6) % 7; // Pazartesi = 0

        let html = '';

        // Önceki aydan günler
        for (let i = 0; i < startOffset; i++) {
            html += `<div class="aspect-square p-1 text-center text-xs text-slate-300 dark:text-slate-600"></div>`;
        }

        // Bu ayın günleri
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Bugünün gece yarısını al

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const nobet = nobetlerData.find(n => n.nobet_tarihi === dateStr);
            const date = new Date(year, month, day);
            date.setHours(0, 0, 0, 0);
            const isToday = date.getTime() === today.getTime();
            const isPast = date < today;
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;

            let dayClass = 'bg-slate-50 dark:bg-slate-800';
            let textClass = 'text-slate-600 dark:text-slate-400';
            let hasNobet = false;
            let opacityClass = '';

            if (isToday) {
                dayClass = 'bg-primary/10 ring-2 ring-primary';
                textClass = 'text-primary font-bold';
            }

            if (nobet) {
                hasNobet = true;
                if (isPast) {
                    // Geçmiş nöbetler için pasif/soluk renkler
                    dayClass = isWeekend ? 'bg-purple-200 dark:bg-purple-900/40' : 'bg-blue-200 dark:bg-blue-900/40';
                    textClass = isWeekend ? 'text-purple-400 dark:text-purple-600' : 'text-blue-400 dark:text-blue-600';
                    opacityClass = 'opacity-60';
                } else {
                    // Gelecek nöbetler için normal renkler
                    dayClass = isWeekend ? 'bg-purple-500' : 'bg-blue-500';
                    textClass = 'text-white font-bold';
                }
            }

            html += `
                <div onclick="${hasNobet ? `showNobetDetay('${nobet?.id}')` : ''}" 
                     class="aspect-square p-1 rounded-lg ${dayClass} ${opacityClass} flex flex-col items-center justify-center cursor-pointer transition-transform active:scale-95">
                    <span class="text-sm ${textClass}">${day}</span>
                    ${hasNobet ? `<span class="w-1.5 h-1.5 ${isPast ? 'bg-slate-400' : 'bg-white'} rounded-full mt-0.5"></span>` : ''}
                </div>
            `;
        }

        grid.innerHTML = html;
    }

    // ============ LİSTE RENDER ============
    function renderList() {
        const container = document.getElementById('nobet-list');

        if (nobetlerData.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">event_busy</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Bu ay nöbetiniz yok</p>
                    <p class="text-sm text-slate-500">Henüz size atanmış nöbet bulunmuyor.</p>
                </div>
            `;
            return;
        }

        // Tarihe göre sırala
        const sorted = [...nobetlerData].sort((a, b) =>
            new Date(a.nobet_tarihi) - new Date(b.nobet_tarihi)
        );

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        container.innerHTML = sorted.map(nobet => {
            const date = new Date(nobet.nobet_tarihi);
            date.setHours(0, 0, 0, 0);
            const gunAdi = gunler[date.getDay()];
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            const isPast = date < today;

            // Geçmiş nöbetler için pasif stiller
            const cardOpacity = isPast ? 'opacity-60' : '';
            const bgClass = isPast 
                ? (isWeekend ? 'bg-purple-100/50 dark:bg-purple-900/20' : 'bg-blue-100/50 dark:bg-blue-900/20')
                : (isWeekend ? 'bg-purple-100 dark:bg-purple-900/30' : 'bg-blue-100 dark:bg-blue-900/30');
            const textClass = isPast
                ? (isWeekend ? 'text-purple-400' : 'text-blue-400')
                : (isWeekend ? 'text-purple-600' : 'text-blue-600');
            const subTextClass = isPast
                ? (isWeekend ? 'text-purple-300' : 'text-blue-300')
                : (isWeekend ? 'text-purple-500' : 'text-blue-500');
            const titleClass = isPast ? 'text-slate-500 dark:text-slate-500' : 'text-slate-900 dark:text-white';

            return `
                <div class="card p-4 ${cardOpacity}" onclick="showNobetDetay('${nobet.id}')">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-xl ${bgClass} flex flex-col items-center justify-center flex-shrink-0">
                            <span class="text-lg font-bold ${textClass}">${date.getDate()}</span>
                            <span class="text-[10px] ${subTextClass}">${aylar[date.getMonth()].substring(0, 3)}</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold ${titleClass}">${gunAdi}</p>
                            <p class="text-sm text-slate-500">${nobet.baslangic_saati?.substring(0, 5) || '18:00'} - ${nobet.bitis_saati?.substring(0, 5) || '08:00'}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="badge ${isPast ? 'bg-slate-200/50 text-slate-400 dark:bg-slate-700/30 dark:text-slate-500' : (isWeekend ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400')}">
                                    ${isWeekend ? 'Hafta Sonu' : 'Hafta İçi'}
                                </span>
                                ${isPast ? '<span class="badge bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-400">Geçmiş</span>' : ''}
                            </div>
                        </div>
                        <span class="material-symbols-outlined ${isPast ? 'text-slate-300' : 'text-slate-400'}">chevron_right</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ============ NÖBET DETAY ============
    async function showNobetDetay(nobetId) {
        const nobet = nobetlerData.find(n => n.id == nobetId);
        if (!nobet) return;

        const date = new Date(nobet.nobet_tarihi);
        const gunAdi = gunler[date.getDay()];
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        const isPast = date < new Date();
        const formattedDate = date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' });

        document.getElementById('nobet-detay-content').innerHTML = `
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-xl ${isWeekend ? 'bg-purple-100 dark:bg-purple-900/30' : 'bg-blue-100 dark:bg-blue-900/30'} flex flex-col items-center justify-center">
                        <span class="text-2xl font-bold ${isWeekend ? 'text-purple-600' : 'text-blue-600'}">${date.getDate()}</span>
                        <span class="text-xs ${isWeekend ? 'text-purple-500' : 'text-blue-500'}">${aylar[date.getMonth()]}</span>
                    </div>
                    <div>
                        <p class="font-bold text-lg text-slate-900 dark:text-white">${gunAdi}</p>
                        <p class="text-sm text-slate-500">${formattedDate}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                        <p class="text-xs text-slate-500 mb-1">Başlangıç</p>
                        <p class="font-semibold text-slate-900 dark:text-white">${nobet.baslangic_saati?.substring(0, 5) || '18:00'}</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                        <p class="text-xs text-slate-500 mb-1">Bitiş</p>
                        <p class="font-semibold text-slate-900 dark:text-white">${nobet.bitis_saati?.substring(0, 5) || '08:00'}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span class="badge ${isWeekend ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}">
                        ${isWeekend ? 'Hafta Sonu Nöbeti' : 'Hafta İçi Nöbeti'}
                    </span>
                    ${nobet.nobet_tipi ? `<span class="badge bg-slate-100 text-slate-600">${nobet.nobet_tipi}</span>` : ''}
                </div>

                ${nobet.aciklama ? `
                <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                    <p class="text-xs text-slate-500 mb-1">Açıklama</p>
                    <p class="text-sm text-slate-700 dark:text-slate-300">${nobet.aciklama}</p>
                </div>
                ` : ''}

                ${nobet.durum === 'mazeret_bildirildi' ? `
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/30 p-4 rounded-xl">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-500">warning</span>
                        <p class="font-semibold text-red-700 dark:text-red-400">Mazeret Bildirildi</p>
                    </div>
                    <p class="text-sm text-red-600 dark:text-red-400 mt-1">Bu nöbet için mazeret bildirilmiştir. Yönetici tarafından işlem bekleniyor.</p>
                </div>
                ` : ''}
            </div>
        `;

        // Action buttons
        const actionsContainer = document.getElementById('nobet-detay-actions');
        const isMazeretBildirildi = nobet.durum === 'mazeret_bildirildi';

        if (!isPast && !isMazeretBildirildi) {
            actionsContainer.innerHTML = `
                <button onclick="openDegisimModal('${nobetId}')" 
                    class="w-full py-3 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 font-semibold rounded-xl flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">swap_horiz</span>
                    Değişim Talep Et
                </button>
                <button onclick="openMazeretModal('${nobetId}')" 
                    class="w-full py-3 bg-red-50 dark:bg-red-900/20 text-red-600 font-semibold rounded-xl flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-lg">warning</span>
                    Mazeret Bildir
                </button>
                <button onclick="Modal.close('nobet-detay-modal')" 
                    class="w-full py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    Kapat
                </button>
            `;
        } else {
            actionsContainer.innerHTML = `
                <button onclick="Modal.close('nobet-detay-modal')" 
                    class="w-full py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                    Kapat
                </button>
            `;
        }

        Modal.open('nobet-detay-modal');
    }

    // ============ DEĞİŞİM TALEBİ ============
    function openDegisimModal(nobetId) {
        const nobet = nobetlerData.find(n => n.id == nobetId);
        if (!nobet) return;

        const date = new Date(nobet.nobet_tarihi);
        const formattedDate = date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric', weekday: 'long' });

        document.getElementById('degisim-nobet-id').value = nobetId;
        document.getElementById('degisim-nobet-info').textContent = formattedDate;

        Modal.close('nobet-detay-modal');
        Modal.open('degisim-modal');
    }

    async function submitDegisimTalebi(form) {
        const formData = Form.serialize(form);

        if (!formData.talep_edilen_id) {
            Toast.show('Lütfen bir personel seçiniz', 'error');
            return;
        }

        try {
            const response = await API.request('createNobetDegisimTalebi', formData);

            if (response.success) {
                Toast.show('Değişim talebiniz gönderildi', 'success');
                Modal.close('degisim-modal');
                form.reset();
                loadTalepler();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    // ============ MAZERET BİLDİRİMİ ============
    function openMazeretModal(nobetId) {
        const nobet = nobetlerData.find(n => n.id == nobetId);
        if (!nobet) return;

        const date = new Date(nobet.nobet_tarihi);
        const formattedDate = date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'long', year: 'numeric', weekday: 'long' });

        document.getElementById('mazeret-nobet-id').value = nobetId;
        document.getElementById('mazeret-nobet-info').textContent = formattedDate;

        Modal.close('nobet-detay-modal');
        Modal.open('mazeret-modal');
    }

    async function submitMazeretBildirimi(form) {
        const formData = Form.serialize(form);

        if (!formData.aciklama || formData.aciklama.trim().length < 10) {
            Toast.show('Lütfen mazeret açıklamasını giriniz (en az 10 karakter)', 'error');
            return;
        }

        try {
            const response = await API.request('createNobetMazeretBildirimi', formData);

            if (response.success) {
                Toast.show('Mazeret bildiriminiz iletildi', 'success');
                Modal.close('mazeret-modal');
                form.reset();
                loadNobetler();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    // ============ TALEPLER ============
    function openTaleplerModal() {
        renderTalepler();
        Modal.open('talepler-modal');
    }

    function filterTalepler(filter) {
        currentTalepFilter = filter;

        document.querySelectorAll('.talep-tab').forEach(tab => {
            tab.classList.remove('bg-primary', 'text-white');
            tab.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');
        });

        const activeTab = document.getElementById(`tab-${filter}`);
        activeTab.classList.add('bg-primary', 'text-white');
        activeTab.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400');

        renderTalepler();
    }

    function renderTalepler() {
        const container = document.getElementById('talepler-list');
        const filtered = taleplerData.filter(t => t.talep_tipi === currentTalepFilter);

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="empty-state py-8">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">inbox</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Talep bulunamadı</p>
                    <p class="text-sm text-slate-500">${currentTalepFilter === 'gelen' ? 'Size gelen' : 'Gönderdiğiniz'} talep bulunmuyor.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(talep => {
            const date = new Date(talep.nobet_tarihi);
            const formattedDate = date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' });

            let statusBadge = '';
            let statusColor = '';

            switch (talep.durum) {
                case 'beklemede':
                    statusBadge = 'Bekliyor';
                    statusColor = 'bg-amber-100 text-amber-700';
                    break;
                case 'personel_onayladi':
                    statusBadge = 'Onay Bekliyor';
                    statusColor = 'bg-blue-100 text-blue-700';
                    break;
                case 'onaylandi':
                    statusBadge = 'Onaylandı';
                    statusColor = 'bg-green-100 text-green-700';
                    break;
                case 'reddedildi':
                    statusBadge = 'Reddedildi';
                    statusColor = 'bg-red-100 text-red-700';
                    break;
            }

            const kisiAdi = currentTalepFilter === 'gelen' ? talep.talep_eden_adi : talep.talep_edilen_adi;

            return `
                <div class="card p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-slate-500">person</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-slate-900 dark:text-white">${kisiAdi}</p>
                            <p class="text-xs text-slate-500">${formattedDate} tarihli nöbet</p>
                            <span class="badge ${statusColor} mt-2">${statusBadge}</span>
                        </div>
                        ${talep.durum === 'beklemede' && currentTalepFilter === 'gelen' ? `
                            <div class="flex gap-2">
                                <button onclick="onaylaTalep('${talep.id}')" 
                                    class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-green-600 text-lg">check</span>
                                </button>
                                <button onclick="reddetTalep('${talep.id}')" 
                                    class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-red-600 text-lg">close</span>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    async function onaylaTalep(talepId) {
        const confirmed = await Alert.confirm(
            'Talebi Onayla',
            'Bu değişim talebini onaylamak istediğinize emin misiniz?',
            'Evet, Onayla',
            'Vazgeç'
        );

        if (!confirmed) return;

        try {
            const response = await API.request('onaylaNobetDegisimTalebi', { talep_id: talepId });

            if (response.success) {
                Toast.show('Talep onaylandı.Yönetici onayını bekliyor.', 'success');
                loadTalepler();
                loadNobetler();
                Modal.close('talepler-modal');
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    async function reddetTalep(talepId) {
        const confirmed = await Alert.confirm(
            'Talebi Reddet',
            'Bu değişim talebini reddetmek istediğinize emin misiniz?',
            'Evet, Reddet',
            'Vazgeç'
        );

        if (!confirmed) return;

        try {
            const response = await API.request('reddetNobetDegisimTalebi', { talep_id: talepId });

            if (response.success) {
                Toast.show('Talep reddedildi', 'success');
                loadTalepler();
                renderTalepler();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    // ============ YENİ NÖBET TALEBİ ============
    async function openYeniTalepModal() {
        const container = document.getElementById('musait-gunler-container');
        container.innerHTML = '<div class="col-span-4 py-8 text-center"><div class="shimmer w-full h-10 rounded-lg"></div></div>';

        Modal.open('yeni-talep-modal');

        try {
            const response = await API.request('getMusaitNobetGunleri', {
                yil: currentDate.getFullYear(),
                ay: currentDate.getMonth() + 1
            });

            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(d => `
                    <button type="button" onclick="selectTalepTarih('${d.tarih}', this)" 
                        class="flex flex-col items-center justify-center p-2 rounded-xl bg-slate-50 dark:bg-slate-800 border-2 border-transparent transition-all hover:bg-primary/5">
                        <span class="text-xs text-slate-500">${d.gun_adi.substring(0, 3)}</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">${d.gun}</span>
                    </button>
                `).join('');
            } else {
                container.innerHTML = '<div class="col-span-4 py-8 text-center text-slate-500 text-sm">Bu ay için müsait nöbet günü bulunamadı.</div>';
            }
        } catch (error) {
            container.innerHTML = '<div class="col-span-4 py-8 text-center text-red-500 text-sm">Veriler yüklenemedi.</div>';
        }
    }

    function selectTalepTarih(tarih, element) {
        document.querySelectorAll('#musait-gunler-container button').forEach(btn => {
            btn.classList.remove('border-primary', 'bg-primary/5');
            btn.classList.add('border-transparent');
        });

        element.classList.remove('border-transparent');
        element.classList.add('border-primary', 'bg-primary/5');
        document.getElementById('selected-talep-tarih').value = tarih;
    }

    async function submitYeniNobetTalebi(form) {
        const formData = Form.serialize(form);

        if (!formData.tarih) {
            Toast.show('Lütfen bir tarih seçiniz', 'error');
            return;
        }

        try {
            const response = await API.request('createYeniNobetTalebi', formData);

            if (response.success) {
                Toast.show(response.message, 'success');
                Modal.close('yeni-talep-modal');
                form.reset();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }
</script>