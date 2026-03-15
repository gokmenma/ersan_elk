<?php
use App\Model\CariModel;
use App\Helper\Security;
use App\Helper\Helper;

$Cari = new CariModel();
$db = $Cari->getDb();

// Bakiye Özeti
$summaryInfo = $Cari->summary();
$toplam_borc = $summaryInfo->toplam_borc ?? 0;
$toplam_alacak = $summaryInfo->toplam_alacak ?? 0;
$genel_bakiye = $summaryInfo->genel_bakiye ?? 0;

// Cari Listesi
$sql = "SELECT c.*, 
        (SELECT ROUND(SUM(alacak) - SUM(borc), 2) FROM cari_hareketleri WHERE cari_id = c.id AND silinme_tarihi IS NULL) as bakiye
        FROM cari c 
        WHERE c.silinme_tarihi IS NULL ORDER BY c.CariAdi ASC";
$stmt = $db->query($sql);
$cariler = $stmt->fetchAll(PDO::FETCH_OBJ);

// Format helper functions
if (!function_exists('formatMoneyCariTakip')) {
    function formatMoneyCariTakip($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' ₺';
    }
    function absMoneyCariTakip($amount) {
        return number_format(abs((float)$amount), 2, ',', '.') . ' ₺';
    }
}
?>

<div class="px-3 py-4 space-y-4 pb-20">

    <!-- Özet Kartları -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-rose-500 text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-rose-500 text-[22px] bg-rose-50 dark:bg-rose-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">trending_up</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Toplam Borç</p>
            <p class="font-bold text-rose-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyCariTakip($toplam_borc) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-emerald-500 text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-emerald-500 text-[22px] bg-emerald-50 dark:bg-emerald-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">trending_down</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Toplam Alacak</p>
            <p class="font-bold text-emerald-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyCariTakip($toplam_alacak) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-primary text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-primary text-[22px] bg-blue-50 dark:bg-blue-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">account_balance_wallet</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Bakiye</p>
            <p class="font-bold <?= $genel_bakiye < 0 ? 'text-rose-600' : ($genel_bakiye > 0 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300') ?> text-xs sm:text-sm mt-0.5 truncate w-full"><?= absMoneyCariTakip($genel_bakiye) ?></p>
            <span class="text-[8px] opacity-70"><?= $genel_bakiye < 0 ? '(Borçlu)' : ($genel_bakiye > 0 ? '(Alacaklı)' : '') ?></span>
        </div>
    </div>

    <!-- Arama Kutusu -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <span class="material-symbols-outlined text-slate-400">search</span>
        </div>
        <input type="text" id="cariSearch" placeholder="Cari Ara..." autocomplete="off"
               class="w-full pl-10 pr-4 py-3 bg-white dark:bg-card-dark border-transparent focus:border-primary focus:ring-0 rounded-xl shadow-sm text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
    </div>

    <!-- Cari Listesi -->
    <div class="space-y-2" id="cariList">
        <?php foreach ($cariler as $cari): 
            $bakiye = $cari->bakiye ?? 0;
            $bakiyeColor = $bakiye < 0 ? 'text-rose-600' : ($bakiye > 0 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300');
            $bakiyeLabel = $bakiye < 0 ? 'BORÇLU' : ($bakiye > 0 ? 'ALACAKLI' : 'BAKİYE YOK');
            $initial = mb_strtoupper(mb_substr($cari->CariAdi, 0, 1, 'UTF-8'), 'UTF-8');
            $encId = Security::encrypt($cari->id);
            $searchString = mb_strtolower($cari->CariAdi . ' ' . $cari->Telefon . ' ' . $cari->Email, 'UTF-8');
        ?>
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border border-slate-100 dark:border-slate-700/50 flex items-center active:scale-95 transition-transform cari-item" data-search="<?= htmlspecialchars($searchString) ?>" onclick="location.href='?p=hesap-hareketleri&id=<?= $encId ?>'">
            <!-- Icon -->
            <div class="w-10 h-10 rounded-[10px] bg-[#f8fbff] dark:bg-slate-800 text-primary uppercase font-bold text-lg flex items-center justify-center shrink-0 border border-primary/10 dark:border-slate-700">
                <?= $initial ?>
            </div>
            
            <!-- Info -->
            <div class="ml-3 flex-1 min-w-0">
                <h4 class="font-semibold text-[13px] text-slate-900 dark:text-white truncate pb-0.5"><?= htmlspecialchars($cari->CariAdi) ?></h4>
                <div class="flex items-center text-[10px] text-slate-500 dark:text-slate-400 gap-1 truncate font-medium">
                    <span class="material-symbols-outlined text-[12px]">call</span>
                    <?= htmlspecialchars($cari->Telefon ?: '-') ?>
                </div>
            </div>
            
            <!-- Right Actions -->
            <div class="flex flex-col items-end shrink-0 pl-2">
                <span class="font-bold text-xs <?= $bakiyeColor ?>"><?= absMoneyCariTakip($bakiye) ?></span>
                <span class="text-[8px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mt-0.5"><?= $bakiyeLabel ?></span>
                <div class="mt-1 flex items-center gap-1">
                    <button type="button" class="w-7 h-7 bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-lg flex items-center justify-center active:bg-emerald-100" data-id="<?= $encId ?>" onclick="event.stopPropagation(); window.openHizliIslem('<?= $encId ?>');">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    </button>
                    <span class="w-6 h-6 flex items-center justify-center text-slate-300">
                        <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($cariler) == 0): ?>
            <div class="text-center py-8 text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
                <p class="text-sm">Henüz hesap kaydı bulunmuyor.</p>
            </div>
        <?php endif; ?>
        <div id="noResult" class="hidden text-center py-8 text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">search_off</span>
            <p class="text-sm">Sonuç bulunamadı.</p>
        </div>
    </div>

    <!-- FAB -->
    <button onclick="window.openCariModal()" class="fixed bottom-[80px] right-4 w-14 h-14 bg-primary text-white rounded-full shadow-lg shadow-primary/30 flex items-center justify-center z-40 active:scale-95 transition-transform border-0 focus:outline-none">
        <span class="material-symbols-outlined text-3xl">person_add</span>
    </button>
