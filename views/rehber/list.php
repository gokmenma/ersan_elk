<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\RehberModel;

$Rehber = new RehberModel();

$kisiler = $Rehber->all()->get();
?>


<style>
    table th:not(.no-sort) {
        cursor: pointer;
    }
</style>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Telefon Listesi";
    $title = "Telefon Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Rehber</h4>
                        <p class="card-title-desc">Telefon veya adresleri görüntüleyebilir ve yeni kayıt
                            ekleyebilirsiniz.

                        </p>
                    </div>

                    <div class="col-md-4">

                        <a href="#" type="button" id="yeniEkle" data-bs-toggle="modal" data-bs-target="#rehberModal"
                            class="btn btn-success waves-effect btn-label waves-light float-end"><i
                                class="bx bx-plus label-icon"></i> Yeni Ekle</a>
                    </div>

                </div>

                <div class="card-body overflow-auto">

                    <table id="rehberTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center">Sıra</th>
                                <th class="text-center">Adı Soyadı</th>
                                <th data-tooltip="true" data-tooltip-title="top">Kurumu</th>
                                <th>Telefon</th>
                                <th>Email</th>
                                <th>Adres</th>
                             
                                <th>Kayıt Tarihi</th>
                                <th style="width:5%" class="no-sort">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($kisiler as $kisi) {
                                $i++;
                                $enc_id = Security::encrypt($kisi->id);
                                ?>
                                <tr data-id="<?php echo $enc_id ?>">
                                    <td class="text-center">
                                        <?php echo $i ?>
                                    </td>

                                    <td class="text-center">
                                        <?php echo $kisi->adi_soyadi ?>
                                    </td>
                                    
                                    <td data-tooltip="true" data-tooltip-title="top">
                                        <a href="#" data-id="<?php echo $enc_id; ?>" class="dropdown-item duzenle">
                                            <?php echo $kisi->kurum_adi; ?></a>
                                    </td>
                                    
                                    <td>
                                        <?php echo $kisi->telefon ?>
                                    </td>
                                    
                                    <td>
                                        <?php echo $kisi->email ?>
                                    </td>
                                    
                                    <td>
                                        <?php echo $kisi->adres ?>
                                    </td>

                                    <td>
                                        <?php echo $kisi->kayit_tarihi ?>

                                    </td>

                                    <td class="text-center" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="#" data-id="<?php echo $enc_id; ?>" 
                                                        class="dropdown-item kayit-duzenle"><span
                                                            class="mdi mdi-account-edit font-size-18"></span>
                                                        Düzenle</a>
                                                    <a href="#" class="dropdown-item kayit-sil"
                                                        data-id="<?php echo $enc_id; ?>"
                                                        data-name="<?php echo $kisi->adi_soyadi; ?>">
                                                        <span class="mdi mdi-delete font-size-18"></span>
                                                        Sil</a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->
</div> <!-- container-fluid -->

<!-- Modal -->
<?php include_once "modal/general-modal.php" ?>