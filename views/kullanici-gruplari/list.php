<?php

require_once "vendor/autoload.php";

use App\Helper\Alert;
use App\Helper\Security;
use App\Service\Gate;
use App\Model\UserRolesModel;

$UserGroups = new UserRolesModel();
$usergroups = $UserGroups->getRolesWithDetails();

if (Gate::allows("yetki_gruplari") || Gate::allows("yetki_gruplari_izleme")) { ?>
    <style>
        .select2-results__option[aria-disabled=true] {
            display: none !important;
        }

        .bg-soft-purple {
            background-color: rgba(116, 120, 241, 0.12) !important;
            color: #7478f1 !important;
        }

        .bg-soft-success {
            background-color: rgba(10, 179, 156, 0.12) !important;
            color: #0ab39c !important;
        }

        .bg-soft-primary {
            background-color: rgba(91, 115, 232, 0.12) !important;
            color: #5b73e8 !important;
        }

        .bg-soft-info {
            background-color: rgba(23, 162, 184, 0.12) !important;
            color: #17a2b8 !important;
        }

        .bg-soft-warning {
            background-color: rgba(247, 184, 75, 0.15) !important;
            color: #e59819 !important;
        }

        .bg-soft-danger {
            background-color: rgba(240, 101, 72, 0.12) !important;
            color: #f06548 !important;
        }

        .bg-soft-secondary {
            background-color: rgba(116, 120, 141, 0.12) !important;
            color: #74788d !important;
        }

        .bg-gradient-custom {
            background: linear-gradient(90deg, #0ab39c 0%, #405189 100%) !important;
        }

        .btn-soft-primary {
            color: #5b73e8;
            background-color: rgba(91, 115, 232, 0.1);
            border: 1px solid rgba(91, 115, 232, 0.2);
        }
        .btn-soft-primary:hover {
            color: #fff;
            background-color: #5b73e8;
        }

        .btn-soft-info {
            color: #17a2b8;
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid rgba(23, 162, 184, 0.2);
        }
        .btn-soft-info:hover {
            color: #fff;
            background-color: #17a2b8;
        }

        .btn-soft-warning {
            color: #d98200;
            background-color: rgba(247, 184, 75, 0.15);
            border: 1px solid rgba(247, 184, 75, 0.25);
        }
        .btn-soft-warning:hover {
            color: #fff;
            background-color: #f7b84b;
        }

        .btn-soft-danger {
            color: #f06548;
            background-color: rgba(240, 101, 72, 0.1);
            border: 1px solid rgba(240, 101, 72, 0.2);
        }
        .btn-soft-danger:hover {
            color: #fff;
            background-color: #f06548;
        }

        .btn-soft-secondary {
            color: #74788d;
            background-color: rgba(116, 120, 141, 0.1);
            border: 1px solid rgba(116, 120, 141, 0.2);
        }
        .btn-soft-secondary:hover {
            color: #fff;
            background-color: #74788d;
        }

        .role-matrix-table td {
            vertical-align: middle !important;
            padding: 0.85rem 0.75rem !important;
        }

        .role-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: rgba(91, 115, 232, 0.12);
            color: #5b73e8;
        }
    </style>

    <div class="container-fluid">
        <!-- start page title -->
        <?php
        $maintitle = "Ana Sayfa";
        $title = "Rol & Yetki Matrisi";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 bg-white border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="role-icon-box">
                                <i class="bx bx-shield-quarter"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1 fw-bold text-dark font-size-16">Rol & Yetki Matrisi Listesi</h5>
                                <p class="card-title-desc mb-0 text-muted font-size-12">
                                    Tanımlı kullanıcı rolleri, izin dağılımları ve atanan personeller
                                </p>
                            </div>
                        </div>

                        <div>
                            <?php if (Gate::allows("yetki_gruplari")) { ?>
                                <button type="button" id="groupAddBtn" data-bs-toggle="modal" data-bs-target="#groupModal"
                                    class="btn btn-success waves-effect btn-label waves-light">
                                    <i class="bx bx-plus label-icon"></i> Yeni Ekle
                                </button>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="card-body overflow-auto p-3">
                        <table id="groupsTable" class="datatable table table-hover align-middle nowrap w-100 role-matrix-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 5%;" data-filter="string"># SIRA</th>
                                    <th style="width: 20%;" data-filter="string">POZİSYON / ROL ADI</th>
                                    <th style="width: 25%;" data-filter="string">AÇIKLAMA</th>
                                    <th class="text-center" style="width: 15%;" data-filter="string">ATANAN PERSONEL</th>
                                    <th style="width: 25%;" data-filter="string">YETKİ DAĞILIMI & KAPSAM</th>
                                    <?php if (Gate::allows("yetki_gruplari")) { ?>
                                        <th class="text-center no-sort" style="width: 10%;">İŞLEM</th>
                                    <?php } ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $i = 0;
                                foreach ($usergroups as $group) {
                                    $i++;
                                    $enc_id = Security::encrypt($group->id);
                                    $userCount = (int) ($group->assigned_user_count ?? 0);
                                    $permCount = (int) ($group->permission_count ?? 0);
                                    $totalPerms = (int) ($group->total_permissions ?? 0);
                                    $percent = (int) ($group->permission_percent ?? 0);
                                    $roleColor = $group->role_color ?? 'primary';
                                    ?>
                                    <tr data-id="<?= $enc_id ?>">
                                        <!-- 1. Sıra / #ID -->
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark font-size-12 px-2 py-1 border">#<?= $i ?></span>
                                        </td>

                                        <!-- 2. Pozisyon / Rol Adı -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="fw-bold text-dark font-size-13 role-summary-trigger" 
                                                      data-id="<?= $enc_id ?>" 
                                                      data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>" 
                                                      style="cursor: pointer;"
                                                      title="Yetki Detaylarını Görüntüle">
                                                    <?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <span class="badge bg-<?= $roleColor ?> font-size-10 text-uppercase px-2 py-1">
                                                    <?= htmlspecialchars(mb_strtoupper($group->role_name, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- 3. Açıklama -->
                                        <td>
                                            <span class="text-muted font-size-12 d-inline-block text-truncate" style="max-width: 280px;" title="<?= htmlspecialchars($group->description ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($group->description ?: 'Açıklama belirtilmemiş', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>

                                        <!-- 4. Atanan Personel -->
                                        <td class="text-center">
                                            <?php if ($userCount > 0): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-soft-success rounded-pill px-3 py-1 fw-semibold show-assigned-users d-inline-flex align-items-center gap-1 shadow-none" 
                                                        data-id="<?= $enc_id ?>" 
                                                        data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>"
                                                        data-color="<?= $roleColor ?>"
                                                        title="Bu yetkiye sahip <?= $userCount ?> kullanıcıyı listele">
                                                    <i class="mdi mdi-account-multiple font-size-14"></i>
                                                    <span><?= $userCount ?> Kullanıcı</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-soft-secondary text-secondary rounded-pill px-3 py-1 font-size-11 fw-normal d-inline-flex align-items-center gap-1">
                                                    <i class="mdi mdi-account-off-outline font-size-13"></i>
                                                    <span>Atama Yok</span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 5. Yetki Dağılımı & Kapsam -->
                                        <td>
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="font-size-12 fw-semibold text-dark">
                                                    <i class="mdi mdi-check-circle text-primary me-1"></i><?= $permCount ?> / <?= $totalPerms ?> İzin
                                                </span>
                                                <span class="badge bg-soft-primary text-primary font-size-11 fw-bold">%<?= $percent ?></span>
                                            </div>
                                            <div class="progress progress-sm mb-2" style="height: 6px; border-radius: 4px; background: #eef2f7;">
                                                <div class="progress-bar bg-gradient-custom" role="progressbar" 
                                                     style="width: <?= $percent ?>%;" 
                                                     aria-valuenow="<?= $percent ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-1">
                                                <?php if (!empty($group->sample_permissions)): ?>
                                                    <?php foreach ($group->sample_permissions as $samplePerm): ?>
                                                        <span class="badge bg-light text-secondary border font-size-11 fw-normal px-2 py-1">
                                                            <?= htmlspecialchars($samplePerm, ENT_QUOTES, 'UTF-8') ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php 
                                                    $remainingPerms = $permCount - count($group->sample_permissions);
                                                    if ($remainingPerms > 0): ?>
                                                        <span class="badge bg-soft-purple text-purple font-size-11 fw-semibold px-2 py-1 role-summary-trigger" 
                                                              data-id="<?= $enc_id ?>" 
                                                              data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>" 
                                                              style="cursor: pointer;" 
                                                              title="Tüm izinleri görüntüle">
                                                            +<?= $remainingPerms ?> daha
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted font-size-11 fst-italic">İzin atanmamış</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- 6. İşlemler -->
                                        <?php if (Gate::allows("yetki_gruplari")) { ?>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center gap-1">
                                                    <!-- Görüntüle (Göz) -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-primary role-summary-trigger p-1 px-2" 
                                                            data-id="<?= $enc_id ?>" 
                                                            data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>" 
                                                            title="Yetki Detaylarını Görüntüle">
                                                        <i class="mdi mdi-eye-outline font-size-16"></i>
                                                    </button>

                                                    <!-- Yetkileri Düzenle (Matris/Yetki Sayfası) -->
                                                    <a href="index?p=kullanici-gruplari/duzenle&id=<?= $enc_id ?>" 
                                                       class="btn btn-sm btn-soft-info p-1 px-2" 
                                                       title="Yetkileri Düzenle">
                                                        <i class="mdi mdi-shield-edit-outline font-size-16"></i>
                                                    </a>

                                                    <!-- Grup Adı/Rengi Düzenle -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-secondary kullanici-duzenle p-1 px-2" 
                                                            data-id="<?= $enc_id ?>" 
                                                            title="Grup Bilgilerini Düzenle">
                                                        <i class="mdi mdi-pencil-outline font-size-16"></i>
                                                    </button>

                                                    <!-- Yetkileri Kopyala -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-warning yetki-kopyala p-1 px-2" 
                                                            data-id="<?= $enc_id ?>" 
                                                            data-raw-id="<?= $group->id ?>" 
                                                            data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>" 
                                                            title="Yetkileri Kopyala">
                                                        <i class="mdi mdi-content-copy font-size-16"></i>
                                                    </button>

                                                    <!-- Sil -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-soft-danger kullanici-sil p-1 px-2" 
                                                            data-id="<?= $enc_id ?>" 
                                                            data-name="<?= htmlspecialchars($group->role_name, ENT_QUOTES, 'UTF-8') ?>" 
                                                            title="Yetki Grubunu Sil">
                                                        <i class="mdi mdi-trash-can-outline font-size-16"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> <!-- end col -->
        </div> <!-- end row -->
    </div> <!-- container-fluid -->

    <!-- Grup Ekle/Düzenle Modal -->
    <div class="modal fade" id="groupModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content group-modal-content">
                <!-- Modern spinner -->
                <div class="d-flex justify-content-center align-items-center" style="height: 300px;">
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
    <div class="modal fade" id="copyPermissionsModal" tabindex="-1" aria-labelledby="copyPermissionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title font-size-16 fw-bold" id="copyPermissionsModalLabel">
                        <i class="mdi mdi-content-copy me-2 text-primary"></i>Yetkileri Kopyala
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="copyPermissionsForm">
                        <input type="hidden" name="target_role_id" id="target_role_id">
                        <div class="alert alert-info">
                            <strong id="target_role_name"></strong> grubuna hangi gruptan yetkileri kopyalamak istiyorsunuz?
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Kaynak Yetki Grubu</label>
                            <select class="form-select select2" name="source_role_id" id="source_role_id" style="width: 100%;">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($usergroups as $srcGroup) { ?>
                                    <option value="<?php echo Security::encrypt($srcGroup->id); ?>"
                                        data-raw-id="<?php echo $srcGroup->id; ?>">
                                        <?php echo htmlspecialchars($srcGroup->role_name, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="btnCopyPermissions" class="btn btn-primary">
                        <i class="mdi mdi-content-copy me-1"></i>Kopyala
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Yetki Özet Modalı -->
    <div class="modal fade" id="roleSummaryModal" tabindex="-1" aria-labelledby="roleSummaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white py-3">
                    <div>
                        <h5 class="modal-title text-white font-size-16 fw-bold mb-0" id="roleSummaryModalLabel">
                            <i class="mdi mdi-shield-check me-2"></i>Yetki Grubu İzinleri
                        </h5>
                        <p class="text-white-50 small mb-0 mt-1" id="roleSummaryModalDescription" style="display: none;"></p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3" style="max-height: 70vh;">
                    <div id="roleSummaryContent">
                        <!-- Yetkiler dinamik yüklenecek -->
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Atanan Kullanıcılar Modalı -->
    <div class="modal fade" id="assignedUsersModal" tabindex="-1" aria-labelledby="assignedUsersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light py-3 border-bottom">
                    <div>
                        <h5 class="modal-title font-size-16 fw-bold mb-0 text-dark" id="assignedUsersModalLabel">
                            <i class="mdi mdi-account-group-outline me-2 text-primary"></i>Atanan Kullanıcılar
                        </h5>
                        <p class="text-muted small mb-0 mt-1" id="assignedUsersModalSub">Bu yetki grubuna sahip personellerin listesi</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="assignedUsersContent">
                        <!-- Kullanıcılar dinamik yüklenecek -->
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

<?php } else {
    Alert::danger("Bu yetkiye sahip değilsiniz.");
}
?>