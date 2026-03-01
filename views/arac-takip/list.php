<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Model\PersonelModel;
use App\Model\AracServisModel;

$Arac = new AracModel();
$Zimmet = new AracZimmetModel();
$Yakit = new AracYakitModel();
$Km = new AracKmModel();
$Personel = new PersonelModel();
$Servis = new AracServisModel();

$personeller = $Personel->all(false, 'arac');
$aracStats = $Arac->getStats();
$evrakStats = $Arac->getAracEvrakStats(30);
$zimmetStats = $Zimmet->getStats();
$zimmetliSayi = $Arac->getZimmetliAracSayisi();

// Aylık istatistikler (mevcut ay)
$yakitStats = $Yakit->getStats(date('Y'), date('m'));
$kmStats = $Km->getStats(date('Y'), date('m'));

$activeTab = $_GET['tab'] ?? 'arac';
$filter = $_GET['filter'] ?? null;

if ($filter === 'muayene') {
    $araclar = $Arac->getMuayeneBitenler();
    $title = "Muayenesi Biten Araçlar";
} elseif ($filter === 'muayene_yaklasan') {
    $araclar = $Arac->getMuayeneYaklasanlar(30);
    $title = "Muayenesi Yaklaşan Araçlar";
} elseif ($filter === 'sigorta') {
    $araclar = $Arac->getSigortaBitenler();
    $title = "Sigortası Biten Araçlar";
} elseif ($filter === 'sigorta_yaklasan') {
    $araclar = $Arac->getSigortaYaklasanlar(30);
    $title = "Sigortası Yaklaşan Araçlar";
} elseif ($filter === 'kasko') {
    $araclar = $Arac->getKaskoBitenler();
    $title = "Kaskosu Biten Araçlar";
} elseif ($filter === 'kasko_yaklasan') {
    $araclar = $Arac->getKaskoYaklasanlar(30);
    $title = "Kaskosu Yaklaşan Araçlar";
} elseif ($filter === 'zimmetli') {
    $araclar = $Arac->getZimmetliAraclar();
    $title = "Zimmetli Araçlar";
} elseif ($filter === 'bosta') {
    $araclar = $Arac->getBostaAraclar();
    $title = "Boşta Olan Araçlar";
} elseif ($filter === 'serviste') {
    $araclar = $Arac->getServistekiAraclar();
    $title = "Servisteki Araçlar";
} else {
    $araclar = $Arac->all();
}
?>

