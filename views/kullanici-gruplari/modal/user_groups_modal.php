<?php
require_once dirname(__DIR__, 3) . "/vendor/autoload.php";

use App\Helper\Form;
?>
<div class="modal-header">
    <h5 class="modal-title" id="actionModalLabel">Yetki Grubu İşlemleri</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form id="actionForm">
        <input type="hidden" name="id" id="group_id" class="form-control" value="0">
  <div class="row mb-3">
            <div class="col-md-12">
                <?php
                $colors = [
                    'primary' => 'Mavi (Primary)',
                    'success' => 'Yeşil (Success)',
                    'danger' => 'Kırmızı (Danger)',
                    'warning' => 'Sarı (Warning)',
                    'info' => 'Turkuaz (Info)',
                    'dark' => 'Siyah (Dark)',
                    'secondary' => 'Gri (Secondary)'
                ];
                echo Form::FormSelect2(
                    "role_color",
                    $colors,
                    "secondary",
                    "Grup Rengi",
                    "command"
                ); ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <?php echo
                    Form::FormFloatInput(
                        "text",
                        "role_name",
                        "",
                        "Yetki Grubu Adı giriniz!",
                        "Yetki Grubu Adı",
                        "shield",
                        "form-control"
                    ); ?>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <?php echo
                    Form::FormFloatTextarea(
                        "description",
                        "",
                        "Açıklama giriniz",
                        "Açıklama",
                        "file-text"
                    ); ?>
            </div>
        </div>

      
    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
        data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
    <button type="button" id="actionKaydet" class="btn btn-primary waves-effect btn-label waves-light float-end"><i
            class="bx bx-save label-icon"></i>Kaydet</button>
</div>