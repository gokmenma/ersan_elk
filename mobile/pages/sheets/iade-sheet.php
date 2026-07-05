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
                    <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                        İade Fotoğrafları
                        <span class="inline-flex items-center gap-0.5 text-[9px] text-green-600 dark:text-green-400 normal-case font-bold bg-green-50 dark:bg-green-900/20 px-1.5 py-0.5 rounded">
                            <span class="material-symbols-outlined text-[11px]">lock</span> Şifreli
                        </span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <!-- Galeri / PDF Seç -->
                        <label class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer transition-all border border-slate-200 dark:border-slate-700">
                            <span class="material-symbols-outlined text-base">photo_library</span> Galeri / PDF
                            <input type="file" id="i_galeri_input" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                        </label>
                        <!-- Fotoğraf Çek -->
                        <label class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-rose-500 hover:bg-rose-600 text-white font-bold rounded-xl text-xs cursor-pointer transition-all shadow-sm">
                            <span class="material-symbols-outlined text-base">photo_camera</span> Fotoğraf Çek
                            <input type="file" id="i_camera_input" accept="image/*" capture="environment" class="hidden">
                        </label>
                    </div>
                    <!-- Gerçekten gönderilecek olan gizli input -->
                    <input type="file" name="iade_fotograflari[]" id="iade_fotograflari" multiple class="hidden">
                    
                    <!-- Seçilen dosya listesi -->
                    <div id="i_secilen_dosyalar_listesi" class="mt-2 space-y-1"></div>
                    <p class="text-[10px] text-slate-400 mt-1">Aracın iade durumunu belgeleyin. Birden fazla dosya seçebilir veya fotoğraf çekebilirsiniz (maks. 8MB).</p>
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

if (typeof updateSelectedFilesList !== 'function') {
    window.updateSelectedFilesList = function(inputId, listId, dtInstance) {
        const listEl = document.getElementById(listId);
        if (!listEl) return;
        listEl.innerHTML = '';
        
        Array.from(dtInstance.files).forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl text-xs border border-slate-100 dark:border-slate-800/50 mt-1';
            item.innerHTML = `
                <span class="truncate text-slate-650 dark:text-slate-350 max-w-[80%]">${file.name}</span>
                <button type="button" class="text-rose-500 hover:text-rose-700 flex items-center justify-center" data-index="${index}">
                    <span class="material-symbols-outlined text-base">cancel</span>
                </button>
            `;
            item.querySelector('button').onclick = function() {
                const idx = parseInt(this.getAttribute('data-index'));
                dtInstance.items.remove(idx);
                document.getElementById(inputId).files = dtInstance.files;
                updateSelectedFilesList(inputId, listId, dtInstance);
            };
            listEl.appendChild(item);
        });
    };
}

const iadeDT = new DataTransfer();

document.getElementById('i_galeri_input').addEventListener('change', function() {
    Array.from(this.files).forEach(file => iadeDT.items.add(file));
    document.getElementById('iade_fotograflari').files = iadeDT.files;
    updateSelectedFilesList('iade_fotograflari', 'i_secilen_dosyalar_listesi', iadeDT);
    this.value = '';
});

document.getElementById('i_camera_input').addEventListener('change', function() {
    Array.from(this.files).forEach(file => iadeDT.items.add(file));
    document.getElementById('iade_fotograflari').files = iadeDT.files;
    updateSelectedFilesList('iade_fotograflari', 'i_secilen_dosyalar_listesi', iadeDT);
    this.value = '';
});
</script>
