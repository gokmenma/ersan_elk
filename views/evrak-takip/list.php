<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\EvrakTakipModel;
use App\Model\PersonelModel;

$Evrak = new EvrakTakipModel();
$Personel = new PersonelModel();

$evraklar = $Evrak->all();
$personeller = $Personel->all(false, 'evrak');
$stats = $Evrak->getStats();
$gelen_evraklar = $Evrak->getGelenEvraklar();
?>

<div class="container-fluid">
    <!-- start page title -->
    <?php
    $maintitle = "Evrak Takip";
    $title = "Genel Evrak Takip";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->


    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-none bg-transparent">
                <div class="card-header bg-transparent border-0 p-0 mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <!-- Sol Taraf: Filtreler (Gerekirse eklenebilir, şimdilik boş) -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <div class="px-3 py-1">
                                <span class="text-muted small fw-bold text-uppercase"
                                    style="letter-spacing: 0.5px; font-size: 10px;">Evrak İşlemleri</span>
                            </div>
                        </div>

                        <!-- Sağ Taraf: Aksiyon Butonları -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                            <button type="button"
                                class="btn btn-link btn-sm text-primary text-decoration-none px-3 d-flex align-items-center fw-bold"
                                id="btnRefresh">
                                <i data-feather="refresh-cw" class="icon-sm me-1"></i> <span
                                    class="d-none d-md-inline">Yenile</span>
                            </button>

                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                            <button type="button"
                                class="btn btn-primary btn-sm text-white shadow-primary text-decoration-none px-3 d-flex align-items-center fw-bold"
                                id="btnYeniEvrak">
                                <i data-feather="plus-circle" class="icon-sm me-1"></i> <span
                                    class="d-none d-md-inline">Yeni Evrak Ekle</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Özet Kartları -->
                <div class="row g-3 mb-4">
                    <!-- Toplam Evrak -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                            style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                            <div class="card-body p-3">
                                <div class="icon-label-container">
                                    <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                        <i data-feather="file" class="fs-4" style="color: #0ea5e9;"></i>
                                    </div>
                                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GENEL</span>
                                </div>
                                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">
                                    TOPLAM EVRAK</p>
                                <h4 class="mb-0 fw-bold bordro-text-heading">
                                    <?php echo $stats->toplam_evrak ?? 0; ?> <span
                                        style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Gelen Evrak -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                            style="--card-color: #10b981; border-bottom: 3px solid var(--card-color) !important;">
                            <div class="card-body p-3">
                                <div class="icon-label-container">
                                    <div class="icon-box" style="background: rgba(16, 185, 129, 0.1);">
                                        <i data-feather="download" class="fs-4 text-success"></i>
                                    </div>
                                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GİRİŞ</span>
                                </div>
                                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">
                                    GELEN EVRAK</p>
                                <h4 class="mb-0 fw-bold bordro-text-heading">
                                    <?php echo $stats->gelen_evrak ?? 0; ?> <span
                                        style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Giden Evrak -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                            style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                            <div class="card-body p-3">
                                <div class="icon-label-container">
                                    <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                        <i data-feather="upload" class="fs-4 text-danger"></i>
                                    </div>
                                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ÇIKIŞ</span>
                                </div>
                                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">
                                    GİDEN EVRAK</p>
                                <h4 class="mb-0 fw-bold bordro-text-heading">
                                    <?php echo $stats->giden_evrak ?? 0; ?> <span
                                        style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Cevap Bekleyen -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                            style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                            <div class="card-body p-3">
                                <div class="icon-label-container">
                                    <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                        <i data-feather="clock" class="fs-4 text-warning"></i>
                                    </div>
                                    <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BEKLEYEN</span>
                                </div>
                                <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">
                                    CEVAP BEKLEYEN</p>
                                <h4 class="mb-0 fw-bold bordro-text-heading text-warning">
                                    <?php echo $stats->cevap_bekleyen ?? 0; ?> <span
                                        style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="evrakTable"
                                class="table datatable table-hover table-bordered nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr
                                        style="background: linear-gradient(to top, rgba(var(--bs-primary-rgb), 0.02) 0%, rgba(var(--bs-primary-rgb), 0.06) 100%) !important;">
                                        <th class="text-center" style="width: 50px;">#</th>
                                        <th style="width: 80px;" class="text-center">Tip</th>
                                        <th style="width: 100px;">Tarih</th>
                                        <th>Konu / Evrak No</th>
                                        <th>Gelen/Giden Kurum</th>
                                        <th>Zimmetli (Ofis)</th>
                                        <th>İlgili Personel</th>
                                        <th class="text-center" style="width: 90px;">Cevap</th>
                                        <th class="text-center" style="width: 90px;">Dosya</th>
                                        <th class="text-center" style="width: 110px;">İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1;
                                    foreach ($evraklar as $evrak): ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="fw-bold text-muted"><?php echo $i++; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($evrak->evrak_tipi == 'gelen'): ?>
                                                    <span class="badge bg-success-subtle text-success p-2 rounded-3 fw-bold" style="font-size: 10px;">
                                                        <i data-feather="arrow-down" class="icon-xs me-1"></i>GELEN
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger p-2 rounded-3 fw-bold" style="font-size: 10px;">
                                                        <i data-feather="arrow-up" class="icon-xs me-1"></i>GİDEN
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold text-dark small"><?php echo date('d.m.Y', strtotime($evrak->tarih)); ?></span>
                                                    <span class="text-muted" style="font-size: 10px;"><?php echo date('H:i', strtotime($evrak->olusturulma_tarihi ?? 'now')); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark mb-1" style="font-size: 13px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo $evrak->konu ?? '-'; ?>
                                                </div>
                                                <div class="d-flex align-items-center text-muted fw-medium" style="font-size: 10px;">
                                                    <i data-feather="hash" class="icon-xs me-1"></i>
                                                    <?php echo $evrak->evrak_no ?? '-'; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-dark small pb-1 d-block"><?php echo $evrak->kurum_adi ?? '-'; ?></span>
                                                <span class="text-muted" style="font-size: 10px;"><i data-feather="home" class="icon-xs me-1"></i>Kurum/Firma</span>
                                            </td>
                                            <td>
                                                <?php if ($evrak->personel_adi): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                            <i data-feather="user-check" style="width: 10px;"></i>
                                                        </div>
                                                        <span class="small fw-bold text-dark"><?php echo $evrak->personel_adi; ?></span>

                                                        <button type="button" class="btn btn-link text-primary p-0 ms-2 evrak-bildir-manuel" 
                                                            data-id="<?php echo $evrak->id; ?>" 
                                                            data-personel-id="<?php echo $evrak->personel_id; ?>"
                                                            data-type="personel"
                                                            data-last-notified="<?php echo $evrak->son_bildirim_tarihi_personel; ?>"
                                                            title="Bildirim ve Mail Gönder">
                                                            <i data-feather="bell" style="width: 14px;"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($evrak->ilgili_personel_adi): ?>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-xs bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center me-2">
                                                            <i data-feather="user" style="width: 10px;"></i>
                                                        </div>
                                                        <span class="small fw-bold text-info"><?php echo $evrak->ilgili_personel_adi; ?></span>
                                                        
                                                        <button type="button" class="btn btn-link text-warning p-0 ms-2 evrak-bildir-manuel" 
                                                            data-id="<?php echo $evrak->id; ?>" 
                                                            data-personel-id="<?php echo $evrak->ilgili_personel_id; ?>"
                                                            data-type="ilgili"
                                                            data-last-notified="<?php echo $evrak->son_bildirim_tarihi_ilgili; ?>"
                                                            title="Bildirim ve Mail Gönder">
                                                            <i data-feather="bell" style="width: 14px;"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($evrak->evrak_tipi == 'gelen'): ?>
                                                    <?php if ($evrak->cevap_verildi_mi): ?>
                                                        <span class="badge bg-success-subtle text-success p-2 rounded-3 w-100 fw-bold" style="font-size: 10px;" title="Cevap Tarihi: <?php echo $evrak->cevap_tarihi ? date('d.m.Y', strtotime($evrak->cevap_tarihi)) : '-'; ?>">
                                                            EVET
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning p-2 rounded-3 w-100 fw-bold" style="font-size: 10px;">
                                                            BEKLEMEDE
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($evrak->dosya_yolu): ?>
                                                    <a href="<?php echo $evrak->dosya_yolu; ?>" target="_blank"
                                                        class="btn btn-sm btn-info btn-soft text-uppercase fw-bold"
                                                        style="font-size: 10px;">
                                                        DOSYA
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button type="button" class="btn btn-soft-primary btn-sm evrak-duzenle border-0 p-1 px-2" data-id="<?php echo $evrak->id; ?>">
                                                        <i data-feather="edit-2" style="width:14px;"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-soft-danger btn-sm evrak-sil border-0 p-1 px-2" data-id="<?php echo $evrak->id; ?>">
                                                        <i data-feather="trash-2" style="width:14px;"></i>
                                                    </button>
                                                </div>
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

<style>
    .btn-soft {
        background-color: rgba(0, 171, 142, 0.1);
        color: #00ab8e;
        border: none;
    }

    .btn-soft:hover {
        background-color: #00ab8e;
        color: #fff;
    }

    .table> :not(caption)>*>* {
        padding: 1rem 0.75rem;
    }

    .table thead th {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        color: #495057;
    }

    .avatar-xs {
        height: 24px;
        width: 24px;
    }

    .shadow-primary {
        box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.3) !important;
    }

    .icon-sm {
        width: 16px;
        height: 16px;
    }

    .icon-xs {
        width: 12px;
        height: 12px;
    }
</style>



<?php include_once "modal/evrak-modal.php"; ?>

<script src="views/evrak-takip/js/evrak-takip.js"></script>