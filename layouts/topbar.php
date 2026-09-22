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

        <!-- Global Arama Kutusu (Geniş Ekranlarda Topbarın Tam Ortasında) -->
        <?php if (\App\Service\Gate::allows('personel_listesi')): ?>
        <div class="topbar-global-search-wrapper d-none d-xl-block">
            <div class="position-relative" id="global-search-wrapper">
                <div class="global-search-box">
                    <span class="global-search-icon">
                        <i class="bx bx-search-alt-2"></i>
                    </span>
                    <input type="text" class="global-search-input" id="global-search-input" 
                           placeholder="Personel ara... (İsim, TC, Görev, Telefon)" 
                           autocomplete="off" spellcheck="false">
                    <div class="global-search-actions">
                        <span class="global-search-spinner" id="global-search-spinner" style="display: none;">
                            <i class="bx bx-loader-alt bx-spin"></i>
                        </span>
                        <button type="button" class="btn btn-sm btn-link global-search-clear p-0" id="global-search-clear" style="display: none;" title="Temizle">
                            <i class="bx bx-x font-size-18"></i>
                        </button>
                        <span class="global-search-kbd d-none d-xxl-inline-flex" title="Kısayol">
                            <kbd>Ctrl</kbd><kbd class="ms-1">K</kbd>
                        </span>
                    </div>
                </div>

                <!-- Açılır Şık Sonuç Paneli -->
                <div class="global-search-dropdown shadow-lg" id="global-search-dropdown" style="display: none;">
                    <div class="global-search-results-list" id="global-search-results">
                        <!-- Dinamik Sonuçlar -->
                    </div>
                    <div class="global-search-footer">
                        <span class="global-search-stats" id="global-search-stats">Toplam 0 sonuç</span>
                        <div class="global-search-shortcuts">
                            <span><kbd>↑</kbd><kbd class="ms-1">↓</kbd> Gezin</span>
                            <span class="ms-2"><kbd>↵</kbd> Seç</span>
                            <span class="ms-2"><kbd>ESC</kbd> Kapat</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex align-items-center">

            <!-- Küçük/Orta Ekranlarda (1200px altı) Arama İkon Butonu -->
            <?php if (\App\Service\Gate::allows('personel_listesi')): ?>
            <div class="dropdown d-inline-block d-xl-none ms-1">
                <button type="button" class="btn header-item noti-icon position-relative" id="page-header-search-dropdown" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false" title="Personel Ara (Ctrl+K)">
                    <i data-feather="search" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0 shadow-lg border-0"
                    aria-labelledby="page-header-search-dropdown" style="min-width: 320px; width: 88vw; max-width: 420px; border-radius: 12px; overflow: hidden;">
                    <div class="p-3 border-bottom bg-light">
                        <div class="position-relative">
                            <input type="text" class="form-control rounded-pill ps-4 pe-4 font-size-13" id="global-search-mobile-input" 
                                   placeholder="Personel ara... (İsim, TC, Görev, Telefon)" autocomplete="off">
                            <i class="bx bx-search-alt position-absolute top-50 start-0 translate-middle-y ms-2 text-muted font-size-16"></i>
                            <span class="position-absolute top-50 end-0 translate-middle-y me-2" id="global-search-mobile-spinner" style="display: none;">
                                <i class="bx bx-loader-alt bx-spin text-primary font-size-16"></i>
                            </span>
                        </div>
                    </div>
                    <div class="global-search-results-list" id="global-search-mobile-results" style="max-height: 360px; overflow-y: auto;">
                        <div class="p-4 text-center text-muted font-size-12">
                            <i class="bx bx-search-alt font-size-24 d-block mb-1 text-muted opacity-50"></i>
                            Aramak istediğiniz personelin adını, TC'sini veya görevini yazınız.
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

        // Global Arama Kutusu (Spotlight Search) JS Kodları
        const $searchInput = $('#global-search-input');
        const $searchDropdown = $('#global-search-dropdown');
        const $searchResults = $('#global-search-results');
        const $searchSpinner = $('#global-search-spinner');
        const $searchClear = $('#global-search-clear');
        const $searchStats = $('#global-search-stats');
        let searchDebounceTimer = null;
        let activeItemIndex = -1;
        let lastSearchQuery = '';

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightMatch(text, query) {
            if (!text || !query) return text || '';
            const cleanQuery = query.trim();
            if (cleanQuery.length === 0) return text;
            const regex = new RegExp('(' + escapeRegExp(cleanQuery) + ')', 'gi');
            return String(text).replace(regex, '<mark class="global-search-highlight">$1</mark>');
        }

        function performSearch(query) {
            query = query.trim();
            if (query.length < 2) {
                $searchSpinner.hide();
                $searchDropdown.hide();
                $searchResults.empty();
                return;
            }

            $searchSpinner.show();
            lastSearchQuery = query;

            $.ajax({
                url: 'api/global_search.php',
                type: 'GET',
                data: { q: query },
                dataType: 'json',
                success: function (response) {
                    $searchSpinner.hide();
                    if (response.status === 'success') {
                        renderSearchResults(response, query);
                    } else {
                        renderSearchError(response.message || 'Arama sırasında bir hata oluştu.');
                    }
                },
                error: function () {
                    $searchSpinner.hide();
                    renderSearchError('Sunucu bağlantı hatası oluştu.');
                }
            });
        }

        function renderSearchResults(response, query) {
            activeItemIndex = -1;
            const categories = response.categories || {};
            const total = response.total || 0;

            $searchStats.text('Toplam ' + total + ' sonuç bulundu');

            if (total === 0) {
                $searchResults.html(`
                    <div class="global-search-empty text-center p-4">
                        <div class="avatar-md mx-auto mb-2 text-muted">
                            <i class="bx bx-search-alt font-size-24"></i>
                        </div>
                        <h6 class="font-size-14 text-dark mb-1">Sonuç Bulunamadı</h6>
                        <p class="text-muted font-size-12 mb-0">"<strong>${$('<div>').text(query).html()}</strong>" ile eşleşen personel kaydı bulunamadı.</p>
                    </div>
                `);
                $searchDropdown.show();
                return;
            }

            let html = '';

            // Personel Kategorisi
            if (categories.personel && categories.personel.items && categories.personel.items.length > 0) {
                html += `
                    <div class="global-search-category">
                        <div class="global-search-category-header">
                            <span><i class="bx bx-user me-1 text-primary"></i> ${categories.personel.label}</span>
                            <span class="badge bg-soft-primary text-primary font-size-11">${categories.personel.count}</span>
                        </div>
                        <div class="global-search-items-group">
                `;

                categories.personel.items.forEach(function (p, index) {
                    const highlightedName = highlightMatch(p.title, query);
                    const highlightedDuty = highlightMatch(p.duty, query);
                    const highlightedDept = p.department ? highlightMatch(p.department, query) : '';
                    const highlightedPhone = p.phone ? highlightMatch(p.phone, query) : '';

                    let avatarHtml = '';
                    if (p.avatar_url) {
                        avatarHtml = `
                            <div class="global-search-avatar">
                                <img src="${p.avatar_url}" alt="${p.title}" class="rounded-circle w-100 h-100 object-fit-cover">
                                <span class="global-search-status-dot ${p.is_active ? 'bg-success' : 'bg-danger'}"></span>
                            </div>
                        `;
                    } else {
                        avatarHtml = `
                            <div class="global-search-avatar" style="background-color: ${p.avatar_color};">
                                <span>${p.initials}</span>
                                <span class="global-search-status-dot ${p.is_active ? 'bg-success' : 'bg-danger'}"></span>
                            </div>
                        `;
                    }

                    let tcBadge = '';
                    if (p.masked_tc || p.tc) {
                        tcBadge = `<span class="badge bg-light text-muted border font-size-11 ms-2 font-monospace"><i class="bx bx-id-card me-1"></i>${p.masked_tc || p.tc}</span>`;
                    }

                    let metaItems = [];
                    if (highlightedDuty) metaItems.push(`<span><i class="bx bx-briefcase-alt-2 me-1"></i>${highlightedDuty}</span>`);
                    if (highlightedDept) metaItems.push(`<span><i class="bx bx-buildings me-1"></i>${highlightedDept}</span>`);
                    if (highlightedPhone) metaItems.push(`<span><i class="bx bx-phone me-1"></i>${highlightedPhone}</span>`);
                    if (p.team) metaItems.push(`<span><i class="bx bx-group me-1"></i>${p.team}</span>`);

                    html += `
                        <a href="${p.url}" class="global-search-item" data-index="${index}">
                            ${avatarHtml}
                            <div class="global-search-item-info flex-grow-1 min-w-0 ms-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center text-truncate">
                                        <span class="global-search-item-title text-truncate">${highlightedName}</span>
                                        ${tcBadge}
                                    </div>
                                    <span class="badge ${p.status_badge} font-size-11 ms-2 flex-shrink-0">${p.status_text}</span>
                                </div>
                                <div class="global-search-item-meta text-truncate">
                                    ${metaItems.join('<span class="mx-1 text-muted">•</span>')}
                                </div>
                            </div>
                            <div class="global-search-item-arrow ms-2">
                                <i class="bx bx-chevron-right font-size-18 text-muted"></i>
                            </div>
                        </a>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            }

            $searchResults.html(html);
            $searchDropdown.show();
        }

        function renderSearchError(msg) {
            $searchResults.html(`
                <div class="global-search-empty text-center p-3 text-danger">
                    <i class="bx bx-error-circle font-size-20 me-1"></i> ${$('<div>').text(msg).html()}
                </div>
            `);
            $searchDropdown.show();
        }

        function updateActiveItem(items) {
            items.removeClass('active');
            if (activeItemIndex >= 0 && activeItemIndex < items.length) {
                const $active = items.eq(activeItemIndex);
                $active.addClass('active');
                
                // Otomatik scroll
                const container = $searchResults[0];
                const activeEl = $active[0];
                if (container && activeEl) {
                    const containerTop = container.scrollTop;
                    const containerBottom = containerTop + container.clientHeight;
                    const elemTop = activeEl.offsetTop;
                    const elemBottom = elemTop + activeEl.clientHeight;

                    if (elemTop < containerTop) {
                        container.scrollTop = elemTop;
                    } else if (elemBottom > containerBottom) {
                        container.scrollTop = elemBottom - container.clientHeight;
                    }
                }
            }
        }

        // Input Olayları
        $searchInput.on('input', function () {
            const query = $(this).val();
            
            if (query.trim().length > 0) {
                $searchClear.show();
            } else {
                $searchClear.hide();
            }

            clearTimeout(searchDebounceTimer);
            if (query.trim().length >= 2) {
                searchDebounceTimer = setTimeout(function () {
                    performSearch(query);
                }, 250);
            } else {
                $searchSpinner.hide();
                $searchDropdown.hide();
                $searchResults.empty();
            }
        });

        $searchInput.on('focus', function () {
            const query = $(this).val().trim();
            if (query.length >= 2 && $searchResults.children().length > 0) {
                $searchDropdown.show();
            }
        });

        $searchClear.on('click', function () {
            $searchInput.val('').focus();
            $searchClear.hide();
            $searchSpinner.hide();
            $searchDropdown.hide();
            $searchResults.empty();
            lastSearchQuery = '';
        });

        // Klavye Navigasyonu
        $searchInput.on('keydown', function (e) {
            const items = $searchResults.find('.global-search-item');
            
            if (!$searchDropdown.is(':visible') || items.length === 0) {
                if (e.key === 'Escape') {
                    $searchInput.blur();
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeItemIndex = (activeItemIndex + 1) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeItemIndex = (activeItemIndex - 1 + items.length) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeItemIndex >= 0 && activeItemIndex < items.length) {
                    window.location.href = items.eq(activeItemIndex).attr('href');
                } else if (items.length > 0) {
                    window.location.href = items.first().attr('href');
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                $searchDropdown.hide();
                $searchInput.blur();
            }
        });

        // Mobil / Kompakt Arama Olayları
        const $mobileInput = $('#global-search-mobile-input');
        const $mobileResults = $('#global-search-mobile-results');
        const $mobileSpinner = $('#global-search-mobile-spinner');
        let mobileDebounceTimer = null;

        $mobileInput.on('input', function () {
            const query = $(this).val().trim();
            clearTimeout(mobileDebounceTimer);

            if (query.length < 2) {
                $mobileSpinner.hide();
                $mobileResults.html('<div class="p-4 text-center text-muted font-size-12"><i class="bx bx-search-alt font-size-24 d-block mb-1 text-muted opacity-50"></i>Aramak istediğiniz personelin adını, TC\'sini veya görevini yazınız.</div>');
                return;
            }

            $mobileSpinner.show();

            mobileDebounceTimer = setTimeout(function () {
                $.ajax({
                    url: 'api/global_search.php',
                    type: 'GET',
                    data: { q: query },
                    dataType: 'json',
                    success: function (response) {
                        $mobileSpinner.hide();
                        if (response.status === 'success') {
                            const categories = response.categories || {};
                            if (categories.personel && categories.personel.items && categories.personel.items.length > 0) {
                                let mHtml = '';
                                categories.personel.items.forEach(function (p) {
                                    const hName = highlightMatch(p.title, query);
                                    let avHtml = p.avatar_url ? 
                                        `<div class="global-search-avatar"><img src="${p.avatar_url}" class="rounded-circle w-100 h-100 object-fit-cover"><span class="global-search-status-dot ${p.is_active ? 'bg-success' : 'bg-danger'}"></span></div>` :
                                        `<div class="global-search-avatar" style="background-color: ${p.avatar_color};"><span>${p.initials}</span><span class="global-search-status-dot ${p.is_active ? 'bg-success' : 'bg-danger'}"></span></div>`;

                                    let tcBadge = p.masked_tc || p.tc ? `<span class="badge bg-light text-muted border font-size-10 ms-1">${p.masked_tc || p.tc}</span>` : '';

                                    mHtml += `
                                        <a href="${p.url}" class="global-search-item">
                                            ${avHtml}
                                            <div class="global-search-item-info flex-grow-1 min-w-0 ms-2">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <div class="d-flex align-items-center text-truncate">
                                                        <span class="global-search-item-title text-truncate font-size-13">${hName}</span>
                                                        ${tcBadge}
                                                    </div>
                                                    <span class="badge ${p.status_badge} font-size-10 ms-1 flex-shrink-0">${p.status_text}</span>
                                                </div>
                                                <div class="global-search-item-meta text-truncate font-size-11">
                                                    <span>${p.duty}</span>
                                                    ${p.department ? '<span class="mx-1">•</span><span>' + p.department + '</span>' : ''}
                                                    ${p.phone ? '<span class="mx-1">•</span><span>' + p.phone + '</span>' : ''}
                                                </div>
                                            </div>
                                            <div class="global-search-item-arrow ms-1">
                                                <i class="bx bx-chevron-right font-size-16 text-muted"></i>
                                            </div>
                                        </a>
                                    `;
                                });
                                $mobileResults.html(mHtml);
                            } else {
                                $mobileResults.html('<div class="p-4 text-center text-muted font-size-12">"<strong>' + $('<div>').text(query).html() + '</strong>" ile eşleşen kayıt bulunamadı.</div>');
                            }
                        }
                    },
                    error: function() {
                        $mobileSpinner.hide();
                    }
                });
            }, 250);
        });

        $('#page-header-search-dropdown').on('shown.bs.dropdown', function () {
            setTimeout(function () {
                $mobileInput.focus();
            }, 100);
        });

        // Dışarı tıklandığında kapat
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#global-search-wrapper').length) {
                $searchDropdown.hide();
            }
        });

        // Global Kısayol: Ctrl+K veya Cmd+K (Geniş ekranda bara, küçük ekranda menüye odaklanır)
        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if ($searchInput.is(':visible')) {
                    $searchInput.focus().select();
                } else {
                    const dropdownBtn = document.getElementById('page-header-search-dropdown');
                    if (dropdownBtn) {
                        const bsDropdown = bootstrap.Dropdown.getOrCreateInstance(dropdownBtn);
                        bsDropdown.toggle();
                    }
                }
            }
        });

    });
</script>

<style>
/* Global Arama Çubuğu ve Sonuç Paneli Stilleri */
.navbar-header {
    position: relative;
}

.topbar-global-search-wrapper {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 500px);
    max-width: 560px;
    z-index: 1000;
}

@media (min-width: 1500px) {
    .topbar-global-search-wrapper {
        max-width: 640px;
        width: calc(100% - 540px);
    }
}

@media (min-width: 1200px) and (max-width: 1499px) {
    .topbar-global-search-wrapper {
        max-width: 460px;
        width: calc(100% - 460px);
    }
}

.global-search-box {
    display: flex;
    align-items: center;
    position: relative;
    background: rgba(var(--bs-tertiary-bg-rgb, 243, 243, 249), 0.85);
    border: 1px solid var(--bs-border-color, #e2e5e8);
    border-radius: 30px;
    padding: 0.3rem 0.8rem 0.3rem 1.1rem;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.global-search-box:focus-within {
    background: var(--bs-card-bg, #ffffff);
    border-color: #5156be;
    box-shadow: 0 0 0 3.5px rgba(81, 86, 190, 0.18), 0 2px 8px rgba(0, 0, 0, 0.04);
}

.global-search-icon {
    color: #74788d;
    font-size: 1.2rem;
    margin-right: 0.4rem;
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.global-search-input {
    border: none;
    background: transparent;
    padding: 0.35rem 0.4rem;
    font-size: 0.875rem;
    width: 100%;
    outline: none !important;
    box-shadow: none !important;
    color: var(--bs-body-color, #495057);
}

.global-search-input::placeholder {
    color: #98a6ad;
    font-weight: 400;
}

.global-search-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    gap: 6px;
}

.global-search-spinner {
    color: #5156be;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.global-search-clear {
    color: #74788d;
    text-decoration: none !important;
    line-height: 1;
    display: flex;
    align-items: center;
}
.global-search-clear:hover {
    color: #f46a6a;
}

.global-search-kbd {
    display: flex;
    align-items: center;
    gap: 2px;
}
.global-search-kbd kbd {
    font-size: 0.68rem;
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
    color: #74788d;
    border-radius: 4px;
    padding: 2px 5px;
    font-weight: 600;
    box-shadow: none;
}

/* Açılır Sonuç Paneli */
.global-search-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    width: 100%;
    min-width: 100%;
    background: var(--bs-card-bg, #ffffff);
    border: 1px solid var(--bs-border-color, rgba(0, 0, 0, 0.08));
    border-radius: 14px;
    box-shadow: 0 16px 40px rgba(18, 38, 63, 0.16), 0 3px 10px rgba(18, 38, 63, 0.06);
    z-index: 1060;
    overflow: hidden;
    animation: searchDropdownFadeIn 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes searchDropdownFadeIn {
    from {
        opacity: 0;
        transform: translateY(-6px) scale(0.99);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.global-search-results-list {
    max-height: 380px;
    overflow-y: auto;
    padding: 0.4rem 0;
}

.global-search-category-header {
    padding: 0.5rem 1rem 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #74788d;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--bs-border-color, #f1f1f5);
}

.global-search-item {
    display: flex;
    align-items: center;
    padding: 0.65rem 1rem;
    color: var(--bs-body-color, #495057);
    text-decoration: none !important;
    transition: all 0.15s ease;
    border-left: 3px solid transparent;
    cursor: pointer;
    border-bottom: 1px solid rgba(0, 0, 0, 0.03);
}

.global-search-item:last-child {
    border-bottom: none;
}

.global-search-item:hover,
.global-search-item.active {
    background-color: rgba(81, 86, 190, 0.08);
    border-left-color: #5156be;
    color: var(--bs-body-color, #495057);
}

.global-search-item:hover .global-search-item-title,
.global-search-item.active .global-search-item-title {
    color: #5156be;
}

.global-search-item:hover .global-search-item-arrow i,
.global-search-item.active .global-search-item-arrow i {
    color: #5156be !important;
    transform: translateX(3px);
    transition: transform 0.15s ease;
}

/* Avatar */
.global-search-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: #ffffff;
    position: relative;
    flex-shrink: 0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}

.global-search-status-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid var(--bs-card-bg, #ffffff);
}

/* Personel Bilgi Metinleri */
.global-search-item-title {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--bs-heading-color, #212529);
}

.global-search-item-meta {
    font-size: 0.74rem;
    color: #74788d;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 2px;
}

.global-search-highlight {
    background-color: rgba(241, 180, 76, 0.35);
    color: inherit;
    padding: 0 2px;
    border-radius: 2px;
    font-weight: 700;
}

/* Footer */
.global-search-footer {
    padding: 0.45rem 1rem;
    background: var(--bs-tertiary-bg, #f8f9fa);
    border-top: 1px solid var(--bs-border-color, #e9e9ef);
    font-size: 0.74rem;
    color: #74788d;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.global-search-shortcuts kbd {
    font-size: 0.68rem;
    background: rgba(0, 0, 0, 0.05);
    border: 1px solid rgba(0, 0, 0, 0.1);
    color: #74788d;
    border-radius: 3px;
    padding: 1px 4px;
}

/* Dark Mode Desteği */
[data-bs-theme="dark"] .global-search-box {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
}
[data-bs-theme="dark"] .global-search-box:focus-within {
    background: var(--bs-card-bg);
}
[data-bs-theme="dark"] .global-search-kbd kbd,
[data-bs-theme="dark"] .global-search-shortcuts kbd {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.15);
    color: #a6b0cf;
}
[data-bs-theme="dark"] .global-search-highlight {
    background-color: rgba(241, 180, 76, 0.45);
    color: #fff;
}
</style>

    });
</script>