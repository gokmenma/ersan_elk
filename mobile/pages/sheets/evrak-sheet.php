<?php
$personeller = $Personel->all(false, 'evrak');
$ofisPersoneller = array_filter($personeller, function($p) { return ($p->departman ?? '') == 'BÜRO'; });
$gelenEvraklar = $Evrak->getGelenEvraklar();

// Plakalar listesini çek
$db_conn = new App\Core\Db();
$araclar_list = $db_conn->db->query("SELECT id, plaka, marka, model FROM araclar WHERE silinme_tarihi IS NULL AND firma_id = " . intval($_SESSION['firma_id']) . " ORDER BY plaka ASC")->fetchAll(PDO::FETCH_OBJ);
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
                    <input type="date" name="tarih" id="tarih" value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" onchange="queryAracZimmet()" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Evrak No</label>
                    <input type="text" name="evrak_no" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white" placeholder="No">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Konu *</label>
                <select id="evrak_konu_select" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" onchange="onKonuSelectChange(this.value)" required>
                    <option value="">Seçiniz veya Yazınız...</option>
                    <option value="İcra Yazısı">İcra Yazısı</option>
                    <option value="Haciz Kaldırma Yazısı">Haciz Kaldırma Yazısı</option>
                    <option value="Maaş Haczi">Maaş Haczi</option>
                    <option value="Sigorta Giriş/Çıkış">Sigorta Giriş/Çıkış</option>
                    <option value="Resmi Yazışma">Resmi Yazışma</option>
                    <option value="Trafik Cezası">Trafik Cezası</option>
                    <option value="manuel">Diğer / Manuel Giriş...</option>
                </select>
                <div id="konu_manuel_container" class="hidden mt-2">
                    <input type="text" id="konu_manuel" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-medium" placeholder="Manuel Evrak Konusu girin..." oninput="onKonuManuelInput(this.value)">
                </div>
                <input type="hidden" name="konu" id="form_konu" value="" required>
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

        <!-- Trafik Cezası Bilgileri (Dinamik) -->
        <div id="trafficFineSection" class="hidden bg-white dark:bg-card-dark rounded-2xl shadow-sm border-2 border-dashed border-sky-500/50 p-4 space-y-4">
            <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800/60 pb-2">
                <span class="material-symbols-outlined text-sky-500">warning</span>
                <span class="font-bold text-sm text-slate-800 dark:text-white">Trafik Cezası Detayları</span>
            </div>
            
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Araç Plakası</label>
                <select name="plaka" id="plaka" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-bold" onchange="queryAracZimmet()">
                    <option value="">Plaka Seçiniz...</option>
                    <?php foreach ($araclar_list as $ar): ?>
                        <option value="<?= htmlspecialchars($ar->plaka) ?>"><?= htmlspecialchars($ar->plaka . ' - ' . $ar->marka . ' ' . $ar->model) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="plakaFeedback" class="small mt-1.5 px-1 font-bold text-xs" style="display:none;"></div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Ceza Tutarı (TL)</label>
                    <input type="number" step="any" name="ceza_tutari" id="ceza_tutari" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-medium" placeholder="Ceza Tutarı" oninput="calculateDiscount(this.value)">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kesilecek Tutar (TL)</label>
                    <input type="number" step="any" name="tutar" id="tutar" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-sky-500 outline-none transition-all dark:text-white font-medium" placeholder="Kesilecek Tutar">
                    <span class="text-[9px] text-slate-400 mt-1 block">Boş bırakılırsa Ceza Tutarı geçerli olur.</span>
                </div>
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

function onKonuSelectChange(val) {
    const hiddenInput = document.getElementById('form_konu');
    const manuelContainer = document.getElementById('konu_manuel_container');
    const manuelInput = document.getElementById('konu_manuel');
    
    if (val === 'manuel') {
        manuelContainer.classList.remove('hidden');
        hiddenInput.value = manuelInput.value;
        checkTrafficFineVisibility(manuelInput.value);
    } else {
        manuelContainer.classList.add('hidden');
        hiddenInput.value = val;
        checkTrafficFineVisibility(val);
    }
}

function onKonuManuelInput(val) {
    document.getElementById('form_konu').value = val;
    checkTrafficFineVisibility(val);
}

function checkTrafficFineVisibility(val) {
    const konu = (val || '').toLowerCase();
    const searchSubject = konu
        .replace(/ı/g, 'i')
        .replace(/ğ/g, 'g')
        .replace(/ü/g, 'u')
        .replace(/ş/g, 's')
        .replace(/ö/g, 'o')
        .replace(/ç/g, 'c');
        
    if (searchSubject.includes('trafik') || searchSubject.includes('ceza')) {
        document.getElementById('trafficFineSection').classList.remove('hidden');
    } else {
        document.getElementById('trafficFineSection').classList.add('hidden');
        document.getElementById('plaka').value = '';
        document.getElementById('ceza_tutari').value = '';
        document.getElementById('tutar').value = '';
        document.getElementById('plakaFeedback').style.display = 'none';
        document.getElementById('plakaFeedback').innerHTML = '';
    }
}

function calculateDiscount(val) {
    const amount = parseFloat(val);
    if (!isNaN(amount) && amount > 0) {
        document.getElementById('tutar').value = (amount * 0.75).toFixed(2);
    } else {
        document.getElementById('tutar').value = '';
    }
}

function queryAracZimmet() {
    const plaka = $('#plaka').val() || '';
    const tarih = $('#tarih').val() || '';
    const feedback = $('#plakaFeedback');
    
    if (plaka.length >= 5 && tarih !== '') {
        feedback.show().html('<span class="text-slate-500">Sorgulanıyor...</span>');
        
        $.post('../views/evrak-takip/api.php', {
            action: 'arac-zimmet-sorgula',
            plaka: plaka,
            tarih: tarih
        }, function(response) {
            const res = (typeof response === 'object') ? response : JSON.parse(response);
            if (res.status === 'success' && res.personel_id) {
                $('select[name="ilgili_personel_id"]').val(res.personel_id);
                feedback.html(`<span class="text-emerald-600">✓ Bu tarihte plakaya zimmetli personel otomatik seçildi: <strong>${res.personel_adi}</strong></span>`);
            } else {
                feedback.html('<span class="text-amber-600">⚠ Bu tarihte zimmetli personel bulunamadı.</span>');
            }
        }).fail(function() {
            feedback.html('<span class="text-rose-600">Sorgulama başarısız oldu.</span>');
        });
    } else {
        feedback.hide().html('');
    }
}

function saveEvrak() {
    const form = document.getElementById('evrakForm');
    
    // Validasyon
    const hiddenKonu = document.getElementById('form_konu').value;
    if (!hiddenKonu || !form.kurum_adi.value) {
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
