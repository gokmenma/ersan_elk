<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Financial;

$Financial = new Financial();


use App\Model\TanimlamalarModel;
$Tanimlama = new TanimlamalarModel();

$turler = $Tanimlama->all()->get();

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Tanımlamalar";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Gelir-Gider Türleri Listesi</h4>
                        <p class="card-title-desc">Gelir Gider türlerini görüntüleyebilir ve yeni tür
                            ekleyebilirsiniz.

                        </p>
                    </div>

                    <div class="col-md-4">

                        <button type="button" id="actionEkle"
                            class="btn btn-primary waves-effect btn-label waves-light float-end" data-bs-toggle="modal"
                            data-bs-target="#actionModal"><i class="bx bx-save label-icon"></i>Yeni Ekle
                        </button>
                        <button type="button" id="actionEkle"
                            class="btn btn-secondary waves-effect btn-label waves-light float-end me-2"
                            data-bs-toggle="modal" data-bs-target="#actionModal">
                            <i class='bx bxs-file-export label-icon'></i>
                            Excele Aktar
                        </button>
                    </div>

                </div>

                <div class="card-body overflow-auto">


                    <table id="actionTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center">Sıra</th>
                                <th class="text-center">İşlem Türü</th>
                                <th class="text-center">Tür Adı</th>
                                <th class="text-center">Açıklama</th>
                                <th class="text-center">Kayıt Tarihi</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($turler as $tur) {
                                $i++;
                                $enc_id = Security::encrypt($tur->id);
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <?php echo $i ?>
                                    </td>
                                    <td class="text-center" style="width:10%">
                                        <?php echo $tur->type == 1 ? "<span class='badge badge-success p-2'>Gelir</span>" : "<span class='badge badge-danger p-2'>Gider</span>" ?>
                                    </td >
                                    <td class="text-center">
                                        <?php echo $tur->tur_adi ?>
                                    </td>
                                

                                    <td class="text-center">
                                        <?php echo $tur->aciklama ?>
                                    </td>

                                    <td class="text-center">
                                        <?php echo $tur->kayit_tarihi ?>
                                    </td>


                                    <td class="text-center" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
                                                    <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="uye-duzenle?id=<?php echo $enc_id; ?>"
                                                        class="dropdown-item duzenle"><span
                                                            class="mdi mdi-account-edit font-size-18"></span>
                                                        Düzenle</a>
                                                    <a href="#" class="dropdown-item gelir-gider-sil"
                                                        data-id="<?php echo $enc_id; ?>">
                                                        <span class="mdi mdi-delete font-size-18"></span>
                                                        Sil</a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->

</div> <!-- container-fluid -->

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Gelir Gider İşlemler</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="tur_id" id="tur_id" class="form-control" value="0">

                    <div class="row form-selectgroup-boxes row mb-3">
                        <div class="col-md-6">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="type" value="1" class="form-selectgroup-input" checked="">
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span class="">
                                        <span class="form-selectgroup-title strong mb-1">Gelir</span>
                                        <span class="d-block text-secondary">Gelir Türünü seçiniz</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="type" value="2" class="form-selectgroup-input">
                                <span class="form-selectgroup-label d-flex align-items-center p-3">
                                    <span class="me-3">
                                        <span class="form-selectgroup-check"></span>
                                    </span>
                                    <span class="form-selectgroup-label-content">
                                        <span class="form-selectgroup-title strong mb-1">Gider</span>
                                        <span class="d-block text-secondary">Gider türünü seçiniz</span>
                                    </span>
                                </span>
                            </label>
                        </div>



                    </div>


                    <div class="row mb-3">

                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "gelir_gider_turu",
                                    "",
                                    "Tür Adı giriniz!",
                                    "Tur Adı",
                                    "briefcase",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
                                    "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "map-pin",


                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="actionKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>