</div>

<!-- Modal Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/50 dark:bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300" onclick="window.closeModals()"></div>

<!-- Yeni Cari Ekle Modal -->
<div id="cariModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-2xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white dark:bg-card-dark z-10 shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">person_add</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Yeni Cari Ekle</h3>
                <p class="text-[10px] text-slate-500 hidden sm:block">Yeni kayıt oluşturmak için form doldurun</p>
            </div>
        </div>
        <button onclick="window.closeModals()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-4 overflow-y-auto">
        <form id="cariForm" onsubmit="window.submitCariForm(event)">
            <input type="hidden" name="action" value="cari-kaydet">
            <input type="hidden" name="cari_id" value="">
            
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Cari Adı*</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">person</span>
                        <input type="text" name="CariAdi" required class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Telefon</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">call</span>
                        <input type="tel" name="Telefon" class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">E-Posta</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">mail</span>
                        <input type="email" name="Email" class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Adres</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 material-symbols-outlined text-slate-400 text-lg">location_on</span>
                        <textarea name="Adres" rows="2" class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300"></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="cariSubmitBtn" class="w-full py-3 mt-6 bg-slate-900 border border-transparent dark:bg-primary dark:text-white dark:border-primary text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform mb-4">
                <span class="material-symbols-outlined text-lg">save</span> Kaydet
            </button>
        </form>
    </div>
</div>

