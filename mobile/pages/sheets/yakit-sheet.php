<div id="sheet-content-yakit" class="app-sheet-content hidden">
    <?php
    $yAracMap = [];
    foreach ($araclar as $arac) {
        $yAracMap[$arac->id] = ['km' => $arac->guncel_km, 'yakit_tipi' => $arac->yakit_tipi];
    }
    $yakitTipleri = ['dizel' => 'Dizel', 'benzin' => 'Benzin', 'lpg' => 'LPG', 'elektrik' => 'Elektrik'];
    ?>
    <form id="yakitForm" class="space-y-5 px-1">
        <input type="hidden" name="action" value="yakit-kaydet">

        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-500 text-lg align-middle">local_gas_station</span>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-300">Yakıt Bilgileri</span>
            </div>
            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Araç Seçin *</label>
                    <select name="arac_id" id="y_arac_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white font-bold" required>
                        <option value="">-- Araç Seçin --</option>
                        <?php foreach($araclar as $arac): ?>
                            <option value="<?= $arac->id ?>"><?= htmlspecialchars($arac->plaka) ?> - <?= htmlspecialchars($arac->marka . ' ' . $arac->model) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tarih *</label>
                        <input type="date" name="tarih" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white" required>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Güncel KM *</label>
                        <input type="number" name="km" id="y_km" value="" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white font-bold text-emerald-600" required min="0">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Yakıt Tipi</label>
                        <select name="yakit_tipi" id="y_yakit_tipi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white">
                            <?php foreach($yakitTipleri as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">İstasyon</label>
                        <input type="text" name="istasyon" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white" placeholder="Opet, Shell vb.">
                    </div>
                </div>

                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/10 rounded-xl border border-emerald-100 dark:border-emerald-800/30">
                     <span class="block text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-2">Maliyet Hesaplama</span>
                     <div class="grid grid-cols-3 gap-2">
                         <div>
                            <label class="block text-[10px] text-slate-500 mb-1">Miktar (L)</label>
                            <input type="number" name="yakit_miktari" id="y_miktar" step="0.01" min="0" class="w-full bg-white dark:bg-slate-800 border-0 rounded-lg px-2 py-2 text-sm text-center font-bold focus:ring-1 focus:ring-emerald-500 outline-none transition-all shadow-sm" placeholder="0.00" required>
                         </div>
                         <div class="flex items-center justify-center pt-4 text-slate-300">
                             <span class="material-symbols-outlined text-sm">close</span>
                         </div>
                         <div>
                            <label class="block text-[10px] text-slate-500 mb-1">Birim (₺)</label>
                            <input type="number" name="birim_fiyat" id="y_birim" step="0.01" min="0" class="w-full bg-white dark:bg-slate-800 border-0 rounded-lg px-2 py-2 text-sm text-center font-bold focus:ring-1 focus:ring-emerald-500 outline-none transition-all shadow-sm" placeholder="0.00">
                         </div>
                     </div>
                     <div class="mt-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-slate-300 text-sm">drag_handle</span>
                        <div class="flex-1">
                            <label class="block text-[10px] text-slate-500 mb-1">Toplam Tutar (₺) *</label>
                            <input type="number" name="toplam_tutar" id="y_toplam" step="0.01" min="0" class="w-full bg-white dark:bg-slate-800 border-0 rounded-lg px-3 py-2 text-base font-extrabold text-emerald-600 focus:ring-1 focus:ring-emerald-500 outline-none transition-all shadow-sm" placeholder="0.00" required>
                        </div>
                     </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-1">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Fatura no</label>
                        <input type="text" name="fatura_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white" placeholder="Fiş Numarası">
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Depo Tamamen Dolduruldu mu?</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                      <input type="checkbox" name="tam_depo_mu" value="1" class="sr-only peer">
                      <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Notlar</label>
                    <textarea name="notlar" rows="2" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm transition-all" placeholder="Ek notlar..."></textarea>
                </div>
            </div>
        </div>

        <button type="button" onclick="saveYakit()" class="w-full py-3.5 bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
const yakitAracMap = <?= json_encode($yAracMap) ?>;
document.getElementById('y_arac_id').addEventListener('change', function() {
    if(this.value && yakitAracMap[this.value] !== undefined) {
        document.getElementById('y_km').value = yakitAracMap[this.value].km || '';
        document.getElementById('y_yakit_tipi').value = yakitAracMap[this.value].yakit_tipi || 'dizel';
    }
});

const inMiktar = document.getElementById('y_miktar');
const inBirim = document.getElementById('y_birim');
const inToplam = document.getElementById('y_toplam');

function calcPrices(e) {
    const targetId = e.target.id;
    const miktar = parseFloat(inMiktar.value) || 0;
    const birim = parseFloat(inBirim.value) || 0;
    const toplam = parseFloat(inToplam.value) || 0;

    if (targetId === 'y_miktar' || targetId === 'y_birim') {
        if (miktar > 0 && birim > 0) {
            inToplam.value = (miktar * birim).toFixed(2);
        }
    } else if (targetId === 'y_toplam') {
        if (miktar > 0 && toplam > 0) {
            inBirim.value = (toplam / miktar).toFixed(2);
        }
    }
}
inMiktar.addEventListener('input', calcPrices);
inBirim.addEventListener('input', calcPrices);
inToplam.addEventListener('input', calcPrices);

function saveYakit() {
    const form = document.getElementById('yakitForm');
    if(!form.arac_id.value || !form.tarih.value || !form.km.value || !form.yakit_miktari.value || !form.toplam_tutar.value) {
        MobileSwal.fire({icon: 'warning', title: 'Hata', text: 'Zorunlu alanları doldurun.'});
        return;
    }
    const formData = new FormData(form);
    if(!form.tam_depo_mu.checked) formData.append('tam_depo_mu', '0');

    MobileSwal.fire({title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
    fetch('../views/arac-takip/api.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({icon: 'success', title: 'Başarılı', text: data.message, timer: 1500})
            .then(() => { location.hash = 'yakit'; location.reload(); });
        } else MobileSwal.fire('Hata', data.message, 'error');
    }).catch(() => MobileSwal.fire('Hata', 'Bağlantı sorunu', 'error'));
}
</script>
