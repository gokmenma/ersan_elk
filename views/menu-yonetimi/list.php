<?php

require_once "vendor/autoload.php";

use App\Helper\Security;
use App\Service\Gate;
use App\Model\MenuManagementModel;
use App\Helper\Form;

// Strict SuperAdmin Authorization Check
if (!Gate::isSuperAdmin()) {
    Gate::authorizeOrDie("superadmin", "Menü yönetim sayfasına yalnızca Superadmin yetkisine sahip kullanıcılar erişebilir.");
}

$menuModel = new MenuManagementModel();
$parents = $menuModel->getParentMenus();
$groups = $menuModel->getGroupNames();

$parentOptions = [0 => 'Ana Menü (Üst Menü Yok)'];
foreach ($parents as $p) {
    $parentOptions[$p->id] = $p->menu_name;
}

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

                    /* High contrast soft badges for menu table */
                    .badge-soft-menu-parent {
                        background-color: #f1f5f9 !important;
                        color: #475569 !important;
                        border: 1px solid #cbd5e1 !important;
                        font-weight: 600;
                        padding: 4px 10px;
                        font-size: 11px;
                    }
                    .badge-soft-menu-sub {
                        background-color: #e0f2fe !important;
                        color: #0369a1 !important;
                        border: 1px solid #bae6fd !important;
                        font-weight: 600;
                        padding: 4px 10px;
                        font-size: 11px;
                    }
                    .badge-soft-menu-group {
                        background-color: #e0e7ff !important;
                        color: #3730a3 !important;
                        border: 1px solid #c7d2fe !important;
                        font-weight: 600;
                        padding: 4px 10px;
                        font-size: 11px;
                    }

                    [data-bs-theme="dark"] .badge-soft-menu-parent {
                        background-color: #334155 !important;
                        color: #cbd5e1 !important;
                        border-color: #475569 !important;
                    }
                    [data-bs-theme="dark"] .badge-soft-menu-sub {
                        background-color: #075985 !important;
                        color: #e0f2fe !important;
                        border-color: #0284c7 !important;
                    }
                    [data-bs-theme="dark"] .badge-soft-menu-group {
                        background-color: #3730a3 !important;
                        color: #e0e7ff !important;
                        border-color: #4338ca !important;
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
                                    <th class="text-center" data-filter="string">İKON</th>
                                    <th class="text-center" data-filter="number" style="width: 70px;">SIRA</th>
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
                            <?= Form::FormFloatInput("text", "menu_name", "", "Örn: Personel İşlemleri", "Menü Adı", "menu", "form-control", true) ?>
                        </div>

                        <!-- Sayfa Açıklaması -->
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "page_description", "", "Top-bar altında görünecek açıklama", "Sayfa Açıklaması", "file-text") ?>
                        </div>

                        <!-- Üst Menü -->
                        <div class="col-md-6">
                            <?= Form::FormSelect2("parent_id", $parentOptions, 0, "Üst Menü", "corner-down-right", "key", "", "form-select") ?>
                        </div>

                        <!-- Grup Adı -->
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "group_name", "", "Örn: Yönetim", "Grup Adı", "grid", "form-control", false, null, "on", false, 'list="group_list"') ?>
                            <datalist id="group_list">
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <!-- Sayfa Bağlantısı (Link) -->
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "menu_link", "", "Örn: personel/list", "Sayfa Bağlantısı (Link)", "link") ?>
                        </div>

                        <!-- İkon -->
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "menu_icon", "", "Örn: users, settings, sliders", "Feather / BoxIcon İkon Adı", "help-circle", "form-control", false, null, "on", false, '', false, 'iconPreview') ?>
                        </div>

                        <!-- Menü Sırası & Grup Sırası -->
                        <div class="col-md-3">
                            <?= Form::FormFloatInput("number", "menu_order", "1", "1", "Menü Sırası", "hash", "form-control", false, null, "on", false, 'min="1"') ?>
                        </div>

                        <div class="col-md-3">
                            <?= Form::FormFloatInput("number", "group_order", "1", "1", "Grup Sırası", "hash", "form-control", false, null, "on", false, 'min="1"') ?>
                        </div>

                        <!-- Durum & Gösterim Seçenekleri -->
                        <div class="col-md-6">
                            <?= Form::FormSelect2("is_active", [1 => 'Aktif', 0 => 'Pasif'], 1, "Durum", "check-circle", "key", "", "form-select") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormSelect2("is_menu", [1 => 'Evet', 0 => 'Hayır (Gizli Rota)'], 1, "Menüde Görünsün mü?", "eye", "key", "", "form-select") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormSelect2("is_authorized", [1 => 'Evet (Rol Yetkisi Gerekir)', 0 => 'Hayır (Herkese Açık)'], 1, "Yetki Kontrolü Olsun mu?", "shield", "key", "", "form-select") ?>
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
