<div id="sheet-content-servis" class="app-sheet-content hidden">
    <form id="servisForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="servis-kaydet">

        <!-- Tab Menü -->
        <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-4 gap-1">
            <button type="button" onclick="switchServisTab('giris')" id="btn-tab-giris" class="flex-1 py-1.5 text-sm font-bold rounded-lg transition-all bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm">Giriş</button>
            <button type="button" onclick="switchServisTab('cikis')" id="btn-tab-cikis" class="flex-1 py-1.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 dark:text-slate-400">Çıkış</button>
            <button type="button" onclick="switchServisTab('ikame')" id="btn-tab-ikame" class="flex-1 py-1.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 dark:text-slate-400">İkame</button>
        </div>

        <!-- Tab 1: Giriş Bilgileri -->
        <div id="tab-servis-giris" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden block">
            <div class="bg-indigo-50 dark:bg-indigo-900/10 p-3 border-b border-indigo-100 dark:border-indigo-800/30 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-lg align-middle">login</span>
                <span class="font-bold text-sm text-indigo-700 dark:text-indigo-300">Servis Giriş</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç Seçin *</label>
                    <select name="arac_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white font-bold" required>
                        <option value="">-- Araç Seçin --</option>
                        <?php foreach($araclar as $arac): ?>
                            <option value="<?= $arac->id ?>"><?= htmlspecialchars($arac->plaka) ?> - <?= htmlspecialchars($arac->marka . ' ' . $arac->model) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Giriş Tarihi *</label>
                        <input type="date" name="servis_tarihi" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Giriş KM *</label>
                        <input type="number" name="giris_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white font-bold text-indigo-600" required min="0">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Servis Noktası</label>
                    <input type="text" name="servis_adi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" placeholder="Firma, Usta vs.">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Şikayet / Neden</label>
                    <textarea name="servis_nedeni" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm transition-all" placeholder="Bakım, Hasar..."></textarea>
                </div>
            </div>
        </div>

        <!-- Tab 2: Çıkış Bilgileri -->
        <div id="tab-servis-cikis" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-500 text-lg align-middle">logout</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Servis Çıkış (İade)</span>
            </div>
            <div class="p-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Çıkış Tarihi</label>
                        <input type="date" name="iade_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Çıkış KM</label>
                        <input type="number" name="cikis_km" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" min="0">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Yapılan İşlemler</label>
                    <textarea name="yapilan_islemler" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm transition-all"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Toplam Maliyet (₺)</label>
                        <input type="number" name="tutar" step="0.01" min="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm font-bold text-indigo-700" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Fatura no</label>
                        <input type="text" name="fatura_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm transition-all">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: İkame Araç -->
        <div id="tab-servis-ikame" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-500 text-lg align-middle">car_crash</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">İkame Araç Temini</span>
            </div>
            <div class="p-4 space-y-4">
                <div class="p-3 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-xl text-[11px] flex gap-2">
                    <span class="material-symbols-outlined text-[16px] shrink-0">info</span>
                    Servisten verilen ikame aracın bilgilerini içerir. Araç çıkısı yapıldığında ikame otomatik iade kabul edilir.
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plaka</label>
                    <input type="text" name="ikame_plaka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm uppercase" placeholder="34 XX 123">
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Marka</label>
                        <input type="text" name="ikame_marka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Model</label>
                        <input type="text" name="ikame_model" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Alış Tarihi</label>
                        <input type="date" name="ikame_alis_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Teslim KM</label>
                        <input type="number" name="ikame_teslim_km" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm" min="0">
                    </div>
                </div>
            </div>
        </div>

        <button type="button" onclick="saveServis()" class="w-full py-3.5 bg-indigo-500 hover:bg-indigo-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
function switchServisTab(id) {
    const tabs = ['giris', 'cikis', 'ikame'];
    tabs.forEach(t => {
        document.getElementById('tab-servis-' + t).classList.add('hidden');
        document.getElementById('tab-servis-' + t).classList.remove('block');
        document.getElementById('btn-tab-' + t).className = "flex-1 py-1.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 dark:text-slate-400";
    });
    
    document.getElementById('tab-servis-' + id).classList.remove('hidden');
    document.getElementById('tab-servis-' + id).classList.add('block');
    document.getElementById('btn-tab-' + id).className = "flex-1 py-1.5 text-sm font-bold rounded-lg transition-all bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-sm";
}

function saveServis() {
    const form = document.getElementById('servisForm');
    
    if(!form.arac_id.value || !form.servis_tarihi.value || !form.giris_km.value) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Giriş Tarihi ve Grup KM zorunludur.'})
        .then(() => switchServisTab('giris'));
        return;
    }

    const formData = new FormData(form);
    MobileSwal.fire({title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
    fetch('../views/arac-takip/api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({icon: 'success', title: 'Başarılı', text: data.message, timer: 1500})
            .then(() => { location.hash = 'servis'; location.reload(); });
        } else {
            MobileSwal.fire('Hata', data.message, 'error');
        }
    }).catch(() => MobileSwal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
