<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;

$maintitle = "Demirbaş";
$title = "Servis Kayıtları";
?>

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <style>
        .personel-preloader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            min-height: 320px;
            background: rgba(255, 255, 255, 0.82);
            z-index: 1060;
            border-radius: 4px;
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        [data-bs-theme="dark"] .personel-preloader {
            background: rgba(25, 30, 34, 0.85);
        }

        .personel-preloader .loader-content {
            background: white;
            padding: 2rem;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 240px;
        }

        [data-bs-theme="dark"] .personel-preloader .loader-content {
            background: #2a3042;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        .status-filter-group {
            background: #f8f9fa;
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
            background: transparent !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-filter-group .btn-check:checked + .btn {
            background: #fff !important;
            color: #0ea5e9;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
    </style>

    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bx bx-wrench me-1 text-warning"></i> Servis Kayıtları
                </h5>

                <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                    <button type="button" id="btnExportExcelServis"
                        class="btn btn-success btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1">
                        <i class="bx bx-spreadsheet fs-5 me-1"></i> Excel'e Aktar
                    </button>
                   <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                    <button type="button" id="btnYeniServis"
                        class="btn btn-warning btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1">
                        <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Servis Kaydı
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body position-relative">
            <div class="personel-preloader" id="personel-loader">
                <div class="loader-content">
                    <div class="spinner-border text-warning m-1" role="status">
                        <span class="sr-only">Yükleniyor...</span>
                    </div>
                    <h5 class="mt-2 mb-0">Servis Kayıtları Hazırlanıyor...</h5>
                    <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                </div>
            </div>

            <div class="row g-3 mb-4" id="servisStatsRow">
                <div class="col-xl col-md-4">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-3">
                            <div class="icon-label-container">
                                <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                    <i class="bx bx-wrench fs-4" style="color: #0ea5e9;"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SERVİS</span>
                            </div>
                            <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM KAYIT</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis_toplam_kayit">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-3">
                            <div class="icon-label-container">
                                <div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
                                    <i class="bx bx-time fs-4" style="color: #ef4444;"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SERVİSTE</span>
                            </div>
                            <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">ŞU AN SERVİSTE</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis_aktif_sayisi">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-xl col-md-4">
                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                        style="--card-color: #22c55e; border-bottom: 3px solid var(--card-color) !important;">
                        <div class="card-body p-3">
                            <div class="icon-label-container">
                                <div class="icon-box" style="background: rgba(34, 197, 94, 0.1);">
                                    <i class="bx bx-money fs-4" style="color: #22c55e;"></i>
                                </div>
                                <span class="text-muted small fw-bold" style="font-size: 0.65rem;">MALİYET</span>
                            </div>
                            <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM MALİYET</p>
                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis_toplam_maliyet">0 ₺</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-transparent border-0 shadow-none mb-2">
                <div class="card-body p-0">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="status-filter-group shadow-sm">
                            <input type="radio" class="btn-check" name="servis-status-filter" id="servis_status_all" value="all" checked>
                            <label class="btn px-3" for="servis_status_all">
                                <i class="bx bx-check-double"></i> Tümü
                            </label>

                            <input type="radio" class="btn-check" name="servis-status-filter" id="servis_status_active" value="active">
                            <label class="btn px-3" for="servis_status_active">
                                <i class="bx bx-wrench"></i> Serviste
                            </label>

                            <input type="radio" class="btn-check" name="servis-status-filter" id="servis_status_completed" value="completed">
                            <label class="btn px-3" for="servis_status_completed">
                                <i class="bx bx-check-circle"></i> Bitti
                            </label>
                        </div>

                        <div class="ms-auto d-flex align-items-end gap-2">
                            <div style="width: 280px;">
                                <?php echo Form::FormFloatInput('text', 'servis_filtre_range', date('01.m.Y') . ' to ' . date('t.m.Y'), 'Tarih Aralığı', 'Tarih Aralığı', 'calendar', 'form-control flatpickr-range'); ?>
                            </div>
                            <button type="button" class="btn btn-primary px-4" id="btnServisListele" style="height: 50.5px;">
                                <i class="bx bx-search-alt"></i> Listele
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="servisTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:5%" data-filter="number">Sıra</th>
                            <th style="width:15%" data-filter="string">Demirbaş</th>
                            <th style="width:10%" class="text-center" data-filter="date">Giriş Tarihi</th>
                            <th style="width:10%" class="text-center" data-filter="date">Çıkış Tarihi</th>
                            <th style="width:15%" data-filter="string">Servis Noktası</th>
                            <th style="width:15%" data-filter="string">Teslim Eden</th>
                            <th style="width:20%" data-filter="string">Neden / Yapılan İşlem</th>
                            <th style="width:10%" class="text-end" data-filter="string">Tutar</th>
                            <th style="width:10%" class="text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once "modal/servis-modal.php" ?>
