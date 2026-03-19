<?php
use App\Helper\Security;
use App\Model\CariModel;
use App\Model\CariHareketleriModel;

$cari_id_enc = $_GET['id'] ?? '';
$cari_id = Security::decrypt($cari_id_enc);

$Cari = new CariModel();
$cariData = $Cari->find($cari_id);

if (!$cariData) {
    echo '<div class="p-4 text-center mt-10"><span class="material-symbols-outlined text-4xl text-slate-400 mb-2">error</span><p class="text-slate-500 font-semibold">Cari bulunamadı!</p><a href="?p=cari-takip" class="mt-4 inline-block px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold">Geri Dön</a></div>';
    return;
}

$db = $Cari->getDb();

// Özet Bilgiler
$stmt = $db->prepare("SELECT SUM(borc) as toplam_borc, SUM(alacak) as toplam_alacak, SUM(alacak - borc) as bakiye FROM cari_hareketleri WHERE cari_id = :cari_id AND silinme_tarihi IS NULL");
$stmt->execute(['cari_id' => $cari_id]);
$ozet = $stmt->fetch(PDO::FETCH_OBJ);
$toplam_borc = $ozet->toplam_borc ?? 0;
$toplam_alacak = $ozet->toplam_alacak ?? 0;
$bakiye = $ozet->bakiye ?? 0;

// Hareketler
$sql = "SELECT h.*, 
        (SELECT ROUND(SUM(alacak - borc), 2) FROM cari_hareketleri 
         WHERE cari_id = :cari_id AND silinme_tarihi IS NULL 
           AND (islem_tarihi < h.islem_tarihi OR (islem_tarihi = h.islem_tarihi AND id <= h.id))) as yuruyen_bakiye
        FROM cari_hareketleri h
        WHERE h.cari_id = :cari_id AND h.silinme_tarihi IS NULL
        ORDER BY h.islem_tarihi DESC, h.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute(['cari_id' => $cari_id]);
$hareketler = $stmt->fetchAll(PDO::FETCH_OBJ);

if (!function_exists('formatMoneyCariTakip')) {
    function formatMoneyCariTakip($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' ₺';
    }
    function absMoneyCariTakip($amount) {
        return number_format(abs((float)$amount), 2, ',', '.') . ' ₺';
    }
}
?>

