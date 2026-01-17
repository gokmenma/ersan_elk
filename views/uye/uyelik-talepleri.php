<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\UyeTalepModel;


$UyeTalep = new UyeTalepModel();
$uyetalepleri = $UyeTalep->getUyeTalep();


?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Üyeler";
    $title = "Üyelik Talepleri";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-12">

                        <h4 class="card-title">Talep Listesi</h4>
                        <p class="card-title-desc">Üyelik taleplerini görüntüleyebilir ve talepleri onaylayabilirsiniz.

                        </p>
                    </div>
                </div>
                <div class="card-body overflow-auto">

                    <table id="membersTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th>Sıra</th>
                                <th>Ad Soyad</th>
                                <th>Tc Kimlik</th>
                                <th>Doğum Tarihi</th>
                                <th>Telefon</th>
                                <th>Email</th>
                                <th>Talep Tarihi</th>
                                <th>Sendika Üyesi mi</th>
                                <th>Üye Olduğu Sendika</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($uyetalepleri as $talep) {
                                $i++;
                                $enc_id = Security::encrypt($talep->id);
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $i; ?></td>
                                    <td><?php echo $talep->adi_soyadi; ?></td>
                                    <td><?php echo $talep->tc_kimlik; ?></td>
                                    <td><?php echo $talep->dogum_tarihi; ?></td>
                                    <td><?php echo $talep->telefon; ?></td>
                                    <td><?php echo $talep->email; ?></td>
                                    <td><?php echo $talep->talep_tarihi; ?></td>
                                    <td><?php echo $talep->sendika_uyesi_mi; ?></td>
                                    <td><?php echo $talep->uye_oldugu_sendika; ?></td>
                                    <td><?php echo $talep->onaylandi_mi; ?></td>


                                    <td>
                                        <div class="flex-shrink-0 text-end">
                                            <div class="dropdown align-self-start">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item uyelik_onayla" data-id="<?php echo $enc_id; ?>"
                                                        href="#"><span class="mdi mdi-account-check font-size-18"></span>
                                                        Onayla</a>
                                                    <a class="dropdown-item uyelik_talep_sil"
                                                        data-id="<?php echo $enc_id; ?>" href="#"><span
                                                            class="mdi mdi-delete font-size-18"></span>Sil</a>
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