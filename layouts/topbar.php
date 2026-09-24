<?php
use App\Helper\Helper;
use App\Helper\Route;
use App\Helper\Form;
use App\Model\FirmaModel;
use App\Service\RequestPerformanceProfiler;

$currentUserId = (int) ($_SESSION['user_id'] ?? ($_SESSION['user']->id ?? 0));
$cacheTtl = 300;
$firma_option = [];

if ($currentUserId > 0) {
    $sessionCache = $_SESSION['topbar_firma_option_cache'][$currentUserId] ?? null;
    $hasValidSessionCache = is_array($sessionCache)
        && !empty($sessionCache['expires_at'])
        && ($sessionCache['expires_at'] > time())
        && is_array($sessionCache['data'] ?? null);

    if ($hasValidSessionCache) {
        $firma_option = $sessionCache['data'];
    } else {
        $FirmaModel = new FirmaModel();
        $firma_option = RequestPerformanceProfiler::measure(
            'topbar.firma_option',
            fn() => $FirmaModel->optionByUserPermission(),
            1
        );

        $_SESSION['topbar_firma_option_cache'][$currentUserId] = [
            'expires_at' => time() + $cacheTtl,
            'data' => $firma_option
        ];
    }
}

if (!is_array($firma_option)) {
    $firma_option = [];
}

//Helper::dd($firma_option);


