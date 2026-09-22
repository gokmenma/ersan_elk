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

$maintitle = "Yönetim";
$title = "Menü Yönetimi";
?>

<div class="container-fluid">
    <!-- start page title -->
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 rounded-3">
                <style>
                    /* Minimal Tree & Card Styles */
                    .menu-tree-container {
                        min-height: 150px;
                        display: flex;
                        flex-direction: column;
                        gap: 6px;
                    }

                    .menu-item-card {
                        background: #ffffff;
                        border: 1px solid #edf2f7;
                        border-radius: 6px;
                        transition: all 0.15s ease;
                        position: relative;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
                    }

                    .menu-item-card:hover {
                        border-color: #cbd5e1;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
                    }

                    .menu-item-card.is-parent {
                        border-left: 3px solid #3b82f6 !important;
                        margin-left: 0;
                    }

                    .menu-item-card.is-child {
                        border-left: 3px solid #8b5cf6 !important;
                        margin-left: 32px;
                        background-color: #fcfcfd;
                    }

                    @media (max-width: 768px) {
                        .menu-item-card.is-child {
                            margin-left: 16px;
                        }
                    }

                    .menu-item-header {
                        padding: 8px 12px;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 10px;
                        cursor: pointer;
                        user-select: none;
                    }

                    .menu-drag-handle {
                        cursor: grab;
                        color: #cbd5e1;
                        padding: 2px 4px;
                        display: flex;
                        align-items: center;
                        transition: color 0.15s;
                    }

                    .menu-drag-handle:hover {
                        color: #64748b;
                    }

                    .menu-drag-handle:active {
                        cursor: grabbing;
                    }

                    .menu-icon-box {
                        width: 28px;
                        height: 28px;
                        border-radius: 6px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 14px;
                        flex-shrink: 0;
                    }

                    .menu-icon-box.parent-icon {
                        background-color: #eff6ff;
                        color: #2563eb;
                    }

                    .menu-icon-box.child-icon {
                        background-color: #f5f3ff;
                        color: #7c3aed;
                    }

                    /* Minimal High-Contrast Tags */
                    .menu-tag {
                        display: inline-flex;
                        align-items: center;
                        font-size: 10.5px;
                        font-weight: 600;
                        line-height: 1;
                        padding: 3px 6px;
                        border-radius: 4px;
                        white-space: nowrap;
                    }

                    .menu-tag-parent {
                        background-color: #f1f5f9 !important;
                        color: #334155 !important;
                        border: 1px solid #e2e8f0 !important;
                    }

                    .menu-tag-child {
                        background-color: #f5f3ff !important;
                        color: #6b21a8 !important;
                        border: 1px solid #ede9fe !important;
                    }

                    .menu-tag-group {
                        background-color: #eff6ff !important;
                        color: #1d4ed8 !important;
                        border: 1px solid #dbeafe !important;
                    }

                    /* Ghost Action Buttons */
                    .menu-action-btn-group {
                        display: flex;
                        align-items: center;
                        gap: 2px;
                    }

                    .menu-action-btn-group .btn-ghost {
                        width: 28px;
                        height: 28px;
                        padding: 0;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                        border: none;
                        background: transparent;
                        color: #94a3b8;
                        transition: all 0.15s ease;
                    }

                    .menu-action-btn-group .btn-ghost:hover {
                        background: #f1f5f9;
                        color: #1e293b;
                    }

                    .menu-action-btn-group .btn-indent:hover {
                        color: #2563eb !important;
                        background: #eff6ff !important;
                    }

                    .menu-action-btn-group .btn-outdent:hover {
                        color: #7c3aed !important;
                        background: #f5f3ff !important;
                    }

                    .menu-action-btn-group .btn-delete:hover {
                        color: #dc2626 !important;
                        background: #fef2f2 !important;
                    }

                    .menu-item-body {
                        display: none;
                        padding: 12px 16px;
                        background: #f8fafc;
                        border-top: 1px solid #edf2f7;
                        border-bottom-left-radius: 6px;
                        border-bottom-right-radius: 6px;
                    }

                    .sortable-ghost {
                        opacity: 0.3;
                        background: #f1f5f9 !important;
                        border: 1.5px dashed #94a3b8 !important;
                    }

                    .sortable-chosen {
                        box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
                    }

                    /* Dark Mode Support */
                    [data-bs-theme="dark"] .menu-item-card {
                        background: #2a3042;
                        border-color: #32394e;
                    }
                    [data-bs-theme="dark"] .menu-item-card.is-child {
                        background-color: #262b3c;
                    }
                    [data-bs-theme="dark"] .menu-item-body {
                        background: #222736;
                        border-top-color: #32394e;
                    }
                    [data-bs-theme="dark"] .menu-action-btn-group .btn-ghost:hover {
                        background: #32394e;
                        color: #f1f5f9;
                    }
                    [data-bs-theme="dark"] .menu-tag-parent {
                        background-color: #334155 !important;
                        color: #f1f5f9 !important;
                        border-color: #475569 !important;
                    }
                    [data-bs-theme="dark"] .menu-tag-child {
                        background-color: #3b2d54 !important;
                        color: #e9d5ff !important;
                        border-color: #6b21a8 !important;
                    }
                    [data-bs-theme="dark"] .menu-tag-group {
                        background-color: #1e3a8a !important;
                        color: #bfdbfe !important;
                        border-color: #2563eb !important;
                    }
                </style>

                <div class="card-body p-3 p-md-4">
                    <!-- Minimal Top Toolbar -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="mb-0 fw-bold text-dark fs-15 d-flex align-items-center">
                                <i class="bx bx-menu-alt-left text-primary me-1 fs-4"></i>Menü Hiyerarşisi
                            </h5>
                            <span class="badge bg-light text-secondary border px-2 py-1 fs-11 fw-semibold" id="badgeTotalItems">0 Menü</span>

                            <!-- Group Filter Select -->
                            <div class="ms-2" style="min-width: 160px;">
                                <select id="filterGroupSelect" class="form-select form-select-sm shadow-none rounded-2" style="font-size: 12px;">
                                    <option value="">Tüm Gruplar</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" id="btnToggleAllAccordions" class="btn btn-sm btn-light border text-muted px-2 py-1 fs-12 fw-medium">
                                <i class="bx bx-expand-vertical me-1"></i> Tümünü Aç
                            </button>

                            <button type="button" id="btnResetDefaults" class="btn btn-sm btn-outline-danger px-2 py-1 fs-12 fw-medium" title="Tüm hiyerarşiyi varsayılan sistem ayarlarına sıfırlar">
                                <i class="bx bx-reset me-1"></i> Varsayılana Dön
                            </button>

                            <button type="button" id="btnAddNewMenu" class="btn btn-sm btn-success px-3 py-1 fs-12 fw-semibold shadow-sm">
                                <i class="bx bx-plus me-1"></i> Yeni Menü
                            </button>
                        </div>
                    </div>

                    <!-- Minimal Tip Box -->
                    <div class="alert alert-info border-0 d-flex align-items-center py-2 px-3 mb-3 rounded-2" style="background-color: #f0f7ff; color: #1e40af; border-left: 3px solid #3b82f6 !important;">
                        <i class="feather feather-info text-primary fs-6 me-2 flex-shrink-0"></i>
                        <div class="fs-12">
                            <strong>İpucu:</strong> Menüleri sürükleyerek sıralayabilir; <i class="bx bx-right-arrow-alt text-primary fw-bold"></i> butonu ile alt menü, <i class="bx bx-left-arrow-alt text-purple fw-bold"></i> ile ana menü yapabilirsiniz.
                        </div>
                    </div>

                    <!-- Dynamic Menu Tree Container -->
                    <div id="menuTreeLoading" class="text-center py-4">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="text-muted ms-2 fs-12">Menü yapısı yükleniyor...</span>
                    </div>

                    <div id="menuTreeContainer" class="menu-tree-container" style="display: none;">
                        <!-- Rendered via JS -->
                    </div>

                    <div id="menuTreeEmpty" class="text-center py-4" style="display: none;">
                        <i class="bx bx-folder-open display-5 text-muted"></i>
                        <p class="text-muted mt-2 fs-12">Bu grupta görüntülenecek menü bulunamadı.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Yeni Menü Ekle -->
