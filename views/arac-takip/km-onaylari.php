<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;

$KmBildirim = new AracKmBildirimModel();
$pendingReports = $KmBildirim->getPendingReports();
$approvedReports = $KmBildirim->getReportsByStatus('onaylandi');
$rejectedReports = $KmBildirim->getReportsByStatus('reddedildi');
?>

<style>
    /* Premium Filter Buttons Styles */
    .status-filter-group {
        background: #f8fafc; padding: 4px; border-radius: 50px;
        border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 2px;
    }
    [data-bs-theme="dark"] .status-filter-group { background: #2a3042; border-color: #32394e; }
    
    .status-filter-group .nav-link {
        border: none !important;
        border-radius: 100px !important;
        font-size: 0.75rem; font-weight: 600; padding: 8px 20px; color: #64748b;
        transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; line-height: normal;
    }
    
    [data-bs-theme="dark"] .status-filter-group .nav-link { color: #a6b0cf; }
    .status-filter-group .nav-link:hover { background: rgba(0, 0, 0, 0.04); color: #1e293b; }
    [data-bs-theme="dark"] .status-filter-group .nav-link:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
    
    .status-filter-group .nav-link.active { 
        border-radius: 100px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
        color: white !important;
    }
    
    .status-filter-group .nav-link.active[href="#pending"] { background: #3b82f6 !important; }
    .status-filter-group .nav-link.active[href="#approved"] { background: #10b981 !important; }
    .status-filter-group .nav-link.active[href="#rejected"] { background: #ef4444 !important; }
    .status-filter-group .nav-link.active[href="#unreported"] { background: #f59e0b !important; }
    
    .status-filter-group .nav-link i { font-size: 1.1rem; }
    .status-filter-group .nav-link .badge { font-size: 0.7rem; padding: 0.25em 0.6em; }

    /* Custom Checkbox Styles */
    .unreported-checkbox-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .custom-check {
        cursor: pointer;
        width: 20px;
        height: 20px;
        background: #fff;
        border: 2px solid #ccc;
        border-radius: 4px;
        position: relative;
        transition: all 0.2s;
    }
    input.unreported-checkbox:checked + .custom-check,
    input#checkAllUnreported:checked + .custom-check {
        background: #3b82f6;
        border-color: #3b82f6;
    }
    input.unreported-checkbox:checked + .custom-check::after,
    input#checkAllUnreported:checked + .custom-check::after {
        content: '\2713';
        color: white;
        font-size: 14px;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }
    .unreported-checkbox { display: none !important; }
</style>

<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">KM Bildirim Onayları</h4>

                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="arac-takip">Araç Takip</a></li>
                        <li class="breadcrumb-item active">KM Onayları</li>
                    </ol>
                </div>

            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="status-filter-group nav nav-pills" role="tablist">
                            <a class="nav-link active" data-bs-toggle="tab" href="#pending" role="tab">
                                <i class="bx bx-time"></i>
                                <span>Onay Bekleyenler</span>
                                <span class="badge bg-danger ms-1"><?= count($pendingReports) ?></span>
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" href="#approved" role="tab">
                                <i class="bx bx-check-circle"></i>
                                <span>Onaylananlar</span>
                                <span class="badge bg-success ms-1"><?= count($approvedReports) ?></span>
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" href="#rejected" role="tab">
                                <i class="bx bx-x-circle"></i>
                                <span>Reddedilenler</span>
                                <span class="badge bg-secondary ms-1"><?= count($rejectedReports) ?></span>
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" href="#unreported" role="tab" id="tabUnreportedLink">
                                <i class="bx bx-error-circle"></i>
                                <span>Bildirim Yapmayanlar</span>
                            </a>
                        </div>

                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                            <button type="button" id="btnTopluOnayla" class="btn btn-success btn-sm text-white shadow-success text-decoration-none px-2 d-none align-items-center" title="Seçilenleri Onayla">
                                <i class="bx bx-check-double fs-5 me-1"></i> <span>Toplu Onayla (<span id="selectedCount">0</span>)</span>
                            </button>
                            <div class="vr d-none" id="btnTopluOnaylaDivider" style="height: 20px; align-self: center;"></div>
                            <button type="button" id="exportExcelKm" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center" title="Excel'e Aktar">
                                <i class="bx bx-spreadsheet fs-5 me-1"></i> <span class="d-none d-xl-inline">Excel'e Aktar</span>
                            </button>
                        </div>
                    </div>

                    <div class="tab-content">
                        <!-- Onay Bekleyenler -->
                        <div class="tab-pane active" id="pending" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100 datatable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="form-check font-size-16">
                                                    <input class="form-check-input" type="checkbox" id="checkAllKm">
                                                    <label class="form-check-label" for="checkAllKm"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%" data-filter="text">Personel</th>
                                            <th style="width:15%" data-filter="text">Araç</th>
                                            <th style="width:10%" data-filter="date">Tarih</th>
                                            <th style="width:10%" data-filter="text">Kayıt Tarihi</th>
                                            <th style="width:10%" data-filter="select">Tür</th>
                                            <th style="width:10%" class="text-end">Bildirilen KM</th>
                                            <th style="width:15%">Açıklama</th>
                                            <th style="width:10%" class="text-center">Resim</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingReports as $index => $report): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <div class="form-check font-size-16">
                                                        <input class="form-check-input km-checkbox" type="checkbox" data-id="<?= $report->id ?>">
                                                        <label class="form-check-label"></label>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= $index + 1 ?></td>
                                                <td><span class="fw-bold"><?= $report->personel_adi ?></span></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= $report->plaka ?></span>
                                                    <small class="d-block text-muted"><?= $report->marka . ' ' . $report->model ?></small>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($report->tarih)) ?></td>
                                                <td><small class="fw-bold"><?= date('d.m.Y H:i', strtotime($report->olusturma_tarihi)) ?></small></td>
                                                <td>
                                                    <?php if ($report->tur === 'sabah'): ?>
                                                        <span class="badge bg-soft-warning text-warning"><i class="bx bx-sun me-1"></i> Sabah</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-soft-info text-info"><i class="bx bx-moon me-1"></i> Akşam</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold text-primary"><?= number_format($report->bitis_km, 0, ',', '.') ?> KM</td>
                                                <td><small><?= $report->aciklama ?: '-' ?></small></td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-info btn-view-km-img" 
                                                        data-img="<?= Helper::base_url($report->resim_yolu) ?>"
                                                        data-plaka="<?= $report->plaka ?>"
                                                        data-date="<?= date('d.m.Y', strtotime($report->tarih)) ?>"
                                                        data-tur="<?= ucfirst($report->tur) ?>">
                                                        <i class="bx bx-image-alt"></i>
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-success btn-km-onayla" 
                                                            data-id="<?= $report->id ?>" 
                                                            data-arac-id="<?= $report->arac_id ?>"
                                                            data-km="<?= $report->bitis_km ?>"
                                                            data-plaka="<?= $report->plaka ?>"
                                                            data-tur="<?= $report->tur ?>">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger btn-km-reddet" data-id="<?= $report->id ?>">
                                                            <i class="bx bx-x"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onaylananlar -->
                        <div class="tab-pane" id="approved" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100 datatable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Personel</th>
                                            <th style="width:15%">Araç</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%">Kayıt Tarihi</th>
                                            <th style="width:10%">Tür</th>
                                            <th style="width:10%" class="text-end">Onaylanan KM</th>
                                            <th style="width:15%">Onaylayan / Tarih</th>
                                            <th style="width:10%" class="text-center">Resim</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($approvedReports as $index => $report): ?>
                                            <tr>
                                                <td class="text-center"><?= $index + 1 ?></td>
                                                <td><span class="fw-bold"><?= $report->personel_adi ?></span></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= $report->plaka ?></span>
                                                    <small class="d-block text-muted"><?= $report->marka . ' ' . $report->model ?></small>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($report->tarih)) ?></td>
                                                <td><small class="fw-bold"><?= date('d.m.Y H:i', strtotime($report->olusturma_tarihi)) ?></small></td>
                                                <td>
                                                    <?php if ($report->tur === 'sabah'): ?>
                                                        <span class="badge bg-soft-warning text-warning"><i class="bx bx-sun me-1"></i> Sabah</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-soft-info text-info"><i class="bx bx-moon me-1"></i> Akşam</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold text-success"><?= number_format($report->bitis_km, 0, ',', '.') ?> KM</td>
                                                <td>
                                                    <span class="d-block"><?= $report->onaylayan_adi ?></span>
                                                    <small class="text-muted"><?= date('d.m.Y H:i', strtotime($report->onay_tarihi)) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-info btn-view-km-img" 
                                                        data-img="<?= Helper::base_url($report->resim_yolu) ?>"
                                                        data-plaka="<?= $report->plaka ?>"
                                                        data-date="<?= date('d.m.Y', strtotime($report->tarih)) ?>"
                                                        data-tur="<?= ucfirst($report->tur) ?>">
                                                        <i class="bx bx-image-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Reddedilenler -->
                        <div class="tab-pane" id="rejected" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100 datatable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Personel</th>
                                            <th style="width:15%">Araç</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%">Kayıt Tarihi</th>
                                            <th style="width:10%">Tür</th>
                                            <th style="width:10%" class="text-end">Bildirilen KM</th>
                                            <th style="width:15%">Red Nedeni</th>
                                            <th style="width:10%" class="text-center">Resim</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rejectedReports as $index => $report): ?>
                                            <tr>
                                                <td class="text-center"><?= $index + 1 ?></td>
                                                <td><span class="fw-bold"><?= $report->personel_adi ?></span></td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?= $report->plaka ?></span>
                                                    <small class="d-block text-muted"><?= $report->marka . ' ' . $report->model ?></small>
                                                </td>
                                                <td><?= date('d.m.Y', strtotime($report->tarih)) ?></td>
                                                <td><small class="fw-bold"><?= date('d.m.Y H:i', strtotime($report->olusturma_tarihi)) ?></small></td>
                                                <td>
                                                    <?php if ($report->tur === 'sabah'): ?>
                                                        <span class="badge bg-soft-warning text-warning"><i class="bx bx-sun me-1"></i> Sabah</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-soft-info text-info"><i class="bx bx-moon me-1"></i> Akşam</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold text-danger"><?= number_format($report->bitis_km, 0, ',', '.') ?> KM</td>
                                                <td>
                                                    <span class="text-danger"><?= $report->red_nedeni ?: 'Neden belirtilmedi' ?></span>
                                                    <small class="d-block text-muted mt-1">Reddeden: <?= $report->onaylayan_adi ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-soft-info btn-view-km-img" 
                                                        data-img="<?= Helper::base_url($report->resim_yolu) ?>"
                                                        data-plaka="<?= $report->plaka ?>"
                                                        data-date="<?= date('d.m.Y', strtotime($report->tarih)) ?>"
                                                        data-tur="<?= ucfirst($report->tur) ?>">
                                                        <i class="bx bx-image-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Bildirim Yapmayanlar -->
                        <div class="tab-pane" id="unreported" role="tabpanel">
                            <div class="d-flex align-items-center mb-3 gap-2">
                                <button type="button" class="btn btn-primary btn-sm d-none" id="btnBulkSendReminder">
                                    <i class="bx bx-paper-plane"></i> Seçilenlere Bildirim Gönder
                                </button>
                                <div class="ms-auto text-muted small" id="selectionSummary"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100" id="tableUnreported">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="unreported-checkbox-wrapper">
                                                    <input type="checkbox" id="checkAllUnreported" class="unreported-checkbox">
                                                    <label for="checkAllUnreported" class="custom-check mb-0"></label>
                                                </div>
                                            </th>
                                            <th style="width:22%">Personel</th>
                                            <th style="width:15%">Araç / Plaka</th>
                                            <th style="width:12%">Hedef Tarih</th>
                                            <th style="width:18%">Tür / Gecikme</th>
                                            <th style="width:15%">Telefon</th>
                                            <th style="width:15%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="unreportedListBody">
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Yükleniyor...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Veriler kontrol ediliyor...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div> <!-- container-fluid -->

<!-- KM Red Modalı -->
<div class="modal fade" id="kmRedModal" tabindex="-1" role="dialog" aria-labelledby="kmRedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kmRedModalLabel"><i class="bx bx-x-circle text-danger me-2"></i>Bildirimi Reddet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kmRedForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="km-onay-reddet">
                    <input type="hidden" name="id" id="red_bildirim_id">
                    <div class="mb-3">
                        <label class="form-label" for="red_nedeni">Red Nedeni <small class="text-muted">(Zorunlu değil)</small></label>
                        <textarea class="form-control" name="red_nedeni" id="red_nedeni" rows="4" placeholder="Neden reddettiğinizi buraya yazabilirsiniz..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger" id="btnKmReddetSubmit">Bildirimi Reddet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resim Görüntüleme Modalı -->
<div class="modal fade no-upgrade" id="imgViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content overflow-hidden border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0 bg-light py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-soft-info text-info">
                            <i class="bx bx-car"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0">Gün ve KM Bildirimi</h6>
                        <small class="text-muted" id="modalImgHeaderInfo"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <img src="" id="modalViewImg" class="img-fluid w-100" alt="KM Bildirim Resmi" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script src="views/arac-takip/js/arac-takip.js?v=<?= time() ?>"></script>
<script>
$(document).ready(function() {
    // Initialize all datatables on the page
    $('.datatable').each(function() {
        AracTakip.initDataTable(this);
    });

    // Excel Export Handler
    $('#exportExcelKm').on('click', function() {
        // Get the active tab's table
        var activeTable = $('.tab-pane.active table.datatable').DataTable();
        if (activeTable) {
            activeTable.button('.buttons-excel').trigger();
        }
    });

    // Handle tab changes to re-adjust datatable columns if needed
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // Image Modal Viewer
    $(document).on('click', '.btn-view-km-img', function() {
        const btn = $(this);
        const imgSrc = btn.data('img');
        const plaka = btn.data('plaka');
        const date = btn.data('date');
        const tur = btn.data('tur');

        $('#modalImgHeaderInfo').text(plaka + ' | ' + date + ' - ' + tur);
        $('#modalViewImg').attr('src', imgSrc);
        $('#imgViewModal').modal('show');
    });

    // Onay Yapmayanlar Listesini Yükle
    function loadUnreported() {
        // Prepare table for loading
        if ($.fn.DataTable.isDataTable('#tableUnreported')) {
            $('#tableUnreported').DataTable().destroy();
        }
        $('#unreportedListBody').html('<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Veriler kontrol ediliyor...</p></td></tr>');

        $.get('views/arac-takip/api.php?action=get-km-onay-yapmayanlar', function(res) {
            if (res.status === 'success') {
                const body = $('#unreportedListBody');
                body.empty();
                if (res.data.length === 0) {
                    body.append('<tr><td colspan="7" class="text-center py-4 text-success"><i class="bx bx-check-double fs-2"></i><br>Tüm bildirimler eksiksiz!</td></tr>');
                    return;
                }
                res.data.forEach((r, i) => {
                    const turBadge = r.hedef_tur === 'sabah' ? '<span class="badge bg-soft-warning text-warning"><i class="bx bx-sun"></i> Sabah</span>' : '<span class="badge bg-soft-info text-info"><i class="bx bx-moon"></i> Akşam</span>';
                    const rowId = `row_un_${r.personel_id}_${r.hedef_tarih}_${r.hedef_tur}`;
                    body.append(`
                        <tr>
                            <td class="text-center">
                                <div class="unreported-checkbox-wrapper">
                                    <input type="checkbox" class="unreported-checkbox row-selector" id="${rowId}"
                                        data-id="${r.personel_id}" 
                                        data-tarih="${r.hedef_tarih}" 
                                        data-tur="${r.hedef_tur}"
                                        data-adi="${r.personel_adi}">
                                    <label for="${rowId}" class="custom-check mb-0"></label>
                                </div>
                            </td>
                            <td><span class="fw-bold">${r.personel_adi}</span></td>
                            <td><span class="badge bg-light text-dark border">${r.plaka}</span></td>
                            <td>${r.hedef_tarih.split('-').reverse().join('.')}</td>
                            <td><span class="fw-bold text-danger">${r.gecikme_turu}</span> <br> ${turBadge}</td>
                            <td><a href="tel:${r.telefon || ''}">${r.telefon || '-'}</a></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-warning btn-send-reminder" 
                                    data-id="${r.personel_id}" 
                                    data-tarih="${r.hedef_tarih}" 
                                    data-tur="${r.hedef_tur}"
                                    title="Bildirim Gönder">
                                    <i class="bx bx-bell"></i> Bildirim Gönder
                                </button>
                            </td>
                        </tr>
                    `);
                });

                // Initialize DataTable
                if (typeof AracTakip !== 'undefined' && AracTakip.initDataTable) {
                    AracTakip.initDataTable('#tableUnreported');
                }
            } else {
                $('#unreportedListBody').html(`<tr><td colspan="7" class="text-center py-4 text-warning"><i class="bx bx-error fs-2"></i><br>${res.message || 'Veriler alınamadı.'}</td></tr>`);
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error("AJAX Error:", error);
            $('#unreportedListBody').html('<tr><td colspan="7" class="text-center py-4 text-danger"><i class="bx bx-error fs-2"></i><br>Veriler yüklenirken sunucu hatası oluştu.</td></tr>');
        });
    }

    $(document).on('click', '#tabUnreportedLink', function() {
        loadUnreported();
    });

    // Sayfa açıldığında veya yenilendiğinde kontrol et
    function checkInitialTab() {
        const hash = window.location.hash;
        const activeTab = $('.nav-link.active').attr('href');
        
        if (hash === '#unreported' || activeTab === '#unreported') {
            loadUnreported();
        }
    }

    $(document).ready(function() {
        setTimeout(checkInitialTab, 500);
    });

    // Toplu Seçim Olayları (DataTables uyumlu)
    $(document).on('change', '#checkAllUnreported', function() {
        const isChecked = $(this).is(':checked');
        const table = $('#tableUnreported').DataTable();
        
        // Tüm sayfalardaki checkboxları seçmek için DataTable API kullan
        // Ancak görsel olarak sadece bu sayfadakiler değişir
        $('.row-selector').prop('checked', isChecked);
        
        // Global durum için bir marker ekle
        if (isChecked) {
            $('#tableUnreported').addClass('all-selected-mode');
        } else {
            $('#tableUnreported').removeClass('all-selected-mode');
        }
        
        updateSelectionSummary();
    });

    $(document).on('change', '.row-selector', function() {
        if (!$(this).is(':checked')) {
            $('#checkAllUnreported').prop('checked', false);
            $('#tableUnreported').removeClass('all-selected-mode');
        }
        updateSelectionSummary();
    });

    function updateSelectionSummary() {
        const table = $('#tableUnreported').DataTable();
        let count = 0;
        
        if ($('#tableUnreported').hasClass('all-selected-mode')) {
            count = table.rows({ filter: 'applied' }).count();
        } else {
            count = $('.row-selector:checked').length;
        }
        
        if (count > 0) {
            $('#btnBulkSendReminder').removeClass('d-none');
            $('#selectionSummary').html(`<span class="badge bg-primary">${count}</span> kayıt seçildi`);
        } else {
            $('#btnBulkSendReminder').addClass('d-none');
            $('#selectionSummary').text('');
        }
    }

    // Toplu Bildirim Gönderimi (Tüm sayfaları kapsar)
    $(document).on('click', '#btnBulkSendReminder', function() {
        const table = $('#tableUnreported').DataTable();
        const selectedItems = [];
        
        if ($('#tableUnreported').hasClass('all-selected-mode')) {
            // Filtrelenmiş TÜM kayıtları al (sayfalamadan bağımsız)
            table.rows({ filter: 'applied' }).every(function() {
                const node = this.node();
                const $chk = $(node).find('.row-selector');
                selectedItems.push({
                    personel_id: $chk.data('id'),
                    tarih: $chk.data('tarih'),
                    tur: $chk.data('tur'),
                    adi: $chk.data('adi')
                });
            });
        } else {
            // Sadece seçili olan yerel kayıtları al
            $('.row-selector:checked').each(function() {
                selectedItems.push({
                    personel_id: $(this).data('id'),
                    tarih: $(this).data('tarih'),
                    tur: $(this).data('tur'),
                    adi: $(this).data('adi')
                });
            });
        }

        if (selectedItems.length === 0) return;

        Swal.fire({
            title: 'Toplu Bildirim',
            text: `${selectedItems.length} personele hatırlatma bildirimi gönderilecektir. Onaylıyor musunuz?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#3b82f6'
        }).then((result) => {
            if (result.isConfirmed) {
                processBulkReminders(selectedItems);
            }
        });
    });

    async function processBulkReminders(items) {
        Swal.fire({
            title: 'Bildirimler Gönderiliyor',
            html: `Lütfen pencereyi kapatmayın... <br><br> <div class="progress mb-2"><div class="progress-bar progress-bar-animated progress-bar-striped" role="progressbar" style="width: 0%"></div></div> <b id="bulkProgressText">0 / ${items.length}</b>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let successCount = 0;
        const progressBar = Swal.getHtmlContainer().querySelector('.progress-bar');
        const progressText = Swal.getHtmlContainer().querySelector('#bulkProgressText');

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            try {
                const res = await $.post('views/arac-takip/api.php', {
                    action: 'send-km-bildirim-hatirlatma',
                    personel_id: item.personel_id,
                    tarih: item.tarih,
                    tur: item.tur
                });
                if (res.status === 'success') successCount++;
            } catch (err) {
                console.error("Bulk send error for " + item.adi, err);
            }

            const percent = Math.round(((i + 1) / items.length) * 100);
            progressBar.style.width = percent + '%';
            progressText.textContent = `${i + 1} / ${items.length}`;
        }

        Swal.fire({
            title: 'İşlem Tamamlandı',
            text: `${successCount} personele bildirim başarıyla iletildi.`,
            icon: 'success',
            confirmButtonText: 'Kapat'
        }).then(() => {
            loadUnreported(); // Listeyi yenile
        });
    }

    // Hatırlatma Gönder
    $(document).on('click', '.btn-send-reminder', function() {
        const btn = $(this);
        const personelId = btn.data('id');
        const tarih = btn.data('tarih');
        const tur = btn.data('tur');
        const personelAdi = btn.closest('tr').find('td').eq(1).text();

        const trFmt = tur === 'sabah' ? 'Sabah' : 'Akşam';
        const tarihFmt = tarih.split('-').reverse().join('.');
        const message = `${tarihFmt} tarihli ${trFmt} bildirimini yapmadınız, Lütfen yapınız.`;

        Swal.fire({
            title: 'Bildirim Onayı',
            html: `
                <div class="text-start">
                    <p class="mb-2">Aşağıdaki personele hatırlatma bildirimi gönderilecektir:</p>
                    <div class="p-2 border rounded bg-light mb-3">
                        <span class="fw-bold">Personel:</span> ${personelAdi}
                    </div>
                    <p class="mb-2 fw-bold">Gönderilecek Mesaj:</p>
                    <div class="alert alert-warning py-2 px-3 border-0 bg-soft-warning text-warning mb-0" style="font-size: 0.9rem;">
                        ${message}
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#74788d',
            confirmButtonText: 'Evet, Gönder',
            cancelButtonText: 'İptal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i>');

                const data = {
                    action: 'send-km-bildirim-hatirlatma',
                    personel_id: personelId,
                    tarih: tarih,
                    tur: tur
                };

                $.post('views/arac-takip/api.php', data, function(res) {
                        if (res.status === 'success') {
                            if (typeof Toastify !== 'undefined') {
                                Toastify({
                                    text: "Bildirim başarıyla gönderildi.",
                                    duration: 3000,
                                    gravity: "top",
                                    position: "right",
                                    stopOnFocus: true,
                                    style: {
                                        background: "linear-gradient(to right, #00b09b, #96c93d)",
                                        borderRadius: "8px",
                                        boxShadow: "0 4px 12px rgba(0,0,0,0.1)"
                                    }
                                }).showToast();
                            }

                            // Buton durumunu güncelle (Tekrar gönderime izin ver)
                            btn.prop('disabled', false)
                                .removeClass('btn-warning')
                                .addClass('btn-outline-secondary btn-sm')
                                .html('<i class="bx bx-redo"></i> Tekrar Gönder');
                            
                            // Gönderildi bilgisini yanına ekle (Mavi çift tik)
                            if (btn.parent().find('.sent-status-icon').length === 0) {
                                btn.before('<span class="sent-status-icon me-1" style="color: #34B7F1 !important; font-size: 1.3rem; vertical-align: middle;" title="Gönderildi"><i class="bx bx-check-double"></i></span>');
                            }
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                            btn.prop('disabled', false).html('<i class="bx bx-bell"></i> Bildirim Gönder');
                        }
                }, 'json').fail(function() {
                    Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
                    btn.prop('disabled', false).html('<i class="bx bx-bell"></i> Bildirim Gönder');
                });
            }
        });
    });

    // Toplu Seçim İşlemleri
    $('#checkAllKm').on('change', function() {
        $('.km-checkbox').prop('checked', $(this).is(':checked'));
        updateBatchButton();
    });

    $(document).on('change', '.km-checkbox', function() {
        updateBatchButton();
        if ($('.km-checkbox:checked').length === $('.km-checkbox').length) {
            $('#checkAllKm').prop('checked', true);
        } else {
            $('#checkAllKm').prop('checked', false);
        }
    });

    function updateBatchButton() {
        const selectedCount = $('.km-checkbox:checked').length;
        $('#selectedCount').text(selectedCount);
        if (selectedCount > 0) {
            $('#btnTopluOnayla').removeClass('d-none').addClass('d-flex');
            $('#btnTopluOnaylaDivider').removeClass('d-none');
        } else {
            $('#btnTopluOnayla').addClass('d-none').removeClass('d-flex');
            $('#btnTopluOnaylaDivider').addClass('d-none');
        }
    }

    $('#btnTopluOnayla').on('click', function() {
        const selectedIds = [];
        $('.km-checkbox:checked').each(function() {
            selectedIds.push($(this).data('id'));
        });
        
        if (selectedIds.length > 0) {
            AracTakip.kmTopluOnayla(selectedIds);
        }
    });
});
</script>
