<?php

use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;
use App\Service\Gate;
use App\Model\FirmaModel;
use App\Helper\Alert;

$id = Security::decrypt($_GET['id'] ?? 0);

/**Yetki kontrolü */
if (Gate::allows('personel_duzenle')) {



$PersonelModel = new PersonelModel();
$personel = $id > 0 ? $PersonelModel->findByEkipNo($id) : null;
$TanimlamalarModel = new TanimlamalarModel();
$FirmaModel = new FirmaModel();

if ($personel) {
    $ekip_adi = $personel->ekip_no ? $TanimlamalarModel->getTurAdi($personel->ekip_no) : "Ekip Yok";
    $adi_soyadi_ekipno = $personel->adi_soyadi;
} else {
    $adi_soyadi_ekipno = "Yeni Personel";
}

$mevcutEkipNo = $personel->ekip_no ?? null;
$mevcutBolge = $personel->ekip_bolge ?? null;
if ($mevcutBolge) {
    $ekip_kodlari_raw = $TanimlamalarModel->getMusaitEkipKodlariByBolge($mevcutBolge, $mevcutEkipNo);
} else {
    $ekip_kodlari_raw = $TanimlamalarModel->getMusaitEkipKodlari($mevcutEkipNo);
}
$ekip_kodlari_options = ['' => 'Seçiniz'];
foreach ($ekip_kodlari_raw as $item) {
    $ekip_kodlari_options[$item->id] = $item->tur_adi;
}




$allPersonel = $PersonelModel->all(false, 'personel');
/**Personel id'sini şifrele */
$selectedOption = '';
$allPersonel = array_map(function ($item) use ($id, &$selectedOption) {
    $rawId = $item->id;
    $item->id = Security::encrypt($item->id);
    if ($rawId == $id) {
        $selectedOption = $item->id;
    }
    return $item;
}, $allPersonel);

$activeTab = $_GET['tab'] ?? 'home';

$tabCategories = [
    'ozluk' => [
        'title' => 'Özlük & Profil',
        'icon' => 'bx bx-user-pin',
        'tabs' => [
            'home' => ['label' => 'Genel Bilgiler', 'icon' => 'bx bx-id-card'],
            'calisma' => ['label' => 'Çalışma Bilgileri', 'icon' => 'bx bx-briefcase-alt-2'],
            'finansal' => ['label' => 'Maaş & Görev', 'icon' => 'bx bx-wallet-alt'],
            'diger' => ['label' => 'Diğer Bilgiler', 'icon' => 'bx bx-info-circle'],
        ]
    ]
];

if ($id > 0) {
    $tabCategories['finans'] = [
        'title' => 'Finans & Haklar',
        'icon' => 'bx bx-dollar-circle',
        'tabs' => [
            'kesintiler' => ['label' => 'Kesintiler', 'icon' => 'bx bx-minus-circle'],
            'ek_odemeler' => ['label' => 'Ek Ödemeler', 'icon' => 'bx bx-plus-circle'],
            'icralar' => ['label' => 'İcralar', 'icon' => 'bx bx-gavel'],
            'vergi_matrahlari' => ['label' => 'Vergi Matrahları', 'icon' => 'bx bx-trending-up'],
            'finansal_islemler' => ['label' => 'Hesap Hareketleri', 'icon' => 'bx bx-transfer-alt'],
        ]
    ];

    $tabCategories['operasyon'] = [
        'title' => 'Kayıt & Operasyon',
        'icon' => 'bx bx-folder',
        'tabs' => [
            'puantaj' => ['label' => 'İş Takip', 'icon' => 'bx bx-time-five'],
            'izinler' => ['label' => 'İzin / Rapor / Eksik Gün', 'icon' => 'bx bx-calendar-event'],
            'zimmetler' => ['label' => 'Zimmetler', 'icon' => 'bx bx-devices'],
            'evraklar' => ['label' => 'Evraklar', 'icon' => 'bx bx-file'],
            'giris_loglari' => ['label' => 'Giriş Logları', 'icon' => 'bx bx-history'],
        ]
    ];
}

$tabs = [];
$activeCategory = 'ozluk';
foreach ($tabCategories as $catKey => $cat) {
    foreach ($cat['tabs'] as $tabKey => $tabVal) {
        $tabs[$tabKey] = $tabVal;
        if ($tabKey === $activeTab) {
            $activeCategory = $catKey;
        }
    }
}
?>
<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Personel";
    $title = "Personel Düzenle";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-3">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex flex-column align-items-center position-relative">
                                        <div class="position-relative" style="width: 72px; height: 72px;">
                                            <?php
                                            $rootRoot = dirname(__DIR__, 2) . '/';
                                            $resimYoluAdmin = 'assets/images/users/user-dummy-img.jpg';
                                            if (!empty($personel->resim_yolu) && file_exists($rootRoot . $personel->resim_yolu)) {
                                                $resimYoluAdmin = $personel->resim_yolu;
                                            }
                                            ?>
                                            <img id="personelImage"
                                                src="<?php echo $resimYoluAdmin; ?>"
                                                alt="" class="img-thumbnail rounded-3 shadow-sm"
                                                style="width: 72px; height: 72px; object-fit: cover; cursor: zoom-in;"
                                                onclick="window.open(this.src, '_blank')">
                                            <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle shadow-sm"
                                                id="changePhotoButton" style="width: 24px; height: 24px; padding: 0; display: flex; align-items: center; justify-content: center; transform: translate(15%, 15%);" title="Fotoğraf Değiştir">
                                                <i class="bx bx-camera font-size-13"></i>
                                            </button>
                                            <input type="file" id="avatarInput" name="resim_yolu" accept="image/*"
                                                style="display: none;">
                                        </div>
                                        <span class="badge bg-primary-subtle text-primary mt-1 px-2 py-0" style="font-size: 10px; font-weight: 600;">Resmi Kayıt</span>
                                    </div>

                                    <?php if (!empty($personel->personel_resim_yolu) && file_exists($rootRoot . $personel->personel_resim_yolu)): ?>
                                        <div class="d-flex flex-column align-items-center position-relative">
                                            <div class="position-relative" style="width: 72px; height: 72px;">
                                                <img id="personelPwaImage"
                                                    src="<?php echo $personel->personel_resim_yolu; ?>"
                                                    alt="" class="img-thumbnail rounded-3 shadow-sm"
                                                    style="width: 72px; height: 72px; object-fit: cover; cursor: zoom-in;"
                                                    onclick="window.open(this.src, '_blank')">
                                            </div>
                                            <span class="badge bg-success-subtle text-success mt-1 px-2 py-0" style="font-size: 10px; font-weight: 600;">Mobil</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="font-size-16 mb-1 text-truncate">
                                        <?php echo $adi_soyadi_ekipno; ?>
                                    </h5>
                                    <p class="text-muted mb-0 text-truncate">
                                        <?php echo $personel->gorev ?? 'Görev Tanımsız'; ?> -
                                        <?php echo $personel->departman ?? 'Departman Tanımsız'; ?>
                                    </p>
                                    <small class="text-muted">Ekip No:
                                        <b class="fw-bold">
                                            <?php echo $personel->ekip_adi ?? '---'; ?>
                                        </b>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-wrap gap-2 float-end align-items-center">
                                <!-- /**Yeni personel ekleniyorsa gösterme */ -->
                                <?php if ($id > 0) { ?>
                                    <div class="personel-select-container" style="min-width: 250px;">
                                        <?php echo Form::FormSelect2('personel_select', $allPersonel, $selectedOption, 'Personel Değiştir', 'users', 'id', 'adi_soyadi', 'form-select select2'); ?>
                                    </div>
                                <?php } ?>

                                <div
                                    class="mobile-action-buttons d-flex align-items-center border rounded shadow-sm p-1 gap-1">
                                    <a href="index?p=personel/list"
                                        class="btn btn-link btn-sm text-decoration-none px-2 d-flex align-items-center"
                                        title="Listeye Dön">
                                        <i class="mdi mdi-arrow-left-circle fs-5 me-1"></i> <span
                                            class="d-none d-xl-inline">Listeye Dön</span>
                                    </a>

                                    <div class="vr mx-1 d-none d-xl-block" style="height: 25px; align-self: center;">
                                    </div>

                                    <a href="index?p=personel/manage"
                                        class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center"
                                        title="Yeni Personel">
                                        <i class="mdi mdi-plus-circle fs-5 me-1"></i> <span
                                            class="d-none d-xl-inline">Yeni Personel</span>
                                    </a>

                                    <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                                    <button type="button" id="saveButton"
                                        class="btn btn-primary px-3 fw-bold shadow-primary">
                                        <i class="mdi mdi-content-save-outline me-1"></i> Kaydet
                                    </button>

                                    <!-- Mobile Tabs Menu -->
                                    <div class="dropup d-md-none">
                                        <button class="btn btn-light waves-effect" type="button" id="mobileTabsMenuBtn"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bx bx-dots-horizontal-rounded"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end mobile-tabs-dropdown shadow-lg"
                                            aria-labelledby="mobileTabsMenuBtn" style="max-height: 380px; overflow-y: auto;">
                                            <?php foreach ($tabCategories as $catKey => $cat): ?>
                                                <li class="dropdown-header text-uppercase font-size-11 fw-bold text-muted px-3 py-1 mt-1">
                                                    <i class="<?php echo $cat['icon']; ?> me-1"></i> <?php echo $cat['title']; ?>
                                                </li>
                                                <?php foreach ($cat['tabs'] as $key => $tab): ?>
                                                    <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === $key ? 'active' : ''; ?> px-3 py-2"
                                                            href="javascript:void(0);"
                                                            data-target="#<?php echo $key; ?>"
                                                            data-category="<?php echo $catKey; ?>"><i class="<?php echo $tab['icon']; ?> me-2"></i><?php echo $tab['label']; ?></a>
                                                    </li>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modern Categorized Tabs Navigation (Desktop Only) -->
                <div class="card-body py-2 px-3 border-bottom bg-light-subtle d-none d-md-block">
                    <?php if (count($tabCategories) > 1): ?>
                        <!-- Kategori Başlıkları (Segmented Pills) -->
                        <div class="personel-category-pills d-flex align-items-center gap-2 mb-2">
                            <?php foreach ($tabCategories as $catKey => $cat): ?>
                                <button type="button" 
                                        class="btn btn-category-pill <?php echo $activeCategory === $catKey ? 'active' : ''; ?> d-inline-flex align-items-center gap-2" 
                                        data-category-target="<?php echo $catKey; ?>">
                                    <i class="<?php echo $cat['icon']; ?> font-size-16"></i>
                                    <span class="fw-semibold font-size-13"><?php echo $cat['title']; ?></span>
                                    <span class="badge rounded-pill <?php echo $activeCategory === $catKey ? 'bg-white text-primary' : 'bg-primary-subtle text-primary'; ?> category-badge ms-1"><?php echo count($cat['tabs']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Alt Sekmeler Grubu (Subtabs) -->
                    <div class="personel-subtabs-container p-1 rounded-3 bg-white border shadow-sm" id="desktopTabs">
                        <?php foreach ($tabCategories as $catKey => $cat): ?>
                            <div class="subtab-group nav nav-pills gap-1 flex-wrap <?php echo $activeCategory === $catKey ? 'd-flex' : 'd-none'; ?>" 
                                 id="subtabs-<?php echo $catKey; ?>" 
                                 role="tablist">
                                <?php foreach ($cat['tabs'] as $tabKey => $tab): 
                                    $isActive = ($activeTab === $tabKey);
                                ?>
                                    <a class="nav-link subtab-nav-link <?php echo $isActive ? 'active' : ''; ?> d-inline-flex align-items-center gap-2 px-3 py-2" 
                                       data-bs-toggle="tab" 
                                       href="#<?php echo $tabKey; ?>" 
                                       role="tab" 
                                       data-category="<?php echo $catKey; ?>">
                                        <i class="<?php echo $tab['icon']; ?> font-size-15"></i>
                                        <span><?php echo $tab['label']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <style>
                    #personelTabContent > .tab-pane,
                    #personelTabContent > form > .tab-pane {
                        display: none !important;
                    }

                    #personelTabContent > .tab-pane.active,
                    #personelTabContent > form > .tab-pane.active {
                        display: block !important;
                    }

                    /* Modern Personel Category Pills */
                    .personel-category-pills .btn-category-pill {
                        background-color: #ffffff;
                        border: 1px solid #d9e3ef;
                        color: #495057;
                        border-radius: 30px;
                        padding: 5px 16px;
                        transition: all 0.2s ease;
                        cursor: pointer;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
                    }

                    .personel-category-pills .btn-category-pill:hover {
                        background-color: #f8f9fa;
                        border-color: #cbd5e1;
                        color: var(--bs-primary);
                    }

                    .personel-category-pills .btn-category-pill.active {
                        background-color: var(--bs-primary) !important;
                        border-color: var(--bs-primary) !important;
                        color: #ffffff !important;
                        box-shadow: 0 3px 10px rgba(var(--bs-primary-rgb), 0.28);
                    }

                    .personel-category-pills .btn-category-pill.active .category-badge {
                        background-color: #ffffff !important;
                        color: var(--bs-primary) !important;
                    }

                    .personel-subtabs-container {
                        background-color: #ffffff;
                        border: 1px solid #e2e8f0;
                    }

                    .subtab-nav-link {
                        color: #556070;
                        font-weight: 500;
                        font-size: 13px;
                        border-radius: 8px !important;
                        border: 1px solid transparent;
                        transition: all 0.15s ease;
                        white-space: nowrap;
                    }

                    .subtab-nav-link:hover {
                        color: var(--bs-primary);
                        background-color: #f1f5f9;
                    }

                    .subtab-nav-link.active {
                        color: var(--bs-primary) !important;
                        background-color: rgba(var(--bs-primary-rgb), 0.1) !important;
                        border-color: rgba(var(--bs-primary-rgb), 0.25) !important;
                        font-weight: 600 !important;
                    }

                    /* Dark Mode Overrides */
                    html[data-bs-theme="dark"] .personel-category-pills .btn-category-pill {
                        background-color: #2a3042;
                        border-color: #32394e;
                        color: #a6b0cf;
                    }

                    html[data-bs-theme="dark"] .personel-category-pills .btn-category-pill:hover {
                        background-color: #32394e;
                        color: #ffffff;
                    }

                    html[data-bs-theme="dark"] .personel-category-pills .btn-category-pill.active {
                        background-color: var(--bs-primary) !important;
                        border-color: var(--bs-primary) !important;
                        color: #ffffff !important;
                    }

                    html[data-bs-theme="dark"] .personel-subtabs-container {
                        background-color: #2a3042 !important;
                        border-color: #32394e !important;
                    }

                    html[data-bs-theme="dark"] .subtab-nav-link {
                        color: #9299af;
                    }

                    html[data-bs-theme="dark"] .subtab-nav-link:hover {
                        background-color: rgba(255, 255, 255, 0.05);
                        color: #ffffff;
                    }

                    html[data-bs-theme="dark"] .subtab-nav-link.active {
                        background-color: rgba(var(--bs-primary-rgb), 0.2) !important;
                        border-color: rgba(var(--bs-primary-rgb), 0.4) !important;
                        color: #ffffff !important;
                    }

                    @media (max-width: 768px) {
                        .mobile-action-buttons {
                            position: fixed;
                            bottom: 20px;
                            left: 0;
                            right: 0;
                            z-index: 9999;
                            display: flex !important;
                            flex-direction: row !important;
                            justify-content: center !important;
                            align-items: center !important;
                            gap: 8px !important;
                            width: 100% !important;
                            padding: 0 15px;
                        }

                        .mobile-action-buttons .btn {
                            width: 40px !important;
                            height: 40px !important;
                            border-radius: 12px !important;
                            display: flex !important;
                            align-items: center;
                            justify-content: center;
                            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
                            padding: 0 !important;
                        }

                        #saveButton {
                            width: auto !important;
                            min-width: 90px !important;
                            padding: 0 15px !important;
                        }

                        #saveButton span {
                            display: inline-block !important;
                            margin-left: 5px;
                            font-size: 13px;
                        }

                        .mobile-tabs-dropdown {
                            border-radius: 14px !important;
                            border: none !important;
                            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
                            padding: 8px !important;
                            min-width: 220px !important;
                            margin-bottom: 10px !important;
                        }

                        .mobile-tabs-dropdown .dropdown-item {
                            display: flex !important;
                            align-items: center;
                            justify-content: flex-start;
                            padding: 8px 14px !important;
                            border-radius: 8px !important;
                            font-size: 13px;
                            font-weight: 500;
                            color: #495057;
                            transition: all 0.2s ease;
                        }

                        .mobile-tabs-dropdown .dropdown-item i {
                            font-size: 16px;
                            width: 20px;
                        }

                        .mobile-tabs-dropdown .dropdown-item.active {
                            background: var(--bs-primary) !important;
                            color: #ffffff !important;
                        }

                        .personel-select-container {
                            margin-bottom: 10px;
                            width: 100%;
                        }

                        .card-header .col-md-5 {
                            padding-top: 5px;
                        }
                    }

                    /* Global Feather Icon Fix */
                    #personelTabContent svg.feather,
                    #personelTabContent [data-feather] {
                        width: 18px !important;
                        height: 18px !important;
                        stroke: currentColor;
                        stroke-width: 2;
                        stroke-linecap: round;
                        stroke-linejoin: round;
                        fill: none;
                        display: inline-block;
                        vertical-align: middle;
                    }

                    .invalid-feedback {
                        display: block !important;
                        width: 100%;
                        margin-top: 0.25rem;
                        font-size: 80%;
                        color: #f46a6a;
                    }
                </style>
                <div class="tab-content p-3 text-muted" id="personelTabContent">
                    <form id="personelForm" enctype="multipart/form-data">
                        <input type="hidden" name="personel_id" id="personel_id"
                            value="<?php echo $personel->id ?? ''; ?>">
                        <!-- Tab panes -->
                        <div class="tab-pane <?php echo $activeTab === 'home' ? 'active show' : ''; ?>" id="home"
                            role="tabpanel">
                            <?php include_once "icerik/genel_bilgiler.php"; ?>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'calisma' ? 'active show' : ''; ?>" id="calisma"
                            role="tabpanel">
                            <?php include_once "icerik/calisma_bilgileri.php"; ?>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'finansal' ? 'active show' : ''; ?>"
                            id="finansal" role="tabpanel">
                            <?php include_once "icerik/finansal_bilgiler.php"; ?>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'diger' ? 'active show' : ''; ?>" id="diger"
                            role="tabpanel">
                            <?php include_once "icerik/diger_bilgiler.php"; ?>
                        </div>
                    </form>
                    <?php include_once "icerik/modals/ekip_gecmisi.php"; ?>
                    <?php include_once "icerik/modals/gorev_gecmisi.php"; ?>
                    <?php include_once "icerik/modals/calisma_gecmisi.php"; ?>
                    <?php include_once "icerik/modals/calisma_bilgileri_bilgi.php"; ?>
                    <?php include_once "icerik/modals/ozel_is_turu_ucreti.php"; ?>
                    <!-- Dinamik yüklenen tab'lar (izinler, zimmetler vb.) form dışında kalmalı, iç içe form sorunu yaşanmaması için -->
                    <?php if ($id > 0): ?>
                        <div class="tab-pane <?php echo $activeTab === 'izinler' ? 'active show' : ''; ?>" id="izinler"
                            role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=izinler&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'zimmetler' ? 'active show' : ''; ?>" id="zimmetler"
                            role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=zimmetler&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'kesintiler' ? 'active show' : ''; ?>"
                            id="kesintiler" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=kesintiler&id=<?php echo $id; ?>&filter_mode=<?= $_SESSION['filter_kesinti_mode'] ?? 'donem' ?>&filter_kesinti_baslangic=<?= $_SESSION['filter_kesinti_baslangic'] ?? '' ?>&filter_kesinti_bitis=<?= $_SESSION['filter_kesinti_bitis'] ?? '' ?><?= !empty($_SESSION['filter_kesinti_donem']) ? '&filter_kesinti_donem=' . $_SESSION['filter_kesinti_donem'] : '' ?>&filter_kesinti_ay_yil=<?= $_SESSION['filter_kesinti_ay_yil'] ?? date('Y-m') ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'ek_odemeler' ? 'active show' : ''; ?>"
                            id="ek_odemeler" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=ek_odemeler&id=<?php echo $id; ?>&filter_mode=<?= $_SESSION['filter_ek_mode'] ?? 'donem' ?>&filter_ek_baslangic=<?= $_SESSION['filter_ek_baslangic'] ?? '' ?>&filter_ek_bitis=<?= $_SESSION['filter_ek_bitis'] ?? '' ?><?= !empty($_SESSION['filter_ek_donem']) ? '&filter_ek_donem=' . $_SESSION['filter_ek_donem'] : '' ?>&filter_ek_ay_yil=<?= $_SESSION['filter_ek_ay_yil'] ?? date('Y-m') ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'icralar' ? 'active show' : ''; ?>" id="icralar"
                            role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=icralar&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'vergi_matrahlari' ? 'active show' : ''; ?>"
                            id="vergi_matrahlari" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=vergi_matrahlari&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane <?php echo $activeTab === 'finansal_islemler' ? 'active show' : ''; ?>"
                            id="finansal_islemler" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=finansal_islemler&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'evraklar' ? 'active show' : ''; ?>" id="evraklar"
                            role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=evraklar&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane <?php echo $activeTab === 'puantaj' ? 'active show' : ''; ?>" id="puantaj"
                            role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=puantaj&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'giris_loglari' ? 'active show' : ''; ?>"
                            id="giris_loglari" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=giris_loglari&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div><!-- end card-body -->

            <div class="card-footer">

            </div>
        </div>
    </div>
