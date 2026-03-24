<?php
use App\Model\GorevModel;
use App\Helper\Security;

$gorevModel = new GorevModel();
$firmaId = $_SESSION['firma_id'] ?? 0;

// $currentUserId is populated in mobile/index.php
$listelerHam = $gorevModel->getListeler($firmaId, $currentUserId);
$tumGorevler = $gorevModel->getTumGorevler($firmaId, $currentUserId);

$listeler = [];
$listeSecenekleri = [];
$aktifToplam = 0;
$tamamlananToplam = 0;

foreach ($listelerHam as $l) {
    $listeler[$l->id] = [
        'liste_adi' => $l->baslik,
        'renk' => $l->renk ?? '#4285f4',
        'id' => Security::encrypt($l->id),
        'aktif' => [],
        'tamamlanan' => []
    ];
    $listeSecenekleri[Security::encrypt($l->id)] = $l->baslik;
}

foreach ($tumGorevler as $g) {
    if (!isset($listeler[$g->liste_id])) continue;
    
    if ($g->tamamlandi == 1) {
        $listeler[$g->liste_id]['tamamlanan'][] = $g;
        $tamamlananToplam++;
    } else {
        $listeler[$g->liste_id]['aktif'][] = $g;
        $aktifToplam++;
    }
}

$renkler = [
    '#4285f4' => 'Mavi',
    '#ea4335' => 'Kırmızı',
    '#fbbc04' => 'Sarı',
    '#34a853' => 'Yeşil',
    '#ff6d01' => 'Turuncu',
    '#46bdc6' => 'Turkuaz',
    '#7baaf7' => 'Açık Mavi',
    '#a142f4' => 'Mor',
    '#f538a0' => 'Pembe',
    '#185abc' => 'Koyu Mavi',
    '#137333' => 'Koyu Yeşil',
    '#5f6368' => 'Gri'
];
?>

