<?php

require_once "vendor/autoload.php";

use App\Helper\Alert;
use App\Helper\Security;
use App\Service\Gate;


use App\Model\UserRolesModel;

$UserGroups = new UserRolesModel();

$usergroups = $UserGroups->getUserGroups();

if (Gate::allows("yetki_gruplari")) { ?>
    <style>
        .select2-results__option[aria-disabled=true] {
            display: none !important;
        }
    </style>


    <div class="container-fluid">

        <!-- start page title -->
        <?php
        $maintitle = "Ana Sayfa";
        $title = "Yetki Grup Listesi";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->


        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-grid d-md-flex d-block">
                        <div class="card-title col-md-8">

                            <h4 class="card-title">Yetki Grupları Listesi</h4>
                            <p class="card-title-desc">Yetki Gruplarını görüntüleyebilir ve yeni yetki grubu
                                ekleyebilirsiniz.

                            </p>
                        </div>

                        <div class="col-md-4">

                            <a href="#" type="button" id="groupAddBtn" data-bs-toggle="modal" data-bs-target="#groupModal"
                                class="btn btn-success waves-effect btn-label waves-light float-end"><i
                                    class="bx bx-plus label-icon"></i> Yeni Ekle</a>
                        </div>

                    </div>

                    <div class="card-body overflow-auto">

                        <table id="groupsTable" class="datatable table table-bordered nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="text-center">Sıra</th>
                                    <th style="width:25%">Yetki Adı</th>
                                    <th>Yetki Açıklaması</th>
                                    <th>Kayıt Tarihi</th>
                                    <th style="width:5%" class="no-sort">İşlem</th>
                                </tr>
                            </thead>


                            <tbody>

                                <?php
                                $i = 0;
                                foreach ($usergroups as $group) {
                                    $i++;
                                    $enc_id = Security::encrypt($group->id);
                                    ?>
                                    <tr data-id="<?php echo $enc_id ?>">
                                        <td class="text-center">
                                            <?php echo $i ?>
                                        </td>

                                        <td><span
                                                class="badge bg-<?php echo $group->role_color ?? 'secondary'; ?>"><?php echo $group->role_name; ?></span>
                                        </td>
                                        <td><?php echo $group->description; ?></td>
                                        <td><?php echo $group->kayit_tarihi; ?></td>

                                        <td class="text-center" style="width:5%">
                                            <div class="flex-shrink-0">
                                                <div class="dropdown align-self-start icon-demo-content">
                                                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                        <i class="bx bx-list-ul font-size-24 text-dark"></i>

                                                    </a>
                                                    <div class="dropdown-menu">
                                                        <a href="index?p=kullanici-gruplari/duzenle&id=<?php echo $enc_id; ?>"
                                                            data-id="<?php echo $enc_id; ?>" class="dropdown-item"><span
                                                                class="mdi mdi-account-edit font-size-18"></span>
                                                            Yetkileri Düzenle</a>
                                                        <a href="javascript:void(0)" data-id="<?php echo $enc_id; ?>"
                                                            class="dropdown-item kullanici-duzenle"><span
                                                                class="mdi mdi-account-edit font-size-18"></span>
                                                            Düzenle</a>
                                                        <a href="javascript:void(0)" data-id="<?php echo $enc_id; ?>"
                                                            data-raw-id="<?php echo $group->id; ?>"
                                                            data-name="<?php echo $group->role_name; ?>"
                                                            class="dropdown-item yetki-kopyala">
                                                            <span class="mdi mdi-content-copy font-size-18"></span>
                                                            Yetkileri Kopyala</a>
                                                        <a href="#" class="dropdown-item kullanici-sil"
                                                            data-id="<?php echo $enc_id; ?>"
                                                            data-name="<?php echo $group->role_name; ?>">
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
    <div class="modal fade" id="groupModal" tabindex="-1" aria-labelledby="usreModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content group-modal-content ">
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

    <!-- Yetki Kopyalama Modalı -->
    <div class="modal fade" id="copyPermissionsModal" tabindex="-1" aria-labelledby="copyPermissionsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="copyPermissionsModalLabel">Yetkileri Kopyala</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="copyPermissionsForm">
                        <input type="hidden" name="target_role_id" id="target_role_id">
                        <div class="alert alert-info">
                            <strong id="target_role_name"></strong> grubuna hangi gruptan yetkileri kopyalamak
                            istiyorsunuz?
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kaynak Yetki Grubu</label>
                            <select class="form-select select2" name="source_role_id" id="source_role_id"
                                style="width: 100%;">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($usergroups as $srcGroup) { ?>
                                    <option value="<?php echo Security::encrypt($srcGroup->id); ?>"
                                        data-raw-id="<?php echo $srcGroup->id; ?>">
                                        <?php echo $srcGroup->role_name; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" id="btnCopyPermissions" class="btn btn-primary">Kopyala</button>
                </div>
            </div>
        </div>
    </div>

<?php } else {
    Alert::danger("Bu yetkiye sahip değilsiniz.");
}
?>