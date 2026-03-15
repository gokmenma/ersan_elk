<div id="sheet-content-km" class="app-sheet-content hidden">
    <?php
    $kmAracMap2 = [];
    foreach ($araclar as $arac) {
        $kmAracMap2[$arac->id] = isset($maxKmList[$arac->id]) ? $maxKmList[$arac->id] : ($arac->baslangic_km ?? 0);
    }
    ?>
    <form id="kmForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="km-kaydet">

        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-sky-500 text-lg align-middle">speed</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Kilometre Bilgileri</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç Seçin *</label>
                    <select name="arac_id" id="k_arac_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" required>
                        <option value="">-- Araç Seçin --</option>
                        <?php foreach($araclar as $arac): ?>
                            <option value="<?= $arac->id ?>"><?= htmlspecialchars($arac->plaka) ?> - <?= htmlspecialchars($arac->marka . ' ' . $arac->model) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tarih *</label>
                    <input type="date" name="tarih" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" required>
                </div>
                
                <div class="grid grid-cols-2 gap-3 relative">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Başlangıç KM *</label>
                        <input type="number" name="baslangic_km" id="k_baslangic_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" required min="0">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Bitiş KM *</label>
                        <input type="number" name="bitis_km" id="k_bitis_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" required min="0">
                    </div>
                </div>

                <div class="p-4 bg-sky-50 dark:bg-sky-900/10 rounded-xl flex items-center justify-between border border-sky-100 dark:border-sky-800/30">
                     <span class="text-xs font-bold text-sky-600 dark:text-sky-400">Net Mesafe:</span>
                     <span id="k_yapilan_badge" class="font-extrabold text-lg text-sky-700 dark:text-sky-300">0 Km</span>
                     <input type="hidden" id="k_yapilan_km" name="yapilan" value="0">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Gidilen Rota & Notlar</label>
                    <textarea name="notlar" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm transition-all" placeholder="Gün içi rotalar..."></textarea>
                </div>
            </div>
        </div>

        <button type="button" id="btnKmSave" onclick="saveKm()" class="w-full py-3.5 bg-sky-500 hover:bg-sky-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
const kmAracMapStatic = <?= json_encode($kmAracMap2) ?>;
document.getElementById('k_arac_id').addEventListener('change', function() {
    if(this.value && kmAracMapStatic[this.value] !== undefined) {
        document.getElementById('k_baslangic_km').value = kmAracMapStatic[this.value];
        calcNetKm();
    }
});

const inkBas = document.getElementById('k_baslangic_km');
const inkBit = document.getElementById('k_bitis_km');
const outkBadge = document.getElementById('k_yapilan_badge');
const savekBtn = document.getElementById('btnKmSave');

function calcNetKm() {
    const bas = parseInt(inkBas.value) || 0;
    const bit = parseInt(inkBit.value) || 0;
    const net = bit - bas;

    if (bit > 0 && bit < bas) {
        outkBadge.innerText = 'HATA! (Eksi)';
        outkBadge.classList.replace('text-sky-700', 'text-red-600');
        outkBadge.classList.replace('dark:text-sky-300', 'text-red-400');
        savekBtn.disabled = true;
        savekBtn.classList.add('opacity-50');
    } else {
        outkBadge.innerText = (net >= 0 ? net : 0) + ' Km';
        outkBadge.classList.replace('text-red-600', 'text-sky-700');
        outkBadge.classList.replace('text-red-400', 'dark:text-sky-300');
        savekBtn.disabled = false;
        savekBtn.classList.remove('opacity-50');
    }
}
inkBas.addEventListener('input', calcNetKm);
inkBit.addEventListener('input', calcNetKm);

function saveKm() {
    const form = document.getElementById('kmForm');
    if(!form.arac_id.value || !form.tarih.value || !form.baslangic_km.value || !form.bitis_km.value) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Zorunlu alanları doldurun.'});
        return;
    }
    if (parseInt(form.bitis_km.value) < parseInt(form.baslangic_km.value)) {
        MobileSwal.fire('Hata', 'Bitiş KM, başlangıç KM\'den küçük olamaz!', 'warning');
        return;
    }
    const formData = new FormData(form);
    
    MobileSwal.fire({title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
    fetch('../views/arac-takip/api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({icon: 'success', title: 'Başarılı', text: data.message, timer: 1500})
            .then(() => { location.hash = 'km'; location.reload(); });
        } else MobileSwal.fire('Hata', data.message, 'error');
    }).catch(() => MobileSwal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