</div>

<script>
    function loadTabContent(targetPane, callback) {
        if (targetPane && targetPane.hasAttribute('data-url') && targetPane.getAttribute('data-loaded') === 'false') {
            var url = targetPane.getAttribute('data-url');
            $(targetPane).html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>');

            $.get(url, function (html) {
                $(targetPane).html(html);
                targetPane.setAttribute('data-loaded', 'true');
                initPlugins(targetPane);
                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function () {
                $(targetPane).html('<div class="alert alert-danger">İçerik yüklenirken bir hata oluştu.</div>');
            });
        }
    }

    window.loadTabContent = loadTabContent;
    window.initPlugins = initPlugins;

    window.reloadActiveTab = function (callback) {
        var activePane = document.querySelector('.tab-pane.active');
        if (activePane && activePane.hasAttribute('data-url')) {
            activePane.setAttribute('data-loaded', 'false');
            loadTabContent(activePane, callback);
        }
    };

    window.reloadTab = function (tabId, callback) {
        var pane = document.getElementById(tabId);
        if (pane && pane.hasAttribute('data-url')) {
            pane.setAttribute('data-loaded', 'false');
            loadTabContent(pane, callback);
        }
    };

    window.invalidateAllTabs = function () {
        var panes = document.querySelectorAll('.tab-pane[data-url]');
        panes.forEach(function (pane) {
            pane.setAttribute('data-loaded', 'false');
        });
    };

    function initPlugins(container) {
        if ($(container).find(".select2:not(#topbar-personel-search)").length > 0) {
            $(container).find(".select2:not(#topbar-personel-search)").each(function () {
                var tags = $(this).data('tags') || false;
                $(this).select2({
                    tags: tags,
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body)
                });
            });
        }

        if ($(container).find(".flatpickr:not(.flatpickr-input)").length > 0) {
            $(container).find(".flatpickr:not(.flatpickr-input)").each(function () {
                if (!this._flatpickr) {
                    $(this).flatpickr({
                        dateFormat: "d.m.Y",
                        altInput: true,
                        altFormat: "d.m.Y",
                        locale: "tr",
                        onChange: function (selectedDates, dateStr, instance) {
                            var elem = null;
                            try {
                                if (instance && instance.element) {
                                    elem = instance.element;
                                } else if (this && this.element) {
                                    elem = this.element;
                                }
                                if (elem) $(elem).trigger('change');
                            } catch (e) {
                                console.error('Flatpickr onChange error:', e);
                            }
                        }
                    });
                }
            });
        }

        if ($(container).find(".flatpickr-date:not(.flatpickr-input)").length > 0) {
            $(container).find(".flatpickr-date:not(.flatpickr-input)").each(function () {
                if (!this._flatpickr) {
                    $(this).flatpickr({
                        enableTime: true,
                        dateFormat: "d.m.Y H:i",
                        time_24hr: true,
                        locale: "tr",
                        onChange: function (selectedDates, dateStr, instance) {
                            var elem = null;
                            try {
                                if (instance && instance.element) {
                                    elem = instance.element;
                                } else if (this && this.element) {
                                    elem = this.element;
                                }
                                if (elem) $(elem).trigger('change');
                            } catch (e) {
                                console.error('Flatpickr-date onChange error:', e);
                            }
                        }
                    });
                }
            });
        }

        if ($(container).find(".datatable").length > 0) {
            $(container).find(".datatable").each(function () {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable(getDatatableOptions());
                }
            });
        }

        // Segmented Control Aktiflik Durumunu Senkronize Et
        if ($(container).find(".segmented-control-container").length > 0) {
            $(container).find(".segmented-control-container").each(function () {
                var c = $(this);
                var checked = c.find('.segmented-control-input:checked');
                c.find('.segmented-control-label').removeClass('active');
                if (checked.length) {
                    var firstChecked = checked.first();
                    c.find('.segmented-control-input').not(firstChecked).prop('checked', false).removeAttr('checked');
                    firstChecked.prop('checked', true).attr('checked', 'checked');
                    var forId = firstChecked.attr('id');
                    if (forId) {
                        c.find('label[for="' + forId + '"]').addClass('active');
                    }
                }
            });
        }

        if (typeof feather !== "undefined") {
            feather.replace();
        }
    }

    // Global Segmented Control Delegasyonu
    $(document).off('change.segmented', '.segmented-control-input').on('change.segmented', '.segmented-control-input', function () {
        var c = $(this).closest('.segmented-control-container');
        if (c.length) {
            c.find('.segmented-control-input').not(this).prop('checked', false).removeAttr('checked');
            c.find('.segmented-control-label').removeClass('active');
            var forId = $(this).attr('id');
            if (forId) {
                c.find('label[for="' + forId + '"]').addClass('active');
            }
        }
    });

    $(document).off('click.segmented', '.segmented-control-label').on('click.segmented', '.segmented-control-label', function (e) {
        var c = $(this).closest('.segmented-control-container');
        var forId = $(this).attr('for');
        var input = c.length && forId ? c.find('#' + forId) : (forId ? $('#' + forId) : null);
        if (input && input.length) {
            e.preventDefault();
            if (c.length) {
                c.find('.segmented-control-input').prop('checked', false).removeAttr('checked');
                c.find('.segmented-control-label').removeClass('active');
            }
            input.prop('checked', true).attr('checked', 'checked');
            $(this).addClass('active');
            input.trigger('change');
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        initPlugins(document);

        // Personel seçimi değiştiğinde yönlendir
        $('#personel_select').on('change', function () {
            var selectedId = $(this).val();
            var activeTab = $('.subtab-nav-link.active').attr('href') || $('.nav-link.active').attr('href');
            if (activeTab) {
                activeTab = activeTab.replace('#', '');
            } else {
                activeTab = 'home';
            }
            if (selectedId) {
                window.location.href = 'index?p=personel/manage&id=' + selectedId + '&tab=' + activeTab;
            }
        });

        // Kategori butonlarına tıklandığında alt sekmeleri göster
        $('.btn-category-pill').on('click', function(e) {
            e.preventDefault();
            var catKey = $(this).data('category-target');
            
            // Kategori butonlarını güncelle
            $('.btn-category-pill').removeClass('active');
            $(this).addClass('active');
            
            // Alt sekme gruplarını göster/gizle
            $('.subtab-group').addClass('d-none').removeClass('d-flex');
            var targetGroup = $('#subtabs-' + catKey);
            targetGroup.removeClass('d-none').addClass('d-flex');
            
            // Eğer bu kategorideki sekmelerden biri zaten aktif değilse ilk sekmeyi aktif yap
            var currentActiveInCat = targetGroup.find('.subtab-nav-link.active');
            if (currentActiveInCat.length === 0) {
                var firstTab = targetGroup.find('.subtab-nav-link').first();
                if (firstTab.length) {
                    var tab = new bootstrap.Tab(firstTab[0]);
                    tab.show();
                }
            }
        });

        // Tab değişikliklerini dinle
        var triggerTabList = [].slice.call(document.querySelectorAll('.subtab-nav-link[data-bs-toggle="tab"], #desktopTabs [data-bs-toggle="tab"]'));
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('show.bs.tab', function (event) {
                var targetId = event.target.getAttribute('href');
                // Form içi ve form dışı tüm üst düzey tab-pane elemanlarını gizle
                $('#personelTabContent > .tab-pane, #personelTabContent > form > .tab-pane').removeClass('active show');
                $(targetId).addClass('active show');
            });

            triggerEl.addEventListener('shown.bs.tab', function (event) {
                var targetId = event.target.getAttribute('href');
                var catKey = event.target.getAttribute('data-category');
                
                // Eğer kategori aktif değilse senkronize et
                if (catKey) {
                    $('.btn-category-pill').removeClass('active');
                    $('.btn-category-pill[data-category-target="' + catKey + '"]').addClass('active');
                    
                    $('.subtab-group').addClass('d-none').removeClass('d-flex');
                    $('#subtabs-' + catKey).removeClass('d-none').addClass('d-flex');
                }

                // Hedef dışındakileri temizle, hedefi göster
                $('#personelTabContent > .tab-pane, #personelTabContent > form > .tab-pane').not(targetId).removeClass('active show');
                $(targetId).addClass('active show');
                
                var targetPane = document.querySelector(targetId);
                loadTabContent(targetPane);

                // Sync mobile dropdown active state
                $('.mobile-tab-link').removeClass('active');
                $('.mobile-tab-link[data-target="' + targetId + '"]').addClass('active');
            });
        });

        // Mobile Tab Click Handler
        $(document).on('click', '.mobile-tab-link', function (e) {
            e.preventDefault();
            var target = $(this).data('target');
            var tabEl = document.querySelector('.subtab-nav-link[href="' + target + '"]') || document.querySelector('#desktopTabs a[href="' + target + '"]');
            if (tabEl) {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        });

        // Sayfa yüklendiğinde aktif tab eğer dinamik içerikliyse yükle
        var activeTabLink = document.querySelector('.subtab-nav-link.active') || document.querySelector('.nav-link.active');
        if (activeTabLink) {
            var targetId = activeTabLink.getAttribute('href');
            var targetPane = document.querySelector(targetId);
            loadTabContent(targetPane);
        }
    });