<div class="px-3 py-4 space-y-4 pb-28">
    
    <!-- Top Nav / Header Card -->
    <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-3 flex flex-col gap-3 relative">
        <a href="?p=cari-takip" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center active:scale-95">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </a>
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0 uppercase font-bold text-xl border border-primary/20">
                <?= mb_strtoupper(mb_substr($cariData->CariAdi, 0, 1, 'UTF-8'), 'UTF-8') ?>
            </div>
            <div class="pr-8 min-w-0">
                <h2 class="font-bold text-slate-900 dark:text-white leading-tight mb-1 text-sm truncate"><?= htmlspecialchars($cariData->CariAdi) ?></h2>
                <div class="flex items-center gap-2 text-[11px] text-slate-500 font-medium truncate">
                    <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[13px]">call</span> <?= htmlspecialchars($cariData->Telefon ?: '-') ?></span>
                </div>
            </div>
        </div>
        
        <!-- Summary Mini Cards -->
        <div class="grid grid-cols-3 gap-2 mt-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <div class="text-center bg-rose-50/50 dark:bg-rose-900/10 rounded-lg py-2 flex flex-col items-center justify-center">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5">Top. Aldım</p>
                <p class="font-bold text-rose-600 text-xs sm:text-sm truncate px-1 w-full"><?= formatMoneyCariTakip($toplam_borc) ?></p>
            </div>
            <div class="text-center bg-emerald-50/50 dark:bg-emerald-900/10 rounded-lg py-2 flex flex-col items-center justify-center">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5">Top. Verdim</p>
                <p class="font-bold text-emerald-600 text-xs sm:text-sm truncate px-1 w-full"><?= formatMoneyCariTakip($toplam_alacak) ?></p>
            </div>
            <div class="text-center <?= $bakiye < 0 ? 'bg-rose-50/50 dark:bg-rose-900/10' : ($bakiye > 0 ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : 'bg-slate-50/50 dark:bg-slate-800') ?> rounded-lg py-2 flex flex-col items-center justify-center">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-0.5"><?= $bakiye < 0 ? 'BENİM BORCUM' : ($bakiye > 0 ? 'BENİM ALACAĞIM' : 'DURUM') ?></p>
                <p class="font-bold <?= $bakiye < 0 ? 'text-rose-600' : ($bakiye > 0 ? 'text-emerald-600' : 'text-slate-600') ?> text-xs sm:text-sm truncate px-1 w-full"><?= ($bakiye < 0 ? '-' : '+') . absMoneyCariTakip($bakiye) ?></p>
            </div>
        </div>

        <?php if($cariData->notlar): ?>
        <div class="mt-2 p-2 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/20 rounded-lg">
            <div class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 mb-0.5">
                <span class="material-symbols-outlined text-[14px]">sticky_note_2</span>
                <span class="text-[10px] font-bold uppercase tracking-wider">Cari Notu</span>
            </div>
            <p class="text-[11px] text-slate-700 dark:text-slate-300 leading-tight italic"><?= nl2br(htmlspecialchars($cariData->notlar)) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Hızlı Aksiyonlar -->
    <div class="flex items-center justify-around bg-white dark:bg-card-dark rounded-xl shadow-sm p-2 mb-2">
        <a onclick="window.editCariNote()" class="flex flex-col items-center gap-1.5 p-2 rounded-lg active:bg-slate-50 dark:active:bg-slate-800 active:scale-95 transition-all text-slate-600 dark:text-slate-300 w-1/4 cursor-pointer">
            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">sticky_note_2</span>
            </div>
            <span class="text-[10px] font-semibold text-center leading-tight">Cari Notu</span>
        </a>
        <a href="tel:<?= htmlspecialchars($cariData->Telefon) ?>" class="flex flex-col items-center gap-1.5 p-2 rounded-lg active:bg-slate-50 dark:active:bg-slate-800 active:scale-95 transition-all text-slate-600 dark:text-slate-300 w-1/4">
            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">call</span>
            </div>
            <span class="text-[10px] font-semibold text-center leading-tight">Ara</span>
        </a>
        <a onclick="window.shareCari()" class="flex flex-col items-center gap-1.5 p-2 rounded-lg active:bg-slate-50 dark:active:bg-slate-800 active:scale-95 transition-all text-slate-600 dark:text-slate-300 w-1/4 cursor-pointer">
            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">share</span>
            </div>
            <span class="text-[10px] font-semibold text-center leading-tight">Paylaş</span>
        </a>
        <a onclick="window.exportEkstre()" class="flex flex-col items-center gap-1.5 p-2 rounded-lg active:bg-slate-50 dark:active:bg-slate-800 active:scale-95 transition-all text-slate-600 dark:text-slate-300 w-1/4 cursor-pointer">
            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">description</span>
            </div>
            <span class="text-[10px] font-semibold text-center leading-tight">Ekstre</span>
        </a>
    </div>

    <!-- Title and Count -->
    <div class="flex items-center justify-between px-1">
        <h3 class="font-bold text-slate-800 dark:text-white text-sm">Hesap Hareketleri</h3>
        <span class="bg-primary/10 text-primary px-2 py-0.5 rounded-full text-[10px] font-bold"><?= count($hareketler) ?> İşlem</span>
    </div>

    <!-- Hareket Listesi -->
    <div class="space-y-2">
        <?php foreach ($hareketler as $h): 
            $isBorc = $h->borc > 0;
            $amt = $isBorc ? $h->borc : $h->alacak;
            $icon = $isBorc ? 'remove_circle_outline' : 'add_circle_outline'; 
            $iconColor = $isBorc ? 'text-rose-500 bg-rose-50 dark:bg-rose-900/30' : 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30';
            $dateFormatted = date('d.m.Y', strtotime($h->islem_tarihi));
            $timeFormatted = date('H:i', strtotime($h->islem_tarihi));
            
            $currentBakiye = $h->yuruyen_bakiye ?? ($h->alacak - $h->borc);
            $cbColor = $currentBakiye < 0 ? 'text-rose-600' : ($currentBakiye > 0 ? 'text-emerald-600' : 'text-slate-500');
        ?>
        <div class="relative movement-item-container overflow-hidden rounded-xl shadow-sm">
            <!-- Delete Action (Reveal on swipe right) -->
            <div class="absolute left-0 top-0 bottom-0 w-[70px] bg-rose-500 flex items-center justify-center text-white cursor-pointer swipe-action-right opacity-0 pointer-events-none transition-opacity duration-200" 
                 onclick="event.stopPropagation(); window.deleteHareket('<?= Security::encrypt($h->id) ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                    <span class="text-[9px] font-bold uppercase">Sil</span>
                </div>
            </div>

            <!-- Edit Action (Reveal on swipe left) -->
            <div class="absolute right-0 top-0 bottom-0 w-[70px] bg-amber-500 flex items-center justify-center text-white cursor-pointer swipe-action-left opacity-0 pointer-events-none transition-opacity duration-200" 
                 onclick="event.stopPropagation(); window.editHareket('<?= Security::encrypt($h->id) ?>')">
                <div class="flex flex-col items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">edit</span>
                    <span class="text-[9px] font-bold uppercase">Düzenle</span>
                </div>
            </div>

            <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 p-3 flex items-center justify-between transition-transform duration-200 swipe-content" 
                 onclick="if(Math.abs(parseInt(this.style.transform.replace(/[^\d-]/g, '') || 0)) > 10) { window.closeAllSwipes(); return; } window.showMovementReceipt({
                     type: '<?= $isBorc ? 'Aldım' : 'Verdim' ?>',
                     amount: '<?= ($isBorc ? '-' : '+') . absMoneyCariTakip($amt) ?>',
                     date: '<?= $dateFormatted ?>',
                     time: '<?= $timeFormatted ?>',
                     belge_no: '<?= htmlspecialchars($h->belge_no ?: "-") ?>',
                     aciklama: '<?= htmlspecialchars(addslashes($h->aciklama ?: "-")) ?>',
                     cari_adi: '<?= htmlspecialchars(addslashes($cariData->CariAdi)) ?>',
                     is_borc: <?= $isBorc ? 'true' : 'false' ?>
                 })">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-[10px] flex items-center justify-center shrink-0 <?= $iconColor ?>">
                        <span class="material-symbols-outlined text-[22px]"><?= $icon ?></span>
                    </div>
                    <div>
                        <p class="font-bold text-[12px] text-slate-800 dark:text-slate-300 leading-tight mb-0.5"><?= $isBorc ? 'Aldım' : 'Verdim' ?></p>
                        <div class="flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400 font-medium">
                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[11px]">event</span> <?= $dateFormatted ?> <?= $timeFormatted ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-col items-end pl-2">
                    <p class="font-bold text-sm <?= $isBorc ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                        <?= $isBorc ? '-' : '+' ?><?= absMoneyCariTakip($amt) ?>
                    </p>
                    <div class="mt-0.5 flex items-center text-[9px] uppercase tracking-wide">
                        <span class="text-slate-400 mr-1">Bakiye:</span>
                        <span class="font-bold <?= $cbColor ?>"><?= ($currentBakiye < 0 ? '-' : '+') . absMoneyCariTakip($currentBakiye) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($hareketler) == 0): ?>
            <div class="text-center py-10 text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 opacity-30">receipt_long</span>
                <p class="text-xs font-semibold">Henüz hiç hesap hareketi bulunmuyor.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Fixed Actions Bar inside page to override bottom nav -->
    <div class="fixed bottom-[70px] left-0 right-0 px-4 py-3 bg-white/90 dark:bg-card-dark/90 backdrop-blur-md border-t border-slate-100 dark:border-slate-800 flex items-center gap-3 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)] z-40 safe-area-bottom pb-nav">
        <button onclick="window.openHizliIslem('<?= $cari_id_enc ?>', 'aldim')" class="flex-1 py-3 px-2 bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 rounded-xl font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm">
            <span class="material-symbols-outlined text-[20px]">remove_circle_outline</span>
            <span class="text-sm">Aldım</span>
        </button>
        <button onclick="window.openHizliIslem('<?= $cari_id_enc ?>', 'verdim')" class="flex-1 py-3 px-2 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 rounded-xl font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-sm">
            <span class="material-symbols-outlined text-[20px]">add_circle_outline</span>
            <span class="text-sm">Verdim</span>
        </button>
    </div>
