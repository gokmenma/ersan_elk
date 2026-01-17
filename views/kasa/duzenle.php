<?php 
use App\Model\KasaModel;
use App\Helper\Security;


$Kasa = new KasaModel();


$kasa_id = isset($_GET['id']) ? $_GET['id'] : null;

$kasa= $Kasa->find(Security::decrypt($kasa_id));

?>

<div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">
                        <h4 class="card-title"><?php echo $sube->sube_adi ?? "Yeni Şube"; ?></h4>
                    </div>
                    <div class="col-md-4">

                        <div class="d-flex flex-wrap gap-2 float-end">
                            <a type="button" href="index?p=kasa/list" class="btn btn-light waves-effect btn-label waves-light">
                                <i class="bx bx-left-arrow-alt label-icon"></i> Listeye
                                Dön</a>


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
                       
                        <!-- <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#profile" role="tab" aria-selected="true">
                                <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                <span class="d-none d-sm-block">Kasa Hareketleri</span>
                            </a>
                        </li> -->
                      
                    </ul>


                    <!-- Tab panes -->
                    <div class="tab-content p-3 text-muted">
                        <div class="tab-pane active show" id="home" role="tabpanel">
                            <?php require_once "icerik/genel-bilgiler.php" ?>
                        </div>
                        <div class="tab-pane" id="profile" role="tabpanel">
                            <?php //require_once "icerik/kasa-hareketleri.php"?>
                        </div>
                                             
                    </div>

                </div><!-- end card-body -->

                <div class="card-footer">

                </div>
            </div>