<style>
/* Modal Animations and Transitions */
.bottom-sheet {
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.bottom-sheet.open {
    transform: translateY(0);
}
.sheet-overlay {
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.sheet-overlay.open {
    opacity: 1;
    pointer-events: auto;
}
.color-swatch {
    width: 32px; height: 32px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: all 0.2s;
}
.color-swatch.selected {
    border-color: #1e293b; transform: scale(1.1); box-shadow: 0 0 0 2px white inset;
}
</style>

<!-- Gradient Başlık -->
<header class="bg-gradient-primary text-white px-4 pt-4 pb-12 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">Görevleriniz</h2>
        </div>
        <div class="flex items-center gap-2">
            <a href="?p=talepler" class="relative w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white active:scale-95 transition-transform border border-white/10">
                <span class="material-symbols-outlined text-[22px]">notifications</span>
                <?php if ($unreadNotificationCount > 0): ?>
                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-[#135bec] animate-pulse">
                        <?= $unreadNotificationCount ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <div class="relative z-10 flex gap-2 mt-4">
        <div class="flex gap-2 flex-1">
            <div class="text-center">
                <div class="bg-white/20 rounded-xl px-2 py-1 backdrop-blur-sm min-w-[50px]">
                    <span id="total-aktif-count" class="block text-lg font-bold leading-none"><?= $aktifToplam ?></span>
                    <span class="text-[9px] uppercase tracking-wider text-white/90">Bekliyor</span>
                </div>
            </div>
            <div class="text-center">
                <div class="bg-white/10 rounded-xl px-2 py-1 backdrop-blur-sm border border-white/20 min-w-[50px]">
                    <span id="total-completed-count" class="block text-lg font-bold leading-none"><?= $tamamlananToplam ?></span>
                    <span class="text-[9px] uppercase tracking-wider text-white/90">Biten</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="relative z-10 flex gap-2 mt-2">
        <button onclick="openListeModal()" class="flex-1 bg-white/20 hover:bg-white/30 text-white py-2 px-3 rounded-xl font-semibold text-xs flex items-center justify-center gap-1 backdrop-blur-sm transition-colors">
            <span class="material-symbols-outlined text-[16px]">add_task</span> Liste Ekle
        </button>
        <?php if (!empty($listeSecenekleri)): ?>
        <button onclick="openGorevModal()" class="flex-1 bg-white text-primary hover:bg-slate-50 py-2 px-3 rounded-xl font-bold text-xs flex items-center justify-center gap-1 shadow-sm transition-colors">
            <span class="material-symbols-outlined text-[16px]">add</span> Görev Ekle
        </button>
        <?php endif; ?>
    </div>
</header>

<div class="px-4 mt-[-24px] relative z-10 space-y-4 pb-20"> <!-- Added pb-20 for FAB spacing if needed -->
    <?php if (empty($listeler)): ?>
        <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-8 text-center border border-slate-100 dark:border-slate-800">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl text-slate-400">task</span>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Henüz Bir Liste Yok</h3>
            <p class="text-slate-500 dark:text-slate-400 text-sm mb-4">Görev ekleyebilmek için önce bir liste oluşturmalısınız.</p>
            <button onclick="openListeModal()" class="px-4 py-2 bg-primary text-white rounded-lg font-semibold text-sm">Liste Oluştur</button>
        </div>
    <?php else: ?>
        <?php foreach ($listeler as $real_liste_id => $liste): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-visible list-container">
                <!-- Liste Başlığı -->
                <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between rounded-t-2xl" style="background-color: <?= $liste['renk'] ?>15;">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" style="background-color: <?= $liste['renk'] ?>"></div>
                        <h3 class="font-bold text-slate-800 dark:text-white text-sm tracking-wide"><?= htmlspecialchars($liste['liste_adi']) ?></h3>
                        <span id="list-count-<?= $liste['id'] ?>" class="text-slate-500 dark:text-slate-400 text-[10px] font-bold px-1.5 py-0.5 rounded bg-white/50 dark:bg-black/20">
                            <?= count($liste['aktif']) ?>
                        </span>
                    </div>
                    
                    <button type="button" onclick="openListeActionsOverlay('<?= $liste['id'] ?>', '<?= htmlspecialchars(addslashes($liste['liste_adi'])) ?>', '<?= $liste['renk'] ?>')" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-xl">more_vert</span>
                    </button>
                </div>
                
                <div class="divide-y divide-slate-100 dark:divide-slate-800/60 p-2">
                    <!-- Aktif Görevler -->
                    <?php if (count($liste['aktif']) > 0): ?>
                        <div class="space-y-1 mb-2">
                            <?php foreach ($liste['aktif'] as $g): ?>
                                <?php $encTaskId = Security::encrypt($g->id); ?>
                                <div id="gorev-<?= $encTaskId ?>" data-list-id="<?= $liste['id'] ?>" class="flex items-start gap-2 p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                    <label class="relative flex items-center justify-center pt-0.5 cursor-pointer flex-shrink-0">
                                        <input type="checkbox" class="task-check peer appearance-none w-5 h-5 border-2 border-slate-300 dark:border-slate-600 rounded-full checked:bg-green-500 checked:border-green-500 transition-all"
                                               data-id="<?= $encTaskId ?>" 
                                               data-action="tamamla">
                                        <span class="material-symbols-outlined absolute text-white text-[14px] opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity">check</span>
                                    </label>
                                    
                                    <div class="flex-1 min-w-0 flex justify-between items-start gap-2">
                                        <?php 
                                        $yinelemeData = htmlspecialchars(json_encode([
                                            'sikligi' => $g->yineleme_sikligi, 'birimi' => $g->yineleme_birimi, 
                                            'gunleri' => $g->yineleme_gunleri, 'baslangic' => $g->yineleme_baslangic, 
                                            'bitis_tipi' => $g->yineleme_bitis_tipi, 'bitis_tarihi' => $g->yineleme_bitis_tarihi, 
                                            'bitis_adet' => $g->yineleme_bitis_adet
                                        ]), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <div class="flex-1 min-w-0" onclick="openGorevModal('<?= $encTaskId ?>', '<?= $liste['id'] ?>', '<?= htmlspecialchars(addslashes($g->baslik)) ?>', '<?= htmlspecialchars(addslashes($g->aciklama)) ?>', '<?= $g->tarih ?>', '<?= !empty($g->saat) ? substr($g->saat, 0, 5) : '' ?>', '<?= $yinelemeData ?>')">
                                            <p class="text-[13px] font-semibold text-slate-800 dark:text-white leading-snug group-hover:text-primary transition-colors cursor-pointer"><?= htmlspecialchars($g->baslik) ?></p>
                                            <?php if (!empty($g->aciklama)): ?>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($g->aciklama) ?></p>
                                            <?php endif; ?>
                                            <div class="flex items-center gap-2 mt-1">
                                                <?php if (!empty($g->tarih)): ?>
                                                    <div class="flex items-center gap-1 text-[10px] font-medium text-slate-400">
                                                        <span class="material-symbols-outlined text-[12px]">calendar_today</span>
                                                        <span><?= date('d.m.Y', strtotime($g->tarih)) ?> <?= !empty($g->saat) ? substr($g->saat, 0, 5) : '' ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($g->yineleme_sikligi)): ?>
                                                    <span class="material-symbols-outlined text-[13px] text-blue-500" title="Tekrarlanan Görev">sync</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button type="button" onclick="openGorevActionsOverlay('<?= $encTaskId ?>')" class="text-slate-300 hover:text-slate-500 flex-shrink-0 p-1">
                                            <span class="material-symbols-outlined text-lg">more_horiz</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="py-4 text-center">
                            <p class="text-xs text-slate-400 dark:text-slate-500">Bu listede bekleyen görev yok.</p>
                        </div>
                    <?php endif; ?>

                    <!-- Tamamlanan Görevler Toggle -->
                    <?php if (count($liste['tamamlanan']) > 0): ?>
                        <div class="pt-2">
                            <button type="button" class="w-full flex items-center justify-between px-3 py-2 bg-slate-50 dark:bg-slate-800 rounded-lg text-slate-600 dark:text-slate-300 text-xs font-semibold active:opacity-70 transition-opacity completed-toggle-btn">
                                <span class="completed-count-text" data-list-id="<?= $liste['id'] ?>" data-count="<?= count($liste['tamamlanan']) ?>">Tamamlananlar (<?= count($liste['tamamlanan']) ?>)</span>
                                <span class="material-symbols-outlined text-[18px] transition-transform duration-300 completed-chevron">expand_more</span>
                            </button>
                            
                            <div class="completed-tasks hidden space-y-1 mt-2">
                                <?php foreach ($liste['tamamlanan'] as $g): ?>
                                    <div id="gorev-<?= Security::encrypt($g->id) ?>" data-list-id="<?= $liste['id'] ?>" class="flex items-start gap-2 p-2 rounded-xl bg-slate-50/50 dark:bg-slate-800/20">
                                        <label class="relative flex items-center justify-center pt-0.5 opacity-70 cursor-pointer flex-shrink-0">
                                            <input type="checkbox" checked class="task-check peer appearance-none w-5 h-5 border-2 border-green-500 bg-green-500 rounded-full transition-all"
                                                   data-id="<?= Security::encrypt($g->id) ?>" 
                                                   data-action="geri-al">
                                            <span class="material-symbols-outlined absolute text-white text-[14px] pointer-events-none">check</span>
                                        </label>
                                        <div class="flex-1 min-w-0 opacity-70 flex justify-between gap-2">
                                            <div>
                                                <p class="text-[13px] font-medium text-slate-500 dark:text-slate-400 line-through leading-snug mt-0.5"><?= htmlspecialchars($g->baslik) ?></p>
                                                <?php if (!empty($g->yineleme_sikligi)): ?>
                                                    <span class="material-symbols-outlined text-[13px] text-slate-400" title="Tekrarlanan Görev">sync</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" onclick="deleteGorevDirect('<?= Security::encrypt($g->id) ?>')" class="text-slate-300 hover:text-red-500 flex-shrink-0">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- COMMON OVERLAYS AND MODALS -->

<!-- Background Overlay -->
<div id="general-overlay" class="fixed inset-0 bg-slate-900/40 dark:bg-black/60 z-[60] sheet-overlay" onclick="closeAllModals()"></div>

<!-- LISTE ACTIONS BOTTOM SHEET -->
<div id="listeActionsSheet" class="fixed bottom-0 left-0 right-0 z-[61] bg-white dark:bg-card-dark rounded-t-2xl bottom-sheet shadow-2xl safe-area-bottom pb-4">
    <div class="flex justify-center pt-3 pb-2"><div class="w-10 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div></div>
    <div class="px-4 py-2">
        <h4 class="text-sm font-bold text-slate-500 mb-3 text-center border-b border-slate-100 dark:border-slate-800 pb-2">Liste Seçenekleri</h4>
        <button id="btnEditListe" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            <span class="material-symbols-outlined text-primary">edit</span>
            <span class="font-semibold text-slate-800 dark:text-white text-sm">Listeyi Düzenle</span>
        </button>
        <button id="btnDeleteListe" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 transition-colors mt-1">
            <span class="material-symbols-outlined">delete</span>
            <span class="font-semibold text-sm">Listeyi Sil</span>
        </button>
    </div>
</div>

<!-- GÖREV ACTIONS BOTTOM SHEET -->
<div id="gorevActionsSheet" class="fixed bottom-0 left-0 right-0 z-[61] bg-white dark:bg-card-dark rounded-t-2xl bottom-sheet shadow-2xl safe-area-bottom pb-4">
    <div class="flex justify-center pt-3 pb-2"><div class="w-10 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div></div>
    <div class="px-4 py-2">
        <h4 class="text-sm font-bold text-slate-500 mb-3 text-center border-b border-slate-100 dark:border-slate-800 pb-2">Görev Seçenekleri</h4>
        <button id="btnDeleteGorev" class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 transition-colors">
            <span class="material-symbols-outlined">delete</span>
            <span class="font-semibold text-sm">Görevi Sil</span>
        </button>
    </div>
</div>

<!-- LİSTE EKLE/DÜZENLE MODAL -->
<div id="listeModal" class="fixed top-1/2 left-4 right-4 -translate-y-1/2 bg-white dark:bg-card-dark rounded-2xl z-[65] opacity-0 pointer-events-none transition-opacity duration-300 shadow-2xl p-4 scale-95 transform">
    <h3 id="listeModalTitle" class="text-lg font-bold text-slate-800 dark:text-white mb-4">Liste Oluştur</h3>
    <input type="hidden" id="listeModalId">
    
    <div class="space-y-4">
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Liste Adı</label>
            <input type="text" id="listeAdInput" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="örn. Alışveriş Listesi">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-2">Renk</label>
            <div class="flex flex-wrap gap-2" id="colorPickerContainer">
                <?php foreach ($renkler as $hex => $name): ?>
                    <button type="button" class="color-swatch" style="background-color: <?= $hex ?>" data-color="<?= $hex ?>" title="<?= $name ?>"></button>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="listeRenkInput" value="#4285f4">
        </div>
    </div>
    
    <div class="flex gap-2 mt-6">
        <button type="button" onclick="closeAllModals()" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold text-sm transition-colors hover:bg-slate-200 dark:hover:bg-slate-700">İptal</button>
        <button type="button" onclick="saveListe()" class="flex-1 py-2.5 rounded-xl bg-primary text-white font-semibold text-sm transition-colors hover:bg-primary-dark">Kaydet</button>
    </div>
</div>

<!-- GÖREV EKLE/DÜZENLE BOTTOM SHEET -->
<div id="gorevModal" class="fixed bottom-0 left-0 right-0 z-[65] bg-white dark:bg-card-dark rounded-t-2xl bottom-sheet shadow-2xl safe-area-bottom pb-4 max-h-[90vh] flex flex-col">
    <!-- Header Pull Bar -->
    <div class="flex justify-center pt-3 pb-2 flex-shrink-0">
        <div class="w-10 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
    </div>
    
    <!-- Modal Header -->
    <div class="flex items-center justify-between px-4 pb-3 border-b border-slate-100 dark:border-slate-800 flex-shrink-0">
        <h3 id="gorevModalTitle" class="text-base font-bold text-slate-800 dark:text-white">Görev Ekle</h3>
        <button type="button" onclick="closeAllModals()" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>
    
    <!-- Modal Body -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <input type="hidden" id="gorevModalId">
        
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Görev Başlığı *</label>
            <input type="text" id="gorevAdInput" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Ne yapılması gerekiyor?">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Açıklama (İsteğe Bağlı)</label>
            <textarea id="gorevAciklamaInput" rows="3" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Detaylar..."></textarea>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tarih</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">calendar_today</span>
                    <input type="date" id="gorevTarihInput" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Saat</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">schedule</span>
                    <input type="time" id="gorevSaatInput" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Tekrarlama</label>
            <button type="button" onclick="openYinelemeSheet()" class="w-full flex items-center justify-between bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary active:opacity-70 transition-opacity">
                <span id="btnYinelemeText">Tekrarlama Yok</span>
                <span class="material-symbols-outlined text-slate-400">edit_calendar</span>
            </button>
            <input type="hidden" id="gorevYinelemeData" value="{}">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Liste Seçimi *</label>
            <div class="relative">
                <select id="gorevListeSecim" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                    <?php foreach($listeSecenekleri as $encId => $baslik): ?>
                        <option value="<?= $encId ?>"><?= htmlspecialchars($baslik) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
            </div>
        </div>

    </div>

    <!-- Modal Footer / Save Button -->
    <div class="px-4 pt-2 flex-shrink-0">
        <button type="button" onclick="saveGorev()" class="w-full py-3 rounded-xl bg-primary text-white font-bold text-sm transition-colors hover:bg-primary-dark">
            Kaydet
        </button>
    </div>
</div>

<!-- YİNELEME BOTTOM SHEET -->
<div id="gorevYinelemeSheet" class="fixed bottom-0 left-0 right-0 z-[70] bg-white dark:bg-card-dark rounded-t-2xl bottom-sheet shadow-2xl safe-area-bottom pb-4 max-h-[90vh] flex flex-col">
    <!-- Header Pull Bar -->
    <div class="flex justify-center pt-3 pb-2 flex-shrink-0">
        <div class="w-10 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
    </div>
    
    <!-- Modal Header -->
    <div class="flex items-center justify-between px-4 pb-3 border-b border-slate-100 dark:border-slate-800 flex-shrink-0">
        <h3 class="text-base font-bold text-slate-800 dark:text-white">Yinelenme Sıklığı</h3>
        <button type="button" onclick="closeYinelemeSheet()" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>
    
    <!-- Modal Body -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="flex gap-2">
            <input type="number" id="ySikligi" value="1" min="1" max="99" class="w-20 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary text-center">
            <div class="relative flex-1">
                <select id="yBirimi" class="w-full h-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white appearance-none focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary" onchange="toggleHaftaGunleri()">
                    <option value="gun">gün</option>
                    <option value="hafta">hafta</option>
                    <option value="ay">ay</option>
                    <option value="yil">yıl</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
            </div>
        </div>

        <!-- Haftanın Günleri -->
        <div id="yHaftaGunleri" class="hidden">
            <label class="block text-xs font-semibold text-slate-500 mb-2">Hangi Günler?</label>
            <div class="flex justify-between gap-1">
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="1">P</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="2">S</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="3">Ç</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="4">P</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="5">C</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="6">C</button>
                <button type="button" class="gun-btn w-10 h-10 rounded-full font-bold text-sm bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors" data-gun="0">P</button>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">Başlangıç</label>
            <input type="date" id="yBaslangic" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-2">Bitiş</label>
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input type="radio" name="yBitisTipi" value="asla" checked class="w-4 h-4 text-primary bg-slate-100 border-slate-300 dark:border-slate-600 focus:ring-primary dark:bg-slate-700">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Asla</span>
                </label>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-3 flex-shrink-0">
                        <input type="radio" name="yBitisTipi" value="tarih" class="w-4 h-4 text-primary bg-slate-100 border-slate-300 dark:border-slate-600 focus:ring-primary dark:bg-slate-700">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Şu tarihte:</span>
                    </label>
                    <input type="date" id="yBitisTarihi" class="flex-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2 py-2 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-primary" onclick="document.querySelector('input[name=yBitisTipi][value=tarih]').checked=true">
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-3 flex-shrink-0">
                        <input type="radio" name="yBitisTipi" value="adet" class="w-4 h-4 text-primary bg-slate-100 border-slate-300 dark:border-slate-600 focus:ring-primary dark:bg-slate-700">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Yinele:</span>
                    </label>
                    <input type="number" id="yBitisAdet" value="30" min="1" max="999" class="w-16 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2 py-2 text-sm text-slate-800 dark:text-white text-center focus:outline-none focus:border-primary" onclick="document.querySelector('input[name=yBitisTipi][value=adet]').checked=true">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">kez</span>
                </div>
            </div>
        </div>
        
        <div class="pt-2 flex justify-between gap-2">
            <button type="button" onclick="clearYineleme()" class="py-2.5 px-4 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 font-semibold text-sm transition-colors hover:bg-red-100 dark:hover:bg-red-900/40">
                Tekrarlamayı Kaldır
            </button>
            <button type="button" onclick="saveYinelemeSheet()" class="flex-1 py-2.5 rounded-xl bg-primary text-white font-semibold text-sm transition-colors hover:bg-primary-dark">
                Uygula
            </button>
        </div>
    </div>
</div>

<div id="loader" class="fixed inset-0 bg-white/50 dark:bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark p-4 rounded-2xl shadow-xl flex items-center gap-3">
        <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">İşleniyor...</span>
    </div>
</div>


<script>
// UI Control Functions
const overlay = document.getElementById('general-overlay');
let activeListId = null;
let activeTaskId = null;

// Color Picker Logic
document.querySelectorAll('.color-swatch').forEach(el => {
    el.addEventListener('click', function() {
        document.querySelectorAll('.color-swatch').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('listeRenkInput').value = this.dataset.color;
    });
});

function showLoader() { document.getElementById('loader').classList.remove('opacity-0', 'pointer-events-none'); }
function hideLoader() { document.getElementById('loader').classList.add('opacity-0', 'pointer-events-none'); }

function closeAllModals() {
    overlay.classList.remove('open');
    document.querySelectorAll('.bottom-sheet').forEach(el => el.classList.remove('open'));
    
    // Dialog modal hide
    const lModal = document.getElementById('listeModal');
    lModal.classList.remove('opacity-100');
    lModal.classList.add('opacity-0', 'pointer-events-none');
    lModal.classList.replace('scale-100', 'scale-95');

    // Note: gorevModal is now a bottom-sheet, so it is handled by the loop above.
}

// LİSTE İŞLEMLERİ
function openListeActionsOverlay(id, name, color) {
    activeListId = id;
    document.getElementById('btnEditListe').onclick = () => { closeAllModals(); openListeModal(id, name, color); };
    document.getElementById('btnDeleteListe').onclick = () => { closeAllModals(); deleteListe(id); };
    
    overlay.classList.add('open');
    document.getElementById('listeActionsSheet').classList.add('open');
}

function openListeModal(id = '', name = '', color = '#4285f4') {
    closeAllModals();
    activeListId = id;
    document.getElementById('listeModalId').value = id;
    document.getElementById('listeAdInput').value = name;
    document.getElementById('listeRenkInput').value = color;
    document.getElementById('listeModalTitle').textContent = id ? "Listeyi Düzenle" : "Liste Oluştur";
    
    // set selected color manually
    document.querySelectorAll('.color-swatch').forEach(c => c.classList.remove('selected'));
    const target = document.querySelector(`.color-swatch[data-color="${color}"]`);
    if(target) target.classList.add('selected');

    overlay.classList.add('open');
    const m = document.getElementById('listeModal');
    m.classList.remove('opacity-0', 'pointer-events-none', 'scale-95');
    m.classList.add('opacity-100', 'scale-100');
}

async function saveListe() {
    const baslik = document.getElementById('listeAdInput').value.trim();
    const renk = document.getElementById('listeRenkInput').value;
    const isEdit = !!activeListId;
    
    if(!baslik) {
        Toast.show('Liste adı boş olamaz.', 'warning');
        return;
    }
    
    showLoader();
    const fd = new URLSearchParams();
    fd.append('action', isEdit ? 'update-liste' : 'add-liste');
    fd.append('baslik', baslik);
    fd.append('renk', renk);
    if(isEdit) fd.append('liste_id', activeListId);
    
    try {
        const res = await fetch('../views/gorevler/api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            window.location.reload();
        } else {
            Toast.show(data.message || 'Hata oluştu.', 'error');
            hideLoader();
        }
    } catch(err) {
        Toast.show('Bağlantı hatası.', 'error');
        hideLoader();
    }
}

async function deleteListe(id) {
    const isConfirmed = await Alert.confirmDelete('Sil', 'Bu listeyi ve içindeki tüm görevleri silmek istediğinize emin misiniz?');
    if(!isConfirmed) return;
    
    showLoader();
    const fd = new URLSearchParams();
    fd.append('action', 'delete-liste');
    fd.append('liste_id', id);
    try {
        const res = await fetch('../views/gorevler/api.php', { method: 'POST', body: fd });
        const data = await res.json();
        Toast.show('Liste silindi.', 'success');
        setTimeout(() => window.location.reload(), 1000);
    } catch {
        Toast.show('Silme sırasında hata oluştu.', 'error');
        hideLoader();
    }
}

// GÖREV İŞLEMLERİ
function openGorevActionsOverlay(id) {
    activeTaskId = id;
    document.getElementById('btnDeleteGorev').onclick = () => { closeAllModals(); deleteGorevDirect(id); };
    
    overlay.classList.add('open');
    document.getElementById('gorevActionsSheet').classList.add('open');
}

function openGorevModal(id = '', listeId = '', baslik = '', aciklama = '', tarih = '', saat = '', yinelemeDataStr = '{}') {
    closeAllModals();
    activeTaskId = id;
    document.getElementById('gorevModalId').value = id;
    document.getElementById('gorevAdInput').value = baslik;
    document.getElementById('gorevAciklamaInput').value = aciklama;
    document.getElementById('gorevTarihInput').value = tarih;
    document.getElementById('gorevSaatInput').value = saat;
    document.getElementById('gorevYinelemeData').value = yinelemeDataStr || '{}';
    updateYinelemeSummaryText(yinelemeDataStr || '{}');
    if(listeId) document.getElementById('gorevListeSecim').value = listeId;
    
    document.getElementById('gorevModalTitle').textContent = id ? "Görevi Düzenle" : "Görev Ekle";

    const m = document.getElementById('gorevModal');
    overlay.classList.add('open');
    m.classList.add('open');
}

async function saveGorev() {
    const baslik = document.getElementById('gorevAdInput').value.trim();
    if(!baslik) {
        Toast.show('Görev başlığı zorunludur.', 'warning');
        return;
    }
    
    showLoader();
    const isEdit = !!activeTaskId;
    const fd = new URLSearchParams();
    fd.append('action', isEdit ? 'update-gorev' : 'add-gorev');
    fd.append('baslik', baslik);
    fd.append('aciklama', document.getElementById('gorevAciklamaInput').value.trim());
    fd.append('tarih', document.getElementById('gorevTarihInput').value);
    fd.append('saat', document.getElementById('gorevSaatInput').value);
    
    let yineleme = {};
    try {
        const yStr = document.getElementById('gorevYinelemeData').value;
        if (yStr && yStr !== '{}') {
            yineleme = JSON.parse(yStr);
        }
    } catch(e){}
    
    fd.append('yineleme_sikligi', yineleme.sikligi || '');
    fd.append('yineleme_birimi', yineleme.birimi || '');
    fd.append('yineleme_gunleri', yineleme.gunleri || '');
    fd.append('yineleme_baslangic', yineleme.baslangic || '');
    fd.append('yineleme_bitis_tipi', yineleme.bitis_tipi || '');
    fd.append('yineleme_bitis_tarihi', yineleme.bitis_tarihi || '');
    fd.append('yineleme_bitis_adet', yineleme.bitis_adet || '');
    
    if(isEdit) {
        fd.append('gorev_id', activeTaskId);
        // Liste ID cannot be easily changed in UI right now without custom API logic, kept simplest
    } else {
        fd.append('liste_id', document.getElementById('gorevListeSecim').value);
    }
    
    try {
        const res = await fetch('../views/gorevler/api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if(data.success) {
            window.location.reload();
        } else {
            Toast.show(data.message || 'Hata oluştu.', 'error');
            hideLoader();
        }
    } catch(err) {
        Toast.show('Bağlantı hatası.', 'error');
        hideLoader();
    }
}

async function deleteGorevDirect(id) {
    const isConfirmed = await Alert.confirmDelete('Sil', 'Bu görevi silmek istediğinize emin misiniz?');
    if(!isConfirmed) return;
    
    showLoader();
    const fd = new URLSearchParams();
    fd.append('action', 'delete-gorev');
    fd.append('gorev_id', id);
    try {
        const res = await fetch('../views/gorevler/api.php', { method: 'POST', body: fd });
        const data = await res.json();
        Toast.show('Görev silindi.', 'success');
        const taskEl = document.getElementById('gorev-' + id);
        if (taskEl) {
            const listId = taskEl.dataset.listId;
            const isCompleted = taskEl.querySelector('.task-check').checked;
            
            const totalAktif = document.getElementById('total-aktif-count');
            const totalCompleted = document.getElementById('total-completed-count');
            const listCountCounter = document.getElementById('list-count-' + listId);

            if (isCompleted) {
                if (totalCompleted) totalCompleted.textContent = Math.max(0, parseInt(totalCompleted.textContent) - 1);
                
                // Update list's completed count text
                const completedBtnText = document.querySelector(`.completed-count-text[data-list-id="${listId}"]`);
                if (completedBtnText) {
                    let cCount = parseInt(completedBtnText.dataset.count) || 0;
                    cCount = Math.max(0, cCount - 1);
                    completedBtnText.dataset.count = cCount;
                    completedBtnText.textContent = `Tamamlananlar (${cCount})`;
                }
            } else {
                if (totalAktif) totalAktif.textContent = Math.max(0, parseInt(totalAktif.textContent) - 1);
                if (listCountCounter) listCountCounter.textContent = Math.max(0, parseInt(listCountCounter.textContent) - 1);
            }

            taskEl.style.transition = 'all 0.3s ease';
            taskEl.style.opacity = '0';
            taskEl.style.transform = 'scale(0.9)';
            setTimeout(() => taskEl.remove(), 300);
        }
        hideLoader();
    } catch {
        Toast.show('Silme sırasında hata oluştu.', 'error');
        hideLoader();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Checkbox olayları
    const taskChecks = document.querySelectorAll('.task-check');
    taskChecks.forEach(check => {
        check.addEventListener('change', async (e) => {
            const gorevId = e.target.getAttribute('data-id');
            const action = e.target.getAttribute('data-action');
            const isChecked = e.target.checked;
            
            if ((action === 'tamamla' && !isChecked) || (action === 'geri-al' && isChecked)) return;

            showLoader();
            try {
                const fd = new URLSearchParams();
                fd.append('action', action);
                fd.append('gorev_id', gorevId);
                const res = await fetch('../views/gorevler/api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    Toast.show('İşlem başarılı', 'success');
                    
                    const taskEl = document.getElementById('gorev-' + gorevId);
                    if (taskEl) {
                        const listId = taskEl.dataset.listId;
                        const totalAktif = document.getElementById('total-aktif-count');
                        const totalCompleted = document.getElementById('total-completed-count');
                        const listCountCounter = document.getElementById('list-count-' + listId);

                        if (action === 'tamamla') {
                            if (totalAktif) totalAktif.textContent = Math.max(0, parseInt(totalAktif.textContent) - 1);
                            if (totalCompleted) totalCompleted.textContent = parseInt(totalCompleted.textContent) + 1;
                            if (listCountCounter) listCountCounter.textContent = Math.max(0, parseInt(listCountCounter.textContent) - 1);
                        } else {
                            if (totalAktif) totalAktif.textContent = parseInt(totalAktif.textContent) + 1;
                            if (totalCompleted) totalCompleted.textContent = Math.max(0, parseInt(totalCompleted.textContent) - 1);
                            if (listCountCounter) listCountCounter.textContent = parseInt(listCountCounter.textContent) + 1;
                        }

                        // Update list's completed count text
                        const completedBtnText = document.querySelector(`.completed-count-text[data-list-id="${listId}"]`);
                        if (completedBtnText) {
                            let cCount = parseInt(completedBtnText.dataset.count) || 0;
                            if (action === 'tamamla') cCount++; else cCount--;
                            completedBtnText.dataset.count = cCount;
                            completedBtnText.textContent = `Tamamlananlar (${cCount})`;
                        }

                        taskEl.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                        taskEl.style.opacity = '0';
                        taskEl.style.transform = 'translateX(30px)';
                        setTimeout(() => taskEl.remove(), 400);
                    }
                    hideLoader();
                }
                else { Toast.show(data.message, 'error'); e.target.checked = !isChecked; hideLoader(); }
            } catch (err) {
                Toast.show('Sunucu hatası.', 'error'); e.target.checked = !isChecked; hideLoader();
            }
        });
    });

    // Tamamlanan görevleri gizle/göster
    const toggleBtns = document.querySelectorAll('.completed-toggle-btn');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.nextElementSibling;
            const chevron = btn.querySelector('.completed-chevron');
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                container.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        });
    });

    // Haftanın günleri seçimi
    document.querySelectorAll('.gun-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.classList.toggle('bg-primary');
            this.classList.toggle('text-white');
            this.classList.toggle('bg-slate-100');
            this.classList.toggle('text-slate-500');
            this.classList.toggle('dark:bg-slate-800');
            this.classList.toggle('dark:text-slate-400');
            this.classList.toggle('selected');
        });
    });
});

