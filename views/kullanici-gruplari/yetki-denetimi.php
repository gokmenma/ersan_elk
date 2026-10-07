<?php

require_once 'vendor/autoload.php';

use App\Helper\Alert;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\PermissionAuditModel;
use App\Service\Gate;

if (!Gate::isSuperAdmin()) {
    Alert::danger('Bu sayfa yalnızca Superadmin tarafından kullanılabilir.');
    return;
}

$auditModel = new PermissionAuditModel();
$subjects = $auditModel->getSubjects((int) ($_SESSION['owner_id'] ?? 0));
$roleOptions = ['' => 'Yetki grubu seçiniz...'];
$userOptions = ['' => 'Kullanıcı seçiniz...'];
foreach ($subjects['roles'] as $role) {
    $roleOptions[Security::encrypt($role->id)] = $role->role_name;
}
foreach ($subjects['users'] as $user) {
    $label = trim((string) $user->adi_soyadi) ?: (string) $user->user_name;
    $userOptions[Security::encrypt($user->id)] = $label . ' (@' . $user->user_name . ')';
}
?>
<script>window.permissionAuditCsrf = <?= json_encode(Security::csrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>try { document.documentElement.classList.toggle('permission-audit-summary-hidden', localStorage.getItem('permission_audit_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
    #summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
    .permission-audit-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
    .audit-kpi { min-height: 112px; border: 1px solid var(--bs-border-color); border-radius: 12px; background: var(--bs-body-bg); }
    .audit-kpi-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; }
    .audit-kpi-value { font-size: 24px; line-height: 1; font-weight: 700; }
    .audit-table td { vertical-align: middle; }
    .audit-code { max-width: 340px; white-space: normal; word-break: break-word; }
</style>

<div class="container-fluid">
    <?php $maintitle = 'Yetki Grupları'; $title = 'Yetki Denetimi'; include 'layouts/breadcrumb.php'; ?>

    <div class="row align-items-center mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-danger-subtle text-danger rounded-3 border border-danger-subtle d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
                    <i class="mdi mdi-shield-search font-size-24"></i>
                </div>
                <div><h4 class="font-size-16 fw-bold text-dark mb-1">Yetki Denetimi</h4><p class="font-size-12 text-muted mb-0">Kullanıcı ve rol gözünden menü erişimlerini salt okunur olarak denetleyin.</p></div>
            </div>
        </div>
        <div class="col-auto personel-action-toolbar">
            <a href="index?p=kullanici-gruplari/yetki-katalogu" class="btn btn-outline-primary bg-white top-action-btn shadow-sm"><i class="mdi mdi-shield-key-outline me-1"></i>Yetki Kataloğu</a>
            <a href="index?p=kullanici-gruplari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm"><i class="mdi mdi-arrow-left me-1"></i>Yetki Gruplarına Dön</a>
            <button type="button" id="btnToggleSummaryCards" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" title="Özet kartlarını gizle" aria-label="Özet kartlarını gizle" aria-expanded="true"><i class="bx bx-chevron-up"></i></button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white border-bottom d-flex align-items-center gap-3 py-3">
            <div class="audit-kpi-icon bg-primary-subtle text-primary"><i class="mdi mdi-account-search-outline font-size-20"></i></div>
            <div><h5 class="font-size-14 fw-bold mb-1">Denetim Kapsamı</h5><p class="font-size-11 text-muted mb-0">Kullanıcı veya yetki grubunu seçerek erişim haritasını oluşturun.</p></div>
        </div>
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-3">
                    <?= Form::FormSelect2('audit_type', ['user' => 'Kullanıcı gözünden', 'role' => 'Yetki grubu gözünden'], 'user', 'Denetim Türü', 'eye', 'key', '', 'form-select select2') ?>
                </div>
                <div class="col-lg-6" id="auditUserWrap">
                    <?= Form::FormSelect2('audit_user', $userOptions, '', 'Kullanıcı', 'user', 'key', '', 'form-select select2') ?>
                </div>
                <div class="col-lg-6 d-none" id="auditRoleWrap">
                    <?= Form::FormSelect2('audit_role', $roleOptions, '', 'Yetki Grubu', 'users', 'key', '', 'form-select select2') ?>
                </div>
                <div class="col-lg-3 d-grid"><button type="button" id="btnRunPermissionAudit" class="btn btn-primary btn-lg"><i class="mdi mdi-shield-search me-1"></i>Denetimi Başlat</button></div>
            </div>
        </div>
    </div>

    <div id="permissionAuditEmpty" class="card shadow-sm border-0"><div class="card-body text-center py-5 text-muted"><i class="mdi mdi-account-search-outline font-size-40 text-primary d-block mb-2"></i>Bir kullanıcı veya yetki grubu seçerek denetimi başlatın.</div></div>
    <div id="permissionAuditResults" class="d-none">
        <div id="summaryCardsContainer" class="row g-3 mb-3">
            <div class="col-12 col-sm-6 col-xl-3"><div class="audit-kpi p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted font-size-11 mb-2">Toplam Rota</div><div id="auditTotalCount" class="audit-kpi-value text-primary">0</div></div><div class="audit-kpi-icon bg-primary-subtle text-primary"><i class="mdi mdi-sitemap font-size-20"></i></div></div><div class="text-muted font-size-11 mt-2">Denetime alınan aktif menü ve rotalar</div></div></div>
            <div class="col-12 col-sm-6 col-xl-3"><div class="audit-kpi p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted font-size-11 mb-2">Erişilebilir</div><div id="auditAccessibleCount" class="audit-kpi-value text-success">0</div></div><div class="audit-kpi-icon bg-success-subtle text-success"><i class="mdi mdi-shield-check font-size-20"></i></div></div><button type="button" class="btn btn-sm btn-subtle-success rounded-pill mt-2 audit-quick-filter" data-filter="accessible">Erişilebilirleri göster</button></div></div>
            <div class="col-12 col-sm-6 col-xl-3"><div class="audit-kpi p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted font-size-11 mb-2">Kapalı</div><div id="auditDeniedCount" class="audit-kpi-value text-secondary">0</div></div><div class="audit-kpi-icon bg-secondary-subtle text-secondary"><i class="mdi mdi-shield-off-outline font-size-20"></i></div></div><button type="button" class="btn btn-sm btn-light border rounded-pill mt-2 audit-quick-filter" data-filter="denied">Kapalıları göster</button></div></div>
            <div class="col-12 col-sm-6 col-xl-3"><div class="audit-kpi p-3"><div class="d-flex justify-content-between align-items-start"><div><div class="text-muted font-size-11 mb-2">Güvenlik Bulgusu</div><div id="auditFindingCount" class="audit-kpi-value text-danger">0</div></div><div class="audit-kpi-icon bg-danger-subtle text-danger"><i class="mdi mdi-alert-octagon-outline font-size-20"></i></div></div><div class="font-size-11 mt-2"><span id="auditCriticalCount" class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">0 kritik</span> <span id="auditWarningCount" class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">0 uyarı</span></div></div></div>
        </div>
        <div class="card shadow-sm border-0" id="permissionAuditListCard">
            <div class="card-header bg-white d-flex align-items-center justify-content-between gap-2 flex-wrap py-3">
                <div class="d-flex align-items-center gap-3"><div class="audit-kpi-icon bg-primary-subtle text-primary"><i class="bx bx-list-ul font-size-20"></i></div><div><h5 class="font-size-14 fw-bold mb-1" id="auditSubjectTitle">Denetim Sonucu</h5><div class="font-size-11 text-muted" id="auditRoleSummary"></div></div></div>
                <div class="btn-group btn-group-sm" id="auditFilters"><button class="btn btn-primary active" data-filter="all">Tümü</button><button class="btn btn-outline-danger" data-filter="critical">Kritik</button><button class="btn btn-outline-warning" data-filter="warning">Uyarı</button><button class="btn btn-outline-success" data-filter="accessible">Erişilebilir</button><button class="btn btn-outline-secondary" data-filter="denied">Kapalı</button></div>
            </div>
            <div class="card-body p-0"><div class="table-responsive"><table id="permissionAuditTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 audit-table"><thead class="table-light"><tr><th data-filter="string">Menü / Rota</th><th data-filter="select">Grup</th><th data-filter="string">Yetki Kodu</th><th data-filter="select">Yetkinin Kaynağı</th><th data-filter="select">Politika</th><th data-filter="select" class="text-center">Sidebar</th><th data-filter="select" class="text-center">Rota Erişimi</th><th data-filter="select">Bulgular</th></tr></thead><tbody id="auditTableBody"></tbody></table></div></div>
        </div>
    </div>
</div>