?>
<?php
$currentPageKey = $_GET['p'] ?? 'home';
$topbarMenuModel = new \App\Model\MenuModel();
$currentMenuObj = $topbarMenuModel->getMenuByLink($currentPageKey);
$topbarTitle = $currentMenuObj->menu_name ?? ($title ?? 'Ana Sayfa');
$topbarDesc = $currentMenuObj->page_description ?? '';
?>
<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex align-items-center">
            <!-- LOGO -->
            <div class="navbar-brand-box d-lg-none">
                <a href="index.php" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="<?php echo Helper::base_url("assets/images/logo.png") ?>" alt="" height="30">
                    </span>
                    <span class="logo-lg">
                        <img src="<?php echo Helper::base_url("assets/images/logo.png") ?>" alt="" height="24"> <span
                            class="logo-txt"></span>
                    </span>
                </a>

                <a href="index.php" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="<?php echo Helper::base_url("assets/images/logo.png") ?>" alt="" height="30">
                    </span>
                    <span class="logo-lg">
                        <img src="<?php echo Helper::base_url("assets/images/logo.png") ?>" alt="" height="36"><span
                            class="logo-txt">
                        </span>
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm header-item" id="vertical-menu-btn">
                <i data-feather="menu" class="icon-lg"></i>
            </button>

            <!-- Topbar Sayfa Başlığı ve Açıklaması -->
            <div class="topbar-page-header d-none d-md-flex flex-column justify-content-center">
                <h5 class="topbar-page-title m-0" id="topbar-page-title">
                    <?php echo htmlspecialchars($topbarTitle); ?>
                </h5>
                <span class="topbar-page-desc mt-1" id="topbar-page-desc" <?php echo empty($topbarDesc) ? 'style="display: none;"' : ''; ?>>
                    <?php echo htmlspecialchars($topbarDesc); ?>
                </span>
            </div>
        </div>

        <?php
        $isSuperAdmin = \App\Service\Gate::isSuperAdmin();
        $hasPersonelPerm = $isSuperAdmin || \App\Service\Gate::allows('Personel Listesi') || \App\Service\Gate::allows('Personeller') || \App\Service\Gate::allows('personel_listesi');
        $hasAracPerm = $isSuperAdmin || \App\Service\Gate::allows('Araç Takip') || \App\Service\Gate::allows('Araç Takip/Yönetim');
        $hasDemirbasPerm = $isSuperAdmin || \App\Service\Gate::allows('Demirbaş Yönetimi') || \App\Service\Gate::allows('Demirbaş/Zimmet İşlemleri Sayfası');
        $hasCariPerm = $isSuperAdmin || \App\Service\Gate::allows('Cari Takibi') || \App\Service\Gate::allows('Cari Hesap Hareketleri');
        $hasEvrakPerm = $isSuperAdmin || \App\Service\Gate::allows('Evrak Takip') || \App\Service\Gate::allows('Evrak Bilgileri Sekmesi');
        $hasGorevPerm = $isSuperAdmin || \App\Service\Gate::allows('Görevler') || \App\Service\Gate::allows('Görev ve Bildirimler');
        $hasKacakPerm = $isSuperAdmin || \App\Service\Gate::allows('Kaçak İşlemleri') || \App\Service\Gate::allows('Kaçak Bildirim Onayı');
        $hasAparatPerm = $isSuperAdmin || \App\Service\Gate::allows('Aparat Takip') || \App\Service\Gate::allows('Aparat Deposu') || \App\Service\Gate::allows('Aparat Tanımları');

        $allowedSearchModules = [];
        if ($hasPersonelPerm) $allowedSearchModules[] = 'personel';
        if ($hasAracPerm) $allowedSearchModules[] = 'araclar';
        if ($hasDemirbasPerm) $allowedSearchModules[] = 'demirbaslar';
        if ($hasCariPerm) $allowedSearchModules[] = 'cariler';
        if ($hasEvrakPerm) $allowedSearchModules[] = 'evraklar';
        if ($hasGorevPerm) $allowedSearchModules[] = 'gorevler';
        if ($hasKacakPerm) $allowedSearchModules[] = 'kacak';
        if ($hasAparatPerm) $allowedSearchModules[] = 'aparatlar';

        $hasAnySearchPerm = !empty($allowedSearchModules);
        ?>

        <!-- Global Arama Kutusu (Geniş Ekranlarda Topbarın Tam Ortasında) -->
        <?php if ($hasAnySearchPerm): ?>
        <div class="topbar-global-search-wrapper d-none d-xl-block">
            <div class="global-search-container" id="global-search-wrapper">
                <div class="global-search-input-box">
                    <i class="bx bx-search-alt-2 global-search-icon"></i>
                    <input type="text" 
                           class="global-search-input" 
                           id="global-search-input" 
                           placeholder="Personel, araç, demirbaş, cari, evrak veya görev ara..." 
                           autocomplete="off" 
                           spellcheck="false">
                    <button type="button" class="global-search-clear-btn" id="global-search-clear" title="Temizle" style="display: none;">
                        <i class="bx bx-x"></i>
                    </button>
                    <div class="global-search-kbd-badge" title="Kısayol: Ctrl + K">
                        <kbd>ctrl</kbd><kbd>k</kbd>
                    </div>
                    <div class="global-search-spinner" id="global-search-spinner" style="display: none;">
                        <i class="bx bx-loader-alt bx-spin"></i>
                    </div>
                </div>

                <!-- Arama Sonuç Dropdown Kartı -->
                <div class="global-search-dropdown shadow-lg" id="global-search-dropdown" style="display: none;">
                    <!-- Kategori Filtreleme Sekmeleri -->
                    <div class="global-search-categories" id="global-search-categories">
                        <button type="button" class="gs-cat-pill active" data-cat="all">
                            <i class="bx bx-grid-alt"></i> Tümü <span class="gs-count" id="count-all">0</span>
                        </button>
                        <?php if ($hasPersonelPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="personel">
                            <i class="bx bx-user"></i> Personeller <span class="gs-count" id="count-personel">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasAracPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="araclar">
                            <i class="bx bx-car"></i> Araçlar <span class="gs-count" id="count-araclar">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasDemirbasPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="demirbaslar">
                            <i class="bx bx-cube"></i> Demirbaşlar <span class="gs-count" id="count-demirbaslar">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasCariPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="cariler">
                            <i class="bx bx-buildings"></i> Cariler <span class="gs-count" id="count-cariler">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasEvrakPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="evraklar">
                            <i class="bx bx-file"></i> Evraklar <span class="gs-count" id="count-evraklar">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasGorevPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="gorevler">
                            <i class="bx bx-check-square"></i> Görevler <span class="gs-count" id="count-gorevler">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasKacakPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="kacak">
                            <i class="bx bx-shield-quarter"></i> Kaçak / Saha <span class="gs-count" id="count-kacak">0</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($hasAparatPerm): ?>
                        <button type="button" class="gs-cat-pill" data-cat="aparatlar">
                            <i class="bx bx-wrench"></i> Aparatlar <span class="gs-count" id="count-aparatlar">0</span>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Sonuç İçerik Alanı -->
                    <div class="global-search-results" id="global-search-results">
                        <!-- JS dinamik render edecek -->
                    </div>

                    <!-- Alt Bilgi / Kısayol İpuçları Çubuğu -->
                    <div class="global-search-footer">
                        <div class="gs-footer-info" id="gs-footer-info">
                            Toplam <span id="gs-total-count">0</span> sonuç bulundu
                        </div>
                        <div class="gs-footer-hints">
                            <span class="gs-hint-item"><kbd>↑</kbd><kbd>↓</kbd> Gezin</span>
                            <span class="gs-hint-item"><kbd>↵</kbd> Seç</span>
                            <span class="gs-hint-item"><kbd>Esc</kbd> Kapat</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex align-items-center">

            <!-- Küçük/Orta Ekranlarda (1200px altı) Arama İkon Butonu -->
            <?php if ($hasAnySearchPerm): ?>
            <div class="dropdown d-inline-block d-xl-none ms-1">
                <button type="button" class="btn header-item noti-icon position-relative" id="page-header-search-dropdown" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false" title="Ara (Ctrl+K)">
                    <i data-feather="search" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0 shadow-lg border-0"
                    aria-labelledby="page-header-search-dropdown" style="min-width: 320px; width: 88vw; max-width: 440px; border-radius: 14px; overflow: hidden;">
                    <div class="p-3 border-bottom bg-light">
                        <div class="position-relative">
                            <input type="text" class="form-control rounded-pill ps-4 pe-4 font-size-13" id="global-search-mobile-input" 
                                   placeholder="Personel, araç, demirbaş, cari veya evrak ara..." autocomplete="off">
                            <i class="bx bx-search-alt position-absolute top-50 start-0 translate-middle-y ms-2 text-muted font-size-16"></i>
                            <span class="position-absolute top-50 end-0 translate-middle-y me-2" id="global-search-mobile-spinner" style="display: none;">
                                <i class="bx bx-loader-alt bx-spin text-primary font-size-16"></i>
                            </span>
                        </div>
                    </div>
                    <div class="global-search-results-list" id="global-search-mobile-results" style="max-height: 380px; overflow-y: auto;">
                        <div class="p-4 text-center text-muted font-size-12">
                            <i class="bx bx-search-alt font-size-24 d-block mb-1 text-muted opacity-50"></i>
                            Personel, araç, demirbaş, cari veya evrak aramak için yazmaya başlayın.
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="dropdown d-inline-block language-switch">


            </div>

            <div class="dropdown d-none d-sm-inline-block">
                <button type="button" class="btn header-item" id="mode-setting-btn">
                    <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
                    <i data-feather="sun" class="icon-lg layout-mode-light"></i>
                </button>
            </div>

            <!-- Destek Talepleri -->
            <div class="dropdown d-inline-block">
                <?php $supportUrl = \App\Service\Gate::allows('admin_destek_talebi') ? 'index.php?p=yardim/list' : 'index.php?p=yardim/user-list'; ?>
                <button type="button" class="btn header-item noti-icon position-relative"
                    id="page-header-support-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false" title="Destek Talepleri">
                    <i data-feather="help-circle" class="icon-lg"></i>
                    <span class="badge bg-danger rounded-pill" id="support-notification-badge" style="display: none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-support-dropdown" style="max-height: 500px; overflow: hidden;">
                    <div class="p-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0"> Destek Bildirimleri</h6>
                            </div>
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 350px; overflow-y: auto !important;"
                        id="topbar-support-notification-list">
                        <!-- Destek bildirimleri buraya yüklenecek -->
                        <div class="text-center p-3 text-muted">Bildirim yok</div>
                    </div>
                    <div class="p-2 border-top d-grid">
                        <a class="btn btn-sm btn-link font-size-14 text-center" href="<?php echo $supportUrl; ?>">
                            <i class="mdi mdi-arrow-right-circle me-1"></i>
                            <span>Tüm Destek Talepleri</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    <i data-feather="grid" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <div class="p-2">
                        <div class="row g-0">
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/github.png" alt="Github">
                                    <span><?php echo $language['GitHub'] ?></span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/bitbucket.png" alt="bitbucket">
                                    <span><?php echo $language['Bitbucket'] ?></span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/dribbble.png" alt="dribbble">
                                    <span><?php echo $language['Dribbble'] ?></span>
                                </a>
                            </div>
                        </div>

                        <div class="row g-0">
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/dropbox.png" alt="dropbox">
                                    <span><?php echo $language['Dropbox'] ?></span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/mail_chimp.png" alt="mail_chimp">
                                    <span><?php echo $language['Mail Chimp'] ?></span>
                                </a>
                            </div>
                            <div class="col">
                                <a class="dropdown-icon-item" href="#">
                                    <img src="assets/images/brands/slack.png" alt="slack">
                                    <span><?php echo $language['Slack'] ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon position-relative"
                    id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                    aria-expanded="false">
                    <i data-feather="bell" class="icon-lg"></i>
                    <span class="badge bg-danger rounded-pill" id="notification-badge" style="display: none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-notifications-dropdown" style="max-height: 500px; overflow: hidden;">
                    <div class="p-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0"> Bildirimler</h6>
                            </div>
                            <div class="col-auto">
                                <a href="javascript:void(0);" id="mark-all-read"
                                    class="small text-reset text-decoration-underline">
                                    Tümünü Okundu İşaretle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div data-simplebar style="max-height: 350px; overflow-y: auto !important;"
                        id="topbar-notification-list">
                        <!-- Notifications will be loaded here -->
                    </div>
                    <div class="p-2 border-top d-grid">
                        <a class="btn btn-sm btn-link font-size-14 text-center" href="index.php?p=bildirim/list">
                            <i class="mdi mdi-arrow-right-circle me-1"></i>
                            <span>Tümünü Gör</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item right-bar-toggle me-2">
                    <i data-feather="settings" class="icon-lg"></i>
                </button>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <img class="rounded-circle header-profile-user user-profile-image"
                        src="<?php echo Helper::base_url('assets/images/users/avatar.png'); ?>" alt="Header Avatar"
                        id="user_image">
                    <span class="d-none d-xl-inline-block ms-1 fw-medium setting_user_name"
                        id="setting_user_name">
                        <?php 
                        $displayName = $_SESSION['user_full_name'] ?? '';
                        if (empty($displayName) && isset($_SESSION['user'])) {
                            $displayName = $_SESSION['user']->adi_soyadi ?? 'Yönetici';
                        }
                        echo htmlspecialchars($displayName ?: 'Yönetici');
                        ?>
                    </span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <!-- item-->
                    <a class="dropdown-item" href="index.php?p=profil/index"><i
                            class="mdi mdi-face-profile font-size-16 align-middle me-1"></i><?php echo $_SESSION["user"]->adi_soyadi; ?></a>
                    <a class="dropdown-item" href="auth-lock-screen.php"><i
                            class="mdi mdi-lock font-size-16 align-middle me-1"></i> Kilitle</a>
                    <div class="dropdown-divider"></div>
                    <?php if (count($firma_option) >= 2): ?>
                    <a class="dropdown-item" href="firma-degistir.php"><i
                            class="mdi mdi-swap-horizontal font-size-16 align-middle me-1"></i> Firma Değiştir</a>
                    <?php endif; ?>
                    <a class="dropdown-item" href="logout.php"><i
                            class="mdi mdi-logout font-size-16 align-middle me-1"></i> Çıkış Yap</a>
                </div>
            </div>

        </div>
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let lastNotificationId = 0;
        let isFirstLoad = true;
        let pollingInterval = null;
        const POLLING_INTERVAL = 15000; // 15 saniye

        /**
         * Badge'ları güncelle
         */
        function updateBadge(count, supportCount) {
            // Normal bildirimler
            $('#notification-badge').text(count);
            if (count > 0) {
                $('#notification-badge').show();
            } else {
                $('#notification-badge').hide();
            }

            // Destek bildirimleri
            $('#support-notification-badge').text(supportCount);
            if (supportCount > 0) {
                $('#support-notification-badge').show();
            } else {
                $('#support-notification-badge').hide();
            }
        }

        /**
         * Bildirim listesini güncelle
         */
        function updateNotificationList(notifications, supportNotifications) {
            // Normal Bildirimler
            let html = '';
            if (!notifications || notifications.length === 0) {
                html = '<div class="text-center p-3 text-muted">Bildirim yok</div>';
            } else {
                notifications.forEach(function(n) {
                    html += generateNotificationItemHtml(n);
                });
            }
            $('#topbar-notification-list').html(html);

            // Destek Bildirimleri
            let supportHtml = '';
            if (!supportNotifications || supportNotifications.length === 0) {
                supportHtml = '<div class="text-center p-3 text-muted">Bildirim yok</div>';
            } else {
                supportNotifications.forEach(function(n) {
                    supportHtml += generateNotificationItemHtml(n);
                });
            }
            $('#topbar-support-notification-list').html(supportHtml);
        }

        /**
         * Tekli bildirim HTML üretici
         */
        function generateNotificationItemHtml(n) {
            let iconClass = n.icon || 'bell';

            // İkon mapping - eski/hatalı ikon adlarını düzelt
            const iconMap = {
                'lira-sign': 'bx-money',
                'calendar': 'bx-calendar',
                'message-square': 'bx-message-square-detail',
                'bell': 'bx-bell'
            };

            if (iconMap[iconClass]) {
                iconClass = iconMap[iconClass];
            } else if (!iconClass.startsWith('bx-') && !iconClass.startsWith('mdi-')) {
                iconClass = 'bx-' + iconClass;
            }

            return `
            <a href="${n.link}" class="text-reset notification-item" onclick="markAsRead(${n.id})">
                <div class="d-flex">
                    <div class="flex-shrink-0 avatar-sm me-3">
                        <span class="avatar-title bg-${n.color || 'primary'} rounded-circle font-size-16">
                            <i class="bx ${iconClass}"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${n.title}</h6>
                        <div class="font-size-13 text-muted">
                            <p class="mb-1">${n.message}</p>
                            <p class="mb-0"><i class="mdi mdi-clock-outline"></i> <span>${n.time_ago}</span></p>
                        </div>
                    </div>
                </div>
            </a>
            `;
        }

        /**
         * Toast bildirimi göster
         */
        function showNotificationToast(n) {
            if (typeof Toastify !== 'undefined') {
                const safeMsg = `<strong>${n.title}</strong><br>${n.message}`;
                Toastify({
                    text: typeof safeMsg === 'string' ? safeMsg : String(safeMsg || ''),
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    style: { background: n.color === 'danger' ? "#f46a6a" : (n.color === 'warning' ? "#f1b44c" : "#34c38f") },
                    escapeMarkup: false,
                    onClick: function () {
                        window.location.href = n.link;
                    }
                }).showToast();
            }
        }

        /**
         * Bildirimleri getir
         */
        function fetchNotifications() {
            $.post('views/bildirim/api.php', { action: 'get-unread' }, function (response) {
                if (response.status === 'success') {
                    updateBadge(response.count, response.support_count);

                    let maxId = 0;
                    
                    // Tüm bildirimleri tara
                    const allNotifications = (response.notifications || []).concat(response.support_notifications || []);
                    
                    if (allNotifications.length > 0) {
                        allNotifications.forEach(function (n) {
                            if (n.id > maxId) maxId = n.id;

                            // İlk yüklemede toast gösterme, sadece yeni bildirimler için
                            if (!isFirstLoad && n.id > lastNotificationId) {
                                showNotificationToast(n);
                            }
                        });
                    }

                    updateNotificationList(response.notifications, response.support_notifications);

                    if (maxId > lastNotificationId) {
                        lastNotificationId = maxId;
                    }
                    isFirstLoad = false;
                }
            }, 'json').fail(function () {
                console.log('Bildirim kontrolü başarısız oldu');
            });
        }

        /**
         * Polling'i başlat
         */
        function startPolling() {
            if (pollingInterval) return;
            fetchNotifications();
            pollingInterval = setInterval(fetchNotifications, POLLING_INTERVAL);
        }

        /**
         * Polling'i durdur
         */
        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Sayfa yüklendiğinde polling'i başlat
        startPolling();

        // Visibility API
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                isFirstLoad = true;
                startPolling();
            }
        });

        window.addEventListener('beforeunload', function () {
            stopPolling();
        });

        window.markAsRead = function (id) {
            $.post('views/bildirim/api.php', { action: 'mark-read', id: id });
        };

        $('#mark-all-read').click(function () {
            $.post('views/bildirim/api.php', { action: 'mark-all-read' }, function (response) {
                if (response.status === 'success') {
                    updateBadge(0);
                    updateNotificationList([]);
                }
            }, 'json');
        });

    });