function toggleHaftaGunleri() {
    if (document.getElementById('yBirimi').value === 'hafta') {
        document.getElementById('yHaftaGunleri').classList.remove('hidden');
    } else {
        document.getElementById('yHaftaGunleri').classList.add('hidden');
    }
}

function openYinelemeSheet() {
    const raw = document.getElementById('gorevYinelemeData').value;
    let data = {};
    try { data = JSON.parse(raw); } catch(e){}
    
    document.getElementById('ySikligi').value = data.sikligi || 1;
    document.getElementById('yBirimi').value = data.birimi || 'hafta';
    toggleHaftaGunleri();
    
    document.querySelectorAll('.gun-btn').forEach(btn => {
        btn.classList.remove('bg-primary', 'text-white', 'selected');
        btn.classList.add('bg-slate-100', 'text-slate-500', 'dark:bg-slate-800', 'dark:text-slate-400');
    });
    if (data.birimi === 'hafta' && data.gunleri) {
        let selectedGunler = String(data.gunleri).split(',');
        document.querySelectorAll('.gun-btn').forEach(btn => {
            if (selectedGunler.includes(btn.dataset.gun)) {
                btn.classList.remove('bg-slate-100', 'text-slate-500', 'dark:bg-slate-800', 'dark:text-slate-400');
                btn.classList.add('bg-primary', 'text-white', 'selected');
            }
        });
    }
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('yBaslangic').value = data.baslangic || today;
    
    const bitisTipi = data.bitis_tipi || 'asla';
    const radioEl = document.querySelector(`input[name="yBitisTipi"][value="${bitisTipi}"]`);
    if(radioEl) radioEl.checked = true;
    
    document.getElementById('yBitisTarihi').value = data.bitis_tarihi || '';
    document.getElementById('yBitisAdet').value = data.bitis_adet || 30;

    document.getElementById('gorevYinelemeSheet').classList.add('open');
}

