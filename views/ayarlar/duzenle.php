<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\TemsilcilikModel;
use App\Helper\Route;
use App\Helper\City;
use App\Model\SettingsModel;

$City = new City();

$Temsilcilik = new TemsilcilikModel();

$Settings = new SettingsModel();

$id = isset($_GET['id']) ? $_GET['id'] : 0;

$temsilcilik = $Temsilcilik->find(Security::decrypt($id));

/**Ayarları al */
$settings = (object)$Settings->getAllSettingsAsKeyValue();


?>

<div class="container-fluid">


    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
     $title = $id == 0 ? "Ayarlar" : "Ayarlar";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">

        <div class="col-12">

            <!-- end row -->
            <div class="card">
                                <div class="card-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#home" role="tab"
                                aria-selected="false" tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                <span class="d-none d-sm-block">Sendika Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#profile" role="tab" aria-selected="false"
                                tabindex="-1">
                                <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                <span class="d-none d-sm-block">Evrak Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#messages" role="tab" aria-selected="true">
                                <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                <span class="d-none d-sm-block">Mail& Sms Ayarları</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#settings" role="tab" aria-selected="false"
                                tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-cog"></i></span>
                                <span class="d-none d-sm-block">Bildirim Ayarları</span>
                            </a>
                        </li>

                    </ul>


                    <!-- Tab panes -->
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active show" id="home" role="tabpanel">
                            <?php include_once "icerik/genel-bilgiler.php"; ?>
                        </div>
                        <div class="tab-pane" id="profile" role="tabpanel">
                            <?php include_once "icerik/evrak-bilgileri.php"; ?>

                        </div>
                        <div class="tab-pane  " id="messages" role="tabpanel">
                            <?php include_once "icerik/mail-sms-ayarlari.php"; ?>
                        </div>
                        <div class="tab-pane" id="settings" role="tabpanel">
                        <?php include_once "icerik/bildirim-ayarlari.php"; ?>
                        </div>
                    </div>

                </div><!-- end card-body -->
            </div>
        </div>
    </div>
</div>

<script>
  
</script>