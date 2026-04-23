<?php
$personeller = $Personel->all(false, 'evrak');
$ofisPersoneller = array_filter($personeller, function($p) { return ($p->departman ?? '') == 'BÜRO'; });
$gelenEvraklar = $Evrak->getGelenEvraklar();
?>
<div id="sheet-content-evrak" class="app-sheet-content hidden">
    <form id="evrakForm" class="space-y-4 px-1" enctype="multipart/form-data">
        <input type="hidden" name="action" value="evrak-kaydet">
        <input type="hidden" name="id" id="form_evrak_id" value="">

        <!-- Tip & Tarih -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 space-y-4">
            <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl gap-1">
                <label class="flex-1 text-center py-2.5 rounded-lg text-xs font-bold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-emerald-600 has-[:checked]:shadow-sm transition-all">
                    <input type="radio" name="evrak_tipi" value="gelen" class="hidden" checked onchange="toggleTip(this.value)">
                    GELEN
                </label>
                <label class="flex-1 text-center py-2.5 rounded-lg text-xs font-bold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-rose-600 has-[:checked]:shadow-sm transition-all">
                    <input type="radio" name="evrak_tipi" value="giden" class="hidden" onchange="toggleTip(this.value)">
                    GİDEN
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Evrak Tarihi *</label>
                    <input type="date" name="tarih" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Evrak No</label>
                    <input type="text" name="evrak_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" placeholder="No">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Konu *</label>
                <input type="text" name="konu" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" placeholder="Evrak Konusu" required>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kurum / Firma Adı *</label>
                <input type="text" name="kurum_adi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" placeholder="Gelen/Giden Kurum" required>
            </div>
        </div>

        <!-- Atamalar -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 space-y-4">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Zimmetli Personel (Ofis)</label>
                <select name="personel_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white">
                    <option value="">Seçiniz...</option>
                    <?php foreach ($ofisPersoneller as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->adi_soyadi) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İlgili Personel</label>
                <select name="ilgili_personel_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white">
                    <option value="">Seçiniz...</option>
                    <?php foreach ($personeller as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->adi_soyadi) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Durum & Dosya -->
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 space-y-4">
            <!-- Gelen Evrak İçin -->
            <div id="gelenExtra" class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-500">check_circle</span>
                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Cevap Verildi mi?</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="cevap_verildi_mi" value="1" class="sr-only peer" onchange="toggleCevap(this.checked)">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
                <div id="cevapTarihContainer" class="hidden">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cevap Tarihi</label>
                    <input type="date" name="cevap_tarihi" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all dark:text-white">
                </div>
            </div>

            <!-- Giden Evrak İçin -->
            <div id="gidenExtra" class="hidden space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İlişkili Gelen Evrak</label>
                    <select name="ilgili_evrak_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-rose-500 outline-none transition-all dark:text-white">
                        <option value="">Seçiniz...</option>
                        <?php foreach ($gelenEvraklar as $ge): ?>
                            <option value="<?= $ge->id ?>"><?= htmlspecialchars($ge->evrak_no . ' - ' . $ge->konu) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Açıklama</label>
                <textarea name="aciklama" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" placeholder="Ek Notlar..."></textarea>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Dosya Yükle (Resim/PDF)</label>
                <input type="file" name="dosya" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[11px] file:font-black file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-900/30 dark:file:text-sky-400">
            </div>
        </div>

        <button type="button" onclick="saveEvrak()" class="w-full py-4 bg-sky-500 hover:bg-sky-600 active:scale-95 text-white font-black rounded-2xl transition-all shadow-lg flex items-center justify-center gap-2 mt-4">
            <span class="material-symbols-outlined text-xl">save</span> Kaydet
        </button>
    </form>
</div>

<script>
function toggleTip(val) {
    if (val === 'gelen') {
        document.getElementById('gelenExtra').classList.remove('hidden');
        document.getElementById('gidenExtra').classList.add('hidden');
    } else {
        document.getElementById('gelenExtra').classList.add('hidden');
        document.getElementById('gidenExtra').classList.remove('hidden');
    }
}

function toggleCevap(checked) {
    document.getElementById('cevapTarihContainer').classList.toggle('hidden', !checked);
}

function saveEvrak() {
    const form = document.getElementById('evrakForm');
    if (!form.konu.value || !form.kurum_adi.value) {
        MobileSwal.fire({ icon: 'warning', title: 'Uyarı', text: 'Lütfen zorunlu alanları doldurun.' });
        return;
    }

    const formData = new FormData(form);
    
    MobileSwal.fire({
        title: 'Kaydediliyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../views/evrak-takip/api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            MobileSwal.fire({ icon: 'success', title: 'Başarılı', text: data.message, timer: 1500 }).then(() => location.reload());
        } else {
            MobileSwal.fire('Hata', data.message, 'error');
        }
    })
    .catch(() => MobileSwal.fire('Hata', 'Sunucu hatası', 'error'));
}
</script>
