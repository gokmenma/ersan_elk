<?php

$projectRoot = dirname(__DIR__);
chdir($projectRoot);
require_once $projectRoot . '/bootstrap.php';

use App\Model\SystemLogModel;
use App\Model\UserModel;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$User = new UserModel();
$user = $userId > 0 ? $User->find($userId) : null;

if (($_SESSION['portal_scope'] ?? '') !== 'kaski'
    || !$user
    || ($user->durum ?? 'Aktif') === 'Pasif'
    || !$User->hasRoleName($userId, 'KASKİ Görüntüleme')
    || empty($_SESSION['firma_id'])) {
    header('Location: logout.php');
    exit;
}

$_SESSION['user'] = $user;
$_GET['p'] = 'kacak/list';
$page = 'kacak/list';
$bodyClass = 'kaski-no-sidebar';
$safeUserName = htmlspecialchars((string) ($user->adi_soyadi ?? $user->user_name ?? ''), ENT_QUOTES, 'UTF-8');

try {
    (new SystemLogModel())->logPageView($userId, 'kaski/kacak/list', 'KASKİ Portal');
} catch (Throwable $e) {
    // Görüntüleme logu sayfayı engellemez.
}

include $projectRoot . '/layouts/main.php';
?>
<head>
    <base href="../">
    <title>KASKİ Kaçak Kontrol Portalı</title>
    <?php include $projectRoot . '/layouts/head.php'; ?>
    <?php include $projectRoot . '/layouts/head-style.php'; ?>
    <style>
        body.kaski-no-sidebar .main-content { margin-left: 0 !important; width: 100% !important; }
        body.kaski-no-sidebar #page-topbar { left: 0 !important; width: 100% !important; }
        body.kaski-no-sidebar .footer { left: 0 !important; }
        .kaski-topbar-mark { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); font-size: 20px; }
        .kaski-portal-badge { font-size: .65rem; letter-spacing: .03em; }
        .kaski-readonly-note { border-left: 4px solid var(--bs-info); }
        @media (max-width: 991.98px) {
            .kaski-topbar-subtitle { display: none; }
            #page-topbar .navbar-header { padding-left: 10px !important; padding-right: 10px !important; }
        }
    </style>
</head>
<?php include $projectRoot . '/layouts/body.php'; ?>

<div id="layout-wrapper">
    <header id="page-topbar">
        <div class="navbar-header">
            <div class="d-flex align-items-center gap-3">
                <div class="kaski-topbar-mark"><i class="bx bx-shield-quarter"></i></div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="fw-semibold">KASKİ Kaçak Kontrol</div>
                        <span class="badge bg-info-subtle text-info kaski-portal-badge d-none d-sm-inline">SADECE GÖRÜNTÜLEME</span>
                    </div>
                    <div class="text-muted small kaski-topbar-subtitle">Kaçak kayıtları ve raporlama portalı</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="d-none d-md-inline text-muted"><?= $safeUserName ?></span>
                <a class="btn btn-outline-danger btn-sm" href="kaski/logout.php"><i class="bx bx-log-out me-1"></i>Çıkış</a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid mb-3">
                <div class="alert alert-info kaski-readonly-note mb-0 py-2" role="status">
                    <i class="bx bx-info-circle me-1"></i> Bu portal salt okunurdur; kayıtlar üzerinde değişiklik yapılamaz.
                </div>
            </div>
            <?php include $projectRoot . '/views/kacak/list.php'; ?>
        </div>
        <?php include $projectRoot . '/layouts/footer.php'; ?>
    </div>
</div>

<?php include $projectRoot . '/layouts/vendor-scripts.php'; ?>
</body>
</html>
