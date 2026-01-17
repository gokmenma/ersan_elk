<?php

use App\Model\UyeIslemModel;
use App\Helper\Form;
use App\Helper\Date;
use App\Helper\Security;

$UyeIslem = new UyeIslemModel();

$uye_islemleri = [];
if (isset($_GET['id'])) {
    $uye_islemleri = $UyeIslem->getUyeIslemleri(Security::decrypt($id));
}
?>
<div class="row ">
    <div class="col-md-12">
        <button type="button" id="uyeIslemEkle" class="btn btn-success waves-effect btn-label waves-light float-end"
            ><i class="bx bx-plus label-icon"></i>Yeni İşlem
        </button>

    </div>

    <div class="overflow-auto">
        <table id="uyeIslemTable" class="datatable table-hover table table-bordered nowrap w-100 ">
            <thead>
                <tr>
                    <th style="width:3%">Sıra</th>
                    <th>Üyelik Tarihi</th>
                    <th>İstifa Tarihi</th>
                    <th>Karar Tarihi/No</th>
                    <th>Giden Evrak No</th>
                    <th>Birim Evrak No</th>
                    <th>Açıklama</th>
                    <th>Kayıt Tarihi</th>
                    <th>İşlem</th>
                </tr>
            </thead>


            <tbody>

                <?php

                foreach ($uye_islemleri as $islem) {
                    $enc_id = Security::encrypt($islem->id);
                    ?>
                    <tr id="islem_<?php echo $islem->id ?>" data-id="<?php echo $enc_id ?>">
                        <td style="width:3%">
                            <?php echo $islem->id ?>
                        </td>
                        <td>
                            <?php echo Date::dmy($islem->uyelik_tarihi) ?>
                        </td>
                        <td>
                            <?php echo Date::dmY($islem->istifa_tarihi) ?>
                        </td>
                        <td>
                            <?php echo $islem->karar_tarihi_no ?>
                        </td>
                        <td>
                            <?php echo $islem->giden_evrak ?>
                        </td>
                        <td>
                            <?php echo $islem->birim_evrak ?>
                        <td>
                            <?php echo $islem->aciklama ?>
                        </td>
                        <td>
                            <?php echo $islem->kayit_tarihi ?>
                        </td>
                        <td class="text-center" style="width:5%">
                            <div class="flex-shrink-0">
                                <div class="dropdown align-self-start icon-demo-content">
                                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-list-ul font-size-24 text-dark"></i>
                                    </a>
                                    <div class="dropdown-menu">
                                        <a href="#" data-id="<?php echo $enc_id; ?>"
                                            class="dropdown-item uye-islem-duzenle"><span
                                                class="mdi mdi-account-edit font-size-18"></span>
                                            Düzenle</a>
                                        <a href="#" class="dropdown-item uye-islem-sil" data-id="<?php echo $enc_id; ?>">
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

<div class="modal fade" id="uyeIslemModal" tabindex="-1" aria-labelledby="uyeIslemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">


            <div class="modal-header">
                <h5 class="modal-title" id="uyeIslemModalLabel">Üyelik İşlemleri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uyeIslemForm">
                    <input type="hidden" name="islem_id" id="islem_id" class="form-control" value="0">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="alert alert-info alert-dismissible alert-label-icon label-arrow fade show mb-0"
                                role="alert">
                                <i class="mdi mdi-alert-circle-outline label-icon"></i><strong>Bilgi!</strong> - Üyelik
                                bilgileri veya istifa bilgilerini buradan girebilirsiniz
                            </div>

                        </div>
                    </div>
                    <hr>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "uyelik_tarihi",
                                    date("d.m.Y"),
                                    "Üyelik Tarihi giriniz!",
                                    "Üyelik Tarihi",
                                    "calendar",
                                    "form-control flatpickr"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "istifa_tarihi",
                                    "",
                                    "İstifa Tarihi giriniz!",
                                    "İstifa Tarihi",
                                    "calendar",
                                    "form-control flatpickr"

                                ); ?>
                        </div>

                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "karar_tarihi_no",
                                    "",
                                    "Karar Tarihi ve No giriniz!",
                                    "Karar Tarihi/No",
                                    "calendar",
                                    "form-control"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "giden_evrak",
                                    "",
                                    "Giden Evrak No giriniz!",
                                    "Giden Evrak Tarih/No",
                                    "hash",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "birim_evrak",
                                    "",
                                    "Birim Evrak No giriniz!",
                                    "Birim Evrak Tarih/No",
                                    "hash",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
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
                <button type="reset" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="uyeIslemKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>

        </div>
    </div>
</div>