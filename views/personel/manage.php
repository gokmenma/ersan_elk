<?php

use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;

$id = Security::decrypt($_GET['id'] ?? 0);
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




$allPersonel = $PersonelModel->all();
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
                                            <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'home' ? 'active' : ''; ?>"
                                                    href="javascript:void(0);" data-target="#home">Genel Bilgiler <i
                                                        class="fas fa-home ms-2"></i></a></li>
                                            <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'calisma' ? 'active' : ''; ?>"
                                                    href="javascript:void(0);" data-target="#calisma">Çalışma Bilgileri
                                                    <i class="far fa-user ms-2"></i></a></li>
                                            <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'finansal' ? 'active' : ''; ?>"
                                                    href="javascript:void(0);" data-target="#finansal">Finansal Bilgiler
                                                    <i class="fas fa-wallet ms-2"></i></a></li>
                                            <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'diger' ? 'active' : ''; ?>"
                                                    href="javascript:void(0);" data-target="#diger">Diğer Bilgiler <i
                                                        class="far fa-envelope ms-2"></i></a></li>
                                            <?php if ($id > 0): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'izinler' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#izinler">İzin/Rapor/Eksik
                                                        Gün <i class="bx bx-calendar-event ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'zimmetler' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#zimmetler">Zimmetler <i
                                                            class="bx bx-devices ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'kesintiler' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#kesintiler">Kesintiler <i
                                                            class="bx bx-minus-circle ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'ek_odemeler' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#ek_odemeler">Ek Ödemeler <i
                                                            class="bx bx-plus-circle ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'icralar' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#icralar">İcralar <i
                                                            class="bx bx-gavel ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'finansal_islemler' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#finansal_islemler">Hesap
                                                        Hareketleri <i class="bx bx-lira ms-2"></i></a></li>
                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'evraklar' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#evraklar">Evraklar <i
                                                            class="bx bx-file ms-2"></i></a></li>

                                                <li><a class="dropdown-item mobile-tab-link <?php echo $activeTab === 'puantaj' ? 'active' : ''; ?>"
                                                        href="javascript:void(0);" data-target="#puantaj">Puantaj/İş Takip
                                                        <i class="bx bx-time-five ms-2"></i></a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Nav tabs (Desktop Only) -->
                    <ul class="nav nav-tabs d-none d-md-flex" role="tablist" id="desktopTabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'home' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#home" role="tab">
                                <i class="fas fa-home me-1"></i> Genel Bilgiler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'calisma' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#calisma" role="tab">
                                <i class="far fa-user me-1"></i> Çalışma Bilgileri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'finansal' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#finansal" role="tab">
                                <i class="fas fa-wallet me-1"></i> Finansal Bilgileri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'diger' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#diger" role="tab">
                                <i class="far fa-envelope me-1"></i> Diğer Bilgiler
                            </a>
                        </li>
                        <?php if ($id > 0): ?>
                            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'izinler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#izinler" role="tab">İzin/Rapor/Eksik Gün</a></li>
                            <li class="nav-item"><a
                                    class="nav-link <?php echo $activeTab === 'zimmetler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#zimmetler" role="tab">Zimmetler</a></li>
                            <li class="nav-item"><a
                                    class="nav-link <?php echo $activeTab === 'kesintiler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#kesintiler" role="tab">Kesintiler</a></li>
                            <li class="nav-item"><a
                                    class="nav-link <?php echo $activeTab === 'ek_odemeler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#ek_odemeler" role="tab">Ek Ödemeler</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'icralar' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#icralar" role="tab">İcralar</a></li>
                            <li class="nav-item"><a
                                    class="nav-link <?php echo $activeTab === 'finansal_islemler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#finansal_islemler" role="tab">Hesap Hareketleri</a></li>
                            <li class="nav-item"><a
                                    class="nav-link <?php echo $activeTab === 'evraklar' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#evraklar" role="tab">Evraklar</a></li>

                            <li class="nav-item"><a class="nav-link <?php echo $activeTab === 'puantaj' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#puantaj" role="tab">Puantaj/İş Takip</a></li>
                        <?php endif; ?>
                    </ul>

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
                            data-url="views/personel/get-tab-content.php?tab=kesintiler&id=<?php echo $id; ?>">
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Yükleniyor...</span>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane <?php echo $activeTab === 'ek_odemeler' ? 'active show' : ''; ?>"
                            id="ek_odemeler" role="tabpanel" data-loaded="false"
                            data-url="views/personel/get-tab-content.php?tab=ek_odemeler&id=<?php echo $id; ?>">
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

        /**Flatpickr varsa init yap */
        if ($(container).find(".flatpickr").length > 0) {
            $(container).find(".flatpickr").flatpickr({
                dateFormat: "d.m.Y",
                altInput: true,
                altFormat: "d.m.Y",
                locale: "tr",
                onChange: function (selectedDates, dateStr, instance) {
                    $(instance.element).trigger('change');
                }
            });
        }

        if ($(container).find(".flatpickr-date").length > 0) {
            $(container).find(".flatpickr-date").flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "tr"
            });
        }

        /**DataTable varsa init yap */
        if ($(container).find(".datatable").length > 0) {
            $(container).find(".datatable").each(function () {
                if (!$.fn.DataTable.isDataTable(this)) {
                    $(this).DataTable(getDatatableOptions());
                }
            });
        }

        feather.replace();
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
    });
</script>
<script src="views/personel/js/zimmet.js"></script>
<script src="views/personel/js/kesinti.js"></script>
<script src="views/personel/js/ek_odeme.js"></script>
<script src="views/personel/js/icra.js"></script>
<script src="views/personel/js/evrak.js"></script>