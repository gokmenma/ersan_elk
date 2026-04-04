<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\PersonelModel;
use App\Model\DemirbasZimmetModel;

$Personel = new PersonelModel();
$Zimmet = new DemirbasZimmetModel();

$personeller = $Personel->all(false, 'demirbas');
$zimmetStats = $Zimmet->getStats();
?>
<div class="container-fluid">
    <?php
    $maintitle = "Demirbaş";
    $title = "Zimmet İşlemleri";
    include 'layouts/breadcrumb.php';
    ?>

    <!-- Zimmet İstatistikleri -->
    <div class="row g-2 mb-4">
        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(85, 110, 230, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-list-ul fs-6 text-primary"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">TOPLAM ZİMMET</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $zimmetStats->toplam_zimmet ?? 0 ?></h5>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #f1b44c; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(241, 180, 76, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-user-check fs-6 text-warning"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">AKTİF ZİMMETLİ</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $zimmetStats->aktif_zimmet ?? 0 ?></h5>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #34c38f; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(52, 195, 143, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-undo fs-6 text-success"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">İADE EDİLEN</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $zimmetStats->iade_edilen ?? 0 ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtreler ve Tablo -->
    <div class="card">
        <div class="card-body">
            <div class="row mb-3 align-items-center">
                <div class="col-sm-3">
                    <h4 class="card-title mb-0">
                        <i class="bx bx-list-ol text-primary me-2"></i>Tüm Zimmetler
                    </h4>
                </div>
                <div class="col-sm-9">
                    <div class="d-flex flex-wrap justify-content-sm-end gap-2 align-items-center">
                        <!-- Durum Filtresi (Radio Button Group) -->
                        <div class="btn-group filter-group btn-group-sm bg-light p-1 rounded border shadow-sm status-filter-group" role="group">
                            <input type="radio" class="btn-check" name="zimmetFilter" id="zimmetAll" value="all" checked autocomplete="off">
                            <label class="btn btn-outline-primary border-0 rounded" for="zimmetAll">Hepsi</label>

                            <input type="radio" class="btn-check" name="zimmetFilter" id="zimmetActive" value="active" autocomplete="off">
                            <label class="btn btn-outline-warning border-0 rounded" for="zimmetActive">Zimmetliler</label>

                            <input type="radio" class="btn-check" name="zimmetFilter" id="zimmetReturned" value="returned" autocomplete="off">
                            <label class="btn btn-outline-success border-0 rounded" for="zimmetReturned">İade Edilenler</label>
                        </div>
                        
                        <!-- Personel Filtresi -->
                        <div style="min-width: 200px;">
                            <select id="zimmet_personel_filtre" class="form-select form-select-sm select2">
                                <option value="">Tüm Personeller</option>
                                <?php foreach ($personeller as $p): ?>
                                    <option value="<?= $p->id ?>"><?= htmlspecialchars($p->adi_soyadi) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Aksiyon Butonları -->
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-light btn-sm dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                İşlemler <i class="mdi mdi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow">
                                <a class="dropdown-item py-2 fw-medium text-warning" href="javascript:void(0);" id="btnZimmetVer" data-bs-toggle="modal" data-bs-target="#zimmetModal">
                                    <i class="bx bx-transfer me-2"></i> Yeni Zimmet Ver
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item py-2 fw-medium text-success" href="javascript:void(0);" id="topluIadeBtn">
                                    <i class="bx bx-undo me-2"></i> Toplu İade Al
                                </a>
                                <a class="dropdown-item py-2 fw-medium text-danger" href="javascript:void(0);" id="topluZimmetSilBtn">
                                    <i class="bx bx-trash me-2"></i> Toplu Zimmet Sil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="zimmetTable" class="table table-bordered table-hover dt-responsive nowrap w-100 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20px;" class="align-middle text-center">
                                <div class="custom-checkbox-container">
                                    <input type="checkbox" class="custom-checkbox-input" id="checkAllZimmet">
                                    <label class="custom-checkbox-label" for="checkAllZimmet"></label>
                                </div>
                            </th>
                            <th class="text-center">No</th>
                            <th>Kategori</th>
                            <th>Cihaz / Demirbaş</th>
                            <th>Marka / Model</th>
                            <th>Zimmetli Personel</th>
                            <th class="text-center">Miktar</th>
                            <th>Teslim Tarihi</th>
                            <th class="text-center">Durum</th>
                            <th class="text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Veriler AJAX ile gelecek -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
// Modalleri dahil et
include_once "modal/zimmet-modal.php"; 
include_once "modal/demirbas-hareket-modal.php";
?>

<script src="views/demirbas/js/demirbas.js?v=<?= time() ?>"></script>
<script src="views/demirbas/js/zimmet.js?v=<?= time() ?>"></script>
