<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracKmBildirimModel;
use App\Service\Gate;

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
                                <table class="table table-hover table-bordered nowrap w-100" id="tablePending">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="form-check font-size-16">
                                                     <input class="form-check-input" type="checkbox" id="checkAllKm">
                                                     <label class="form-check-label" for="checkAllKm"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Personel</th>
                                            <th style="width:15%">Araç</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%">Kayıt Tarihi</th>
                                            <th style="width:10%">Tür</th>
                                            <th style="width:10%" class="text-end">Bildirilen KM</th>
                                            <th style="width:15%">Açıklama</th>
                                            <th style="width:10%" class="text-center">Resim</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Onaylananlar -->
                        <div class="tab-pane" id="approved" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100" id="tableApproved">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Personel</th>
                                            <th style="width:15%">Araç</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%">Kayıt Tarihi</th>
                                            <th style="width:10%">Tür</th>
                                            <th style="width:10%" class="text-end">Bildirilen KM</th>
                                            <th style="width:10%" class="text-end">Onaylanan KM</th>
                                            <th style="width:15%">Onaylayan / Tarih</th>
                                            <th style="width:10%" class="text-center">Resim / İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Reddedilenler -->
                        <div class="tab-pane" id="rejected" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered nowrap w-100" id="tableRejected">
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
                                            <th style="width:10%" class="text-center">Resim / İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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

<!-- Onaylı KM Düzenleme Modalı -->
<div class="modal fade no-upgrade" id="editApprovedKmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-warning py-3 px-4 border-0" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-white text-warning">
                            <i class="bx bx-edit"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold">Onaylı KM Düzenle</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editApprovedKmForm">
                <input type="hidden" id="edit_approved_id" name="id">
                <input type="hidden" id="edit_approved_arac_id" name="arac_id">
                
                <div class="modal-body p-4">
                    <div class="p-3 border rounded bg-light mb-3" style="font-size: 0.9rem; border-left: 4px solid #ffbb44 !important;">
                        <div class="mb-1"><span class="fw-bold text-muted">Araç Plaka:</span> <span id="lbl_edit_plaka" class="fw-bold text-dark"></span></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="edit_approved_date" class="form-label fw-bold text-dark">Tarih</label>
                            <input type="date" class="form-control" id="edit_approved_date" name="tarih" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_approved_tur" class="form-label fw-bold text-dark">Tür</label>
                            <select class="form-select" id="edit_approved_tur" name="tur" required>
                                <option value="sabah">Sabah</option>
                                <option value="aksam">Akşam</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="edit_approved_km_value" class="form-label fw-bold text-dark">Yeni KM Değeri</label>
                        <input type="number" class="form-control" id="edit_approved_km_value" name="km" min="0" required>
                    </div>

                    <div class="form-group mb-0">
                        <label for="edit_approved_aciklama" class="form-label fw-bold text-dark">Açıklama (İsteğe Bağlı)</label>
                        <textarea class="form-control" id="edit_approved_aciklama" name="aciklama" rows="3" placeholder="KM düzeltme nedeni veya açıklama girin..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-0" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold" id="btnSubmitEditApprovedKm">
                        <i class="bx bx-save me-1"></i>Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- KM Düzelt ve Onayla Modalı -->
