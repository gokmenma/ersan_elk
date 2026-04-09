<?php
$aktifAracZimmeti = $AracZimmetModel->getAktifZimmetByPersonel($personel_id);
?>
<div class="flex flex-col min-h-screen bg-slate-50 dark:bg-background-dark pb-20">
    <!-- Header -->
    <header class="bg-white dark:bg-card-dark px-4 pt-4 pb-4 sticky top-0 z-30 border-b border-slate-100 dark:border-slate-800 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div class="flex flex-col">
                <h1 class="text-xl font-black text-slate-800 dark:text-white tracking-tight">KM Takibi</h1>
                <p class="text-xs text-slate-400 font-medium">Bildirimlerinizi takip edin</p>
            </div>
            <?php if ($aktifAracZimmeti): ?>
            <button onclick="openKmBildirModal()" 
                class="flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl font-bold shadow-lg shadow-primary/30 transition-all active:scale-95">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span class="text-sm">YENİ BİLDİR</span>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <!-- Stats -->
    <section class="p-4 grid grid-cols-3 gap-3">
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center text-center">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-1">
                <span class="material-symbols-outlined text-sm">assignment</span>
            </div>
            <span class="text-xs text-slate-400 font-bold uppercase tracking-tighter">TOPLAM</span>
            <p class="text-lg font-black text-slate-800 dark:text-white mt-1" id="stat-total">0</p>
        </div>
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center text-center">
            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center mb-1">
                <span class="material-symbols-outlined text-sm">schedule</span>
            </div>
            <span class="text-xs text-slate-400 font-bold uppercase tracking-tighter">BEKLEYEN</span>
            <p class="text-lg font-black text-slate-800 dark:text-white mt-1" id="stat-pending">0</p>
        </div>
        <div class="bg-white dark:bg-card-dark p-3 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm flex flex-col items-center justify-center text-center">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-1">
                <span class="material-symbols-outlined text-sm">check_circle</span>
            </div>
            <span class="text-xs text-slate-400 font-bold uppercase tracking-tighter">ONAYLI</span>
            <p class="text-lg font-black text-slate-800 dark:text-white mt-1" id="stat-approved">0</p>
        </div>
    </section>

    <!-- Tabs -->
    <section class="px-4 mb-4">
        <div class="bg-slate-200/50 dark:bg-slate-800/50 p-1.5 rounded-2xl flex items-center gap-1">
            <button onclick="filterKmReports('all')" id="tab-all" class="flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-all bg-white dark:bg-slate-900 text-primary shadow-md">Tümü</button>
            <button onclick="filterKmReports('beklemede')" id="tab-pending" class="flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700">Bekleyen</button>
            <button onclick="filterKmReports('onaylandi')" id="tab-approved" class="flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700">Onaylı</button>
            <button onclick="filterKmReports('reddedildi')" id="tab-rejected" class="flex-1 py-2.5 px-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-700">Reddedilen</button>
        </div>
    </section>

    <!-- List -->
    <section class="px-4 flex-1">
        <div id="km-list-container" class="space-y-4">
            <!-- Loading -->
            <div class="py-20 flex flex-col items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-2"></div>
                <p class="text-xs text-slate-400 font-medium">Yükleniyor...</p>
            </div>
        </div>
    </section>
</div>

