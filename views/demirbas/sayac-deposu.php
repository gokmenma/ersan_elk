<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Model\DemirbasModel;
use App\Model\TanimlamalarModel;
use App\Model\PersonelModel;

$Demirbas = new DemirbasModel();
$Tanimlamalar = new TanimlamalarModel();
$Personel = new PersonelModel();

// ====== SAYAÇ KATEGORİ ID'LERİ ======
$sayacKatIds = [];
$tumKategoriler = $Tanimlamalar->getDemirbasKategorileri();
foreach ($tumKategoriler as $kat) {
    if (str_contains(mb_strtolower($kat->tur_adi, 'UTF-8'), 'sayac') || str_contains(mb_strtolower($kat->tur_adi, 'UTF-8'), 'sayaç')) {
        $sayacKatIds[] = (string) $kat->id;
    }
}

// Global Depo Stok Bilgileri
$depoOzet = (object) ['yeni_depoda' => 0, 'hurda_depoda' => 0, 'yeni_personelde' => 0, 'hurda_personelde' => 0];
if (!empty($sayacKatIds)) {
    $katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));
    $paramArr = $sayacKatIds;
    $paramArr[] = $_SESSION['firma_id'];

    // Ana depodaki stok
    $sqlDepo = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(durum) != 'hurda' AND LOWER(durum) != 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as yeni_depoda,
            COALESCE(SUM(CASE WHEN LOWER(durum) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_depoda
        FROM demirbas 
        WHERE kategori_id IN ($katPlaceholders) AND firma_id = ?
    ");
    $sqlDepo->execute($paramArr);
    $depoResult = $sqlDepo->fetch(PDO::FETCH_OBJ);
    $depoOzet->yeni_depoda = $depoResult->yeni_depoda ?? 0;
    $depoOzet->hurda_depoda = $depoResult->hurda_depoda ?? 0;

    // Personeldeki stok (aktif zimmetler)
    $sqlPersonelde = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi' THEN z.teslim_miktar ELSE 0 END), 0) as yeni_personelde,
            COALESCE(SUM(CASE WHEN LOWER(d.durum) = 'hurda' THEN z.teslim_miktar ELSE 0 END), 0) as hurda_personelde
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        WHERE z.durum = 'teslim' AND d.kategori_id IN ($katPlaceholders) AND d.firma_id = ?
    ");
    $sqlPersonelde->execute($paramArr);
    $personeldeResult = $sqlPersonelde->fetch(PDO::FETCH_OBJ);
    $depoOzet->yeni_personelde = $personeldeResult->yeni_personelde ?? 0;
    $depoOzet->hurda_personelde = $personeldeResult->hurda_personelde ?? 0;
}
?>

<div class="container-fluid">

    <?php
    $maintitle = "Demirbaş";
    $title = "Sayaç Deposu";
    include 'layouts/breadcrumb.php';
    ?>

    <!-- Özet Kartları -->
    <div class="row g-2 mb-4">
        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(85, 110, 230, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-package fs-6 text-primary"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">DEPO (YENİ)</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $depoOzet->yeni_depoda ?></h5>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #34c38f; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(52, 195, 143, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-user-check fs-6 text-success"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">PERSONELDE (YENİ)</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $depoOzet->yeni_personelde ?></h5>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #f1b44c; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(241, 180, 76, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-recycle fs-6 text-warning"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">DEPO (HURDA)</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $depoOzet->hurda_depoda ?></h5>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-3 col-sm-6">
            <div class="card border border-light shadow-none h-100 bordro-summary-card" style="--card-color: #50a5f1; border-bottom: 2px solid var(--card-color) !important;">
                <div class="card-body p-2 px-3">
                    <div class="icon-label-container">
                        <div class="icon-box" style="background: rgba(80, 165, 241, 0.1); width: 28px; height: 28px;">
                            <i class="bx bx-user-minus fs-6 text-info"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size: 0.5rem; opacity: 0.5;">PERSONELDE (HURDA)</span>
                    </div>
                    <h5 class="mb-0 fw-bold mt-1"><?= $depoOzet->hurda_personelde ?></h5>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-bottom-0 pb-0 bg-transparent">
            <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active fw-bold" data-bs-toggle="tab" href="#personel-tab" role="tab">
                        <span class="d-none d-sm-block"><i class="bx bx-group me-1"></i> Personel Bazlı Sayaç Durumu</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold" data-bs-toggle="tab" href="#sayaclar-tab" role="tab">
                        <span class="d-none d-sm-block"><i class="bx bx-package me-1"></i> Sayaç Listesi</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content text-muted">
                <!-- PERSONEL TAB -->
                <div class="tab-pane active" id="personel-tab" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold" style="color: #495057;">
                            <i class="bx bx-group me-1"></i> Personel Bazlı Sayaç Durumu
                        </h5>
                        <div>
                            <button type="button" class="btn btn-warning btn-sm fw-bold shadow-sm" id="btnPersonelAta">
                                <i class="bx bx-user-plus me-1"></i> Personele Sayaç Ver
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="sayacPersonelTable" class="table table-hover table-bordered align-middle nowrap w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Personel</th>
                                    <th class="text-center">Toplam Verilen</th>
                                    <th class="text-center">İade/Kullanılan</th>
                                    <th class="text-center text-primary fw-bold" style="background-color: rgba(85, 110, 230, 0.05);">Elinde Kalan</th>
                                    <th class="text-center" style="width: 10%;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via Ajax -->
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td class="text-end">GENEL TOPLAM:</td>
                                    <td class="text-center" id="footerToplamVerilen">0</td>
                                    <td class="text-center" id="footerToplamIade">0</td>
                                    <td class="text-center text-primary" id="footerElindeKalan">0</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- SAYAÇLAR TAB -->
                <div class="tab-pane" id="sayaclar-tab" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold" style="color: #495057;">
                            <i class="bx bx-package me-1"></i> Tüm Sayaçların Detaylı Listesi
                        </h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm fw-bold shadow-sm" id="btnYeniSayac" data-bs-toggle="modal" data-bs-target="#demirbasModal">
                                <i class="bx bx-plus me-1"></i> Yeni Sayaç Ekle
                            </button>
                            <button type="button" class="btn btn-info btn-sm fw-bold shadow-sm d-none" id="btnTopluKaskiyeTeslim">
                                <i class="bx bx-buildings me-1"></i> Toplu Kaskiye Teslim
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="sayacTable" class="table table-bordered table-hover dt-responsive nowrap w-100 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;" class="align-middle text-center">
                                        <div class="custom-checkbox-container">
                                            <input type="checkbox" class="custom-checkbox-input" id="checkAllSayac">
                                            <label class="custom-checkbox-label" for="checkAllSayac"></label>
                                        </div>
                                    </th>
                                    <th class="text-center">No</th>
                                    <th class="text-center">Demirbaş No</th>
                                    <th>Sayaç Adı</th>
                                    <th>Marka / Model</th>
                                    <th>Seri No</th>
                                    <th class="text-center">Stok Durumu</th>
                                    <th class="text-center">Öz. / Durum</th>
                                    <th>Kayıt T.</th>
                                    <th class="text-center">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via Ajax in demirbas.js -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Personel Detay -->
<div class="modal fade" id="sayacPersonelDetayModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-soft-info border-bottom">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bx bx-history text-info fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-info mb-0 fw-bold">Personel Sayaç Hareketleri</h6>
                        <p class="text-muted small mb-0" id="detayPersonelAdi" style="font-size: 0.7rem;">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table id="sayacPersonelDetayTable" class="table table-hover table-striped dt-responsive nowrap w-100 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>Tablo / Kategori</th>
                                <th>İşlem Tipi / Model</th>
                                <th>Seri No</th>
                                <th>Adet</th>
                                <th>Durum</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php 
// Modalleri dahil et
include_once "modal/zimmet-modal.php"; 
include_once "modal/general-modal.php"; 
include_once "modal/kasiye-teslim-modal.php"; 
?>

<script>
    var sayacKatIds = <?= json_encode($sayacKatIds) ?>;
</script>
<script src="views/demirbas/js/demirbas.js?v=<?= time() ?>"></script>
<script src="views/demirbas/js/sayac-deposu.js?v=<?= time() ?>"></script>