</script>

<script>
    window.GLOBAL_SEARCH_ALLOWED_MODULES = <?php echo json_encode($allowedSearchModules ?? []); ?>;
</script>
<script src="<?php echo Helper::assetVersion('assets/js/global-search.js'); ?>"></script>

<style>
/* ==============================================================
   GLOBAL SEARCH COMPONENT STYLES
   ============================================================== */
.navbar-header {
    position: relative;
}

.topbar-global-search-wrapper {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 480px);
    max-width: 580px;
    z-index: 1000;
}

@media (min-width: 1500px) {
    .topbar-global-search-wrapper {
        max-width: 660px;
        width: calc(100% - 520px);
    }
}

@media (min-width: 1200px) and (max-width: 1499px) {
    .topbar-global-search-wrapper {
        max-width: 480px;
        width: calc(100% - 460px);
    }
}

.global-search-container {
    position: relative;
    width: 100%;
}

.global-search-input-box {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0 12px;
    height: 40px;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
}

.global-search-input-box:focus-within {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15), 0 2px 4px rgba(0, 0, 0, 0.05);
}

.global-search-icon {
    color: #94a3b8;
    font-size: 18px;
    margin-right: 8px;
    flex-shrink: 0;
    transition: color 0.2s ease;
}

.global-search-input-box:focus-within .global-search-icon {
    color: #3b82f6;
}

