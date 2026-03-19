<?php
use App\Helper\Security;
use App\Model\CariHareketleriModel;

$Hareket = new CariHareketleriModel();
$db = $Hareket->getDb();

// Get all movements globally with cari names
$sql = "SELECT h.*, c.CariAdi, c.firma
        FROM cari_hareketleri h
        LEFT JOIN cari c ON h.cari_id = c.id
        WHERE h.silinme_tarihi IS NULL AND c.silinme_tarihi IS NULL
        ORDER BY h.islem_tarihi DESC, h.id DESC";

$stmt = $db->query($sql);
$hareketler = $stmt->fetchAll(PDO::FETCH_OBJ);

// Formatter functions
if (!function_exists('formatMoneyCariTum')) {
    function formatMoneyCariTum($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' ₺';
    }
}
?>

<div class="px-3 py-4 space-y-4 pb-28">

    <!-- Header Card -->
    <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm p-4 flex items-center justify-between border-l-4 border-blue-500">
        <div>
            <h2 class="font-bold text-slate-800 dark:text-white text-base">Tüm İşlem Geçmişi</h2>
            <p class="text-[10px] text-slate-500 font-medium tracking-wide"><?= count($hareketler) ?> İşlem Kaydı</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="../views/cari/export-tum-hareketler-pdf.php" class="w-10 h-10 rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 flex items-center justify-center active:scale-95 transition-transform shadow-sm border border-emerald-100 dark:border-emerald-500/10" title="PDF Dışarı Aktar">
                <span class="material-symbols-outlined text-[20px]">description</span>
            </a>
            <a href="?p=cari-takip" class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 flex items-center justify-center active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </a>
        </div>
    </div>

    <!-- Search Box -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <span class="material-symbols-outlined text-slate-400">search</span>
        </div>
        <input type="text" id="histSearch" placeholder="Cari, işlem veya açıklama ara..." autocomplete="off"
               class="w-full pl-10 pr-4 py-3 bg-white dark:bg-card-dark border-transparent focus:border-primary focus:ring-0 rounded-xl shadow-sm text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500">
    </div>

    <!-- Movements List -->
    <div class="space-y-3" id="histList">
        <?php foreach ($hareketler as $h): 
            $isBorc = $h->borc > 0;
            $amt = $isBorc ? $h->borc : $h->alacak;
            $icon = $isBorc ? 'trending_up' : 'trending_down'; 
            $iconColor = $isBorc ? 'text-rose-500 bg-rose-50 dark:bg-rose-900/30' : 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/30';
            $dateFmt = date('d.m.Y', strtotime($h->islem_tarihi));
            $timeFmt = date('H:i', strtotime($h->islem_tarihi));
            $searchStr = mb_strtolower($h->CariAdi . ' ' . $h->firma . ' ' . $h->aciklama . ' ' . $h->belge_no, 'UTF-8');
        ?>
        <div class="bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 p-3 hist-item flex items-center justify-between" data-search="<?= htmlspecialchars($searchStr) ?>">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-[10px] flex items-center justify-center shrink-0 <?= $iconColor ?>">
                    <span class="material-symbols-outlined text-[22px]"><?= $icon ?></span>
                </div>
                <div class="min-w-0">
                    <h4 class="font-bold text-[12px] text-slate-800 dark:text-white leading-tight mb-0.5 truncate"><?= htmlspecialchars($h->CariAdi) ?></h4>
                    <div class="text-[10px] text-slate-400 dark:text-slate-500 truncate mb-1"><?= htmlspecialchars($h->aciklama ?: ($isBorc ? 'Aldım' : 'Verdim')) ?></div>
                    <div class="flex items-center gap-2 text-[9px] text-slate-500 dark:text-slate-400 font-medium">
                        <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[11px]">event</span> <?= $dateFmt ?> <?= $timeFmt ?></span>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-end pl-2 shrink-0">
                <p class="font-bold text-sm <?= $isBorc ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                    <?= $isBorc ? '-' : '+' ?><?= formatMoneyCariTum($amt) ?>
                </p>
                <?php if($h->belge_no): ?>
                <div class="mt-0.5 bg-slate-50 dark:bg-slate-800/50 px-1.5 py-0.5 rounded text-[8px] font-bold text-slate-400 uppercase tracking-tighter">
                    #<?= htmlspecialchars($h->belge_no) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(count($hareketler) == 0): ?>
            <div class="text-center py-12 text-slate-400">
                <span class="material-symbols-outlined text-5xl mb-3 opacity-30">history</span>
                <p class="text-xs font-semibold uppercase tracking-widest">Henüz işlem bulunmuyor.</p>
            </div>
        <?php endif; ?>
        <div id="noResult" class="hidden text-center py-12 text-slate-400">
            <span class="material-symbols-outlined text-5xl mb-3 opacity-30">search_off</span>
            <p class="text-xs font-semibold uppercase tracking-widest">Arama sonucu bulunamadı.</p>
        </div>
    </div>
</div>

<script>
// Search Functionality
document.getElementById('histSearch').addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    const items = document.querySelectorAll('.hist-item');
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
</script>
