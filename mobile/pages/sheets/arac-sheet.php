<div id="sheet-content-arac" class="app-sheet-content hidden">
    <form id="aracForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="arac-kaydet">
        <input type="hidden" name="id" value="">

        <!-- Genel Bilgiler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-teal-500 text-lg align-middle">info</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Genel Bilgiler</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plaka *</label>
                    <input type="text" name="plaka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm font-bold uppercase focus:ring-2 focus:ring-teal-500 focus:border-transparent outline-none transition-all dark:text-white" placeholder="34 ABC 123" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Marka</label>
                        <input type="text" name="marka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Örn: Ford">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Model</label>
                        <input type="text" name="model" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Örn: Focus">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Yıl/Renk</label>
                        <div class="flex gap-2">
                            <input type="number" name="model_yili" value="<?= date('Y') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Yıl">
                            <input type="text" name="renk" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white" placeholder="Renk">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Durum</label>
                        <select name="durum" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="1">Aktif</option>
                            <option value="0">Pasif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç / Yakıt Tipi</label>
                    <div class="grid grid-cols-2 gap-3">
                        <select name="arac_tipi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="">Seçiniz</option>
                            <option value="binek">Binek</option>
                            <option value="kamyonet">Kamyonet</option>
                            <option value="kamyon">Kamyon</option>
                            <option value="minibus">Minibüs</option>
                            <option value="otobus">Otobüs</option>
                            <option value="motosiklet">Motosiklet</option>
                            <option value="diger">Diğer</option>
                        </select>
                        <select name="yakit_tipi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-teal-500 outline-none transition-all dark:text-white">
                            <option value="">Seçiniz</option>
                            <option value="dizel">Dizel</option>
                            <option value="benzin">Benzin</option>
                            <option value="lpg">LPG</option>
                            <option value="elektrik">Elektrik</option>
                            <option value="hibrit">Hibrit</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teknik Bilgiler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-500 text-lg align-middle">build</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Teknik & KM</span>
            </div>
            <div class="p-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Başlangıç KM</label>
                        <input type="number" name="baslangic_km" value="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" min="0">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Güncel KM</label>
                        <input type="number" name="guncel_km" value="0" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white font-bold text-indigo-700" min="0">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Şase No</label>
                        <input type="text" name="sase_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Ruhsat No</label>
                        <input type="text" name="ruhsat_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white">
                    </div>
                </div>
            </div>
        </div>

        <!-- Evrak - Tarihler -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-500 text-lg align-middle">calendar_month</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Evrak Bitiş Tarihleri</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">verified</span>Muayene</label>
                    <input type="date" name="muayene_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">shield</span>Sigorta</label>
                    <input type="date" name="sigorta_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"><span class="material-symbols-outlined text-[12px] align-middle mr-1">gpp_good</span>Kasko</label>
                    <input type="date" name="kasko_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-orange-500 outline-none transition-all dark:text-white">
                </div>
            </div>
        </div>

        <button type="button" onclick="saveArac()" class="w-full py-3.5 bg-teal-500 hover:bg-teal-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
function saveArac() {
    const form = document.getElementById('aracForm');
    const plaka = form.plaka.value.trim();
    
    if(!plaka) {
        Swal.fire({
            icon: 'warning',
            title: 'Hata',
            text: 'Plaka zorunludur.',
            confirmButtonColor: '#14b8a6'
        });
        return;
    }

    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Kaydediliyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('?p=arac-takip/api', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({icon: 'success', title: 'Başarılı', text: data.message, confirmButtonColor: '#14b8a6', timer: 1500}).then(() => location.reload());
        } else {
            Swal.fire('Hata', data.message, 'error');
        }
    })
    .catch(() => Swal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
