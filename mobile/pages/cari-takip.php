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
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Top. Aldım</p>
            <p class="font-bold text-rose-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyCariTakip($toplam_borc) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-emerald-500 text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-emerald-500 text-[22px] bg-emerald-50 dark:bg-emerald-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">trending_down</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide">Top. Verdim</p>
            <p class="font-bold text-emerald-600 text-xs sm:text-sm mt-0.5 truncate w-full"><?= formatMoneyCariTakip($toplam_alacak) ?></p>
        </div>
        
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 border-b-2 border-primary text-center flex flex-col justify-center items-center">
            <span class="material-symbols-outlined text-primary text-[22px] bg-blue-50 dark:bg-blue-900/20 w-8 h-8 rounded-full flex items-center justify-center mb-1">account_balance_wallet</span>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wide"><?= $genel_bakiye < 0 ? 'BENİM BORCUM' : ($genel_bakiye > 0 ? 'BENİM ALACAĞIM' : 'DURUM') ?></p>
            <p class="font-bold <?= $genel_bakiye < 0 ? 'text-rose-600' : ($genel_bakiye > 0 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300') ?> text-xs sm:text-sm mt-0.5 truncate w-full"><?= ($genel_bakiye < 0 ? '-' : '+') . absMoneyCariTakip($genel_bakiye) ?></p>
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
    <div class="space-y-3" id="cariList">
        <?php foreach ($cariler as $cari): 
            $bakiye = $cari->bakiye ?? 0;
            $bakiyeColor = $bakiye < 0 ? 'text-rose-600' : ($bakiye > 0 ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300');
            $bakiyeLabel = $bakiye < 0 ? 'BORÇLU' : ($bakiye > 0 ? 'ALACAKLI' : 'BAKİYE YOK');
            $initial = mb_strtoupper(mb_substr($cari->CariAdi, 0, 1, 'UTF-8'), 'UTF-8');
            $encId = Security::encrypt($cari->id);
            $searchString = mb_strtolower($cari->CariAdi . ' ' . $cari->firma . ' ' . $cari->Telefon . ' ' . $cari->Email, 'UTF-8');
        ?>
        <div class="relative cari-item-container swipe-container overflow-hidden rounded-xl shadow-sm">
            <!-- Delete Action (revealed on swipe right) -->
            <div class="absolute left-0 top-0 bottom-0 w-[70px] bg-rose-500 flex items-center justify-center text-white cursor-pointer swipe-action-right opacity-0 pointer-events-none transition-opacity duration-200" 
                 onclick="event.stopPropagation(); window.deleteCari('<?= $encId ?>', '<?= addslashes($cari->CariAdi) ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                    <span class="text-[9px] font-bold uppercase">Sil</span>
                </div>
            </div>

            <!-- Edit Action (revealed on swipe left) -->
            <div class="absolute right-0 top-0 bottom-0 w-[70px] bg-amber-500 flex items-center justify-center text-white cursor-pointer swipe-action-left opacity-0 pointer-events-none transition-opacity duration-200" 
                 onclick="event.stopPropagation(); window.editCari('<?= $encId ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                    <span class="text-[9px] font-bold uppercase">Düzenle</span>
                </div>
            </div>

            <div class="bg-white dark:bg-card-dark p-3 border border-slate-100 dark:border-slate-700/50 flex items-center transition-transform duration-200 swipe-content cari-item" 
                 data-search="<?= htmlspecialchars($searchString) ?>" 
                 onclick="if(Math.abs(parseInt(this.style.transform.replace(/[^\d-]/g, '') || 0)) > 10) { window.closeAllSwipes(); return; } location.href='?p=hesap-hareketleri&id=<?= $encId ?>'">
                <!-- Icon -->
                <div class="w-10 h-10 rounded-[10px] bg-[#f8fbff] dark:bg-slate-800 text-primary uppercase font-bold text-lg flex items-center justify-center shrink-0 border border-primary/10 dark:border-slate-700">
                    <?= $initial ?>
                </div>
                
                <!-- Info -->
                <div class="ml-3 flex-1 min-w-0">
                    <h4 class="font-semibold text-[13px] text-slate-900 dark:text-white truncate pb-0.5"><?= htmlspecialchars($cari->CariAdi) ?></h4>
                    <?php if(!empty($cari->firma)): ?>
                        <div class="text-[10px] text-slate-400 dark:text-slate-500 truncate mb-1"><?= htmlspecialchars($cari->firma) ?></div>
                    <?php endif; ?>
                    <div class="flex items-center text-[10px] text-slate-500 dark:text-slate-400 gap-1 truncate font-medium">
                        <span class="material-symbols-outlined text-[12px]">call</span>
                        <?= htmlspecialchars($cari->Telefon ?: '-') ?>
                    </div>
                </div>
                
                <!-- Right Actions -->
                <div class="flex flex-col items-end shrink-0 pl-2">
                    <span class="font-bold text-xs <?= $bakiyeColor ?>"><?= ($bakiye < 0 ? '-' : '+') . absMoneyCariTakip($bakiye) ?></span>
                    <div class="mt-1 flex items-center gap-1">
                        <button type="button" class="w-7 h-7 bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 rounded-lg flex items-center justify-center active:bg-emerald-100 shadow-sm border border-emerald-100/50 dark:border-emerald-500/10" data-id="<?= $encId ?>" onclick="event.stopPropagation(); window.openHizliIslem('<?= $encId ?>');">
                            <span class="material-symbols-outlined text-[18px]">add_circle</span>
                        </button>
                        <span class="w-6 h-6 flex items-center justify-center text-slate-300">
                            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                        </span>
                    </div>
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

    <!-- FABs -->
    <div class="fixed bottom-[80px] right-4 flex flex-col items-center gap-3 z-[60]">
        <button onclick="window.openTumHareketlerModal()" class="w-12 h-12 bg-slate-600 text-white rounded-full shadow-lg flex items-center justify-center active:scale-95 transition-transform border-0">
            <span class="material-symbols-outlined text-2xl">history</span>
        </button>
        <button onclick="window.openCariModal()" class="w-14 h-14 bg-primary text-white rounded-full shadow-lg shadow-primary/30 flex items-center justify-center active:scale-95 transition-transform border-0 focus:outline-none">
            <span class="material-symbols-outlined text-3xl">person_add</span>
        </button>
    </div>
</div>

<!-- Modal Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/50 dark:bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300 backdrop-blur-sm" onclick="window.closeModals()"></div>
<div id="modalOverlay2" class="fixed inset-0 bg-slate-900/40 dark:bg-black/40 z-[65] opacity-0 pointer-events-none transition-opacity duration-300 backdrop-blur-[2px]" onclick="window.closeHizliIslemModal()"></div>

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
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400">Cari Adı*</label>
                        <button type="button" onclick="window.selectContact()" id="contactPickerBtn" class="hidden flex items-center gap-1 text-primary text-[10px] bg-primary/5 px-2 py-1 rounded-full active:bg-primary/10 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">contact_page</span> Rehberden Seç
                        </button>
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">person</span>
                        <input type="text" name="CariAdi" required class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Firma / Ünvan</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">corporate_fare</span>
                        <input type="text" name="firma" class="w-full pl-10 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm placeholder-slate-300">
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
<div id="hizliIslemModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-2xl z-[70] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
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
        <button onclick="window.closeHizliIslemModal()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-4 overflow-y-auto">
        <form id="hizliIslemForm" onsubmit="window.submitHizliIslemForm(event)">
            <input type="hidden" name="action" value="hizli-hareket-kaydet">
            <input type="hidden" name="cari_id" id="hizli_islem_cari_id" value="">
            
            <!-- Type Toggle -->
            <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-4 gap-1">
                <label class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-rose-600 has-[:checked]:shadow-sm transition-all focus-within:ring-2 focus-within:ring-rose-500/20">
                    <input type="radio" name="type" value="aldim" class="hidden peer" checked>
                    <span class="flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-[16px]">remove_circle_outline</span> Aldım</span>
                </label>
                <label class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold cursor-pointer has-[:checked]:bg-white dark:has-[:checked]:bg-slate-700 has-[:checked]:text-emerald-600 has-[:checked]:shadow-sm transition-all focus-within:ring-2 focus-within:ring-emerald-500/20">
                    <input type="radio" name="type" value="verdim" class="hidden peer">
                    <span class="flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-[16px]">add_circle_outline</span> Verdim</span>
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

                <div>
                    <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Belge Yükle (Resim/PDF)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[18px]">upload_file</span>
                        <input type="file" name="dosya" accept="image/*,application/pdf" capture="environment" class="w-full pl-9 pr-3 py-2.5 bg-background-light dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm">
                    </div>
                </div>
            </div>
            
            <button type="submit" id="islemSubmitBtn" class="w-full py-3 mt-6 bg-slate-900 border border-transparent dark:bg-primary dark:text-white dark:border-primary text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform mb-4">
                <span class="material-symbols-outlined text-lg">save</span> Kaydet
            </button>
        </form>
    </div>
</div>

<!-- Tüm Hareketler Modal (Bottom Sheet) -->
<div id="tumHareketlerModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-hidden w-full max-w-lg mx-auto flex flex-col">
    <div class="px-4 pt-5 pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">history</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white text-xs" id="modalTitle">İşlem Geçmişi</h3>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.toggleModalFilter()" class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-primary flex items-center justify-center active:scale-90 transition-transform shadow-sm">
                   <span class="material-symbols-outlined text-lg">filter_list</span>
                </button>
                <a id="modalExportBtn" href="../views/cari/export-tum-hareketler-pdf.php" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center active:scale-90 transition-transform shadow-sm">
                   <span class="material-symbols-outlined text-lg">description</span>
                </a>
                <button onclick="window.closeModals()" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center active:scale-90 transition-transform shrink-0">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
        </div>
        
        <div id="modalFilterArea" class="hidden space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
             <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
                <input type="text" id="modalSearch" placeholder="Cari veya açıklama ara..." class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800 border-0 rounded-xl text-xs placeholder-slate-400 focus:ring-1 focus:ring-primary/20">
             </div>
             
             <div class="grid grid-cols-2 gap-2">
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[14px]">calendar_today</span>
                    <input type="date" id="modalDateStart" class="w-full pl-8 pr-2 py-2 bg-slate-50 dark:bg-slate-800 border-0 rounded-xl text-[10px] font-bold text-slate-600 dark:text-slate-300 focus:ring-1 focus:ring-primary/20">
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[14px]">calendar_today</span>
                    <input type="date" id="modalDateEnd" class="w-full pl-8 pr-2 py-2 bg-slate-50 dark:bg-slate-800 border-0 rounded-xl text-[10px] font-bold text-slate-600 dark:text-slate-300 focus:ring-1 focus:ring-primary/20">
                </div>
             </div>

             <div class="flex gap-2 p-1 bg-slate-50 dark:bg-slate-800 rounded-xl">
                 <button onclick="window.filterModal('all')" class="modal-tab flex-1 py-1.5 rounded-lg text-[10px] font-bold transition-all bg-white dark:bg-slate-700 shadow-sm text-primary" data-type="all">Tümü</button>
                 <button onclick="window.filterModal('aldim')" class="modal-tab flex-1 py-1.5 rounded-lg text-[10px] font-bold transition-all text-slate-500" data-type="aldim">Aldım</button>
                 <button onclick="window.filterModal('verdim')" class="modal-tab flex-1 py-1.5 rounded-lg text-[10px] font-bold transition-all text-slate-500" data-type="verdim">Verdim</button>
             </div>
        </div>
    </div>
    
    <div class="flex-1 overflow-y-auto p-4 space-y-2.5 bg-slate-50/50 dark:bg-slate-900/50 pb-20" id="modalHareketList">
        <div class="flex justify-center p-8">
            <div class="w-6 h-6 border-2 border-primary/30 border-t-primary rounded-full animate-spin"></div>
        </div>
    </div>
</div>

<!-- Hareket Detay Modal (Readonly View) -->
<div id="hareketDetayModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[75] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-hidden w-full max-w-lg mx-auto flex flex-col">
    <div class="px-5 py-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div id="detailIconBg" class="w-12 h-12 rounded-2xl flex items-center justify-center">
                    <span id="detailIcon" class="material-symbols-outlined text-2xl font-bold"></span>
                </div>
                <div>
                    <h3 id="detailTitle" class="font-bold text-slate-900 dark:text-white text-lg">İşlem Detayı</h3>
                    <p id="detailDateTime" class="text-xs text-slate-400 font-medium"></p>
                </div>
            </div>
            <button onclick="window.closeHareketDetayModal()" class="w-10 h-10 flex items-center justify-center text-slate-400 rounded-full bg-slate-50 dark:bg-slate-800 active:scale-95 transition-transform">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="p-6 bg-slate-50/50 dark:bg-slate-900/30 rounded-3xl border border-dashed border-slate-200 dark:border-slate-800 mb-8">
            <div class="text-center mb-6">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">İşlem Tutarı</p>
                <h2 id="detailAmount" class="text-3xl font-black"></h2>
            </div>
            
            <div class="w-full h-px bg-slate-100 dark:bg-slate-800 mb-6"></div>

            <div class="space-y-4">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-400">Hesap/Kişi</span>
                    <span id="detailAccount" class="font-bold text-slate-800 dark:text-white"></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-400">İşlem Tipi</span>
                    <span id="detailType" class="font-bold uppercase tracking-wider"></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="font-medium text-slate-400">Açıklama</span>
                    <span id="detailDesc" class="font-bold text-slate-800 dark:text-white text-right max-w-[60%]">-</span>
                </div>
                <div id="detailFileRow" class="flex items-center justify-between text-xs hidden">
                    <span class="font-medium text-slate-400">Belge</span>
                    <a id="detailFileLink" href="#" target="_blank" class="font-bold text-primary flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">visibility</span> Belgeyi Görüntüle
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <button onclick="window.editHareketFromDetail()" class="flex-1 py-4 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform border border-slate-200 dark:border-slate-700">
                <span class="material-symbols-outlined text-lg">edit</span> Düzenle
            </button>
            <button onclick="window.closeHareketDetayModal()" class="flex-1 py-4 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform border border-slate-200 dark:border-slate-700">
                <span class="material-symbols-outlined text-lg">close</span> Kapat
            </button>
        </div>
    </div>
</div>

<script>
// Swipe Functionality
(function() {
    let touchStartX = 0;
    let touchStartY = 0;
    let isMoving = false;

    window.closeAllSwipes = function() {
        document.querySelectorAll('.swipe-content').forEach(el => {
            el.style.transform = 'translateX(0)';
        });
        document.querySelectorAll('.swipe-action-right, .swipe-action-left').forEach(el => {
            el.style.opacity = '0';
            el.classList.add('pointer-events-none');
            el.classList.remove('pointer-events-auto');
        });
    };

    document.addEventListener('touchstart', e => {
        const container = e.target.closest('.swipe-container');
        if (!container) {
            window.closeAllSwipes();
            return;
        }
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isMoving = false;
    }, { passive: true });

    document.addEventListener('touchmove', e => {
        const container = e.target.closest('.swipe-container');
        if (!container) return;
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const diffX = currentX - touchStartX;
        const diffY = currentY - touchStartY;
        
        if (Math.abs(diffX) > Math.abs(diffY)) {
            isMoving = true;
        }
    }, { passive: true });

    document.addEventListener('touchend', e => {
        const container = e.target.closest('.swipe-container');
        if (!container) return;
        const touchEndX = e.changedTouches[0].clientX;
        const diffX = touchEndX - touchStartX;
        
        const swipeContent = container.querySelector('.swipe-content');
        const actionRight = container.querySelector('.swipe-action-right');
        const actionLeft = container.querySelector('.swipe-action-left');

        if (isMoving && diffX > 50) {
            // Swipe Right (Reveal Delete on Left)
            window.closeAllSwipes();
            swipeContent.style.transform = 'translateX(70px)';
            if (actionRight) {
                actionRight.style.opacity = '1';
                actionRight.classList.remove('pointer-events-none');
                actionRight.classList.add('pointer-events-auto');
            }
        } else if (isMoving && diffX < -50) {
            // Swipe Left (Reveal Edit on Right)
            window.closeAllSwipes();
            swipeContent.style.transform = 'translateX(-70px)';
            if (actionLeft) {
                actionLeft.style.opacity = '1';
                actionLeft.classList.remove('pointer-events-none');
                actionLeft.classList.add('pointer-events-auto');
            }
        } else if (isMoving && Math.abs(diffX) < 20) {
            // Cancel swipe if movement is small
            swipeContent.style.transform = 'translateX(0)';
            if (actionRight) {
                actionRight.style.opacity = '0';
                actionRight.classList.add('pointer-events-none');
                actionRight.classList.remove('pointer-events-auto');
            }
            if (actionLeft) {
                actionLeft.style.opacity = '0';
                actionLeft.classList.add('pointer-events-none');
                actionLeft.classList.remove('pointer-events-auto');
            }
        }
    }, { passive: true });
})();

window.editCari = function(id) {
    const formData = new FormData();
    formData.append('action', 'cari-getir');
    formData.append('cari_id', id);

    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data) {
            const form = document.getElementById('cariForm');
            form.querySelector('input[name="cari_id"]').value = id;
            form.querySelector('input[name="CariAdi"]').value = data.CariAdi || '';
            form.querySelector('input[name="firma"]').value = data.firma || '';
            form.querySelector('input[name="Telefon"]').value = data.Telefon || '';
            form.querySelector('input[name="Email"]').value = data.Email || '';
            form.querySelector('textarea[name="Adres"]').value = data.Adres || '';
            
            document.querySelector('#cariModal h3').innerText = 'Cari Düzenle';
            document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
            document.getElementById('cariModal').classList.remove('translate-y-full');
            window.closeAllSwipes();
        }
    });
};

