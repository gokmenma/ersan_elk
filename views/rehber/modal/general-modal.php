<?php
use App\Helper\Form;

?>

<div class="modal fade" id="rehberModal" tabindex="-1" aria-labelledby="rehberModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rehberModalLabel">Rehber İşlemler</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rehberForm">
                    <input type="hidden" name="id" id="id" class="form-control" value="0">

                    <!--İnfo alert Bilgi ekle -->

                     <div class="alert alert-info alert-dismissible fade show" role="alert">
                        Kişi veya Kurum bilgilerini ekleyebilirsiniz
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div> 

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "adi_soyadi",
                                    "",
                                    "",
                                    "Adı Soyadı",
                                    "user",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">

                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "kurum_adi",
                                    "",
                                    "",
                                    "Kurum Adi",
                                    "hash",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>

                    <!-- Marka ve Model -->

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "telefon",
                                    "",
                                    "",
                                    "Telefon",
                                    "phone",
                                    "form-control"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "telefon2",
                                    "",
                                    "",
                                    "Telefon2",
                                    "phone",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                       
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "email",
                                    "",
                                    "",
                                    "Email",
                                    "at-sign",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "adres",
                                    "",
                                    "",
                                    "Adres",
                                    "map-pin",
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
                                    "file-text",


                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="rehberKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>