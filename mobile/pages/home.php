<?php
/**
 * Mobil Admin — Ana Sayfa
 * Modeller views/home.php ile aynı PHP modelleri kullanır, kod tekrarı yoktur.
 */

use App\Model\PersonelModel;
use App\Model\AracModel;
use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Service\SayacDegisimService;

$personelModel = new PersonelModel();
$aracModel     = new AracModel();
$puantajModel  = new PuantajModel();
$endeksModel   = new EndeksOkumaModel();

// Personel istatistikleri (personelSayilari() — views/home.php ile aynı çağrı)
$istatistik = $personelModel->personelSayilari();
$toplam_p   = (int) ($istatistik->toplam_personel ?? 0);
$aktif_p    = (int) ($istatistik->aktif_personel  ?? 0);
$pasif_p    = (int) ($istatistik->pasif_personel  ?? 0);

// Araç istatistikleri (getStats() + getServistekiAracSayisi() — views/home.php ile aynı)
$aracStats       = $aracModel->getStats();
$toplam_aktif_a  = (int) ($aracStats->aktif_arac ?? 0);
$bosta_arac      = (int) ($aracStats->bosta_arac ?? 0);
$servisteki_arac = (int) $aracModel->getServistekiAracSayisi();
$saha_arac       = max(0, $toplam_aktif_a - $servisteki_arac - $bosta_arac);

// Progress yüzdeleri
$p_div          = $toplam_p ?: 1;
$a_div          = $toplam_aktif_a ?: 1;
$aktif_p_yuzde  = round(($aktif_p  / $p_div) * 100);
$pasif_p_yuzde  = round(($pasif_p  / $p_div) * 100);
$saha_a_yuzde   = round(($saha_arac       / $a_div) * 100);
$servis_a_yuzde = round(($servisteki_arac / $a_div) * 100);
$bosta_a_yuzde  = round(($bosta_arac      / $a_div) * 100);

// Operasyonel istatistikler (views/home.php ile aynı model kaynakları)
$dailyWorkStats      = $puantajModel->getDailyStats();
$monthlyWorkStats    = $puantajModel->getMonthlyStats();
$dailyReadingTotal   = (int) ($endeksModel->getDailyStats() ?? 0);
$monthlyReadingTotal = (int) ($endeksModel->getMonthlyStats() ?? 0);
$kacakDailyTotal     = $puantajModel->getKacakDailyStats();
$kacakMonthlyTotal   = $puantajModel->getKacakMonthlyStats();

$sayacDegisimService = new SayacDegisimService();
$sayacDailyStats     = $sayacDegisimService->getDailyStats();
$sayacMonthlyStats   = $sayacDegisimService->getMonthlyStats();
if ($dailyWorkStats) {
    $dailyWorkStats->sayac_degisimi = (int) ($sayacDailyStats->sayac_degisimi ?? 0);
}
if ($monthlyWorkStats) {
    $monthlyWorkStats->sayac_degisimi = (int) ($sayacMonthlyStats->sayac_degisimi ?? 0);
}

