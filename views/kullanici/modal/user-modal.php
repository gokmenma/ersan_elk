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
        font-size: 0.9rem;
        font-weight: 600;
        color: #5b73e8;
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .user-modal .form-check-label {
        font-size: 0.85rem;
    }

    .user-modal .form-check.form-switch {
        margin-bottom: 0.25rem !important;
    }

    .user-modal .mail-notification-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }

    .user-modal .mail-notification-item {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 0.65rem 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.65rem;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        margin-bottom: 0;
        /* Label olduğu için margin sıfırlama */
    }

    .user-modal .mail-notification-item:hover {
        background: linear-gradient(135deg, #e3f2fd 0%, #f5f9ff 100%);
        border-color: #5b73e8;
        box-shadow: 0 2px 8px rgba(91, 115, 232, 0.15);
        transform: translateY(-1px);
    }

    .user-modal .mail-notification-item .form-check-input {
        margin: 0;
        width: 1.25rem;
        height: 1.25rem;
        cursor: pointer;
        border: 2px solid #ced4da;
        transition: all 0.2s ease;
    }

    .user-modal .mail-notification-item .form-check-input:checked {
        background-color: #5b73e8;
        border-color: #5b73e8;
        box-shadow: 0 0 0 3px rgba(91, 115, 232, 0.15);
    }

    .user-modal .mail-notification-item .form-check-input:focus {
        box-shadow: 0 0 0 3px rgba(91, 115, 232, 0.2);
    }

    .user-modal .mail-notification-item span {
        margin: 0;
        font-size: 0.82rem;
        cursor: pointer;
        color: #495057;
        font-weight: 500;
        flex: 1;
    }

    .user-modal .mail-notification-item svg,
    .user-modal .mail-notification-item i[data-feather] {
        width: 16px !important;
        height: 16px !important;
        color: #5b73e8;
        opacity: 0.7;
        transition: opacity 0.3s ease;
    }

    .user-modal .mail-notification-item:hover svg,
    .user-modal .mail-notification-item:hover i[data-feather] {
        opacity: 1;
    }
</style>

<div class="modal-header py-2">
    <h5 class="modal-title" id="uyeIslemModalLabel">
        <i class="mdi mdi-account-cog me-2"></i>Kullanıcı İşlemleri
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body user-modal py-2">
    <form id="userForm">
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $_GET["id"] ?? 0; ?>">

        <!-- Uyarı -->
        <div class="alert alert-success mb-2" role="alert">
            <i class="mdi mdi-information-outline me-1"></i>
            Kullanıcı adı ve şifre benzersiz olmalıdır. Şifre en az 8 karakter olmalıdır.
        </div>

        <!-- Temel Bilgiler -->
        <div class="section-title"><i class="mdi mdi-account me-1"></i>Temel Bilgiler</div>
        <div class="row mb-2">
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("text", "user_name", $user->user_name ?? '', "", "Kullanıcı Adı", "user"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("password", "password", '', "", "Şifre", "lock"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormFloatInput("text", "adi_soyadi", $user->adi_soyadi ?? '', "", "Adı Soyadı", "user"); ?>
            </div>
            <div class="col-md-3">
                <?php echo Form::FormSelect2(
                    name: "durum",
                    label: "Durum",
                    options: ['Aktif' => 'Aktif', 'Pasif' => 'Pasif'],
                    selectedValue: $user->durum ?? 'Aktif',
                    icon: "info-circle"
                ); ?>
            </div>
        </div>
        <div class="row mb-2">
            <div class="col-md-4">
                <?php echo Form::FormFloatInput("text", "gorevi", $user->gorevi ?? '', "", "Görevi", "briefcase"); ?>
            </div>
            <div class="col-md-4">
                <?php echo Form::FormFloatInput("email", "email_adresi", $user->email_adresi ?? '', "", "E-Posta", "mail"); ?>
            </div>
            <div class="col-md-4">
                <?php echo Form::FormFloatInput("text", "telefon", $user->telefon ?? '', "", "Telefon", "phone"); ?>
            </div>
        </div>

        <!-- Yetki ve Firma -->
        <div class="section-title"><i class="mdi mdi-shield-account me-1"></i>Yetki ve Firma Ayarları</div>
        <div class="row mb-2">
            <div class="col-md-6">
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
            <div class="col-md-6">
                <?php echo Form::FormMultipleSelect2(
                    name: "user_firms",
                    options: $firmalar,
                    selectedValues: $user_firmler,
                    label: "Yetkili olduğu Şubeler",
                    icon: "briefcase",
                    valueField: "id",
                    textField: "firma_adi"
                ); ?>
            </div>
        </div>

        <!-- İzin Onay Ayarları -->
        <div class="section-title"><i class="mdi mdi-calendar-check me-1"></i>İzin Onay Ayarları</div>
        <div class="row mb-2">
            <div class="col-md-6">
                <?php echo Form::FormSelect2(
                    name: "izin_onayi_yapacakmi",
                    label: "İzin Onayı Yapacak mı?",
                    options: ['Evet' => 'Evet', 'Hayır' => 'Hayır'],
                    selectedValue: $user->izin_onayi_yapacakmi ?? 'Hayır',
                    icon: "check-circle"
                ); ?>
            </div>
            <div class="col-md-6">
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
                <span>Avans Talepleri</span>
            </label>
            <label class="mail-notification-item" for="mail_izin_talep">
                <input class="form-check-input" type="checkbox" id="mail_izin_talep" name="mail_izin_talep" value="Evet"
                    <?= ($user->mail_izin_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="calendar"></i>
                <span>İzin Talepleri</span>
            </label>
            <label class="mail-notification-item" for="mail_genel_talep">
                <input class="form-check-input" type="checkbox" id="mail_genel_talep" name="mail_genel_talep"
                    value="Evet" <?= ($user->mail_genel_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="message-square"></i>
                <span>Genel Talepler</span>
            </label>
            <label class="mail-notification-item" for="mail_ariza_talep">
                <input class="form-check-input" type="checkbox" id="mail_ariza_talep" name="mail_ariza_talep"
                    value="Evet" <?= ($user->mail_ariza_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                <i data-feather="alert-circle"></i>
                <span>Arıza Talepleri</span>
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
                    minHeight: "60px"
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