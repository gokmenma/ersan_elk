<div id="sheet-content-iade" class="app-sheet-content hidden">
    <form id="iadeForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="zimmet-iade">
        <input type="hidden" name="zimmet_id" id="iade_zimmet_id" value="">

        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-rose-500 text-lg align-middle">assignment_return</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">İade Bilgileri</span>
            </div>
            <div class="p-4 space-y-4">
                <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-100 dark:border-rose-900/30 rounded-xl p-3 flex items-start gap-2">
                    <span class="material-symbols-outlined text-rose-500 text-lg">info</span>
                    <p class="text-xs text-rose-700 dark:text-rose-300 font-semibold" id="iade_arac_bilgi">Aracı iade alırken güncel KM bilgisini giriniz.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">İade Tarihi *</label>
                        <input type="date" name="iade_tarihi" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">İade KM *</label>
                        <input type="number" name="iade_km" id="iade_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all dark:text-white font-bold text-rose-600" required min="0">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">İade Notu</label>
                    <textarea name="notlar" rows="2" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all dark:text-white" placeholder="İade notları (opsiyonel)"></textarea>
                </div>

                <div>
                    <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                        İade Fotoğrafları
                        <span class="inline-flex items-center gap-0.5 text-[9px] text-green-600 dark:text-green-400 normal-case font-bold bg-green-50 dark:bg-green-900/20 px-1.5 py-0.5 rounded">
                            <span class="material-symbols-outlined text-[11px]">lock</span> Şifreli
                        </span>
                    </label>
                    <input type="file" name="iade_fotograflari[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-rose-50 file:text-rose-600 dark:file:bg-rose-900/20 dark:file:text-rose-400">
                    <p class="text-[10px] text-slate-400 mt-1">Aracın iade durumunu belgeleyin. Birden fazla dosya seçebilirsiniz (maks. 8MB).</p>
                </div>
            </div>
        </div>

        <button type="button" onclick="saveIade()" class="w-full py-3.5 bg-rose-500 hover:bg-rose-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">check</span> İadeyi Tamamla
        </button>
    </form>
</div>

<script>
function openIadeSheet(zimmetId, plaka, personel) {
    openSheet('iade');
    document.getElementById('iade_zimmet_id').value = zimmetId;
    document.getElementById('iade_km').value = '';
    const bilgi = document.getElementById('iade_arac_bilgi');
    if (bilgi) {
        bilgi.textContent = (plaka || '') + ' plakalı araç ' + (personel ? '(' + personel + ') ' : '') + 'iade alınıyor. Güncel KM bilgisini giriniz.';
    }
}

function saveIade() {
    const form = document.getElementById('iadeForm');
    if (!form.zimmet_id.value) {
        MobileSwal.fire({icon: 'error', title: 'Hata', text: 'Zimmet bilgisi bulunamadı.'});
        return;
    }
    if (!form.iade_km.value || parseInt(form.iade_km.value) <= 0) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Lütfen geçerli bir iade KM giriniz.'});
        return;
    }
    if (!form.iade_tarihi.value) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Lütfen iade tarihini giriniz.'});
        return;
    }
    const formData = new FormData(form);
    MobileSwal.fire({title: 'İşleniyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
    fetch('../views/arac-takip/api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({icon: 'success', title: 'Başarılı', text: data.message, timer: 1500})
            .then(() => { location.hash = 'zimmet'; location.reload(); });
        } else {
            MobileSwal.fire('Hata', data.message, 'error');
        }
    }).catch(() => MobileSwal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
