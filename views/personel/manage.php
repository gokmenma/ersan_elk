<?php

use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;
use App\Service\Gate;
use App\Helper\Alert;

$id = Security::decrypt($_GET['id'] ?? 0);

/**Yetki kontrolü */
if (Gate::allows('personel_duzenle')) {



$PersonelModel = new PersonelModel();
$personel = $id > 0 ? $PersonelModel->findByEkipNo($id) : null;
$TanimlamalarModel = new TanimlamalarModel();

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

$tabs = [
    'home' => ['label' => 'Genel Bilgiler', 'icon' => 'fas fa-home'],
    'calisma' => ['label' => 'Çalışma Bilgileri', 'icon' => 'far fa-user'],
    'finansal' => ['label' => 'Maaş & Görev Bilgileri', 'icon' => 'fas fa-wallet'],
    'diger' => ['label' => 'Diğer Bilgiler', 'icon' => 'far fa-envelope'],
];

if ($id > 0) {
    $tabs += [
        'izinler' => ['label' => 'İzin/Rapor/Eksik Gün', 'icon' => 'bx bx-calendar-event'],
        'zimmetler' => ['label' => 'Zimmetler', 'icon' => 'bx bx-devices'],
        'kesintiler' => ['label' => 'Kesintiler', 'icon' => 'bx bx-minus-circle'],
        'ek_odemeler' => ['label' => 'Ek Ödemeler', 'icon' => 'bx bx-plus-circle'],
        'icralar' => ['label' => 'İcralar', 'icon' => 'bx bx-gavel'],
        'finansal_islemler' => ['label' => 'Hesap Hareketleri', 'icon' => 'bx bx-lira'],
        'evraklar' => ['label' => 'Evraklar', 'icon' => 'bx bx-file'],
        'puantaj' => ['label' => 'İş Takip', 'icon' => 'bx bx-time-five'],
        'giris_loglari' => ['label' => 'Giriş Logları', 'icon' => 'bx bx-history'],
    ];
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
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-3">

                                <div class="avatar-lg position-relative">
                                    <?php
                                    $resimYolu = $personel->resim_yolu ?? '';
                                    ?>
                                    <img id="personelImage"
                                        src="<?php echo !empty($resimYolu) ? $resimYolu : 'assets/images/users/user-dummy-img.jpg'; ?>"
                                        alt="" class="img-thumbnail"
                                        style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px !important;">
                                    <button type="button" class="btn btn-sm btn-light position-absolute bottom-0 end-0"
                                        id="changePhotoButton" style="padding: 2px 6px;">
                                        <i class="bx bx-camera"></i>
                                    </button>
                                    <input type="file" id="avatarInput" name="resim_yolu" accept="image/*"
                                        style="display: none;">
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
                                        <ul class="dropdown-menu dropdown-menu-end mobile-tabs-dropdown"
                                            aria-labelledby="mobileTabsMenuBtn">
                                            <?php foreach ($tabs as $key => $tab): ?>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === $key ? 'active' : ''; ?>"
                                                        href="javascript:void(0);"
                                                        data-target="#<?php echo $key; ?>"><?php echo $tab['label']; ?></a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body pb-0">
                    <!-- Nav tabs (Desktop Only) -->
                    <div class="d-none d-md-flex align-items-center gap-2">
                        <div class="calendar-nav d-flex gap-1">
                            <button type="button"
                                class="btn border shadow-sm d-flex align-items-center justify-content-center tab-scroll-btn"
                                id="scrollTabsLeft" style="height: 38px; width: 38px; border-radius: 8px !important;">
                                <i class="bx bx-chevron-left fs-4"></i>
                            </button>
                        </div>

                        <div class="flex-grow-1 border rounded shadow-sm p-1 overflow-hidden tab-nav-container"
                            style="height: 48px;">
                            <div class="d-flex align-items-center gap-1 overflow-auto no-scrollbar" id="desktopTabs"
                                role="tablist" style="scroll-behavior: smooth; height: 100%;">
                                <?php
                                $count = count($tabs);
                                $i = 0;
                                foreach ($tabs as $key => $tab):
                                    $i++;
                                    $isActive = ($activeTab === $key);
                                    ?>
                                    <a class="nav-link btn <?php echo $isActive ? 'active' : ''; ?> d-flex align-items-center px-3"
                                        style="white-space: nowrap; border-radius: 8px !important; transition: all 0.2s ease; height: 38px; flex-shrink: 0;"
                                        data-bs-toggle="tab" href="#<?php echo $key; ?>" role="tab">
                                        <?php echo $tab['label']; ?>
                                    </a>
                                    <?php if ($i < $count): ?>
                                        <div class="vr mx-1"
                                            style="height: 25px; align-self: center; opacity: 0.15; flex-shrink: 0;">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="calendar-nav d-flex gap-1">
                            <button type="button"
                                class="btn border shadow-sm d-flex align-items-center justify-content-center tab-scroll-btn"
                                id="scrollTabsRight" style="height: 38px; width: 38px; border-radius: 8px !important;">
                                <i class="bx bx-chevron-right fs-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <style>
                    #personelTabContent>.tab-pane,
                    #personelTabContent>form>.tab-pane {
                        display: none;
                    }

                    #personelTabContent>.tab-pane.active,
                    #personelTabContent>form>.tab-pane.active {
                        display: block;
                    }

                    #desktopTabs .nav-link {
                        border: none;
                        background: transparent;
                        color: #adb5bd;
                        font-weight: 500;
                    }

                    #desktopTabs .nav-link.active {
                        background-color: var(--bs-primary) !important;
                        color: #fff !important;
                        font-weight: 700 !important;
                        box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.3);
                    }

                    #desktopTabs .nav-link:hover:not(.active) {
                        background-color: rgba(255, 255, 255, 0.05) !important;
                        color: var(--bs-primary) !important;
                    }

                    .tab-nav-container {
                        background: #fff;
                    }

                    .tab-scroll-btn {
                        background: #fff;
                    }

                    /* Dark Mode Overrides */
                    html[data-bs-theme="dark"] .tab-nav-container {
                        background: #2a3042 !important;
                        border-color: #32394e !important;
                    }

                    html[data-bs-theme="dark"] .tab-scroll-btn {
                        background: #2a3042 !important;
                        border-color: #32394e !important;
                        color: #fff !important;
                    }

                    html[data-bs-theme="dark"] #desktopTabs .nav-link {
                        color: #9299af;
                    }

                    html[data-bs-theme="dark"] #desktopTabs .nav-link.active {
                        background-color: var(--bs-primary) !important;
                        color: #fff !important;
                    }

                    html[data-bs-theme="dark"] #desktopTabs .nav-link:hover:not(.active) {
                        background-color: rgba(255, 255, 255, 0.1) !important;
                        color: #fff !important;
                    }

                    .no-scrollbar::-webkit-scrollbar {
                        display: none;
                    }

                    .no-scrollbar {
                        -ms-overflow-style: none;
                        scrollbar-width: none;
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
                            min-width: 200px !important;
                            margin-bottom: 10px !important;
                        }

                        .mobile-tabs-dropdown .dropdown-item {
                            display: flex !important;
                            align-items: center;
                            justify-content: flex-end;
                            /* Right aligned text */
                            padding: 10px 15px !important;
                            border-radius: 10px !important;
                            font-size: 14px;
                            font-weight: 500;
                            color: #495057;
                            transition: all 0.2s ease;
                        }

                        .mobile-tabs-dropdown .dropdown-item i {
                            font-size: 16px;
                            width: 20px;
                            text-align: center;
                        }

                        .mobile-tabs-dropdown .dropdown-item.active {
                            background: #000000 !important;
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
                            data-url="views/personel/get-tab-content.php?tab=kesintiler&id=<?php echo $id; ?>&filter_mode=<?= $_SESSION['filter_kesinti_mode'] ?? 'donem' ?>&filter_kesinti_baslangic=<?= $_SESSION['filter_kesinti_baslangic'] ?? '' ?>&filter_kesinti_bitis=<?= $_SESSION['filter_kesinti_bitis'] ?? '' ?>&filter_kesinti_donem=<?= $_SESSION['filter_kesinti_donem'] ?? '' ?>&filter_kesinti_ay_yil=<?= $_SESSION['filter_kesinti_ay_yil'] ?? date('Y-m') ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'ek_odemeler' ? 'active show' : ''; ?>"
                            id="ek_odemeler" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=ek_odemeler&id=<?php echo $id; ?>&filter_mode=<?= $_SESSION['filter_ek_mode'] ?? 'donem' ?>&filter_ek_baslangic=<?= $_SESSION['filter_ek_baslangic'] ?? '' ?>&filter_ek_bitis=<?= $_SESSION['filter_ek_bitis'] ?? '' ?>&filter_ek_donem=<?= $_SESSION['filter_ek_donem'] ?? '' ?>&filter_ek_ay_yil=<?= $_SESSION['filter_ek_ay_yil'] ?? date('Y-m') ?>">
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
    function loadTabContent(targetPane) {
        if (targetPane && targetPane.hasAttribute('data-url') && targetPane.getAttribute('data-loaded') === 'false') {
            var url = targetPane.getAttribute('data-url');
            $(targetPane).html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>');

            $.get(url, function (html) {
                $(targetPane).html(html);
                targetPane.setAttribute('data-loaded', 'true');
                initPlugins(targetPane);
            }).fail(function () {
                $(targetPane).html('<div class="alert alert-danger">İçerik yüklenirken bir hata oluştu.</div>');
            });
        }
    }

    window.reloadActiveTab = function () {
        var activePane = document.querySelector('.tab-pane.active');
        if (activePane && activePane.hasAttribute('data-url')) {
            activePane.setAttribute('data-loaded', 'false');
            loadTabContent(activePane);
        }
    };

    window.invalidateAllTabs = function () {
        var panes = document.querySelectorAll('.tab-pane[data-url]');
        panes.forEach(function (pane) {
            pane.setAttribute('data-loaded', 'false');
        });
    };

    function initPlugins(container) {
        /**Sayfada Select2 varsa init yap */
        if ($(container).find(".select2").length > 0) {
            $(container).find(".select2").each(function () {
                $(this).select2({
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

        if (typeof feather !== 'undefined') {
            feather.replace();
            // More aggressive replace for AJAX content
            setTimeout(function () { feather.replace(); }, 50);
            setTimeout(function () { feather.replace(); }, 300);
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        initPlugins(document);

        // Personel seçimi değiştiğinde yönlendir
        $('#personel_select').on('change', function () {
            var selectedId = $(this).val();
            var activeTab = $('.nav-link.active').attr('href');
            if (activeTab) {
                activeTab = activeTab.replace('#', '');
            } else {
                activeTab = 'home';
            }
            if (selectedId) {
                window.location.href = 'index?p=personel/manage&id=' + selectedId + '&tab=' + activeTab;
            }
        });

        // Tab değişikliklerini dinle
        var triggerTabList = [].slice.call(document.querySelectorAll('#desktopTabs [data-bs-toggle="tab"]'))
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('shown.bs.tab', function (event) {
                var targetId = event.target.getAttribute('href');
                var targetPane = document.querySelector(targetId);
                loadTabContent(targetPane);

                // Sync mobile dropdown active state
                $('.mobile-tab-link').removeClass('active');
                $('.mobile-tab-link[data-target="' + targetId + '"]').addClass('active');
            })
        })

        // Mobile Tab Click Handler
        $(document).on('click', '.mobile-tab-link', function (e) {
            e.preventDefault();
            var target = $(this).data('target');
            var tabEl = document.querySelector('#desktopTabs a[href="' + target + '"]');
            if (tabEl) {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        });

        // Sayfa yüklendiğinde aktif tab eğer dinamik içerikliyse yükle
        var activeTabLink = document.querySelector('.nav-link.active');
        if (activeTabLink) {
            var targetId = activeTabLink.getAttribute('href');
            var targetPane = document.querySelector(targetId);
            loadTabContent(targetPane);
        }

        // Tab Kaydırma İşlemleri
        const scrollAmount = 300;
        const tabsContainer = document.getElementById('desktopTabs');

        if (tabsContainer) {
            $('#scrollTabsLeft').on('click', function (e) {
                e.preventDefault();
                tabsContainer.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });

            $('#scrollTabsRight').on('click', function (e) {
                e.preventDefault();
                tabsContainer.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });

            // Sayfa yüklendiğinde aktif tabı görünür yap
            setTimeout(() => {
                const activeTab = tabsContainer.querySelector('.nav-link.active');
                if (activeTab) {
                    const containerRect = tabsContainer.getBoundingClientRect();
                    const tabRect = activeTab.getBoundingClientRect();

                    if (tabRect.left < containerRect.left || tabRect.right > containerRect.right) {
                        activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
            }, 300);
        }
    });
</script>
<script src="views/personel/js/zimmet.js"></script>
<script src="views/personel/js/kesinti.js"></script>
<script src="views/personel/js/ek_odeme.js"></script>
<script src="views/personel/js/icra.js"></script>
<script src="views/personel/js/evrak.js"></script>
<script>
    window.personelData = {
        maas_tutari: <?= floatval($personel->maas_tutari ?? 0) ?>,
        maas_durumu: "<?= $personel->maas_durumu ?? '' ?>"
    };
</script>


    
<?php }else{
    Alert::danger('Bu sayfayı görüntüleme yetkiniz bulunmamaktadır.Lütfen yöneticiye başvurun.');
    
} ?>