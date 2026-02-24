<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;

use App\Model\DemirbasModel;
use App\Model\DemirbasKategoriModel;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasHareketModel;
use App\Model\PersonelModel;

$Demirbas = new DemirbasModel();
$Kategori = new DemirbasKategoriModel();
$Zimmet = new DemirbasZimmetModel();
$Hareket = new DemirbasHareketModel();
$Personel = new PersonelModel();
$Tanimlamalar = new \App\Model\TanimlamalarModel();

$demirbaslar = $Demirbas->getAllWithCategory();
$kategoriler = $Kategori->getActiveCategories();
$personeller = $Personel->all();
$stokOzeti = $Demirbas->getStockSummary();
$zimmetStats = $Zimmet->getStats();

// Otomatik zimmet ayarı yapılmış demirbaşları getir
$sqlAyarlar = $Demirbas->db->prepare("SELECT * FROM demirbas WHERE (otomatik_zimmet_is_emri IS NOT NULL OR otomatik_iade_is_emri IS NOT NULL)");
$sqlAyarlar->execute();
$ayarYapilmisDemirbaslar = $sqlAyarlar->fetchAll(PDO::FETCH_OBJ);

// ====== SAYAÇ DEPOSU İSTATİSTİKLERİ ======
// Sayaç kategorisindeki demirbaşları bul
$sqlSayacKat = $Demirbas->db->prepare("SELECT id FROM demirbas_kategorileri WHERE LOWER(kategori_adi) LIKE '%sayaç%' OR LOWER(kategori_adi) LIKE '%sayac%'");
$sqlSayacKat->execute();
$sayacKatIds = $sqlSayacKat->fetchAll(PDO::FETCH_COLUMN);

