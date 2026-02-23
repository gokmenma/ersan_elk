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
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($personel->foto)): ?>
                            <img src="<?php echo Helper::base_url('uploads/personel/' . $personel->foto); ?>" alt="Profil"
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-2xl">person</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-white/80 text-sm">Hoş geldin,</p>
                        <h1 class="text-xl font-bold"><?php echo $personel->adi_soyadi ?? 'Personel'; ?></h1>
                    </div>
                </div>
                <button onclick="openNotificationModal()"
                    class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <span id="notification-badge"
                        class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border-2 border-primary hidden"></span>
                </button>
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
                    <div id="konum-izni-uyari"
                        class="hidden bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-3 mb-4">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-amber-600 text-lg">warning</span>
                            <div>
                                <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Konum İzni Gerekli</p>
                                <p class="text-xs text-amber-600 dark:text-amber-400">Göreve başlamak için konum izni
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

    <!-- Stats Cards -->
    <section class="px-4 relative z-20">
        <div class="grid grid-cols-1 gap-3">
            <!-- Toplam Hakediş -->
            <div class="card p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">account_balance_wallet</span>
                </div>
                <div class="flex-1">
                    <p class="text-slate-500 dark:text-slate-400 text-sm">Toplam Hakediş</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white" id="total-earning">0,00 ₺</p>
                </div>
                <div class="flex items-center gap-1">
                    <span class="badge badge-success">+%5.2</span>
                </div>
            </div>

            <!-- 2 Column Stats -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Alınan Ödeme -->
                <div class="card p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-primary text-xl">payments</span>
                        <p class="text-slate-500 dark:text-slate-400 text-xs">Alınan Ödeme</p>
                    </div>
                    <p class="text-lg font-bold text-slate-900 dark:text-white" id="received-payment">0,00 ₺</p>
                </div>

                <!-- Kalan Bakiye -->
                <div class="card p-4 bg-gradient-primary text-white stat-card">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-xl">savings</span>
                        <p class="text-white/80 text-xs">Kalan Bakiye</p>
                    </div>
                    <p class="text-lg font-bold" id="remaining-balance">0,00 ₺</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="px-4 mt-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-3">Hızlı İşlemler</h2>
        <div class="grid grid-cols-2 gap-3">
            <a href="?page=izin" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">event_busy</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">İzin Talebi</h3>
                    <p class="text-xs text-slate-500">Yeni izin planla</p>
                </div>
            </a>

            <a href="?page=talep" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">assignment</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Talep Oluştur</h3>
                    <p class="text-xs text-slate-500">Talep, öneri, şikayet</p>
                </div>
            </a>

            <a href="?page=bordro" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">receipt_long</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Avanslar</h3>
                    <p class="text-xs text-slate-500">Avans Talebi Yap</p>
                </div>
            </a>

            <a href="?page=profil" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">person_search</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Profilim</h3>
                    <p class="text-xs text-slate-500">Bilgileri güncelle</p>
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

        async function loadDashboardData() {
            try {
                var response = await API.request('getDashboardData');
                if (response.success) {
                    document.getElementById('total-earning').textContent = Format.currency(response.data.total_earning || 0);
                    document.getElementById('received-payment').textContent = Format.currency(response.data.received_payment || 0);
                    document.getElementById('remaining-balance').textContent = Format.currency(response.data.remaining_balance || 0);
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
                        var bgImg = duyuru.resim ? 'background-image: linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.3)), url(\'' + escapeHtml(duyuru.resim) + '\'); background-size: cover; background-position: center;'
                            : 'background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);';

                        var onClick = duyuru.hedef_sayfa ? 'window.location.href=\'' + escapeHtml(duyuru.hedef_sayfa) + '\';' : '';
                        var cursorClass = duyuru.hedef_sayfa ? 'cursor-pointer' : '';

                        var kalan_gun_html = '';
                        if (duyuru.kalan_gun !== null && duyuru.kalan_gun !== undefined) {
                            kalan_gun_html = '<div class="absolute top-4 right-4 bg-black/40 backdrop-blur-md border border-white/20 rounded-xl px-4 py-2 text-center shadow-2xl z-20 flex flex-col justify-center items-center shadow-[0_0_15px_rgba(255,255,255,0.2)]">' +
                                '<span class="block text-3xl font-black text-white leading-[1] tracking-tighter" style="text-shadow: 0 2px 4px rgba(0,0,0,0.5);">-' + escapeHtml(duyuru.kalan_gun) + '</span>' +
                                '<span class="block text-[9px] font-bold text-white/90 uppercase tracking-widest mt-1">GÜN KALDI</span>' +
                                '</div>';
                        }

                        return '<div class="snap-center shrink-0 w-[85%] sm:w-[300px] rounded-2xl p-4 text-white shadow-lg relative overflow-hidden transition-transform active:scale-[0.98] ' + cursorClass + '" ' +
                            'style="' + bgImg + '" onclick="' + onClick + '">' +
                            kalan_gun_html +
                            '<div class="relative z-10 pr-16">' + // padding for kalan_gun badge
                            '<span class="badge badge-primary bg-white/20 text-white border-none mb-2 text-[10px]">' + escapeHtml(duyuru.tarih) + '</span>' +
                            '<h3 class="font-bold text-lg leading-tight mb-1 line-clamp-1 text-white pr-4">' + escapeHtml(duyuru.baslik) + '</h3>' +
                            '<p class="text-xs text-white/80 line-clamp-2">' + escapeHtml(duyuru.icerik) + '</p>' +
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

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>