<div class="modal fade no-upgrade" id="kmDuzeltOnaylaModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-warning py-3 px-4 border-0" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2">
                        <span class="avatar-title rounded-circle bg-white text-warning">
                            <i class="bx bx-check-shield"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 text-white fw-bold">KM Düzelt ve Onayla</h6>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kmDuzeltOnaylaForm">
                <input type="hidden" id="duzelt_onay_id" name="id">
                
                <div class="modal-body p-4">
                    <div class="p-3 border rounded bg-light mb-3" style="font-size: 0.9rem; border-left: 4px solid #ffbb44 !important;">
                        <div class="mb-1"><span class="fw-bold text-muted">Personel:</span> <span id="lbl_duzelt_personel" class="fw-bold text-dark"></span></div>
                        <div class="mb-1"><span class="fw-bold text-muted">Araç Plaka:</span> <span id="lbl_duzelt_plaka" class="fw-bold text-dark"></span></div>
                        <div class="mb-1"><span class="fw-bold text-muted">Tarih / Tür:</span> <span id="lbl_duzelt_tarih_tur" class="fw-bold text-dark"></span></div>
                        <div class="mb-0"><span class="fw-bold text-muted">Bildirilen KM:</span> <span id="lbl_duzelt_bildirilen_km" class="fw-bold text-danger"></span></div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="duzelt_onay_km_value" class="form-label fw-bold text-dark">Doğru KM Değeri</label>
                        <input type="number" class="form-control" id="duzelt_onay_km_value" name="km" min="0" required>
                    </div>

                    <div class="form-group mb-0">
                        <label for="duzelt_onay_aciklama" class="form-label fw-bold text-dark">Açıklama (İsteğe Bağlı)</label>
                        <textarea class="form-control" id="duzelt_onay_aciklama" name="aciklama" rows="3" placeholder="KM düzeltme nedeni veya açıklama girin..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-0" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning text-white fw-bold" id="btnSubmitKmDuzeltOnayla">
                        <i class="bx bx-check me-1"></i>Güncelle ve Onayla
                    </button>
                </div>
            </form>
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

    const pendingColumns = [
        { 
            data: 'checkbox', 
            orderable: false, 
            searchable: false, 
            className: 'text-center' 
        },
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-primary' },
        { data: 'aciklama', name: 'aciklama' },
        { data: 'resim', orderable: false, searchable: false, className: 'text-center' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    const approvedColumns = [
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-primary' },
        { data: 'onaylanan_km', name: 'onaylanan_km', className: 'text-end fw-bold text-success' },
        { data: 'onaylayan_tarih', name: 'onaylayan_tarih' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    const rejectedColumns = [
        { 
            data: null, 
            orderable: false, 
            searchable: false, 
            className: 'text-center',
            render: function (data, type, row, meta) { 
                return meta.row + meta.settings._iDisplayStart + 1; 
            } 
        },
        { data: 'personel', name: 'personel' },
        { data: 'arac', name: 'arac' },
        { data: 'tarih', name: 'tarih', className: 'text-center' },
        { data: 'olusturma_tarihi', name: 'olusturma_tarihi', className: 'text-center' },
        { data: 'tur', name: 'tur', className: 'text-center' },
        { data: 'bitis_km', name: 'bitis_km', className: 'text-end fw-bold text-danger' },
        { data: 'red_nedeni', name: 'red_nedeni' },
        { data: 'islem', orderable: false, searchable: false, className: 'text-center' }
    ];

    function initServerSideDataTable(selector, status, columnsConfig, defaultOrder) {
        if (!$(selector).length) return null;
        
        let options = typeof getDatatableOptions === "function" ? getDatatableOptions() : {
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json" },
            pageLength: 10
        };

        options.processing = true;
        options.serverSide = true;
        options.ajax = {
            url: "views/arac-takip/api.php",
            type: "POST",
            data: function (d) {
                d.action = "get-km-onay-list-server-side";
                d.status = status;
            }
        };
        options.columns = columnsConfig;
        options.order = defaultOrder || [[0, "desc"]];
        options.dom = "Bfrtip";
        options.buttons = [
            {
                extend: "excelHtml5",
                className: "d-none",
                text: "Excel",
                exportOptions: {
                    columns: ":visible:not(:last-child)"
                }
            }
        ];

        return $(selector).DataTable(options);
    }

    // Initialize Server-Side Tables
    initServerSideDataTable('#tablePending', 'beklemede', pendingColumns, [[5, 'desc']]);
    initServerSideDataTable('#tableApproved', 'onaylandi', approvedColumns, [[4, 'desc']]);
    initServerSideDataTable('#tableRejected', 'reddedildi', rejectedColumns, [[4, 'desc']]);

    // Excel Export Handler
    $('#exportExcelKm').on('click', function() {
        // Get the active tab's table
        var activeTable = $('.tab-pane.active table').DataTable();
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
        let hasInvalidAksam = false;
        
        const now = new Date();
        const currentHour = now.getHours();
        const todayStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

        $('.km-checkbox:checked').each(function() {
            const id = $(this).data('id');
            const tur = $(this).data('tur');
            const tarih = $(this).data('tarih');

            // Eğer bugün ise, tür akşam ise ve saat 13:00'dan önce ise
            if (tarih === todayStr && tur === 'aksam' && currentHour < 13) {
                hasInvalidAksam = true;
                $(this).closest('tr').addClass('table-danger');
                setTimeout(() => $(this).closest('tr').removeClass('table-danger'), 5000);
            } else {
                selectedIds.push(id);
            }
        });

        if (hasInvalidAksam) {
            Swal.fire({
                icon: 'warning',
                title: 'Uyarı',
                text: 'Bugün için henüz akşam KM onayı yapılamaz (Saat 13:00\'dan sonra onaylanabilir). Akşam türündeki kayıtlar hariç tutularak devam edilsin mi?',
                showCancelButton: true,
                confirmButtonText: 'Evet, Kalanları Onayla',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#34c38f',
            }).then((result) => {
                if (result.isConfirmed && selectedIds.length > 0) {
                    AracTakip.kmTopluOnayla(selectedIds);
                }
            });
        } else if (selectedIds.length > 0) {
            AracTakip.kmTopluOnayla(selectedIds);
        }
    });

    // Onaylı KM Düzenleme modalını aç
    $(document).on('click', '.btn-edit-approved-km', function() {
        const btn = $(this);
        const id = btn.data('id');
        const aracId = btn.data('arac-id');
        const plaka = btn.data('plaka');
        const tarihRaw = btn.data('date-raw');
        const tur = btn.data('tur');
        const km = btn.data('km');
        const aciklama = btn.data('aciklama');

        $('#edit_approved_id').val(id);
        $('#edit_approved_arac_id').val(aracId);
        $('#lbl_edit_plaka').text(plaka);
        $('#edit_approved_date').val(tarihRaw);
        $('#edit_approved_tur').val(tur);
        $('#edit_approved_km_value').val(km);
        $('#edit_approved_aciklama').val(aciklama || '');

        $('#editApprovedKmModal').modal('show');
    });

    // Onaylı KM Düzenleme formunu gönder
    $('#editApprovedKmForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btnSubmit = $('#btnSubmitEditApprovedKm');
        const oldBtnHtml = btnSubmit.html();
        
        btnSubmit.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Güncelleniyor...');

        const formData = form.serialize() + '&action=onayli-km-guncelle';

        $.post('views/arac-takip/api.php', formData, function(res) {
            if (res.status === 'success') {
                $('#editApprovedKmModal').modal('hide');
                Swal.fire({
                    title: 'Başarılı',
                    text: 'KM kaydı başarıyla güncellendi.',
                    icon: 'success',
                    confirmButtonText: 'Tamam'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata', res.message, 'error');
                btnSubmit.prop('disabled', false).html(oldBtnHtml);
            }
        }, 'json').fail(function() {
            Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
            btnSubmit.prop('disabled', false).html(oldBtnHtml);
        });
    });

    // KM Düzelt ve Onayla modalını aç
    $(document).on('click', '.btn-km-duzelt-onayla', function() {
        const btn = $(this);
        const id = btn.data('id');
        const aracId = btn.data('arac-id');
        const plaka = btn.data('plaka');
        const personel = btn.data('personel');
        const tarihRaw = btn.data('tarih');
        const tur = btn.data('tur');
        const km = btn.data('km');

        $('#duzelt_onay_id').val(id);
        $('#lbl_duzelt_personel').text(personel);
        $('#lbl_duzelt_plaka').text(plaka);
        
        const turFmt = tur === 'sabah' ? 'Sabah' : 'Akşam';
        const dateFmt = tarihRaw.split('-').reverse().join('.');
        $('#lbl_duzelt_tarih_tur').text(dateFmt + ' / ' + turFmt);
        $('#lbl_duzelt_bildirilen_km').text(new Intl.NumberFormat("tr-TR").format(km) + ' KM');
        $('#duzelt_onay_km_value').val(km);
        $('#duzelt_onay_aciklama').val('');

        $('#kmDuzeltOnaylaModal').modal('show');
    });

    // KM Düzelt ve Onayla formunu gönder
    $('#kmDuzeltOnaylaForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btnSubmit = $('#btnSubmitKmDuzeltOnayla');
        const oldBtnHtml = btnSubmit.html();
        
        btnSubmit.prop('disabled', true).html('<i class="bx bx-loader bx-spin me-1"></i> Güncelleniyor...');

        const formData = form.serialize() + '&action=km-onay-duzelt-onayla';

        $.post('views/arac-takip/api.php', formData, function(res) {
            if (res.status === 'success') {
                $('#kmDuzeltOnaylaModal').modal('hide');
                Swal.fire({
                    title: 'Başarılı',
                    text: 'KM kaydı düzeltilerek onaylandı.',
                    icon: 'success',
                    confirmButtonText: 'Tamam'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire('Hata', res.message, 'error');
                btnSubmit.prop('disabled', false).html(oldBtnHtml);
            }
        }, 'json').fail(function() {
            Swal.fire('Hata', 'İşlem sırasında bir bağlantı hatası oluştu.', 'error');
            btnSubmit.prop('disabled', false).html(oldBtnHtml);
        });
    });
});
</script>
