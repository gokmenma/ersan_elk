<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\City;
use App\Helper\Security;
use App\Helper\Route;

$City = new City();


use App\Model\DefineModel;
use App\Model\UyeModel;
$Uye = new UyeModel();
$Define = new DefineModel();

$id = isset($_GET['id']) ? $_GET['id'] : 0;

$uye = $Uye->find(Security::decrypt($id));

$uye_no = isset($_GET["id"]) ? $uye->uye_no : $Define->getUyeNo();

$adi_soyadi = isset($_GET["id"]) ? $uye->adi_soyadi : "Yeni Üye";

//bir önceki kaydın id'sini al
$prev_id = Security::encrypt($Uye->getPreviousRecord(Security::decrypt($id)));

//bir sonraki kaydın id'sini al
$next_id = Security::encrypt($Uye->getNextRecord(Security::decrypt($id)));
?>

<div class="container-fluid">


    <!-- start page title -->
    <?php
    $maintitle = "Forms";
    $title = "Üye Düzenle";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">

        <div class="col-12">

            <!-- end row -->
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8 d-flex align-items-center">
                        <?php if ($prev_id != null && isset($_GET["id"])) { ?>


                            <a href="index?p=uye/duzenle&id=<?php echo $prev_id ?>">
                                <span class="mdi mdi-arrow-left-bold-box cursor float-start font-size-24 text-muted"></span>
                            </a>
                        <?php } ?>
                        <?php if ($next_id != null && isset($_GET["id"])) { ?>
                            <a href="index?p=uye/duzenle&id=<?php echo $next_id ?>">
                                <span class="mdi mdi-arrow-right-bold-box cursor float-end font-size-24 text-muted"></span>
                            </a>
                        <?php } ?>



                        <h4 class="card-title m-0 p-0"><?php echo $uye_no . "-" . $adi_soyadi ?></h4>

                    </div>
                    <div class="col-md-4">

                        <div class="d-flex flex-wrap gap-2 float-end">
                            <a type="button" href="<?php Route::get("uye/list") ?>"
                                class="btn btn-light waves-effect btn-label waves-light">
                                <i class="bx bx-left-arrow-alt label-icon"></i> Listeye
                                Dön</a>

                            <a href="<?php Route::get("uye/duzenle") ?>" type="button"
                                class="btn btn-success waves-effect btn-label waves-light float-end"><i
                                    class="bx bx-plus label-icon"></i> Yeni Üye</a>

                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#home" role="tab"
                                aria-selected="false" tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                <span class="d-none d-sm-block">Genel Bilgiler</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#uyelik" role="tab" aria-selected="false"
                                tabindex="-1">
                                <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                <span class="d-none d-sm-block">Üyelik Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#finansal" role="tab" aria-selected="false"
                                tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-cog"></i></span>
                                <span class="d-none d-sm-block">Finansal Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#diger" role="tab" aria-selected="true">
                                <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                <span class="d-none d-sm-block">Diğer Bilgiler</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#notes" role="tab" aria-selected="false"
                                tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-cog"></i></span>
                                <span class="d-none d-sm-block">Notlar</span>
                            </a>
                        </li>

                    </ul>


                    <!-- Tab panes -->
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active show" id="home" role="tabpanel">
                            <?php include_once "icerik/genel-bilgiler.php"; ?>
                        </div>
                        <div class="tab-pane" id="uyelik" role="tabpanel">
                            <?php include_once "icerik/uyelik-bilgileri.php"; ?>

                        </div>
                        <div class="tab-pane" id="finansal" role="tabpanel">
                            <?php include_once "icerik/finansal-islemler.php"; ?>

                        </div>
                        <div class="tab-pane" id="diger" role="tabpanel">
                            <p class="mb-0">
                                Food truck fixie locavore, accusamus mcsweeney's marfa nulla

                            </p>
                        </div>
                        <div class="tab-pane" id="notes" role="tabpanel">
                            <p class="mb-0">
                                <?php include_once "icerik/notlar.php"; ?>

                            </p>
                        </div>
                    </div>

                </div><!-- end card-body -->

                <div class="card-footer">

                </div>
            </div>
        </div>
    </div>
</div>