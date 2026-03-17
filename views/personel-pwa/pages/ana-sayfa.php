<?php
/**
 * Personel PWA - Ana Sayfa
 * Özet bilgiler ve hızlı işlemler
 */
use App\Helper\Helper;

?>

<div class="flex flex-col min-h-screen">
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
                        <div class="bg-gradient-to-r from-primary to-primary-dark text-white p-4 rounded-t-none">
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


    <section class="px-4 mt-6 overflow-hidden">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-3">Hızlı İşlemler</h2>
        <div class="flex overflow-x-auto hide-scrollbar gap-3 pb-6 snap-x snap-mandatory">
            <!-- <a href="?page=izin"
                class="quick-action group border-2 neon-indigo bg-gradient-to-br from-indigo-500 to-indigo-700 p-4 transition-all active:scale-95 w-[140px] flex-shrink-0 snap-start">
                <div
                    class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-2.5 shadow-inner">
                    <span class="material-symbols-outlined text-white text-xl filled">event_busy</span>
                </div>
                <div>
                    <h3 class="font-bold text-[13px] text-white">İzin Talebi</h3>
                    <p class="text-[9px] text-indigo-100/80 font-medium">Yeni izin planla</p>
                </div>
                <div
                    class="absolute -right-3 -bottom-3 opacity-10 group-hover:opacity-20 transition-opacity pointer-events-none">
                    <span class="material-symbols-outlined text-5xl text-white">event_busy</span>
                </div>
            </a>

            <a href="?page=talep"
                class="quick-action group border-2 neon-emerald bg-gradient-to-br from-emerald-500 to-emerald-700 p-4 transition-all active:scale-95 w-[140px] flex-shrink-0 snap-start">
                <div
                    class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-2.5 shadow-inner">
                    <span class="material-symbols-outlined text-white text-xl filled">assignment</span>
                </div>
                <div>
                    <h3 class="font-bold text-[13px] text-white">Talep Oluştur</h3>
                    <p class="text-[9px] text-emerald-100/80 font-medium">Öneri ve Şikayet</p>
                </div>
                <div
                    class="absolute -right-3 -bottom-3 opacity-10 group-hover:opacity-20 transition-opacity pointer-events-none">
                    <span class="material-symbols-outlined text-5xl text-white">assignment</span>
                </div>
            </a> -->

            <a href="?page=bordro"
                class="quick-action group border-2 neon-orange bg-gradient-to-br from-orange-500 to-orange-700 p-4 transition-all active:scale-95 w-[140px] flex-shrink-0 snap-start">
                <div
                    class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-2.5 shadow-inner">
                    <span class="material-symbols-outlined text-white text-xl filled">receipt_long</span>
                </div>
                <div>
                    <h3 class="font-bold text-[13px] text-white">Avanslar</h3>
                    <p class="text-[9px] text-orange-100/80 font-medium">Avans Talebi Yap</p>
                </div>
                <div
                    class="absolute -right-3 -bottom-3 opacity-10 group-hover:opacity-20 transition-opacity pointer-events-none">
                    <span class="material-symbols-outlined text-5xl text-white">receipt_long</span>
                </div>
            </a>

            <a href="?page=zimmetler"
                class="quick-action group border-2 neon-amber bg-gradient-to-br from-amber-500 to-amber-700 p-4 transition-all active:scale-95 w-[140px] flex-shrink-0 snap-start">
                <div
                    class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center mb-2.5 shadow-inner">
                    <span class="material-symbols-outlined text-white text-xl filled">inventory_2</span>
                </div>
                <div>
                    <h3 class="font-bold text-[13px] text-white">Zimmetler</h3>
                    <p class="text-[9px] text-amber-100/80 font-medium">Demirbaş Takibi</p>
                </div>
                <div
                    class="absolute -right-3 -bottom-3 opacity-10 group-hover:opacity-20 transition-opacity pointer-events-none">
                    <span class="material-symbols-outlined text-5xl text-white">inventory_2</span>
                </div>
            </a>
        </div>
    </section>

    <!-- Quick Actions Ends Here -->

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

    <!-- Notification Detail Modal -->
    <div id="notification-detail-modal" class="modal-overlay">
        <div class="modal-content p-6 pt-3">
            <div class="modal-handle"></div>

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <button onclick="closeNotificationDetail()"
                        class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                    </button>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bildirim Detayı</h3>
                </div>
                <button onclick="deleteCurrentNotification()"
                    class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"
                    title="Sil">
                    <span class="material-symbols-outlined text-red-600 text-lg">delete</span>
                </button>
            </div>

            <div id="notification-detail-content" class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                <!-- Detail content -->
            </div>

            <button onclick="closeNotificationDetail()"
                class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>

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
            // Uygulama açık olduğu sürece her 2 dakikada bir kontrol et
            checkKonumIstegi();
            setInterval(checkKonumIstegi, 120000);
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
                    Toast.show(response.message || 'Görev başlatılamadı', 'error');
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

        async function gorevBitir() {
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
                        if (dailyParts.length > 0) {
                            todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${dailyParts.join(' &bull; ')}</p>`;
                        }

                        let monthlyParts = [];
                        if (stat.details.monthly_sekme) {
                            if (stat.details.monthly_sekme.sokme_takma > 0) monthlyParts.push(`${stat.details.monthly_sekme.sokme_takma} Sayaç Değ.`);
                            if (stat.details.monthly_sekme.kesme > 0) monthlyParts.push(`${stat.details.monthly_sekme.kesme} Kesme-Açma`);
                        }
                        if (monthlyParts.length > 0) {
                            monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${monthlyParts.join(' &bull; ')}</p>`;
                        }
                    } else {
                        // Normal ekipler için (Endeks + Kesme)
                        if (stat.details && (stat.details.daily_isler > 0 || stat.details.daily_endeks > 0)) {
                            let parts = [];
                            if (stat.details.daily_isler > 0) parts.push(`${stat.details.daily_isler} Kesme`);
                            if (stat.details.daily_endeks > 0) parts.push(`${stat.details.daily_endeks} Endeks`);
                            if (parts.length > 0) {
                                todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }

                        if (stat.details && (stat.details.monthly_isler > 0 || stat.details.monthly_endeks > 0)) {
                            let parts = [];
                            if (stat.details.monthly_isler > 0) parts.push(`${stat.details.monthly_isler} Kesme`);
                            if (stat.details.monthly_endeks > 0) parts.push(`${stat.details.monthly_endeks} Endeks`);
                            if (parts.length > 0) {
                                monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }
                    }

                    let siralamaHtml = '';
                    if (stat.siralama) {
                        const isEndeksDept = (stat.departman || '').toLowerCase().includes('endeks okuma');

                        if (isEndeksDept) {
                            // Show both rankings for Endeks teams
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
                        } else {
                            // Only show Department ranking for other teams
                            siralamaHtml = `
                                <div class="col-span-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                                    <h4 class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Aylık Performans Sıralaması</h4>
                                    <div class="flex items-center gap-4 bg-white dark:bg-slate-800/80 rounded-2xl p-4 border border-slate-100 dark:border-slate-700 shadow-sm active:scale-[0.98] transition-all">
                                        <div class="relative shrink-0">
                                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex flex-col items-center justify-center shadow-lg shadow-indigo-500/30">
                                                <span class="text-[10px] text-white/70 font-bold leading-none mb-1">SIRA</span>
                                                <span class="text-xl font-black text-white leading-none">#${stat.siralama.departman_sira}</span>
                                            </div>
                                            <!-- Ring effect -->
                                            <div class="absolute -inset-1.5 rounded-2xl border-2 border-indigo-500/10 animate-pulse"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-1">DEPARTMAN SIRALAMASI</p>
                                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200 truncate mb-0.5" title="${stat.departman || 'Departman Bulunamadı'}">${stat.departman || 'Departman Bulunamadı'}</p>
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                                <p class="text-xs text-slate-400 font-medium">${stat.siralama.departman_kisi} Kişi Arasında</p>
                                            </div>
                                        </div>
                                        <div class="shrink-0 pr-2">
                                            <span class="material-symbols-outlined text-indigo-200 dark:text-indigo-900/50 text-4xl">workspace_premium</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
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

                        var duyuruJson = JSON.stringify(duyuru).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
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
                            '<p class="text-xs text-white/80 line-clamp-2 max-w-[85%]">' + escapeHtml(duyuru.icerik) + '</p>' +
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

                        // Resim varsa küçük thumbnail göster
                        var thumbnailHtml = notification.image
                            ? '<img src="' + escapeHtml(notification.image) + '" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" onerror="this.style.display=\'none\'">'
                            : '';

                        return '<div class="relative flex items-start gap-3 p-3 ' + bgClass + ' rounded-xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" onclick="showNotificationDetail(' + index + ')">' +
                            unreadIndicator +
                            '<div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">' +
                            '<span class="material-symbols-outlined text-blue-600 text-lg">notifications</span>' +
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

            // Bildirimi okundu olarak işaretle
            if (!notification.okundu) {
                await API.request('markNotificationRead', { notification_id: notification.id });
                allNotificationsData[index].okundu = true;
                loadNotificationCount(); // Badge'i güncelle
            }

            var container = document.getElementById('notification-detail-content');

            // Resim HTML'i oluştur
            var imageHtml = '';
            if (notification.image) {
                imageHtml = '<div class="mt-4 rounded-xl overflow-hidden">' +
                    '<img src="' + escapeHtml(notification.image) + '" alt="Bildirim resmi" class="w-full h-auto object-cover" onerror="this.parentElement.style.display=\'none\'">' +
                    '</div>';
            }

            container.innerHTML =
                '<div class="flex items-center gap-3 mb-4">' +
                '<div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">' +
                '<span class="material-symbols-outlined text-blue-600 text-2xl">notifications</span>' +
                '</div>' +
                '<div>' +
                '<p class="text-xs text-primary font-medium">' + escapeHtml(notification.time_ago) + '</p>' +
                '</div>' +
                '</div>' +
                '<h4 class="text-lg font-bold text-slate-900 dark:text-white mb-3">' + escapeHtml(notification.title) + '</h4>' +
                '<p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">' + escapeHtml(notification.body) + '</p>' +
                imageHtml;

            Modal.close('notification-modal');
            setTimeout(function () {
                Modal.open('notification-detail-modal');
            }, 200);
        }

        function closeNotificationDetail() {
            Modal.close('notification-detail-modal');
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

        async function deleteAllNotifications() {
            var confirmed = await Alert.confirm('Tüm Bildirimleri Sil', 'Tüm bildirimleri silmek istediğinize emin misiniz?', 'Evet, Tümünü Sil', 'Vazgeç');
            if (!confirmed) return;

            try {
                var response = await API.request('deleteAllNotifications');
                if (response.success) {
                    Toast.show('Tüm bildirimler silindi', 'success');
                    allNotificationsData = [];
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }
    </script>