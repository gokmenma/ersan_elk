<?php
use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Model\AracServisModel;

$Arac = new AracModel();
$Zimmet = new AracZimmetModel();
$Yakit = new AracYakitModel();
$Km = new AracKmModel();
$Servis = new AracServisModel();

$aracStats = $Arac->getStats();
$evrakStats = $Arac->getAracEvrakStats(30);
$zimmetliSayi = $Arac->getZimmetliAracSayisi();
$servistekiSayi = $Arac->getServistekiAracSayisi();

$araclar = $Arac->getAktifAraclar() ?? [];
$zimmetler = $Zimmet->all() ?? [];
$servisler = $Servis->all() ?? [];

// Filtreleme Periyodu
$period = $_GET['period'] ?? 'this_month';
$selectedAy = $_GET['ay'] ?? date('m');
$selectedYil = $_GET['yil'] ?? date('Y');

$todayDate = date('Y-m-d');
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');
$firstDayOfLastMonth = date('Y-m-01', strtotime('first day of last month'));
$lastDayOfLastMonth = date('Y-m-t', strtotime('last day of last month'));

if ($period == 'today') {
    $yakitlar = $Yakit->getByDateRange($todayDate, $todayDate);
    $kmlar = $Km->getByDateRange($todayDate, $todayDate);
} elseif ($period == 'this_month') {
    $yakitlar = $Yakit->getByDateRange($firstDayOfMonth, $lastDayOfMonth);
    $kmlar = $Km->getByDateRange($firstDayOfMonth, $lastDayOfMonth);
} elseif ($period == 'custom') {
    $firstDay = "$selectedYil-$selectedAy-01";
    $lastDay = date('Y-m-t', strtotime($firstDay));
    $yakitlar = $Yakit->getByDateRange($firstDay, $lastDay);
    $kmlar = $Km->getByDateRange($firstDay, $lastDay);
} else {
    $yakitlar = $Yakit->all() ?? [];
    $kmlar = $Km->all() ?? [];
}

// Toplamları hesapla (Slicedan önce)
$totalYakitTutar = 0;
$totalYakitLitre = 0;
foreach($yakitlar as $y) {
    $totalYakitTutar += (float)($y->toplam_tutar ?? 0);
    $totalYakitLitre += (float)($y->yakit_miktari ?? 0);
}

$totalYapilanKm = 0;
foreach($kmlar as $k) {
    $totalYapilanKm += (float)($k->yapilan_km ?? 0);
}

// Mobil görünüm için sadece son 20 kaydı alalım, sayfa şişmesin
$zimmetler = array_slice($zimmetler, 0, 20);
$yakitlar_list = array_slice($yakitlar, 0, 20);
$kmlar_list = array_slice($kmlar, 0, 20);
$servisler = array_slice($servisler, 0, 20);