</div>

<style>
/* Remove margin bottom for nav element if present on this page */
.pb-nav { padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px)) !important; }
</style>

<!-- Modal Overlay -->
<div id="modalOverlay" class="fixed inset-0 bg-slate-900/50 dark:bg-black/60 z-[60] opacity-0 pointer-events-none transition-opacity duration-300" onclick="window.closeModals()"></div>

<!-- Hızlı İşlem Modal -->
<div id="hizliIslemModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
    <div class="px-4 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-sm z-10 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                <span id="modalIcon" class="material-symbols-outlined text-xl">swap_horiz</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">İşlem Ekle</h3>
                <p class="text-[10px] text-slate-500 font-medium tracking-wide" id="modalDesc">İşlem tutarını ve detayları girin</p>
            </div>
        </div>
        <button onclick="window.closeModals()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-4 overflow-y-auto pb-10">
        <form id="hizliIslemForm" onsubmit="window.submitHizliIslemForm(event)">
            <input type="hidden" name="action" value="hizli-hareket-kaydet">
            <input type="hidden" name="cari_id" id="hizli_islem_cari_id" value="">
            <input type="hidden" name="hareket_id" id="hizli_islem_hareket_id" value="">
            <input type="hidden" name="type" id="hidden_type" value="aldim">
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1">İşlem Tarihi</label>
                        <input type="datetime-local" id="field_islem_tarihi" name="islem_tarihi" value="<?= date('Y-m-d\TH:i') ?>" required class="w-full px-3 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm font-semibold">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1">Tutar (TL)</label>
                        <div class="relative flex items-center">
                            <input type="number" step="0.01" min="0.01" id="field_tutar" name="tutar" placeholder="0.00" required class="w-full pl-4 pr-8 py-3 bg-white dark:bg-background-dark border-2 border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-primary/20 text-lg text-right font-black text-slate-800 dark:text-white shadow-sm">
                            <span class="absolute right-4 text-slate-400 font-black text-sm pointer-events-none">₺</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1">Evrak / Belge No</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-[18px]">receipt_long</span>
                        <input type="text" id="field_belge_no" name="belge_no" placeholder="Örn: 0045" class="w-full pl-9 pr-3 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm font-semibold placeholder-slate-300">
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1">Açıklama</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 material-symbols-outlined text-slate-400 text-[18px]">notes</span>
                        <textarea id="field_aciklama" name="aciklama" rows="2" placeholder="Not ekleyin..." class="w-full pl-9 pr-3 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-primary focus:ring-1 focus:ring-primary/20 text-sm font-semibold placeholder-slate-300"></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" id="islemSubmitBtn" class="w-full py-4 mt-8 bg-slate-900 dark:bg-primary text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-slate-900/20 dark:shadow-primary/20 text-base">
                <span class="material-symbols-outlined text-[20px]">task_alt</span> İşlemi Onayla
            </button>
        </form>
    </div>
