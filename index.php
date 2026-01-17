<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'layouts/session.php';





?>
<?php include 'layouts/main.php'; ?>

<head>

    <title>Cansen | Ana Sayfa</title>

    <?php include 'layouts/head.php'; ?>
    <link href="assets/libs/admin-resources/jquery.vectormap/jquery-jvectormap-1.2.2.css" rel="stylesheet"
        type="text/css" />
    <?php include 'layouts/head-style.php'; ?>


</head>

<?php include 'layouts/body.php'; ?>



<?php

require_once __DIR__ . '/Autoloader.php';
 require __DIR__ . '/vendor/autoload.php';
setlocale(LC_CTYPE, 'tr_TR.UTF-8');

use App\Helper\Route;
use App\Model\MenuModel;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();





$Menus = new MenuModel();

//Eğer oturum açmamışsa giriş sayfasına yönlendir
if (!isset($_SESSION['id'] ) || !isset($_SESSION['sube_id'])) {
    header("Location: " . "/admin/logout.php");
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

            // JSON endpoint'ler HTML layout'a girmemeli.
            // online-api.php, fetch ile çağrıldığı için burada erken include edip çıkıyoruz.
            if ($page === 'gelir-gider/online-api') {
                include_once "views/" . $page . ".php";
                exit;
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
    //Title'ı page-title-box classına dahip div'in textinden al
    var title = $(".page-title-box>h4").text();
    //Document'ın title'ına ata
    document.title = "Ersan Elektrik | " + title;
</script>

</body>

</html>