// Format helper
if (!function_exists('formatMoneyMobile')) {
    function formatMoneyMobile($amount) {
        return number_format((float)$amount, 2, ',', '.') . ' ₺';
    }
}
if (!function_exists('formatDateMobile')) {
    function formatDateMobile($dateStr) {
        if (!$dateStr) return '-';
        return date('d.m.Y', strtotime($dateStr));
    }
}
if (!function_exists('formatKmMobile')) {
    function formatKmMobile($km) {
        return number_format((float)$km, 0, '', '.') . ' km';
    }
}
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-teal-600 to-teal-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex flex-col gap-4">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">
                Araç Takip
            </h2>
            <p class="text-white/80 text-sm mt-1 font-medium">Toplam <?= $aracStats->toplam_arac ?? 0 ?> araç</p>
        </div>
        <?php
            $toplamArac = $aracStats->toplam_arac ?? 0;
            $aktifArac = $aracStats->aktif_arac ?? 0;
            $pasifArac = $toplamArac - $aktifArac;
            $zimmetliSayi = $zimmetliSayi ?? 0;
            $servistekiSayi = $servistekiSayi ?? 0;
            $bostaArac = $aracStats->bosta_arac ?? ($aktifArac - $zimmetliSayi - $servistekiSayi); 
            if($bostaArac < 0) $bostaArac = 0;
            $ikameArac = $aracStats->ikame_arac_sayisi ?? 0;
        ?>
        <div class="flex gap-1.5 overflow-x-auto no-scrollbar pb-1 -mr-4 pr-4 snap-x" style="scroll-snap-type: x mandatory;">
            <button onclick="filterBadge('aktif')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $aktifArac ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">Aktif</span>
                </div>
            </button>
            <button onclick="filterBadge('zimmetli')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $zimmetliSayi ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">Zimmet</span>
                </div>
            </button>
            <button onclick="filterBadge('boşta')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $bostaArac ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">Boşta</span>
                </div>
            </button>
            <button onclick="filterBadge('serviste')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $servistekiSayi ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">Servis</span>
                </div>
            </button>
            <?php if($ikameArac > 0): ?>
            <button onclick="filterBadge('ikame')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $ikameArac ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">İkame</span>
                </div>
            </button>
            <?php endif; ?>
            <?php if($pasifArac > 0): ?>
            <button onclick="filterBadge('pasif')" class="flex-1 min-w-[50px] text-center badge-btn transition-transform active:scale-95 focus:outline-none snap-start">
                <div class="bg-white/20 rounded-xl px-1.5 py-1.5 backdrop-blur-sm border border-white/20 shadow-sm">
                    <span class="block text-lg font-black leading-tight"><?= $pasifArac ?></span>
                    <span class="text-[9px] uppercase font-bold tracking-wider text-white/90 truncate">Pasif</span>
                </div>
            </button>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-5 pb-6">

    <!-- Tab Buttons -->
    <div class="flex gap-2 p-1 bg-white dark:bg-card-dark rounded-xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar" style="scroll-snap-type: x mandatory;">
        <button onclick="switchTab('araclar')" id="btn-tab-araclar" class="flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400" style="scroll-snap-align: start;">
            <span class="material-symbols-outlined text-[18px]">directions_car</span>
            Araçlar
        </button>
        <button onclick="switchTab('zimmet')" id="btn-tab-zimmet" class="flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" style="scroll-snap-align: start;">
            <span class="material-symbols-outlined text-[18px]">transfer_within_a_station</span>
            Zimmet
        </button>
        <button onclick="switchTab('yakit')" id="btn-tab-yakit" class="flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" style="scroll-snap-align: start;">
            <span class="material-symbols-outlined text-[18px]">local_gas_station</span>
            Yakıt
        </button>
        <button onclick="switchTab('km')" id="btn-tab-km" class="flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" style="scroll-snap-align: start;">
            <span class="material-symbols-outlined text-[18px]">speed</span>
            KM
        </button>
        <button onclick="switchTab('servis')" id="btn-tab-servis" class="flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800" style="scroll-snap-align: start;">
            <span class="material-symbols-outlined text-[18px]">handyman</span>
            Servis
        </button>
    </div>
    
    <!-- Toplam Özet Kartları (Sekmeye Göre) -->
    <div id="summary-yakit" class="summary-area hidden">
        <div class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-4 text-white shadow-lg relative overflow-hidden mb-1">
             <div class="absolute right-[-10px] top-[-10px] opacity-10">
                 <span class="material-symbols-outlined text-8xl">local_gas_station</span>
             </div>
             <div class="relative z-10 grid grid-cols-2 gap-4">
                 <div>
                     <span class="text-[10px] uppercase font-bold opacity-80 block mb-0.5 tracking-wider">Toplam Tutar</span>
                     <h4 class="text-xl font-black"><?= formatMoneyMobile($totalYakitTutar) ?></h4>
                 </div>
                 <div>
                     <span class="text-[10px] uppercase font-bold opacity-80 block mb-0.5 tracking-wider">Toplam Litre</span>
                     <h4 class="text-xl font-black"><?= number_format($totalYakitLitre, 2, ',', '.') ?> <small class="text-[10px] font-normal">L</small></h4>
                 </div>
             </div>
        </div>
    </div>

    <div id="summary-km" class="summary-area hidden">
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-4 text-white shadow-lg relative overflow-hidden mb-1">
             <div class="absolute right-[-10px] top-[-10px] opacity-10">
                 <span class="material-symbols-outlined text-8xl">speed</span>
             </div>
             <div class="relative z-10">
                 <span class="text-[10px] uppercase font-bold opacity-80 block mb-0.5 tracking-wider">Toplam Yapılan KM</span>
                 <h4 class="text-2xl font-black"><?= formatKmMobile($totalYapilanKm) ?></h4>
             </div>
        </div>
    </div>

    <!-- Dönem Seçici -->
    <div id="period-selector-area" class="flex gap-2 overflow-x-auto no-scrollbar py-1">
        <?php
        $periods = [
            'today' => 'Bugün',
            'this_month' => 'Bu Ay',
            'all' => 'Tümü'
        ];
        foreach ($periods as $key => $label):
            $isActive = ($period == $key);
        ?>
            <button onclick="changePeriod('<?= $key ?>')" 
               class="flex-none px-4 py-1.5 rounded-full text-[11px] font-bold transition-all <?= $isActive ? 'bg-teal-500 text-white shadow-md' : 'bg-white dark:bg-card-dark text-slate-500 border border-slate-100 dark:border-slate-800' ?>">
                <?= $label ?>
            </button>
        <?php endforeach; ?>
        
        <!-- Ay Seçimi -->
        <?php
        $monthNames = ['01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan', '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos', '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'];
        $customLabel = $period == 'custom' ? ($monthNames[$selectedAy] . ' ' . $selectedYil) : 'Ay Seç';
        $isCustomActive = ($period == 'custom');
        ?>
        <button onclick="openMonthPicker()" 
           class="flex-none px-4 py-1.5 rounded-full text-[11px] font-bold transition-all flex items-center gap-1.5 <?= $isCustomActive ? 'bg-teal-500 text-white shadow-md' : 'bg-white dark:bg-card-dark text-slate-500 border border-slate-100 dark:border-slate-800' ?>">
            <span class="material-symbols-outlined text-[16px]">calendar_month</span>
            <?= $customLabel ?>
        </button>
    </div>

    <!-- Arama Alanı -->
    <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
        <input type="text" id="aracSearchInput" onkeyup="filterCards()" placeholder="Plaka, marka, personel, istasyon vb. ara..." class="w-full bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/50 transition-shadow text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 shadow-sm">
    </div>

    <!-- ARAÇLAR TAB -->
    <div id="tab-content-araclar" class="tab-content block space-y-3 mt-4">
        <?php if (empty($araclar)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-teal-50 dark:bg-teal-900/20 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-teal-400 text-2xl">info</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Araç Bulunamadı</h3>
            </div>
        <?php else: ?>
            <?php foreach ($araclar as $arac): 
                // Filtreleme için dinamik veri etiketleri (tags) oluştur
                $tags = ['aktif']; // varsayılan tüm gelenler aktif
                if ($arac->serviste_mi) $tags[] = 'serviste';
                elseif (!empty($arac->zimmetli_personel_id)) $tags[] = 'zimmetli';
                else $tags[] = 'boşta';
                
                if (!empty($arac->ikame_mi)) $tags[] = 'ikame';
                $tagsString = implode(' ', $tags);
            ?>
                <div onclick="openAracEdit(<?= $arac->id ?>)" class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 relative overflow-hidden transition-transform active:scale-95 cursor-pointer" data-tags="<?= $tagsString ?>">
                    <div class="absolute right-[-10px] top-1/2 -translate-y-1/2 text-slate-300 dark:text-slate-700">
                         <span class="material-symbols-outlined text-4xl opacity-50">chevron_right</span>
                    </div>
                    <div class="flex items-start justify-between mb-3 border-b border-slate-100 dark:border-slate-800/60 pb-3 pr-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-teal-50 dark:bg-teal-900/20 flex flex-col flex-shrink-0 items-center justify-center border border-teal-100 dark:border-teal-900/50">
                                <span class="material-symbols-outlined text-teal-600 dark:text-teal-400 text-lg">directions_car</span>
                                <span class="text-[8px] uppercase font-bold text-teal-600 dark:text-teal-400 leading-none mt-0.5"><?= htmlspecialchars($arac->yakit_tipi ?? '') ?></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white text-base"><?= htmlspecialchars($arac->plaka) ?></h3>
                                <p class="text-[11px] text-slate-500 font-medium"><?= htmlspecialchars(($arac->marka ?? '') . ' ' . ($arac->model ?? '')) ?></p>
                                <p class="text-[11px] font-bold text-slate-600 dark:text-slate-400 mt-0.5"><?= formatKmMobile($arac->guncel_km ?? 0) ?></p>
                            </div>
                        </div>
                        <div class="text-right flex flex-col items-end">
                            <?php if ($arac->serviste_mi): ?>
                                <span class="inline-flex items-center bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400 text-[10px] px-1.5 py-0.5 rounded font-bold">Serviste</span>
                            <?php elseif (!empty($arac->zimmetli_personel_id)): ?>
                                <span class="inline-flex items-center bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400 text-[10px] px-1.5 py-0.5 rounded font-bold">Zimmetli</span>
                                <span class="text-[9px] font-bold text-slate-600 dark:text-slate-400 mt-1 uppercase text-right max-w-[80px] leading-tight"><?= htmlspecialchars($arac->zimmetli_personel_adi ?: '-') ?></span>
                            <?php else: ?>
                                <span class="inline-flex items-center bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 text-[10px] px-1.5 py-0.5 rounded font-bold">Boşta</span>
                            <?php endif; ?>
                            <?php if (!empty($arac->ikame_mi)): ?>
                                <span class="inline-flex items-center bg-warning-subtle text-warning text-[10px] px-1.5 py-0.5 rounded font-bold mt-1">İkame</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        // Progress Bar Helper
                        if (!function_exists('getProgressBarColor')) {
                            function getProgressBarColor($daysLeft) {
                                if ($daysLeft <= 15) return 'bg-red-500';
                                if ($daysLeft <= 30) return 'bg-warning';
                                return 'bg-teal-500';
                            }
                        }
                        if (!function_exists('getProgressBarWidth')) {
                            function getProgressBarWidth($daysLeft) {
                                if ($daysLeft <= 0) return 100;
                                $maxDays = 365; // Bir yıl baz alınarak
                                if ($daysLeft > $maxDays) return 5;
                                $percent = (($maxDays - $daysLeft) / $maxDays) * 100;
                                return min(100, max(5, $percent)); // En az %5, en fazla %100 genişlik
                            }
                        }
                    ?>
                    <div class="mt-4 border-t border-slate-50 dark:border-slate-800/50 pt-3">
                        <button onclick="toggleEvraklar(event, this)" class="w-full flex justify-between items-center text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest hover:text-teal-500 transition-colors">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">assignment</span>
                                Evrak Durumları
                            </span>
                            <span class="material-symbols-outlined text-[18px] transition-transform duration-300">expand_more</span>
                        </button>
                        
                        <div class="evraklar-content hidden space-y-3 mt-3 overflow-hidden">
                            <?php 
                            $evraklar = [
                                ['isim' => 'Muayene', 'tarih' => $arac->muayene_bitis_tarihi],
                                ['isim' => 'Sigorta', 'tarih' => $arac->sigorta_bitis_tarihi],
                                ['isim' => 'Kasko', 'tarih' => $arac->kasko_bitis_tarihi]
                            ];
                            
                            foreach ($evraklar as $evrak): 
                                $isEmpty = empty($evrak['tarih']);
                                if ($isEmpty) {
                                    $width = 0;
                                    $colorClass = 'bg-slate-300 dark:bg-slate-700'; // Boş durum rengi
                                    $textColorClass = 'text-slate-400 dark:text-slate-500';
                                    $displayDate = 'Yok';
                                } else {
                                    $daysLeft = (strtotime($evrak['tarih']) - time()) / (60 * 60 * 24);
                                    $width = getProgressBarWidth($daysLeft);
                                    $colorClass = getProgressBarColor($daysLeft);
                                    $textColorClass = $daysLeft <= 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-600 dark:text-slate-400';
                                    $displayDate = formatDateMobile($evrak['tarih']);
                                }
                            ?>
                                <div>
                                    <div class="flex justify-between items-center text-[10px] mb-1">
                                        <span class="font-bold uppercase opacity-70 <?= $textColorClass ?>"><?= $evrak['isim'] ?></span>
                                        <span class="font-bold <?= $textColorClass ?>"><?= $displayDate ?></span>
                                    </div>
                                    <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5">
                                        <div class="<?= $colorClass ?> h-1.5 rounded-full transition-all duration-500" style="width: <?= $width ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ZİMMET TAB -->
    <div id="tab-content-zimmet" class="tab-content hidden space-y-3">
        <?php if (empty($zimmetler)): ?>
            <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-slate-400 text-2xl">info</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Kayıtlı Zimmet Yok</h3>
            </div>
        <?php else: ?>
            <?php foreach ($zimmetler as $zimmet): ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-2">
                             <span class="material-symbols-outlined text-blue-500 text-[18px]">person</span>
                             <h3 class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($zimmet->personel_adi) ?></h3>
                        </div>
                        <?php if ($zimmet->durum == 'aktif'): ?>
                            <span class="inline-flex items-center bg-green-50 text-green-600 dark:bg-green-900/20 dark:text-green-400 text-[10px] px-1.5 py-0.5 rounded font-bold">Aktif</span>
                        <?php else: ?>
                            <span class="inline-flex items-center bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 text-[10px] px-1.5 py-0.5 rounded font-bold">İade Edildi</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                         <span class="material-symbols-outlined text-slate-400 text-[16px]">directions_car</span>
                         <span class="font-bold text-teal-600 text-xs"><?= htmlspecialchars($zimmet->plaka) ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-2">
                            <span class="text-slate-500 block mb-0.5">Zimmet Tarihi</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= formatDateMobile($zimmet->zimmet_tarihi) ?></span>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-2">
                            <span class="text-slate-500 block mb-0.5">İade Tarihi</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= formatDateMobile($zimmet->iade_tarihi) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- YAKIT TAB -->
    <div id="tab-content-yakit" class="tab-content hidden space-y-3">
        <?php if (empty($yakitlar_list)): ?>
             <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-slate-400 text-2xl">info</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Yakıt Kaydı Yok</h3>
            </div>
        <?php else: ?>
            <?php foreach ($yakitlar_list as $yak): ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800/60 pb-2">
                        <div class="flex items-center gap-2">
                             <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 dark:bg-orange-900/30 flex items-center justify-center">
                                 <span class="material-symbols-outlined text-[18px]">local_gas_station</span>
                             </div>
                             <div>
                                 <h3 class="font-bold text-teal-600 text-sm"><?= htmlspecialchars($yak->plaka) ?></h3>
                                 <p class="text-[10px] text-slate-500"><?= formatDateMobile($yak->tarih) ?></p>
                             </div>
                        </div>
                        <div class="text-right">
                             <span class="font-bold text-slate-800 dark:text-white text-sm"><?= formatMoneyMobile($yak->toplam_tutar) ?></span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-[10px]">
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-1.5 flex flex-col">
                            <span class="text-slate-500 mb-0.5 text-[9px]">Litre</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= number_format($yak->yakit_miktari, 2) ?> L</span>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-1.5 flex flex-col">
                            <span class="text-slate-500 mb-0.5 text-[9px]">KM</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= formatKmMobile($yak->km) ?></span>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-1.5 flex flex-col">
                            <span class="text-slate-500 mb-0.5 text-[9px]">İstasyon</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300 truncate"><?= htmlspecialchars($yak->istasyon ?: '-') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- KM TAB -->
    <div id="tab-content-km" class="tab-content hidden space-y-3">
         <?php if (empty($kmlar_list)): ?>
             <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-slate-400 text-2xl">info</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">KM Kaydı Yok</h3>
            </div>
        <?php else: ?>
            <?php foreach ($kmlar_list as $kmData): ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                     <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                             <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 flex items-center justify-center">
                                 <span class="material-symbols-outlined text-[18px]">speed</span>
                             </div>
                             <div>
                                 <h3 class="font-bold text-teal-600 text-sm"><?= htmlspecialchars($kmData->plaka) ?></h3>
                                 <p class="text-[10px] text-slate-500"><?= formatDateMobile($kmData->tarih) ?></p>
                             </div>
                        </div>
                        <div class="text-right">
                             <span class="inline-flex items-center bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 text-[10px] px-1.5 py-0.5 rounded font-bold">+ <?= formatKmMobile($kmData->yapilan_km) ?></span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] bg-slate-50 dark:bg-slate-800/50 p-2 rounded-lg mt-2 relative">
                        <div>
                            <span class="text-slate-500 block mb-0.5 text-[9px]">Başlangıç</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= formatKmMobile($kmData->baslangic_km) ?></span>
                        </div>
                        <div class="absolute left-1/2 transform -translate-x-1/2 border-t-2 border-dashed border-slate-300 dark:border-slate-600 w-12 top-1/2 mt-1 hidden sm:block"></div>
                        <div class="text-right">
                            <span class="text-slate-500 block mb-0.5 text-[9px]">Bitiş</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300"><?= formatKmMobile($kmData->bitis_km) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SERVIS TAB -->
    <div id="tab-content-servis" class="tab-content hidden space-y-3">
         <?php if (empty($servisler)): ?>
             <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-slate-100 dark:border-slate-800 shadow-sm mt-4">
                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="material-symbols-outlined text-slate-400 text-2xl">info</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white">Servis Kaydı Yok</h3>
            </div>
        <?php else: ?>
            <?php foreach ($servisler as $srv): ?>
                <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4">
                    <div class="flex items-start justify-between mb-2 border-b border-slate-100 dark:border-slate-800/60 pb-2">
                        <div class="flex items-center gap-2">
                             <div class="w-8 h-8 rounded-lg bg-red-50 text-red-600 dark:bg-red-900/30 flex items-center justify-center">
                                 <span class="material-symbols-outlined text-[18px]">handyman</span>
                             </div>
                             <div>
                                 <h3 class="font-bold text-teal-600 text-sm"><?= htmlspecialchars($srv->plaka) ?></h3>
                                 <p class="text-[10px] text-slate-500">Giriş: <?= formatDateMobile($srv->servis_tarihi) ?></p>
                             </div>
                        </div>
                        <div class="text-right flex flex-col items-end">
                            <?php if (empty($srv->iade_tarihi)): ?>
                                <span class="bg-warning-subtle text-warning text-[10px] px-1.5 py-0.5 rounded font-bold mb-1">Devam Ediyor</span>
                            <?php else: ?>
                                <span class="bg-success-subtle text-success text-[10px] px-1.5 py-0.5 rounded font-bold mb-1">Tamamlandı</span>
                            <?php endif; ?>
                            <span class="font-bold text-slate-800 dark:text-white text-xs"><?= formatMoneyMobile($srv->tutar ?? 0) ?></span>
                        </div>
                    </div>
                    <?php if (!empty($srv->yapilan_islemler)): ?>
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-lg p-2.5 mt-2">
                            <span class="text-slate-500 text-[9px] uppercase font-bold block mb-1">İşlem</span>
                            <p class="text-xs text-slate-600 dark:text-slate-400 truncate"><?= htmlspecialchars($srv->yapilan_islemler) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Yüzen Araç Ekle Butonu -->
    <button id="fabAddButton" onclick="openSheet('arac')" class="fixed bottom-24 right-4 w-14 h-14 bg-teal-500 hover:bg-teal-600 text-white rounded-full shadow-lg flex items-center justify-center transition-transform active:scale-95 z-50 focus:outline-none">
        <span class="material-symbols-outlined text-3xl">add</span>
    </button>
