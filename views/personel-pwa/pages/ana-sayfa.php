<?php
/**
 * Personel PWA - Ana Sayfa
 * Özet bilgiler ve hızlı işlemler
 */
use App\Helper\Helper;

?>

<div class="flex flex-col min-h-screen">
    <!-- iOS PWA Kurulum Rehberi (Sadece iOS Safari'de görünür) -->
    <div id="ios-install-guide" class="hidden px-4 pt-3">
        <div class="bg-blue-600/10 dark:bg-blue-400/10 border border-blue-600/30 dark:border-blue-400/30 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-lg shadow-blue-500/10 active:scale-95 transition-transform" onclick="showInstallInstructions()">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center">
                    <span class="material-symbols-outlined">install_mobile</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-blue-700 dark:text-blue-400">Uygulama Olarak Kullanın</h4>
                    <p class="text-[11px] text-blue-600/80 dark:text-blue-400/80 font-medium">Bildirimler için ana ekrana ekleyin</p>
                </div>
            </div>
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">chevron_right</span>
        </div>
    </div>
    <!-- Header -->
    <header class="bg-gradient-primary text-white px-4 pt-4 pb-8 rounded-b-3xl relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
        </div>

        <div class="relative z-10">
            <!-- User Info & Notification -->
            <div class="flex items-center justify-between mb-6">
                <a href="?page=profil" class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center overflow-hidden active:scale-90 transition-transform">
                        <?php 
                        $pResim = !empty($personel->personel_resim_yolu) ? $personel->personel_resim_yolu : ($personel->resim_yolu ?? '');
                        if (!empty($pResim)): ?>
                            <img src="<?php echo Helper::base_url($pResim); ?>" alt="Profil"
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-2xl">person</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-white/80 text-sm">Hoş geldin,</p>
                        <h1 class="text-xl font-bold"><?php echo $personel->adi_soyadi ?? 'Personel'; ?></h1>
                    </div>
                </a>
                <div class="flex items-center gap-2">
                    <button onclick="Theme.toggleDarkMode()"
                        class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center active:scale-95 transition-transform">
                        <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                        <span class="material-symbols-outlined hidden dark:block">light_mode</span>
                    </button>
                    <button onclick="openNotificationModal()"
                        class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center relative active:scale-95 transition-transform">
                        <span class="material-symbols-outlined">notifications</span>
                        <span id="notification-badge"
                            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border-2 border-primary hidden"></span>
                    </button>
                </div>
            </div>

            <!-- Welcome Message -->
            <div class="mb-2">
                <p class="text-white/80 text-sm">
                    <?php
                    $hour = date('H');
                    if ($hour < 12)
                        echo 'Günaydın!';
                    elseif ($hour < 18)
                        echo 'İyi günler!';
                    else
                        echo 'İyi akşamlar!';
                    ?>
                </p>
                <p class="text-white/90 text-base">İşte bugünkü özet bilgileriniz.</p>
            </div>
        </div>
    </header>

    <!-- Etkinlik Slider -->
    <section class="px-4 mt-[-20px] relative z-20 mb-4" id="etkinlik-slider-section" style="display: none;">
        <div class="flex overflow-x-auto hide-scrollbar snap-x snap-mandatory gap-3 pb-2"
            id="etkinlik-slider-container">
            <!-- Slider öğeleri buraya yüklenecek -->
        </div>
    </section>

    <!-- Görev Takip Bileşeni -->
    <?php if (($personel->saha_takibi ?? 0) == 1): ?>
        <section class="px-4 relative z-20 mb-4">
            <div id="gorev-takip-card" class="card overflow-hidden">
                <!-- Loading State -->
                <div id="gorev-loading" class="p-6 flex items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>

                <!-- Görev Durumu Container -->
                <div id="gorev-durumu-container" class="hidden">
                    <!-- GÖREVE BAŞLA (Görev Yok) -->
                    <div id="gorev-basla-panel" class="p-4 hidden">
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <span class="material-symbols-outlined text-green-600 text-2xl">play_circle</span>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-slate-900 dark:text-white">Saha Görev Takibi</h3>
                                <p class="text-xs text-slate-500">Konumunuz kayıt altına alınacaktır</p>
                            </div>
                        </div>

                        <!-- Konum İzni Uyarı -->
                        <div id="konum-izni-uyari" onclick="requestKonumIzni()"
                            class="hidden bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 mb-4 cursor-pointer active:scale-[0.98] transition-all">
                            <div class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Konum İzni Gerekli</p>
                                    <p class="text-xs text-amber-600 dark:text-amber-400">Göreve başlamak için buraya
                                        tıklayarak konum izni
                                        vermeniz gerekmektedir.</p>
                                </div>
                            </div>
                        </div>

                        <button id="btn-gorev-basla" onclick="gorevBasla()"
                            class="w-full py-4 px-6 rounded-xl font-bold text-white text-lg transition-all duration-300 flex items-center justify-center gap-3 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 shadow-lg shadow-green-500/30 active:scale-[0.98]">
                            <span class="material-symbols-outlined text-2xl">play_arrow</span>
                            <span>Göreve Başla</span>
                        </button>
                    </div>

                    <!-- GÖREVİ BİTİR (Görev Var) -->
                    <div id="gorev-bitir-panel" class="hidden">
                        <!-- Aktif Görev Bilgi Kartı -->
                        <div class="bg-gradient-to-r from-primary to-primary-dark text-white p-4 rounded-t-xl">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center animate-pulse">
                                    <span class="material-symbols-outlined text-2xl">location_on</span>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white/80 text-xs">Aktif Görev Devam Ediyor</p>
                                    <p class="font-bold text-lg" id="gorev-baslangic-saat">--:--</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-white/80 text-xs">Geçen Süre</p>
                                    <p class="font-bold text-lg" id="gorev-gecen-sure">0 dk</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <button id="btn-gorev-bitir" onclick="gorevBitir()"
                                class="w-full py-4 px-6 rounded-xl font-bold text-white text-lg transition-all duration-300 flex items-center justify-center gap-3 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 shadow-lg shadow-red-500/30 active:scale-[0.98]">
                                <span class="material-symbols-outlined text-2xl">stop_circle</span>
                                <span>Görevi Bitir</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($aktifAracZimmeti): ?>
        <!-- ARAÇ KM TAKİBİ (Separate Premium Card) -->
        <section class="px-4 mt-6">
            <div class="card overflow-hidden border-none shadow-xl shadow-indigo-500/10 bg-white dark:bg-slate-900 group">
                <div class="p-5 flex flex-col gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-3xl">directions_car</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-bold text-slate-800 dark:text-white line-clamp-1">Araç KM Yönetimi</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Aktif Araç: <span class="text-indigo-600 dark:text-indigo-400 font-bold"><?= $aktifAracZimmeti->plaka ?? 'Zimmetli Araç' ?></span></p>
                        </div>
                        <div class="text-right">
                            <div class="px-2 py-1 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                                AKTİF
                            </div>
                        </div>
                    </div>

                    <button onclick="openKmBildirModal()"
                        class="w-full py-4 px-6 rounded-2xl font-black text-white text-base transition-all duration-300 flex items-center justify-center gap-3 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 shadow-lg shadow-indigo-500/40 active:scale-[0.97] border-none group-active:translate-y-0.5">
                        <span class="material-symbols-outlined text-2xl animate-pulse">speed</span>
                        <span class="tracking-tight uppercase">Günlük KM Bildir</span>
                    </button>
                    
                    <div class="flex items-center justify-center gap-2 mt-1">
                        <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-medium italic">Resimli bildirim zorunludur</p>
                        <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>


    <?php if (!$isBuro): ?>
    <!-- Performance Summary -->
    <section class="px-4 mt-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Performans Özeti</h2>
        </div>

        <div id="work-stats-container" class="grid grid-cols-2 gap-3">
            <!-- Loading -->
            <div class="col-span-2 py-8 flex justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Özet Bilgiler (Combined) -->
    <section class="px-4 relative z-20 mt-5">
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Genel Özet</h2>
            <span class="text-xs text-slate-400 font-medium" id="combined-donem-label">Mart 2026</span>
        </div>

        <div
            class="card overflow-hidden border border-slate-100 dark:border-slate-800 shadow-xl shadow-slate-200/40 dark:shadow-none bg-white dark:bg-slate-900">
            <?php if (false): ?>
                <!-- Financial Header (Minimal) -->
                <div class="p-5 relative border-b border-slate-50 dark:border-slate-800">
                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex flex-col">
                                <div class="flex items-center gap-1.5 text-slate-400 dark:text-slate-500 mb-1">
                                    <span class="material-symbols-outlined text-xs">savings</span>
                                    <span class="text-[10px] font-bold uppercase tracking-[0.1em]">Kalan Bakiye</span>
                                </div>
                                <h3 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white"
                                    id="combined-remaining-balance">0,00 ₺</h3>
                            </div>
                            <div class="flex flex-col items-end">
                                <span id="combined-hakedis-donem"
                                    class="text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2.5 py-1 rounded-full font-bold mb-2"></span>
                                <div
                                    class="badge badge-success bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-none text-[10px] font-bold">
                                    +%5.2</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-4 mt-2 border-t border-slate-50 dark:border-slate-800/50">
                            <div>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 font-medium mb-0.5">Toplam Hakediş
                                </p>
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-300" id="combined-total-earning">
                                    0,00 ₺</p>
                            </div>
                            <div class="border-l border-slate-50 dark:border-slate-800 pl-4">
                                <p class="text-[10px] text-slate-400 dark:text-slate-500 font-medium mb-0.5">Alınan Ödeme
                                </p>
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-300"
                                    id="combined-received-payment">0,00 ₺</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Working Stats Grid with Neon Effects -->
            <div class="p-4">
                <div class="grid grid-cols-3 gap-3">
                    <!-- Çalışılan -->
                    <div
                        class="flex flex-col items-center p-3 rounded-2xl bg-blue-50/30 dark:bg-blue-900/10 border border-blue-100/40 dark:border-blue-800/20 active:scale-95 transition-all shadow-[0_0_15px_-3px_rgba(59,130,246,0.15)]">
                        <div
                            class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-2 shadow-sm">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-lg">work</span>
                        </div>
                        <span id="combined-actual-worked"
                            class="text-lg font-black text-slate-800 dark:text-white leading-none">0</span>
                        <span
                            class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase mt-1.5 tracking-tighter">Çalışılan</span>
                    </div>

                    <!-- Ücretsiz İzin -->
                    <div
                        class="flex flex-col items-center p-3 rounded-2xl bg-amber-50/30 dark:bg-amber-900/10 border border-amber-100/40 dark:border-amber-800/20 active:scale-95 transition-all shadow-[0_0_15px_-3px_rgba(245,158,11,0.15)]">
                        <div
                            class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-2 shadow-sm">
                            <span
                                class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-lg">money_off</span>
                        </div>
                        <span id="combined-unpaid-leave"
                            class="text-lg font-black text-slate-800 dark:text-white leading-none">0</span>
                        <span
                            class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase mt-1.5 tracking-tighter text-center">Ücretsiz</span>
                    </div>

                    <!-- Ücretli İzin -->
                    <div
                        class="flex flex-col items-center p-3 rounded-2xl bg-emerald-50/30 dark:bg-emerald-900/10 border border-emerald-100/40 dark:border-emerald-800/20 active:scale-95 transition-all shadow-[0_0_15px_-3px_rgba(16,185,129,0.15)]">
                        <div
                            class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-2 shadow-sm">
                            <span
                                class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-lg">beach_access</span>
                        </div>
                        <span id="combined-paid-leave"
                            class="text-lg font-black text-slate-800 dark:text-white leading-none">0</span>
                        <span
                            class="text-[9px] text-slate-400 dark:text-slate-500 font-bold uppercase mt-1.5 tracking-tighter text-center">Ücretli</span>
                    </div>
                </div>

                <div
                    class="mt-4 pt-3 border-t border-slate-50 dark:border-slate-800/50 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary/60 dark:bg-primary/40 animate-pulse"></div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium"
                            id="combined-footer-label">Yükleniyor...</span>
                    </div>
                    <a href="?page=izin"
                        class="text-[10px] font-bold text-primary flex items-center gap-0.5 active:opacity-60 transition-opacity">
                        DETAYLAR <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>
    </section>


    <?php
    $PersonelModelForActions = new \App\Model\PersonelModel();
    $userSavedActionKeys = $PersonelModelForActions->getHizliIslemler($personel_id);

    $allQuickActionCatalog = [
        'izin' => [
            'key' => 'izin',
            'title' => 'İzin Talebi',
            'desc' => 'Yeni izin planla',
            'url' => '?page=izin',
            'icon' => 'event_busy',
            'gradient' => 'from-indigo-500 to-indigo-700',
            'neon' => 'neon-indigo',
            'text_color' => 'text-indigo-100/80',
        ],
        'yardim' => [
            'key' => 'yardim',
            'title' => 'Destek Talebi',
            'desc' => 'Yardım ve Destek',
            'url' => '?page=yardim',
            'icon' => 'support_agent',
            'gradient' => 'from-emerald-500 to-emerald-700',
            'neon' => 'neon-emerald',
            'text_color' => 'text-emerald-100/80',
        ],
        'bordro' => [
            'key' => 'bordro',
            'title' => 'Avanslar',
            'desc' => 'Avans Talebi Yap',
            'url' => '?page=bordro',
            'icon' => 'receipt_long',
            'gradient' => 'from-orange-500 to-orange-700',
            'neon' => 'neon-orange',
            'text_color' => 'text-orange-100/80',
        ],
        'zimmetler' => [
            'key' => 'zimmetler',
            'title' => 'Zimmetler',
            'desc' => 'Demirbaş Takibi',
            'url' => '?page=zimmetler',
            'icon' => 'inventory_2',
            'gradient' => 'from-amber-500 to-amber-700',
            'neon' => 'neon-amber',
            'text_color' => 'text-amber-100/80',
        ],
        'ihbar' => [
            'key' => 'ihbar',
            'title' => 'İhbar Yap',
            'desc' => 'Kaçak Su İhbarı',
            'url' => '?page=ihbar',
            'icon' => 'campaign',
            'gradient' => 'from-red-500 to-red-700',
            'neon' => 'neon-red',
            'text_color' => 'text-red-100/80',
        ],
        'puantaj' => [
            'key' => 'puantaj',
            'title' => 'Puantajım',
            'desc' => 'Aylık Çalışma Özeti',
            'url' => '?page=puantaj',
            'icon' => 'calendar_month',
            'gradient' => 'from-blue-500 to-blue-700',
            'neon' => 'neon-blue',
            'text_color' => 'text-blue-100/80',
        ],
        'talep' => [
            'key' => 'talep',
            'title' => 'Taleplerim',
            'desc' => 'Talep ve Görevler',
            'url' => '?page=talep',
            'icon' => 'assignment',
            'gradient' => 'from-violet-500 to-violet-700',
            'neon' => 'neon-violet',
            'text_color' => 'text-violet-100/80',
        ],
        'km-bildirimleri' => [
            'key' => 'km-bildirimleri',
            'title' => 'KM Bildirimi',
            'desc' => 'Araç KM Girişi',
            'url' => '?page=km-bildirimleri',
            'icon' => 'speed',
            'gradient' => 'from-rose-500 to-rose-700',
            'neon' => 'neon-rose',
            'text_color' => 'text-rose-100/80',
        ],
        'etkinlikler' => [
            'key' => 'etkinlikler',
            'title' => 'Etkinlikler',
            'desc' => 'Duyuru & Etkinlik',
            'url' => '?page=etkinlikler',
            'icon' => 'event',
            'gradient' => 'from-fuchsia-500 to-fuchsia-700',
            'neon' => 'neon-fuchsia',
            'text_color' => 'text-fuchsia-100/80',
        ],
        'icralar' => [
            'key' => 'icralar',
            'title' => 'İcralarım',
            'desc' => 'İcra Dosya Takibi',
            'url' => '?page=icralar',
            'icon' => 'gavel',
            'gradient' => 'from-purple-500 to-purple-700',
            'neon' => 'neon-purple',
            'text_color' => 'text-purple-100/80',
            'condition' => $hasIcra ?? false,
        ],
        'ekip-takibi' => [
            'key' => 'ekip-takibi',
            'title' => 'Ekip Takibi',
            'desc' => 'Saha Ekip Durumu',
            'url' => '?page=ekip-takibi',
            'icon' => 'groups',
            'gradient' => 'from-cyan-500 to-cyan-700',
            'neon' => 'neon-cyan',
            'text_color' => 'text-cyan-100/80',
            'condition' => ($isEndeksOkuma ?? false) && ($isEkipSefi ?? false),
        ],
        'nobet' => [
            'key' => 'nobet',
            'title' => 'Nöbet Çizelgesi',
            'desc' => 'Nöbet Listesi',
            'url' => '?page=nobet',
            'icon' => 'nights_stay',
            'gradient' => 'from-teal-500 to-teal-700',
            'neon' => 'neon-teal',
            'text_color' => 'text-teal-100/80',
            'condition' => $isKesmeAcma ?? false,
        ],
        'aparat' => [
            'key' => 'aparat',
            'title' => 'Aparat Takip',
            'desc' => 'Kesme-Açma Kaydı',
            'url' => '?page=aparat',
            'icon' => 'build',
            'gradient' => 'from-indigo-500 to-indigo-700',
            'neon' => 'neon-indigo',
            'text_color' => 'text-indigo-100/80',
            'condition' => $isKesmeAcma ?? false,
        ],
        'kacak' => [
            'key' => 'kacak',
            'title' => 'Kaçak İşlemleri',
            'desc' => 'Tutanak Bildirimi',
            'url' => '?page=kacak',
            'icon' => 'policy',
            'gradient' => 'from-red-600 to-rose-700',
            'neon' => 'neon-red',
            'text_color' => 'text-red-100/80',
            'condition' => $isKacakKontrol ?? false,
        ],
    ];

    $availableQuickActions = [];
    foreach ($allQuickActionCatalog as $key => $action) {
        if (!isset($action['condition']) || $action['condition'] === true) {
            $availableQuickActions[$key] = $action;
        }
    }

    $activeQuickActions = [];
    foreach ($userSavedActionKeys as $key) {
        if (isset($availableQuickActions[$key])) {
            $activeQuickActions[] = $availableQuickActions[$key];
        }
    }
    ?>

    <section class="px-4 mt-6 overflow-hidden">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Hızlı İşlemler</h2>
            <button type="button" onclick="openHizliIslemlerModal()" class="text-xs font-semibold text-primary flex items-center gap-1 bg-primary/10 hover:bg-primary/20 px-2.5 py-1 rounded-lg transition-colors active:scale-95">
                <span class="material-symbols-outlined text-sm">tune</span>
                <span>Düzenle</span>
            </button>
        </div>
        <div class="flex overflow-x-auto hide-scrollbar gap-3 pb-6 snap-x snap-mandatory">
            <?php if (empty($activeQuickActions)): ?>
                <div class="w-full p-4 text-center text-xs text-slate-400 bg-slate-100 dark:bg-slate-800 rounded-2xl">
                    Henüz eklenmiş bir hızlı işlem yok. "Düzenle" butonundan ekleyebilirsiniz.
                </div>
            <?php else: ?>
                <?php foreach ($activeQuickActions as $action): ?>
                    <a href="<?php echo htmlspecialchars($action['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        style="width: 105px !important; min-width: 105px !important; max-width: 105px !important; padding: 10px !important; border-radius: 1rem !important;"
                        class="quick-action group border-2 <?php echo htmlspecialchars($action['neon'], ENT_QUOTES, 'UTF-8'); ?> bg-gradient-to-br <?php echo htmlspecialchars($action['gradient'], ENT_QUOTES, 'UTF-8'); ?> transition-all active:scale-95 flex-shrink-0 snap-start">
                        <div
                            class="w-7 h-7 rounded-lg bg-white/20 backdrop-blur-md flex items-center justify-center mb-1.5 shadow-inner">
                            <span class="material-symbols-outlined text-white text-base filled"><?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="w-full">
                            <h3 class="font-bold text-[11px] text-white leading-tight truncate"><?php echo htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-[8.5px] <?php echo htmlspecialchars($action['text_color'], ENT_QUOTES, 'UTF-8'); ?> font-medium truncate mt-0.5"><?php echo htmlspecialchars($action['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div
                            class="absolute -right-2 -bottom-2 opacity-10 group-hover:opacity-20 transition-opacity pointer-events-none">
                            <span class="material-symbols-outlined text-3xl text-white"><?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>


    <!-- Hızlı İşlemler Özelleştirme Modalı -->
    <div id="hizli-islemler-modal" class="modal-overlay">
        <div class="modal-content p-5 pt-3 max-h-[85vh] flex flex-col">
            <div class="modal-handle"></div>

            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">tune</span>
                        Hızlı İşlemleri Özelleştir
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Sırasını değiştirin, yeni buton ekleyin veya çıkarın.</p>
                </div>
                <button type="button" onclick="Modal.close('hizli-islemler-modal')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-5 pr-1 hide-scrollbar" id="hizli-islemler-modal-body">
                <!-- Aktif (Ekli) Butonlar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aktif İşlemler (Sıralanabilir)</span>
                        <span class="text-[11px] font-semibold text-primary" id="active-count-badge">0 aktif</span>
                    </div>
                    <div id="active-actions-list" class="space-y-2">
                        <!-- Dynamic active items -->
                    </div>
                </div>

                <!-- Kullanılabilir (Eklenebilir) Butonlar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kullanılabilir İşlemler (Eklenebilir)</span>
                    </div>
                    <div id="available-actions-list" class="space-y-2">
                        <!-- Dynamic available items -->
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <button type="button" onclick="resetHizliIslemlerToDefault()" class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center gap-1 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-sm">restart_alt</span>
                    Sıfırla
                </button>
                <button type="button" onclick="saveHizliIslemlerConfig()" id="btn-save-hizli-islemler" class="flex-1 bg-primary text-white py-2.5 rounded-xl font-bold text-xs shadow-lg shadow-primary/30 flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-base">save</span>
                    Değişiklikleri Kaydet
                </button>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notification-modal" class="modal-overlay">
        <div class="modal-content p-6 pt-3">
            <div class="modal-handle"></div>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bildirimler</h3>
                <div class="flex items-center gap-2">
                    <button onclick="markAllAsRead()" class="text-xs text-primary font-medium"
                        title="Tümünü Okundu İşaretle">
                        <span class="material-symbols-outlined text-lg">done_all</span>
                    </button>
                    <button onclick="deleteAllNotifications()" class="text-xs text-red-500 font-medium"
                        title="Tümünü Sil">
                        <span class="material-symbols-outlined text-lg">delete_sweep</span>
                    </button>
                </div>
            </div>

            <div id="notification-list" class="flex flex-col gap-3 max-h-[60vh] overflow-y-auto">
                <!-- Bildirimler buraya yüklenecek -->
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            </div>

            <button onclick="Modal.close('notification-modal')"
                class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>

    <!-- Notification Modal follows directly -->

    <!-- Notification Detail Modal removed and replaced by Generic Full Screen Modal in index.php -->

    <script>
        // Global data
        var userIsSef = <?php echo json_encode($isSef ?? false); ?>;
        var lastUpdateDate = "<?php echo Helper::getLastUpdateDate(['yapilan_isler', 'endeks_okuma']); ?>";
        var allActivitiesData = [];
        var allNotificationsData = [];
        var currentNotificationIndex = -1;

        var gorevSureInterval = null;
        var gorevBaslangicZamani = null;

        document.addEventListener('DOMContentLoaded', function () {
            // iOS Kurulum Rehberi Kontrolü
            checkIOSInstallGuide();

            // Load görev durumu (öncelikli)
            loadGorevDurumu();
            // Load dashboard data
            loadDashboardData();
            // Load notification count
            loadNotificationCount();
            // Load events slider
            loadEtkinlikSlider();
            // Load work stats
            loadWorkStats();
            // Load çalışma bilgileri
            loadCalismaStats();

            // --- ANLIK KONUM İSTEĞİ KONTROLÜ ---
            // Yönlendirme ekranındaki 30 saniyelik bekleme penceresine yanıt verebilmek için sık kontrol et.
            checkKonumIstegi();
            setInterval(checkKonumIstegi, 10000);
            setInterval(canliKonumGuncelle, 120000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') canliKonumGuncelle();
            });
        });

        async function checkKonumIstegi() {
            try {
                const response = await API.request('checkKonumIstegi');
                if (response.success && response.data && response.data.istek_id) {
                    const istekId = response.data.istek_id;
                    console.log('Anlık konum isteği alındı (ID: ' + istekId + '). Konum alınıyor...');

                    // getKonum() fonksiyonu aşağıda tanımlı olmalı
                    const konum = await getKonum();
                    if (konum) {
                        await API.request('yanitlaKonumIstegi', {
                            istek_id: istekId,
                            lat: konum.enlem,
                            lng: konum.boylam
                        });
                        console.log('Anlık konum başarıyla iletildi.');
                    }
                }
            } catch (error) {
                console.error('Konum isteği kontrol hatası:', error);
            }
        }

        let canliKonumGuncelleniyor = false;
        async function canliKonumGuncelle() {
            if (!gorevBaslangicZamani || canliKonumGuncelleniyor || document.visibilityState !== 'visible') return;
            canliKonumGuncelleniyor = true;
            try {
                const konum = await getKonum();
                await API.request('canliKonumGuncelle', {
                    lat: konum.enlem,
                    lng: konum.boylam,
                    hassasiyet: konum.hassasiyet
                });
            } catch (error) {
                console.warn('Canlı konum güncellenemedi:', error);
            } finally {
                canliKonumGuncelleniyor = false;
            }
        }

        // iOS Kurulum Rehberi Fonksiyonları
        function checkIOSInstallGuide() {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

            if (isIOS && !isStandalone) {
                document.getElementById('ios-install-guide').classList.remove('hidden');
            }
        }

        function showInstallInstructions() {
            Swal.fire({
                title: 'Uygulamayı Yükleyin',
                html: `
                    <div class="text-left text-sm leading-relaxed p-2">
                        <p class="mb-3">Bildirimlerin çalışması ve tam uygulama deneyimi için Ersan Elektrik'i ana ekranınıza ekleyin:</p>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">1</div>
                                <p>Safari alt çubuğundaki <b>Paylaş</b> simgesine <img src="https://simpleicons.org/icons/safari.svg" style="display:inline; width:14px;"/> tıklayın.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">2</div>
                                <p>Menüyü yukarı kaydırıp <b>Ana Ekrana Ekle</b> seçeneğine dokunun.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">3</div>
                                <p>Sağ üstten <b>Ekle</b>'ye basın ve uygulamayı ana ekranınızdan açın.</p>
                            </div>
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Anladım',
                customClass: {
                    popup: 'swal-custom-popup',
                    title: 'swal-custom-title',
                    htmlContainer: 'swal-custom-content',
                    actions: 'swal-custom-actions',
                    confirmButton: 'swal-custom-confirm swal-confirm-primary',
                }
            });
        }

        // ===== GÖREV TAKİP FONKSİYONLARI =====

        async function loadGorevDurumu() {
            try {
                var response = await API.request('getGorevDurumu');

                document.getElementById('gorev-loading').classList.add('hidden');
                document.getElementById('gorev-durumu-container').classList.remove('hidden');

                if (response.success && response.data) {
                    if (response.data.gorev_var) {
                        // Aktif görev var - Bitir panelini göster
                        showGorevBitirPanel(response.data);
                    } else {
                        // Görev yok - Başla panelini göster
                        showGorevBaslaPanel();
                    }
                } else {
                    showGorevBaslaPanel();
                }
            } catch (error) {
                console.error('Görev durumu yüklenemedi:', error);
                document.getElementById('gorev-loading').classList.add('hidden');
                document.getElementById('gorev-durumu-container').classList.remove('hidden');
                showGorevBaslaPanel();
            }
        }

        function showGorevBaslaPanel() {
            document.getElementById('gorev-basla-panel').classList.remove('hidden');
            document.getElementById('gorev-bitir-panel').classList.add('hidden');

            // Konum izni kontrolü
            checkKonumIzni();
        }

        function showGorevBitirPanel(data) {
            document.getElementById('gorev-basla-panel').classList.add('hidden');
            document.getElementById('gorev-bitir-panel').classList.remove('hidden');

            // Başlangıç saatini göster
            document.getElementById('gorev-baslangic-saat').textContent = data.baslangic_saat || '--:--';

            // Süre takibini başlat
            // Safari ve bazı mobil tarayıcılar için ISO formatına (boşluk yerine T) dönüştür
            var zamanStr = data.baslangic_zamani;
            if (zamanStr && typeof zamanStr === 'string') {
                zamanStr = zamanStr.replace(' ', 'T');
            }
            gorevBaslangicZamani = new Date(zamanStr);
            canliKonumGuncelle();
            updateGecenSure();
            gorevSureInterval = setInterval(updateGecenSure, 60000); // Her dakika güncelle
        }

        function updateGecenSure() {
            if (!gorevBaslangicZamani) return;

            var simdi = new Date();
            // Safari uyumluluğu için NaN kontrolü ve güvenli tarih farkı hesaplama
            var diff = simdi.getTime() - gorevBaslangicZamani.getTime();

            if (isNaN(diff) || diff < 0) {
                document.getElementById('gorev-gecen-sure').textContent = '...';
                return;
            }

            var dakika = Math.floor(diff / 60000);
            var saat = Math.floor(dakika / 60);
            dakika = dakika % 60;

            var sureText = '';
            if (saat > 0) {
                sureText = saat + ' sa ' + dakika + ' dk';
            } else {
                sureText = dakika + ' dk';
            }

            document.getElementById('gorev-gecen-sure').textContent = sureText;
        }

        async function checkKonumIzni() {
            if (!navigator.geolocation) {
                showKonumUyari();
                disableGorevButton();
                return;
            }

            try {
                var permission = await navigator.permissions.query({ name: 'geolocation' });

                if (permission.state === 'denied') {
                    showKonumUyari();
                    disableGorevButton();
                } else {
                    hideKonumUyari();
                    enableGorevButton();
                }

                permission.onchange = function () {
                    if (this.state === 'denied') {
                        showKonumUyari();
                        disableGorevButton();
                    } else {
                        hideKonumUyari();
                        enableGorevButton();
                    }
                };
            } catch (error) {
                // Permissions API desteklenmiyorsa devam et
                hideKonumUyari();
                enableGorevButton();
            }
        }

        function showKonumUyari() {
            document.getElementById('konum-izni-uyari').classList.remove('hidden');
        }

        function hideKonumUyari() {
            document.getElementById('konum-izni-uyari').classList.add('hidden');
        }

        async function requestKonumIzni() {
            try {
                // getKonum() navigator.geolocation.getCurrentPosition() çağrısı yaptığı için 
                // tarayıcının izin penceresini tetikler.
                await getKonum();
                // İzin verildikten sonra kontrolü tekrar çalıştır
                checkKonumIzni();
            } catch (error) {
                console.error('Konum izni isteği hatası:', error);
                // Hata mesajını kullanıcıya göster (Reddedildi vs)
                Toast.show(error.message, 'error');
            }
        }

        function disableGorevButton() {
            var btn = document.getElementById('btn-gorev-basla');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        function enableGorevButton() {
            var btn = document.getElementById('btn-gorev-basla');
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        function getKonum() {
            return new Promise(function (resolve, reject) {
                if (!navigator.geolocation) {
                    reject(new Error('Konum servisi bu tarayıcıda desteklenmiyor.'));
                    return;
                }

                // Localhost testi için yardımcı mesaj
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log('Localhost üzerindesiniz, konum alma biraz zaman alabilir...');
                }

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        resolve({
                            enlem: position.coords.latitude,
                            boylam: position.coords.longitude,
                            hassasiyet: position.coords.accuracy
                        });
                    },
                    function (error) {
                        var message = '';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                message = 'Konum izni reddedildi. Lütfen tarayıcı ayarlarından konuma izin verin.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                message = 'Konum bilgisi şu an ulaşılamaz durumda. GPS sinyalini kontrol edin.';
                                break;
                            case error.TIMEOUT:
                                message = 'Konum isteği zaman aşımına uğradı. Tekrar deneyiniz.';
                                break;
                            default:
                                message = 'Konum alınırken bilinmeyen bir hata oluştu.';
                        }

                        // Localhost için özel durum: Gerçekten konum alınamıyorsa sabit bir konum önerelim mi?
                        // Şimdilik sadece hata mesajını detaylandırıyoruz.
                        reject(new Error(message));
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 20000, // 20 saniye
                        maximumAge: 0
                    }
                );
            });
        }

        async function gorevBasla() {
            // if (!userIsSef) {
            //     Toast.show('Bu işlemi gerçekleştirmek için "Şef" yetkisine sahip olmalısınız.', 'error');
            //     return;
            // }

            var btn = document.getElementById('btn-gorev-basla');
            var originalHtml = btn.innerHTML;

            // Butonu disable yap
            btn.disabled = true;
            btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Konum Alınıyor...</span>';

            try {
                // Konum al
                var konum = await getKonum();

                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Kaydediliyor...</span>';

                // API'ye gönder
                var response = await API.request('baslaGorev', {
                    konum_enlem: konum.enlem,
                    konum_boylam: konum.boylam,
                    konum_hassasiyeti: konum.hassasiyet,
                    cihaz_bilgisi: navigator.userAgent
                });

                if (response.success) {
                    Toast.show(response.message || 'Göreve başarıyla başladınız!', 'success');

                    // Paneli güncelle
                    showGorevBitirPanel({
                        baslangic_saat: response.data.baslangic_saat,
                        baslangic_zamani: new Date().toISOString()
                    });
                } else {
                    if (response.data && response.data.requires_morning_km) {
                        Toast.show(response.message, 'warning');
                        openKmBildirModal(null, true, false, response.data.missing_yesterday_evening_km === true);
                    } else {
                        Toast.show(response.message || 'Görev başlatılamadı', 'error');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Görev Başla Hatası:', error);
                Toast.show(error.message || 'Bir hata oluştu', 'error');

                // Konum izni reddedildiyse uyarı göster
                if (error.message && error.message.includes('izni')) {
                    showKonumUyari();
                }
            } finally {
                // Buton her durumda eski haline dönsün
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function gorevBitir(kmHatirlatmayiAtla = false) {
            if (!kmHatirlatmayiAtla) {
                try {
                    const kmStatus = await API.request('get-evening-km-status', {}, false);
                    if (kmStatus.success && kmStatus.data.has_active_vehicle && !kmStatus.data.reported) {
                        const kmBildir = await Alert.confirm(
                            'Akşam KM Bildirimi',
                            `${kmStatus.data.plaka} plakalı aracın akşam KM bildirimi henüz yapılmadı. Şimdi bildirmek ister misiniz?`,
                            'Akşam KM Bildir',
                            'Şimdi Değil'
                        );

                        if (kmBildir) {
                            openKmBildirModal(null, false, true);
                            return;
                        }

                        API.request('log-evening-km-skip', {}, false).catch(function (error) {
                            console.error('Akşam KM erteleme tercihi kaydedilemedi:', error);
                        });
                    }
                } catch (error) {
                    console.error('Akşam KM durumu alınamadı:', error);
                }
            }

            var confirmed = await Alert.confirm(
                'Görevi Bitir',
                'Görevinizi bitirmek istediğinize emin misiniz?',
                'Evet, Bitir',
                'Vazgeç'
            );

            if (!confirmed) return;

            var btn = document.getElementById('btn-gorev-bitir');
            var originalHtml = btn.innerHTML;

            try {
                // Butonu disable yap ve spinner göster
                btn.disabled = true;
                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Konum Alınıyor...</span>';

                // Konum al
                var konum = await getKonum();

                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Kaydediliyor...</span>';

                // API'ye gönder
                var response = await API.request('bitirGorev', {
                    konum_enlem: konum.enlem,
                    konum_boylam: konum.boylam,
                    konum_hassasiyeti: konum.hassasiyet,
                    cihaz_bilgisi: navigator.userAgent
                });

                if (response.success) {
                    // Süre takibini durdur
                    if (gorevSureInterval) {
                        clearInterval(gorevSureInterval);
                        gorevSureInterval = null;
                    }
                    gorevBaslangicZamani = null;

                    Toast.show(response.message || 'Görev başarıyla tamamlandı!', 'success');

                    // Butonu temizle (panel gizlenecek olsa da UI tutarlılığı için)
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;

                    // Paneli güncelle ve verileri yenile
                    showGorevBaslaPanel();
                    loadDashboardData();
                } else {
                    Toast.show(response.message || 'Görev bitirilemedi', 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Görev Bitir Hatası:', error);
                Toast.show(error.message || 'Bir hata oluştu', 'error');
            } finally {
                // Buton her durumda eski haline dönsün
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function loadWorkStats() {
            var container = document.getElementById('work-stats-container');
            // Show loading if container is empty or has items (to show refresh)
            if (container.children.length > 1 || container.querySelector('.animate-spin') === null) {
                container.innerHTML = '<div class="col-span-2 py-8 flex justify-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';
            }

            try {
                var response = await API.request('getWorkStats');
                if (response.success && response.data) {
                    var stat = response.data;

                    let todayBreakdown = '';
                    let monthBreakdown = '';

                    if (stat.is_sayac_ekibi) {
                        // Sayaç Ekipleri için özel etiketler
                        let dailyParts = [];
                        if (stat.details.daily_sekme) {
                            if (stat.details.daily_sekme.sokme_takma > 0) dailyParts.push(`${stat.details.daily_sekme.sokme_takma} Sayaç Değ.`);
                            if (stat.details.daily_sekme.kesme > 0) dailyParts.push(`${stat.details.daily_sekme.kesme} Kesme-Açma`);
                        }
                        if (stat.details && stat.details.daily_kacak > 0) dailyParts.push(`${stat.details.daily_kacak} Kaçak`);
                        if (dailyParts.length > 0) {
                            todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${dailyParts.join(' &bull; ')}</p>`;
                        }

                        let monthlyParts = [];
                        if (stat.details.monthly_sekme) {
                            if (stat.details.monthly_sekme.sokme_takma > 0) monthlyParts.push(`${stat.details.monthly_sekme.sokme_takma} Sayaç Değ.`);
                            if (stat.details.monthly_sekme.kesme > 0) monthlyParts.push(`${stat.details.monthly_sekme.kesme} Kesme-Açma`);
                        }
                        if (stat.details && stat.details.monthly_kacak > 0) monthlyParts.push(`${stat.details.monthly_kacak} Kaçak`);
                        if (monthlyParts.length > 0) {
                            monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${monthlyParts.join(' &bull; ')}</p>`;
                        }
                    } else if (stat.is_kacak_ekibi) {
                        // Kaçak Kontrol Ekipleri (Bekleyen Kaçaklar Dahil)
                        let dailyParts = [];
                        if (stat.details && stat.details.daily_kacak > 0) dailyParts.push(`${stat.details.daily_kacak} Kaçak Tutanak`);
                        if (stat.details && stat.details.daily_isler > 0) dailyParts.push(`${stat.details.daily_isler} İş`);
                        if (dailyParts.length > 0) {
                            todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${dailyParts.join(' &bull; ')}</p>`;
                        }

                        let monthlyParts = [];
                        if (stat.details && stat.details.monthly_kacak > 0) monthlyParts.push(`${stat.details.monthly_kacak} Kaçak Tutanak`);
                        if (stat.details && stat.details.monthly_isler > 0) monthlyParts.push(`${stat.details.monthly_isler} İş`);
                        if (monthlyParts.length > 0) {
                            monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${monthlyParts.join(' &bull; ')}</p>`;
                        }
                    } else {
                        // Normal ekipler için (Endeks + Kesme + Kaçak)
                        if (stat.details && (stat.details.daily_isler > 0 || stat.details.daily_endeks > 0 || stat.details.daily_kacak > 0)) {
                            let parts = [];
                            if (stat.details.daily_isler > 0) parts.push(`${stat.details.daily_isler} Kesme`);
                            if (stat.details.daily_endeks > 0) parts.push(`${stat.details.daily_endeks} Endeks`);
                            if (stat.details.daily_kacak > 0) parts.push(`${stat.details.daily_kacak} Kaçak`);
                            if (parts.length > 0) {
                                todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }

                        if (stat.details && (stat.details.monthly_isler > 0 || stat.details.monthly_endeks > 0 || stat.details.monthly_kacak > 0)) {
                            let parts = [];
                            if (stat.details.monthly_isler > 0) parts.push(`${stat.details.monthly_isler} Kesme`);
                            if (stat.details.monthly_endeks > 0) parts.push(`${stat.details.monthly_endeks} Endeks`);
                            if (stat.details.monthly_kacak > 0) parts.push(`${stat.details.monthly_kacak} Kaçak`);
                            if (parts.length > 0) {
                                monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }
                    }

                    let siralamaHtml = '';
                    if (stat.siralama) {
                        siralamaHtml = `
                            <div class="col-span-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                                <h4 class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Aylık Performans Sıralaması</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/80 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 shadow-sm active:scale-[0.98] transition-all">
                                        <div class="relative shrink-0">
                                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex flex-col items-center justify-center shadow-lg shadow-blue-500/30">
                                                <span class="text-[10px] text-white/70 font-bold leading-none mb-0.5">SIRA</span>
                                                <span class="text-lg font-black text-white leading-none">#${stat.siralama.ekip_sira}</span>
                                            </div>
                                            <!-- Ring effect -->
                                            <div class="absolute -inset-1 rounded-2xl border border-blue-500/20 animate-pulse"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-tighter mb-0.5">BÖLGE GENELİ</p>
                                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 truncate" title="${stat.ekip_bolge || 'Bölge Bulunamadı'}">${stat.ekip_bolge || 'Bölge Bulunamadı'}</p>
                                            <p class="text-[9px] text-slate-400 font-medium">${stat.siralama.ekip_kisi} Kişi Arasında</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/80 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 shadow-sm active:scale-[0.98] transition-all">
                                        <div class="relative shrink-0">
                                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex flex-col items-center justify-center shadow-lg shadow-indigo-500/30">
                                                <span class="text-[10px] text-white/70 font-bold leading-none mb-0.5">SIRA</span>
                                                <span class="text-lg font-black text-white leading-none">#${stat.siralama.departman_sira}</span>
                                            </div>
                                            <!-- Ring effect -->
                                            <div class="absolute -inset-1 rounded-2xl border border-indigo-500/20 animate-pulse"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-tighter mb-0.5">DEPARTMAN</p>
                                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 truncate" title="${stat.departman || 'Departman Bulunamadı'}">${stat.departman || 'Departman Bulunamadı'}</p>
                                            <p class="text-[9px] text-slate-400 font-medium">${stat.siralama.departman_kisi} Kişi Arasında</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    container.innerHTML = `
                        <div class="col-span-2 card p-4 flex flex-col gap-2 relative overflow-hidden group">
                            <div class="absolute -right-2 -bottom-2 opacity-[0.05] group-hover:opacity-[0.1] transition-opacity">
                                <span class="material-symbols-outlined text-8xl text-primary">task_alt</span>
                            </div>
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary text-xl">fact_check</span>
                                </div>
                                <div>
                                    <p class="text-slate-500 dark:text-slate-400 text-sm font-bold uppercase tracking-wider">Toplam Tamamlanan İş</p>
                                    <p class="text-[10px] text-slate-400 font-medium">Son Güncelleme: ${lastUpdateDate}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Bugün</p>
                                    <p class="text-2xl font-black text-slate-900 dark:text-white">${stat.today}</p>
                                    ${todayBreakdown}
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Bu Ay</p>
                                    <p class="text-2xl font-black text-slate-900 dark:text-white">${stat.month}</p>
                                    ${monthBreakdown}
                                </div>
                            </div>
                            ${siralamaHtml}
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="col-span-2 card p-8 flex flex-col items-center justify-center text-center opacity-60"><span class="material-symbols-outlined text-4xl mb-2 text-slate-300">history_toggle_off</span><p class="text-sm text-slate-500">Bu dönemde henüz iş kaydı bulunmamaktadır.</p></div>';
                }
            } catch (error) {
                console.error('Work stats load error:', error);
                container.innerHTML = '<div class="col-span-2 card p-6 text-center text-red-500 text-sm">Veriler yüklenirken bir hata oluştu.</div>';
            }
        }

        async function loadCalismaStats() {
            var donemLabel = document.getElementById('combined-donem-label');
            var footerLabel = document.getElementById('combined-footer-label');
            var workedEl = document.getElementById('combined-actual-worked');
            var unpaidEl = document.getElementById('combined-unpaid-leave');
            var paidEl = document.getElementById('combined-paid-leave');

            var aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            var now = new Date();
            var year = now.getFullYear();
            var month = now.getMonth();
            var daysInMonth = new Date(year, month + 1, 0).getDate();

            var includeToday = now.getHours() >= 17;
            var totalDays = includeToday ? now.getDate() : Math.max(0, now.getDate() - 1);
            var limitDay = totalDays;

            if (donemLabel) donemLabel.textContent = aylar[month] + ' ' + year;

            try {
                var response = await API.request('getIzinler');
                var unpaidLeaveDays = 0;
                var paidLeaveDays = 0;

                if (response.success && response.data && response.data.length > 0 && totalDays > 0) {
                    var monthStart = new Date(year, month, 1);
                    var monthEnd = new Date(year, month, Math.max(1, limitDay));
                    monthStart.setHours(0, 0, 0, 0);
                    monthEnd.setHours(0, 0, 0, 0);

                    response.data.forEach(function (izin) {
                        var status = (izin.durum || '').toLowerCase();
                        if (status !== 'onaylandi' && status !== 'onaylandı') return;

                        var start = parseCalismaDate(izin.baslangic);
                        var end = parseCalismaDate(izin.bitis);
                        if (!start || !end) return;

                        start.setHours(0, 0, 0, 0);
                        end.setHours(0, 0, 0, 0);

                        if (start > monthEnd || end < monthStart) return;

                        var overlapStart = start < monthStart ? monthStart : start;
                        var overlapEnd = end > monthEnd ? monthEnd : end;

                        var diffTime = Math.abs(overlapEnd - overlapStart);
                        var overlapDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                        var typeName = (izin.izin_tipi_text || '').toLowerCase();
                        if (typeName.includes('ücretsiz') || typeName.includes('ucretsiz')) {
                            unpaidLeaveDays += overlapDays;
                        } else {
                            paidLeaveDays += overlapDays;
                        }
                    });
                }

                var actualWorked = Math.max(0, totalDays - (paidLeaveDays + unpaidLeaveDays));

                if (workedEl) workedEl.textContent = actualWorked;
                if (unpaidEl) unpaidEl.textContent = unpaidLeaveDays;
                if (paidEl) paidEl.textContent = paidLeaveDays;
                if (footerLabel) footerLabel.textContent = (includeToday ? 'Bugün dahil' : 'Düne kadar') + ' · Toplam ' + totalDays + ' / ' + daysInMonth + ' gün';

            } catch (error) {
                console.error('Çalışma stats load error:', error);
            }
        }

        function parseCalismaDate(str) {
            if (!str) return null;
            var parts = str.split('.');
            if (parts.length === 3) {
                return new Date(parts[2], parseInt(parts[1]) - 1, parts[0]);
            }
            return new Date(str);
        }

        async function loadDashboardData() {
            try {
                var response = await API.request('getDashboardData');
                if (response.success) {
                    var totalEarningEl = document.getElementById('combined-total-earning');
                    var receivedPaymentEl = document.getElementById('combined-received-payment');
                    var remainingBalanceEl = document.getElementById('combined-remaining-balance');
                    var donemEl = document.getElementById('combined-hakedis-donem');

                    if (totalEarningEl) totalEarningEl.textContent = Format.currency(response.data.total_earning || 0);
                    if (receivedPaymentEl) receivedPaymentEl.textContent = Format.currency(response.data.received_payment || 0);
                    if (remainingBalanceEl) remainingBalanceEl.textContent = Format.currency(response.data.remaining_balance || 0);

                    if (response.data.son_donem_adi && donemEl) {
                        donemEl.textContent = response.data.son_donem_adi;
                        donemEl.classList.remove('hidden');
                    }
                }
            } catch (error) {
                console.error('Dashboard data load error:', error);
            }
        }

        async function loadNotificationCount() {
            try {
                var response = await API.request('getMyNotifications');
                if (response.success && response.data) {
                    // Sadece okunmamış bildirimleri say
                    var unreadCount = response.data.filter(function (n) { return !n.okundu; }).length;
                    var badge = document.getElementById('notification-badge');
                    if (badge) {
                        if (unreadCount > 0) {
                            badge.style.display = 'flex';
                            badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            } catch (error) {
                console.error('Notification count error:', error);
            }
        }

        // RecentActivities Removed

        async function loadEtkinlikSlider() {
            var container = document.getElementById('etkinlik-slider-container');
            var section = document.getElementById('etkinlik-slider-section');

            try {
                var response = await API.request('getEtkinlikSlider');

                if (response.success && response.data && response.data.length > 0) {
                    section.style.display = 'block';

                    container.innerHTML = response.data.map(function (duyuru) {
                        var bgImg = 'background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);';

                        var duyuruJson = JSON.stringify(duyuru).replace(/\\/g, "\\\\").replace(/"/g, "&quot;").replace(/'/g, "\\'");
                        var onClick = "showEtkinlikFullScreen('" + duyuruJson + "');";
                        var cursorClass = 'cursor-pointer';

                        var kalan_gun_html = '';
                        if (duyuru.kalan_gun !== null && duyuru.kalan_gun !== undefined) {
                            kalan_gun_html = '<div class="absolute -top-6 -right-2 pointer-events-none select-none z-0 flex flex-col items-end opacity-80">' +
                                '<span class="text-[9rem] font-black leading-[0.8] tracking-tighter bg-gradient-to-bl from-white/70 to-white/0 text-transparent bg-clip-text">' + escapeHtml(duyuru.kalan_gun) + '</span>' +
                                '<span class="text-[10px] font-bold text-white/40 uppercase tracking-[0.2em] relative -top-6 pr-6">GÜN KALDI</span>' +
                                '</div>';
                        }

                        return '<div class="snap-center shrink-0 w-[85%] sm:w-[300px] rounded-2xl p-4 text-white shadow-lg relative overflow-hidden transition-transform active:scale-[0.98] ' + cursorClass + '" ' +
                            'style="' + bgImg + '" onclick="' + onClick + '">' +
                            kalan_gun_html +
                            '<div class="relative z-10 pr-2">' + // removed large pr-16 padding to let text flow
                            '<span class="badge badge-primary bg-white/20 text-white border-none mb-2 text-[10px]">' + escapeHtml(duyuru.tarih) + '</span>' +
                            '<h3 class="font-bold text-lg leading-tight mb-1 text-white truncate max-w-[85%]">' + escapeHtml(duyuru.baslik) + '</h3>' +
                            '<p class="text-xs text-white/80 line-clamp-2 max-w-[85%]">' + escapeHtml(duyuru.icerik ? duyuru.icerik.replace(/<[^>]*>?/gm, '') : '') + '</p>' +
                            '</div>' +
                            '</div>';
                    }).join('');
                } else {
                    section.style.display = 'none';
                }
            } catch (error) {
                console.error('Slider load error:', error);
                section.style.display = 'none';
            }
        }

        // RenderActivityItem Removed

        function openNotificationModal() {
            Modal.open('notification-modal');
            loadNotifications();
        }

        async function loadNotifications() {
            var container = document.getElementById('notification-list');
            container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';

            try {
                var response = await API.request('getMyNotifications');

                if (response.success && response.data && response.data.length > 0) {
                    allNotificationsData = response.data;
                    container.innerHTML = response.data.map(function (notification, index) {
                        var unreadIndicator = notification.okundu ? '' : '<div class="absolute top-2 left-2 w-2 h-2 bg-primary rounded-full"></div>';
                        var bgClass = notification.okundu ? 'bg-slate-50 dark:bg-slate-800' : 'bg-blue-50 dark:bg-blue-900/20 border border-primary/20';
                        
                        var isNobet = notification.type === 'nobet_degisim';
                        var icon = isNobet ? 'swap_horiz' : 'notifications';
                        var iconBg = isNobet ? 'bg-amber-100' : 'bg-blue-100';
                        var iconColor = isNobet ? 'text-amber-600' : 'text-blue-600';

                        // Resim varsa küçük thumbnail göster
                        var thumbnailHtml = notification.image
                            ? '<img src="' + escapeHtml(notification.image) + '" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" onerror="this.style.display=\'none\'">'
                            : '';

                        return '<div class="relative flex items-start gap-3 p-3 ' + bgClass + ' rounded-xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" onclick="showNotificationDetail(' + index + ')">' +
                            unreadIndicator +
                            '<div class="w-8 h-8 rounded-full ' + iconBg + ' flex items-center justify-center flex-shrink-0">' +
                            '<span class="material-symbols-outlined ' + iconColor + ' text-lg">' + icon + '</span>' +
                            '</div>' +
                            '<div class="flex-1 min-w-0">' +
                            '<p class="text-sm font-medium text-slate-900 dark:text-white ' + (notification.okundu ? '' : 'font-bold') + '">' + escapeHtml(notification.title) + '</p>' +
                            '<p class="text-xs text-slate-500 line-clamp-2">' + escapeHtml(notification.body) + '</p>' +
                            '<p class="text-[10px] text-primary mt-1">' + notification.time_ago + '</p>' +
                            '</div>' +
                            thumbnailHtml +
                            '<span class="material-symbols-outlined text-slate-400 text-lg self-center">chevron_right</span>' +
                            '</div>';
                    }).join('');
                } else {
                    container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-slate-300 mb-2">notifications_off</span><p class="text-sm text-slate-500">Henüz bildirim yok</p></div>';
                }
            } catch (error) {
                console.error('Notifications load error:', error);
                container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-red-300 mb-2">error</span><p class="text-sm text-slate-500">Bildirimler yüklenemedi</p></div>';
            }
        }

        async function showNotificationDetail(index) {
            var notification = allNotificationsData[index];
            if (!notification) return;

            currentNotificationIndex = index;

            // Bildirimi okundu olarak işaretle (Sadece push tipi için)
            if (!notification.okundu && notification.type === 'push') {
                await API.request('markNotificationRead', { notification_id: notification.id });
                allNotificationsData[index].okundu = true;
                loadNotificationCount(); // Badge'i güncelle
            }

            const isNobet = notification.type === 'nobet_degisim';
            const bgImg = isNobet 
                ? `background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);` // Amber for shift requests
                : `background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);`; // Dark slate for notifications
            
            let imageHtml = '';
            if (notification.image) {
                imageHtml = `
                    <div class="mt-8">
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 pl-1">EKLİ GÖRSEL</p>
                        <div class="rounded-2xl overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800 relative bg-slate-100 dark:bg-slate-800">
                            <img src="${escapeHtml(notification.image)}" class="w-full h-auto object-cover max-h-[400px]" alt="Bildirim Görseli">
                        </div>
                    </div>
                `;
            }

            let actionsHtml = '';
            if (isNobet) {
                actionsHtml = `
                    <div class="flex items-center gap-3">
                        <button onclick="reddetNotificationTalep('${notification.talep_id}')" class="w-10 h-10 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center pointer-events-auto active:scale-90 transition-transform">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                        <button onclick="onaylaNotificationTalep('${notification.talep_id}')" class="px-6 h-10 rounded-full bg-white text-amber-600 font-bold text-sm flex items-center justify-center pointer-events-auto active:scale-95 transition-transform shadow-lg">
                            Onayla
                        </button>
                    </div>
                `;
            } else {
                actionsHtml = `
                    <button onclick="deleteCurrentNotification()" class="w-10 h-10 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center pointer-events-auto active:scale-90 transition-transform">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                `;
            }

            const html = `
                <div class="header-main relative px-6 pt-12 pb-8 flex flex-col items-start shadow-xl rounded-b-[2.5rem] safe-area-top shrink-0 overflow-hidden" style="${bgImg}">
                    <div class="absolute inset-0 opacity-10 overflow-hidden rounded-b-[2.5rem] pointer-events-none">
                        <span class="material-symbols-outlined absolute -right-4 -top-4 text-[10rem] text-white opacity-10">${isNobet ? 'swap_horiz' : 'notifications'}</span>
                    </div>
                    
                    <div class="relative w-full z-10 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-6">
                            <div class="w-10 h-10"></div> <!-- Placeholder -->
                            <span class="bg-white/10 backdrop-blur-md border border-white/10 text-white/90 rounded-lg px-3 py-1 text-[11px] font-semibold tracking-wide shadow-sm">${escapeHtml(notification.time_ago)}</span>
                        </div>

                        <div class="flex flex-col justify-end mt-2">
                            <h1 class="text-white text-2xl font-black tracking-tight leading-[1.15] break-words" style="text-shadow: 0 4px 8px rgba(0,0,0,0.5);">${escapeHtml(notification.title)}</h1>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-8 flex-1 bg-transparent -mt-5 relative z-20">
                    <div class="bg-white dark:bg-card-dark rounded-[2rem] p-6 shadow-xl shadow-black/5 dark:shadow-black/20 border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-700 dark:text-slate-300 text-[15px] leading-relaxed whitespace-pre-wrap">${escapeHtml(notification.body)}</p>
                        ${imageHtml}
                        ${isNobet ? `
                            <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-100 dark:border-amber-900/20">
                                <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">Bu talebi onayladığınızda, ilgili tarihteki nöbet sizin üzerinize atanacak ve bir amirin onayına sunulacaktır.</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            Modal.close('notification-modal');
            showPwaFullModal({ 
                html: html,
                actionsHtml: actionsHtml
            });
        }

        async function onaylaNotificationTalep(talepId) {
            const confirmed = await Alert.confirm('Talebi Onayla', 'Bu nöbet değişim talebini onaylamak istediğinize emin misiniz?', 'Evet, Onayla', 'Vazgeç');
            if (!confirmed) return;

            try {
                const response = await API.request('onaylaNobetDegisimTalebi', { talep_id: talepId });
                if (response.success) {
                    Toast.show('Talep onaylandı. Yönetici onayını bekliyor.', 'success');
                    closePwaFullModal();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        async function reddetNotificationTalep(talepId) {
            const confirmed = await Alert.confirm('Talebi Reddet', 'Bu nöbet değişim talebini reddetmek istediğinize emin misiniz?', 'Evet, Reddet', 'Vazgeç');
            if (!confirmed) return;

            try {
                const response = await API.request('reddetNobetDegisimTalebi', { talep_id: talepId });
                if (response.success) {
                    Toast.show('Talep reddedildi', 'success');
                    closePwaFullModal();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        function closeNotificationDetail() {
            closePwaFullModal();
            setTimeout(function () {
                Modal.open('notification-modal');
                loadNotifications(); // Listeyi güncelle
            }, 200);
        }

        async function deleteCurrentNotification() {
            if (currentNotificationIndex < 0) return;

            var notification = allNotificationsData[currentNotificationIndex];
            if (!notification) return;

            var confirmed = await Alert.confirm('Bildirimi Sil', 'Bu bildirimi silmek istediğinize emin misiniz?', 'Evet, Sil', 'Vazgeç');
            if (!confirmed) return;

            try {
                var response = await API.request('deleteNotification', { notification_id: notification.id });
                if (response.success) {
                    Toast.show('Bildirim silindi', 'success');
                    allNotificationsData.splice(currentNotificationIndex, 1);
                    currentNotificationIndex = -1;
                    loadNotificationCount();
                    closeNotificationDetail();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        async function markAllAsRead() {
            try {
                var response = await API.request('markAllNotificationsRead');
                if (response.success) {
                    Toast.show('Tüm bildirimler okundu olarak işaretlendi', 'success');
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        // ===== Hızlı İşlemler Özelleştirme Mantığı =====
        const ALL_ACTION_CATALOG = <?php echo json_encode(array_values($availableQuickActions), JSON_UNESCAPED_UNICODE); ?>;
        let currentActiveKeys = <?php echo json_encode(array_column($activeQuickActions, 'key'), JSON_UNESCAPED_UNICODE); ?>;
        const DEFAULT_KEYS = <?php echo json_encode(\App\Model\PersonelModel::DEFAULT_PWA_HIZLI_ISLEMLER, JSON_UNESCAPED_UNICODE); ?>;

        function openHizliIslemlerModal() {
            renderHizliIslemlerModalLists();
            Modal.open('hizli-islemler-modal');
        }

        function renderHizliIslemlerModalLists() {
            const activeContainer = document.getElementById('active-actions-list');
            const availableContainer = document.getElementById('available-actions-list');
            const activeBadge = document.getElementById('active-count-badge');

            if (!activeContainer || !availableContainer) return;

            activeContainer.innerHTML = '';
            availableContainer.innerHTML = '';

            const catalogMap = {};
            ALL_ACTION_CATALOG.forEach(function(act) { catalogMap[act.key] = act; });

            const validActiveKeys = currentActiveKeys.filter(function(k) { return catalogMap[k]; });
            currentActiveKeys = validActiveKeys;

            if (activeBadge) activeBadge.textContent = currentActiveKeys.length + ' aktif';

            if (currentActiveKeys.length === 0) {
                activeContainer.innerHTML = '<div class="text-center py-4 text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">Aktif hızlı işlem bulunmuyor. Aşağıdan ekleyebilirsiniz.</div>';
            } else {
                currentActiveKeys.forEach(function(key, index) {
                    const act = catalogMap[key];
                    const itemEl = document.createElement('div');
                    itemEl.className = 'quick-action-edit-item flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 shadow-sm transition-all select-none';
                    itemEl.setAttribute('draggable', 'true');
                    itemEl.setAttribute('data-key', key);
                    itemEl.setAttribute('data-index', index);

                    const isFirst = index === 0;
                    const isLast = index === currentActiveKeys.length - 1;

                    itemEl.innerHTML = `
                        <div class="flex items-center gap-3">
                            <span class="drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 material-symbols-outlined touch-none">drag_indicator</span>
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br ${act.gradient} flex items-center justify-center text-white shadow-sm">
                                <span class="material-symbols-outlined text-lg filled">${act.icon}</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">${escapeHtml(act.title)}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">${escapeHtml(act.desc)}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="moveHizliIslemItem(${index}, -1)" ${isFirst ? 'disabled' : ''} class="w-7 h-7 rounded-lg flex items-center justify-center ${isFirst ? 'text-slate-300 dark:text-slate-600' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'}">
                                <span class="material-symbols-outlined text-base">arrow_upward</span>
                            </button>
                            <button type="button" onclick="moveHizliIslemItem(${index}, 1)" ${isLast ? 'disabled' : ''} class="w-7 h-7 rounded-lg flex items-center justify-center ${isLast ? 'text-slate-300 dark:text-slate-600' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'}">
                                <span class="material-symbols-outlined text-base">arrow_downward</span>
                            </button>
                            <button type="button" onclick="removeHizliIslemItem('${key}')" class="w-7 h-7 rounded-lg flex items-center justify-center text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 ml-1" title="Çıkar">
                                <span class="material-symbols-outlined text-base">remove_circle_outline</span>
                            </button>
                        </div>
                    `;

                    setupDragAndDropEvents(itemEl, index);
                    activeContainer.appendChild(itemEl);
                });
            }

            const availableItems = ALL_ACTION_CATALOG.filter(function(act) { return !currentActiveKeys.includes(act.key); });

            if (availableItems.length === 0) {
                availableContainer.innerHTML = '<div class="text-center py-3 text-xs text-slate-400">Tüm kullanılabilir hızlı işlemler eklenmiş durumda.</div>';
            } else {
                availableItems.forEach(function(act) {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40 transition-all';
                    itemEl.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <span class="material-symbols-outlined text-lg">${act.icon}</span>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">${escapeHtml(act.title)}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">${escapeHtml(act.desc)}</div>
                            </div>
                        </div>
                        <button type="button" onclick="addHizliIslemItem('${act.key}')" class="px-2.5 py-1.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold flex items-center gap-1 transition-colors active:scale-95">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Ekle
                        </button>
                    `;
                    availableContainer.appendChild(itemEl);
                });
            }
        }

        function moveHizliIslemItem(index, direction) {
            const targetIndex = index + direction;
            if (targetIndex < 0 || targetIndex >= currentActiveKeys.length) return;
            const temp = currentActiveKeys[index];
            currentActiveKeys[index] = currentActiveKeys[targetIndex];
            currentActiveKeys[targetIndex] = temp;
            renderHizliIslemlerModalLists();
        }

        function addHizliIslemItem(key) {
            if (!currentActiveKeys.includes(key)) {
                currentActiveKeys.push(key);
                renderHizliIslemlerModalLists();
            }
        }

        function removeHizliIslemItem(key) {
            currentActiveKeys = currentActiveKeys.filter(function(k) { return k !== key; });
            renderHizliIslemlerModalLists();
        }

        function resetHizliIslemlerToDefault() {
            currentActiveKeys = Array.from(DEFAULT_KEYS);
            renderHizliIslemlerModalLists();
        }

        let draggedIndex = null;
        function setupDragAndDropEvents(el, index) {
            el.addEventListener('dragstart', function(e) {
                draggedIndex = index;
                el.classList.add('opacity-40', 'scale-95');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', index);
            });

            el.addEventListener('dragend', function() {
                draggedIndex = null;
                el.classList.remove('opacity-40', 'scale-95');
            });

            el.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            el.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedIndex === null || draggedIndex === index) return;
                const movedItem = currentActiveKeys.splice(draggedIndex, 1)[0];
                currentActiveKeys.splice(index, 0, movedItem);
                renderHizliIslemlerModalLists();
            });

            const handle = el.querySelector('.drag-handle');
            if (handle) {
                let startY = 0;
                let initialIndex = index;

                handle.addEventListener('touchstart', function(e) {
                    if (e.touches && e.touches[0]) {
                        startY = e.touches[0].clientY;
                        initialIndex = index;
                        el.classList.add('bg-primary/10', 'border-primary', 'shadow-md');
                    }
                }, { passive: true });

                handle.addEventListener('touchmove', function(e) {
                    if (!e.touches || !e.touches[0]) return;
                    const currentY = e.touches[0].clientY;
                    const diffY = currentY - startY;

                    if (Math.abs(diffY) > 36) {
                        const step = diffY > 0 ? 1 : -1;
                        const newIndex = initialIndex + step;
                        if (newIndex >= 0 && newIndex < currentActiveKeys.length) {
                            const temp = currentActiveKeys[initialIndex];
                            currentActiveKeys[initialIndex] = currentActiveKeys[newIndex];
                            currentActiveKeys[newIndex] = temp;
                            startY = currentY;
                            initialIndex = newIndex;
                            renderHizliIslemlerModalLists();
                        }
                    }
                }, { passive: true });

                handle.addEventListener('touchend', function() {
                    el.classList.remove('bg-primary/10', 'border-primary', 'shadow-md');
                });
            }
        }

        async function saveHizliIslemlerConfig() {
            const btn = document.getElementById('btn-save-hizli-islemler');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span> Kaydediliyor...';

            try {
                const formData = new FormData();
                formData.append('action', 'update_hizli_islemler');
                formData.append('hizli_islemler', JSON.stringify(currentActiveKeys));

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    const savedKeys = data.data && Array.isArray(data.data.hizli_islemler)
                        ? data.data.hizli_islemler
                        : null;
                    if (!savedKeys || JSON.stringify(savedKeys) !== JSON.stringify(currentActiveKeys)) {
                        throw new Error('Hızlı işlem sırası sunucuda doğrulanamadı.');
                    }
                    if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                        Toast.show(data.message || 'Hızlı işlemler güncellendi', 'success');
                    }
                    Modal.close('hizli-islemler-modal');
                    setTimeout(function() { window.location.reload(); }, 300);
                } else {
                    if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                        Toast.show(data.message || 'Bir hata oluştu', 'error');
                    } else {
                        alert(data.message || 'Bir hata oluştu');
                    }
                }
            } catch (err) {
                console.error('Save quick actions error:', err);
                if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                    Toast.show('Bir sunucu hatası oluştu', 'error');
                } else {
                    alert('Bir sunucu hatası oluştu');
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
