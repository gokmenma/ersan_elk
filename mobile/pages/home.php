<?php
/**
 * Mobil Admin — Ana Sayfa
 * Modeller views/home.php ile aynı PHP modelleri kullanır, kod tekrarı yoktur.
 */

use App\Model\PersonelModel;
use App\Model\AracModel;
use App\Model\PuantajModel;
use App\Model\EndeksOkumaModel;
use App\Model\PersonelHareketleriModel;
use App\Service\SayacDegisimService;

$personelModel = new PersonelModel();
$aracModel     = new AracModel();
$puantajModel  = new PuantajModel();
$endeksModel   = new EndeksOkumaModel();
$hareketModel  = new PersonelHareketleriModel();

// Personel istatistikleri (Pasif hariç, Total = Aktif + İzinli)
$istatistik      = $personelModel->personelSayilari();
$aktif_p         = (int) ($istatistik->aktif_personel  ?? 0); // Toplam Employed
$advStats        = $personelModel->getAdvancedDashboardStats();
$izinli_p        = (int) ($advStats->izinli_personel ?? 0); // İzinli
$gec_kalan_p     = (int) $hareketModel->getGecKalanlarCount($_SESSION['firma_id'] ?? 0); // Geç Kalanlar
$calisan_p       = max(0, $aktif_p - $izinli_p); // Aktif çalışan (sahadaki + ofis)
$toplam_p        = $aktif_p; // Kullanıcı isteği: Pasif hariç toplam

// Araç istatistikleri (getStats() + getServistekiAracSayisi() — views/home.php ile aynı)
$aracStats       = $aracModel->getStats();
$toplam_aktif_a  = (int) ($aracStats->aktif_arac ?? 0);
$bosta_arac      = (int) ($aracStats->bosta_arac ?? 0);
$servisteki_arac = (int) $aracModel->getServistekiAracSayisi();
$saha_arac       = max(0, $toplam_aktif_a - $servisteki_arac - $bosta_arac);

// Progress yüzdeleri
$p_div          = $toplam_p ?: 1;
$a_div          = $toplam_aktif_a ?: 1;
$aktif_p_yuzde  = round(($calisan_p / $p_div) * 100);
$izinli_p_yuzde = round(($izinli_p  / $p_div) * 100);
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

$widgets = [];

// Personel Durumu
if (\App\Service\Gate::allows("personel_listesi")) {
    ob_start(); ?>
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
            <div class="h-full bg-amber-400 transition-all duration-500" style="width:<?= $izinli_p_yuzde ?>%"></div>
        </div>
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-1">
            <div class="flex items-center gap-1.5">
                <div class="w-1 h-8 bg-primary rounded-full flex-shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 truncate">Saha Görevlisi</p>
                    <p class="font-bold text-slate-900 dark:text-white text-xs"><?= $calisan_p ?> Adet</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-1 h-8 bg-amber-400 rounded-full flex-shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 truncate">İzinli</p>
                    <p class="font-bold text-slate-900 dark:text-white text-xs"><?= $izinli_p ?> Adet</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-1 h-8 bg-red-500 rounded-full flex-shrink-0"></div>
                <div class="min-w-0">
                    <p class="text-[10px] text-slate-400 truncate">Geç Kalan</p>
                    <p class="font-bold text-slate-900 dark:text-white text-xs"><?= $gec_kalan_p ?> Adet</p>
                </div>
            </div>
        </div>
    </div>
    <?php $widgets['personel-durumu'] = ob_get_clean();
}

// Araç Durumu
if (\App\Service\Gate::allows("arac_takip_yonetim")) {
    ob_start(); ?>
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
    <?php $widgets['arac-durumu'] = ob_get_clean();
}

$operasyonKartlari = [
    [
        'id' => 'widget-gunluk-muhurleme',
        'title' => 'Gunluk Muhurleme',
        'icon' => 'shield',
        'daily' => (int) ($dailyWorkStats->muhurleme ?? 0),
        'monthly' => (int) ($monthlyWorkStats->muhurleme ?? 0),
        'sub_daily' => 'Bugun yapilan muhurleme',
        'sub_monthly' => 'Bu ay yapilan muhurleme',
        'last_update' => $last_update_isler,
        'link' => '../index.php?p=puantaj/raporlar&tab=muhurleme',
        'color_bg' => 'bg-slate-100 dark:bg-slate-700/70',
        'color_text' => 'text-slate-600',
        'color_border' => 'border-slate-300 dark:border-slate-600',
        'btn_class' => 'text-slate-500 border-slate-300',
        'api_action' => 'online-puantaj-sorgula',
        'api_tab' => 'muhurleme'
    ],
    [
        'id' => 'widget-gunluk-kesme-acma',
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
        'btn_class' => 'text-red-500 border-red-300',
        'api_action' => 'online-puantaj-sorgula',
        'api_tab' => 'kesme'
    ],
    [
        'id' => 'widget-gunluk-endeks-okuma',
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
        'btn_class' => 'text-cyan-500 border-cyan-300',
        'api_action' => 'online-icmal-sorgula',
        'api_tab' => ''
    ],
    [
        'id' => 'widget-gunluk-sayac-degisimi',
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
        'btn_class' => 'text-emerald-500 border-emerald-300',
        'api_action' => 'online-sayac-degisim-sorgula',
        'api_tab' => 'sokme_takma'
    ],
    [
        'id' => 'widget-kacak-sayisi',
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
        'btn_class' => 'text-rose-500 border-rose-300',
        'api_action' => '',
        'api_tab' => ''
    ],
];

