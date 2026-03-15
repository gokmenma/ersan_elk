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
        Swal.fire({icon: 'warning', title: 'Hata', text: 'Zorunlu alanları doldurun.', confirmButtonColor: '#f59e0b'});
        return;
    }
    const formData = new FormData(form);
    Swal.fire({title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
    fetch('?p=arac-takip/api', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({icon: 'success', title: 'Başarılı', text: data.message, confirmButtonColor: '#f59e0b', timer: 1500})
            .then(() => { location.hash = 'zimmet'; location.reload(); });
        } else {
            Swal.fire('Hata', data.message, 'error');
        }
    }).catch(() => Swal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