<!-- Hızlı İşlem Modal -->
<div id="hizliIslemModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-2xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white dark:bg-card-dark z-10 shrink-0">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">swap_horiz</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Hızlı İşlem Ekle</h3>
                <p class="text-[10px] text-slate-500 hidden sm:block">İşlem türünü seçin ve tutarı girin</p>
            </div>
        </div>
        <button onclick="window.closeModals()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-4 overflow-y-auto">
        <form id="hizliIslemForm" onsubmit="window.submitHizliIslemForm(event)">
            <input type="hidden" name="action" value="hizli-hareket-kaydet">
            <input type="hidden" name="cari_id" id="hizli_islem_cari_id" value="">
            
            <!-- Type Toggle -->
            <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-4 gap-1">
                <label class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-emerald-600 has-[:checked]:shadow-sm transition-all focus-within:ring-2 focus-within:ring-emerald-500/20">
                    <input type="radio" name="type" value="aldim" class="hidden peer" checked>
                    <span class="flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-[16px]">add_circle</span> Tahsilat (Aldım)</span>
                </label>
                <label class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-rose-600 has-[:checked]:shadow-sm transition-all focus-within:ring-2 focus-within:ring-rose-500/20">
                    <input type="radio" name="type" value="verdim" class="hidden peer">
                    <span class="flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-[16px]">do_not_disturb_on</span> Ödeme (Verdim)</span>
                </label>
            </div>
            
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">İşlem Tarihi*</label>
                        <input type="datetime-local" name="islem_tarihi" value="<?= date('Y-m-d\TH:i') ?>" required class="w-full px-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Tutar (TL)*</label>
                        <div class="relative">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs pointer-events-none">₺</span>
                            <input type="number" step="0.01" min="0.01" name="tutar" placeholder="0.00" required class="w-full pl-3 pr-8 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm text-right font-bold tracking-wide">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Belge No</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[18px]">receipt</span>
                        <input type="text" name="belge_no" class="w-full pl-9 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Açıklama</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 material-symbols-outlined text-slate-400 text-[18px]">notes</span>
                        <textarea name="aciklama" rows="2" class="w-full pl-9 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300"></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="islemSubmitBtn" class="w-full py-3 mt-6 bg-slate-900 border border-transparent dark:bg-primary dark:text-white dark:border-primary text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform mb-4">
                <span class="material-symbols-outlined text-lg">save</span> Kaydet
            </button>
        </form>
    </div>
</div>

<script>
// Search Functionality
document.getElementById('cariSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    const items = document.querySelectorAll('.cari-item');
    let hasVisible = false;
    
    items.forEach(item => {
        const text = item.getAttribute('data-search');
        if (text.includes(term)) {
            item.style.display = 'flex';
            hasVisible = true;
        } else {
            item.style.display = 'none';
        }
    });
    
    document.getElementById('noResult').style.display = hasVisible || items.length === 0 ? 'none' : 'block';
});

// Modal Logic
window.openCariModal = function() {
    document.getElementById('cariForm').reset();
    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('cariModal').classList.remove('translate-y-full');
};

window.openHizliIslem = function(cariId) {
    document.getElementById('hizliIslemForm').reset();
    document.getElementById('hizli_islem_cari_id').value = cariId;
    
    // Set default datetime to current local time
    const now = new Date();
    const tzOffsetMs = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now.getTime() - tzOffsetMs)).toISOString().slice(0, 16);
    document.querySelector('input[name="islem_tarihi"]').value = localISOTime;
    
    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('hizliIslemModal').classList.remove('translate-y-full');
};

window.closeModals = function() {
    document.getElementById('modalOverlay').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('cariModal').classList.add('translate-y-full');
    document.getElementById('hizliIslemModal').classList.add('translate-y-full');
};

// Form submit helper generic
function handleApiSubmit(e, apiPath, btnId, defaultBtnHtml) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById(btnId);
    const formData = new FormData(form);
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch(apiPath, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success' || data.status === 'success_alert') {
            window.location.reload();
        } else {
            alert("Hata: " + (data.message || "Bir hata oluştu."));
            btn.disabled = false;
            btn.innerHTML = defaultBtnHtml;
        }
    })
    .catch(err => {
        alert("Sunucu ile bağlantı kurulamadı.");
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;
    });
}

window.submitCariForm = function(e) {
    handleApiSubmit(e, '../views/cari/api.php', 'cariSubmitBtn', '<span class=\"material-symbols-outlined text-lg\">save</span> Kaydet');
};

window.submitHizliIslemForm = function(e) {
    handleApiSubmit(e, '../views/cari/api.php', 'islemSubmitBtn', '<span class=\"material-symbols-outlined text-lg\">save</span> Kaydet');
};
</script>
