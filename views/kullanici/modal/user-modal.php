<?php

require_once "../../../vendor/autoload.php";

use App\Helper\Helper;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\UserModel;
use App\Model\UserRolesModel;
use App\Model\FirmaModel;

$User = new UserModel();
$UserRoles = new UserRolesModel();

$id = $_GET['id'] ?? 0;
$decrypted_id = Security::decrypt($id);

$user = $User->find($decrypted_id ?: 0);
$usergroups = $UserRoles->getGroupsOptions();


$firma = new FirmaModel();
$firmalar = $firma->getFirmaList();
$user_firmler = explode(',', $user ? ($user->firma_ids ?? '') : '');
?>

<style>
    .user-modal .row.mb-3 {
        margin-bottom: 0.75rem !important;
    }

    .user-modal .form-floating-custom {
        margin-bottom: 0.5rem !important;
    }

    .user-modal .alert {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        margin-bottom: 0.5rem !important;
    }

    .user-modal .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #3f51b5;
        border-bottom: 2px solid #eef0f7;
        padding-bottom: 0.35rem;
        margin-bottom: 0.65rem;
        background: #f8faff;
        padding: 5px 10px;
        border-radius: 4px;
    }

    .user-modal .row.mb-2 {
        margin-bottom: 0.5rem !important;
    }

    .user-modal .mail-notification-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.5rem;
    }

    .user-modal .mail-notification-item {
        padding: 0.45rem 0.65rem;
    }

    .user-modal .form-floating-custom > label {
        padding: 0.5rem 0.75rem;
    }
</style>

<div class="modal-header py-2 bg-light">
    <h6 class="modal-title" id="uyeIslemModalLabel">
        <i class="mdi mdi-account-cog me-2 text-primary"></i>Kullanıcı İşlemleri
    </h6>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body user-modal py-2">
    <form id="userForm">
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $_GET["id"] ?? 0; ?>">

        <!-- Uyarı -->
        <div class="alert alert-info py-1 mb-2" role="alert">
            <i class="mdi mdi-information-outline me-1"></i>
            Kullanıcı adı/şifre benzersiz olmalı, şifre min. 8 karakter.
        </div>

        <!-- Temel Bilgiler -->
        <div class="section-title"><i class="mdi mdi-account me-1"></i>Temel Bilgiler</div>
        <div class="row mb-2">
            <div class="col-md-4">
                <?php echo Form::FormFloatInput("text", "adi_soyadi", $user->adi_soyadi ?? '', "", "Adı Soyadı", "user"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("text", "user_name", $user->user_name ?? '', "", "Kullanıcı Adı", "shield"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("password", "password", '', "", "Şifre", "lock"); ?>
            </div>
            <div class="col-md-2">
                <?php echo Form::FormSelect2(
                    name: "durum",
                    label: "Durum",
                    options: ['Aktif' => 'Aktif', 'Pasif' => 'Pasif'],
                    selectedValue: $user->durum ?? 'Aktif',
                    icon: "play"
                ); ?>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("text", "gorevi", $user->gorevi ?? '', "", "Görevi", "briefcase"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("email", "email_adresi", $user->email_adresi ?? '', "", "E-Posta", "mail"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("text", "telefon", $user->telefon ?? '', "", "Telefon", "phone"); ?>
            </div>
            <div class="col-md-3">
                <?php 
                $db = (new \App\Core\Db())->db;
                $deptsQuery = $db->query("SELECT DISTINCT departman FROM personel WHERE departman IS NOT NULL AND departman != '' ORDER BY departman");
                $depts = [];
                while($d = $deptsQuery->fetch(PDO::FETCH_ASSOC)) {
                    $depts[$d['departman']] = $d['departman'];
                }
                echo Form::FormSelect2(
                    name: "yonetilen_departman",
                    label: "Yönettiği Departman",
                    options: array_merge(['' => 'Kısıtlama Yok'], $depts),
                    selectedValue: $user->yonetilen_departman ?? '',
                    icon: "users"
                ); ?>
            </div>
        </div>

        <!-- Yetki ve Firma -->
        <div class="section-title"><i class="mdi mdi-shield-account me-1"></i>Yetki ve Firma Ayarları</div>
        <div class="row mb-2">
            <div class="col-md-4">
                <?php echo Form::FormMultipleSelect2(
                    name: "roles",
                    options: $usergroups,
                    selectedValues: explode(',', $user->roles ?? ''),
                    label: "Yetki Grubu",
                    icon: "shield",
                    valueField: "id",
                    textField: "role_name"
                ); ?>
            </div>
            <div class="col-md-4">
                <?php echo Form::FormMultipleSelect2(
                    name: "user_firms",
                    options: $firmalar,
                    selectedValues: $user_firmler,
                    label: "Yetkili olduğu Şubeler",
                    icon: "home",
                    valueField: "id",
                    textField: "firma_adi"
                ); ?>
            </div>
            <div class="col-md-2">
                <?php echo Form::FormSelect2(
                    name: "izin_onayi_yapacakmi",
                    label: "İzin Onayı?",
                    options: ['Evet' => 'Evet', 'Hayır' => 'Hayır'],
                    selectedValue: $user->izin_onayi_yapacakmi ?? 'Hayır',
                    icon: "check-circle"
                ); ?>
            </div>
            <div class="col-md-2">
                <?php echo Form::FormFloatInput("number", "izin_onay_sirasi", $user->izin_onay_sirasi ?? '', "", "Onay Sırası", "hash"); ?>
            </div>
        </div>

        <!-- Mail Bildirim Ayarları -->
        <div class="section-title"><i class="mdi mdi-email-outline me-1"></i>Mail Bildirim Ayarları</div>
        <div class="mail-notification-grid mb-2">
            <label class="mail-notification-item" for="mail_avans_talep">
                <input class="form-check-input" type="checkbox" id="mail_avans_talep" name="mail_avans_talep"
                    value="Evet" <?= ($user->mail_avans_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="dollar-sign"></i>
                <span>Avans</span>
            </label>
            <label class="mail-notification-item" for="mail_izin_talep">
                <input class="form-check-input" type="checkbox" id="mail_izin_talep" name="mail_izin_talep" value="Evet"
                    <?= ($user->mail_izin_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="calendar"></i>
                <span>İzin</span>
            </label>
            <label class="mail-notification-item" for="mail_genel_talep">
                <input class="form-check-input" type="checkbox" id="mail_genel_talep" name="mail_genel_talep"
                    value="Evet" <?= ($user->mail_genel_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="message-square"></i>
                <span>Genel</span>
            </label>
            <label class="mail-notification-item" for="mail_ariza_talep">
                <input class="form-check-input" type="checkbox" id="mail_ariza_talep" name="mail_ariza_talep"
                    value="Evet" <?= ($user->mail_ariza_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="alert-circle"></i>
                <span>Arıza</span>
            </label>
        </div>

        <!-- Açıklama -->
        <div class="row">
            <div class="col-md-12">
                <?php echo Form::FormFloatTextarea(
                    name: "aciklama",
                    value: $user->aciklama ?? '',
                    placeholder: "",
                    label: "Açıklama",
                    icon: "file-text",
                    minHeight: "50px"
                ); ?>
            </div>
        </div>
    </form>
</div>
<div class="modal-footer py-2">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
        <i class="mdi mdi-close me-1"></i>Kapat
    </button>
    <button type="button" id="userSaveBtn" class="btn btn-primary">
        <i class="mdi mdi-content-save me-1"></i>Kaydet
    </button>
</div>