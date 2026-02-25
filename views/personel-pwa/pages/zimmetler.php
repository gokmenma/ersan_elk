<?php
/**
 * Personel PWA - Zimmetler Sayfası
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header
        class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Zimmetler</h1>
                <p class="text-sm text-slate-500">Üzerinizdeki demirbaşlar</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="exportZimmetToExcel()"
                    class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 transition-all active:scale-90"
                    title="Excel'e Aktar">
                    <span class="material-symbols-outlined">download</span>
                </button>
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary">inventory_2</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Stats Summary Section -->
    <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="grid grid-cols-2 gap-3">
            <!-- Araç Zimmetleri -->
            <div id="card-arac" onclick="toggleZimmetCategory('arac')"
                class="card p-3 flex flex-col gap-2 cursor-pointer transition-all border-2 border-transparent relative overflow-hidden group">
                <div class="flex items-center justify-between z-10">
                    <div
                        class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600">
                        <span class="material-symbols-outlined">directions_car</span>
                    </div>
                    <p class="text-xl font-black text-slate-900 dark:text-white" id="arac-zimmet-count">0</p>
                </div>
                <div class="z-10">
                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Araçlar</p>
                </div>
                <!-- Subtle Background Decorative Icon -->
                <span
                    class="material-symbols-outlined absolute -right-2 -bottom-2 text-6xl text-slate-200/20 dark:text-slate-700/10 pointer-events-none group-hover:scale-110 transition-transform">directions_car</span>
            </div>

            <!-- Teknoloji (Telefon, Tablet, Yazıcı) -->
            <div id="card-tech" onclick="toggleZimmetCategory('tech')"
                class="card p-3 flex flex-col gap-2 cursor-pointer transition-all border-2 border-transparent relative overflow-hidden group">
                <div class="flex items-center justify-between z-10">
                    <div
                        class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600">
                        <span class="material-symbols-outlined">devices</span>
                    </div>
                    <p class="text-xl font-black text-slate-900 dark:text-white" id="tech-zimmet-count">0</p>
                </div>
                <div class="z-10">
                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Teknoloji</p>
                </div>
                <span
                    class="material-symbols-outlined absolute -right-2 -bottom-2 text-6xl text-slate-200/20 dark:text-slate-700/10 pointer-events-none group-hover:scale-110 transition-transform">devices</span>
            </div>

            <!-- Sayaçlar (Sıfır & Hurda) -->
            <div id="card-sayac" onclick="showSayacDetay()"
                class="card p-3 flex flex-col gap-2 cursor-pointer transition-all border-2 border-transparent relative overflow-hidden group bg-rose-50/50 dark:bg-rose-900/5 hover:bg-rose-50 dark:hover:bg-rose-900/10">
                <div class="flex items-center justify-between z-10">
                    <div
                        class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center text-rose-600">
                        <span class="material-symbols-outlined">speed</span>
                    </div>
                    <p class="text-xl font-black text-slate-900 dark:text-white" id="sayac-zimmet-count">0</p>
                </div>
                <div class="z-10">
                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Sayaçlar</p>
                </div>
                <span
                    class="material-symbols-outlined absolute -right-2 -bottom-2 text-6xl text-slate-200/20 dark:text-slate-700/10 pointer-events-none group-hover:scale-110 transition-transform">speed</span>
            </div>

            <!-- Aparatlar -->
            <div id="card-aparat" onclick="showAparatDetay()"
                class="card p-3 flex flex-col gap-2 cursor-pointer transition-all border-2 border-transparent relative overflow-hidden group bg-amber-50/50 dark:bg-amber-900/5 hover:bg-amber-50 dark:hover:bg-amber-900/10">
                <div class="flex items-center justify-between z-10">
                    <div
                        class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600">
                        <span class="material-symbols-outlined">build</span>
                    </div>
                    <p class="text-xl font-black text-slate-900 dark:text-white" id="aparat-zimmet-count">0</p>
                </div>
                <div class="z-10">
                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">Aparatlar</p>
                </div>
                <span
                    class="material-symbols-outlined absolute -right-2 -bottom-2 text-6xl text-slate-200/20 dark:text-slate-700/10 pointer-events-none group-hover:scale-110 transition-transform">build</span>
            </div>
        </div>

        <!-- Filter Info / Reset -->
        <div id="filter-status" class="hidden mt-3 flex items-center justify-between px-2">
            <span class="text-xs text-slate-500 font-medium" id="filter-text">Seçili kategori gösteriliyor</span>
            <button onclick="toggleZimmetCategory('all')"
                class="text-xs text-primary font-bold flex items-center gap-1">
                Tümünü Göster <span class="material-symbols-outlined text-sm">refresh</span>
            </button>
        </div>
    </section>

    <!-- Zimmet List -->
    <div class="flex-1 px-4 py-4">
        <div class="flex flex-col gap-3" id="zimmet-list">
            <!-- Shimmer Loading -->
            <div class="shimmer h-24 rounded-2xl"></div>
            <div class="shimmer h-24 rounded-2xl"></div>
            <div class="shimmer h-24 rounded-2xl"></div>
        </div>
    </div>
</div>

<!-- Sayaç Detay Modal -->
<div id="sayac-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[80vh]">
        <div class="modal-handle"></div>
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Sayaç Zimmetleri</h3>
            <button onclick="Modal.close('sayac-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div
                class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-800/50 flex flex-col items-center">
                <span class="text-3xl font-black text-emerald-600" id="sayac-sifir-val">0</span>
                <span class="text-[10px] font-bold text-emerald-600/70 uppercase tracking-widest mt-1">Sıfır
                    Sayaç</span>
            </div>
            <div
                class="bg-rose-50 dark:bg-rose-900/20 p-4 rounded-2xl border border-rose-100 dark:border-rose-800/50 flex flex-col items-center">
                <span class="text-3xl font-black text-rose-600" id="sayac-hurda-val">0</span>
                <span class="text-[10px] font-bold text-rose-600/70 uppercase tracking-widest mt-1">Hurda Sayaç</span>
            </div>
        </div>
        <button onclick="Modal.close('sayac-detay-modal'); toggleZimmetCategory('sayac')"
            class="w-full py-3.5 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/20 transition-all active:scale-95 mb-3">
            Listeyi Görüntüle
        </button>
        <button onclick="Modal.close('sayac-detay-modal')"
            class="w-full py-3 bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold rounded-2xl">Kapat</button>
    </div>
</div>

<!-- Aparat Detay Modal -->
<div id="aparat-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[90vh]">
        <div class="modal-handle"></div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Aparat Detayları</h3>
                <p class="text-xs text-slate-500" id="aparat-total-info">Toplam 0 aparat zimmetli</p>
            </div>
            <button onclick="Modal.close('aparat-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto pr-1">
            <div id="aparat-detail-list" class="space-y-3">
                <!-- Aparatlar buraya gelecek -->
            </div>
        </div>
        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button onclick="Modal.close('aparat-detay-modal'); toggleZimmetCategory('aparat')"
                class="w-full py-3.5 mb-3 bg-primary text-white font-bold rounded-2xl">Filtrele ve Listele</button>
            <button onclick="Modal.close('aparat-detay-modal')"
                class="w-full py-3.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-2xl">Kapat</button>
        </div>
    </div>
</div>

<!-- Zimmet Detay Modal (Existing) -->
<div id="zimmet-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 flex flex-col max-h-[90vh]">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Zimmet Geçmişi</h3>
            <button onclick="Modal.close('zimmet-detay-modal')"
                class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center transition-colors hover:bg-slate-200">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto pr-1">
            <div id="zimmet-hareket-list" class="space-y-4">
                <!-- Hareketler buraya gelecek -->
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
            <button onclick="Modal.close('zimmet-detay-modal')"
                class="w-full py-3.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-2xl transition-all active:scale-95">
                Kapat
            </button>
        </div>
    </div>
</div>

<script>
    let allZimmetler = [];
    let currentZimmetFilter = 'all';

    document.addEventListener('DOMContentLoaded', function () {
        loadZimmetler();
    });

    async function loadZimmetler() {
        const container = document.getElementById('zimmet-list');

        try {
            const response = await API.request('getZimmetler');

            if (response.success) {
                allZimmetler = response.data;
                calculateCategoryStats();
                renderZimmetler();
            }
        } catch (error) {
            console.error('Zimmetler yüklenemedi:', error);
            container.innerHTML = '<div class="card p-8 text-center text-rose-500">Veriler yüklenirken bir hata oluştu.</div>';
        }
    }

    function calculateCategoryStats() {
        // Aktif zimmetleri filtrele (üzerinde olanlar)
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');

        // 1. Araçlar
        const araclar = aktifler.filter(z => z.type === 'arac');
        document.getElementById('arac-zimmet-count').textContent = araclar.length;

        // 2. Teknoloji (Telefon, Tablet, Yazıcı, Bilgisayar)
        const techTerms = ['telefon', 'tablet', 'yazıcı', 'yazici', 'bilgisayar', 'laptop', 'ekran', 'monitör'];
        const tech = aktifler.filter(z => 
            z.type === 'demirbas' && 
            techTerms.some(term => z.kategori.toLowerCase().includes(term) || z.demirbas_adi.toLowerCase().includes(term))
        );
        document.getElementById('tech-zimmet-count').textContent = tech.length;

        // 3. Sayaçlar
        const sayaclar = aktifler.filter(z => z.kategori.toLowerCase().includes('sayaç') || z.kategori.toLowerCase().includes('sayac'));
        document.getElementById('sayac-zimmet-count').textContent = sayaclar.length;

        // 4. Aparatlar
        const aparatlar = aktifler.filter(z => z.kategori.toLowerCase().includes('aparat'));
        // Aparatlarda miktar toplamı önemli olabilir
        const aparatTotal = aparatlar.reduce((sum, item) => sum + (parseInt(item.miktar) || 0), 0);
        document.getElementById('aparat-zimmet-count').textContent = aparatTotal;
    }

    function toggleZimmetCategory(category) {
        currentZimmetFilter = category;
        
        // UI Reset
        const cards = ['arac', 'tech', 'sayac', 'aparat'];
        cards.forEach(c => {
            const el = document.getElementById(`card-${c}`);
            if (el) el.classList.remove('border-primary/50');
        });

        const filterStatus = document.getElementById('filter-status');
        const filterText = document.getElementById('filter-text');

        if (category === 'all') {
            filterStatus.classList.add('hidden');
        } else {
            filterStatus.classList.remove('hidden');
            const el = document.getElementById(`card-${category}`);
            if (el) el.classList.add('border-primary/50');
            
            const names = {
                'arac': 'Araçlar',
                'tech': 'Teknoloji Ürünleri',
                'sayac': 'Sayaçlar',
                'aparat': 'Aparatlar'
            };
            filterText.textContent = `${names[category] || category} filtresi aktif`;
        }

        renderZimmetler();
    }

    function showSayacDetay() {
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');
        const sayaclar = aktifler.filter(z => z.kategori.toLowerCase().includes('sayaç') || z.kategori.toLowerCase().includes('sayac'));
        
        let sifir = 0;
        let hurda = 0;

        sayaclar.forEach(z => {
            const name = z.demirbas_adi.toLowerCase();
            const miktar = parseInt(z.miktar) || 0;
            
            if (name.includes('sıfır') || name.includes('sifir')) {
                sifir += miktar;
            } else if (name.includes('hurda')) {
                hurda += miktar;
            } else {
                // Diğerleri (belirtilmemişse sıfır kabul edilebilir veya name bazlı bakılabilir)
                sifir += miktar; 
            }
        });

        document.getElementById('sayac-sifir-val').textContent = sifir;
        document.getElementById('sayac-hurda-val').textContent = hurda;
        
        Modal.open('sayac-detay-modal');
    }

    function showAparatDetay() {
        const aktifler = allZimmetler.filter(z => z.durum === 'teslim');
        const aparatlar = aktifler.filter(z => z.kategori.toLowerCase().includes('aparat'));
        
        const totalAmount = aparatlar.reduce((sum, item) => sum + (parseInt(item.miktar) || 0), 0);
        document.getElementById('aparat-total-info').textContent = `Zimmetinizde toplam ${totalAmount} aparat bulunuyor.`;

        const listContainer = document.getElementById('aparat-detail-list');
        
        if (aparatlar.length === 0) {
            listContainer.innerHTML = '<div class="text-center py-6 text-slate-500">Zimmetli aparat bulunamadı.</div>';
        } else {
            listContainer.innerHTML = aparatlar.map(z => `
                <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">${z.demirbas_adi}</span>
                        <span class="text-[10px] text-slate-500">${z.marka_model || 'Marka Belirtilmemiş'}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-400">×</span>
                        <span class="text-lg font-black text-primary">${z.miktar}</span>
                    </div>
                </div>
            `).join('');
        }

        Modal.open('aparat-detay-modal');
    }

    function renderZimmetler() {
        const container = document.getElementById('zimmet-list');
        let filtered = allZimmetler;

        // Önce durum filtresi (tüm kategoriler için aktifleri gösterelim kategori seçiliyse)
        if (currentZimmetFilter !== 'all') {
            filtered = allZimmetler.filter(z => z.durum === 'teslim');
            
            if (currentZimmetFilter === 'arac') {
                filtered = filtered.filter(z => z.type === 'arac');
            } else if (currentZimmetFilter === 'tech') {
                const terms = ['telefon', 'tablet', 'yazıcı', 'yazici', 'bilgisayar', 'laptop', 'ekran', 'monitör'];
                filtered = filtered.filter(z => 
                    z.type === 'demirbas' && 
                    terms.some(term => z.kategori.toLowerCase().includes(term) || z.demirbas_adi.toLowerCase().includes(term))
                );
            } else if (currentZimmetFilter === 'sayac') {
                filtered = filtered.filter(z => z.kategori.toLowerCase().includes('sayaç') || z.kategori.toLowerCase().includes('sayac'));
            } else if (currentZimmetFilter === 'aparat') {
                filtered = filtered.filter(z => z.kategori.toLowerCase().includes('aparat'));
            }
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-slate-400 text-4xl">inventory_2</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold">Zimmet Bulunamadı</h3>
                    <p class="text-slate-500 text-sm mt-1">Bu kategoride kayıtlı herhangi bir aktif zimmet bulunmuyor.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(z => `
            <div class="card p-4 transition-all active:scale-[0.98] cursor-pointer group" onclick="showZimmetHareketleri(${z.id}, '${z.demirbas_adi}', '${z.type}')">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-${z.durum_color}-100 dark:bg-${z.durum_color}-900/30 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                        <span class="material-symbols-outlined text-${z.durum_color}-600 text-2xl font-light">
                            ${z.type === 'arac' ? 'directions_car' : (z.durum === 'teslim' ? 'check_circle' : (z.durum === 'iade' ? 'history' : 'warning'))}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="font-bold text-slate-900 dark:text-white text-base truncate">${z.demirbas_adi}</h4>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-${z.durum_color}-100 dark:bg-${z.durum_color}-900/30 text-${z.durum_color}-700 dark:text-${z.durum_color}-300">
                                ${z.durum_text}
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">${z.type === 'arac' ? 'tag' : 'label'}</span>
                                ${z.kategori}
                            </span>
                            ${z.marka_model ? `
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">settings_suggest</span>
                                    ${z.marka_model}
                                </span>
                            ` : ''}
                             <span class="flex items-center gap-1 ml-auto font-bold text-primary">
                                ${z.miktar} Adet
                            </span>
                        </div>
                        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-xs text-slate-500">calendar_month</span>
                                </div>
                                <span class="text-[11px] text-slate-500 font-medium">${z.teslim_tarihi}</span>
                            </div>
                            <div class="flex items-center gap-1 text-primary">
                                <span class="text-[11px] font-bold">Geçmiş</span>
                                <span class="material-symbols-outlined text-sm">chevron_right</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function showZimmetHareketleri(zimmetId, demirbasAdi, type = 'demirbas') {
        const listContainer = document.getElementById('zimmet-hareket-list');
        listContainer.innerHTML = '<div class="flex justify-center py-8"><div class="shimmer w-full h-32 rounded-2xl"></div></div>';

        Modal.open('zimmet-detay-modal');

        try {
            const response = await API.request('getZimmetHareketleri', { zimmet_id: zimmetId, type: type });

            if (response.success) {
                const hareketler = response.data;

                if (hareketler.length === 0) {
                    listContainer.innerHTML = '<div class="text-center py-8 text-slate-500">Hareket kaydı bulunamadı.</div>';
                    return;
                }

                listContainer.innerHTML = hareketler.map((h, index) => `
                    <div class="relative pl-8 pb-2 last:pb-0">
                        <!-- Timeline Line -->
                        ${index !== hareketler.length - 1 ? '<div class="absolute left-[11px] top-7 bottom-0 w-0.5 bg-slate-100 dark:bg-slate-800"></div>' : ''}
                        
                        <!-- Timeline Dot -->
                        <div class="absolute left-0 top-1.5 w-6 h-6 rounded-full border-4 border-white dark:border-card-dark bg-${h.tip === 'zimmet' ? 'amber' : (h.tip === 'iade' ? 'emerald' : 'rose')}-500 z-10 shadow-sm"></div>
                        
                        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-${h.tip === 'zimmet' ? 'amber' : (h.tip === 'iade' ? 'emerald' : 'rose')}-600">
                                    ${h.tip === 'zimmet' ? 'TESLİM ALINDI' : (h.tip === 'iade' ? 'İADE EDİLDİ' : h.tip.toUpperCase())}
                                </span>
                                <span class="text-[10px] font-medium text-slate-400 bg-white dark:bg-slate-800 px-2 py-0.5 rounded-lg border border-slate-100 dark:border-slate-700">
                                    ${h.tarih}
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="material-symbols-outlined text-sm text-slate-400">inventory_2</span>
                                <span class="text-sm text-slate-700 dark:text-slate-300 font-medium">Miktar: ${h.miktar} Adet</span>
                            </div>
                            
                            ${h.aciklama ? `
                                <div class="bg-white dark:bg-card-dark p-3 rounded-xl border border-slate-100 dark:border-slate-800 mb-2">
                                    <p class="text-xs text-slate-500 leading-relaxed italic">"${h.aciklama}"</p>
                                </div>
                            ` : ''}
                            
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="material-symbols-outlined text-xs text-slate-400">person</span>
                                <span class="text-[10px] text-slate-400 font-medium">İşlem Yapan: ${h.islem_yapan || '-'}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                listContainer.innerHTML = `<div class="card p-4 text-center text-rose-500">${response.message}</div>`;
            }
        } catch (error) {
            console.error('Hareketler yüklenemedi:', error);
            listContainer.innerHTML = '<div class="card p-4 text-center text-rose-500">Hareketler yüklenirken bir hata oluştu.</div>';
        }
    }

    function exportZimmetToExcel() {
        if (allZimmetler.length === 0) {
            Toast.show('Dışa aktarılacak veri bulunamadı.', 'warning');
            return;
        }

        let html = `
            <table border="1">
                <thead>
                    <tr style="background-color: #f3f4f6;">
                        <th>Tür</th>
                        <th>Demirbaş No / Plaka</th>
                        <th>Demirbaş Adı</th>
                        <th>Kategori</th>
                        <th>Marka/Model</th>
                        <th>Seri No</th>
                        <th>Miktar</th>
                        <th>Teslim Tarihi</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
        `;

        allZimmetler.forEach(z => {
            html += `
                <tr>
                    <td>${z.type === 'arac' ? 'Araç' : 'Demirbaş'}</td>
                    <td>${z.demirbas_no}</td>
                    <td>${z.demirbas_adi}</td>
                    <td>${z.kategori}</td>
                    <td>${z.marka_model || '-'}</td>
                    <td>${z.seri_no || '-'}</td>
                    <td>${z.miktar}</td>
                    <td>${z.teslim_tarihi}</td>
                    <td>${z.durum_text}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';

        const blob = new Blob(['\ufeff', html], {
            type: 'application/vnd.ms-excel'
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Zimmetler_${new Date().toLocaleDateString().replace(/\./g, '-')}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        Toast.show('Excel dosyası indiriliyor...', 'success');
    }
</script>