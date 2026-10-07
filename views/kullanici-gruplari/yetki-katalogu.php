<?php

require_once 'vendor/autoload.php';

use App\Helper\Alert;
use App\Model\PermissionCatalogModel;
use App\Service\Gate;

if (!Gate::isSuperAdmin()) {
    Alert::danger('Bu sayfa yalnızca Superadmin tarafından kullanılabilir.');
    return;
}

$catalog = (new PermissionCatalogModel())->getCatalog();
$stats = $catalog['stats'];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<script>try { document.documentElement.classList.toggle('permission-catalog-summary-hidden', localStorage.getItem('permission_catalog_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
    #summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
    .permission-catalog-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
    .catalog-kpi { min-height: 112px; border: 1px solid var(--bs-border-color); border-radius: 12px; background: var(--bs-body-bg); }
    .catalog-icon { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; }
    .catalog-value { font-size: 24px; line-height: 1; font-weight: 700; }
    .catalog-code { white-space: normal; word-break: break-word; }
</style>

<div class="container-fluid">
    <?php $maintitle = 'Yetki Grupları'; $title = 'Yetki Kataloğu'; include 'layouts/breadcrumb.php'; ?>

    <div class="row align-items-center mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width:44px;height:44px;"><i class="mdi mdi-shield-key-outline font-size-24"></i></div>
                <div><h4 class="font-size-16 fw-bold text-dark mb-1">Yetki Kataloğu</h4><p class="font-size-12 text-muted mb-0">Menü–yetki eşleşmelerini ve geçişte kalan eski bağlantıları tek ekranda izleyin.</p></div>
            </div>
        </div>
        <div class="col-auto personel-action-toolbar">
            <a href="index?p=kullanici-gruplari/yetki-denetimi" class="btn btn-outline-danger bg-white top-action-btn shadow-sm"><i class="mdi mdi-shield-search me-1"></i>Yetki Denetimi</a>
            <a href="index?p=kullanici-gruplari/list" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm"><i class="mdi mdi-arrow-left me-1"></i>Yetki Grupları</a>
            <button type="button" id="btnToggleSummaryCards" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" aria-expanded="true"><i class="bx bx-chevron-up"></i></button>
        </div>
    </div>

    <?php if (!$catalog['schema_ready']): ?>
        <div class="alert alert-warning border-warning-subtle"><i class="mdi mdi-alert-outline me-1"></i>Faz 1 veritabanı scripti henüz uygulanmamış. Sistem eski eşleştirmeyle çalışmaya devam ediyor; açık eşleştirmeler script uygulandıktan sonra görünecek.</div>
    <?php endif; ?>

    <div id="summaryCardsContainer" class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><div class="catalog-kpi p-3"><div class="d-flex justify-content-between"><div><div class="text-muted font-size-11 mb-2">Toplam Yetki</div><div class="catalog-value text-primary"><?= $e($stats['total']) ?></div></div><div class="catalog-icon bg-primary-subtle text-primary"><i class="mdi mdi-key-chain font-size-20"></i></div></div><button class="btn btn-sm btn-primary rounded-pill mt-2 catalog-filter active" data-status="">Tümünü göster</button></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="catalog-kpi p-3"><div class="d-flex justify-content-between"><div><div class="text-muted font-size-11 mb-2">Açık Eşleşme</div><div class="catalog-value text-success"><?= $e($stats['explicit']) ?></div></div><div class="catalog-icon bg-success-subtle text-success"><i class="mdi mdi-link-variant font-size-20"></i></div></div><button class="btn btn-sm btn-subtle-success rounded-pill mt-2 catalog-filter" data-status="Açık eşleşme">Filtrele</button></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="catalog-kpi p-3"><div class="d-flex justify-content-between"><div><div class="text-muted font-size-11 mb-2">Eski Eşleşme</div><div class="catalog-value text-warning"><?= $e($stats['legacy']) ?></div></div><div class="catalog-icon bg-warning-subtle text-warning"><i class="mdi mdi-link-variant-off font-size-20"></i></div></div><button class="btn btn-sm btn-subtle-warning rounded-pill mt-2 catalog-filter" data-status="Eski eşleşme">Filtrele</button></div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="catalog-kpi p-3"><div class="d-flex justify-content-between"><div><div class="text-muted font-size-11 mb-2">İncelenecek Kayıt</div><div class="catalog-value text-danger"><?= $e($stats['unmapped'] + $stats['orphan']) ?></div></div><div class="catalog-icon bg-danger-subtle text-danger"><i class="mdi mdi-alert-octagon-outline font-size-20"></i></div></div><div class="font-size-11 mt-2"><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill"><?= $e($stats['unmapped']) ?> eşleşmemiş</span> <span class="badge bg-light text-secondary border rounded-pill"><?= $e($stats['orphan']) ?> menüsüz</span></div></div></div>
    </div>

    <div class="card shadow-sm border-0" id="permissionCatalogListCard">
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
            <div class="d-flex align-items-center gap-3"><div class="catalog-icon bg-primary-subtle text-primary"><i class="bx bx-list-ul font-size-20"></i></div><div><h5 class="font-size-14 fw-bold mb-1">Menü ve Yetki Eşleştirmeleri</h5><p class="font-size-11 text-muted mb-0">Menüsüz aksiyon yetkileri hata olmayabilir; API ve işlem politikaları aşamasında sınıflandırılacaktır.</p></div></div>
        </div>
        <div class="card-body p-0"><div class="table-responsive"><table id="permissionCatalogTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0"><thead class="table-light"><tr><th data-filter="select">Tür</th><th data-filter="select">Modül</th><th data-filter="string">Yetki</th><th data-filter="string">Kanonik Anahtar</th><th data-filter="string">Menü / Rota</th><th data-filter="select">Eşleşme</th><th data-filter="select">Koruma</th></tr></thead><tbody>
            <?php foreach ($catalog['rows'] as $row):
                $mappingLabels = ['explicit' => 'Açık eşleşme', 'legacy' => 'Eski eşleşme', 'unmapped' => 'Eşleşmemiş', 'orphan' => 'Menüsüz yetki'];
                $mappingClasses = ['explicit' => 'success', 'legacy' => 'warning', 'unmapped' => 'danger', 'orphan' => 'secondary'];
                $mappingLabel = $mappingLabels[$row['mapping_type']] ?? 'Bilinmiyor';
                $mappingClass = $mappingClasses[$row['mapping_type']] ?? 'secondary';
            ?><tr>
                <td><?= $e($row['record_type']) ?></td><td><?= $e($row['group_name']) ?></td>
                <td class="fw-semibold"><?= $e($row['permission_name']) ?></td><td class="catalog-code"><code><?= $e($row['permission_key']) ?></code></td>
                <td><div class="fw-semibold"><?= $e($row['menu_name']) ?></div><code class="font-size-11"><?= $e($row['menu_link']) ?></code></td>
                <td><span class="badge bg-<?= $e($mappingClass) ?>-subtle text-<?= $e($mappingClass) ?> border border-<?= $e($mappingClass) ?>-subtle rounded-pill px-2 py-1"><?= $e($mappingLabel) ?></span></td>
                <td><span class="badge <?= $row['protected'] ? 'bg-success-subtle text-success border-success-subtle' : 'bg-light text-secondary' ?> border rounded-pill px-2 py-1"><?= $row['protected'] ? 'Korumalı' : 'Genel' ?></span></td>
            </tr><?php endforeach; ?>
        </tbody></table></div></div>
    </div>
</div>
