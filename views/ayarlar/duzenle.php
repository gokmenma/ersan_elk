<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\SettingsModel;
use App\Service\Gate;

$Settings = new SettingsModel();

$id = isset($_GET['id']) ? $_GET['id'] : 0;

/**Ayarları al */
$settings = (object) $Settings->getAllSettingsAsKeyValue();


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
                        <?php if (Gate::allows('mail_sms_ayarlari_sekmesi')): ?>

                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" data-bs-toggle="tab" href="#messages" role="tab"
                                    aria-selected="true">
                                    <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                    <span class="d-none d-sm-block">Mail & Sms Ayarları</span>
                                </a>
                            </li>
                        <?php endif ?>
                        <?php if (Gate::allows('online_sorgulama_ayarlari_sekmesi')): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#cron_ayarlari" role="tab"
                                aria-selected="false">
                                <span class="d-block d-sm-none"><i class="far fa-clock"></i></span>
                                <span class="d-none d-sm-block">Online Sorgulama Ayarları</span>
                            </a>
                        </li>
                        <?php endif ?>
                        <?php if (Gate::allows('sgk_vizite_ayarlari_sekmesi')): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#sgk_vizite_ayarlari" role="tab"
                                aria-selected="false">
                                <span class="d-block d-sm-none"><i class="ti ti-building-hospital"></i></span>
                                <span class="d-none d-sm-block">SGK Vizite Ayarları</span>
                            </a>
                        </li>
                        <?php endif ?>
                        <?php if (Gate::allows('canli_destek_ayarlari_sekmesi')): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#canli_destek_ayarlari" role="tab"
                                aria-selected="false">
                                <span class="d-block d-sm-none"><i class="bx bx-support"></i></span>
                                <span class="d-none d-sm-block">Canlı Destek Ayarları</span>
                            </a>
                        </li>
                        <?php endif ?>
                    </ul>


                    <!-- Tab panes -->
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active show" id="messages" role="tabpanel">
                            <?php if (Gate::allows('mail_sms_ayarlari_sekmesi')): ?>
                            <?php include_once "icerik/mail-sms-ayarlari.php"; ?>
                            <?php endif ?>
                        </div>
                        <div class="tab-pane" id="cron_ayarlari" role="tabpanel">
                            <?php if (Gate::allows('online_sorgulama_ayarlari_sekmesi')): ?>
                            <?php include_once "icerik/online-sorgulama-ayarlari.php"; ?>
                            <?php endif ?>
                        </div>
                        <div class="tab-pane" id="sgk_vizite_ayarlari" role="tabpanel">
                            <?php if (Gate::allows('sgk_vizite_ayarlari_sekmesi')): ?>
                            <?php include_once "icerik/sgk-vizite-ayarlari.php"; ?>
                            <?php endif ?>
                        </div>
                        <div class="tab-pane" id="canli_destek_ayarlari" role="tabpanel">
                            <?php if (Gate::allows('canli_destek_ayarlari_sekmesi')): ?>
                            <?php include_once "icerik/canli-destek-ayarlari.php"; ?>
                            <?php endif ?>
                        </div>
                    </div>

                </div><!-- end card-body -->
            </div>
        </div>
    </div>
</div>

<script>

</script>