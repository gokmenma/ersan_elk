<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Form;

use App\Model\DemirbasModel;
use App\Model\DemirbasZimmetModel;
use App\Model\DemirbasHareketModel;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Service\Gate;

$Demirbas = new DemirbasModel();
$Tanimlamalar = new TanimlamalarModel();
$Zimmet = new DemirbasZimmetModel();
$Hareket = new DemirbasHareketModel();
$Personel = new PersonelModel();

$tumDemirbaslar = $Demirbas->getAllWithCategory();
$kategoriler = $Tanimlamalar->getDemirbasKategorileri();
$personeller = $Personel->all(false, 'demirbas');
$zimmetStats = $Zimmet->getStats();

// Otomatik zimmet ayarı yapılmış demirbaşları getir
$sqlAyarlar = $Demirbas->db->prepare("SELECT * FROM demirbas WHERE (otomatik_zimmet_is_emri_ids IS NOT NULL OR otomatik_iade_is_emri_ids IS NOT NULL OR otomatik_zimmetten_dus_is_emri_ids IS NOT NULL) AND firma_id = ?");
$sqlAyarlar->execute([$_SESSION['firma_id']]);
$ayarYapilmisDemirbaslar = $sqlAyarlar->fetchAll(PDO::FETCH_OBJ);

// ====== SAYAÇ VE APARAT KATEGORİ ID'LERİ (Daha Sağlıklı Tespit) ======
$sayacKatIds = [];
$aparatKatIds = [];
$tumKategoriler = $Tanimlamalar->getDemirbasKategorileri();
foreach ($tumKategoriler as $kat) {
    $katAdiLower = mb_strtolower($kat->tur_adi, 'UTF-8');
    if (str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac')) {
        $sayacKatIds[] = (string) $kat->id;
    }
    if (str_contains($katAdiLower, 'aparat') || $kat->id == 645) {
        $aparatKatIds[] = (string) $kat->id;
    }
}

// ====== DEMİRBAŞ, SAYAÇ VE APARAT LİSTELERİNİ AYIR ======
$demirbaslar = [];
$sayaclar = [];
$aparatlar = [];
$stokOzeti = (object) ['toplam_cesit' => 0, 'toplam_adet' => 0, 'stokta_kalan' => 0, 'zimmetli_adet' => 0];

// Kategori bazlı envanter raporu için veriler
$kategoriEnvanteri = [];

foreach ($tumDemirbaslar as $item) {
    if (!empty($sayacKatIds) && in_array($item->kategori_id, $sayacKatIds)) {
        $sayaclar[] = $item;
    } elseif (!empty($aparatKatIds) && in_array($item->kategori_id, $aparatKatIds)) {
        $aparatlar[] = $item;
    } else {
        $demirbaslar[] = $item;

        $rowMiktar = (int)($item->miktar ?? 1);
        $rowKalan = (int)($item->kalan_miktar ?? 0);
        $rowKalan = ($rowKalan > $rowMiktar) ? $rowMiktar : $rowKalan; // Cap at miktar to prevent calculation errors
        $rowZimmetli = $rowMiktar - $rowKalan;

        $durum = strtolower($item->durum ?? 'aktif');
        $is_serviste = ($durum == 'arizali');
        $is_hurda = str_contains($durum, 'hurda');
        $is_active = (!$is_serviste && !$is_hurda);

        // Stok özetini genel hesapla
        $stokOzeti->toplam_cesit++;
        $stokOzeti->toplam_adet += $rowMiktar;
        
        if ($is_active) {
            $stokOzeti->stokta_kalan += $rowKalan;
            $stokOzeti->zimmetli_adet += $rowZimmetli;
        }

        // Kategori bazlı envantere ekle
        $katAdi = empty($item->kategori_adi) ? 'Kategorisiz' : $item->kategori_adi;
        if (!isset($kategoriEnvanteri[$katAdi])) {
            $kategoriEnvanteri[$katAdi] = [
                'cesit' => 0,
                'toplam' => 0,
                'bosta' => 0,
                'zimmetli' => 0,
                'serviste' => 0,
                'hurda' => 0
            ];
        }
        $kategoriEnvanteri[$katAdi]['cesit']++;
        $kategoriEnvanteri[$katAdi]['toplam'] += $rowMiktar;
        
        if ($is_active) {
            $kategoriEnvanteri[$katAdi]['bosta'] += $rowKalan;
            $kategoriEnvanteri[$katAdi]['zimmetli'] += $rowZimmetli;
        } elseif ($is_serviste) {
            $kategoriEnvanteri[$katAdi]['serviste'] += $rowMiktar;
        } elseif ($is_hurda) {
            $kategoriEnvanteri[$katAdi]['hurda'] += $rowMiktar;
        }
    }
}