.global-search-input {
    width: 100%;
    border: none;
    background: transparent;
    font-size: 13.5px;
    color: #1e293b;
    outline: none;
    padding: 0;
    font-weight: 500;
}

.global-search-input::placeholder {
    color: #94a3b8;
    font-weight: 400;
}

.global-search-clear-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 2px 6px;
    margin-right: 6px;
    font-size: 16px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.global-search-clear-btn:hover {
    color: #ef4444;
    background: #fee2e2;
}

.global-search-kbd-badge {
    display: flex;
    align-items: center;
    gap: 3px;
    user-select: none;
    pointer-events: none;
}

.global-search-kbd-badge kbd {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #64748b;
    font-size: 10.5px;
    font-family: inherit;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 5px;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.06);
    line-height: 1;
    text-transform: lowercase;
}

@media (max-width: 767px) {
    .global-search-kbd-badge {
        display: none;
    }
}

.global-search-spinner {
    color: #3b82f6;
    font-size: 18px;
    margin-left: 6px;
    flex-shrink: 0;
}

/* ==============================================================
   GLOBAL SEARCH DROPDOWN CARD
   ============================================================== */
.global-search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(-6px);
    width: 660px;
    max-width: 92vw;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1060;
    overflow: hidden;
}

.global-search-dropdown.show {
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
    transform: translateX(-50%) translateY(0);
}