</div>

<!-- Cari Notu Modal -->
<div id="cariNotuModal" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[61] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] overflow-y-auto w-full max-w-lg mx-auto flex flex-col">
    <div class="px-4 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-sm z-10 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-xl">sticky_note_2</span>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Cari Notu</h3>
                <p class="text-[10px] text-slate-500 font-medium tracking-wide">Müşteri hakkındaki genel notlarınız</p>
            </div>
        </div>
        <button onclick="window.closeModals()" class="w-8 h-8 flex items-center justify-center text-slate-400 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform shrink-0">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
    
    <div class="p-4">
        <form id="cariNotuForm" onsubmit="window.submitCariNotu(event)">
            <input type="hidden" name="action" value="cari-not-kaydet">
            <input type="hidden" name="cari_id" value="<?= $cari_id_enc ?>">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-2">Not Detayı</label>
                    <textarea name="notlar" rows="6" placeholder="Örn: Ödemelerini genellikle ayın 15inde yapar..." class="w-full px-4 py-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-amber-500 focus:ring-1 focus:ring-amber-500/20 text-sm font-medium placeholder-slate-300"><?= htmlspecialchars($cariData->notlar ?: '') ?></textarea>
                </div>
            </div>
            
            <div class="flex items-center gap-2 mt-6">
                <button type="button" onclick="window.closeModals()" class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl font-bold active:scale-95 transition-transform">İptal</button>
                <button type="submit" id="cariNotuSubmitBtn" class="flex-1 py-3 bg-amber-500 text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-amber-500/30">
                    <span class="material-symbols-outlined text-[18px]">save</span> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Receipt Modal
