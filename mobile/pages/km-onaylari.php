<?php
/**
 * Mobil KM Onay Sayfası
 */

require_once dirname(__DIR__, 1) . '/index.php'; // Helperlar için (zaten include ediliyor ama IDE için)
use App\Model\AracKmBildirimModel;
use App\Helper\Helper;

$KmBildirim = new AracKmBildirimModel();

$show = $_GET['show'] ?? 'pending';

if ($show === 'approved') {
    $reports = $KmBildirim->getReportsByStatus('onaylandi');
    $title = "Onaylanan KM'ler";
    $subtitle = "Daha önce onaylanmış kayıtlar";
} elseif ($show === 'rejected') {
    $reports = $KmBildirim->getReportsByStatus('reddedildi');
    $title = "Reddedilen KM'ler";
    $subtitle = "İşleme alınmayan bildirimler";
} elseif ($show === 'unreported') {
    $today = date('Y-m-d');
    $reports = $KmBildirim->getUnreported($today, 'sabah'); // Default to morning
    $title = "Bildirim Yapmayanlar";
    $subtitle = "Bugün KM bildirimi yapmayan personeller";
} else {
    $reports = $KmBildirim->getPendingReports();
    $title = "KM Onayları";
    $subtitle = "Bekleyen kilometre bildirimleri";
}

$pendingCount = count($KmBildirim->getPendingReports());
$approvedCount = count($KmBildirim->getReportsByStatus('onaylandi'));
$rejectedCount = count($KmBildirim->getReportsByStatus('reddedildi'));
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-cyan-600 to-cyan-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                <?= $title ?>
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium"><?= $subtitle ?></p>
        </div>
        <div class="flex gap-3">
            <div class="text-center">
                <div class="bg-white/20 rounded-xl px-4 py-2 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-2xl font-black"><?= $pendingCount ?></span>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-white/90">Bekliyor</span>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-4 pb-6">
    <!-- Filter Tabs -->
    <div class="flex gap-2 p-1 bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
        <a href="?p=km-onaylari&show=pending" class="shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $show === 'pending' ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">schedule</span>
            Bekleyen
            <?php if($pendingCount > 0): ?><span class="bg-cyan-500 text-white text-[9px] px-1.5 py-0.5 rounded-full ml-1"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a href="?p=km-onaylari&show=unreported" class="shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $show === 'unreported' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">error</span>
            Yapmayanlar
        </a>
        <a href="?p=km-onaylari&show=approved" class="shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $show === 'approved' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">check_circle</span>
            Onaylanan
        </a>
        <a href="?p=km-onaylari&show=rejected" class="shrink-0 py-2 px-3 rounded-lg text-[11px] font-bold flex items-center justify-center gap-1 transition-all <?= $show === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'text-slate-500' ?>">
            <span class="material-symbols-outlined text-[16px]">cancel</span>
            Reddedilen
        </a>
    </div>

    <!-- List -->
    <div class="space-y-4">
        <?php if (empty($reports)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-8 text-center border border-dashed border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-4xl">speed</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Bildirim Bulunmuyor</h3>
                <p class="text-xs text-slate-500 mt-1">Bu kategoride gösterilecek kayıt yok.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reports as $report): 
                if ($show === 'unreported') {
                    ?>
                    <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-600 font-bold text-sm">
                                <?= mb_strtoupper(mb_substr($report->personel_adi, 0, 1)) ?>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-700 dark:text-white"><?= htmlspecialchars($report->personel_adi) ?></h4>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight"><?= $report->plaka ?> • Bildirim Yapmadı</p>
                            </div>
                        </div>
                        <button onclick="sendReminder('<?= $report->personel_id ?>')" class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center active:scale-95 transition-all">
                            <span class="material-symbols-outlined text-[20px]">notifications_active</span>
                        </button>
                    </div>
                    <?php
                    continue;
                }

                $imgUrl = !empty($report->resim_yolu) ? '../' . $report->resim_yolu : '../assets/images/no-image.png';
                $reportData = [
                    'id' => $report->id,
                    'plaka' => $report->plaka,
                    'personel' => $report->personel_adi,
                    'tarih' => date('d.m.Y', strtotime($report->tarih)),
                    'saat' => date('H:i', strtotime($report->olusturma_tarihi)),
                    'tur' => $report->tur,
                    'km' => number_format($report->bitis_km, 0, ',', '.'),
                    'img' => $imgUrl,
                    'aciklama' => $report->aciklama ?: 'Açıklama yok'
                ];
                $json = htmlspecialchars(json_encode($reportData), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden km-report-card" data-id="<?= $report->id ?>">
                    <div class="relative h-48 bg-slate-100 dark:bg-slate-900 overflow-hidden cursor-pointer active:opacity-90" onclick="viewKmImage('<?= $imgUrl ?>', '<?= $report->plaka ?>')">
                        <img src="<?= $imgUrl ?>" class="w-full h-full object-cover" alt="KM Görseli">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="absolute bottom-3 left-3 right-3 flex justify-between items-end">
                            <div>
                                <span class="bg-white/20 backdrop-blur-md text-white text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider border border-white/20">
                                    <?= $report->tur === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi' ?>
                                </span>
                                <h3 class="text-white font-black text-lg leading-tight mt-1"><?= $report->plaka ?></h3>
                            </div>
                            <div class="text-right">
                                <p class="text-white/70 text-[10px] font-bold"><?= date('d.m.Y', strtotime($report->tarih)) ?></p>
                                <p class="text-cyan-400 font-black text-xl leading-none"><?= number_format($report->bitis_km, 0, ',', '.') ?> <span class="text-[10px]">KM</span></p>
                            </div>
                        </div>
                        <div class="absolute top-3 right-3">
                            <div class="w-8 h-8 rounded-full bg-black/30 backdrop-blur-md flex items-center justify-center text-white">
                                <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($report->personel_resim)): ?>
                                <img src="../<?= $report->personel_resim ?>" class="w-10 h-10 rounded-full object-cover border border-slate-100 dark:border-slate-800">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 font-bold text-xs">
                                    <?= mb_strtoupper(mb_substr($report->personel_adi, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] text-slate-400 font-bold uppercase leading-none mb-1">Bildiren Personel</p>
                                <p class="text-xs font-black text-slate-700 dark:text-white truncate"><?= htmlspecialchars($report->personel_adi) ?></p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">ARAÇ</p>
                                <p class="text-[11px] font-black text-slate-600 dark:text-slate-400"><?= htmlspecialchars($report->marka) ?> <?= htmlspecialchars($report->model) ?></p>
                            </div>
                        </div>

                        <?php if (!empty($report->aciklama)): ?>
                            <div class="bg-slate-50 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800 mb-4">
                                <p class="text-[11px] text-slate-500 italic leading-relaxed line-clamp-2">"<?= htmlspecialchars($report->aciklama) ?>"</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($show === 'pending'): ?>
                            <div class="flex gap-2">
                                <button onclick="rejectKm(<?= $report->id ?>)" class="flex-1 h-10 bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">close</span> Reddet
                                </button>
                                <button onclick="approveKm(<?= $report->id ?>)" class="flex-[2] h-10 bg-cyan-500 hover:bg-cyan-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-cyan-500/20 transition-all flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px] filled">check_circle</span> Onayla
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[14px] text-slate-400">person_check</span>
                                    <span class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($report->onaylayan_adi ?? 'Sistem') ?></span>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold <?= $show === 'approved' ? 'bg-green-50 text-green-600 dark:bg-green-900/20 border border-green-100 dark:border-green-800' : 'bg-red-50 text-red-600 dark:bg-red-900/20 border border-red-100 dark:border-red-800' ?>">
                                    <span class="material-symbols-outlined text-[12px] filled"><?= $show === 'approved' ? 'check_circle' : 'cancel' ?></span>
                                    <?= $show === 'approved' ? 'ONAYLANDI' : 'REDDEDİLDİ' ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Image View Modal -->
<div id="km-img-modal" class="fixed inset-0 z-[200] bg-black/95 hidden opacity-0 transition-opacity flex flex-col items-center justify-center" onclick="closeKmImage()">
    <button class="absolute top-6 right-6 w-12 h-12 rounded-full bg-white/10 text-white flex items-center justify-center backdrop-blur-md">
        <span class="material-symbols-outlined">close</span>
    </button>
    <div class="w-full h-full p-4 flex items-center justify-center">
        <img id="km-modal-img" src="" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl">
    </div>
    <div class="absolute bottom-10 left-0 right-0 text-center text-white p-4">
        <h3 id="km-modal-title" class="text-xl font-black mb-1"></h3>
        <p id="km-modal-date" class="text-white/60 text-sm font-medium"></p>
    </div>
</div>

<script>
function viewKmImage(url, title) {
    const modal = document.getElementById('km-img-modal');
    const img = document.getElementById('km-modal-img');
    const titleEl = document.getElementById('km-modal-title');
    
    img.src = url;
    titleEl.textContent = title;
    
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.remove('opacity-0'), 10);
}