/* Category Filter Pills Bar */
.global-search-categories {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    white-space: nowrap;
    cursor: grab;
    user-select: none;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.global-search-categories.is-dragging {
    cursor: grabbing !important;
}

.global-search-categories::-webkit-scrollbar {
    height: 4px;
}

.global-search-categories::-webkit-scrollbar-track {
    background: transparent;
}

.global-search-categories::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.global-search-categories::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.gs-cat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    user-select: none;
    transition: all 0.15s ease;
}

.gs-cat-pill:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #cbd5e1;
}

.gs-cat-pill.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
    box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
}

.gs-cat-pill .gs-count {
    background: rgba(0, 0, 0, 0.07);
    color: inherit;
    font-size: 10.5px;
    padding: 1px 6px;
    border-radius: 10px;
}

.gs-cat-pill.active .gs-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Results Content Area */
.global-search-results {
    max-height: 420px;
    overflow-y: auto;
    padding: 8px;
}

.global-search-results::-webkit-scrollbar {
    width: 6px;
}

.global-search-results::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Category Groups */
.gs-category-group {
    margin-bottom: 10px;
}

.gs-category-group:last-child {
    margin-bottom: 0;
}

.gs-category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #94a3b8;
    text-transform: uppercase;
}

.gs-category-header .gs-cat-title i {
    margin-right: 5px;
    font-size: 14px;
}