<div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title text-white fw-bold" id="menuModalLabel">
                    <i class="feather feather-plus-circle me-1"></i>Yeni Menü Ekle
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="menuForm" autocomplete="off">
                <input type="hidden" name="id" id="modal_menu_id" value="">
                <div class="modal-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "menu_name", "", "Örn: Personel Listesi", "Menü Adı", "menu", "form-control", true) ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "menu_link", "", "Örn: personel/list", "Sayfa Bağlantısı (Link)", "link") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormSelect2("parent_id", $parentOptions, 0, "Üst Menü", "corner-down-right", "key", "", "form-select") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "group_name", "Yönetim", "Örn: Yönetim", "Grup Adı", "grid", "form-control", false, null, "on", false, 'list="modal_group_list"') ?>
                            <datalist id="modal_group_list">
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "menu_icon", "", "Örn: users, settings, home", "İkon Adı (Feather/BoxIcon)", "help-circle", "form-control", false, null, "on", false, '', false, 'modalIconPreview') ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "page_description", "", "Top-bar altında görünecek açıklama", "Sayfa Açıklaması", "file-text") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormSelect2("is_menu", [1 => 'Evet (Menüde Göster)', 0 => 'Hayır (Gizli Rota)'], 1, "Menüde Görünsün mü?", "eye", "key", "", "form-select") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormSelect2("is_authorized", [1 => 'Evet (Yetki Kontrolü Var)', 0 => 'Hayır (Açık)'], 1, "Yetki Kontrolü", "shield", "key", "", "form-select") ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-secondary btn-sm waves-effect" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="btnSaveModalMenu" class="btn btn-primary btn-sm waves-effect waves-light px-3">
                        <i class="bx bx-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
