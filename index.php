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



use App\Service\Gate;

// Kullanıcı masaüstü kilidini kaldırmak isterse (?mobile=1) tekrar mobil yönlendirmeyi aç
if (isset($_GET['mobile']) && $_GET['mobile'] === '1') {
    unset($_SESSION['force_desktop']);
}

// Mobil cihaz yönlendirmesi: herhangi bir HTML çıktısından önce yap, kullanıcı süper admin ise yönlendirme yapma
if (!isset($_SESSION['force_desktop']) && !Gate::isSuperAdmin()) {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $chMobile = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
    $isMobileUa = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $ua);
    $isMobileCh = ($chMobile === '?1');

    if ($isMobileUa || $isMobileCh) {
        $p = urlencode($_GET['p'] ?? 'home');
        header("Location: mobile/index.php?p=$p");
        exit();
    }
}





?>
<?php include 'layouts/main.php'; ?>

<head>

    <script>
        <?php if (!isset($_SESSION['force_desktop'])): ?>
        (function () {
            function checkMobileRedirect() {
                var isMobileView = window.matchMedia('(max-width: 768px)').matches;
                if (isMobileView) {
                    var qs = new URLSearchParams(window.location.search);
                    var p = qs.get('p') || 'home';
                    window.location.replace('mobile/index.php?p=' + encodeURIComponent(p));
                }
            }
            
            // Sayfa yüklendiğinde kontrol et
            checkMobileRedirect();
            
            // Tarayıcı boyutu değiştiğinde (DevTools mobil görünüme geçince) kontrol et
            window.addEventListener('resize', checkMobileRedirect);
        })();
        <?php endif; ?>
    </script>

    <title>Ersan Elektrik | Personel Yönetimi</title>

    <?php include 'layouts/head.php'; ?>
    <link href="assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet"
        type="text/css" />
    <?php include 'layouts/head-style.php'; ?>


</head>

<?php include 'layouts/body.php'; ?>



<?php






$Menus = new MenuModel();
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

//Eğer oturum açmamışsa giriş sayfasına yönlendir
if ($currentUserId <= 0 || !isset($_SESSION['firma_id'])) {
    header("Location: logout.php");
    exit();
}


//echo "sube id : " . $_SESSION['sube_id'];

?>



<!-- Begin page -->
<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">

        <div class="page-content">

            <?php
            $page = $_GET['p'] ?? 'home';

            $publicPages = [
                'home'
            ];

            if (!in_array($page, $publicPages, true)) {
                $hasMenuAccess = $Menus->userCanAccessMenuLink($currentUserId, $page);
                if (!$hasMenuAccess) {

                    echo "<script> window.location.href = 'unauthorize.php'; </script>";
                    exit;
                }
            }

            // JSON endpoint'ler HTML layout'a girmemeli.
            // online-api.php, fetch ile çağrıldığı için burada erken include edip çıkıyoruz.
            if ($page === 'gelir-gider/online-api') {
                include_once "views/" . $page . ".php";
                exit;
            }

            if ($page === 'home') {
                ?>

                <style id="early-home-skeleton-style">
                    #early-home-skeleton {
                        position: fixed;
                        inset: 60px 0 0 250px;
                        background: #f8fafc;
                        z-index: 1090;
                        padding: 16px 20px;
                        display: block;
                    }

                    #early-home-skeleton .ehs-toolbar {
                        display: flex;
                        justify-content: space-between;
                        gap: 10px;
                        margin-bottom: 16px;
                    }

                    #early-home-skeleton .ehs-line {
                        height: 12px;
                        border-radius: 8px;
                        background: linear-gradient(90deg, rgba(203, 213, 225, .35) 25%, rgba(203, 213, 225, .6) 50%, rgba(203, 213, 225, .35) 75%);
                        background-size: 200% 100%;
                        animation: ehsShimmer 1.2s linear infinite;
                    }

                    #early-home-skeleton .ehs-grid {
                        display: grid;
                        grid-template-columns: repeat(12, minmax(0, 1fr));
                        gap: 14px;
                    }

                    #early-home-skeleton .ehs-card {
                        min-height: 120px;
                        border-radius: 12px;
                        border: 1px solid rgba(203, 213, 225, .5);
                        background: #fff;
                        padding: 12px;
                    }

                    #early-home-skeleton .lg {
                        grid-column: span 6;
                        min-height: 220px;
                    }

                    #early-home-skeleton .sm {
                        grid-column: span 3;
                    }

                    #early-home-skeleton .full {
                        grid-column: span 12;
                        min-height: 220px;
                    }

                    @keyframes ehsShimmer {
                        0% {
                            background-position: 200% 0;
                        }

                        100% {
                            background-position: -200% 0;
                        }
                    }

                    @media (max-width:991.98px) {
                        #early-home-skeleton {
                            left: 0;
                            top: 56px;
                        }

                        #early-home-skeleton .lg,
                        #early-home-skeleton .sm,
                        #early-home-skeleton .full {
                            grid-column: span 12;
                        }
                    }

                    [data-bs-theme="dark"] #early-home-skeleton {
                        background: #0f172a;
                    }

                    [data-bs-theme="dark"] #early-home-skeleton .ehs-card {
                        background: #111827;
                        border-color: #334155;
                    }

                    [data-bs-theme="dark"] #early-home-skeleton .ehs-line {
                        background: linear-gradient(90deg, rgba(51, 65, 85, .45) 25%, rgba(71, 85, 105, .65) 50%, rgba(51, 65, 85, .45) 75%);
                        background-size: 200% 100%;
                    }
                </style>

                <div id="early-home-skeleton" aria-hidden="true">
                    <div class="ehs-toolbar">
                        <div class="ehs-line" style="width:220px"></div>
                        <div class="ehs-line" style="width:320px"></div>
                    </div>
                    <div class="ehs-grid">
                        <div class="ehs-card lg">
                            <div class="ehs-line" style="width:45%"></div>
                            <div class="ehs-line" style="width:96%;margin-top:10px"></div>
                            <div class="ehs-line" style="width:88%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card lg">
                            <div class="ehs-line" style="width:50%"></div>
                            <div class="ehs-line" style="width:92%;margin-top:10px"></div>
                            <div class="ehs-line" style="width:74%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card sm">
                            <div class="ehs-line" style="width:62%"></div>
                            <div class="ehs-line" style="width:80%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card sm">
                            <div class="ehs-line" style="width:55%"></div>
                            <div class="ehs-line" style="width:84%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card sm">
                            <div class="ehs-line" style="width:48%"></div>
                            <div class="ehs-line" style="width:86%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card sm">
                            <div class="ehs-line" style="width:58%"></div>
                            <div class="ehs-line" style="width:82%;margin-top:10px"></div>
                        </div>
                        <div class="ehs-card full">
                            <div class="ehs-line" style="width:38%"></div>
                            <div class="ehs-line" style="width:100%;margin-top:10px"></div>
                            <div class="ehs-line" style="width:96%;margin-top:10px"></div>
                        </div>
                    </div>
                </div>
                <?php

                echo str_repeat(' ', 4096);
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                if (function_exists('flush')) {
                    @flush();
                }
            }


            include_once "views/" . $page . ".php" ?>
        </div>
        <!-- End Page-content -->

        <?php include 'layouts/footer.php'; ?>
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

    //Title'ı page-title-box classına dahip div'in textinden al
    var title = $(".page-title-box>h4").text();
    //Document'ın title'ına ata
    document.title = title + " | Ersan Elektrik" ?? " Ana Sayfa";
</script>

</body>

</html>