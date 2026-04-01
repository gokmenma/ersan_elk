<?php

require_once "vendor/autoload.php";


use App\Helper\Security;

use App\Model\UserModel;

$User = new UserModel();

$users = $User->getUsers();

?>


<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Kullanıcı Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <style>
        #usersTable tbody tr {
            cursor: pointer;
        }

        #usersTable tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.05);
        }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Kullanıcı Listesi</h4>
                        <p class="card-title-desc">Kullanıcıları görüntüleyebilir ve yeni Kullanıcı
                            ekleyebilirsiniz.

                        </p>
                    </div>

                    <div class="col-md-4">

                        <a href="#" type="button" id="userAddBtn"
                            class="btn btn-success waves-effect btn-label waves-light float-end"><i
                                class="bx bx-plus label-icon"></i> Yeni Ekle</a>
                    </div>

                </div>

                <div class="card-body overflow-auto">

                    <table id="usersTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center">Sıra</th>
                                <th style="width:25%">Roller</th>
                                <th style="width:25%">Kullanıcı Adı</th>
                                <th>Adı Soyadi</th>
                                <th class="text-center">Görevi</th>
                                <th class="text-center">E-Posta</th>
                                <th class="text-center">Telefon</th>
                                <th class="text-center">İzin Onayı</th>
                                <th class="text-center">İzin Onay Sırası</th>
                                <th>Durum</th>
                                <th>Kayıt Tarihi</th>
                                <th style="width:5%" class="no-sort">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($users as $user) {
                                $i++;
                                $enc_id = Security::encrypt($user->id);
                                ?>
                                <tr data-id="<?php echo $enc_id ?>">
                                    <td class="text-center">
                                        <?php echo $i ?>
                                    </td>

                                    <td style="width:10%">
                                        <?php
                                        $names = !empty($user->role_names) ? explode(',', $user->role_names) : [];
                                        $colors = !empty($user->role_colors) ? explode(',', $user->role_colors) : [];
                                        foreach ($names as $key => $name):
                                            $color = $colors[$key] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color ?>"><?php echo $name ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><?php echo $user->user_name ?></td>
                                    <td><?php echo $user->adi_soyadi ?></td>
                                    <td class="text-center"><?php echo $user->gorevi ?></td>
                                    <td class="text-center"><?php echo $user->email_adresi ?></td>
                                    <td class="text-center"><?php echo $user->telefon ?></td>
                                    <td class="text-center"><?php echo $user->izin_onayi_yapacakmi ?></td>
                                    <td class="text-center"><?php echo $user->izin_onay_sirasi ?></td>
                                    <td class="text-center">
                                        <?php if ($user->durum == 'Aktif'): ?>
                                            <span class="badge bg-success durum-degistir" data-id="<?php echo $enc_id; ?>" data-status="Pasif" style="cursor: pointer;">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger durum-degistir" data-id="<?php echo $enc_id; ?>" data-status="Aktif" style="cursor: pointer;">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user->created_at ?></td>

                                    <td class="text-center" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start icon-demo-content">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    data-bs-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                                                    <i class="bx bx-list-ul font-size-24 text-dark"></i>

                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="javascript:void(0)" data-id="<?php echo $enc_id; ?>"
                                                        class="dropdown-item kullanici-duzenle"><span
                                                            class="mdi mdi-account-edit font-size-18"></span>
                                                        Düzenle</a>
                                                    <a href="#" class="dropdown-item kullanici-sil"
                                                        data-id="<?php echo $enc_id; ?>"
                                                        data-name="<?php echo $user->adi_soyadi; ?>">
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
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="usreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content user-modal-content">
            <!-- Modern spinner -->
            <div class="d-flex justify-content-center align-items-center" style="height: 600px;">
                <div class="modern-spinner">
                    <div class="spinner-circle"></div>
                    <div class="spinner-circle"></div>
                    <div class="spinner-circle"></div>
                    <div class="spinner-circle"></div>
                </div>
            </div>
        </div>
    </div>
</div>