window.showMovementReceipt = function(data) {
    Swal.fire({
        html: `
            <div class="text-center py-4 px-2">
                <!-- Header Icon -->
                <div class="w-12 h-12 rounded-full ${data.is_borc ? 'bg-rose-50 text-rose-500' : 'bg-emerald-50 text-emerald-500'} mx-auto mb-6 flex items-center justify-center border ${data.is_borc ? 'border-rose-100' : 'border-emerald-100'}">
                    <span class="material-symbols-outlined text-2xl">${data.is_borc ? 'remove_circle_outline' : 'add_circle_outline'}</span>
                </div>

                <div class="mb-8">
                    <p class="text-[10px] font-black uppercase text-slate-400 tracking-[0.3em] mb-2">Cari İşlem</p>
                    <h2 class="text-lg font-bold text-slate-800 leading-tight">${data.cari_adi}</h2>
                </div>
                
                <div class="py-8 border-y border-slate-50/80 mb-8">
                    <h4 class="text-[9px] font-black uppercase text-slate-300 tracking-[0.2em] mb-3">İşlem Tutarı</h4>
                    <p class="text-4xl font-black ${data.is_borc ? 'text-rose-600' : 'text-emerald-600'} tracking-tight">${data.amount}</p>
                    <p class="text-[10px] font-bold text-slate-400 mt-3 uppercase tracking-[0.2em]">${data.type}</p>
                </div>
                
                <div class="space-y-4 px-2 max-w-[280px] mx-auto text-[11px]">
                    <div class="flex justify-between items-center text-slate-400">
                        <span class="font-bold uppercase tracking-wider">Tarih</span>
                        <span class="font-bold text-slate-600">${data.date} <span class="text-slate-200 mx-1">|</span> ${data.time}</span>
                    </div>

                    ${data.belge_no && data.belge_no !== '-' ? `
                    <div class="flex justify-between items-center text-slate-400">
                        <span class="font-bold uppercase tracking-wider">Belge No</span>
                        <span class="font-bold text-slate-600">#${data.belge_no}</span>
                    </div>` : ''}
                    
                    ${data.aciklama && data.aciklama !== '-' && data.aciklama !== '""' && data.aciklama !== '-' ? `
                    <div class="pt-6 border-t border-slate-50/80">
                         <p class="text-[12px] font-medium text-slate-500 italic leading-relaxed">"${data.aciklama}"</p>
                    </div>` : ''}
                </div>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Kapat',
        confirmButtonColor: '#1e293b',
        customClass: {
            popup: 'rounded-[40px] border-0 shadow-2xl',
            confirmButton: 'rounded-2xl px-12 py-3.5 font-bold text-xs uppercase tracking-[0.1em] transition-all active:scale-95'
        },
        buttonsStyling: true
    });
};

// Action Button Functions
window.editCariNote = function() {
    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('cariNotuModal').classList.remove('translate-y-full');
    setTimeout(() => { document.querySelector('#cariNotuModal textarea').focus(); }, 300);
};

window.submitCariNotu = function(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('cariNotuSubmitBtn');
    const defaultBtnHtml = btn.innerHTML;
    const formData = new FormData(form);
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message || "Cari notu güncellendi", "success");
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
};

window.shareCari = function() {
    const bakiyeText = '<?= absMoneyCariTakip($bakiye) ?> <?= $bakiye < 0 ? "(Borç)" : ($bakiye > 0 ? "(Alacak)" : "") ?>';
    const text = `Cari Bilgisi:\nAdı: <?= addslashes($cariData->CariAdi) ?>\nTelefon: <?= $cariData->Telefon ?: '-' ?>\nGüncel Bakiye: ${bakiyeText}`;
    
    if (navigator.share) {
        navigator.share({
            title: 'Cari Bilgisi',
            text: text
        }).catch(err => {
            console.log('Paylaşım iptal edildi veya hata oluştu', err);
        });
    } else {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        if(window.Toast) Toast.show("Cari bilgileri panoya kopyalandı.", "success");
        else if(window.Alert) Alert.success("Kopyalandı", "Cari bilgileri panoya kopyalandı.");
    }
};

window.exportEkstre = function() {
    // encodeURIComponent ile gondererek '+' karakterinin ' ' (bosluk) olarak algilanmasini onluyoruz
    window.location.href = '../views/cari/export-ekstre-pdf.php?id=' + encodeURIComponent('<?= $cari_id_enc ?>');
};

// Swipe Functionality for Movements
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
        const container = e.target.closest('.movement-item-container');
        if (!container) {
            window.closeAllSwipes();
            return;
        }
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isMoving = false;
    }, { passive: true });

    document.addEventListener('touchmove', e => {
        const container = e.target.closest('.movement-item-container');
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
        const container = e.target.closest('.movement-item-container');
        if (!container) return;
        const touchEndX = e.changedTouches[0].clientX;
        const diffX = touchEndX - touchStartX;
        
        const swipeContent = container.querySelector('.swipe-content');
        const actionRight = container.querySelector('.swipe-action-right');
        const actionLeft = container.querySelector('.swipe-action-left');

        if (isMoving && diffX > 50) {
            // Swipe Right
            window.closeAllSwipes();
            swipeContent.style.transform = 'translateX(70px)';
            if (actionRight) {
                actionRight.style.opacity = '1';
                actionRight.classList.remove('pointer-events-none');
                actionRight.classList.add('pointer-events-auto');
            }
        } else if (isMoving && diffX < -50) {
            // Swipe Left
            window.closeAllSwipes();
            swipeContent.style.transform = 'translateX(-70px)';
            if (actionLeft) {
                actionLeft.style.opacity = '1';
                actionLeft.classList.remove('pointer-events-none');
                actionLeft.classList.add('pointer-events-auto');
            }
        }
    }, { passive: true });
})();

window.editHareket = function(hareketId) {
    const formData = new FormData();
    formData.append('action', 'hareket-getir');
    formData.append('hareket_id', hareketId);

    fetch('../views/cari/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data) {
            window.openHizliIslem('<?= $cari_id_enc ?>', data.type);
            document.getElementById('hizli_islem_hareket_id').value = hareketId;
            
            // Fill fields
            document.getElementById('field_tutar').value = data.tutar_raw;
            document.getElementById('field_belge_no').value = data.belge_no || '';
            document.getElementById('field_aciklama').value = data.aciklama || '';
            
            // Format date for datetime-local
            const d = data.islem_tarihi.split('.');
            const [day, month, rest] = d;
            const [year, time] = rest.split(' ');
            document.getElementById('field_islem_tarihi').value = `${year}-${month}-${day}T${time}`;

            document.querySelector('#hizliIslemModal h3').innerText = 'İşlemi Düzenle';
            window.closeAllSwipes();
        }
    });
};

window.deleteHareket = async function(hareketId) {
    const confirmed = await Alert.confirmDelete("İşlem Silinsin mi?", "Bu hareket kaydı kalıcı olarak silinecek.");
    if (confirmed) {
        const formData = new FormData();
        formData.append('action', 'hareket-sil');
        formData.append('hareket_id', hareketId);

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
        });
    }
};

window.openHizliIslem = function(cariId, type) {
    document.getElementById('hizliIslemForm').reset();
    document.getElementById('hizli_islem_cari_id').value = cariId;
    document.getElementById('hizli_islem_hareket_id').value = '';
    document.getElementById('hidden_type').value = type;
    
    // UI feedback for modal theme depending on type
    const mIcon = document.getElementById('modalIcon');
    const mTitle = document.querySelector('#hizliIslemModal h3');
    const mBtn = document.getElementById('islemSubmitBtn');
    
    // Base resets
    mBtn.className = "w-full py-4 mt-8 text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg text-base ";
    
    if (type === 'verdim') {
        mTitle.innerText = "Verdim";
        mTitle.className = "font-black text-emerald-600 dark:text-emerald-400 text-sm uppercase tracking-wide";
        mIcon.innerText = "add_circle_outline";
        mIcon.parentElement.className = "w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center";
        mBtn.className += "bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/30";
        mBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">task_alt</span> Ödemeyi Kaydet';
    } else {
        mTitle.innerText = "Aldım";
        mTitle.className = "font-black text-rose-600 dark:text-rose-400 text-sm uppercase tracking-wide";
        mIcon.innerText = "remove_circle_outline";
        mIcon.parentElement.className = "w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 flex items-center justify-center";
        mBtn.className += "bg-rose-600 hover:bg-rose-700 shadow-rose-600/30";
        mBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">task_alt</span> Tahsili Kaydet';
    }
    
    // Set default datetime to current local time
    const now = new Date();
    const tzOffsetMs = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now.getTime() - tzOffsetMs)).toISOString().slice(0, 16);
    document.querySelector('input[name="islem_tarihi"]').value = localISOTime;
    
    document.getElementById('modalOverlay').classList.remove('pointer-events-none', 'opacity-0');
    document.getElementById('hizliIslemModal').classList.remove('translate-y-full');
    
    // Focus tutor input immediately
    setTimeout(() => { document.querySelector('input[name="tutar"]').focus(); }, 300);
};

window.closeModals = function() {
    document.getElementById('modalOverlay').classList.add('pointer-events-none', 'opacity-0');
    document.getElementById('hizliIslemModal').classList.add('translate-y-full');
    document.getElementById('cariNotuModal').classList.add('translate-y-full');
};

window.submitHizliIslemForm = function(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('islemSubmitBtn');
    const defaultBtnHtml = btn.innerHTML;
    const formData = new FormData(form);
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/cari/api.php', {
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
};
</script>
