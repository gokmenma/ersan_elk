<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'layouts/session.php';
//require_once __DIR__ . '/Autoloader.php';
require __DIR__ . '/vendor/autoload.php';
setlocale(LC_CTYPE, 'tr_TR.UTF-8');

use App\Model\MenuModel;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use App\Model\UserModel;

$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

// KASKİ oturumu genel yönetim panelinde dolaşamaz.
if (($_SESSION['portal_scope'] ?? '') === 'kaski') {
    header('Location: kaski/index.php');
    exit();
}

// Eğer oturum açmamışsa veya firma seçilmemişse giriş sayfasına yönlendir
if ($currentUserId <= 0 || !isset($_SESSION['firma_id'])) {
    header("Location: logout.php");
    exit();
}

// Durum kontrolü: Pasif ise çıkış yap
$StatusCheckUser = new UserModel();
$currentUserStatusCheck = $StatusCheckUser->find($currentUserId);
if (!$currentUserStatusCheck || ($currentUserStatusCheck->durum ?? 'Aktif') === 'Pasif') {
    header("Location: logout.php?status=inactive");
    exit();
}

$Menus = new MenuModel();

//

use App\Service\Gate;
use App\Service\RequestPerformanceProfiler;

RequestPerformanceProfiler::start('index.php', [
    'script' => 'index.php',
    'route' => $_GET['p'] ?? 'home'
]);

if (isset($_GET['mobile']) && $_GET['mobile'] === '1') {
    unset($_SESSION['force_desktop']);
}

if (!isset($_SESSION['force_desktop'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $chMobile = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
    $isPhoneUa = preg_match('/iPhone|iPod|IEMobile|Opera Mini|Windows Phone|BlackBerry/i', $ua)
        || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') !== false);
    $isTabletUa = preg_match('/iPad|Tablet/i', $ua)
        || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false);

    if ($isPhoneUa || (($chMobile === '?1') && !$isTabletUa && stripos($ua, 'Windows NT') === false)) {
        header('Location: ' . \App\Helper\MobileSpa::fromLegacy((string) ($_GET['p'] ?? 'home'), $_GET));
        exit();
    }
}

$page = $_GET['p'] ?? 'home';

// Mobil rota isimlerinin masaüstü eşleşmesi (alias)
$routeAliases = [
    'efatura'            => 'efatura/dashboard',
    'efatura-giden'      => 'efatura/giden-list',
    'efatura-gelen'      => 'efatura/gelen-list',
    'efatura-taslak'     => 'efatura/taslak-list',
    'efatura-olustur'    => 'efatura/olustur',
    'efatura-cari'       => 'efatura/cari-list',
    'efatura-mal-hizmet' => 'efatura/mal-hizmet-list',
    'efatura-ayarlar'    => 'efatura/ayarlar',
    'arac-dashboard'     => 'arac-takip/dashboard',
    'profil'             => 'profil/index',
];
if (isset($routeAliases[$page])) {
    $page = $routeAliases[$page];
}

// Firmanın çalışma/sözleşme dönemi kontrolü
$currentFirmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($currentFirmaId > 0) {
    $sozlesmeDonemModel = new \App\Model\SozlesmeDonemModel();
    $activeDonem = $sozlesmeDonemModel->getActiveDonem($currentFirmaId);

    if ($activeDonem) {
        $_SESSION['aktif_donem_id'] = (int) $activeDonem->id;
        $_SESSION['aktif_donem_adi'] = $activeDonem->donem_adi;
    } else {
        unset($_SESSION['aktif_donem_id'], $_SESSION['aktif_donem_adi']);
        // Firmanın tanımlı dönemi yoksa ve zaten dönem tanımları sayfasında değilse oraya yönlendir
        if ($page !== 'tanimlamalar/donem-tanimlari') {
            header("Location: index?p=tanimlamalar/donem-tanimlari&donem_yok=1");
            exit();
        }
    }
}

// Yetki kontrolü herhangi bir HTML çıktısı üretilmeden önce yapılmalıdır.
$publicPages = ['home'];
if (!in_array($page, $publicPages, true)) {
    $hasMenuAccess = RequestPerformanceProfiler::measure(
        'index.permission_policy_check',
        fn() => Gate::allowsPolicy('page', $page, '', 'GET'),
        1
    );

    if (!$hasMenuAccess) {
        header('Location: unauthorize.php');
        exit();
    }
}

\App\Service\ObserverMode::logPage($page);

try {
    $logModel = new \App\Model\SystemLogModel();
    $logModel->logPageView($currentUserId, $page, 'Desktop');
} catch (\Exception $e) {
}





?>
<?php include 'layouts/main.php'; ?>

<head>


    <title>Ersan Elektrik | Personel Yönetimi</title>

    <?php include 'layouts/head.php'; ?>
    <link href="assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet"
        type="text/css" />
    <?php include 'layouts/head-style.php'; ?>
    <link rel="expect" href="#app-shell-ready" blocking="render">


</head>

<?php include 'layouts/body.php'; ?>







<!-- Begin page -->
<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>
    <?php include 'layouts/observer-banner.php'; ?>

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">

        <div class="page-content">

            <?php
            // JSON endpoint'ler HTML layout'a girmemeli.
            // online-api.php, fetch ile çağrıldığı için burada erken include edip çıkıyoruz.
            if ($page === 'gelir-gider/online-api') {
                include_once "views/" . $page . ".php";
                exit;
            }

            if ($page === 'home') {
                ?>

                <?php

                echo str_repeat(' ', 4096);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                if (function_exists('flush')) {
                    @flush();
                }
            }



            include_once "views/" . $page . ".php"; ?>
        </div>
        <!-- End Page-content -->

        <!-- <?php //include 'layouts/footer.php'; ?> -->
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->




<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<!-- apexcharts -->

<script>
    (function () {
        const hideEarlyHomeSkeleton = function () {
            const skeleton = document.getElementById('early-home-skeleton');
            const style = document.getElementById('early-home-skeleton-style');
            if (skeleton) skeleton.remove();
            if (style) style.remove();
        };

        window.addEventListener('load', hideEarlyHomeSkeleton, { once: true });
        setTimeout(hideEarlyHomeSkeleton, 4500);
    })();

    // Document title güncellemesi
    window.addEventListener('DOMContentLoaded', function() {
        if (window.jQuery) {
            var title = $("#topbar-page-title").text().trim() || $(".page-title-box>h4").first().text().trim();
            if (title) {
                document.title = title + " | Ersan Elektrik";
            }
        }
    });
</script>

</body>

</html>