function closeYinelemeSheet() {
    document.getElementById('gorevYinelemeSheet').classList.remove('open');
}

function clearYineleme() {
    document.getElementById('gorevYinelemeData').value = '{}';
    document.getElementById('btnYinelemeText').textContent = 'Tekrarlama Yok';
    closeYinelemeSheet();
}

function saveYinelemeSheet() {
    const sikligi = document.getElementById('ySikligi').value || 1;
    const birimi = document.getElementById('yBirimi').value;
    const bitisTipi = document.querySelector('input[name="yBitisTipi"]:checked').value;
    
    const selectedGunler = Array.from(document.querySelectorAll('.gun-btn.selected')).map(btn => btn.dataset.gun).join(',');
    
    const data = {
        sikligi: sikligi,
        birimi: birimi,
        gunleri: birimi === 'hafta' ? selectedGunler : '',
        baslangic: document.getElementById('yBaslangic').value,
        bitis_tipi: bitisTipi,
        bitis_tarihi: bitisTipi === 'tarih' ? document.getElementById('yBitisTarihi').value : '',
        bitis_adet: bitisTipi === 'adet' ? document.getElementById('yBitisAdet').value : ''
    };
    
    document.getElementById('gorevYinelemeData').value = JSON.stringify(data);
    updateYinelemeSummaryText(data);
    closeYinelemeSheet();
}

function updateYinelemeSummaryText(dataRaw) {
    if (!dataRaw || dataRaw === '' || dataRaw === '{}') {
        document.getElementById('btnYinelemeText').textContent = 'Tekrarlama Yok';
        return;
    }
    try {
        const data = typeof dataRaw === 'string' ? JSON.parse(dataRaw) : dataRaw;
        if (!data.sikligi) {
            document.getElementById('btnYinelemeText').textContent = 'Tekrarlama Yok';
            return;
        }
        let summary = '';
        if (data.sikligi > 1) {
            summary = `Her ${data.sikligi} ${data.birimi === 'gun' ? 'Günde' : data.birimi === 'hafta' ? 'Haftada' : data.birimi === 'ay' ? 'Ayda' : 'Yılda'}`;
        } else {
            summary = `${data.birimi === 'gun' ? 'Her Gün' : data.birimi === 'hafta' ? 'Her Hafta' : data.birimi === 'ay' ? 'Her Ay' : 'Her Yıl'}`;
        }
        document.getElementById('btnYinelemeText').textContent = summary;
    } catch(e) {
        document.getElementById('btnYinelemeText').textContent = 'Tekrarlama Yok';
    }
}
</script>