<script>
    var globalKmReports = [];
    var activeFilter = 'all';

    async function loadAllKmReports() {
        try {
            const response = await API.request('get-km-history', {}, false);
            if (response.success) {
                globalKmReports = response.data;
                updateStats();
                renderKmReports();
            } else {
                document.getElementById('km-list-container').innerHTML = `
                    <div class="py-20 flex flex-col items-center text-center px-10">
                        <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-4xl text-slate-300">error</span>
                        </div>
                        <p class="text-slate-500 font-medium">Bildirimler yüklenirken bir sorun oluştu.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('KM Yükleme Hatası:', error);
        }
    }

    function updateStats() {
        const total = globalKmReports.length;
        const pending = globalKmReports.filter(r => r.durum === 'beklemede').length;
        const approved = globalKmReports.filter(r => r.durum === 'onaylandi').length;

        document.getElementById('stat-total').innerText = total;
        document.getElementById('stat-pending').innerText = pending;
        document.getElementById('stat-approved').innerText = approved;
    }

    function filterKmReports(filter) {
        activeFilter = filter;
        
        // UI Updates
        document.querySelectorAll('[id^="tab-"]').forEach(btn => {
            btn.classList.remove('bg-white', 'dark:bg-slate-900', 'text-primary', 'shadow-md');
            btn.classList.add('text-slate-500');
        });
        
        let tabId = 'tab-all';
        if (filter === 'beklemede') tabId = 'tab-pending';
        else if (filter === 'onaylandi') tabId = 'tab-approved';
        else if (filter === 'reddedildi') tabId = 'tab-rejected';

        const activeTab = document.getElementById(tabId);
        if (activeTab) {
            activeTab.classList.add('bg-white', 'dark:bg-slate-900', 'text-primary', 'shadow-md');
            activeTab.classList.remove('text-slate-500');
        }

        renderKmReports();
    }

    function renderKmReports() {
        const container = document.getElementById('km-list-container');
        let filtered = globalKmReports;
        
        if (activeFilter !== 'all') {
            filtered = globalKmReports.filter(r => r.durum === activeFilter);
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="py-20 flex flex-col items-center text-center px-10 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-[2rem]">
                    <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-3xl text-slate-300">description</span>
                    </div>
                    <p class="text-slate-400 font-medium text-sm">Burada gösterilecek bildirim bulunmuyor.</p>
                </div>
            `;
            return;
        }

        let html = '';
        filtered.forEach(item => {
            const statusColor = item.durum === 'onaylandi' ? 'emerald' : (item.durum === 'reddedildi' ? 'red' : 'amber');
            const statusLabel = item.durum === 'onaylandi' ? 'ONAYLANDI' : (item.durum === 'reddedildi' ? 'REDDEDİLDİ' : 'BEKLEMEDE');
            const typeIcon = item.tur === 'sabah' ? 'wb_sunny' : 'nights_stay';
            const typeColor = item.tur === 'sabah' ? 'text-amber-500' : 'text-indigo-500';
            
            html += `
                <div class="bg-white dark:bg-card-dark p-4 rounded-3xl border border-slate-100 dark:border-slate-800 shadow-sm flex items-center gap-4 active:scale-[0.98] transition-all">
                    <div class="w-14 h-14 rounded-2xl overflow-hidden shrink-0 border border-slate-200 dark:border-slate-700 shadow-inner">
                        <img src="${item.resim_url}" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="material-symbols-outlined text-[14px] ${typeColor}">${typeIcon}</span>
                                <h4 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest truncate">${item.tarih_format} - ${item.tur_label}</h4>
                            </div>
                            <span class="shrink-0 text-[8px] px-2 py-0.5 rounded-md font-black bg-${statusColor}-500/10 text-${statusColor}-600 dark:text-${statusColor}-400 border border-${statusColor}-500/10">
                                ${statusLabel}
                            </span>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-lg font-black text-slate-800 dark:text-white">${item.bitis_km}</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase">KM</span>
                        </div>
                        <p class="text-xs text-slate-500 truncate mt-0.5">${item.plaka} | ${item.aciklama || 'Açıklama yok'}</p>
                    </div>

                    ${item.durum === 'beklemede' ? `
                        <button onclick='openKmBildirModal(${JSON_safe(item)})' class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shadow-lg shadow-indigo-500/10">
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>
                    ` : `
                        <div class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg text-slate-300">chevron_right</span>
                        </div>
                    `}
                </div>
            `;
        });
        container.innerHTML = html;
    }

    // JSON safe function (already in ana-sayfa but re-defining just in case they land here directly)
    if (typeof JSON_safe !== 'function') {
        window.JSON_safe = function(obj) {
            return JSON.stringify(obj).replace(/'/g, "&apos;");
        }
    }

    document.addEventListener('DOMContentLoaded', loadAllKmReports);
</script>