window.deleteCari = async function(id, name) {
    const result = await Swal.fire({
        title: 'Cari Silinecek',
        html: `<div class="mb-4 text-center"><strong>${name}</strong> kaydını silmek istediğinize emin misiniz?</div>` +
              `<div class="flex items-center justify-center p-3 bg-red-50 dark:bg-red-900/10 rounded-xl border border-red-100 dark:border-red-900/20">` +
              `<input type="checkbox" id="deleteMovements" class="w-4 h-4 text-rose-600 rounded focus:ring-rose-500 border-rose-300">` +
              `<label for="deleteMovements" class="ml-2 text-sm font-medium text-rose-700 dark:text-rose-400 cursor-pointer">Hesap hareketlerini de sil</label>` +
              `</div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, Sil',
        cancelButtonText: 'Vazgeç',
        buttonsStyling: false,
        customClass: {
            popup: "swal-custom-popup",
            title: "swal-custom-title",
            htmlContainer: "swal-custom-content",
            actions: "swal-custom-actions swal-actions-two",
            confirmButton: "swal-custom-confirm swal-confirm-danger",
            cancelButton: "swal-custom-cancel",
            icon: "swal-custom-icon swal-icon-warning",
        }
    });

    if (result.isConfirmed) {
        const deleteMovements = document.getElementById('deleteMovements').checked;
        const formData = new FormData();
        formData.append('action', 'cari-sil');
        formData.append('cari_id', id);
        if (deleteMovements) formData.append('delete_movements', '1');

        fetch('../views/cari/api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                window.location.reload();
            } else {
                Alert.error("Hata", data.message);
            }
        })
        .catch(() => Alert.error("Hata", "Sunucu ile bağlantı kurulamadı."));
    }
};

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
    const form = document.getElementById('hizliIslemForm');
    form.reset();
    const existingFile = form.querySelector('.existing-file');
    if (existingFile) existingFile.remove();
    document.getElementById('hizli_islem_cari_id').value = cariId;
    
    // Set default datetime to current local time
    const now = new Date();
    const tzOffsetMs = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now.getTime() - tzOffsetMs)).toISOString().slice(0, 16);
    document.querySelector('input[name="islem_tarihi"]').value = localISOTime;
    
    document.getElementById('modalOverlay2').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('hizliIslemModal').classList.remove('translate-y-full');
};

window.closeModals = function() {
    document.getElementById('modalOverlay').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('cariModal').classList.add('translate-y-full');
    document.getElementById('tumHareketlerModal').classList.add('translate-y-full');
};

window.closeHizliIslemModal = function() {
    document.getElementById('modalOverlay2').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('hizliIslemModal').classList.add('translate-y-full');
};

window.closeHareketDetayModal = function() {
    document.getElementById('modalOverlay2').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('hareketDetayModal').classList.add('translate-y-full');
};

// Para Formatlama JS
window.formatMoneyCariTakip = function(amount) {
    if (amount === undefined || amount === null) return '0,00 ₺';
    return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount) + ' ₺';
};

// Tum Hareketler Modal Logic
let modalFilter = { search: '', type: 'all', baslangic: '', bitis: '' };
let modalSearchTimeout;
window.currentCariId = '';

window.openTumHareketlerModal = function(cariId = '', cariName = '') {
    window.currentCariId = cariId;
    
    // UI Güncelleme (Başlık ve Export Butonu)
    const title = document.getElementById('modalTitle');
    const exportBtn = document.getElementById('modalExportBtn');
    
    if (cariName) {
        title.innerText = cariName;
        exportBtn.href = `../views/cari/export-ekstre-pdf.php?id=${cariId}`;
    } else {
        title.innerText = 'İşlem Geçmişi';
        exportBtn.href = '../views/cari/export-tum-hareketler-pdf.php';
    }

    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('tumHareketlerModal').classList.remove('translate-y-full');
    document.getElementById('modalFilterArea').classList.add('hidden'); // Her açılışta kapalı başlasın
    window.loadModalHareketler();
};

window.toggleModalFilter = function() {
    const area = document.getElementById('modalFilterArea');
    area.classList.toggle('hidden');
};


window.loadModalHareketler = function() {
    const list = document.getElementById('modalHareketList');
    const formData = new FormData();
    formData.append('action', 'tum-hareketler-getir');
    formData.append('search', modalFilter.search);
    formData.append('type', modalFilter.type);
    formData.append('baslangic', modalFilter.baslangic);
    formData.append('bitis', modalFilter.bitis);
    formData.append('cari_id', window.currentCariId);

    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (!Array.isArray(data)) {
            list.innerHTML = `<div class="text-center py-10 text-slate-400 text-xs">Bir hata oluştu.</div>`;
            return;
        }
        if (data.length === 0) {
            list.innerHTML = `<div class="text-center py-10 text-slate-400 text-xs">Kayıt bulunamadı.</div>`;
            return;
        }
        
        list.innerHTML = data.map(h => {
            const yBakiye = h.yuruyen;
            const yColor = yBakiye < 0 ? 'text-rose-500' : (yBakiye > 0 ? 'text-emerald-500' : 'text-slate-400');
            const ySign = yBakiye < 0 ? '(B)' : (yBakiye > 0 ? '(A)' : '');

            // Tarih parçalarını ayır (h.tarih: dd.mm.yyyy hh:mm)
            const dateParts = h.tarih.split(' ');
            const fullDate = dateParts[0]; 
            const timePart = dateParts[1];

            return `
            <div class="relative swipe-container overflow-hidden rounded-xl shadow-sm">
                <!-- Delete Action -->
                <div class="absolute left-0 top-0 bottom-0 w-[70px] bg-rose-500 flex items-center justify-center text-white cursor-pointer swipe-action-right opacity-0 pointer-events-none transition-opacity duration-200" 
                     onclick="event.stopPropagation(); window.deleteHareket('${h.id}')">
                    <div class="flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                        <span class="text-[9px] font-bold uppercase">Sil</span>
                    </div>
                </div>

                <!-- Edit Action -->
                <div class="absolute right-0 top-0 bottom-0 w-[70px] bg-amber-500 flex items-center justify-center text-white cursor-pointer swipe-action-left opacity-0 pointer-events-none transition-opacity duration-200" 
                     onclick="event.stopPropagation(); window.editHareket('${h.id}')">
                    <div class="flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-[20px]">edit</span>
                        <span class="text-[9px] font-bold uppercase">Düzelt</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-3 border border-slate-100 dark:border-slate-700/50 flex items-center justify-between shadow-sm swipe-content transition-transform duration-200 cursor-pointer active:bg-slate-50 dark:active:bg-slate-700"
                     onclick="if(Math.abs(parseInt(this.style.transform.replace(/[^\d-]/g, '') || 0)) > 10) { window.closeAllSwipes(); return; } window.viewHareketDetay('${h.id}')">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-11 h-11 rounded-xl flex flex-col items-center justify-center shrink-0 bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 leading-none gap-1">
                            <span class="text-[9px] font-black text-primary dark:text-blue-400">${fullDate}</span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase">${timePart}</span>
                        </div>
                        <div class="min-w-0">
                            <h4 class="font-bold text-[12px] text-slate-800 dark:text-white truncate leading-tight">${h.CariAdi}</h4>
                            <div class="flex items-center gap-1 mt-1 text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                                <span class="material-symbols-outlined text-[13px] shrink-0">notes</span>
                                <span class="truncate">${h.aciklama || (h.is_borc ? 'Aldım' : 'Verdim')}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-2 flex flex-col items-end">
                        <p class="font-bold text-xs ${h.is_borc ? 'text-rose-600' : 'text-emerald-600'}">
                            ${h.is_borc ? '-' : '+'}${window.formatMoneyCariTakip(h.amt)}
                        </p>
                        <p class="text-[9px] font-bold ${yColor} mt-0.5 opacity-80">
                            ${window.formatMoneyCariTakip(Math.abs(yBakiye))} ${ySign}
                        </p>
                        <div class="flex items-center gap-1 mt-1">
                            ${h.dosya ? `<span class="material-symbols-outlined text-[14px] text-primary">attachment</span>` : ''}
                            ${h.belge_no ? `<span class="bg-slate-50 dark:bg-slate-700 px-1 rounded text-[8px] text-slate-400 font-bold uppercase tracking-tighter">#${h.belge_no}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    });
};

window.filterModal = function(type) {
    modalFilter.type = type;
    document.querySelectorAll('.modal-tab').forEach(btn => {
        if (btn.getAttribute('data-type') === type) {
            btn.classList.add('bg-white', 'dark:bg-slate-700', 'shadow-sm', 'text-primary');
            btn.classList.remove('text-slate-500');
        } else {
            btn.classList.remove('bg-white', 'dark:bg-slate-700', 'shadow-sm', 'text-primary');
            btn.classList.add('text-slate-500');
        }
    });
    window.loadModalHareketler();
};

document.getElementById('modalSearch').addEventListener('input', function() {
    clearTimeout(modalSearchTimeout);
    modalSearchTimeout = setTimeout(() => {
        modalFilter.search = this.value;
        window.loadModalHareketler();
    }, 400);
});

document.addEventListener('DOMContentLoaded', () => {
    const dStart = document.getElementById('modalDateStart');
    const dEnd = document.getElementById('modalDateEnd');
    if(dStart) dStart.addEventListener('change', (e) => { modalFilter.baslangic = e.target.value; window.loadModalHareketler(); });
    if(dEnd) dEnd.addEventListener('change', (e) => { modalFilter.bitis = e.target.value; window.loadModalHareketler(); });
});

window.editHareket = function(id) {
    const formData = new FormData();
    formData.append('action', 'hareket-getir');
    formData.append('hareket_id', id);

    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data) {
            const form = document.getElementById('hizliIslemForm');
            // Backendden gelen data.cari_id_enc kullanıyoruz (Security nesnesi JS'de yok)
            form.querySelector('input[name="cari_id"]').value = data.cari_id_enc; 
            
            const typeRadio = form.querySelector(`input[name="type"][value="${data.type}"]`);
            if (typeRadio) typeRadio.checked = true;
            
            const dtParts = data.islem_tarihi.split(' ');
            const datePart = dtParts[0].split('.').reverse().join('-');
            form.querySelector('input[name="islem_tarihi"]').value = `${datePart}T${dtParts[1]}`;
            form.querySelector('input[name="tutar"]').value = data.tutar_raw;
            form.querySelector('input[name="belge_no"]').value = data.belge_no || '';
            form.querySelector('textarea[name="aciklama"]').value = data.aciklama || '';
            
            let hInput = form.querySelector('input[name="hareket_id"]');
            if (!hInput) {
                hInput = document.createElement('input');
                hInput.type = 'hidden';
                hInput.name = 'hareket_id';
                form.appendChild(hInput);
            }
            hInput.value = id;

            const existingFile = form.querySelector('.existing-file');
            if (existingFile) existingFile.remove();
            if (data.dosya) {
                const div = document.createElement('div');
                div.className = 'existing-file mt-2 p-2 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-100 dark:border-slate-700 flex items-center justify-between';
                div.innerHTML = `<span class="text-[10px] font-bold text-slate-500">Mevcut Belge:</span> <a href="uploads/cari_belgeler/${data.dosya}" target="_blank" class="text-[10px] font-bold text-primary flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">visibility</span> Görüntüle</a>`;
                form.querySelector('input[name="dosya"]').parentElement.parentElement.appendChild(div);
            }

            window.closeAllSwipes();
            document.getElementById('modalOverlay2').classList.remove('pointer-events-none', 'opacity-0');
            document.getElementById('hizliIslemModal').classList.remove('translate-y-full');
        }
    });
};

window.deleteHareket = async function(id) {
    const result = await Swal.fire({
        title: 'İşlem Silinecek',
        text: "Bu hareketi silmek istediğinize emin misiniz?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, Sil',
        cancelButtonText: 'Vazgeç',
        buttonsStyling: false,
        customClass: {
            popup: "swal-custom-popup",
            title: "swal-custom-title",
            actions: "swal-custom-actions swal-actions-two",
            confirmButton: "swal-custom-confirm swal-confirm-danger",
            cancelButton: "swal-custom-cancel",
            icon: "swal-custom-icon swal-icon-warning",
        }
    });

    if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'hareket-sil');
        formData.append('hareket_id', id);

        fetch('../views/cari/api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Toast.show(data.message, "success");
                window.loadModalHareketler();
                window.closeAllSwipes();
            } else {
                Toast.show(data.message, "error");
            }
        });
    }
};

window.viewHareketDetay = function(id) {
    window.currentViewingHareketId = id;
    const formData = new FormData();
    formData.append('action', 'hareket-getir');
    formData.append('hareket_id', id);

    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data) {
            // Fill Detail Modal
            const amountEl = document.getElementById('detailAmount');
            const typeEl = document.getElementById('detailType');
            const iconBg = document.getElementById('detailIconBg');
            const icon = document.getElementById('detailIcon');
            
            const isBorc = data.type === 'aldim';
            amountEl.innerText = (isBorc ? '-₺' : '+₺') + window.formatMoneyCariTakip(data.tutar_raw).replace(' ₺', '');
            amountEl.className = `text-3xl font-black ${isBorc ? 'text-rose-600' : 'text-emerald-600'}`;
            
            typeEl.innerText = isBorc ? 'GİDER' : 'GELİR';
            typeEl.className = `font-bold uppercase tracking-wider ${isBorc ? 'text-rose-600' : 'text-emerald-600'}`;
            
            iconBg.className = `w-12 h-12 rounded-2xl flex items-center justify-center ${isBorc ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600'}`;
            icon.innerText = isBorc ? 'remove_circle' : 'add_circle';
            
            document.getElementById('detailTitle').innerText = data.belge_no ? 'İşlem #' + data.belge_no : 'İşlem Detayı';
            document.getElementById('detailDateTime').innerText = data.islem_tarihi;
            document.getElementById('detailAccount').innerText = data.CariAdi || '-';
            document.getElementById('detailDesc').innerText = data.aciklama || '-';

            const fileRow = document.getElementById('detailFileRow');
            const fileLink = document.getElementById('detailFileLink');
            if (data.dosya) {
                fileRow.classList.remove('hidden');
                fileLink.href = 'uploads/cari_belgeler/' + data.dosya;
            } else {
                fileRow.classList.add('hidden');
            }

            window.closeAllSwipes();
            document.getElementById('modalOverlay2').classList.remove('pointer-events-none', 'opacity-0');
            document.getElementById('hareketDetayModal').classList.remove('translate-y-full');
        }
    });
};

window.editHareketFromDetail = function() {
    const id = window.currentViewingHareketId;
    window.closeHareketDetayModal();
    setTimeout(() => {
        window.editHareket(id);
    }, 300);
};

// Global click to close swipes
document.addEventListener('mousedown', (e) => {
    if (!e.target.closest('.swipe-container')) {
        window.closeAllSwipes();
    }
});



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
            Toast.show(data.message || "İşlem başarılı", "success");
            setTimeout(() => window.location.reload(), 1000);
        } else {
            Toast.show(data.message || "Bir hata oluştu.", "error");
            btn.disabled = false;
            btn.innerHTML = defaultBtnHtml;
        }
    })
    .catch(err => {
        Toast.show("Sunucu ile bağlantı kurulamadı.", "error");
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

/**
 * Contact Picker API integration
 */
window.selectContact = async function() {
    const isSupported = 'contacts' in navigator && 'select' in navigator.contacts;
    if (!isSupported) {
        return;
    }

    const props = ['name', 'tel'];
    const opts = { multiple: false };

    try {
        const contacts = await navigator.contacts.select(props, opts);
        if (contacts.length > 0) {
            const contact = contacts[0];
            const name = contact.name && contact.name[0] ? contact.name[0] : '';
            const tel = contact.tel && contact.tel[0] ? contact.tel[0] : '';
            
            const form = document.getElementById('cariForm');
            if (name) {
                // Remove any leading/trailing whitespace
                form.querySelector('input[name="CariAdi"]').value = name.trim();
            }
            if (tel) {
                // Clean phone number (keep only digits and +)
                const cleanTel = tel.replace(/[^\d+]/g, '');
                form.querySelector('input[name="Telefon"]').value = cleanTel;
            }
        }
    } catch (ex) {
        console.warn('Contact selection cancelled or failed:', ex);
    }
};

// Initial support check for Contact Picker
document.addEventListener('DOMContentLoaded', () => {
    const isSupported = 'contacts' in navigator && 'select' in navigator.contacts;
    if (isSupported) {
        const btn = document.getElementById('contactPickerBtn');
        if (btn) btn.classList.remove('hidden');
    }
});
</script>