// ====== SAYAÇ DEPOSU İSTATİSTİKLERİ ======
$depoOzet = (object) ['yeni_depoda' => 0, 'hurda_depoda' => 0, 'yeni_personelde' => 0, 'hurda_personelde' => 0];
if (!empty($sayacKatIds)) {
    $katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));

    // Ana depodaki stok (kalan_miktar = zimmetlenmemiş adet)
    $sqlDepo = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(durum) != 'hurda' AND LOWER(durum) != 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as yeni_depoda,
            COALESCE(SUM(CASE WHEN LOWER(durum) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_depoda
        FROM demirbas 
        WHERE kategori_id IN ($katPlaceholders) AND firma_id = ?
    ");
    $paramArr = $sayacKatIds;
    $paramArr[] = $_SESSION['firma_id'];
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

// ====== APARAT DEPOSU İSTATİSTİKLERİ ======
$aparatDepoOzet = (object) ['depoda' => 0, 'personelde' => 0, 'tuketilen' => 0, 'toplam_cesit' => 0];
if (!empty($aparatKatIds)) {
    $aparatPlaceholders = implode(',', array_fill(0, count($aparatKatIds), '?'));
    $aparatParamArr = $aparatKatIds;
    $aparatParamArr[] = $_SESSION['firma_id'];

    // Depodaki stok (kalan_miktar = zimmetlenmemiş adet)
    $sqlAparatDepo = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(kalan_miktar), 0) as depoda,
            COUNT(*) as toplam_cesit
        FROM demirbas 
        WHERE kategori_id IN ($aparatPlaceholders) AND firma_id = ?
    ");
    $sqlAparatDepo->execute($aparatParamArr);
    $aparatDepoResult = $sqlAparatDepo->fetch(PDO::FETCH_OBJ);
    $aparatDepoOzet->depoda = $aparatDepoResult->depoda ?? 0;
    $aparatDepoOzet->toplam_cesit = $aparatDepoResult->toplam_cesit ?? 0;

    // Personelde kalan (aktif zimmetler)
    $sqlAparatPersonelde = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM(z.teslim_miktar - (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL)), 0) as personelde
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        WHERE z.durum = 'teslim' AND d.kategori_id IN ($aparatPlaceholders) AND d.firma_id = ?
    ");
    $sqlAparatPersonelde->execute($aparatParamArr);
    $aparatPersoneldeResult = $sqlAparatPersonelde->fetch(PDO::FETCH_OBJ);
    $aparatDepoOzet->personelde = $aparatPersoneldeResult->personelde ?? 0;

    // Tüketilen (iade edilmiş = tüketildi olarak işaretlenen)
    $sqlAparatTuketilen = $Demirbas->db->prepare("
        SELECT 
            COALESCE(SUM((SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL)), 0) as tuketilen
        FROM demirbas_zimmet z
        INNER JOIN demirbas d ON z.demirbas_id = d.id
        WHERE d.kategori_id IN ($aparatPlaceholders) AND d.firma_id = ? AND (SELECT COALESCE(SUM(miktar), 0) FROM demirbas_hareketler WHERE zimmet_id = z.id AND hareket_tipi IN ('iade', 'sarf', 'kayip') AND silinme_tarihi IS NULL) > 0
    ");
    $sqlAparatTuketilen->execute($aparatParamArr);
    $aparatTuketilenResult = $sqlAparatTuketilen->fetch(PDO::FETCH_OBJ);
    $aparatDepoOzet->tuketilen = $aparatTuketilenResult->tuketilen ?? 0;
}
?>


<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Demirbaş";
    $title = "Demirbaşlar";
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
                            $routePage = $_GET['p'] ?? '';
                            $routeToTabMap = [
                                'demirbas/sayac-deposu' => 'depo',
                                'demirbas/aparat-deposu' => 'aparat',
                                'demirbas/servis' => 'servis',
                                'demirbas/zimmet' => 'zimmet'
                            ];
                            $activeTab = $_GET['tab'] ?? ($routeToTabMap[$routePage] ?? 'demirbas');
                            ?>
                            <ul class="nav nav-pills" id="demirbasTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $activeTab === 'demirbas' ? 'active' : ''; ?>"
                                        id="demirbas-tab" data-bs-toggle="tab" data-bs-target="#demirbasContent"
                                        type="button" role="tab">
                                        <i class="bx bx-package me-1"></i> Demirbaş Listesi
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <style>
                            .status-filter-group {
                                background: #f8f9fa;
                                padding: 4px;
                                border-radius: 50px;
                                border: 1px solid #e2e8f0;
                                display: inline-flex;
                                align-items: center;
                                gap: 2px;
                            }

                            [data-bs-theme="dark"] .status-filter-group {
                                background: #2a3042;
                                border-color: #32394e;
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
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                gap: 6px;
                                line-height: normal;
                                background: transparent !important;
                            }

                            [data-bs-theme="dark"] .status-filter-group .btn-check + .btn {
                                color: #a6b0cf;
                            }

                            .status-filter-group .btn-check + .btn i {
                                font-size: 0.95rem;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                margin-top: 1px;
                            }

                            .status-filter-group .btn-check + .btn:hover {
                                background: rgba(0, 0, 0, 0.04) !important;
                                color: #1e293b;
                            }

                            [data-bs-theme="dark"] .status-filter-group .btn-check + .btn:hover {
                                background: rgba(255, 255, 255, 0.05) !important;
                                color: #fff;
                            }

                            .status-filter-group .btn-check:checked + .btn {
                                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
                                color: white !important;
                            }

                            .status-filter-group .btn-check:checked + .btn[for*="all"] { background: #3b82f6 !important; }
                            .status-filter-group .btn-check:checked + .btn[for*="active"] { background: #f59e0b !important; }
                            .status-filter-group .btn-check:checked + .btn[for*="completed"] { background: #10b981 !important; }
                            .status-filter-group .btn-check:checked + .btn[for*="teslim"] { background: #f59e0b !important; }
                            .status-filter-group .btn-check:checked + .btn[for*="iade"] { background: #10b981 !important; }
                            .status-filter-group .btn-check:checked + .btn[for*="hurda"] { background: #ef4444 !important; }
                        </style>


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
                                    <li id="hurdaIadeLi" class="<?php echo $activeTab !== 'depo' ? 'd-none' : ''; ?>">
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li id="hurdaIadeButonLi"
                                        class="<?php echo $activeTab !== 'depo' ? 'd-none' : ''; ?>">
                                        <a class="dropdown-item py-2 fw-bold" href="javascript:void(0);"
                                            id="btnHurdaSayacIade" style="color: #ef4444;">
                                            <i class="bx bx-recycle me-2 fs-5" style="color: #ef4444;"></i> Hurda Sayaç
                                            İade Al
                                        </a>
                                    </li>
                                    <?php if (Gate::allows('demirbas_toplu_islem_sil')): ?>
                                        <li id="zimmetIslemlerDivider"
                                            class="<?php echo $activeTab !== 'zimmet' ? 'd-none' : ''; ?>">
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li id="topluIadeLi" class="<?php echo $activeTab !== 'zimmet' ? 'd-none' : ''; ?>">
                                            <a class="dropdown-item py-2 text-info fw-bold" href="javascript:void(0);"
                                                id="btnTopluIadeAl">
                                                <i class="bx bx-undo me-2 text-info fs-5"></i> Toplu İade Al
                                            </a>
                                        </li>
                                        <li id="topluZimmetSilLi"
                                            class="<?php echo $activeTab !== 'zimmet' ? 'd-none' : ''; ?>">
                                            <a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0);"
                                                id="btnTopluZimmetSil">
                                                <i class="bx bx-trash me-2 text-danger fs-5"></i> Toplu Zimmet Sil
                                            </a>
                                        </li>

                                        <li id="topluDemirbasSilLi" class="d-none">
                                            <a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0);"
                                                id="btnTopluDemirbasSil">
                                                <i class="bx bx-trash me-2 text-danger fs-5"></i> Seçilileri Sil
                                            </a>
                                        </li>
                                    <?php endif; ?>
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
                            <button type="button" id="btnYeniSayac"
                                class="btn btn-success btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'depo' ? 'd-flex' : 'd-none'; ?>"
                                data-bs-toggle="modal" data-bs-target="#demirbasModal">
                                <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Sayaç
                            </button>
                            <button type="button" id="btnTopluKaskiyeTeslim"
                                class="btn btn-info btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'depo' ? 'd-flex' : 'd-none'; ?>">
                                <i class="bx bx-buildings fs-5 me-1"></i> Toplu Kaskiye Teslim Et
                            </button>
                            <button type="button" id="btnYeniAparat"
                                class="btn btn-success btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'aparat' ? 'd-flex' : 'd-none'; ?>"
                                data-bs-toggle="modal" data-bs-target="#demirbasModal">
                                <i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Aparat
                            </button>
                            <button type="button" id="btnAparatPersoneleVer"
                                class="btn btn-warning btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'aparat' ? 'd-flex' : 'd-none'; ?>">
                                <i class="bx bx-user-plus fs-5 me-1"></i> Personele Ver
                            </button>

                            <button type="button" id="btnYeniServis"
                                class="btn btn-warning btn-sm px-3 py-2 fw-bold align-items-center shadow-sm ms-1 <?php echo $activeTab === 'servis' ? 'd-flex' : 'd-none'; ?>">
                                <i class="bx bx-wrench fs-5 me-1"></i> Yeni Servis Kaydı
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Preloader -->
                    <div class="personel-preloader" id="personel-loader">
                        <div class="loader-content">
                            <div class="spinner-border text-primary m-1" role="status">
                                <span class="sr-only">Yükleniyor...</span>
                            </div>
                            <h5 class="mt-2 mb-0">Demirbaş Listesi Hazırlanıyor...</h5>
                            <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                        </div>
                    </div>
                    <div class="tab-content" id="demirbasTabContent">

                        <!-- Demirbaş Listesi Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'demirbas' ? 'show active' : ''; ?>"
                            id="demirbasContent" role="tabpanel">

                            <!-- Özet Kartlar -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(85, 110, 230, 0.1); width: 32px; height: 32px;">
                                                    <i class="bx bx-cube fs-5 text-primary"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.55rem; opacity: 0.5;">DEMİRBAŞ</span>
                                            </div>
                                            <p class="text-muted mb-0 small fw-bold text-uppercase"
                                                style="letter-spacing: 0.5px; font-size: 0.65rem;">Toplam Çeşit</p>
                                            <h4 class="mb-0 fw-bold"><?php echo $stokOzeti->toplam_cesit ?? 0; ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(52, 195, 143, 0.1); width: 32px; height: 32px;">
                                                    <i class="bx bx-check-circle fs-5 text-success"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.55rem; opacity: 0.5;">STOK</span>
                                            </div>
                                            <p class="text-muted mb-0 small fw-bold text-uppercase"
                                                style="letter-spacing: 0.5px; font-size: 0.65rem;">Stokta Kalan</p>
                                            <h4 class="mb-0 fw-bold"><?php echo $stokOzeti->stokta_kalan ?? 0; ?></h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(241, 180, 76, 0.1); width: 32px; height: 32px;">
                                                    <i class="bx bx-transfer fs-5 text-warning"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.55rem; opacity: 0.5;">ZİMMET</span>
                                            </div>
                                            <p class="text-muted mb-0 small fw-bold text-uppercase"
                                                style="letter-spacing: 0.5px; font-size: 0.65rem;">Aktif Zimmetli</p>
                                            <h4 class="mb-0 fw-bold"><?php echo $zimmetStats->aktif_zimmet ?? 0; ?></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kategori Bazlı Rapor Akordiyon -->
                            <div class="accordion mb-4" id="inventoryAccordion">
                                <div class="accordion-item shadow-sm border-0 rounded-3 overflow-hidden">
                                    <h2 class="accordion-header" id="headingInventory">
                                        <button class="accordion-button collapsed bg-white fw-bold text-dark py-3"
                                            type="button" data-bs-toggle="collapse" data-bs-target="#collapseInventory"
                                            aria-expanded="false" aria-controls="collapseInventory"
                                            style="box-shadow: none;">
                                            <i class="bx bx-bar-chart-alt-2 fs-5 me-2 text-primary"></i>
                                            <span class="me-2">Kategori Bazlı Envanter Raporu</span>
                                            <div id="activeFilterBadges" class="d-flex gap-2"></div>
                                        </button>
                                    </h2>
                                    <div id="collapseInventory" class="accordion-collapse collapse"
                                        aria-labelledby="headingInventory" data-bs-parent="#inventoryAccordion">
                                        <div class="accordion-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="ps-3">Kategori</th>
                                                            <th class="text-center">Çeşit</th>
                                                            <th class="text-center">Toplam</th>
                                                            <th class="text-center">Boşta</th>
                                                            <th class="text-center">Zimmetli</th>
                                                            <th class="text-center">Serviste</th>
                                                            <th class="text-center">Hurda</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($kategoriEnvanteri)): ?>
                                                            <?php foreach ($kategoriEnvanteri as $katAdi => $stats): ?>
                                                                <tr>
                                                                    <td class="ps-3"><span
                                                                            class="badge bg-secondary"><?php echo htmlspecialchars($katAdi); ?></span>
                                                                    </td>
                                                                    <td class="text-center text-muted fw-medium">
                                                                        <?php echo $stats['cesit']; ?>
                                                                    </td>
                                                                    <td class="text-center fw-bold text-dark">
                                                                        <?php echo $stats['toplam']; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <span
                                                                            class="badge bg-success bg-opacity-10 text-success fs-6 fw-bold px-3 inventory-filter"
                                                                            style="cursor:pointer;" data-filter-type="bosta"
                                                                            data-kat-adi="<?php echo htmlspecialchars($katAdi); ?>">
                                                                            <?php echo $stats['bosta']; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($stats['zimmetli'] > 0): ?>
                                                                            <span
                                                                                class="badge bg-warning text-dark inventory-filter"
                                                                                style="cursor:pointer;" data-filter-type="zimmetli"
                                                                                data-kat-adi="<?php echo htmlspecialchars($katAdi); ?>">
                                                                                <?php echo $stats['zimmetli']; ?>
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">-</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($stats['serviste'] > 0): ?>
                                                                            <span class="badge bg-info inventory-filter"
                                                                                style="cursor:pointer;" data-filter-type="arizali"
                                                                                data-kat-adi="<?php echo htmlspecialchars($katAdi); ?>">
                                                                                <?php echo $stats['serviste']; ?>
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">-</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($stats['hurda'] > 0): ?>
                                                                            <span class="badge bg-danger inventory-filter"
                                                                                style="cursor:pointer;" data-filter-type="hurda"
                                                                                data-kat-adi="<?php echo htmlspecialchars($katAdi); ?>">
                                                                                <?php echo $stats['hurda']; ?>
                                                                            </span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">-</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr>
                                                                <td colspan="7" class="text-center text-muted py-4"><i
                                                                        class="bx bx-info-circle fs-4 d-block mb-1"></i>Raporlanacak
                                                                    veri bulunamadı.</td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Demirbaş Filtre Butonları -->
                            <div class="d-flex align-items-center justify-content-between mb-3 mt-2">
                                <div class="status-filter-group d-flex align-items-center shadow-sm" role="group">
                                    <input type="radio" class="btn-check" name="demirbas-status-filter" id="demirbas-filter-all" value="" checked>
                                    <label class="btn px-3" for="demirbas-filter-all">
                                        <i class="bx bx-list-check"></i> Tümü
                                    </label>

                                    <input type="radio" class="btn-check" name="demirbas-status-filter" id="demirbas-filter-bosta" value="bosta">
                                    <label class="btn px-3" for="demirbas-filter-bosta">
                                        <i class="bx bx-package"></i> Boşta
                                    </label>

                                    <input type="radio" class="btn-check" name="demirbas-status-filter" id="demirbas-filter-zimmetli" value="zimmetli">
                                    <label class="btn px-3" for="demirbas-filter-zimmetli">
                                        <i class="bx bx-user-check"></i> Zimmetli
                                    </label>

                                    <input type="radio" class="btn-check" name="demirbas-status-filter" id="demirbas-filter-hurda" value="hurda">
                                    <label class="btn px-3" for="demirbas-filter-hurda">
                                        <i class="bx bx-recycle"></i> Hurda
                                    </label>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="demirbasTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="custom-checkbox-container d-inline-block">
                                                    <input type="checkbox" id="checkAllDemirbas"
                                                        class="custom-checkbox-input">
                                                    <label class="custom-checkbox-label" for="checkAllDemirbas"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%" data-filter="number">Sıra</th>
                                            <th style="width:8%" class="text-center" data-filter="string">D.No</th>
                                            <th style="width:12%" data-filter="select">Kategori</th>
                                            <th style="width:20%" data-filter="string">Demirbaş Adı</th>
                                            <th style="width:15%" data-filter="string">Marka/Model</th>
                                            <th style="width:10%" class="text-center" data-filter="select">Stok</th>
                                            <th style="width:10%" class="text-center" data-filter="select">Durum</th>
                                            <th style="width:10%" class="text-end" data-filter="number">Edinme Tutarı
                                            </th>
                                            <th style="width:10%" data-filter="date">Edinme Tarihi</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Server-side DataTables -->
                                    </tbody>
                                </table>
                            </div>



                        </div>



                        <!-- Sayaç Deposu Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'depo' ? 'show active' : ''; ?>"
                            id="depoContent" role="tabpanel">

                            <!-- Depo Özet Kartları -->
                            <div class="row g-2 mb-4">
                                <!-- Ana Depo (Yeni) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(85, 110, 230, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-package fs-6 text-primary"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">DEPO (YENİ)</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $depoOzet->yeni_depoda; ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Personelde (Yeni) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #34c38f; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(52, 195, 143, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-user-check fs-6 text-success"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">ZİMMET (YENİ)</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $depoOzet->yeni_personelde; ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Hurda Depoda -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #f1b44c; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(241, 180, 76, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-recycle fs-6 text-warning"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">DEPO (HURDA)</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $depoOzet->hurda_depoda; ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Personelde (Hurda) -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #50a5f1; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(80, 165, 241, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-user-minus fs-6 text-info"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">ZİMMET (HURDA)</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $depoOzet->hurda_personelde; ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sayaç Tablosu -->
                            <div class="table-responsive">
                                <table id="sayacTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="custom-checkbox-container d-inline-block">
                                                    <input type="checkbox" id="checkAllSayac"
                                                        class="custom-checkbox-input">
                                                    <label class="custom-checkbox-label" for="checkAllSayac"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:8%" class="text-center">D.No</th>
                                            <th style="width:20%">Sayaç Adı</th>
                                            <th style="width:15%">Marka/Model</th>
                                            <th style="width:15%">Seri No</th>
                                            <th style="width:10%" class="text-center">Stok</th>
                                            <th style="width:10%" class="text-center">Durum</th>
                                            <th style="width:10%">Edinme Tarihi</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Server-side DataTables -->
                                    </tbody>
                                </table>
                            </div>

                        </div>

                        <!-- Aparatlar Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'aparat' ? 'show active' : ''; ?>"
                            id="aparatContent" role="tabpanel">

                            <!-- Aparat Deposu Özet Kartları -->
                            <div class="row g-2 mb-4">
                                <!-- Depoda -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(85, 110, 230, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-package fs-6 text-primary"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">DEPODA</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $aparatDepoOzet->depoda; ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Personelde -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #f1b44c; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(241, 180, 76, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-user-check fs-6 text-warning"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">PERSONELDE</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $aparatDepoOzet->personelde; ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tüketilen -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #ef4444; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(239, 68, 68, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-check-double fs-6" style="color: #ef4444;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">TÜKETİLEN</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $aparatDepoOzet->tuketilen; ?></h5>
                                        </div>
                                    </div>
                                </div>

                                <!-- Toplam Çeşit -->
                                <div class="col-xl col-md-3 col-sm-6">
                                    <div class="card border border-light shadow-none h-100 bordro-summary-card"
                                        style="--card-color: #34c38f; border-bottom: 2px solid var(--card-color) !important;">
                                        <div class="card-body p-2 px-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box"
                                                    style="background: rgba(52, 195, 143, 0.1); width: 28px; height: 28px;">
                                                    <i class="bx bx-grid-alt fs-6 text-success"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.5rem; opacity: 0.5;">TOPLAM ÇEŞİT</span>
                                            </div>
                                            <h5 class="mb-0 fw-bold mt-1"><?php echo $aparatDepoOzet->toplam_cesit; ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aparat Tablosu -->

                            <div class="table-responsive">
                                <table id="aparatTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="custom-checkbox-container d-inline-block">
                                                    <input type="checkbox" id="checkAllAparat"
                                                        class="custom-checkbox-input">
                                                    <label class="custom-checkbox-label" for="checkAllAparat"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%" data-filter="number">Sıra</th>
                                            <th style="width:8%" class="text-center" data-filter="string">D.No</th>
                                            <th style="width:20%" data-filter="string">Aparat Adı</th>
                                            <th style="width:15%" data-filter="string">Marka/Model</th>
                                            <th style="width:15%" data-filter="string">Seri No</th>
                                            <th style="width:10%" class="text-center" data-filter="select">Stok</th>
                                            <th style="width:10%" class="text-center" data-filter="select">Durum</th>
                                            <th style="width:10%" data-filter="date">Edinme Tarihi</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Server-side DataTables -->
                                    </tbody>
                                </table>
                            </div>


                        </div>

                        <!-- Servis Kayıtları Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'servis' ? 'show active' : ''; ?>"
                            id="servisContent" role="tabpanel">

                            <!-- Servis Özet Kartları -->
                            <div class="row g-3 mb-4" id="servisStatsRow">
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                    <i class="bx bx-wrench fs-4" style="color: #0ea5e9;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">SERVİS</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM KAYIT</p>
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
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">SERVİSTE</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">ŞU AN SERVİSTE</p>
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
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">MALİYET</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM MALİYET</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis_toplam_maliyet">0 ₺
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card bg-transparent border-0 shadow-none mb-2">
                                <div class="card-body p-0 d-flex align-items-center">
                                    <div class="me-3 ps-2">
                                        <i class="bx bx-filter-alt text-primary"></i> <span
                                            class="fw-bold small text-muted">FİLTRELE:</span>
                                    </div>
                                    <div class="row g-2 align-items-center flex-grow-1">
                                        <div class="col-sm-auto border-end pe-3 me-2">
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
                                        </div>
                                        <div class="col-md-4">
                                            <?php echo Form::FormFloatInput('text', 'servis_filtre_range', date('01.m.Y') . ' to ' . date('t.m.Y'), 'Tarih Aralığı', 'Tarih Aralığı', 'calendar', 'form-control flatpickr-range'); ?>
                                        </div>
                                        <div class="col-md-2 align-self-end p-1">
                                            <button type="button" class="btn btn-primary w-100" id="btnServisListele">
                                                <i class="bx bx-search-alt"></i> Listele
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="servisTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
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

                        <!-- Zimmet Kayıtları Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'zimmet' ? 'show active' : ''; ?>"
                            id="zimmetContent" role="tabpanel">

                            <!-- Zimmet Filtre Butonları -->
                            <div class="card bg-white border shadow-sm mb-3">
                                <div class="card-body p-2 d-flex align-items-center">
                                    <div class="me-3 ps-2 d-flex align-items-center">
                                        <div class="avatar-xs me-2 rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center"
                                            style="width: 24px; height: 24px;">
                                            <i class="bx bx-filter-alt fs-6"></i>
                                        </div>
                                        <span class="fw-bold small text-muted text-uppercase"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">FİLTRELE:</span>
                                    </div>
                                    <div class="d-flex align-items-center flex-grow-1">
                                        <!-- Modern Segmented Control for List Filter -->
                                        <div class="segmented-control-container ms-1">
                                            <input type="radio" name="zimmetFilter" id="filterTum" value="all"
                                                class="segmented-control-input zimmet-filter" checked>
                                            <label for="filterTum" class="segmented-control-label"><i
                                                    class="bx bx-list-ul me-1 fs-5"></i> Tümü</label>

                                            <input type="radio" name="zimmetFilter" id="filterDemirbas" value="demirbas"
                                                class="segmented-control-input zimmet-filter">
                                            <label for="filterDemirbas" class="segmented-control-label"><i
                                                    class="bx bx-package me-1 fs-5"></i> Demirbaş</label>

                                            <input type="radio" name="zimmetFilter" id="filterSayac" value="sayac"
                                                class="segmented-control-input zimmet-filter">
                                            <label for="filterSayac" class="segmented-control-label"><i
                                                    class="bx bx-tachometer me-1 fs-5"></i> Sayaç</label>

                                            <input type="radio" name="zimmetFilter" id="filterAparat" value="aparat"
                                                class="segmented-control-input zimmet-filter">
                                            <label for="filterAparat" class="segmented-control-label"><i
                                                    class="bx bx-wrench me-1 fs-5"></i> Aparat</label>
                                        </div>

                                        <div class="status-filter-group ms-3 shadow-sm">
                                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-all" value="" checked>
                                            <label class="btn px-3" for="zimmet-filter-all">
                                                <i class="bx bx-check-double"></i> Tümü
                                            </label>

                                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-teslim" value="teslim">
                                            <label class="btn px-3" for="zimmet-filter-teslim">
                                                <i class="bx bx-user-check"></i> Zimmetli
                                            </label>

                                            <input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-iade" value="iade">
                                            <label class="btn px-3" for="zimmet-filter-iade">
                                                <i class="bx bx-undo"></i> İade Alındı
                                            </label>
                                        </div>

                                        <div class="col-md-3 ms-auto pe-2">
                                            <?php
                                            $personelOptions = ['all' => 'Tüm Personeller'];
                                            foreach ($personeller as $p) {
                                                $personelOptions[$p->id] = $p->adi_soyadi;
                                            }
                                            echo Form::FormSelect2('zimmet_personel_filtre', $personelOptions, 'all', 'Personel Filtresi', 'users', 'key', '', 'form-control form-control-sm select2', false, 'width:100%', 'data-placeholder="Personel Filtresi"');
                                            ?>
                                        </div>
                                        <div class="col-auto pe-2">
                                            <button type="button" class="btn btn-sm btn-soft-primary" id="btnAparatPersonelOzet">
                                                <i class="bx bx-bar-chart-alt-2 me-1"></i> Aparat Özet
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Zimmet İstatistikleri Akordiyon -->
                            <style>
                                .accordion-button:not(.collapsed) .zimmet-stats-hint {
                                    display: none !important;
                                }
                            </style>
                            <div class="accordion mb-4" id="zimmetStatsAccordion">
                                <div class="accordion-item shadow-sm border-0 rounded-3 overflow-hidden">
                                    <h2 class="accordion-header" id="headingZimmetStats">
                                        <button class="accordion-button collapsed bg-white fw-bold text-dark py-3"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#collapseZimmetStats" aria-expanded="false"
                                            aria-controls="collapseZimmetStats" style="box-shadow: none;">
                                            <div class="d-flex w-100 align-items-center pe-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="bx bx-pie-chart-alt-2 fs-5 me-2 text-warning"></i>
                                                    <span class="text-nowrap">Zimmet Dağılım İstatistikleri</span>
                                                    <small class="text-warning ms-2">(Grafik için tıklayınız)</small>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapseZimmetStats" class="accordion-collapse collapse"
                                        aria-labelledby="headingZimmetStats" data-bs-parent="#zimmetStatsAccordion">
                                        <div class="accordion-body bg-white border-top">
                                            <div class="row g-3">
                                                <div class="col-lg-6">
                                                    <div class="card shadow-none border mb-0">
                                                        <div class="card-header bg-light py-2">
                                                            <h6 class="mb-0 fw-bold small">Kategori Bazlı Dağılım</h6>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div id="zimmetKategoriChart" style="min-height: 250px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="card shadow-none border mb-0">
                                                        <div class="card-header bg-light py-2">
                                                            <h6 class="mb-0 fw-bold small">Durum Bazlı Dağılım</h6>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div id="zimmetDurumChart" style="min-height: 250px;"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="zimmetTable"
                                    class="table table-demirbas table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:3%">
                                                <div class="custom-checkbox-container">
                                                    <input type="checkbox" id="checkAllZimmet"
                                                        class="custom-checkbox-input">
                                                    <label for="checkAllZimmet" class="custom-checkbox-label"></label>
                                                </div>
                                            </th>
                                            <th class="text-center" style="width:5%" data-filter="number">ID</th>
                                            <th style="width:12%" data-filter="select">Kategori</th>
                                            <th style="width:20%" data-filter="string">Demirbaş</th>
                                            <th style="width:15%" data-filter="string">Marka/Model</th>
                                            <th style="width:18%" data-filter="string">Personel</th>
                                            <th style="width:8%" class="text-center" data-filter="number">Miktar</th>
                                            <th style="width:12%" data-filter="date">Teslim Tarihi</th>
                                            <th style="width:10%" data-filter="select" class="text-center">Durum</th>
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
    /* Dropdown menülerin tablo içinde kesilmesini engellemek için */
    .table-responsive {
        min-height: 300px;
    }

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

    .filter-badge {
        display: inline-flex;
        align-items: center;
        background: #32394e;
        color: #fff;
        padding: 0;
        border-radius: 4px;
        font-size: 0.75rem;
        overflow: hidden;
        border: 1px solid #3e465b;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .filter-badge .filter-label {
        padding: 4px 8px;
        background: rgba(255, 255, 255, 0.05);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        color: #a6b0cf;
    }

    .filter-badge .filter-value {
        padding: 4px 10px;
        font-weight: 600;
    }

    .filter-badge .filter-remove {
        padding: 4px 8px;
        background: rgba(255, 255, 255, 0.1);
        cursor: pointer;
        transition: all 0.2s;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
    }

    .filter-badge .filter-remove:hover {
        background: #f46a6a;
        color: #fff;
    }

    /* Preloader Styles */
    .personel-preloader {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        min-height: 400px;
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
        padding: 2.5rem;
        border-radius: 16px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        text-align: center;
        min-width: 250px;
    }

    [data-bs-theme="dark"] .personel-preloader .loader-content {
        background: #2a3042;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    }
</style>

<!-- Demirbaş Modal -->
<?php include_once "modal/general-modal.php" ?>

<!-- Zimmet Modal -->
<?php include_once "modal/zimmet-modal.php" ?>

<!-- İade Modal -->
<?php include_once "modal/iade-modal.php" ?>

<!-- Toplu İade Modal -->
<?php include_once "modal/toplu-iade-modal.php" ?>

<!-- Hurda Sayaç İade Modal -->
<?php include_once "modal/hurda-iade-modal.php" ?>

<!-- Zimmet Detay Modal -->
<?php include_once "modal/zimmet-detay-modal.php" ?>

<!-- Aparat Personel Özet Modal -->
<?php include_once "modal/aparat-personel-ozet-modal.php" ?>

<!-- Servis Modal -->
<?php include_once "modal/servis-modal.php" ?>

<!-- Toplu Aparat Zimmet Modal -->
<?php include_once "modal/toplu-aparat-zimmet-modal.php" ?>

<!-- Kaskiye Teslim Modal -->
<?php include_once "modal/kasiye-teslim-modal.php" ?>

<!-- Kaskiye Detay Modal -->
<div class="modal fade" id="kasiyeDetayModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header modal-header-dark"
                style="background: rgba(33, 37, 41, 0.03); border-bottom: 2px solid #212529;">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-dark bg-opacity-10 d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;">
                        <i class="bx bx-info-circle text-dark fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-dark mb-0 fw-bold">Kaskiye Teslim Detayı</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.7rem;">Kaskiye'ye yapılan teslimat
                            bilgileri</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <div class="avatar-title bg-soft-success text-success display-4 rounded-circle"
                            style="width: 70px; height: 70px; line-height: 70px;">
                            <i class="bx bx-check-double"></i>
                        </div>
                    </div>
                    <h5 id="detaySayacAdi" class="fw-bold mb-1 text-dark">-</h5>
                    <p id="detaySeriNo" class="text-muted small mb-0 fw-medium">-</p>
                </div>

                <div class="card bg-light border-0 shadow-none mb-0 rounded-3">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-2 text-primary">
                                        <i class="bx bx-user fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-0 small uppercase fw-bold"
                                            style="font-size:0.6rem; letter-spacing: 0.5px;">Teslim Eden</p>
                                        <h6 id="detayTeslimEden" class="mb-0 fw-bold small text-dark">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center border-start ps-3">
                                    <div class="flex-shrink-0 me-2 text-success">
                                        <i class="bx bx-calendar fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-0 small uppercase fw-bold"
                                            style="font-size:0.6rem; letter-spacing: 0.5px;">Teslim Tarihi</p>
                                        <h6 id="detayTarih" class="mb-0 fw-bold small text-dark">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-3 pt-3 border-top">
                                <p class="text-muted mb-1 small uppercase fw-bold"
                                    style="font-size:0.6rem; letter-spacing: 0.5px;">
                                    <i class="bx bx-message-square-detail me-1"></i> Açıklama / Not
                                </p>
                                <p id="detayAciklama" class="text-dark small mb-0 opacity-75">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-dark btn-sm fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Demirbaş İşlem Geçmişi Modal -->
<div class="modal" id="demirbasGecmisModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-soft-info border-bottom">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;">
                        <i class="bx bx-history text-info fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-info mb-0 fw-bold">Demirbaş İşlem Geçmişi</h6>
                        <p class="text-muted small mb-0" id="gecmisDemirbasAdi" style="font-size: 0.7rem;">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table id="demirbasGecmisTable"
                        class="table table-hover table-striped dt-responsive nowrap w-100 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>İşlem Tipi</th>
                                <th class="text-center">Miktar</th>
                                <th>Tarih</th>
                                <th>İlgili Personel</th>
                                <th>Açıklama</th>
                                <th class="text-end">İşlem Yapan</th>
                            </tr>
                        </thead>
                        <tbody id="demirbasGecmisBody">
                            <!-- JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-4"
                    data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Excel Import Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true"
    style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header" style="background: rgba(85, 110, 230, 0.05); border-bottom: 2px solid #556ee6;">
                <div class="d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-primary bg-opacity-10 d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;">
                        <i class="bx bx-upload text-primary fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold" id="importExcelModalLabel">Excel'den Demirbaş Yükle</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.7rem;">Toplu veri yükleme işlemi</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="importExcelForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excelFile" class="form-label fw-bold">Excel Dosyası Seçin (.xlsx, .xls)</label>
                        <input class="form-control" type="file" id="excelFile" name="excelFile" accept=".xlsx, .xls">
                    </div>
                    <div class="alert alert-info py-2 px-3">
                        <i class="bx bx-info-circle me-1"></i> Lütfen uygun şablonu kullandığınızdan emin olun.
                        <br>
                        <a href="views/demirbas/download-template.php" class="alert-link">
                            <i class="bx bx-download me-1"></i>Örnek Şablonu İndir
                        </a>
                    </div>

                    <div class="alert alert-warning py-2 px-3 mb-3">
                        <i class="bx bx-error-circle me-1"></i>
                        <strong>Önemli:</strong> Excel'deki <strong>Kategori Adı</strong> sütunu, aşağıdaki mevcut
                        kategorilerden biriyle
                        <strong>birebir eşleşmelidir</strong>. Eşleşmeyen satırlar atlanacaktır.
                    </div>

                    <?php if (!empty($kategoriler)): ?>
                        <div class="card bg-light border-0 shadow-none mb-0">
                            <div class="card-body py-2 px-3">
                                <p class="mb-1 small fw-bold text-muted">
                                    <i class="bx bx-list-check me-1"></i> Geçerli Kategoriler
                                    <span class="badge bg-secondary ms-1"><?php echo count($kategoriler); ?></span>
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($kategoriler as $kat): ?>
                                        <span class="badge bg-primary bg-opacity-75 fw-normal py-1 px-2">
                                            <?php echo htmlspecialchars($kat->tur_adi); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger py-2 px-3 mb-0">
                            <i class="bx bx-error me-1"></i>
                            Henüz tanımlı kategori bulunmuyor.
                            <strong>Tanımlamalar</strong> sayfasından önce kategori ekleyin.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-4"
                    data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary btn-sm fw-bold px-4" id="btnUploadExcel">
                    <i class="bx bx-upload me-1"></i> Yükle
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var sayacKatIds = <?php echo json_encode($sayacKatIds); ?>;
    var aparatKatIds = <?php echo json_encode($aparatKatIds); ?>;
</script>
<script src="views/demirbas/js/demirbas.js?v=<?php echo time(); ?>"></script>