$last_update_endeks = $last_update_isler = $last_update_sayac = null;
try {
    $dbForUpdates = $personelModel->getDb();
    $stmt_updates = $dbForUpdates->prepare("SELECT
            (SELECT MAX(created_at)
             FROM endeks_okuma
             WHERE firma_id = :firma_id
               AND created_at >= CURDATE()
               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_endeks,
            (SELECT MAX(created_at)
             FROM yapilan_isler
             WHERE firma_id = :firma_id
               AND created_at >= CURDATE()
               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_isler,
            (SELECT MAX(GREATEST(created_at,guncelleme_tarihi))
             FROM sayac_degisim
             WHERE firma_id = :firma_id
               AND created_at >= CURDATE()
               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS last_update_sayac");
    $stmt_updates->execute([':firma_id' => $_SESSION['firma_id'] ?? 0]);
    $updates = $stmt_updates->fetch(\PDO::FETCH_ASSOC) ?: [];
    $last_update_endeks = $updates['last_update_endeks'] ?? null;
    $last_update_isler  = $updates['last_update_isler'] ?? null;
    $last_update_sayac  = $updates['last_update_sayac'] ?? null;
} catch (\Exception $e) {
}

$operasyonKartlari = [
    [
        'title' => 'Gunluk Muhurleme',
        'icon' => 'shield',
        'daily' => (int) ($dailyWorkStats->muhurleme ?? 0),
        'monthly' => (int) ($monthlyWorkStats->muhurleme ?? 0),
        'sub_daily' => 'Bugun yapilan muhurlleme',
        'sub_monthly' => 'Bu ay yapilan muhurlleme',
        'last_update' => $last_update_isler,
        'link' => '../index.php?p=puantaj/raporlar&tab=muhurleme',
        'color_bg' => 'bg-slate-100 dark:bg-slate-700/70',
        'color_text' => 'text-slate-600',
        'color_border' => 'border-slate-300 dark:border-slate-600',
        'btn_class' => 'text-slate-500 border-slate-300'
    ],
    [
        'title' => 'Gunluk Kesme Acma',
        'icon' => 'content_cut',
        'daily' => (int) ($dailyWorkStats->kesme_acma ?? 0),
        'monthly' => (int) ($monthlyWorkStats->kesme_acma ?? 0),
        'sub_daily' => 'Bugun yapilan kesme/acma',
        'sub_monthly' => 'Bu ay yapilan kesme/acma',
        'last_update' => $last_update_isler,
        'link' => '../index.php?p=puantaj/raporlar&tab=kesme',
        'color_bg' => 'bg-red-50 dark:bg-red-900/20',
        'color_text' => 'text-red-600',
        'color_border' => 'border-red-300 dark:border-red-700/60',
        'btn_class' => 'text-red-500 border-red-300'
    ],
    [
        'title' => 'Gunluk Endeks Okuma',
        'icon' => 'speed',
        'daily' => $dailyReadingTotal,
        'monthly' => $monthlyReadingTotal,
        'sub_daily' => 'Bugun okunan endeksler',
        'sub_monthly' => 'Bu ay okunan endeksler',
        'last_update' => $last_update_endeks,
        'link' => '../index.php?p=puantaj/raporlar&tab=okuma',
        'color_bg' => 'bg-cyan-50 dark:bg-cyan-900/20',
        'color_text' => 'text-cyan-600',
        'color_border' => 'border-cyan-300 dark:border-cyan-700/60',
        'btn_class' => 'text-cyan-500 border-cyan-300'
    ],
    [
        'title' => 'Gunluk Sayac Degisimi',
        'icon' => 'autorenew',
        'daily' => (int) ($dailyWorkStats->sayac_degisimi ?? 0),
        'monthly' => (int) ($monthlyWorkStats->sayac_degisimi ?? 0),
        'sub_daily' => 'Bugun yapilan sayac degisimi',
        'sub_monthly' => 'Bu ay yapilan sayac degisimi',
        'last_update' => $last_update_sayac,
        'link' => '../index.php?p=puantaj/raporlar&tab=sokme_takma',
        'color_bg' => 'bg-emerald-50 dark:bg-emerald-900/20',
        'color_text' => 'text-emerald-600',
        'color_border' => 'border-emerald-300 dark:border-emerald-700/60',
        'btn_class' => 'text-emerald-500 border-emerald-300'
    ],
    [
        'title' => 'Gunluk Kacak',
        'icon' => 'error',
        'daily' => (int) ($kacakDailyTotal->toplam ?? 0),
        'monthly' => (int) ($kacakMonthlyTotal->toplam ?? 0),
        'sub_daily' => 'Bugun tespit edilen/girilen',
        'sub_monthly' => 'Bu ay tespit edilen/girilen',
        'last_update' => null,
        'link' => '../index.php?p=puantaj/raporlar&tab=kacakkontrol',
        'color_bg' => 'bg-rose-50 dark:bg-rose-900/20',
        'color_text' => 'text-rose-600',
        'color_border' => 'border-rose-300 dark:border-rose-700/60',
        'btn_class' => 'text-rose-500 border-rose-300'
    ],
];

// Bekleyen onaylar
try {
    $db  = $personelModel->getDb();
    $st1 = $db->prepare("SELECT COUNT(*) FROM personel_talepleri WHERE durum != 'cozuldu' AND silinme_tarihi IS NULL AND firma_id = ?");
    $st1->execute([$_SESSION['firma_id']]);
    $bekleyen_talep = (int) $st1->fetchColumn();

    $st2 = $db->prepare("SELECT COUNT(*) FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
    $st2->execute();
    $bekleyen_avans = (int) $st2->fetchColumn();

    $st3 = $db->prepare("SELECT COUNT(*) FROM personel_izinleri WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
    $st3->execute();
    $bekleyen_izin = (int) $st3->fetchColumn();
} catch (\Exception $e) {
    $bekleyen_talep = $bekleyen_avans = $bekleyen_izin = 0;
}

$toplam_bekleyen = $bekleyen_talep + $bekleyen_avans + $bekleyen_izin;
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-primary text-white px-4 pt-4 pb-10 rounded-b-3xl relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-52 h-52 bg-white rounded-full -mr-24 -mt-24"></div>
        <div class="absolute bottom-0 left-0 w-36 h-36 bg-white rounded-full -ml-16 -mb-16"></div>
    </div>
    <div class="relative z-10">
        <p class="text-white/70 text-sm">
            <?php
            $h = (int) date('H');
            echo $h < 12 ? 'Günaydın!' : ($h < 18 ? 'İyi günler!' : 'İyi akşamlar!');
            ?>
        </p>
        <h2 class="text-lg font-bold leading-tight">
            <?php 
            $displayName = $_SESSION['user_full_name'] ?? '';
            if (empty($displayName) && isset($_SESSION['user'])) {
                $displayName = $_SESSION['user']->adi_soyadi ?? 'Yönetici';
            }
            echo htmlspecialchars($displayName ?: 'Yönetici');
            ?>
        </h2>
        <p class="text-white/60 text-xs mt-0.5"><?= date('d.m.Y') ?> – Yönetim Paneli</p>
    </div>
</header>

<div class="px-4 mt-[-24px] relative z-10 space-y-3 pb-4">

    <!-- Personel Durumu Kartı -->
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-4">
        <div class="flex items-start justify-between mb-2">
            <div>
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wide">Personel Durumu</p>
                <p class="text-[11px] text-slate-400">Toplam Personel</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-tight">
                    <?= $toplam_p ?>
                    <span class="text-sm font-normal text-slate-400">adet</span>
                </p>
            </div>
            <a href="../index.php?p=personel/list"
                class="text-primary text-xs font-semibold active:opacity-70 transition-opacity">
                Personel Listesine Git
            </a>
        </div>
        <!-- Progress -->
        <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden flex mb-3">
            <div class="h-full bg-primary transition-all duration-500" style="width:<?= $aktif_p_yuzde ?>%"></div>
            <div class="h-full bg-slate-300 dark:bg-slate-500 transition-all duration-500" style="width:<?= $pasif_p_yuzde ?>%"></div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2">
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-primary rounded-full flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] text-slate-400">Saha Görevlisi</p>
                    <p class="font-bold text-slate-900 dark:text-white text-sm"><?= $aktif_p ?> Adet</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-slate-300 dark:bg-slate-500 rounded-full flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] text-slate-400">Pasif</p>
                    <p class="font-bold text-slate-900 dark:text-white text-sm"><?= $pasif_p ?> Adet</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Araç Durumu Kartı -->
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-4">
        <div class="flex items-start justify-between mb-2">
            <div>
                <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wide">Araç Durumu</p>
                <p class="text-[11px] text-slate-400">Toplam Aktif Araç</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white leading-tight">
                    <?= $toplam_aktif_a ?>
                    <span class="text-sm font-normal text-slate-400">adet</span>
                </p>
            </div>
            <a href="../index.php?p=arac-takip/list"
                class="text-primary text-xs font-semibold active:opacity-70 transition-opacity">
                Araç Listesine Git
            </a>
        </div>
        <!-- Progress -->
        <div class="w-full h-3 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-700 mb-3">
            <div class="h-full bg-emerald-500 transition-all duration-500" style="width:<?= $saha_a_yuzde ?>%"></div>
            <div class="h-full bg-red-500 transition-all duration-500"     style="width:<?= $servis_a_yuzde ?>%"></div>
            <div class="h-full bg-amber-400 transition-all duration-500"   style="width:<?= $bosta_a_yuzde ?>%"></div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-2">
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-emerald-500 rounded-full flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] text-slate-400">Saha Aracı</p>
                    <p class="font-bold text-slate-900 dark:text-white text-sm"><?= $saha_arac ?> Adet</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-red-500 rounded-full flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] text-slate-400">Serviste</p>
                    <p class="font-bold text-slate-900 dark:text-white text-sm"><?= $servisteki_arac ?> Adet</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-1 h-8 bg-amber-400 rounded-full flex-shrink-0"></div>
                <div>
                    <p class="text-[11px] text-slate-400">Boşta</p>
                    <p class="font-bold text-slate-900 dark:text-white text-sm"><?= $bosta_arac ?> Adet</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Operasyonel Istatistikler (minimal) -->
    <section class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-3">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">monitoring</span>
                Operasyonel Istatistikler
            </h3>
        </div>

        <div class="grid grid-cols-1 gap-2">
            <?php foreach ($operasyonKartlari as $kart): ?>
                <article class="border rounded-xl p-2.5 <?= $kart['color_border'] ?> <?= $kart['color_bg'] ?>"
                    data-op-card
                    data-daily="<?= (int) $kart['daily'] ?>"
                    data-monthly="<?= (int) $kart['monthly'] ?>"
                    data-sub-daily="<?= htmlspecialchars($kart['sub_daily']) ?>"
                    data-sub-monthly="<?= htmlspecialchars($kart['sub_monthly']) ?>">

                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] <?= $kart['color_text'] ?>"><?= $kart['icon'] ?></span>
                            <p class="text-[11px] uppercase tracking-wide font-semibold text-slate-500 dark:text-slate-300 truncate">
                                <?= htmlspecialchars($kart['title']) ?>
                            </p>
                        </div>
                        <p class="text-2xl leading-none font-extrabold text-slate-900 dark:text-white op-stat-value">
                            <?= (int) $kart['daily'] ?>
                        </p>
                    </div>

                    <div class="mt-1.5 flex items-center justify-between gap-2">
                        <p class="text-[11px] text-slate-500 dark:text-slate-300 truncate op-stat-sub">
                            <?= htmlspecialchars($kart['sub_daily']) ?>
                        </p>
                        <a href="<?= htmlspecialchars($kart['link']) ?>" class="text-[11px] font-semibold <?= $kart['btn_class'] ?> border rounded-full px-2 py-0.5 flex-shrink-0">
                            Git
                        </a>
                    </div>

                    <div class="mt-2 inline-flex rounded-md border border-slate-200 dark:border-slate-700 overflow-hidden" role="group">
                        <button type="button" class="px-2 py-0.5 text-[10px] font-semibold bg-white dark:bg-slate-800 op-stat-btn" data-mode="daily">Gun</button>
                        <button type="button" class="px-2 py-0.5 text-[10px] font-semibold bg-transparent op-stat-btn" data-mode="monthly">Ay</button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($toplam_bekleyen > 0): ?>
    <!-- Bekleyen Onay Uyarı Kartı -->
    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-2xl p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-800/40 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-amber-600 text-xl">assignment_late</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-amber-900 dark:text-amber-200 text-sm">Bekleyen Onaylar</p>
                <p class="text-xs text-amber-700 dark:text-amber-300 mt-0.5">
                    <?php
                    $parts = [];
                    if ($bekleyen_avans > 0) $parts[] = "$bekleyen_avans avans";
                    if ($bekleyen_izin  > 0) $parts[] = "$bekleyen_izin izin";
                    if ($bekleyen_talep > 0) $parts[] = "$bekleyen_talep talep";
                    echo implode(', ', $parts) . ' onay bekliyor';
                    ?>
                </p>
            </div>
            <a href="../index.php?p=personel/list"
                class="text-amber-600 font-semibold text-xs flex-shrink-0 active:opacity-70">
                Görüntüle
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hızlı Erişim -->
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-4">
        <h3 class="font-bold text-slate-900 dark:text-white text-sm mb-3">Hızlı Erişim</h3>
        <div class="grid grid-cols-4 gap-2">
            <a href="?p=personel"
                class="flex flex-col items-center gap-1.5 p-2 rounded-xl active:scale-95 active:bg-slate-50 dark:active:bg-slate-700 transition-transform">
                <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-2xl">group</span>
                </div>
                <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 text-center leading-tight">Personel</span>
            </a>
            <a href="?p=gorevler"
                class="flex flex-col items-center gap-1.5 p-2 rounded-xl active:scale-95 active:bg-slate-50 dark:active:bg-slate-700 transition-transform">
                <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-purple-600 text-2xl">task_alt</span>
                </div>
                <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 text-center leading-tight">Görevler</span>
            </a>
            <a href="?p=arac"
                class="flex flex-col items-center gap-1.5 p-2 rounded-xl active:scale-95 active:bg-slate-50 dark:active:bg-slate-700 transition-transform">
                <div class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 text-2xl">directions_car</span>
                </div>
                <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 text-center leading-tight">Araç</span>
            </a>
            <a href="?p=talepler"
                class="flex flex-col items-center gap-1.5 p-2 rounded-xl active:scale-95 active:bg-slate-50 dark:active:bg-slate-700 transition-transform">
                <div class="w-12 h-12 rounded-xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-orange-600 text-2xl">assignment</span>
                </div>
                <span class="text-[10px] font-semibold text-slate-600 dark:text-slate-300 text-center leading-tight">Talepler</span>
            </a>
        </div>
    </div>

</div>

<script>
document.querySelectorAll('[data-op-card]').forEach(function(card) {
    var valueEl = card.querySelector('.op-stat-value');
    var subEl = card.querySelector('.op-stat-sub');
    var buttons = card.querySelectorAll('.op-stat-btn');

    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var mode = btn.getAttribute('data-mode');
            var isDaily = mode === 'daily';

            valueEl.textContent = isDaily ? card.getAttribute('data-daily') : card.getAttribute('data-monthly');
            subEl.textContent = isDaily ? card.getAttribute('data-sub-daily') : card.getAttribute('data-sub-monthly');

            buttons.forEach(function(otherBtn) {
                var active = otherBtn === btn;
                otherBtn.classList.toggle('bg-white', active);
                otherBtn.classList.toggle('dark:bg-slate-800', active);
            });
        });
    });
});
</script>
