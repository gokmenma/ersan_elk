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

    <!-- Stats Summary -->
    <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="grid grid-cols-2 gap-3">
            <div id="card-aktif" onclick="toggleZimmetFilter('active')"
                class="card p-4 flex items-center gap-3 cursor-pointer transition-all border-2 border-transparent">
                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-amber-600">inventory</span>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-900 dark:text-white" id="aktif-zimmet-count">0</p>
                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Aktif Zimmet</p>
                </div>
            </div>
            <div id="card-all" onclick="toggleZimmetFilter('all')"
                class="card p-4 flex items-center gap-3 cursor-pointer transition-all border-2 border-primary/50">
                <div
                    class="w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-emerald-600">history</span>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-900 dark:text-white" id="toplam-zimmet-count">0</p>
                    <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Toplam Kayıt</p>
                </div>
            </div>
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

<!-- Zimmet Detay Modal -->
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
                const aktifZimmetler = allZimmetler.filter(z => z.durum === 'teslim');

                document.getElementById('aktif-zimmet-count').textContent = aktifZimmetler.length;
                document.getElementById('toplam-zimmet-count').textContent = allZimmetler.length;

                renderZimmetler();
            }
        } catch (error) {
            console.error('Zimmetler yüklenemedi:', error);
            container.innerHTML = '<div class="card p-8 text-center text-rose-500">Veriler yüklenirken bir hata oluştu.</div>';
        }
    }

    function toggleZimmetFilter(filter) {
        currentZimmetFilter = filter;

        // Update UI
        const cardAktif = document.getElementById('card-aktif');
        const cardAll = document.getElementById('card-all');

        if (filter === 'active') {
            cardAktif.classList.replace('border-transparent', 'border-primary/50');
            cardAll.classList.replace('border-primary/50', 'border-transparent');
        } else {
            cardAll.classList.replace('border-transparent', 'border-primary/50');
            cardAktif.classList.replace('border-primary/50', 'border-transparent');
        }

        renderZimmetler();
    }

    function renderZimmetler() {
        const container = document.getElementById('zimmet-list');
        let filtered = allZimmetler;

        if (currentZimmetFilter === 'active') {
            filtered = allZimmetler.filter(z => z.durum === 'teslim');
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-slate-400 text-4xl">inventory_2</span>
                    </div>
                    <h3 class="text-slate-900 dark:text-white font-bold">Zimmet Bulunamadı</h3>
                    <p class="text-slate-500 text-sm mt-1">Bu kategoride kayıtlı herhangi bir zimmet bulunmuyor.</p>
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