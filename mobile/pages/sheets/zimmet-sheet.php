<div id="sheet-content-zimmet" class="app-sheet-content hidden">
    <?php
    $bostaAraclar = [];
    $aracKmMap = [];
    foreach ($araclar as $arac) {
        if (empty($arac->zimmetli_personel_id)) {
            $bostaAraclar[] = $arac;
            $aracKmMap[$arac->id] = $arac->guncel_km;
        }
    }
    ?>
    <form id="zimmetForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="zimmet-ver">

        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500 text-lg align-middle">swap_horiz</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Zimmet Bilgileri</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç Seçin *</label>
                    <select name="arac_id" id="z_arac_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all dark:text-white font-bold" required>
                        <option value="">-- Boştaki Araçlar --</option>
                        <?php foreach($bostaAraclar as $arac): ?>
                            <option value="<?= $arac->id ?>"><?= htmlspecialchars($arac->plaka) ?> - <?= htmlspecialchars($arac->marka . ' ' . $arac->model) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Personel Seçin *</label>
                    <select name="personel_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all dark:text-white" required>
                        <option value="">-- Personel Seç --</option>
                        <?php foreach($personeller as $personel): ?>
                            <option value="<?= $personel->id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Zimmet Tarihi *</label>
                        <input type="date" name="zimmet_tarihi" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Teslim KM *</label>
                        <input type="number" name="teslim_km" id="z_teslim_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all dark:text-white font-bold text-amber-600" required min="0">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Notlar</label>
                    <textarea name="notlar" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 outline-none transition-all dark:text-white" placeholder="Aksesuar durumu, hasar kaydı vb."></textarea>
                </div>

                <div>
                    <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                        Teslim Fotoğrafları
                        <span class="inline-flex items-center gap-0.5 text-[9px] text-green-600 dark:text-green-400 normal-case font-bold bg-green-50 dark:bg-green-900/20 px-1.5 py-0.5 rounded">
                            <span class="material-symbols-outlined text-[11px]">lock</span> Şifreli
                        </span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <!-- Galeri / PDF Seç -->
                        <label class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer transition-all border border-slate-200 dark:border-slate-700">
                            <span class="material-symbols-outlined text-base">photo_library</span> Galeri / PDF
                            <input type="file" id="z_galeri_input" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden">
                        </label>
                        <!-- Fotoğraf Çek -->
                        <label class="flex items-center justify-center gap-1.5 py-2.5 px-3 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl text-xs cursor-pointer transition-all shadow-sm">
                            <span class="material-symbols-outlined text-base">photo_camera</span> Fotoğraf Çek
                            <input type="file" id="z_camera_input" accept="image/*" capture="environment" class="hidden">
                        </label>
                    </div>
                    <!-- Gerçekten gönderilecek olan gizli input -->
                    <input type="file" name="teslim_fotograflari[]" id="teslim_fotograflari" multiple class="hidden">
                    
                    <!-- Seçilen dosya listesi -->
                    <div id="z_secilen_dosyalar_listesi" class="mt-2 space-y-1"></div>
                    <p class="text-[10px] text-slate-400 mt-1">Aracın teslim durumunu belgeleyin. Birden fazla dosya seçebilir veya fotoğraf çekebilirsiniz (maks. 8MB).</p>
                </div>
            </div>
        </div>
        
        <button type="button" onclick="saveZimmet()" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
const zAracKmMap = <?= json_encode($aracKmMap) ?>;
document.getElementById('z_arac_id').addEventListener('change', function() {
    if(this.value && zAracKmMap[this.value] !== undefined) {
        document.getElementById('z_teslim_km').value = zAracKmMap[this.value];
    }
});

function saveZimmet() {
    const form = document.getElementById('zimmetForm');
    if(!form.arac_id.value || !form.personel_id.value || !form.zimmet_tarihi.value || !form.teslim_km.value) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Zorunlu alanları doldurun.'});
        return;
    }
    const formData = new FormData(form);
    MobileSwal.fire({title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
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

const teslimDT = new DataTransfer();

document.getElementById('z_galeri_input').addEventListener('change', function() {
    Array.from(this.files).forEach(file => teslimDT.items.add(file));
    document.getElementById('teslim_fotograflari').files = teslimDT.files;
    updateSelectedFilesList('teslim_fotograflari', 'z_secilen_dosyalar_listesi', teslimDT);
    this.value = '';
});

document.getElementById('z_camera_input').addEventListener('change', function() {
    Array.from(this.files).forEach(file => teslimDT.items.add(file));
    document.getElementById('teslim_fotograflari').files = teslimDT.files;
    updateSelectedFilesList('teslim_fotograflari', 'z_secilen_dosyalar_listesi', teslimDT);
    this.value = '';
});
</script>