// Depo özeti: Yeni ve Hurda sayaçların depot stoku
$depoOzet = (object) ['yeni_depoda' => 0, 'hurda_depoda' => 0, 'yeni_personelde' => 0, 'hurda_personelde' => 0, 'toplam_kasiye_teslim' => 0];
if (!empty($sayacKatIds)) {
    $katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));

    // Ana depodaki stok (kalan_miktar = zimmetlenmemiş adet)
    $sqlDepo = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(durum) != 'hurda' THEN kalan_miktar ELSE 0 END), 0) as yeni_depoda,
            COALESCE(SUM(CASE WHEN LOWER(durum) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_depoda
        FROM demirbas 
        WHERE kategori_id IN ($katPlaceholders)
    ");
    $sqlDepo->execute($sayacKatIds);
    $depoResult = $sqlDepo->fetch(PDO::FETCH_OBJ);
    $depoOzet->yeni_depoda = $depoResult->yeni_depoda ?? 0;
    $depoOzet->hurda_depoda = $depoResult->hurda_depoda ?? 0;

    // Personeldeki stok (aktif zimmetler)
    $sqlPersonelde = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(d.durum) != 'hurda' THEN z.teslim_miktar ELSE 0 END), 0) as yeni_personelde,
            COALESCE(SUM(CASE WHEN LOWER(d.durum) = 'hurda' THEN z.teslim_miktar ELSE 0 END), 0) as hurda_personelde
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        WHERE z.durum = 'teslim' AND d.kategori_id IN ($katPlaceholders)
    ");
    $sqlPersonelde->execute($sayacKatIds);
    $personeldeResult = $sqlPersonelde->fetch(PDO::FETCH_OBJ);
    $depoOzet->yeni_personelde = $personeldeResult->yeni_personelde ?? 0;
    $depoOzet->hurda_personelde = $personeldeResult->hurda_personelde ?? 0;

    // Personel bazlı dağılım tablosu
    $sqlPersonelDagilim = $Demirbas->db->prepare("
        SELECT 
            p.id as personel_id,
            p.adi_soyadi,
            COALESCE(SUM(CASE WHEN LOWER(d.durum) != 'hurda' AND z.durum = 'teslim' THEN z.teslim_miktar ELSE 0 END), 0) as yeni_adet,
            COALESCE(SUM(CASE WHEN LOWER(d.durum) = 'hurda' AND z.durum = 'teslim' THEN z.teslim_miktar ELSE 0 END), 0) as hurda_adet
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        INNER JOIN personel p ON z.personel_id = p.id
        WHERE d.kategori_id IN ($katPlaceholders) AND z.durum = 'teslim'
        GROUP BY p.id, p.adi_soyadi
        HAVING yeni_adet > 0 OR hurda_adet > 0
        ORDER BY p.adi_soyadi
    ");
    $sqlPersonelDagilim->execute($sayacKatIds);
    $personelDagilim = $sqlPersonelDagilim->fetchAll(PDO::FETCH_OBJ);

    // Hurda demirbaşları listele (Kaskiye teslim için)
    $sqlHurdaDemirbaslar = $Demirbas->db->prepare("
        SELECT d.id, d.demirbas_adi, d.seri_no, d.kalan_miktar, d.miktar, k.kategori_adi
        FROM demirbas d
        LEFT JOIN demirbas_kategorileri k ON d.kategori_id = k.id
        WHERE d.kategori_id IN ($katPlaceholders) AND LOWER(d.durum) = 'hurda' AND d.kalan_miktar > 0
        ORDER BY d.demirbas_adi
    ");
    $sqlHurdaDemirbaslar->execute($sayacKatIds);
    $hurdaDemirbaslar = $sqlHurdaDemirbaslar->fetchAll(PDO::FETCH_OBJ);
} else {
    $personelDagilim = [];
    $hurdaDemirbaslar = [];
}
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
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <!-- Tab Navigation -->
                            <?php
                            $activeTab = $_GET['tab'] ?? 'demirbas';
                            ?>
                            <ul class="nav nav-pills" id="demirbasTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $activeTab === 'demirbas' ? 'active' : ''; ?>"
                                        id="demirbas-tab" data-bs-toggle="tab" data-bs-target="#demirbasContent"
                                        type="button" role="tab">
                                        <i class="bx bx-package me-1"></i> Demirbaş Listesi
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $activeTab === 'zimmet' ? 'active' : ''; ?>"
                                        id="zimmet-tab" data-bs-toggle="tab" data-bs-target="#zimmetContent"
                                        type="button" role="tab">
                                        <i class="bx bx-transfer me-1"></i> Zimmet Kayıtları
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $activeTab === 'depo' ? 'active' : ''; ?>"
                                        id="depo-tab" data-bs-toggle="tab" data-bs-target="#depoContent" type="button"
                                        role="tab">
                                        <i class="bx bx-store-alt me-1"></i> Sayaç Deposu
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Butonlar -->
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                            <div class="dropdown">
                                <button
                                    class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle text-dark d-flex align-items-center"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bx bx-menu me-1 fs-5"></i> İşlemler
                                    <i class="bx bx-chevron-down ms-1"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                    <li>
                                        <a class="dropdown-item py-2" href="javascript:void(0);" id="exportExcel">
                                            <i class="bx bx-spreadsheet me-2 text-success fs-5"></i> Excel'e Aktar
                                        </a>
                                    </li>
                                    <li id="importExcelLi"
                                        class="<?php echo $activeTab !== 'demirbas' ? 'd-none' : ''; ?>">
                                        <a class="dropdown-item py-2" href="javascript:void(0);" id="importExcel">
                                            <i class="bx bx-upload me-2 text-primary fs-5"></i> Excel'den Yükle
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                            <!-- Tab Bağımlı Ana Butonlar -->
                            <button type="button" id="btnYeniDemirbas"
                                class="btn btn-success btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'demirbas' ? 'd-flex' : 'd-none'; ?>"
                                data-bs-toggle="modal" data-bs-target="#demirbasModal">
                                <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Demirbaş
                            </button>
                            <button type="button" id="btnZimmetVer"
                                class="btn btn-warning btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'zimmet' ? 'd-flex' : 'd-none'; ?>"
                                data-bs-toggle="modal" data-bs-target="#zimmetModal">
                                <i class="bx bx-transfer-alt fs-5 me-1"></i> Zimmet Ver
                            </button>
                            <button type="button" id="btnKasiyeTeslim"
                                class="btn btn-danger btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'depo' ? 'd-flex' : 'd-none'; ?>"
                                data-bs-toggle="modal" data-bs-target="#kasiyeTeslimModal">
                                <i class="bx bx-log-out-circle fs-5 me-1"></i> Kaskiye Teslim
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="demirbasTabContent">

                        <!-- Demirbaş Listesi Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'demirbas' ? 'show active' : ''; ?>"
                            id="demirbasContent" role="tabpanel">

                            <!-- Özet Kartlar -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                                                    <i class="bx bx-cube fs-4 text-primary"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">DEMİRBAŞ</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM
                                                ÇEŞİT</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $stokOzeti->toplam_cesit ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(52, 195, 143, 0.1);">
                                                    <i class="bx bx-check-circle fs-4 text-success"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">STOK</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">STOKTA
                                                KALAN</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $stokOzeti->stokta_kalan ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(241, 180, 76, 0.1);">
                                                    <i class="bx bx-transfer fs-4 text-warning"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">ZİMMET</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">AKTİF
                                                ZİMMETLİ</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $zimmetStats->aktif_zimmet ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="demirbasTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
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
                                                    <td>
                                                        <div>
                                                            <?php echo ($demirbas->marka ?? '-') . ' ' . ($demirbas->model ?? '') ?>
                                                        </div>
                                                        <small
                                                            class="text-muted"><?php echo $demirbas->seri_no ? 'SN: ' . $demirbas->seri_no : '' ?></small>
                                                    </td>
                                                    <td class="text-center"><?php echo $stokBadge ?></td>
                                                    <td class="text-end">
                                                        <?php echo ($demirbas->edinme_tutari ?? 0) ?>
                                                    </td>
                                                    <td><?php echo $demirbas->edinme_tarihi ?? '-' ?></td>
                                                    <td class="text-left text-nowrap">
                                                        <?php if ($kalan > 0): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-soft-warning waves-effect waves-light zimmet-ver"
                                                                data-id="<?php echo $enc_id; ?>"
                                                                data-name="<?php echo $demirbas->demirbas_adi; ?>"
                                                                data-kalan="<?php echo $kalan; ?>" title="Zimmet Ver">
                                                                <i class="bx bx-transfer"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button"
                                                            class="btn btn-sm btn-soft-primary waves-effect waves-light duzenle"
                                                            data-id="<?php echo $enc_id; ?>" title="Düzenle">
                                                            <i class="bx bx-edit"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-soft-danger waves-effect waves-light demirbas-sil"
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
                        <div class="tab-pane fade <?php echo $activeTab === 'zimmet' ? 'show active' : ''; ?>"
                            id="zimmetContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="zimmetTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">ID</th>
                                            <th style="width:12%">Kategori</th>
                                            <th style="width:20%">Demirbaş</th>
                                            <th style="width:15%">Marka/Model</th>
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

                        <!-- Sayaç Deposu Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'depo' ? 'show active' : ''; ?>"
                            id="depoContent" role="tabpanel">

                            <!-- Depo Özet Kartları -->
                            <div class="row g-3 mb-4">
                                <!-- Ana Depo (Yeni) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                                                    <i class="bx bx-package fs-4 text-primary"></i>
                                                </div>
                                                <span class="text-muted small fw-bold" style="font-size: 0.65rem;">ANA
                                                    STOK</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">ANA DEPODA (YENİ)</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $depoOzet->yeni_depoda; ?> <span
                                                    style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>

                                <!-- Personelde (Yeni) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(52, 195, 143, 0.1);">
                                                    <i class="bx bx-user-check fs-4 text-success"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">ZİMMET</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">PERSONELDE (YENİ)</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $depoOzet->yeni_personelde; ?> <span
                                                    style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hurda Depoda -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(241, 180, 76, 0.1);">
                                                    <i class="bx bx-recycle fs-4 text-warning"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">HURDA</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">HURDA DEPODA</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $depoOzet->hurda_depoda; ?> <span
                                                    style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>

                                <!-- Personelde (Hurda) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #50a5f1; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(80, 165, 241, 0.1);">
                                                    <i class="bx bx-user-minus fs-4 text-info"></i>
                                                </div>
                                                <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İADE
                                                    BEKLEYEN</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">PERSONELDE (HURDA)</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading">
                                                <?php echo $depoOzet->hurda_personelde; ?> <span
                                                    style="font-size: 0.85rem; font-weight: 600;">Adet</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Personel Bazlı Stok Dağılımı -->
                            <div class="card border shadow-none mb-3">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0"><i class="bx bx-group text-primary me-2"></i>Personel Bazlı Sayaç
                                        Dağılımı</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-demirbas table-hover table-bordered mb-0"
                                            id="depoPersonelTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:5%" class="text-center">#</th>
                                                    <th style="width:35%">Personel</th>
                                                    <th style="width:20%" class="text-center">Yeni Sayaç</th>
                                                    <th style="width:20%" class="text-center">Hurda Sayaç</th>
                                                    <th style="width:20%" class="text-center">Toplam</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($personelDagilim)): ?>
                                                    <?php $sira = 0;
                                                    foreach ($personelDagilim as $pd):
                                                        $sira++; ?>
                                                        <tr>
                                                            <td class="text-center"><?php echo $sira; ?></td>
                                                            <td>
                                                                <i class="bx bx-user text-muted me-1"></i>
                                                                <?php echo htmlspecialchars($pd->adi_soyadi); ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if ($pd->yeni_adet > 0): ?>
                                                                    <span
                                                                        class="badge bg-success"><?php echo $pd->yeni_adet; ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if ($pd->hurda_adet > 0): ?>
                                                                    <span
                                                                        class="badge bg-danger"><?php echo $pd->hurda_adet; ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <span
                                                                    class="badge bg-primary"><?php echo $pd->yeni_adet + $pd->hurda_adet; ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <?php if (!empty($personelDagilim)): ?>
                                                <tfoot class="table-light">
                                                    <tr class="fw-bold">
                                                        <td></td>
                                                        <td>TOPLAM</td>
                                                        <td class="text-center">
                                                            <span
                                                                class="badge bg-success fs-6"><?php echo $depoOzet->yeni_personelde; ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span
                                                                class="badge bg-danger fs-6"><?php echo $depoOzet->hurda_personelde; ?></span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span
                                                                class="badge bg-primary fs-6"><?php echo $depoOzet->yeni_personelde + $depoOzet->hurda_personelde; ?></span>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            <?php endif; ?>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Hurda Depodaki Sayaçlar -->
                            <?php if (!empty($hurdaDemirbaslar)): ?>
                                <div class="card border shadow-none mb-0">
                                    <div class="card-header bg-danger bg-opacity-10 py-2">
                                        <h6 class="mb-0 text-danger"><i class="bx bx-recycle me-2"></i>Hurda Depodaki
                                            Sayaçlar (Kaskiye Teslime Hazır)</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-bordered mb-0" id="hurdaDemirbasTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-center" style="width:5%">#</th>
                                                        <th>Sayaç Adı</th>
                                                        <th>Seri No</th>
                                                        <th class="text-center">Depodaki Adet</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php $hs = 0;
                                                    foreach ($hurdaDemirbaslar as $hd):
                                                        $hs++; ?>
                                                        <tr>
                                                            <td class="text-center"><?php echo $hs; ?></td>
                                                            <td><?php echo htmlspecialchars($hd->demirbas_adi); ?></td>
                                                            <td><?php echo $hd->seri_no ?? '-'; ?></td>
                                                            <td class="text-center">
                                                                <span
                                                                    class="badge bg-danger"><?php echo $hd->kalan_miktar; ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #demirbasTable tbody tr,
    #zimmetTable tbody tr,
    #depoPersonelTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #demirbasTable tbody tr:hover,
    #zimmetTable tbody tr:hover,
    #depoPersonelTable tbody tr:hover {
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

<!-- Kaskiye Teslim Modal -->
<?php include_once "modal/kasiye-teslim-modal.php" ?>

<!-- Excel Import Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importExcelModalLabel">Excel'den Demirbaş Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Excel Dosyası Seçin (.xlsx, .xls)</label>
                        <input class="form-control" type="file" id="excelFile" name="excelFile" accept=".xlsx, .xls">
                    </div>
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-1"></i> Lütfen uygun şablonu kullandığınızdan emin olun.
                        <br>
                        <a href="views/demirbas/download-template.php" class="alert-link">Örnek Şablonu İndir</a>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnUploadExcel">Yükle</button>
            </div>
        </div>
    </div>
</div>

<script src="views/demirbas/js/demirbas.js"></script>