<?php
use App\Helper\Helper;
use App\Helper\Route;
use App\Helper\Form;
use App\Model\FirmaModel;

if (!empty($_SESSION['lang'])) {
    $sessionLang = $_SESSION['lang'];
    require_once('assets/lang/' . $sessionLang . '.php');
} else {
    require_once(BASE_PATH . '/assets/lang/en.php');
}

$FirmaModel = new FirmaModel();

//seçili firma

$firma_option = $FirmaModel->option();

//Helper::dd($firma_option);


?>
<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
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

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item" id="vertical-menu-btn">
                <i class="fa fa-fw fa-bars"></i>
            </button>

            <!-- App Search-->
            <form class="app-search d-none d-lg-block" style="width: 200px;">


                <?php

                echo Form::FormSelect2(
                    name: "firma_id",
                    options: $firma_option,
                    valueField: "id",
                    textField: "firma_adi",
                    selectedValue: $_SESSION['firma_id'],
                    label: "Firma",
                    icon: "git-branch",
                    class: 'form-control select2 w-100 p-1'
                ); ?>
            </form>
        </div>

        <div class="d-flex">

            <div class="dropdown d-inline-block d-lg-none ms-2">
                <button type="button" class="btn header-item" id="page-header-search-dropdown" data-bs-toggle="dropdown"
                    aria-haspopup="true" aria-expanded="false">
                    <i data-feather="search" class="icon-lg"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                    aria-labelledby="page-header-search-dropdown">

                    <form class="p-3">
                        <div class="form-group m-0">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search ..."
                                    aria-label="Search Result">

                                <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="dropdown d-inline-block language-switch">


            </div>

            <div class="dropdown d-none d-sm-inline-block">
                <button type="button" class="btn header-item" id="mode-setting-btn">
                    <i data-feather="moon" class="icon-lg layout-mode-dark"></i>
                    <i data-feather="sun" class="icon-lg layout-mode-light"></i>
                </button>
            </div>

            <div class="dropdown d-none d-lg-inline-block ms-1">
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

                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item right-bar-toggle me-2">
                        <i data-feather="settings" class="icon-lg"></i>
                    </button>
                </div>

                <div class="dropdown d-inline-block">
                    <button type="button" class="btn header-item bg-light-subtle border-start border-end"
                        id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <img class="rounded-circle header-profile-user user-profile-image"
                            src="<?php echo Helper::base_url('assets/images/users/avatar-1.jpg'); ?>"
                            alt="Header Avatar" id="user_image">
                        <span class="d-none d-xl-inline-block ms-1 fw-medium setting_user_name"
                            id="setting_user_name"><?php echo $_SESSION["user_full_name"]; ?></span>
                        <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <a class="dropdown-item" href="apps-contacts-profile.php"><i
                                class="mdi mdi-face-profile font-size-16 align-middle me-1"></i> Profil</a>
                        <a class="dropdown-item" href="auth-lock-screen.php"><i
                                class="mdi mdi-lock font-size-16 align-middle me-1"></i> Kilitle</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="firma-degistir.php"><i
                                class="mdi mdi-swap-horizontal font-size-16 align-middle me-1"></i> Firma Değiştir</a>
                        <a class="dropdown-item" href="logout.php"><i
                                class="mdi mdi-logout font-size-16 align-middle me-1"></i> Çıkış Yap</a>
                    </div>
                </div>

            </div>
        </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        function fetchNotifications() {
            $.post('views/bildirim/api.php', { action: 'get-unread' }, function (response) {
                if (response.status === 'success') {
                    $('#notification-badge').text(response.count);
                    if (response.count > 0) {
                        $('#notification-badge').show();
                    } else {
                        $('#notification-badge').hide();
                    }

                    let html = '';
                    if (response.notifications.length === 0) {
                        html = '<div class="text-center p-3 text-muted">Bildirim yok</div>';
                    } else {
                        response.notifications.forEach(function (n) {
                            // Icon mapping correction if needed
                            let iconClass = n.icon;
                            if (!iconClass.startsWith('bx-') && !iconClass.startsWith('mdi-')) {
                                iconClass = 'bx-' + iconClass;
                            }

                            html += `
                            <a href="${n.link}" class="text-reset notification-item" onclick="markAsRead(${n.id})">
                                <div class="d-flex">
                                    <div class="flex-shrink-0 avatar-sm me-3">
                                        <span class="avatar-title bg-${n.color} rounded-circle font-size-16">
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
                        });
                    }
                    $('#notification-list').html(html);
                }
            }, 'json');
        }

        // Initial fetch
        fetchNotifications();

        // Poll every 30 seconds
        setInterval(fetchNotifications, 30000);

        window.markAsRead = function (id) {
            $.post('views/bildirim/api.php', { action: 'mark-read', id: id });
        };

        $('#mark-all-read').click(function () {
            $.post('views/bildirim/api.php', { action: 'mark-all-read' }, function (response) {
                fetchNotifications();
            }, 'json');
        });
    });
</script>