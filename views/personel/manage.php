<?php

use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;

$id = Security::decrypt($_GET['id'] ?? 0);
$PersonelModel = new PersonelModel();
$personel = $id > 0 ? $PersonelModel->find($id) : null;
$TanimlamalarModel = new TanimlamalarModel();

if ($personel) {

    $ekip_adi = $personel->ekip_no ? $TanimlamalarModel->getTurAdi($personel->ekip_no) : "Ekip Yok";
    $adi_soyadi_ekipno = $personel->adi_soyadi . " - " . $ekip_adi;
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
$allPersonel = array_map(function ($item) {
    $item->id = Security::encrypt($item->id);
    return $item;
}, $allPersonel);
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
                        <div class="col-md-8">
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
                                    <small class="text-muted">TC:
                                        <?php echo $personel->tc_kimlik_no ?? '---'; ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-wrap gap-2 float-end align-items-center">
                                <!-- /**Yeni personel ekleniyorsa gösterme */ -->
                                <?php if ($id > 0) { ?>
                                    <div style="min-width: 250px;">
                                        <?php echo Form::FormSelect2('personel_select', $allPersonel, $id, 'Personel Değiştir', 'users', 'id', 'adi_soyadi', 'form-select select2'); ?>
                                    </div>
                                <?php } ?>
                                <a href="index?p=personel/list" class="btn btn-light waves-effect waves-light"><i
                                        class="bx bx-left-arrow-alt font-size-16 align-middle"></i></a>

                                <a href="index?p=personel/manage" type="button"
                                    class="btn btn-success waves-effect waves-light" title="Yeni Personel"><i
                                        class="bx bx-plus font-size-16 align-middle"></i></a>
                                <button type="button" id="saveButton"
                                    class="btn btn-primary waves-effect btn-label waves-light">
                                    <i class="bx bx-save label-icon"></i> Kaydet
                                </button>


                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">


                    <!-- Nav tabs -->
                    <?php $activeTab = $_GET['tab'] ?? 'home'; ?>
                    <ul class="nav nav-tabs overflow-x-auto" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'home' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#home" role="tab" aria-selected="false" tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                                <span class="d-none d-sm-block"><i class="fas fa-home me-1"></i> Genel Bilgiler</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'calisma' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#calisma" role="tab" aria-selected="false" tabindex="-1">
                                <span class="d-block d-sm-none"><i class="far fa-user"></i></span>
                                <span class="d-none d-sm-block"><i class="far fa-user me-1"></i> Çalışma
                                    Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'finansal' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#finansal" role="tab" aria-selected="false" tabindex="-1">
                                <span class="d-block d-sm-none"><i class="fas fa-wallet"></i></span>
                                <span class="d-none d-sm-block"><i class="fas fa-wallet me-1"></i> Finansal
                                    Bilgileri</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'diger' ? 'active' : ''; ?>"
                                data-bs-toggle="tab" href="#diger" role="tab" aria-selected="true">
                                <span class="d-block d-sm-none"><i class="far fa-envelope"></i></span>
                                <span class="d-none d-sm-block"><i class="far fa-envelope me-1"></i> Diğer
                                    Bilgiler</span>
                            </a>
                        </li>
                        <?php if ($id > 0): ?>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'izinler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#izinler" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-calendar-event"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-calendar-event me-1"></i>
                                        İzin/Rapor</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'zimmetler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#zimmetler" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-devices"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-devices me-1"></i> Zimmetler</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'kesintiler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#kesintiler" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-minus-circle"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-minus-circle me-1"></i>
                                        Kesintiler</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'ek_odemeler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#ek_odemeler" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-plus-circle"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-plus-circle me-1"></i> Ek
                                        Ödemeler</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'icralar' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#icralar" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-gavel"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-gavel me-1"></i> İcralar</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'finansal_islemler' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#finansal_islemler" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-lira"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-lira me-1"></i> Hesap Hareketleri</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'evraklar' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#evraklar" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-file"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-file me-1"></i> Evraklar</span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link <?php echo $activeTab === 'puantaj' ? 'active' : ''; ?>"
                                    data-bs-toggle="tab" href="#puantaj" role="tab" aria-selected="false">
                                    <span class="d-block d-sm-none"><i class="bx bx-time-five"></i></span>
                                    <span class="d-none d-sm-block"><i class="bx bx-time-five me-1"></i> Puantaj/İş
                                        Takip</span>
                                </a>
                            </li>

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
            if (selectedId) {
                window.location.href = 'index?p=personel/manage&id=' + selectedId;
            }
        });

        // Tab değişikliklerini dinle
        var triggerTabList = [].slice.call(document.querySelectorAll('.nav-tabs a[data-bs-toggle="tab"]'))
        triggerTabList.forEach(function (triggerEl) {
            triggerEl.addEventListener('shown.bs.tab', function (event) {
                var targetId = event.target.getAttribute('href');
                var targetPane = document.querySelector(targetId);
                loadTabContent(targetPane);
            })
        })

        // Sayfa yüklendiğinde aktif tab eğer dinamik içerikliyse yükle
        var activeTabLink = document.querySelector('.nav-tabs a.active');
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