</div>

<!-- ================= BOTTOM SHEETS (Ekle/Düzenle) ================= -->
<div id="bs-container" class="fixed inset-0 z-[100] hidden flex-col justify-end">
    <!-- Mask (Arka Plan Karartısı) -->
    <div id="bs-backdrop" class="absolute inset-0 bg-slate-900/60 transition-opacity duration-300 opacity-0" onclick="closeSheet()"></div>
    
    <!-- İçerisi Dinamik Yüklenecek Ana Sheet Çerçevesi -->
    <div id="bs-sheet" class="relative w-full bg-slate-50 dark:bg-slate-900 rounded-t-3xl min-h-[50vh] max-h-[90vh] flex flex-col translate-y-full transition-transform duration-300 shadow-2xl overflow-hidden">
        
        <!-- Header & Drag Handle (Sabit) -->
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shrink-0 flex flex-col items-center sticky top-0 z-10">
             <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full mb-3 cursor-pointer" onclick="closeSheet()"></div>
             <div class="w-full flex justify-between items-center">
                 <h3 id="bs-title" class="font-bold text-lg text-slate-800 dark:text-white flex items-center gap-2">
                     <!-- Icon & Title dynamically set -->
                 </h3>
                 <button onclick="closeSheet()" class="w-8 h-8 flex items-center justify-center bg-slate-100 dark:bg-slate-800 rounded-full text-slate-500 hover:bg-slate-200 transition-colors focus:outline-none">
                     <span class="material-symbols-outlined text-sm">close</span>
                 </button>
             </div>
        </div>

        <!-- Sheet Body (Scrollable) -->
        <div id="bs-body" class="p-4 overflow-y-auto w-full grow flex flex-col space-y-4 pb-28 relative">
            <!-- Includes are injected or rendered here via JS toggling -->
            <?php include 'sheets/arac-sheet.php'; ?>
            <?php include 'sheets/zimmet-sheet.php'; ?>
            <?php include 'sheets/yakit-sheet.php'; ?>
            <?php include 'sheets/km-sheet.php'; ?>
            <?php include 'sheets/servis-sheet.php'; ?>
        </div>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function changePeriod(period) {
        const hash = window.location.hash || '#araclar';
        window.location.href = `?p=arac&period=${period}${hash}`;
    }

    function openMonthPicker() {
        MobileSwal.fire({
            title: 'Dönem Seçin',
            html: `
                <div class="p-2">
                    <input type="month" id="swalMonth" 
                        class="w-full p-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-white font-bold focus:ring-2 focus:ring-teal-500 outline-none" 
                        value="<?= "$selectedYil-$selectedAy" ?>">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Uygula',
            cancelButtonText: 'İptal',
            preConfirm: () => {
                const val = document.getElementById('swalMonth').value;
                if(!val) {
                    Swal.showValidationMessage('Lütfen bir ay seçin');
                    return false;
                }
                return val;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const [year, month] = result.value.split('-');
                const hash = window.location.hash || '#araclar';
                window.location.href = `?p=arac&period=custom&ay=${month}&yil=${year}${hash}`;
            }
        });
    }

    function toggleEvraklar(event, btn) {
        event.stopPropagation();
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('.material-symbols-outlined:last-child');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.style.transform = 'rotate(180deg)';
        } else {
            content.classList.add('hidden');
            icon.style.transform = 'rotate(0deg)';
        }
    }

    function switchTab(tabId) {
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });
        
        // Hide all summary areas
        document.querySelectorAll('.summary-area').forEach(el => el.classList.add('hidden'));
        
        // Reset button styles
        document.querySelectorAll('[id^=btn-tab-]').forEach(btn => {
            btn.className = "flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800";
        });
        
        // Show active tab
        document.getElementById('tab-content-' + tabId).classList.remove('hidden');
        document.getElementById('tab-content-' + tabId).classList.add('block');
        
        // Show/Hide period filter area
        const filterArea = document.getElementById('period-selector-area');
        if (tabId === 'araclar') {
            filterArea.classList.add('hidden');
        } else {
            filterArea.classList.remove('hidden');
        }
        
        // Show active summary if exists
        const summaryEl = document.getElementById('summary-' + tabId);
        if(summaryEl) summaryEl.classList.remove('hidden');
        
        // Activate button style
        const activeBtn = document.getElementById('btn-tab-' + tabId);
        activeBtn.className = "flex-none py-2 px-3 rounded-lg text-sm font-bold flex items-center justify-center gap-1.5 transition-all bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400";
        
        // Active tab color map 
        const fabBtn = document.getElementById('fabAddButton');
        fabBtn.className = "fixed bottom-24 right-4 w-14 h-14 text-white rounded-full shadow-lg flex items-center justify-center transition-transform active:scale-95 z-50 focus:outline-none";
        
        if (tabId === 'araclar') {
            fabBtn.classList.add('bg-teal-500', 'hover:bg-teal-600');
            fabBtn.onclick = () => openSheet('arac');
            fabBtn.innerHTML = '<span class="material-symbols-outlined text-3xl">add</span>';
        } else if (tabId === 'zimmet') {
            fabBtn.classList.add('bg-amber-500', 'hover:bg-amber-600');
            fabBtn.onclick = () => openSheet('zimmet');
            fabBtn.innerHTML = '<span class="material-symbols-outlined text-3xl">add</span>';
        } else if (tabId === 'yakit') {
            fabBtn.classList.add('bg-emerald-500', 'hover:bg-emerald-600');
            fabBtn.onclick = () => openSheet('yakit');
            fabBtn.innerHTML = '<span class="material-symbols-outlined text-3xl">add</span>';
        } else if (tabId === 'km') {
            fabBtn.classList.add('bg-sky-500', 'hover:bg-sky-600');
            fabBtn.onclick = () => openSheet('km');
            fabBtn.innerHTML = '<span class="material-symbols-outlined text-3xl">add</span>';
        } else if (tabId === 'servis') {
            fabBtn.classList.add('bg-indigo-500', 'hover:bg-indigo-600');
            fabBtn.onclick = () => openSheet('servis');
            fabBtn.innerHTML = '<span class="material-symbols-outlined text-3xl">add</span>';
        }

        // Scroll the tab container so the active button is visible
        const container = activeBtn.parentElement;
        const scrollLeft = activeBtn.offsetLeft - (container.clientWidth / 2) + (activeBtn.clientWidth / 2);
        container.scrollTo({ left: Math.max(0, scrollLeft), behavior: 'smooth' });

        // Arama kutusunu temizle ve filtreyi sıfırla
        document.getElementById('aracSearchInput').value = '';
        filterCards();
    }

    // Auto-select tab if in URL Hash (e.g. #zimmet)
    if (window.location.hash) {
        const hash = window.location.hash.replace('#', '');
        if (['araclar', 'zimmet', 'yakit', 'km', 'servis'].includes(hash)) {
            switchTab(hash);
        }
    }

    function filterCards() {
        // Hem arama kutusu hem de badge tagleri birlikte çalışsın
        const input = document.getElementById('aracSearchInput');
        
        let filter = "";
        // Eğer global bir badgeFilter variable'i set edildiyse onu da dikkate al (Yoksa inputtaki harfleri baz al)
        if(window.currentBadgeFilter) {
            filter = window.currentBadgeFilter.toLocaleLowerCase('tr-TR');
            input.value = ""; // Karışıklığı önlemek için inputu temizle
        } else {
            filter = input.value.toLocaleLowerCase('tr-TR');
        }
        
        const activeTab = document.querySelector('.tab-content.block');
        if (!activeTab) return;
        
        const cards = activeTab.querySelectorAll('.bg-white.dark\\:bg-card-dark.rounded-2xl');
        
        // Sadece Araçlar sekmesindeyken pasif araçların normalde gizlenmesi
        const isAracTab = activeTab.id === 'tab-content-araclar';
        
        cards.forEach(card => {
            // Boş durum bilgilendirme kartlarını atla
            if(card.classList.contains('text-center') && card.classList.contains('p-6')) {
                return;
            }
            
            const textContent = card.innerText.toLocaleLowerCase('tr-TR');
            const dataTags = (card.getAttribute('data-tags') || '').toLocaleLowerCase('tr-TR');
            
            // Hem düz metinde hem de etiketlerde (tags) ara
            const hasMatch = textContent.includes(filter) || dataTags.includes(filter);

            card.style.display = hasMatch ? '' : 'none';
        });
    }

    // Badge butonlarına tıklandığında çalışacak fonksiyon
    function filterBadge(tag) {
        // En başta Araçlar sekmesini açıp gösterelim.
        switchTab('araclar');

        // Gelen tag'i kaydet
        window.currentBadgeFilter = tag;
        
        // CSS için buton opacity ayarı
        document.querySelectorAll('.badge-btn').forEach(btn => btn.style.opacity = '0.5');
        const activeBtn = event.currentTarget || document.querySelector('.badge-btn');
        if(activeBtn) {
           activeBtn.style.opacity = '1';
        }
        
        // Filtreyi çalıştır
        filterCards();
        
        // Aramayı tekrar manuel olarak serbest bırak
        window.currentBadgeFilter = null;
    }
    
    // Yönlendirme (eski kullanım için)
    function openLink(path) {
        window.location.href = `?p=${path}`;
    }

    // Modal / Bottom Sheet Scriptleri
    let currentOpenSheetId = null;

    // SweetAlert Tema Ayarı
    const MobileSwal = Swal.mixin({
        customClass: {
            container: 'z-[9999]',
            popup: 'rounded-[2rem] shadow-2xl bg-white dark:bg-slate-800 dark:text-white border border-slate-100 dark:border-slate-700',
            title: 'text-lg font-extrabold text-slate-800 dark:text-white tracking-tight',
            htmlContainer: 'text-sm text-slate-500 dark:text-slate-400',
            confirmButton: 'w-full py-3 bg-teal-500 hover:bg-teal-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md focus:outline-none mb-2',
            cancelButton: 'w-full py-3 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 font-bold rounded-xl transition-all focus:outline-none'
        },
        buttonsStyling: false,
        showClass: { popup: 'animate__animated animate__zoomIn animate__faster' },
        hideClass: { popup: 'animate__animated animate__zoomOut animate__faster' }
    });
    
    // Düzenleme Fonksiyonları
    function openAracEdit(id) {
        // Araç düzenleme için bilgileri form içine yerleştir (TBA)
        MobileSwal.fire({
            title: 'Yükleniyor...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        fetch(`../views/arac-takip/api.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=arac-detay&id=${id}`
        })
        .then(response => response.json())
        .then(res => {
            Swal.close();
            if (res.status === 'success') {
                const data = res.data;
                const form = document.getElementById('aracForm');
                
                // Form elementlerini dataya göre doldur
                form.id.value = data.id;
                form.plaka.value = data.plaka || '';
                form.marka.value = data.marka || '';
                form.model.value = data.model || '';
                form.durum.value = data.durum || '1';
                form.baslangic_km.value = data.baslangic_km || '0';
                form.guncel_km.value = data.guncel_km || '0';
                form.yakit_tipi.value = data.yakit_tipi || '';
                form.arac_tipi.value = data.arac_tipi || '';
                
                // Tarihler
                if(data.muayene_bitis_tarihi && data.muayene_bitis_tarihi !== '-') {
                    const parts = data.muayene_bitis_tarihi.split('.');
                    if(parts.length === 3) form.muayene_tarihi.value = `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                if(data.sigorta_bitis_tarihi && data.sigorta_bitis_tarihi !== '-') {
                    const parts = data.sigorta_bitis_tarihi.split('.');
                    if(parts.length === 3) form.sigorta_tarihi.value = `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                if(data.kasko_bitis_tarihi && data.kasko_bitis_tarihi !== '-') {
                    const parts = data.kasko_bitis_tarihi.split('.');
                    if(parts.length === 3) form.kasko_tarihi.value = `${parts[2]}-${parts[1]}-${parts[0]}`;
                }

                form.sase_no.value = data.sase_no || '';
                form.ruhsat_no.value = data.ruhsat_no || '';
                
                openSheet('arac');
            } else {
                MobileSwal.fire('Hata', res.message, 'error');
            }
        });
    }

    function openSheet(id) {
        // Formları sıfırla (Düzenleme değilse)
        if (event && event.currentTarget && event.currentTarget.id === 'fabAddButton') {
            const formObj = document.getElementById(id + 'Form');
            if (formObj) {
                formObj.reset();
                if(formObj.arac_id && formObj.arac_id.type === 'hidden') formObj.arac_id.value = '';
                else if(formObj.id) formObj.id.value = '';
            }
        }

        // Tüm sheet containerlarını gizle ve hedefini aç
        document.querySelectorAll('.app-sheet-content').forEach(el => el.classList.add('hidden'));
        const targetContent = document.getElementById('sheet-content-' + id);
        if(!targetContent) return;
        targetContent.classList.remove('hidden');

        // Title icon ve rengi ayarla
        const titleEl = document.getElementById('bs-title');
        let iconHtml = '';
        let titleText = '';
        
        if (id === 'arac') {
            titleText = "Araç Formu";
            iconHtml = '<span class="material-symbols-outlined text-teal-500">directions_car</span>';
        } else if (id === 'zimmet') {
            titleText = "Zimmet Formu";
            iconHtml = '<span class="material-symbols-outlined text-amber-500">swap_horiz</span>';
        } else if (id === 'yakit') {
            titleText = "Yakıt Fişi İşlemi";
            iconHtml = '<span class="material-symbols-outlined text-emerald-500">local_gas_station</span>';
        } else if (id === 'km') {
            titleText = "Günlük KM Kaydı";
            iconHtml = '<span class="material-symbols-outlined text-sky-500">speed</span>';
        } else if (id === 'servis') {
            titleText = "Servis İşlemi";
            iconHtml = '<span class="material-symbols-outlined text-indigo-500">build</span>';
        }
        titleEl.innerHTML = iconHtml + ' ' + titleText;

        currentOpenSheetId = id;

        // Parent container ve animasyonlar
        const container = document.getElementById('bs-container');
        const backdrop = document.getElementById('bs-backdrop');
        const sheet = document.getElementById('bs-sheet');
        
        container.classList.remove('hidden');
        container.classList.add('flex');
        
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            sheet.classList.remove('translate-y-full');
            sheet.classList.add('translate-y-0');
        }, 10);
    }

    function closeSheet() {
        const backdrop = document.getElementById('bs-backdrop');
        const sheet = document.getElementById('bs-sheet');
        const container = document.getElementById('bs-container');
        
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        sheet.classList.remove('translate-y-0');
        sheet.classList.add('translate-y-full');
        
        setTimeout(() => {
            container.classList.add('hidden');
            container.classList.remove('flex');
            currentOpenSheetId = null;
        }, 300);
    }
</script>
