<?php

require_once "vendor/autoload.php";

use App\Helper\Security;
use App\Service\Gate;
use App\Model\MenuManagementModel;

// Strict SuperAdmin Authorization Check
if (!Gate::isSuperAdmin()) {
    Gate::authorizeOrDie("superadmin", "Menü yönetim sayfasına yalnızca Superadmin yetkisine sahip kullanıcılar erişebilir.");
}

$menuModel = new MenuManagementModel();
$parents = $menuModel->getParentMenus();
$groups = $menuModel->getGroupNames();

$maintitle = "Ana Sayfa";
$title = "Menü Yönetimi";
?>

<div class="container-fluid">
    <!-- start page title -->
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <style>
                    /* Premium Filter Buttons matched with Personel List */
                    .status-filter-group {
                        background: #f8fafc;
                        padding: 4px;
                        border-radius: 50px;
                        border: 1px solid #e2e8f0;
                        display: inline-flex;
                        align-items: center;
                        gap: 2px;
                    }

                    .status-filter-group .btn-check + .btn {
                        margin-bottom: 0 !important;
                        border: none !important;
                        border-radius: 50px !important;
                        font-size: 0.75rem;
                        font-weight: 600;
                        padding: 6px 16px;
                        color: #64748b;
                        transition: all 0.2s ease;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 6px;
                        line-height: normal;
                    }

                    .status-filter-group .btn-check:checked + .btn[for="filter-all"] { background: #64748b !important; color: #fff !important; }
                    .status-filter-group .btn-check:checked + .btn[for="filter-aktif"] { background: #34c38f !important; color: #fff !important; }
                    .status-filter-group .btn-check:checked + .btn[for="filter-pasif"] { background: #ef4444 !important; color: #fff !important; }

                    .status-filter-group .count-tag {
                        background: rgba(255,255,255,0.2);
                        padding: 2px 8px;
                        border-radius: 10px;
                        font-size: 11px;
                    }

                    .status-filter-group .btn-check:not(:checked) + .btn .count-tag {
                        background: rgba(0,0,0,0.05);
                        color: #64748b;
                    }

                    [data-bs-theme="dark"] .status-filter-group {
                        background: #2a3042 !important;
                        border-color: #32394e !important;
                    }

                    [data-bs-theme="dark"] .status-filter-group .btn-check + .btn {
                        color: #94a3b8 !important;
                    }

                    [data-bs-theme="dark"] .personel-action-toolbar {
                        background-color: #2a3042 !important;
                        border-color: #32394e !important;
                    }

                    #menuTable {
                        opacity: 1;
                    }
                </style>

                <div class="card-body overflow-auto">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                        <div class="d-flex gap-3 align-items-center flex-wrap">
                            <div class="status-filter-group" role="group">
                                <input type="radio" class="btn-check" name="status-filter" id="filter-all" value="">
                                <label class="btn" for="filter-all">
                                    <i class="bx bx-grid-alt"></i> Tümü 
                                    <span class="count-tag ms-1" id="count-all">0</span>
                                </label>
                                
                                <input type="radio" class="btn-check" name="status-filter" id="filter-aktif" value="Aktif" checked>
                                <label class="btn" for="filter-aktif">
                                    <i class="bx bx-user-check"></i> Aktif 
                                    <span class="count-tag ms-1" id="count-aktif">0</span>
                                </label>

                                <input type="radio" class="btn-check" name="status-filter" id="filter-pasif" value="Pasif">
                                <label class="btn" for="filter-pasif">
                                    <i class="bx bx-user-x"></i> Pasif 
                                    <span class="count-tag ms-1" id="count-pasif">0</span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 personel-action-toolbar">
                            <button type="button" id="btnAddNewMenu" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center fw-semibold">
                                <i class="mdi mdi-plus-circle fs-5 me-1"></i> Yeni Menü
                            </button>
                        </div>
                    </div>

                    <div class="responsive" style="overflow-x: auto !important;">
                        <table id="menuTable" class="table table-bordered nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 40px;">#</th>
                                    <th data-filter="string">MENÜ ADI</th>
                                    <th data-filter="select">ÜST MENÜ</th>
                                    <th data-filter="select">GRUP ADI</th>
                                    <th data-filter="string">SAYFA BAĞLANTISI (LİNK)</th>
                                    <th class="text-center">İKON</th>
                                    <th class="text-center" style="width: 70px;">SIRA</th>
                                    <th class="text-center" data-filter="select" style="width: 100px;">DURUM</th>
                                    <th class="text-center" style="width: 90px;">İŞLEM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded dynamically via DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Menü Ekle / Düzenle Modal -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title text-white fw-bold" id="menuModalLabel">
                    <i class="feather feather-edit me-2"></i>Yeni Menü Ekle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="menuForm" autocomplete="off">
                <input type="hidden" name="id" id="menu_id" value="">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Menü Adı -->
                        <div class="col-md-6">
                            <label for="menu_name" class="form-label fw-semibold">Menü Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="menu_name" name="menu_name" required placeholder="Örn: Personel İşlemleri">
                        </div>

                        <!-- Sayfa Açıklaması -->
                        <div class="col-md-6">
                            <label for="page_description" class="form-label fw-semibold">Sayfa Açıklaması</label>
                            <input type="text" class="form-control" id="page_description" name="page_description" placeholder="Top-bar altında görünecek açıklama">
                        </div>

                        <!-- Üst Menü -->
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label fw-semibold">Üst Menü</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="0">Ana Menü (Üst Menü Yok)</option>
                                <?php foreach ($parents as $p): ?>
                                    <option value="<?php echo (int)$p->id; ?>"><?php echo htmlspecialchars($p->menu_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Grup Adı -->
                        <div class="col-md-6">
                            <label for="group_name" class="form-label fw-semibold">Grup Adı</label>
                            <input type="text" class="form-control" id="group_name" name="group_name" list="group_list" placeholder="Örn: Yönetim">
                            <datalist id="group_list">
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Sayfa Bağlantısı (Link) -->
                        <div class="col-md-6">
                            <label for="menu_link" class="form-label fw-semibold">Sayfa Bağlantısı (Link)</label>
                            <input type="text" class="form-control" id="menu_link" name="menu_link" placeholder="Örn: personel/list">
                        </div>

                        <!-- İkon -->
                        <div class="col-md-6">
                            <label for="menu_icon" class="form-label fw-semibold">Feather / BoxIcon İkon Adı</label>
                            <div class="input-group">
                                <span class="input-group-text" id="iconPreview"><i class="feather feather-help-circle"></i></span>
                                <input type="text" class="form-control" id="menu_icon" name="menu_icon" placeholder="Örn: users, settings, sliders">
                            </div>
                        </div>

                        <!-- Menü Sırası & Grup Sırası -->
                        <div class="col-md-3">
                            <label for="menu_order" class="form-label fw-semibold">Menü Sırası</label>
                            <input type="number" class="form-control" id="menu_order" name="menu_order" value="1" min="1">
                        </div>

                        <div class="col-md-3">
                            <label for="group_order" class="form-label fw-semibold">Grup Sırası</label>
                            <input type="number" class="form-control" id="group_order" name="group_order" value="1" min="1">
                        </div>

                        <!-- Durum & Gösterim Seçenekleri -->
                        <div class="col-md-6">
                            <label for="is_active" class="form-label fw-semibold">Durum</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Pasif</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="is_menu" class="form-label fw-semibold">Menüde Görünsün mü?</label>
                            <select class="form-select" id="is_menu" name="is_menu">
                                <option value="1">Evet</option>
                                <option value="0">Hayır (Gizli Rota)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="is_authorized" class="form-label fw-semibold">Yetki Kontrolü Olsun mu?</label>
                            <select class="form-select" id="is_authorized" name="is_authorized">
                                <option value="1">Evet (Rol Yetkisi Gerekir)</option>
                                <option value="0">Hayır (Herkese Açık)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="btnSaveMenu" class="btn btn-primary waves-effect waves-light">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
