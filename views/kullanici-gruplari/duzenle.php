<style>
    <?php require_once "style.css";
    ?>
</style>

<?php

use App\Helper\Security;
use App\Model\UserRolesModel;

$UserRoles = new UserRolesModel();


$role_id = Security::decrypt($_GET['id']) ?? 0;
$role = $UserRoles->find($role_id);


?>


<?php
$maintitle = "Ana Sayfa";
$title = "Yetki Yönetimi " . ($role ? " - ( " . $role->role_name . " )" : "");
?>
<?php include 'layouts/breadcrumb.php'; ?>
<div class="row">
    <input type="text" id="user_id" name="user_id" value="<?php echo $_GET['id'] ?? 0 ?>" hidden>
    <div class="col-12">
        <div class="card">
            <div class="card-header">


                <!-- Kaydetme Alanı -->
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span id="selectedCount" class="badge bg-primary badge-count">0</span>
                        <span class="text-muted ms-2">yetki seçildi</span>
                        <span class="text-muted ms-3 d-none d-sm-inline">(<span id="requiredCount">0</span>
                            zorunlu)</span>
                    </div>
                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                        <a href="index?p=kullanici-gruplari/list"
                            class="btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center"
                            title="Listeye Dön">
                            <i class="mdi mdi-arrow-left fs-5 me-1"></i> Geri
                        </a>
                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                        <button id="resetChanges"
                            class="btn btn-link btn-sm text-danger text-decoration-none px-2 d-flex align-items-center"
                            title="Sıfırla">
                            <i class="mdi mdi-eraser-variant fs-5 me-1"></i> Sıfırla
                        </button>
                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                        <button id="selectAllPermissions"
                            class="btn btn-link btn-sm text-info text-decoration-none px-2 d-flex align-items-center"
                            title="Tümünü Seç">
                            <i class="mdi mdi-check-all fs-5 me-1"></i> Tümünü Seç
                        </button>
                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                        <button id="savePermissions"
                            class="btn btn-primary btn-sm px-4 fw-bold shadow-primary pulsate-on-change">
                            <i class="mdi mdi-content-save-outline me-1"></i> Kaydet
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-header d-grid d-md-flex d-block">
                <i class="ti ti-search position-absolute search-icon top-50 translate-middle-y"></i>
                <input type="text" class="form-control" id="permissionSearch" placeholder="Yetki veya grup adı ara...">

            </div>

            <div class="card-body overflow-auto">


                <!-- Arama ve Filtreler -->
                <div class="position-relative mb-3">

                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <div class="col-md-9">

                        <div class="filter-chips mb-2 mb-md-0 d-flex flex-wrap gap-1" id="filterChips">

                        </div>
                    </div>
                    <div class="col-md-3 d-flex justify-content-end">

                            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 mb-3">
                                <button class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center" id="selectHighlighted">
                                    <i class="mdi mdi-checkbox-multiple-marked-outline fs-5 me-1"></i> Arama Sonuçlarını Seç
                                </button>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <div class="form-check form-switch ms-1">
                                    <input class="form-check-input" type="checkbox" id="showTreeView">
                                    <label class="form-check-label small fw-bold ms-1" for="showTreeView" style="font-size: 11px;">AĞAÇ GÖRÜNÜMÜ</label>
                                </div>
                            </div>
                    </div>
                </div>

                <!-- Yetki Grupları (Kart Görünümü) -->
                <div id="cardViewContainer">
                    <div id="permissionContainer" class="mb-4">
                        <!-- Dinamik olarak yüklenecek -->
                    </div>

                    <!-- Yükleme Skeleton -->
                    <div id="loadingSkeleton" style="display: none;">
                        <div class="permission-group loading mb-3">
                            <div class="group-header placeholder-glow">
                                <div class="d-flex align-items-center w-100">
                                    <div class="permission-icon placeholder me-3"></div>
                                    <div class="flex-grow-1"><span class="placeholder col-6"></span></div>
                                    <span class="placeholder col-2"></span>
                                </div>
                            </div>
                        </div>
                        <div class="permission-group loading mb-3">
                            <div class="group-header placeholder-glow">
                                <div class="d-flex align-items-center w-100">
                                    <div class="permission-icon placeholder me-3"></div>
                                    <div class="flex-grow-1"><span class="placeholder col-7"></span></div>
                                    <span class="placeholder col-2"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ağaç Görünümü -->
                <div id="treeViewContainer" class="permission-tree mb-4" style="display: none;">
                    <!-- Ağaç yapısı buraya yüklenecek -->
                </div>

                <div class="toast-container"></div>


            </div>
        </div>
    </div>
</div>