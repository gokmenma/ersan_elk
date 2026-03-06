<?php
/**
 * Personel PWA - İcralarım Sayfası
 */
?>

<div class="flex flex-col min-h-screen">
    <header class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">İcralarım</h1>
                <p class="text-sm text-slate-500">İcra ve haciz dosyalarınız</p>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="flex-1 px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="flex flex-col gap-3" id="icralar-list">
            <!-- Loading Skeletons -->
            <div class="card p-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl shimmer"></div>
                    <div class="flex-1 gap-2 flex flex-col">
                        <div class="h-4 w-32 shimmer rounded"></div>
                        <div class="h-3 w-24 shimmer rounded"></div>
                    </div>
                </div>
            </div>
            <div class="card p-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl shimmer"></div>
                    <div class="flex-1 gap-2 flex flex-col">
                        <div class="h-4 w-40 shimmer rounded"></div>
                        <div class="h-3 w-20 shimmer rounded"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İcra Detay Modal / Bottom Sheet -->
<div id="icra-detay-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 h-[85vh] flex flex-col">
        <div class="modal-handle"></div>

        <div class="flex items-center justify-between mb-4 shrink-0">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="icra-modal-title">İcra Dosya Detayı</h3>
            <button onclick="Modal.close('icra-detay-modal')"
                class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-600">close</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto disable-scrollbar pb-10">
            <div id="icra-detay-content">
                <!-- İçerik Dinamik Gelecek -->
            </div>

            <!-- Kesintiler Alanı -->
            <div class="mt-6 border-t border-slate-100 dark:border-slate-800 pt-6">
                <button onclick="loadIcraKesintileri()" id="btn-show-kesintiler" class="w-full btn-secondary flex items-center justify-center gap-2 py-3 mb-4">
                    <span class="material-symbols-outlined">receipt_long</span>
                    Yapılan Kesintileri Göster
                </button>
                
                <div id="icra-kesintiler-alan" class="hidden">
                    <h4 class="font-bold text-sm text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-primary"></span>
                        Kesinti Geçmişi
                    </h4>
                    <div id="icra-kesintiler-list" class="flex flex-col gap-2 relative pl-3 before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-200 dark:before:bg-slate-800">
                        <!-- Kesintiler buraya gelecek -->
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
    let activeIcra = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadIcralar();
    });

    async function loadIcralar() {
        const container = document.getElementById('icralar-list');

        try {
            const response = await API.request('getIcralar');

            if (response.success && response.data.length > 0) {
                container.innerHTML = response.data.map(icra => {
                    const isBitti = icra.durum === 'bitti';
                    const durumRenk = isBitti ? 'emerald' : 'rose';
                    const durumMetin = isBitti ? 'Bitti' : 'Devam Ediyor';
                    
                    // Hesaplamalar
                    const borc = parseFloat(icra.toplam_borc || 0);
                    const kalan = parseFloat(icra.kalan_tutar || 0);
                    const kesilen = borc - kalan;
                    const yuzde = borc > 0 ? Math.min(100, Math.round((kesilen / borc) * 100)) : 0;

                    return `
                    <div class="card p-0 overflow-hidden cursor-pointer" onclick='showIcraDetay(${JSON.stringify(icra).replace(/'/g, "&#39;")})'>
                        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-${durumRenk}-100 dark:bg-${durumRenk}-900/30 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-${durumRenk}-600 text-[20px]">${isBitti ? 'task_alt' : 'gavel'}</span>
                                </div>
                                <div>
                                    <p class="font-bold text-sm text-slate-900 dark:text-white">${icra.icra_dairesi || 'İcra Dairesi'}</p>
                                    <p class="text-xs text-slate-500">${icra.dosya_no || '-'}</p>
                                </div>
                            </div>
                            <span class="badge ${isBitti ? 'badge-success' : 'badge-danger'} text-[10px]">${durumMetin}</span>
                        </div>
                        <div class="p-4 bg-slate-50/50 dark:bg-slate-800/30">
                            <div class="flex justify-between items-end mb-2">
                                <div>
                                    <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-0.5">Kalan Borç</p>
                                    <p class="font-bold text-slate-900 dark:text-white text-base">${Format.currency(kalan)}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-0.5">Esas Borç</p>
                                    <p class="font-bold text-slate-700 dark:text-slate-300 text-sm">${Format.currency(borc)}</p>
                                </div>
                            </div>
                            <!-- ProgressBar -->
                            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-3 mb-1 overflow-hidden">
                                <div class="bg-primary h-1.5 rounded-full transition-all duration-500" style="width: ${yuzde}%"></div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-primary">%${yuzde} Ödendi</span>
                            </div>
                        </div>
                    </div>
                `}).join('');
            } else {
                container.innerHTML = `
                <div class="empty-state mt-10">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">gavel</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">İcra dosyanız bulunmamaktadır</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('İcralar yüklenemedi:', error);
            container.innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    function showIcraDetay(icra) {
        activeIcra = icra;
        const borc = parseFloat(icra.toplam_borc || 0);
        const kalan = parseFloat(icra.kalan_tutar || 0);
        const kesilen = borc - kalan;
        const kesintiText = icra.kesinti_turu === 'oran' ? `%${icra.kesinti_orani}` : Format.currency(icra.aylik_kesinti_tutari);

        document.getElementById('icra-detay-content').innerHTML = `
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-center py-5 bg-rose-50 dark:bg-rose-900/20 rounded-2xl mb-2">
                    <div class="text-center">
                        <p class="text-[11px] text-rose-600 dark:text-rose-400 font-bold mb-1 uppercase tracking-widest pl-1">KALAN BORÇ</p>
                        <h2 class="text-3xl font-black text-rose-600 dark:text-rose-400 font-display tracking-tight">${Format.currency(kalan)}</h2>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl">
                        <p class="text-[10px] text-slate-500 mb-1">Esas Borç</p>
                        <p class="font-bold text-sm text-slate-900 dark:text-white">${Format.currency(borc)}</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl">
                        <p class="text-[10px] text-slate-500 mb-1">Toplam Kesilen</p>
                        <p class="font-bold text-sm text-slate-900 dark:text-white">${Format.currency(kesilen)}</p>
                    </div>
                </div>

                <div class="mt-2 flex flex-col gap-0 border border-slate-100 dark:border-slate-800 rounded-xl overflow-hidden">
                    <div class="flex justify-between items-center py-3 px-4 bg-white dark:bg-card-dark border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-slate-400">gavel</span>
                            <span class="text-slate-500 text-xs font-medium">İcra Dairesi</span>
                        </div>
                        <span class="font-bold text-sm text-slate-900 dark:text-white">${icra.icra_dairesi || '-'}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 px-4 bg-white dark:bg-card-dark border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-slate-400">tag</span>
                            <span class="text-slate-500 text-xs font-medium">Dosya No</span>
                        </div>
                        <span class="font-bold text-sm text-slate-900 dark:text-white">${icra.dosya_no || '-'}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 px-4 bg-white dark:bg-card-dark border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-slate-400">business</span>
                            <span class="text-slate-500 text-xs font-medium">Alacaklı</span>
                        </div>
                        <span class="font-bold text-sm text-slate-900 dark:text-white">${icra.alacakli || '-'}</span>
                    </div>
                    <div class="flex justify-between items-center py-3 px-4 bg-white dark:bg-card-dark">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm text-slate-400">percent</span>
                            <span class="text-slate-500 text-xs font-medium">Aylık Kesinti</span>
                        </div>
                        <span class="font-bold text-sm text-slate-900 dark:text-white">${kesintiText}</span>
                    </div>
                </div>
            </div>
        `;

        // Reset state
        document.getElementById('icra-kesintiler-alan').classList.add('hidden');
        document.getElementById('btn-show-kesintiler').style.display = 'flex';
        document.getElementById('icra-kesintiler-list').innerHTML = '';

        Modal.open('icra-detay-modal');
    }

    async function loadIcraKesintileri() {
        if (!activeIcra) return;

        const btn = document.getElementById('btn-show-kesintiler');
        const list = document.getElementById('icra-kesintiler-list');
        const alan = document.getElementById('icra-kesintiler-alan');

        btn.innerHTML = `<span class="material-symbols-outlined animate-spin">refresh</span> Yükleniyor...`;
        btn.disabled = true;

        try {
            const response = await API.request('getIcraKesintileri', { icra_id: activeIcra.id });

            if (response.success) {
                btn.style.display = 'none';
                alan.classList.remove('hidden');
                
                if (response.data.length > 0) {
                    list.innerHTML = response.data.map(k => `
                        <div class="relative pl-6 py-2">
                            <div class="absolute w-3 h-3 bg-white dark:bg-card-dark border-2 border-primary rounded-full left-[-5px] top-[14px] z-10 shadow-sm"></div>
                            <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 p-3 rounded-xl shadow-sm">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="font-bold text-sm text-slate-900 dark:text-white">${k.donem_adi || k.tarih}</p>
                                    <p class="font-bold text-primary">${Format.currency(k.tutar)}</p>
                                </div>
                                ${k.aciklama ? `<p class="text-xs text-slate-500 mt-1">${k.aciklama}</p>` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `<p class="text-xs text-slate-500 italic py-2 pl-4">Henüz kesinti kaydı bulunmamaktadır.</p>`;
                }
            } else {
                Toast.show("Kesintiler yüklenirken bir hata oluştu.", "error");
                btn.innerHTML = `<span class="material-symbols-outlined">receipt_long</span> Yapılan Kesintileri Göster`;
                btn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            Toast.show("Kesintiler yüklenirken bir bağlantı hatası oluştu.", "error");
            btn.innerHTML = `<span class="material-symbols-outlined">receipt_long</span> Yapılan Kesintileri Göster`;
            btn.disabled = false;
        }
    }
</script>