function closeKmImage() {
    const modal = document.getElementById('km-img-modal');
    modal.classList.add('opacity-0');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function approveKm(id) {
    Alert.confirm('KM Bildirimi Onayı', 'Bu kilometre bildirimini onaylamak istiyor musunuz?', 'Evet, Onayla').then(res => {
        if (res) {
            performKmAction('km-onay-ver', { id: id });
        }
    });
}

function rejectKm(id) {
    Alert.prompt('KM Bildirimi Reddi', 'Reddetme sebebinizi yazın (isteğe bağlı):', 'Reddet', 'Açıklama...').then(reason => {
        if (reason !== false) {
            performKmAction('km-onay-reddet', { id: id, red_nedeni: reason });
        }
    });
}

function performKmAction(action, data) {
    Loading.show();
    $.ajax({
        url: '../views/arac-takip/api.php',
        type: 'POST',
        data: { action: action, ...data },
        success: function(res) {
            Loading.hide();
            try {
                const response = typeof res === 'object' ? res : JSON.parse(res);
                if (response.status === 'success' || response.success) {
                    Toast.show(response.message || 'İşlem başarılı');
                    $(`.km-report-card[data-id="${data.id}"]`).fadeOut(300, function() {
                        $(this).remove();
                        if ($('.km-report-card').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    Alert.error('Hata', response.message || 'Bir hata oluştu');
                }
            } catch (e) {
                Alert.error('Hata', 'Sunucudan geçersiz yanıt alındı');
            }
        },
        error: function() {
            Loading.hide();
            Alert.error('Hata', 'Bağlantı hatası oluştu');
        }
    });
}
function sendReminder(personelId) {
    Loading.show();
    $.ajax({
        url: '../views/arac-takip/api.php',
        type: 'POST',
        data: { action: 'km-hatirlat', personel_id: personelId },
        success: function(res) {
            Loading.hide();
            Toast.show('Hatırlatma gönderildi');
        },
        error: function() {
            Loading.hide();
            Toast.show('Hata oluştu');
        }
    });
}
</script>
