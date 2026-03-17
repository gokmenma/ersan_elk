<?php
use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Security;

$talepModel = new TalepModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();

// $currentUserId is populated in mobile/index.php
$showApproved = isset($_GET['show']) && $_GET['show'] === 'approved';

// Her zaman bekleyenlerin sayısını saymak için
$bekleyenAvanslar = $avansModel->getButunBekleyenAvanslar();
try {
    $bekleyenIzinler = $izinModel->getButunBekleyenIzinler();
} catch (\Exception $e) {
    $bekleyenIzinler = [];
}
$bekleyenTalepler = $talepModel->getButunBekleyenTalepler();

$avansCount = count($bekleyenAvanslar);
$izinCount = count($bekleyenIzinler);
$talepCount = count($bekleyenTalepler);
$toplamCount = $avansCount + $izinCount + $talepCount;

if ($showApproved) {
    $avanslar = $avansModel->getIslenmisAvanslar(30);
    try {
        $izinler = $izinModel->getIslenmisIzinler(30);
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getCozulmusTalepler(30);
} else {
    $avanslar = $bekleyenAvanslar;
    $izinler = $bekleyenIzinler;
    $talepler = $bekleyenTalepler;
}

// Format helper
function formatMoneyMobile($amount) {
    return number_format((float)$amount, 2, ',', '.') . ' ₺';
}
function formatDateMobile($dateStr) {
    if (!$dateStr) return '-';
    return date('d.m.Y H:i', strtotime($dateStr));
}
function formatDateOnlyMobile($dateStr) {
    if (!$dateStr) return '-';
    return date('d.m.Y', strtotime($dateStr));
}
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-orange-600 to-orange-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                Talepler
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium">Onay bekleyen tüm işlemler</p>
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
        <div class="flex gap-3">
            <div class="text-center">
                <div class="bg-white/20 rounded-xl px-4 py-2 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-2xl font-black"><?= $toplamCount ?></span>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-white/90">Bekliyor</span>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-5 pb-6">

    <!-- Tab Buttons -->
    <div class="flex gap-2 p-1 bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
        <button onclick="switchTab('avans')" id="btn-tab-avans" class="flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
            <span class="material-symbols-outlined text-[18px]">payments</span>
            Avans
            <?php if($avansCount > 0): ?><span class="bg-orange-500 text-white text-[10px] px-1.5 py-0.5 rounded-full ml-1"><?= $avansCount ?></span><?php endif; ?>
        </button>
        <button onclick="switchTab('izin')" id="btn-tab-izin" class="flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800">
            <span class="material-symbols-outlined text-[18px]">event_note</span>
            İzin
            <?php if($izinCount > 0): ?><span class="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-[10px] px-1.5 py-0.5 rounded-full ml-1"><?= $izinCount ?></span><?php endif; ?>
        </button>
        <button onclick="switchTab('talep')" id="btn-tab-talep" class="flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800">
            <span class="material-symbols-outlined text-[18px]">support_agent</span>
            Genel
            <?php if($talepCount > 0): ?><span class="bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 text-[10px] px-1.5 py-0.5 rounded-full ml-1"><?= $talepCount ?></span><?php endif; ?>
        </button>
    </div>

    <!-- Filter Buttons -->
    <div class="flex items-center bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 rounded-lg p-1 shadow-sm mt-3">
        <a href="?p=talepler" class="toggle-link <?= !$showApproved ? 'bg-[#ffca58] text-slate-800 shadow-sm pointer-events-none' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' ?> flex-1 py-1.5 flex items-center justify-center gap-1.5 text-xs font-bold rounded-md transition-colors">
            <span class="material-symbols-outlined text-[16px]">schedule</span>
            Bekleyenler
        </a>
        <div class="w-px h-4 bg-slate-200 dark:bg-slate-700 mx-1"></div>
        <a href="?p=talepler&show=approved" class="toggle-link <?= $showApproved ? 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm pointer-events-none' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' ?> flex-1 py-1.5 flex items-center justify-center gap-1.5 text-xs font-bold rounded-md transition-colors">
            <span class="material-symbols-outlined text-[16px]">task_alt</span>
            İşlem Yapılanlar
        </a>
    </div>

    <!-- AVANSLAR -->
    <div id="tab-content-avans" class="tab-content block space-y-3 mt-3">
        <?php if (empty($avanslar)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm">
                <div class="w-12 h-12 <?= !$showApproved ? 'bg-orange-50 text-orange-400 dark:bg-orange-900/20' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-2xl"><?= !$showApproved ? 'check_circle' : 'info' ?></span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white"><?= !$showApproved ? 'Bekleyen Avans Yok' : 'İşlem Gören Kayıt Yok' ?></h3>
                <p class="text-xs text-slate-500 mt-1"><?= !$showApproved ? 'Bekleyen tüm avans talepleri yanıtlandı.' : 'Henüz işlem görmüş avans kaydı bulunmuyor.' ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($avanslar as $avans): ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between mb-3 border-b border-slate-100 dark:border-slate-800/60 pb-3">
                        <div class="flex items-center gap-3">
                            <?php 
                            $paResim = !empty($avans->personel_resim_yolu) ? $avans->personel_resim_yolu : ($avans->resim_yolu ?? '');
                            if (!empty($paResim) && file_exists($paResim)): ?>
                                <img src="../<?= htmlspecialchars($paResim) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700">
                            <?php else: ?>
                                <img src="../assets/images/users/user-dummy-img.jpg" class="w-10 h-10 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700" alt="Avatar">
                            <?php endif; ?>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($avans->requester_name ?? 'Bilinmeyen') ?></h3>
                                <p class="text-[11px] text-slate-500"><?= htmlspecialchars($avans->departman ?? 'Departman Yok') ?></p>
                            </div>
                        </div>
                        <div class="text-right flex flex-col items-end gap-1.5">
                            <span class="inline-flex items-center gap-1 bg-green-50 text-green-600 font-bold px-2 py-1 rounded-lg text-sm border border-green-100 dark:bg-green-900/20 dark:border-green-800">
                                <?= formatMoneyMobile($avans->tutar) ?>
                            </span>
                            <?php if (!$showApproved): ?>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-800">Beklemede</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-medium mb-1.5">
                            <span class="material-symbols-outlined text-[14px]">calendar_clock</span>
                            Talep Tarihi: <span class="text-slate-700 dark:text-slate-300"><?= formatDateMobile($avans->talep_tarihi) ?></span>
                        </div>
                        <?php if (!empty($avans->aciklama)): ?>
                            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-2.5 mt-2">
                                <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">"<?= htmlspecialchars($avans->aciklama) ?>"</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$showApproved): ?>
                    <div class="flex gap-2">
                        <button onclick="openModal('avansRed', <?= $avans->id ?>, '<?= htmlspecialchars(addslashes($avans->requester_name)) ?>')" class="flex-1 px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/20 dark:text-red-400 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">close</span> Reddet
                        </button>
                        <button onclick="openModal('avansOnay', <?= $avans->id ?>, '<?= htmlspecialchars(addslashes($avans->requester_name)) ?>', '<?= formatMoneyMobile($avans->tutar) ?>')" class="flex-1 px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-green-500/30 transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">check</span> Onayla
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                <span class="material-symbols-outlined text-[14px]">person</span>
                                <?= htmlspecialchars($avans->solver_name ?? 'Yönetici') ?>
                            </div>
                            <?php if (!empty($avans->onay_tarihi)): ?>
                                <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                    <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                    <?= formatDateMobile($avans->onay_tarihi) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($avans->durum == 'onaylandi'): ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 rounded text-[10px] font-bold border border-green-100 dark:border-green-800/30">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span> Onaylandı
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 rounded text-[10px] font-bold border border-red-100 dark:border-red-800/30">
                                    <span class="material-symbols-outlined text-[14px]">cancel</span> Reddedildi
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- İZİNLER -->
    <div id="tab-content-izin" class="tab-content hidden space-y-3 mt-3">
        <?php if (empty($izinler)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm">
                <div class="w-12 h-12 <?= !$showApproved ? 'bg-blue-50 text-blue-400 dark:bg-blue-900/20' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-2xl"><?= !$showApproved ? 'check_circle' : 'info' ?></span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white"><?= !$showApproved ? 'Bekleyen İzin Yok' : 'İşlem Gören Kayıt Yok' ?></h3>
                <p class="text-xs text-slate-500 mt-1"><?= !$showApproved ? 'Bekleyen tüm izin talepleri yanıtlandı.' : 'Henüz işlem görmüş izin kaydı bulunmuyor.' ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($izinler as $izin): 
                $gunSayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
                $izinTuruLabel = $izin->izin_tipi_adi ?? $izin->izin_tipi ?? 'Belirtilmemiş';
            ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between mb-3 border-b border-slate-100 dark:border-slate-800/60 pb-3">
                        <div class="flex items-center gap-3">
                            <?php 
                            $piResim = !empty($izin->personel_resim_yolu) ? $izin->personel_resim_yolu : ($izin->resim_yolu ?? '');
                            if (!empty($piResim) && file_exists($piResim)): ?>
                                <img src="../<?= htmlspecialchars($piResim) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700">
                            <?php else: ?>
                                <img src="../assets/images/users/user-dummy-img.jpg" class="w-10 h-10 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700" alt="Avatar">
                            <?php endif; ?>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($izin->requester_name ?? 'Bilinmeyen') ?></h3>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="inline-flex items-center bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 text-[10px] px-1.5 py-0.5 rounded font-bold">
                                        <?= $izinTuruLabel ?>
                                    </span>
                                    <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                        <?= $gunSayisi ?> Gün
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if (!$showApproved): ?>
                            <div class="flex-shrink-0">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">Beklemede</span>
                            </div>
                        <?php else: ?>
                            <div class="flex-shrink-0">
                                <?php if (strtolower($izin->onay_durumu ?? '') == 'onaylandı' || strtolower($izin->onay_durumu ?? '') == 'onaylandi'): ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 rounded text-[10px] font-bold border border-green-100 dark:border-green-900/30">
                                        <span class="material-symbols-outlined text-[13px]">check_circle</span> Onaylandı
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 rounded text-[10px] font-bold border border-red-100 dark:border-red-900/30">
                                        <span class="material-symbols-outlined text-[13px]">cancel</span> Reddedildi
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-4 space-y-1.5">
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-medium">
                            <span class="material-symbols-outlined text-[14px]">flight_takeoff</span>
                            Başlangıç: <span class="text-slate-700 dark:text-slate-300"><?= formatDateOnlyMobile($izin->baslangic_tarihi) ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-500 text-xs font-medium">
                            <span class="material-symbols-outlined text-[14px]">flight_land</span>
                            Bitiş: <span class="text-slate-700 dark:text-slate-300"><?= formatDateOnlyMobile($izin->bitis_tarihi) ?></span>
                        </div>
                        <?php if (!empty($izin->aciklama)): ?>
                            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-2.5 mt-2">
                                <p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2">"<?= htmlspecialchars($izin->aciklama) ?>"</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($showApproved): ?>
                            <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/60 flex items-center gap-3">
                                <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                    <span class="material-symbols-outlined text-[14px]">person</span>
                                    <?= htmlspecialchars($izin->solver_name ?? 'Yönetici') ?>
                                </div>
                                <?php if (!empty($izin->islem_tarihi)): ?>
                                    <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                        <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                        <?= formatDateMobile($izin->islem_tarihi) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$showApproved): ?>
                    <div class="flex gap-2">
                        <button onclick="openModal('izinRed', <?= $izin->id ?>, '<?= htmlspecialchars(addslashes($izin->requester_name)) ?>')" class="flex-1 px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-900/20 dark:text-red-400 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">close</span> Reddet
                        </button>
                        <button onclick="openModal('izinOnay', <?= $izin->id ?>, '<?= htmlspecialchars(addslashes($izin->requester_name)) ?>', '<?= $izinTuruLabel ?>', <?= $gunSayisi ?>)" class="flex-1 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-xs font-bold shadow-sm shadow-blue-500/30 transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">check</span> Onayla
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- GENEL TALEPLER -->
    <div id="tab-content-talep" class="tab-content hidden space-y-3 mt-3">
        <?php if (empty($talepler)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm">
                <div class="w-12 h-12 <?= !$showApproved ? 'bg-indigo-50 text-indigo-400 dark:bg-indigo-900/20' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' ?> rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-2xl"><?= !$showApproved ? 'check_circle' : 'info' ?></span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white"><?= !$showApproved ? 'Bekleyen Talep Yok' : 'İşlem Gören Kayıt Yok' ?></h3>
                <p class="text-xs text-slate-500 mt-1"><?= !$showApproved ? 'Bekleyen tüm genel talepler yanıtlandı.' : 'Henüz işlem görmüş talep kaydı bulunmuyor.' ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($talepler as $talep): 
                $gtResim = !empty($talep->personel_resim_yolu) ? $talep->personel_resim_yolu : ($talep->resim_yolu ?? '');
                $talepData = [
                    'avatar' => (!empty($gtResim) && file_exists($gtResim)) ? '../' . $gtResim : '../assets/images/users/user-dummy-img.jpg',
                    'adi_soyadi' => $talep->requester_name ?? 'Bilinmeyen',
                    'departman' => $talep->departman ?? '',
                    'tarih' => formatDateMobile($talep->olusturma_tarihi),
                    'oncelik' => ucfirst($talep->oncelik ?? 'Normal'),
                    'oncelikType' => $talep->oncelik ?? '',
                    'baslik' => $talep->baslik ?? '',
                    'aciklama' => $talep->aciklama ?? 'Açıklama bulunmuyor.',
                    'durum' => $talep->durum ?? 'beklemede',
                    'cozum' => $talep->cozum_aciklama ?? '',
                    'foto' => !empty($talep->foto) ? '../' . $talep->foto : '',
                    'islem_yapan' => $talep->solver_name ?? '',
                    'islem_tarihi' => !empty($talep->cozum_tarihi) ? formatDateMobile($talep->cozum_tarihi) : ''
                ];
                $talepJson = htmlspecialchars(json_encode($talepData), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 cursor-pointer active:bg-slate-50 dark:active:bg-slate-800/50 transition-colors" onclick="openTalepDetail(this.dataset.talep)" data-talep="<?= $talepJson ?>">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <?php 
                            $gtRowResim = !empty($talep->personel_resim_yolu) ? $talep->personel_resim_yolu : ($talep->resim_yolu ?? '');
                            if (!empty($gtRowResim) && file_exists($gtRowResim)): ?>
                                <img src="../<?= htmlspecialchars($gtRowResim) ?>" class="w-8 h-8 rounded-full object-cover border border-slate-200" alt="Avatar">
                            <?php else: ?>
                                <img src="../assets/images/users/user-dummy-img.jpg" class="w-8 h-8 rounded-full object-cover border border-slate-200" alt="Avatar">
                            <?php endif; ?>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white text-xs"><?= htmlspecialchars($talep->requester_name ?? 'Bilinmeyen') ?></h3>
                                <p class="text-[10px] text-slate-400"><?= formatDateMobile($talep->olusturma_tarihi) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2 mb-3">
                        <h4 class="font-bold text-slate-800 dark:text-white text-sm leading-tight"><?= htmlspecialchars($talep->baslik ?? '') ?></h4>
                        <?php if (!empty($talep->aciklama)): ?>
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars($talep->aciklama) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800/60 pt-3 <?= !$showApproved ? 'mb-3' : '' ?>">
                        <?php
                            $oncelikType = 'bg-slate-100 text-slate-600';
                            if ($talep->oncelik == 'yuksek') $oncelikType = 'bg-red-50 text-red-600 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30';
                            if ($talep->oncelik == 'orta') $oncelikType = 'bg-orange-50 text-orange-600 dark:bg-orange-900/20 border border-orange-100 dark:border-orange-900/30';
                        ?>
                        <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg <?= $oncelikType ?>">
                            <span class="material-symbols-outlined text-[14px]">flag</span>
                            <?= ucfirst($talep->oncelik ?? 'Normal') ?>
                        </span>

                        <?php if (!$showApproved): ?>
                            <?php if ($talep->durum == 'islemde'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 rounded-lg text-xs font-bold"><span class="material-symbols-outlined text-[16px]">sync</span> İşlemde</span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 rounded-lg text-xs font-bold"><span class="material-symbols-outlined text-[16px]">schedule</span> Beklemede</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="flex items-center justify-between mt-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                        <span class="material-symbols-outlined text-[14px]">person</span>
                                        <?= htmlspecialchars($talep->solver_name ?? 'Yönetici') ?>
                                    </div>
                                    <?php if (!empty($talep->cozum_tarihi)): ?>
                                        <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                            <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                            <?= formatDateMobile($talep->cozum_tarihi) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex justify-end">
                                    <?php if ($talep->durum == 'cozuldu'): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 rounded text-[10px] font-bold border border-green-100 dark:border-green-800/30"><span class="material-symbols-outlined text-[12px]">check_circle</span> Çözüldü</span>
                                    <?php elseif ($talep->durum == 'iptal_edildi'): ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 rounded text-[10px] font-bold border border-red-100 dark:border-red-800/30"><span class="material-symbols-outlined text-[12px]">cancel</span> İptal Edildi</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 rounded text-[10px] font-bold border border-indigo-100 dark:border-indigo-800/30"><span class="material-symbols-outlined text-[12px]">info</span> <?= ucfirst($talep->durum) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$showApproved): ?>
                    <div class="flex gap-2 relative z-10" onclick="event.stopPropagation();">
                        <?php if ($talep->durum != 'islemde'): ?>
                        <button onclick="talepIslemeAl(<?= $talep->id ?>)" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">play_arrow</span> İşleme Al
                        </button>
                        <?php endif; ?>
                        <button onclick="openModal('talepCoz', <?= $talep->id ?>, '<?= htmlspecialchars(addslashes($talep->baslik ?? '')) ?>')" class="flex-1 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">task_alt</span> Çözüldü
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODALS DIALOGS OVELAY -->
<div id="modal-overlay" class="fixed inset-0 bg-slate-900/40 dark:bg-black/60 backdrop-blur-sm z-[100] hidden opacity-0 transition-opacity flex items-center justify-center p-4">
    
    <!-- Avans Onay Modal -->
    <div id="modal-avansOnay" class="bg-white dark:bg-card-dark rounded-2xl w-full max-w-sm shadow-2xl hidden transform scale-95 transition-transform duration-200">
        <div class="p-5">
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">payments</span>
            </div>
            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-1">Avansı Onayla</h3>
            <p class="text-sm text-slate-500 mb-4">
                <strong id="mo-avans-isim" class="text-slate-700 dark:text-slate-300"></strong> personelinin
                <strong id="mo-avans-tutar" class="text-slate-700 dark:text-slate-300"></strong> tutarındaki avansını onaylıyorsunuz.
            </p>
            <form id="formAvansOnay">
                <input type="hidden" name="id" id="val-avans-onay-id">
                <input type="hidden" name="action" value="avans-onayla">
                <textarea name="aciklama" class="w-full border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl text-sm p-3 focus:ring-green-500 mb-3" rows="2" placeholder="Açıklama (Opsiyonel)"></textarea>
                
                <label class="flex items-center gap-2 mb-4 p-2.5 rounded-lg bg-green-50 dark:bg-green-900/10 border border-green-100 dark:border-green-900/30 cursor-pointer">
                    <input type="checkbox" name="hesaba_isle" value="1" class="rounded text-green-500 focus:ring-green-500 w-4 h-4" checked>
                    <span class="text-xs font-bold text-green-700 dark:text-green-400 mt-0.5">Bordroya kesinti olarak işle</span>
                </label>
                
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl">İptal</button>
                    <button type="button" onclick="submitModalData('formAvansOnay')" class="flex-1 py-2.5 bg-green-500 hover:bg-green-600 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">check</span> Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Avans Red Modal -->
    <div id="modal-avansRed" class="bg-white dark:bg-card-dark rounded-2xl w-full max-w-sm shadow-2xl hidden transform scale-95 transition-transform duration-200">
        <div class="p-5">
            <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">close</span>
            </div>
            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-1">Avansı Reddet</h3>
            <p class="text-sm text-slate-500 mb-4">
                <strong id="mo-avans-red-isim" class="text-slate-700 dark:text-slate-300"></strong> personelinin avans talebini reddediyorsunuz.
            </p>
            <form id="formAvansRed">
                <input type="hidden" name="id" id="val-avans-red-id">
                <input type="hidden" name="action" value="avans-reddet">
                <textarea name="aciklama" required class="w-full border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl text-sm p-3 focus:ring-red-500 mb-4 border-l-4 border-l-red-500" rows="3" placeholder="Reddetme sebebiniz (Zorunlu)"></textarea>
                
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl">İptal</button>
                    <button type="button" onclick="submitModalData('formAvansRed')" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">close</span> Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- İzin Onay Modal -->
    <div id="modal-izinOnay" class="bg-white dark:bg-card-dark rounded-2xl w-full max-w-sm shadow-2xl hidden transform scale-95 transition-transform duration-200">
        <div class="p-5">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">event_available</span>
            </div>
            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-1">İzin Onayla</h3>
            <p class="text-sm text-slate-500 mb-4">
                <strong id="mo-izin-isim" class="text-slate-700 dark:text-slate-300"></strong> personelinin 
                <strong id="mo-izin-gun"></strong> günlük izin talebini onaylıyorsunuz.
            </p>
            <form id="formIzinOnay">
                <input type="hidden" name="id" id="val-izin-onay-id">
                <input type="hidden" name="action" value="izin-onayla">
                <textarea name="aciklama" class="w-full border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl text-sm p-3 focus:ring-blue-500 mb-4" rows="2" placeholder="Açıklama (Opsiyonel)"></textarea>
                
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl">İptal</button>
                    <button type="button" onclick="submitModalData('formIzinOnay')" class="flex-1 py-2.5 bg-blue-500 hover:bg-blue-600 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">check</span> Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- İzin Red Modal -->
    <div id="modal-izinRed" class="bg-white dark:bg-card-dark rounded-2xl w-full max-w-sm shadow-2xl hidden transform scale-95 transition-transform duration-200">
        <div class="p-5">
            <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">event_busy</span>
            </div>
            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-1">İzin Reddet</h3>
            <p class="text-sm text-slate-500 mb-4">
                <strong id="mo-izin-red-isim" class="text-slate-700 dark:text-slate-300"></strong> personelinin izin talebini reddediyorsunuz.
            </p>
            <form id="formIzinRed">
                <input type="hidden" name="id" id="val-izin-red-id">
                <input type="hidden" name="action" value="izin-reddet">
                <textarea name="aciklama" required class="w-full border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl text-sm p-3 focus:ring-red-500 mb-4 border-l-4 border-l-red-500" rows="3" placeholder="Reddetme sebebiniz (Zorunlu)"></textarea>
                
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl">İptal</button>
                    <button type="button" onclick="submitModalData('formIzinRed')" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">close</span> Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Talep Çöz Modal -->
    <div id="modal-talepCoz" class="bg-white dark:bg-card-dark rounded-2xl w-full max-w-sm shadow-2xl hidden transform scale-95 transition-transform duration-200">
        <div class="p-5">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-2xl">task_alt</span>
            </div>
            <h3 class="font-bold text-lg text-slate-800 dark:text-white mb-1">Talebi Çöz</h3>
            <p class="text-sm text-slate-500 mb-4">
                <strong id="mo-talep-baslik" class="text-slate-700 dark:text-slate-300"></strong> başlıklı talebi çözüldü olarak işaretliyorsunuz.
            </p>
            <form id="formTalepCoz">
                <input type="hidden" name="id" id="val-talep-id">
                <input type="hidden" name="action" value="talep-cozuldu">
                <textarea name="aciklama" class="w-full border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 rounded-xl text-sm p-3 focus:ring-indigo-500 mb-4" rows="3" placeholder="Nasıl çözüldü? (Opsiyonel ama tavsiye edilir)"></textarea>
                
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl">İptal</button>
                    <button type="button" onclick="submitModalData('formTalepCoz')" class="flex-1 py-2.5 bg-indigo-500 hover:bg-indigo-600 text-white font-bold text-sm rounded-xl flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">check</span> Çözüldü
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Talep Detay Bottom Sheet -->
<div id="bs-talep-overlay" class="fixed inset-0 bg-slate-900/40 dark:bg-black/60 backdrop-blur-sm z-[150] hidden opacity-0 transition-opacity duration-300" onclick="closeTalepDetail()"></div>
<div id="bs-talep-detail" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[160] transform translate-y-full transition-transform duration-300 max-h-[85vh] flex flex-col shadow-[0_-10px_40px_rgba(0,0,0,0.1)]">
    <!-- handle -->
    <div class="pt-4 pb-3 flex justify-center shrink-0 cursor-pointer" onclick="closeTalepDetail()">
        <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
    </div>
    
    <div class="px-5 pb-6 overflow-y-auto no-scrollbar" id="bs-talep-content">
        <!-- Content will be injected here -->
    </div>
</div>

<!-- Loader -->
<div id="loader" class="fixed inset-0 bg-white/50 dark:bg-black/50 backdrop-blur-sm z-[200] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white dark:bg-card-dark px-5 py-4 rounded-full shadow-2xl flex items-center gap-3 border border-slate-100 dark:border-slate-800">
        <div class="w-6 h-6 border-3 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm font-bold text-slate-800 dark:text-white mt-0.5">İşleniyor...</span>
    </div>
</div>

<script>
    const API_URL = '../views/talepler/api.php';
    let currentModal = null;

    function switchTab(tabId) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });
        
        // Reset button styles
        document.querySelectorAll('[id^=btn-tab-]').forEach(btn => {
            btn.className = "flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800";
        });
        
        // Show active tab
        document.getElementById('tab-content-' + tabId).classList.remove('hidden');
        document.getElementById('tab-content-' + tabId).classList.add('block');
        
        // Activate button style
        const activeBtn = document.getElementById('btn-tab-' + tabId);
        
        if (tabId === 'avans') {
            activeBtn.className = "flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400";
        } else if (tabId === 'izin') {
            activeBtn.className = "flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400";
        } else if (tabId === 'talep') {
            activeBtn.className = "flex-1 py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400";
        }
    }

    function openModal(type, id, param1, param2, param3) {
        // Reset any forms
        document.querySelectorAll('form').forEach(f => f.reset());
        
        const overlay = document.getElementById('modal-overlay');
        overlay.classList.remove('hidden');
        
        // Timeout to allow display:block to apply before animating opacity
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
        }, 10);

        if (type === 'avansOnay') {
            document.getElementById('val-avans-onay-id').value = id;
            document.getElementById('mo-avans-isim').textContent = param1;
            document.getElementById('mo-avans-tutar').textContent = param2;
            currentModal = document.getElementById('modal-avansOnay');
        } else if (type === 'avansRed') {
            document.getElementById('val-avans-red-id').value = id;
            document.getElementById('mo-avans-red-isim').textContent = param1;
            currentModal = document.getElementById('modal-avansRed');
        } else if (type === 'izinOnay') {
            document.getElementById('val-izin-onay-id').value = id;
            document.getElementById('mo-izin-isim').textContent = param1;
            document.getElementById('mo-izin-gun').textContent = param3 + " (" + param2 + ")";
            currentModal = document.getElementById('modal-izinOnay');
        } else if (type === 'izinRed') {
            document.getElementById('val-izin-red-id').value = id;
            document.getElementById('mo-izin-red-isim').textContent = param1;
            currentModal = document.getElementById('modal-izinRed');
        } else if (type === 'talepCoz') {
            document.getElementById('val-talep-id').value = id;
            document.getElementById('mo-talep-baslik').textContent = param1;
            currentModal = document.getElementById('modal-talepCoz');
        }

        if (currentModal) {
            currentModal.classList.remove('hidden');
            setTimeout(() => {
                currentModal.classList.remove('scale-95');
                currentModal.classList.add('scale-100');
            }, 10);
        }
    }

    function closeModal() {
        const overlay = document.getElementById('modal-overlay');
        
        if (currentModal) {
            currentModal.classList.remove('scale-100');
            currentModal.classList.add('scale-95');
        }
        
        overlay.classList.add('opacity-0');
        
        setTimeout(() => {
            overlay.classList.add('hidden');
            if (currentModal) {
                currentModal.classList.add('hidden');
                currentModal = null;
            }
        }, 200);
    }

    async function submitModalData(formId) {
        const form = document.getElementById(formId);
        if (!form.reportValidity()) return; // Check required fields

        const formData = new FormData(form);
        const loader = document.getElementById('loader');
        
        loader.classList.remove('opacity-0', 'pointer-events-none');
        closeModal();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success' || result.success) {
                window.location.reload();
            } else {
                Alert.error('Hata', result.message || 'Bir hata oluştu!');
                loader.classList.add('opacity-0', 'pointer-events-none');
            }
        } catch (error) {
            Alert.error('Sunucu Hatası', error.message);
            loader.classList.add('opacity-0', 'pointer-events-none');
        }
    }
    
    async function talepIslemeAl(id) {
        const isConfirmed = await Alert.confirm('Talebi İşleme Al', 'Talebi işleme almak istiyor musunuz?', 'İşleme Al', 'Vazgeç');
        if (!isConfirmed) return;
        
        const loader = document.getElementById('loader');
        loader.classList.remove('opacity-0', 'pointer-events-none');
        
        const formData = new FormData();
        formData.append('action', 'talep-isleme-al');
        formData.append('id', id);
        
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.status === 'success' || result.success) {
                window.location.reload();
            } else {
                Alert.error('Hata', result.message || 'Bir hata oluştu!');
                loader.classList.add('opacity-0', 'pointer-events-none');
            }
        } catch (error) {
            Alert.error('Sunucu Hatası', error.message);
            loader.classList.add('opacity-0', 'pointer-events-none');
        }
    }
    
    function openTalepDetail(jsonStr) {
        const talep = JSON.parse(jsonStr);
        let oncelikType = 'bg-slate-100 text-slate-600 border-slate-200 dark:border-slate-700';
        if (talep.oncelikType == 'yuksek') oncelikType = 'bg-red-50 text-red-600 border-red-100 dark:bg-red-900/20 dark:border-red-900/30';
        if (talep.oncelikType == 'orta') oncelikType = 'bg-orange-50 text-orange-600 border-orange-100 dark:bg-orange-900/20 dark:border-orange-900/30';
        
        let durumText = talep.durum;
        let durumBadge = '';
        if (talep.durum === 'cozuldu') { durumText = 'Çözüldü'; durumBadge = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'; }
        else if (talep.durum === 'onaylandi') { durumText = 'Onaylandı'; durumBadge = 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'; }
        else if (talep.durum === 'reddedildi') { durumText = 'Reddedildi'; durumBadge = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'; }
        else if (talep.durum === 'iptal_edildi') { durumText = 'İptal Edildi'; durumBadge = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'; }
        else if (talep.durum === 'islemde') { durumText = 'İşlemde'; durumBadge = 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'; }
        else { durumText = 'Beklemede'; durumBadge = 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'; }

        const html = `
            <div class="flex items-center gap-4 mb-5 pb-5 border-b border-slate-100 dark:border-slate-800 mt-2">
                <img src="${talep.avatar}" class="w-14 h-14 rounded-full object-cover border-2 border-slate-100 dark:border-slate-700">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-base leading-tight">${talep.adi_soyadi}</h3>
                    <p class="text-xs text-slate-500 mt-0.5">${talep.departman || 'Departman Belirtilmedi'}</p>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="text-[11px] font-bold px-2 py-1 rounded inline-flex items-center gap-1 border ${oncelikType}"><span class="material-symbols-outlined text-[14px]">priority_high</span>Öncelik: ${talep.oncelik}</span>
                <span class="text-[11px] font-bold px-2 py-1 rounded inline-flex items-center gap-1 border border-transparent ${durumBadge}"><span class="material-symbols-outlined text-[14px]">info</span>Durum: ${durumText}</span>
                <span class="text-[11px] font-bold px-2 py-1 rounded bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-100 dark:border-slate-700 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">calendar_today</span>${talep.tarih}</span>
            </div>
            
            <h4 class="font-bold text-slate-800 dark:text-white text-lg mb-2">${talep.baslik}</h4>
            
            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 mb-4 mt-3">
                <h5 class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">AÇIKLAMA</h5>
                <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${talep.aciklama}</p>
                
                ${talep.foto ? `
                <div class="mt-4">
                    <img src="${talep.foto}" class="w-full h-auto rounded-lg border border-slate-200 dark:border-slate-700 mb-2" onclick="window.open('${talep.foto}', '_blank')">
                    <p class="text-[10px] text-slate-400 text-center italic">Resmi büyütmek için tıklayın</p>
                </div>
                ` : ''}
            </div>
            
            ${talep.cozum ? `
            <div class="bg-green-50 dark:bg-green-900/10 rounded-xl p-4 border border-green-100 dark:border-green-900/30">
                <h5 class="text-xs font-bold text-green-600 dark:text-green-500 uppercase tracking-wider mb-2">ÇÖZÜM / SONUÇ</h5>
                <p class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">${talep.cozum}</p>
                
                <div class="mt-4 pt-3 border-t border-green-100/60 dark:border-green-800/50 flex items-center gap-4">
                    <div class="flex items-center gap-1.5 text-[10px] text-green-600/70 dark:text-green-400/60 font-medium">
                        <span class="material-symbols-outlined text-[16px]">person</span>
                        ${talep.islem_yapan || 'Yönetici'}
                    </div>
                    ${talep.islem_tarihi ? `
                    <div class="flex items-center gap-1.5 text-[10px] text-green-600/70 dark:text-green-400/60 font-medium">
                        <span class="material-symbols-outlined text-[16px]">calendar_today</span>
                        ${talep.islem_tarihi}
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}
        `;
        
        document.getElementById('bs-talep-content').innerHTML = html;
        
        const overlay = document.getElementById('bs-talep-overlay');
        const sheet = document.getElementById('bs-talep-detail');
        
        overlay.classList.remove('hidden');
        setTimeout(() => {
            overlay.classList.remove('opacity-0');
            sheet.classList.remove('translate-y-full');
        }, 10);
    }
    
    function closeTalepDetail() {
        const overlay = document.getElementById('bs-talep-overlay');
        const sheet = document.getElementById('bs-talep-detail');
        
        overlay.classList.add('opacity-0');
        sheet.classList.add('translate-y-full');
        
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 300);
    }

    // Close modal on outside click
    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Auto-select tab if in URL Hash (e.g. #izin)
    if (window.location.hash) {
        const hash = window.location.hash.replace('#', '');
        if (['avans', 'izin', 'talep'].includes(hash)) {
            switchTab(hash);
        }
    }

    // Toggle Link behavior: Append hash to keep tab state
    document.querySelectorAll('.toggle-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // If already active, prevent navigation
            if (this.classList.contains('pointer-events-none') || this.classList.contains('bg-[#ffca58]') || this.classList.contains('bg-slate-100') && this.classList.contains('text-slate-800')) {
                return;
            }
            
            document.getElementById('loader').classList.remove('opacity-0', 'pointer-events-none');
            const href = this.getAttribute('href');
            let activeTab = 'avans';
            document.querySelectorAll('.tab-content').forEach(el => {
                if (!el.classList.contains('hidden')) {
                    activeTab = el.id.replace('tab-content-', '');
                }
            });
            window.location.href = href + '#' + activeTab;
        });
    });
</script>
