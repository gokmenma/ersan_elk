<?php

require_once "../../../vendor/autoload.php";

use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\UserRolesModel;
use App\Helper\Sube;

$User = new UserModel();
$UserRoles = new UserRolesModel();
$Sube = new Sube();

$user = $User->find(Security::decrypt($_GET['id']) ?? 0);
$usergroups = $UserRoles->getGroupsOptions();



$Sube = new Sube();
$subeler = $Sube->getSubeList();
$user_branchs = explode(',', $user->sube_id ?? '');

?>






<div class="modal-header">
    <h5 class="modal-title" id="uyeIslemModalLabel">Kullanıcı İşlemleri</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form id="userForm">
        <!-- Bu gizli alan, düzenleme işlemi için hangi kaydın güncelleneceğini tutar -->
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $_GET["id"] ?? 0; ?>">
        <div class="row mb-3">
            <div class="col-md-12">

                <div class="alert alert-success mb-0" role="alert">
                    <strong>Not:</strong> Kullanıcı adı ve şifresi benzersiz ve güvenli olmalıdır.
                    <br> <strong>*</strong> Şifre en az 8 karakter uzunluğunda olmalı ve özel karakterler içermelidir
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    type: "text",
                    name: "user_name",
                    value: $user->user_name ?? '',
                    placeholder: "",
                    label: "Kullanıcı Adı",
                    icon: "user"

                );

                ?>
            </div>
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    type: "password",
                    name: "password",
                    value: '',
                    placeholder: "",
                    label: "Şifre",
                    icon: "lock",
                    required: false,
                    autocomplete: "new-password"
                );

                ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <?php
                echo Form::FormFloatInput(
                    "text",
                    "adi_soyadi",
                    $user->adi_soyadi ?? '',
                    "",
                    "Adı Soyadı",
                    "user"

                );

                ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    "text",
                    "gorevi",
                    $user->gorevi ?? '',
                    "Örn.Genel Başkan",
                    "Görevi",
                    "user"
                );
                ?>
            </div>
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    "text",
                    "unvani",
                    $user->unvani ?? '',
                    "",
                    "Unvanı",
                    "user"
                );
                ?>
            </div>
        </div>


        <div class="row mb-3">
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    "email",
                    "email_adresi",
                    $user->email_adresi ?? '',
                    "",
                    "E-Posta",
                    "mail"
                );
                ?>
            </div>
            <div class="col-md-6">
                <?php
                echo Form::FormFloatInput(
                    "text",
                    "telefon",
                    $user->telefon ?? '',
                    "",
                    "Telefon",
                    "phone"
                );
                ?>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <?php
                echo Form::FormSelect2(
                    name: "roles",
                    label: "Yetki Grubu",
                    options: $usergroups,
                    valueField: "id",
                    textField: "role_name",
                    selectedValue: $user->roles ?? '',
                    icon: "users"
                );
                ?>
            </div>
        </div>
        <style>


        </style>
        <div class="row mb-3">
            <div class="col-md-12">
                <span class="text-muted">Kullanıcının yetkili olduğu şubeleri seçiniz</span>
                <div class="form-floating form-floating-custom mb-4">
                    <select class="form-control select2" multiple style="width:100%" id="user_branchs" name="user_branchs[]"
                        placeholder="Enter User Name">
                        <?php foreach($subeler as $key => $value){ ?>
                        <option value="<?php echo $value->id?>"
                            <?php echo in_array($value->id, $user_branchs) ? 'selected' : '' ?>>
                            <?php echo $value->sube_adi?></option>
                        <?php } ?>
                    </select>

                    <div class="form-floating-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="feather feather-users">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <?php
                echo Form::FormFloatTextarea(
                    name: "aciklama",
                    value: $user->aciklama ?? '' ,
                    placeholder: "",
                    label: "Açıklama",
                    icon: "list",
                    minHeight: "100px"
                );
                ?>
            </div>
        </div>


    </form>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary float-start" data-bs-dismiss="modal">Kapat</button>
    <button type="button" id="userSaveBtn" class="btn btn-primary">Kaydet</button>
</div>