<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;

use App\Model\DemirbasModel;
use App\Model\DemirbasKategoriModel;
use App\Model\DemirbasZimmetModel;
use App\Model\PersonelModel;

$Demirbas = new DemirbasModel();
$Kategori = new DemirbasKategoriModel();
$Zimmet = new DemirbasZimmetModel();
$Personel = new PersonelModel();

$demirbaslar = $Demirbas->getAllWithCategory();
$kategoriler = $Kategori->getActiveCategories();
$personeller = $Personel->all();
$stokOzeti = $Demirbas->getStockSummary();
$zimmetStats = $Zimmet->getStats();

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Demirbaş";
    $title = "Demirbaş & Zimmet Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-pills" id="demirbasTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="demirbas-tab" data-bs-toggle="tab"
                                    data-bs-target="#demirbasContent" type="button" role="tab">
                                    <i class="bx bx-package me-1"></i> Demirbaş Listesi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="zimmet-tab" data-bs-toggle="tab"
                                    data-bs-target="#zimmetContent" type="button" role="tab">
                                    <i class="bx bx-transfer me-1"></i> Zimmet Kayıtları
                                </button>
                            </li>
                        </ul>

                        <div class="vr mx-2 d-none d-md-block"></div>

                        <!-- İstatistikler -->
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-primary-subtle text-primary fs-6">
                                <i class="bx bx-cube me-1"></i> Çeşit: <?php echo $stokOzeti->toplam_cesit ?? 0; ?>
                            </span>
                            <span class="badge bg-success-subtle text-success fs-6">
                                <i class="bx bx-check-circle me-1"></i> Stokta:
                                <?php echo $stokOzeti->stokta_kalan ?? 0; ?>
                            </span>
                            <span class="badge bg-warning-subtle text-warning fs-6">
                                <i class="bx bx-transfer me-1"></i> Zimmetli:
                                <?php echo $zimmetStats->aktif_zimmet ?? 0; ?>
                            </span>
                        </div>

                        <!-- Butonlar -->
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button type="button" class="btn btn-success waves-effect waves-light"
                                data-bs-toggle="modal" data-bs-target="#demirbasModal">
                                <i class="bx bx-plus me-1"></i> Yeni Demirbaş
                            </button>
                            <button type="button" class="btn btn-warning waves-effect waves-light"
                                data-bs-toggle="modal" data-bs-target="#zimmetModal">
                                <i class="bx bx-transfer me-1"></i> Zimmet Ver
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="demirbasTabContent">

                        <!-- Demirbaş Listesi Tab -->
                        <div class="tab-pane fade show active" id="demirbasContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="demirbasTable"
                                    class="table datatables table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:8%" class="text-center">D.No</th>
                                            <th style="width:12%">Kategori</th>
                                            <th style="width:20%">Demirbaş Adı</th>
                                            <th style="width:15%">Marka/Model</th>
                                            <th style="width:10%" class="text-center">Stok</th>
                                            <th style="width:10%" class="text-end">Edinme Tutarı</th>
                                            <th style="width:10%">Edinme Tarihi</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($demirbaslar)): ?>
                                            <?php
                                            $i = 0;
                                            foreach ($demirbaslar as $demirbas) {
                                                $i++;
                                                $enc_id = Security::encrypt($demirbas->id);
                                                $miktar = $demirbas->miktar ?? 1;
                                                $kalan = $demirbas->kalan_miktar ?? 1;

                                                // Stok durumu badge
                                                if ($kalan == 0) {
                                                    $stokBadge = '<span class="badge bg-danger">Stok Yok</span>';
                                                } elseif ($kalan < $miktar) {
                                                    $stokBadge = '<span class="badge bg-warning">' . $kalan . '/' . $miktar . '</span>';
                                                } else {
                                                    $stokBadge = '<span class="badge bg-success">' . $kalan . '/' . $miktar . '</span>';
                                                }
                                                ?>
                                                <tr data-id="<?php echo $enc_id ?>">
                                                    <td class="text-center"><?php echo $i ?></td>
                                                    <td class="text-center"><?php echo $demirbas->demirbas_no ?? '-' ?></td>
                                                    <td>
                                                        <span class="badge bg-soft-primary text-primary">
                                                            <?php echo $demirbas->kategori_adi ?? 'Kategorisiz' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="#" data-id="<?php echo $enc_id; ?>"
                                                            class="text-dark duzenle fw-medium">
                                                            <?php echo $demirbas->demirbas_adi ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo ($demirbas->marka ?? '-') . ' ' . ($demirbas->model ?? '') ?>
                                                    </td>
                                                    <td class="text-center"><?php echo $stokBadge ?></td>
                                                    <td class="text-end">
                                                        <?php echo ($demirbas->edinme_tutari ?? 0) ?>
                                                    </td>
                                                    <td><?php echo $demirbas->edinme_tarihi ?? '-' ?></td>
                                                    <td class="text-left text-nowrap">
                                                        <?php if ($kalan > 0): ?>
                                                            <button type="button" class="btn btn-sm btn-warning zimmet-ver"
                                                                data-id="<?php echo $enc_id; ?>"
                                                                data-name="<?php echo $demirbas->demirbas_adi; ?>"
                                                                data-kalan="<?php echo $kalan; ?>" title="Zimmet Ver">
                                                                <i class="bx bx-transfer"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-primary duzenle"
                                                            data-id="<?php echo $enc_id; ?>" title="Düzenle">
                                                            <i class="bx bx-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger demirbas-sil"
                                                            data-id="<?php echo $enc_id; ?>"
                                                            data-name="<?php echo $demirbas->demirbas_adi; ?>" title="Sil">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Zimmet Kayıtları Tab -->
                        <div class="tab-pane fade" id="zimmetContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="zimmetTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">ID</th>
                                            <th style="width:12%">Kategori</th>
                                            <th style="width:20%">Demirbaş</th>
                                            <th style="width:18%">Personel</th>
                                            <th style="width:8%" class="text-center">Miktar</th>
                                            <th style="width:12%">Teslim Tarihi</th>
                                            <th style="width:10%" class="text-center">Durum</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="zimmetTableBody">
                                        <!-- Zimmet verileri JavaScript ile yüklenecek -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #demirbasTable tbody tr,
    #zimmetTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #demirbasTable tbody tr:hover,
    #zimmetTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .nav-pills .nav-link.active {
        background-color: #556ee6;
    }

    <div class="container-fluid">< !-- start page title -->
    <?php
    $maintitle = "Demirbaş";
    $title = "Demirbaş & Zimmet Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    < !-- end page title --><div class="row"><div class="col-12"><div class="card"><div class="card-header"><div class="d-flex flex-wrap align-items-center gap-2">< !-- Tab Navigation --><ul class="nav nav-pills" id="demirbasTab" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="demirbas-tab" data-bs-toggle="tab"
    data-bs-target="#demirbasContent" type="button" role="tab"><i class="bx bx-package me-1"></i>Demirbaş Listesi </button></li><li class="nav-item" role="presentation"><button class="nav-link" id="zimmet-tab" data-bs-toggle="tab"
    data-bs-target="#zimmetContent" type="button" role="tab"><i class="bx bx-transfer me-1"></i>Zimmet Kayıtları </button></li></ul><div class="vr mx-2 d-none d-md-block"></div>< !-- İstatistikler --><div class="d-flex flex-wrap gap-2 align-items-center"><span class="badge bg-primary-subtle text-primary fs-6"><i class="bx bx-cube me-1"></i>Çeşit:
    <?php echo $stokOzeti->toplam_cesit ?? 0; ?>
    </span><span class="badge bg-success-subtle text-success fs-6"><i class="bx bx-check-circle me-1"></i>Stokta:
    <?php echo $stokOzeti->stokta_kalan ?? 0; ?>
    </span><span class="badge bg-warning-subtle text-warning fs-6"><i class="bx bx-transfer me-1"></i>Zimmetli:
    <?php echo $zimmetStats->aktif_zimmet ?? 0; ?>
    </span></div>< !-- Butonlar --><div class="d-flex flex-wrap gap-2 ms-auto"><button type="button" class="btn btn-success waves-effect waves-light"
    data-bs-toggle="modal" data-bs-target="#demirbasModal"><i class="bx bx-plus me-1"></i>Yeni Demirbaş </button><button type="button" class="btn btn-warning waves-effect waves-light"
    data-bs-toggle="modal" data-bs-target="#zimmetModal"><i class="bx bx-transfer me-1"></i>Zimmet Ver </button></div></div></div><div class="card-body"><div class="tab-content" id="demirbasTabContent">< !-- Demirbaş Listesi Tab --><div class="tab-pane fade show active" id="demirbasContent" role="tabpanel"><div class="table-responsive"><table id="demirbasTable"
    class="table datatables table-hover table-bordered nowrap w-100"><thead class="table-light"><tr><th class="text-center" style="width:5%">Sıra</th><th style="width:8%" class="text-center">D.No</th><th style="width:12%">Kategori</th><th style="width:20%">Demirbaş Adı</th><th style="width:15%">Marka/Model</th><th style="width:10%" class="text-center">Stok</th><th style="width:10%" class="text-end">Edinme Tutarı</th><th style="width:10%">Edinme Tarihi</th><th style="width:5%" class="text-center">İşlemler</th></tr></thead><tbody>
    <?php if (!empty($demirbaslar)): ?>
        <?php
        $i = 0;
        foreach ($demirbaslar as $demirbas) {
            $i++;
            $enc_id = Security::encrypt($demirbas->id);
            $miktar = $demirbas->miktar ?? 1;
            $kalan = $demirbas->kalan_miktar ?? 1;

            // Stok durumu badge
            if ($kalan == 0) {
                $stokBadge = '<span class="badge bg-danger">Stok Yok</span>';
            } elseif ($kalan < $miktar) {
                $stokBadge = '<span class="badge bg-warning">' . $kalan . '/' . $miktar . '</span>';
            } else {
                $stokBadge = '<span class="badge bg-success">' . $kalan . '/' . $miktar . '</span>';
            }
            ?>
            <tr data-id="<?php echo $enc_id ?>"><td class="text-center"><?php echo $i ?></td><td class="text-center"><?php echo $demirbas->demirbas_no ?? '-' ?></td><td><span class="badge bg-soft-primary text-primary">
            <?php echo $demirbas->kategori_adi ?? 'Kategorisiz' ?>
            </span></td><td><a href="#" data-id="<?php echo $enc_id; ?>"
            class="text-dark duzenle fw-medium">
            <?php echo $demirbas->demirbas_adi ?>
            </a></td><td><?php echo ($demirbas->marka ?? '-') . ' ' . ($demirbas->model ?? '') ?> </td><td class="text-center"><?php echo $stokBadge ?></td><td class="text-end">
            <?php echo ($demirbas->edinme_tutari ?? 0) ?>
            </td><td><?php echo $demirbas->edinme_tarihi ?? '-' ?></td><td class="text-left text-nowrap">
            <?php if ($kalan > 0): ?>
                <button type="button" class="btn btn-sm btn-warning zimmet-ver"
                data-id="<?php echo $enc_id; ?>"
                data-name="<?php echo $demirbas->demirbas_adi; ?>"
                data-kalan="<?php echo $kalan; ?>" title="Zimmet Ver"><i class="bx bx-transfer"></i></button>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-primary duzenle"
            data-id="<?php echo $enc_id; ?>" title="Düzenle"><i class="bx bx-edit"></i></button><button type="button" class="btn btn-sm btn-danger demirbas-sil"
            data-id="<?php echo $enc_id; ?>"
            data-name="<?php echo $demirbas->demirbas_adi; ?>" title="Sil"><i class="bx bx-trash"></i></button></td></tr>

        <?php } ?>
    <?php endif; ?>
    </tbody></table></div></div>< !-- Zimmet Kayıtları Tab --><div class="tab-pane fade" id="zimmetContent" role="tabpanel"><div class="table-responsive"><table id="zimmetTable" class="table table-hover table-bordered nowrap w-100"><thead class="table-light"><tr><th class="text-center" style="width:5%">ID</th><th style="width:12%">Kategori</th><th style="width:20%">Demirbaş</th><th style="width:18%">Personel</th><th style="width:8%" class="text-center">Miktar</th><th style="width:12%">Teslim Tarihi</th><th style="width:10%" class="text-center">Durum</th><th style="width:5%" class="text-center">İşlemler</th></tr></thead><tbody id="zimmetTableBody">< !-- Zimmet verileri JavaScript ile yüklenecek --></tbody></table></div></div></div></div></div></div></div></div><style>#demirbasTable tbody tr,
    #zimmetTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #demirbasTable tbody tr:hover,
    #zimmetTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .nav-pills .nav-link.active {
        background-color: #556ee6;
    }

    .nav-pills .nav-link {
        color: #495057;
    }
</style>

<!-- Demirbaş Modal -->
<?php include_once "modal/general-modal.php" ?>

<!-- Zimmet Modal -->
<?php include_once "modal/zimmet-modal.php" ?>

<!-- İade Modal -->
<?php include_once "modal/iade-modal.php" ?>

<!-- Zimmet Detay Modal -->
<?php include_once "modal/zimmet-detay-modal.php" ?>

<script src="views/demirbas/js/demirbas.js"></script>