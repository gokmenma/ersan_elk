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
    
    .status-filter-group .nav-link i { font-size: 1.1rem; }
    .status-filter-group .nav-link .badge { font-size: 0.7rem; padding: 0.25em 0.6em; }
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
