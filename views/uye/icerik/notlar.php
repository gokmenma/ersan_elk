<?php
use App\Helper\Form;
use App\Helper\Date;
use App\Helper\Security;
use App\Helper\Helper;

use App\Model\NotModel;




$notes = [];
if (isset($_GET['id'])) {
    $Notes = new NotModel();
    $notes = $Notes->where('uye_id', Security::decrypt($_GET['id']));

}
?>
<div class="row ">
    <div class="col-md-12">
        <button type="button" id="notEkle" class="btn btn-success waves-effect btn-label waves-light float-end"
           ><i class="bx bx-plus label-icon"></i>Yeni Not
        </button>

    </div>

    <div class="overflow-auto">
        <table id="notesTable" class="datatable table-hover table table-bordered nowrap w-100 ">
            <thead>
                <tr>
                    <th style="width:5%">Sıra</th>
                    <th style="width:7%">Tarihi</th>
                    <th style="width:73%">Açıklama</th>
                    <th style="width:10%">Kayıt Tarihi</th>
                    <th style="width:5%">İşlem</th>
                </tr>
            </thead>


            <tbody>

                <?php

                foreach ($notes as $note) {
                    $enc_id = Security::encrypt($note->id);
                    ?>
                    <tr data-id="<?php echo $enc_id; ?>">
                        <td style="width:5%">
                            <?php echo $note->id ?>
                        </td>
                        <td style="width:7%">
                            <?php echo Date::dmy($note->tarih) ?>
                        </td>

                        <td style="width:73%">
                            <?php echo $note->not_aciklama ?>
                        </td>
                        <td>
                            <?php echo $note->kayit_tarihi ?>
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
                                            class="dropdown-item note-duzenle"><span
                                                class="mdi mdi-account-edit font-size-18"></span>
                                            Düzenle</a>
                                        <a href="#" class="dropdown-item note-sil" data-id="<?php echo $enc_id; ?>"
                                            data-name="<?php echo $note->tarih; ?>">
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

<div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="notesModalModalLabel">Üye Notları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="notesModalForm">
                    <input type="hidden" name="note_id" id="note_id" class="form-control" value="0">
                    <div class="row">
                        <div class="col-md-12">

                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Üye ile ilgili notlar ekleyebilirsiniz.Örn: çiçek gönderildi vb.
                                
                            </div>
                            <hr>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "note_tarihi",
                                    date("d.m.Y"),
                                    "Tarihi giriniz!",
                                    "Tarihi",
                                    "calendar",
                                    "form-control flatpickr"

                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "note_konu",
                                    'Genel',
                                    "Konu giriniz!",
                                    "Not Konusu",
                                    "folder",
                                    "form-control"

                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "note",
                                    $uye->aciklama ?? "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "list",


                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="notesKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>