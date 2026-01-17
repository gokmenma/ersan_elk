<?php

use App\Model\UyeIslemModel;
use App\Model\UyeFinansalIslemModel;
use App\Helper\Form;
use App\Helper\Date;
use App\Helper\Security;
use App\Helper\Financial;
use App\Helper\Helper;

$UyeFinansalIslem = new UyeFinansalIslemModel();

$Financial = new Financial();

$finansal_islemler = [];
if (isset($_GET['id'])) {
    $finansal_islemler = $UyeFinansalIslem->getUyeFinansalIslemleri(Security::decrypt($id));
}
?>
<div class="row ">
    <div class="col-md-12">
        <button type="button" id="finansalIslemEkle"
            class="btn btn-success waves-effect btn-label waves-light float-end">
            <i class="bx bx-plus label-icon"></i>
            Yeni İşlem
        </button>

    </div>

    <div class="overflow-auto">
        <table id="finansalIslemTable" class="datatable table-hover table table-bordered nowrap w-100 ">

            <thead>
                <tr>
                    <th style="width:5%">Sıra</th>
                    <th>İşlem Tarihi</th>
                    <th style="max-width:5%">Tip</th>
                    <th>İşlem Türü</th>
                    <th>Tutar</th>
                    <th>Açıklama</th>
                    <th>Ödendi mi?</th>
                    <th>Ödeme Tarihi</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlem</th>
                </tr>
            </thead>


            <tbody>

                <?php

                foreach ($finansal_islemler as $finansal_islem) {
                    $enc_id = Security::encrypt($finansal_islem->id);
                    $odendi_mi = $finansal_islem->odendi_mi == 1 ? '<span class="badge badge-success">Ödendi</span>' : '<span class=badge badge-danger>Ödenmedi</span>';
                    $type = $finansal_islem->type == 1 ? 
                                    '<span class="badge badge-success">Gelir</span>' :
                                    '<span class="badge badge-danger text-white">Gider</span>';
                    ?>
                    <tr data-id="<?php echo $enc_id ?>">
                        <td class="text-center" style="width:5%">
                            <?php echo $finansal_islem->id ?>
                        </td>
                        <td>
                            <?php echo Date::dmy($finansal_islem->islem_tarihi) ?>
                        </td>
                        <td>
                            <?php echo $type ?>
                        </td>
                        <td>
                            <?php echo $finansal_islem->islem_turu ?>
                        </td>
                        <td class="text-end">
                            <?php echo Helper::formattedMoney($finansal_islem->tutar ?? 0) ?>
                        </td>
                        <td>
                            <?php echo $finansal_islem->aciklama ?>
                        </td>
                        <td>
                            <?php echo $odendi_mi ?>
                        </td>
                        <td>
                            <?php echo $finansal_islem->odeme_tarihi ?>
                        </td>
                        <td>
                            <?php echo $finansal_islem->kayit_tarihi ?>
                        </td>

                        <td class="text-center" style="width:5%">
                            <div class="flex-shrink-0">
                                <div class="dropdown align-self-start icon-demo-content">
                                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-list-ul font-size-24 text-dark"></i>
                                    </a>
                                    <div class="dropdown-menu">
                                        <?php if ($finansal_islem->odendi_mi == 0) { ?>
                                            <a href="#" data-id="<?php echo $enc_id; ?>"
                                                class="dropdown-item finansal-islem-odendi-yap">
                                                <span class="mdi mdi-cash-check font-size-18"></span>
                                                Ödendi Yap</a>
                                        <?php } ?>
                                        <a href="#" data-id="<?php echo $enc_id; ?>" class="dropdown-item finansal-islem-duzenle"><span
                                                class="mdi mdi-account-edit font-size-18"></span>
                                            Düzenle</a>
                                        <a href="#" class="dropdown-item finansal-islem-sil"
                                            data-id="<?php echo $enc_id; ?>"
                                            data-name="<?php echo $finansal_islem->adi_soyadi ?? ''; ?>">
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
</div> <!-- end row -->

<div class="modal fade" id="finansalIslemModal" tabindex="-1" aria-labelledby="finansalIslemModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="finansalIslemModalLabel">Finansal İşlemler</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="finansalIslemForm">
                    <input type="hidden" name="finansal_islem_id" id="finansal_islem_id" class="form-control" value="0">

                    <div class="row form-selectgroup-boxes row mb-3">
                        <div class="col-md-6">
                            <label class="form-selectgroup-item">
                                <input type="radio" name="type" value="1" class="form-selectgroup-input"
                                    checked="">
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
                            <?php
                            echo Form::FormSelect2(
                                "islem_turu",
                                $Financial->getGelirTurleri(),
                                "",
                                "Gelir Türü seçiniz!",
                                "map-pin",
                                "id",
                                "tur_adi",

                            ); ?>

                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "islem_tarihi",
                                    date("d.m.Y"),
                                    "İşlem Tarihi giriniz!",
                                    "İşlem Tarihi",
                                    "calendar",
                                    "form-control flatpickr"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "tutar",
                                    "",
                                    "Tutar giriniz!",
                                    "Tutar",
                                    "dollar-sign",
                                    "form-control money"

                                ); ?>
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "finansal_aciklama",
                                    $uye->aciklama ?? "",
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
                <button type="button" id="finansalIslemKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="finansalIslemOdemeModal" tabindex="-1" aria-labelledby="finansalIslemOdemeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="finansalIslemOdemeModalLabel">Finansal İşlem Ödeme Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="finansalIslemOdemeForm">
                    <input type="hidden" name="finansal_islem_odeme_id" id="finansal_islem_odeme_id"
                        class="form-control" value="0">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "odeme_islem_tarihi",
                                    date("d.m.Y"),
                                    "İşlem Tarihi giriniz!",
                                    "Ödeme Tarihi",
                                    "calendar",
                                    "form-control flatpickr"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "odeme_fatura_no",
                                    "",
                                    "Fatura giriniz!",
                                    "Fatura No",
                                    "list",
                                    "form-control"

                                ); ?>
                        </div>

                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="finansalIslemOdemeKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>