<style>
    .badge-filter {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .badge-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .badge-filter.active {
        box-shadow: 0 0 0 2px currentColor;
    }

    .evrak-stat-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .evrak-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .evrak-stat-card.bg-danger-subtle {
        border-left-color: #f46a6a;
    }

    .evrak-stat-card.bg-warning-subtle {
        border-left-color: #f6c23e;
    }

    .evrak-stat-card.bg-info-subtle {
        border-left-color: #50a5f1;
    }

    .evrak-stat-card.active {
        border: 2px solid var(--card-color) !important;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .arac-info-box .plaka-label {
        font-size: 14px;
        font-weight: 700;
        color: #333;
    }

    .arac-info-box .model-label {
        font-size: 11px;
        color: #666;
        display: block;
    }
</style>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Araç Takip";
    $viewTitle = $title ?? "Araç Takip & Yakıt Yönetimi"; // Değişken adını değiştirdik ki breadcrumb ile çakışmasın
    $title = $viewTitle;
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-pills" id="aracTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'arac' ? 'active' : ''; ?>"
                                    id="arac-tab" data-bs-toggle="tab" data-bs-target="#aracContent" type="button"
                                    role="tab">
                                    <i class="bx bx-car me-1"></i> Araçlar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'zimmet' ? 'active' : ''; ?>"
                                    id="zimmet-tab" data-bs-toggle="tab" data-bs-target="#zimmetContent" type="button"
                                    role="tab">
                                    <i class="bx bx-transfer me-1"></i> Zimmetler
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'yakit' ? 'active' : ''; ?>"
                                    id="yakit-tab" data-bs-toggle="tab" data-bs-target="#yakitContent" type="button"
                                    role="tab">
                                    <i class="bx bx-gas-pump me-1"></i> Yakıt Kayıtları
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'km' ? 'active' : ''; ?>" id="km-tab"
                                    data-bs-toggle="tab" data-bs-target="#kmContent" type="button" role="tab">
                                    <i class="bx bx-tachometer me-1"></i> KM Kayıtları
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'rapor' ? 'active' : ''; ?>"
                                    id="rapor-tab" data-bs-toggle="tab" data-bs-target="#raporContent" type="button"
                                    role="tab">
                                    <i class="bx bx-bar-chart-alt-2 me-1"></i> Raporlar
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeTab === 'servis' ? 'active' : ''; ?>"
                                    id="servis-tab" data-bs-toggle="tab" data-bs-target="#servisContent" type="button"
                                    role="tab">
                                    <i class="bx bx-wrench me-1"></i> Servis Kayıtları
                                </button>
                            </li>
                        </ul>


                        <!-- Butonlar -->
                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <button class="btn btn-primary" type="button" id="btnYeniEkle">
                                <i class="bx bx-plus me-1"></i> Yeni Ekle
                            </button>

                            <div class="dropdown">
                                <button type="button" class="btn btn-secondary dropdown-toggle waves-effect waves-light"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bx bx-menu me-1"></i> İşlemler
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="btnExceleAktar">
                                            <i class="bx bx-export me-2"></i> Excele Aktar
                                        </a></li>
                                    <li id="liExcelYakitYukle" style="display: none;"><a class="dropdown-item" href="#"
                                            data-bs-toggle="modal" data-bs-target="#excelModal">
                                            <i class="bx bx-upload me-2"></i> Excel'den Yakıt Yükle
                                        </a></li>
                                    <li id="liExcelAracYukle" <?php echo $activeTab === 'arac' ? '' : 'style="display: none;"'; ?>><a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#aracExcelModal">
                                            <i class="bx bx-upload me-2"></i> Excel'den Araç Yükle
                                        </a></li>
                                    <li id="liExcelKmYukle" <?php echo $activeTab === 'km' ? '' : 'style="display: none;"'; ?>><a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#kmExcelYukleModal">
                                            <i class="bx bx-upload me-2"></i> Excel'den KM Yükle
                                        </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="aracTabContent">

                        <!-- =============================================
                             ARAÇLAR TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'arac' ? 'show active' : ''; ?>"
                            id="aracContent" role="tabpanel">

                            <!-- Araç Evrak Özet Kartları -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card evrak-stat-card <?php echo (!$filter) ? 'active' : ''; ?>"
                                        style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;"
                                        onclick="location.href='index.php?p=arac-takip/list'">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                                                    <i class="bx bx-list-check fs-4" style="color: #556ee6;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">TÜMÜ</span>
                                            </div>
                                            <p class="text-muted mb-2 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">ARAÇ DURUMU</p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="text-primary fw-bold">Toplam Araç</span>
                                                <h4 class="mb-0 fw-bold text-primary">
                                                    <?php echo $aracStats->toplam_arac ?? 0; ?>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card evrak-stat-card <?php echo (strpos($filter, 'muayene') !== false) ? 'active' : ''; ?>"
                                        style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                                                    <i class="bx bx-shield-quarter fs-4" style="color: #f46a6a;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">MUAYENE</span>
                                            </div>
                                            <p class="text-muted mb-2 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">MUAYENE DURUMU</p>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="index.php?p=arac-takip/list&filter=muayene"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none">
                                                    <span class="text-danger fw-bold"><i
                                                            class="bx bx-error-circle me-1"></i>Süresi Geçen</span>
                                                    <h5 class="mb-0 fw-bold text-danger">
                                                        <?php echo $evrakStats->muayene_biten ?? 0; ?>
                                                    </h5>
                                                </a>
                                                <a href="index.php?p=arac-takip/list&filter=muayene_yaklasan"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none border-top pt-1">
                                                    <span class="text-warning fw-bold"><i
                                                            class="bx bx-time-five me-1"></i>Yaklaşan (30 G)</span>
                                                    <h5 class="mb-0 fw-bold text-warning">
                                                        <?php echo $evrakStats->muayene_yaklasan ?? 0; ?>
                                                    </h5>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card evrak-stat-card <?php echo (strpos($filter, 'sigorta') !== false) ? 'active' : ''; ?>"
                                        style="--card-color: #f6c23e; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(246, 194, 62, 0.1);">
                                                    <i class="bx bx-check-shield fs-4" style="color: #f6c23e;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">SİGORTA</span>
                                            </div>
                                            <p class="text-muted mb-2 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">SİGORTA DURUMU</p>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="index.php?p=arac-takip/list&filter=sigorta"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none">
                                                    <span class="text-danger fw-bold"><i
                                                            class="bx bx-error-circle me-1"></i>Süresi Geçen</span>
                                                    <h5 class="mb-0 fw-bold text-danger">
                                                        <?php echo $evrakStats->sigorta_biten ?? 0; ?>
                                                    </h5>
                                                </a>
                                                <a href="index.php?p=arac-takip/list&filter=sigorta_yaklasan"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none border-top pt-1">
                                                    <span class="text-primary fw-bold"><i
                                                            class="bx bx-time-five me-1"></i>Yaklaşan (30 G)</span>
                                                    <h5 class="mb-0 fw-bold text-primary">
                                                        <?php echo $evrakStats->sigorta_yaklasan ?? 0; ?>
                                                    </h5>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card evrak-stat-card <?php echo (strpos($filter, 'kasko') !== false) ? 'active' : ''; ?>"
                                        style="--card-color: #50a5f1; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(80, 165, 241, 0.1);">
                                                    <i class="bx bx-lock-shield fs-4" style="color: #50a5f1;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">KASKO</span>
                                            </div>
                                            <p class="text-muted mb-2 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">KASKO DURUMU</p>
                                            <div class="d-flex flex-column gap-1">
                                                <a href="index.php?p=arac-takip/list&filter=kasko"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none">
                                                    <span class="text-danger fw-bold"><i
                                                            class="bx bx-error-circle me-1"></i>Süresi Geçen</span>
                                                    <h5 class="mb-0 fw-bold text-danger">
                                                        <?php echo $evrakStats->kasko_biten ?? 0; ?>
                                                    </h5>
                                                </a>
                                                <a href="index.php?p=arac-takip/list&filter=kasko_yaklasan"
                                                    class="d-flex justify-content-between align-items-center text-decoration-none border-top pt-1">
                                                    <span class="text-dark fw-bold"><i
                                                            class="bx bx-time-five me-1"></i>Yaklaşan (30 G)</span>
                                                    <h5 class="mb-0 fw-bold text-dark">
                                                        <?php echo $evrakStats->kasko_yaklasan ?? 0; ?>
                                                    </h5>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- İstatistik Badge'leri -->
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <span
                                    class="badge bg-primary-subtle text-primary fs-6 badge-filter <?php echo empty($filter) ? 'active' : ''; ?>"
                                    onclick="location.href='index.php?p=arac-takip/list'">
                                    <i class="bx bx-car me-1"></i> Araç:
                                    <?php echo $aracStats->toplam_arac ?? 0; ?>
                                </span>
                                <span class="badge bg-success-subtle text-success fs-6">
                                    <i class="bx bx-check-circle me-1"></i> Aktif:
                                    <?php echo $aracStats->aktif_arac ?? 0; ?>
                                </span>
                                <span
                                    class="badge bg-warning-subtle text-warning fs-6 badge-filter <?php echo $filter === 'zimmetli' ? 'active' : ''; ?>"
                                    onclick="location.href='index.php?p=arac-takip/list&filter=zimmetli'">
                                    <i class="bx bx-transfer me-1"></i> Zimmetli:
                                    <?php echo $zimmetliSayi; ?>
                                </span>
                                <span
                                    class="badge bg-info-subtle text-info fs-6 badge-filter <?php echo $filter === 'bosta' ? 'active' : ''; ?>"
                                    onclick="location.href='index.php?p=arac-takip/list&filter=bosta'">
                                    <i class="bx bx-user-x me-1"></i> Boşta:
                                    <?php echo $aracStats->bosta_arac ?? 0; ?>
                                </span>
                                <span
                                    class="badge bg-danger-subtle text-danger fs-6 badge-filter <?php echo $filter === 'serviste' ? 'active' : ''; ?>"
                                    id="badge-servisteki-arac"
                                    onclick="location.href='index.php?p=arac-takip/list&filter=serviste'">
                                    <i class="bx bx-wrench me-1"></i> Servisteki:
                                    <?php echo $Arac->getServistekiAracSayisi(); ?>
                                </span>
                            </div>

                            <div class="table-responsive">
                                <table id="aracTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:8%" class="text-center">Tip</th>
                                            <th style="width:15%">Plaka / Araç</th>
                                            <th style="width:10%">Departman</th>
                                            <th style="width:8%">Mülkiyet</th>
                                            <th style="width:12%">Zimmetli Personel</th>
                                            <th style="width:8%" class="text-center">Durum</th>
                                            <th style="width:7%" class="text-center">Yakıt</th>
                                            <th style="width:8%" class="text-end">KM</th>
                                            <th style="width:9%" class="text-center">Muayene Bitiş</th>
                                            <th style="width:9%" class="text-center">Sigorta Bitiş</th>
                                            <th style="width:9%" class="text-center">Kasko Bitiş</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 0;
                                        foreach ($araclar as $arac):
                                            $i++; ?>
                                            <?php
                                            $tipLabels = [
                                                'binek' => 'Binek',
                                                'kamyonet' => 'Kamyonet',
                                                'kamyon' => 'Kamyon',
                                                'minibus' => 'Minibüs',
                                                'otobus' => 'Otobüs',
                                                'motosiklet' => 'Motosiklet',
                                                'diger' => 'Diğer'
                                            ];

                                            $yakitLabels = [
                                                'benzin' => '<span class="badge bg-danger">Benzin</span>',
                                                'dizel' => '<span class="badge bg-dark">Dizel</span>',
                                                'lpg' => '<span class="badge bg-info">LPG</span>',
                                                'elektrik' => '<span class="badge bg-success">Elektrik</span>',
                                                'hibrit' => '<span class="badge bg-warning text-dark">Hibrit</span>'
                                            ];
                                            ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php echo $i; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-light text-dark border"><?php echo $tipLabels[$arac->arac_tipi] ?? '-'; ?></span>
                                                </td>
                                                <td>
                                                    <div class="arac-info-box">
                                                        <a href="javascript:void(0)"
                                                            class="plaka-label text-primary arac-duzenle"
                                                            data-id="<?php echo $arac->id; ?>">
                                                            <?php echo $arac->plaka; ?>
                                                        </a>
                                                        <span
                                                            class="model-label"><?php echo ($arac->marka ?? '-') . ' ' . ($arac->model ?? ''); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="small"><?php echo $arac->departmani ?: '-'; ?></span>
                                                </td>
                                                <td>
                                                    <span class="small"><?php echo $arac->mulkiyet ?: '-'; ?></span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="small fw-bold text-dark"><?php echo $arac->zimmetli_personel_adi ?: '<span class="text-muted">Boşta</span>'; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <?php
                                                    if ($arac->serviste_mi) {
                                                        echo '<span class="badge bg-danger">Serviste</span>';
                                                    } else {
                                                        echo $arac->aktif_mi ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php echo $yakitLabels[$arac->yakit_tipi] ?? '-'; ?>
                                                </td>
                                                <td class="text-end fw-bold">
                                                    <?php echo number_format($arac->guncel_km ?? 0, 0, ',', '.'); ?>
                                                </td>
                                                <td
                                                    class="text-center <?php echo (strtotime($arac->muayene_bitis_tarihi) < time() && $arac->muayene_bitis_tarihi) ? 'text-danger fw-bold' : ''; ?>">
                                                    <span
                                                        class="small"><?php echo $arac->muayene_bitis_tarihi ? date('d.m.Y', strtotime($arac->muayene_bitis_tarihi)) : '-'; ?></span>
                                                </td>
                                                <td
                                                    class="text-center <?php echo (strtotime($arac->sigorta_bitis_tarihi) < time() && $arac->sigorta_bitis_tarihi) ? 'text-danger fw-bold' : ''; ?>">
                                                    <span
                                                        class="small"><?php echo $arac->sigorta_bitis_tarihi ? date('d.m.Y', strtotime($arac->sigorta_bitis_tarihi)) : '-'; ?></span>
                                                </td>
                                                <td
                                                    class="text-center <?php echo (strtotime($arac->kasko_bitis_tarihi) < time() && $arac->kasko_bitis_tarihi) ? 'text-danger fw-bold' : ''; ?>">
                                                    <span
                                                        class="small"><?php echo $arac->kasko_bitis_tarihi ? date('d.m.Y', strtotime($arac->kasko_bitis_tarihi)) : '-'; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (empty($arac->zimmetli_personel_id)): ?>
                                                            <button type="button" class="btn btn-soft-warning zimmet-hizli"
                                                                data-id="<?php echo $arac->id; ?>"
                                                                data-plaka="<?php echo $arac->plaka; ?>"
                                                                data-km="<?php echo $arac->guncel_km; ?>" title="Zimmet Ver">
                                                                <i class="bx bx-transfer"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-soft-primary arac-duzenle"
                                                            data-id="<?php echo $arac->id; ?>" title="Düzenle">
                                                            <i class="bx bx-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-soft-danger arac-sil"
                                                            data-id="<?php echo $arac->id; ?>"
                                                            data-plaka="<?php echo $arac->plaka; ?>" title="Sil">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             ZİMMETLER TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'zimmet' ? 'show active' : ''; ?>"
                            id="zimmetContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="zimmetTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:18%">Araç</th>
                                            <th style="width:18%">Personel</th>
                                            <th style="width:12%">Zimmet Tarihi</th>
                                            <th style="width:12%">İade Tarihi</th>
                                            <th style="width:10%" class="text-center">Durum</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="zimmetTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             YAKIT KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'yakit' ? 'show active' : ''; ?>"
                            id="yakitContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row g-3 mb-4">
                                <div class="col-xl col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                    <i class="bx bx-gas-pump fs-4" style="color: #2a9d8f;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">LİTRE</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM YAKIT</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="yakit-toplam-litre">
                                                <?php echo number_format($yakitStats->toplam_litre ?? 0, 0, ',', '.'); ?>
                                                <span style="font-size: 0.85rem; font-weight: 600;">L</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #E76F51; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(231, 111, 81, 0.1);">
                                                    <i class="bx bx-money fs-4" style="color: #E76F51;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">MALİYET</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM MALİYET</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="yakit-toplam-maliyet">
                                                <?php echo number_format($yakitStats->toplam_tutar ?? 0, 2, ',', '.'); ?>
                                                <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                    <i class="bx bx-purchase-tag fs-4" style="color: #0ea5e9;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">BİRİM</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">ORT. BİRİM FİYAT</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="yakit-ortalama-fiyat">
                                                <?php echo number_format($yakitStats->ortalama_birim_fiyat ?? 0, 2, ',', '.'); ?>
                                                <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-3">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(139, 92, 246, 0.1);">
                                                    <i class="bx bx-list-ul fs-4" style="color: #8b5cf6;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">KAYIT</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM KAYIT</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="yakit-kayit-sayisi">
                                                <?php echo $yakitStats->toplam_kayit ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php
                                            $aracOptions = ['' => 'Tüm Araçlar'];
                                            foreach ($araclar as $arac) {
                                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                            }
                                            echo App\Helper\Form::FormSelect2('yakit-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>







                                        </div>
                                        <div class="col-md-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary w-100" id="btnYakitFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info w-100" id="btnYakitIstatistik"
                                                data-bs-toggle="modal" data-bs-target="#istatistikModal"
                                                data-type="yakit">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="yakitTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%">Plaka</th>
                                            <th style="width:10%">Tarih</th>
                                            <th style="width:10%" class="text-end">KM</th>
                                            <th style="width:10%" class="text-end">Miktar (L)</th>
                                            <th style="width:10%" class="text-end">Birim Fiyat</th>
                                            <th style="width:12%" class="text-end">Toplam Tutar</th>
                                            <th style="width:15%">İstasyon</th>
                                            <th style="width:8%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yakitTableBody">
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             KM KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'km' ? 'show active' : ''; ?>"
                            id="kmContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row g-3 mb-4">
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                    <i class="bx bx-trending-up fs-4" style="color: #0ea5e9;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">YOL</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM YOL</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="km-toplam-yol">
                                                <?php echo number_format($kmStats->toplam_km ?? 0, 0, ',', '.'); ?>
                                                <span style="font-size: 0.85rem; font-weight: 600;">km</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                    <i class="bx bx-stats fs-4" style="color: #2a9d8f;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">ORTALAMA</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">GÜNLÜK ORT. YOL</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="km-ortalama-yol">
                                                <?php echo number_format($kmStats->ortalama_gunluk_km ?? 0, 1, ',', '.'); ?>
                                                <span style="font-size: 0.85rem; font-weight: 600;">km</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(139, 92, 246, 0.1);">
                                                    <i class="bx bx-list-check fs-4" style="color: #8b5cf6;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">KAYIT</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM KAYIT</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="km-kayit-sayisi">
                                                <?php echo $kmStats->toplam_kayit ?? 0; ?>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">

                                            <?php
                                            echo App\Helper\Form::FormSelect2('km-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>







                                        </div>
                                        <div class="col-md-3 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary w-100" id="btnKmFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info w-100" id="btnKmIstatistik"
                                                data-bs-toggle="modal" data-bs-target="#istatistikModal" data-type="km">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="kmTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:15%">Plaka</th>
                                            <th style="width:15%">Tarih</th>
                                            <th style="width:15%" class="text-end">Başlangıç KM</th>
                                            <th style="width:15%" class="text-end">Bitiş KM</th>
                                            <th style="width:15%" class="text-end">Yapılan KM</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kmTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             RAPORLAR TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'rapor' ? 'show active' : ''; ?>"
                            id="raporContent" role="tabpanel">
                            <!-- Filtre -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label class="form-label">Yıl</label>
                                    <select class="form-select" id="raporYil">
                                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                            <option value="<?php echo $y; ?>">
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ay</label>
                                    <select class="form-select" id="raporAy">
                                        <?php
                                        $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                                        for ($m = 1; $m <= 12; $m++):
                                            ?>
                                            <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                <?php echo $aylar[$m - 1]; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Araç (Opsiyonel)</label>
                                    <select class="form-select" id="raporArac">
                                        <option value="">Tüm Araçlar</option>
                                        <?php foreach ($araclar as $arac): ?>
                                            <option value="<?php echo $arac->id; ?>">
                                                <?php echo $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100" id="btnRaporYukle">
                                        <i class="bx bx-search me-1"></i> Rapor Getir
                                    </button>
                                </div>
                            </div>

                            <!-- Rapor İçeriği -->
                            <div id="raporIcerik">
                                <div class="text-center py-5 text-muted">
                                    <i class="bx bx-bar-chart-alt-2 display-1"></i>
                                    <p class="mt-3">Rapor görüntülemek için yukarıdan filtre seçin ve "Rapor Getir"
                                        butonuna tıklayın.</p>
                                </div>
                            </div>
                        </div>

                        <!-- =============================================
                             SERVİS KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'servis' ? 'show active' : ''; ?>"
                            id="servisContent" role="tabpanel">
                            <!-- Servis Özet Kartları -->
                            <div class="row g-3 mb-4">
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
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM SERVİS KAYDI</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis-toplam-kayit">0
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                    <i class="bx bx-time fs-4" style="color: #f59e0b;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">GÜNCEL</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">SERVİSTEKİ ARAÇLAR</p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis-servisteki-arac">0
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl col-md-4">
                                    <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                        style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                        <div class="card-body p-3">
                                            <div class="icon-label-container">
                                                <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                    <i class="bx bx-wallet fs-4" style="color: #f43f5e;"></i>
                                                </div>
                                                <span class="text-muted small fw-bold"
                                                    style="font-size: 0.65rem;">MALİYET</span>
                                            </div>
                                            <p class="text-muted mb-1 small fw-bold"
                                                style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM SERVİS MALİYETİ
                                            </p>
                                            <h4 class="mb-0 fw-bold bordro-text-heading" id="servis-toplam-maliyet">
                                                0.00
                                                <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                            </h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-4">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <?php
                                            $aracOptions = ['' => 'Tüm Araçlar'];
                                            foreach ($araclar as $arac) {
                                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                            }
                                            echo App\Helper\Form::FormSelect2('servis-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'servis-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'servis-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-md-3 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary w-100" id="btnServisFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="servisTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%">Plaka</th>
                                            <th style="width:12%">Servis Giriş</th>
                                            <th style="width:12%">Servis Çıkış</th>
                                            <th style="width:10%" class="text-end">Giriş KM</th>
                                            <th style="width:10%" class="text-end">Çıkış KM</th>
                                            <th style="width:25%">Servis Nedeni</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="servisTableBody">
                                        <tr>
                                            <td class="text-center py-4 text-muted">
                                                <div class="spinner-border spinner-border-sm text-primary"
                                                    role="status"></div>
                                            </td>
                                            <td class="py-4 text-muted">Yükleniyor...</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
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
</div>

<style>
    #yakitTable tbody tr,
    #kmTable tbody tr,
    #servisTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #yakitTable tbody tr:hover,
    #kmTable tbody tr:hover,
    #servisTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .nav-pills .nav-link.active {
        background-color: #556ee6;
    }

    .nav-pills .nav-link {
        color: #495057;
    }

    .card.bg-gradient {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
</style>

<!-- Modaller -->
<?php include_once "modal/arac-modal.php"; ?>
<?php include_once "modal/zimmet-modal.php"; ?>
<?php include_once "modal/yakit-modal.php"; ?>
<?php include_once "modal/km-modal.php"; ?>
<?php include_once "modal/excel-modal.php"; ?>
<?php include_once "modal/arac-excel-modal.php"; ?>
<?php include_once "modal/servis-modal.php"; ?>
<?php include_once "modal/km-excel-yukle-modal.php"; ?>

<!-- İstatistik Modal -->
<div class="modal fade" id="istatistikModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-stats me-2"></i>İstatistik Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="istatistikModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="views/arac-takip/js/arac-takip.js"></script>