</script>
<script src="views/personel/js/zimmet.js?v=<?= filemtime(__DIR__ . '/js/zimmet.js') ?>"></script>
<script src="views/personel/js/kesinti.js?v=<?= filemtime(__DIR__ . '/js/kesinti.js') ?>"></script>
<script src="views/personel/js/ek_odeme.js?v=<?= filemtime(__DIR__ . '/js/ek_odeme.js') ?>"></script>
<script src="views/personel/js/icra.js?v=<?= filemtime(__DIR__ . '/js/icra.js') ?>"></script>
<script src="views/personel/js/evrak.js?v=<?= filemtime(__DIR__ . '/js/evrak.js') ?>"></script>
<script src="assets/libs/pdfjs/pdf.min.js"></script>
<script src="assets/libs/tesseract.js/tesseract.min.js"></script>
<script src="views/personel/js/belge-ocr-ayristirici.js?v=<?= filemtime(__DIR__ . '/js/belge-ocr-ayristirici.js') ?>"></script>
<script src="views/personel/js/belge-analiz.js?v=<?= filemtime(__DIR__ . '/js/belge-analiz.js') ?>"></script>
<script>
    window.personelData = {
        maas_tutari: <?= floatval($personel->maas_tutari ?? 0) ?>,
        maas_durumu: "<?= $personel->maas_durumu ?? '' ?>",
        tc_kimlik_no: "<?= $personel->tc_kimlik_no ?? '' ?>",
        adi_soyadi: "<?= $personel->adi_soyadi ?? '' ?>"
    };
</script>


    
<?php }else{
    Alert::danger('Bu sayfayı görüntüleme yetkiniz bulunmamaktadır.Lütfen yöneticiye başvurun.');
    
} ?>