// Operasyonel Istatistikler (minimal)
ob_start(); ?>
<section class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-3">
    <div class="flex items-center justify-between mb-2">
        <h3 class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">monitoring</span>
            Operasyonel İstatistikler
        </h3>
    </div>

    <div class="grid grid-cols-1 gap-2">
        <?php foreach ($operasyonKartlari as $kart): ?>
            <article class="border rounded-xl p-2.5 <?= $kart['color_border'] ?> <?= $kart['color_bg'] ?>"
                id="<?= $kart['id'] ?>"
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
                    <div class="flex items-center gap-2">
                        <p class="text-2xl leading-none font-extrabold text-slate-900 dark:text-white op-stat-value">
                            <?= (int) $kart['daily'] ?>
                        </p>
                    </div>
                </div>

                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <p class="text-[11px] text-slate-500 dark:text-slate-300 truncate op-stat-sub">
                        <?= htmlspecialchars($kart['sub_daily']) ?>
                    </p>
                    <div class="flex items-center gap-2">
                        <?php if (!empty($kart['api_action'])): ?>
                            <button type="button" class="btn-api-sync text-[11px] font-semibold text-primary/80 border border-primary/20 rounded-full px-2 py-0.5 flex-shrink-0 active:scale-95 transition-transform flex items-center gap-1" 
                                    data-action="<?= $kart['api_action'] ?>" 
                                    data-active-tab="<?= $kart['api_tab'] ?>">
                                <span class="material-symbols-outlined text-[14px]">refresh</span>
                                Yenile
                            </button>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($kart['link']) ?>" class="text-[11px] font-semibold <?= $kart['btn_class'] ?> border rounded-full px-2 py-0.5 flex-shrink-0">
                            Git
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-2">
                    <div class="inline-flex rounded-md border border-slate-200 dark:border-slate-700 overflow-hidden" role="group">
                        <button type="button" class="px-2 py-0.5 text-[10px] font-semibold bg-white dark:bg-slate-800 op-stat-btn" data-mode="daily">Gün</button>
                        <button type="button" class="px-2 py-0.5 text-[10px] font-semibold bg-transparent op-stat-btn" data-mode="monthly">Ay</button>
                    </div>
                    <?php if ($kart['last_update']): ?>
                        <div class="text-[9px] text-slate-400 dark:text-slate-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[12px]">schedule</span>
                            Son: <span class="font-bold last-update-value"><?= date('d.m.Y H:i', strtotime($kart['last_update'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php $widgets['operasyonel-istatistikler'] = ob_get_clean();


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

// Bugünün nöbetçileri
try {
    $db_nobet = $personelModel->getDb();
    $stmt_nobet = $db_nobet->prepare("SELECT n.*, p.adi_soyadi, p.cep_telefonu, p.resim_yolu
                                FROM nobetler n 
                                JOIN personel p ON n.personel_id = p.id 
                                WHERE n.nobet_tarihi = CURDATE() 
                                AND n.firma_id = :firma_id 
                                AND n.silinme_tarihi IS NULL 
                                AND (n.durum IS NULL OR n.durum NOT IN ('talep_edildi', 'reddedildi', 'iptal'))
                                AND n.yonetici_onayi = 1
                                ORDER BY n.baslangic_saati ASC");
    $stmt_nobet->execute([':firma_id' => $_SESSION['firma_id'] ?? 0]);
    $bugunku_nobetler = $stmt_nobet->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $bugunku_nobetler = [];
}
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

    <?php
    // Widget'ları sırayla bas
    $render_order = ['personel-durumu', 'arac-durumu', 'operasyonel-istatistikler'];
    foreach ($render_order as $w_id) {
        if (isset($widgets[$w_id])) {
            echo $widgets[$w_id];
        }
    }
    ?>

    <!-- Bugünün Nöbetçileri -->
    <?php if (!empty($bugunku_nobetler)): ?>
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-[20px] text-primary">event_busy</span>
                Bugünün Nöbetçileri
            </h3>
            <span class="text-[10px] bg-primary/10 text-primary px-2 py-0.5 rounded-full font-bold uppercase"><?= count($bugunku_nobetler) ?> KİŞİ</span>
        </div>
        <div class="space-y-3">
            <?php foreach ($bugunku_nobetler as $nobet): ?>
            <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold overflow-hidden">
                        <?php if (!empty($nobet['resim_yolu']) && file_exists($nobet['resim_yolu'])): ?>
                            <img src="../<?= $nobet['resim_yolu'] ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= mb_substr($nobet['adi_soyadi'], 0, 1) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight"><?= htmlspecialchars($nobet['adi_soyadi']) ?></p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            <?= date('H:i', strtotime($nobet['baslangic_saati'])) ?> - <?= date('H:i', strtotime($nobet['bitis_saati'])) ?>
                        </p>
                    </div>
                </div>
                <?php if (!empty($nobet['cep_telefonu'])): ?>
                <a href="tel:<?= $nobet['cep_telefonu'] ?>" class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">call</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($toplam_bekleyen > 0 && \App\Service\Gate::allows("talepler")): ?>
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
$(document).ready(function() {
    // Mode toggle logic (Day/Month)
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

    // API Sync Logic
    $(document).on('click', '.btn-api-sync', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const $icon = $btn.find('span');
        const action = $btn.data('action');
        const today = '<?= date('Y-m-d') ?>';
        const firmaKodu = '<?= $_SESSION['firma_kodu'] ?? 17 ?>';

        if ($btn.hasClass('syncing')) return;

        $btn.addClass('syncing');
        $icon.addClass('animate-spin text-primary');

        $.ajax({
            url: '../views/puantaj/api.php',
            type: 'POST',
            data: {
                action: action,
                active_tab: $btn.data('active-tab') || '',
                baslangic_tarihi: today,
                bitis_tarihi: today,
                ilk_firma: firmaKodu,
                son_firma: firmaKodu
            },
            success: function (response) {
                try {
                    const res = typeof response === 'object' ? response : JSON.parse(response);
                    if (res.status === 'success') {
                        let msg = res.message || (res.yeni_kayit || 0) + ' adet yeni kayıt eklendi.';
                        
                        refreshMobileOperationalStats().always(function () {
                            $btn.removeClass('syncing');
                            $icon.removeClass('animate-spin text-primary');
                            Alert.show({
                                icon: 'success',
                                title: 'Sorgulama Başarılı',
                                content: msg,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        });
                    } else {
                        $btn.removeClass('syncing');
                        $icon.removeClass('animate-spin text-primary');
                        Alert.error('Hata', res.message || 'Sorgulama sırasında bir hata oluştu.');
                    }
                } catch (err) {
                    $btn.removeClass('syncing');
                    $icon.removeClass('animate-spin text-primary');
                    console.error("API Response Error:", err);
                    Alert.error('Hata', 'Sunucudan geçersiz yanıt alındı.');
                }
            },
            error: function () {
                $btn.removeClass('syncing');
                $icon.removeClass('animate-spin text-primary');
                Alert.error('Hata', 'Bağlantı hatası oluştu.');
            }
        });
    });

    function refreshMobileOperationalStats() {
        return $.ajax({
            url: '../views/home/api.php',
            type: 'POST',
            data: { action: 'get-dashboard-operational-stats' }
        }).done(function (response) {
            const res = typeof response === 'object' ? response : JSON.parse(response);
            if (res.status !== 'success' || !res.data) return;

            const daily = res.data.daily || {};
            const monthly = res.data.monthly || {};
            const lastUpdate = res.data.last_update || {};

            updateMobileWidget('widget-gunluk-muhurleme', daily.muhurleme, monthly.muhurleme, lastUpdate.isler);
            updateMobileWidget('widget-gunluk-kesme-acma', daily.kesme_acma, monthly.kesme_acma, lastUpdate.isler);
            updateMobileWidget('widget-gunluk-endeks-okuma', daily.endeks_okuma, monthly.endeks_okuma, lastUpdate.endeks);
            updateMobileWidget('widget-gunluk-sayac-degisimi', daily.sayac_degisimi, monthly.sayac_degisimi, lastUpdate.sayac);
            updateMobileWidget('widget-kacak-sayisi', daily.toplam, monthly.toplam, null);
        });
    }

    function updateMobileWidget(id, daily, monthly, lastUpdate) {
        const $card = $('#' + id);
        if (!$card.length) return;

        $card.attr('data-daily', daily || 0);
        $card.attr('data-monthly', monthly || 0);

        const activeMode = $card.find('.op-stat-btn.bg-white').data('mode') || 'daily';
        const displayValue = activeMode === 'daily' ? daily : monthly;
        $card.find('.op-stat-value').text(displayValue || 0);

        if (lastUpdate) {
            const date = new Date(lastUpdate);
            const formattedDate = date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
            $card.find('.last-update-value').text(formattedDate);
        }
    }
});
</script>