.gs-cat-count-badge {
    background: #e2e8f0;
    color: #64748b;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 8px;
}

/* Result Item */
.gs-result-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 12px;
    border-radius: 10px;
    text-decoration: none !important;
    transition: all 0.15s ease;
    cursor: pointer;
    position: relative;
    border: 1px solid transparent;
}

.gs-result-item:hover,
.gs-result-item.active {
    background: #f1f5f9;
    border-color: #e2e8f0;
}

/* Avatar / Initial Badge */
.gs-item-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
    position: relative;
}

.gs-avatar-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    border: 2px solid #ffffff;
}

/* Badge Color Themes */
.gs-avatar-purple { background: #f3e8ff; color: #7e22ce; }
.gs-avatar-purple .gs-avatar-dot { background: #9333ea; }

.gs-avatar-amber { background: #fef3c7; color: #b45309; }
.gs-avatar-amber .gs-avatar-dot { background: #f59e0b; }

.gs-avatar-blue { background: #dbeafe; color: #1d4ed8; }
.gs-avatar-blue .gs-avatar-dot { background: #3b82f6; }

.gs-avatar-emerald { background: #d1fae5; color: #047857; }
.gs-avatar-emerald .gs-avatar-dot { background: #10b981; }

.gs-avatar-cyan { background: #cffafe; color: #0e7490; }
.gs-avatar-cyan .gs-avatar-dot { background: #06b6d4; }

.gs-avatar-indigo { background: #e0e7ff; color: #4338ca; }
.gs-avatar-indigo .gs-avatar-dot { background: #6366f1; }

/* Item Content */
.gs-item-content {
    flex: 1;
    min-width: 0;
}

.gs-item-row-primary {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
}

.gs-item-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gs-item-extra {
    font-size: 11.5px;
    font-weight: 600;
    color: #059669;
    background: #ecfdf5;
    padding: 1px 6px;
    border-radius: 4px;
    white-space: nowrap;
}

.gs-item-date {
    font-size: 11px;
    color: #94a3b8;
    margin-left: auto;
    white-space: nowrap;
}

.gs-item-row-secondary {
    display: flex;
    align-items: center;
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.gs-item-subtitle {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Highlight Mark */
.gs-highlight {
    background: #fef08a;
    color: #854d0e;
    font-weight: 700;
    padding: 0 2px;
    border-radius: 2px;
}

/* Right Status & Arrow */
.gs-item-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.gs-status-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
    white-space: nowrap;
}

.gs-status-pill.badge-success { background: #dcfce7; color: #15803d; }
.gs-status-pill.badge-warning { background: #fef3c7; color: #b45309; }
.gs-status-pill.badge-danger { background: #fee2e2; color: #b91c1c; }
.gs-status-pill.badge-primary { background: #dbeafe; color: #1d4ed8; }
.gs-status-pill.badge-secondary { background: #f1f5f9; color: #475569; }

.gs-item-arrow {
    font-size: 16px;
    color: #94a3b8;
    transition: transform 0.15s ease;
}

.gs-result-item:hover .gs-item-arrow,
.gs-result-item.active .gs-item-arrow {
    transform: translateX(2px);
    color: #2563eb;
}

/* Suggestions & Empty States */
.gs-suggestions-wrap {
    padding: 12px 10px;
}

.gs-suggestions-header {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 0.05em;
    margin-bottom: 10px;
}

.gs-suggestions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

@media (max-width: 575px) {
    .gs-suggestions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.gs-suggestion-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-decoration: none !important;
    color: #334155;
    font-size: 12.5px;
    font-weight: 600;
    transition: all 0.15s ease;
}

.gs-suggestion-card i {
    font-size: 18px;
}

.gs-suggestion-card:hover {
    background: #ffffff;
    border-color: #3b82f6;
    color: #2563eb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.gs-empty-state {
    text-align: center;
    padding: 32px 16px;
}

.gs-empty-icon {
    font-size: 32px;
    color: #cbd5e1;
    margin-bottom: 8px;
}

.gs-empty-title {
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 4px;
}

.gs-empty-subtitle {
    font-size: 12px;
    color: #94a3b8;
}

/* Footer Bar */
.global-search-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    font-size: 11.5px;
    color: #64748b;
}

.gs-footer-hints {
    display: flex;
    align-items: center;
    gap: 10px;
}

.gs-hint-item {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.gs-hint-item kbd {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    font-size: 10px;
    padding: 1px 4px;
    border-radius: 3px;
    font-weight: 600;
}

/* ==============================================================
   DARK MODE ADAPTATIONS FOR GLOBAL SEARCH
   ============================================================== */
[data-bs-theme="dark"] .global-search-input-box,
body[data-topbar="dark"] .global-search-input-box {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

[data-bs-theme="dark"] .global-search-input-box:focus-within,
body[data-topbar="dark"] .global-search-input-box:focus-within {
    background: #0f172a;
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}

[data-bs-theme="dark"] .global-search-input,
body[data-topbar="dark"] .global-search-input {
    color: #f8fafc;
}

[data-bs-theme="dark"] .global-search-input::placeholder,
body[data-topbar="dark"] .global-search-input::placeholder {
    color: #64748b;
}

[data-bs-theme="dark"] .global-search-kbd-badge kbd,
body[data-topbar="dark"] .global-search-kbd-badge kbd {
    background: #0f172a;
    border-color: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .global-search-dropdown,
body[data-topbar="dark"] .global-search-dropdown {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
}

[data-bs-theme="dark"] .global-search-categories,
body[data-topbar="dark"] .global-search-categories {
    background: #0f172a;
    border-bottom-color: #334155;
    scrollbar-color: #475569 transparent;
}

[data-bs-theme="dark"] .global-search-categories::-webkit-scrollbar-thumb,
body[data-topbar="dark"] .global-search-categories::-webkit-scrollbar-thumb {
    background: #475569;
}

[data-bs-theme="dark"] .gs-cat-pill,
body[data-topbar="dark"] .gs-cat-pill {
    background: #1e293b;
    border-color: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .gs-cat-pill:hover,
body[data-topbar="dark"] .gs-cat-pill:hover {
    background: #334155;
    color: #f8fafc;
}

[data-bs-theme="dark"] .gs-cat-pill.active,
body[data-topbar="dark"] .gs-cat-pill.active {
    background: #3b82f6;
    color: #ffffff;
    border-color: #3b82f6;
}

[data-bs-theme="dark"] .gs-result-item:hover,
[data-bs-theme="dark"] .gs-result-item.active,
body[data-topbar="dark"] .gs-result-item:hover,
body[data-topbar="dark"] .gs-result-item.active {
    background: #334155;
    border-color: #475569;
}

[data-bs-theme="dark"] .gs-item-title,
body[data-topbar="dark"] .gs-item-title {
    color: #f8fafc;
}

[data-bs-theme="dark"] .gs-item-row-secondary,
body[data-topbar="dark"] .gs-item-row-secondary {
    color: #94a3b8;
}

[data-bs-theme="dark"] .gs-highlight,
body[data-topbar="dark"] .gs-highlight {
    background: rgba(234, 179, 8, 0.35);
    color: #fde047;
}

[data-bs-theme="dark"] .gs-item-extra,
body[data-topbar="dark"] .gs-item-extra {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
}

[data-bs-theme="dark"] .global-search-footer,
body[data-topbar="dark"] .global-search-footer {
    background: #0f172a;
    border-top-color: #334155;
    color: #94a3b8;
}

[data-bs-theme="dark"] .gs-hint-item kbd,
body[data-topbar="dark"] .gs-hint-item kbd {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}

[data-bs-theme="dark"] .gs-suggestion-card,
body[data-topbar="dark"] .gs-suggestion-card {
    background: #0f172a;
    border-color: #334155;
    color: #cbd5e1;
}

[data-bs-theme="dark"] .gs-suggestion-card:hover,
body[data-topbar="dark"] .gs-suggestion-card:hover {
    background: #1e293b;
    border-color: #60a5fa;
    color: #93c5fd;
}
</style>

    });
</script>