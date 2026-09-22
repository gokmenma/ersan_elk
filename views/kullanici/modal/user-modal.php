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

$isEdit = !empty($user && $user->id);
?>

<style>
    .user-modal-dialog-custom {
        max-width: 960px;
    }

    .user-modal-header {
        background: #f8fafc;
        border-bottom: 1px solid #eef2f6;
        padding: 1rem 1.25rem;
    }

    .user-modal-header .user-avatar-badge {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        background: rgba(91, 115, 232, 0.12);
        color: #5b73e8;
    }

    .user-modal-body {
        padding: 1.25rem;
        background: #fdfdfe;
        max-height: calc(85vh - 130px);
        overflow-y: auto;
    }

    /* Section Cards */
    .user-section-card {
        background: #ffffff;
        border: 1px solid #e9edf4;
        border-radius: 10px;
        padding: 1.1rem;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: all 0.2s ease;
    }

    .user-section-card:hover {
        border-color: #dbe2ea;
    }

    .user-section-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #2e384d;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.6rem;
        border-bottom: 1px dashed #e9edf4;
    }

    .user-section-title i {
        font-size: 1.15rem;
        color: #5b73e8;
    }

    .user-section-title .section-hint {
        font-size: 0.75rem;
        font-weight: 400;
        color: #74788d;
        margin-left: auto;
    }

    /* Form Floating Tweaks inside modal */
    .user-modal-body .form-floating-custom {
        margin-bottom: 0.75rem !important;
    }

    .user-modal-body .form-floating-custom > .form-control,
    .user-modal-body .form-floating-custom > .form-select {
        border-radius: 8px;
        border-color: #e2e8f0;
        font-size: 0.875rem;
    }

    .user-modal-body .form-floating-custom > .form-control:focus,
    .user-modal-body .form-floating-custom > .form-select:focus {
        border-color: #5b73e8;
        box-shadow: 0 0 0 0.15rem rgba(91, 115, 232, 0.15);
    }

    /* Password visibility toggle button */
    .password-field-wrapper {
        position: relative;
    }

    .btn-toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        color: #74788d;
        padding: 4px;
        cursor: pointer;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
    }

    .btn-toggle-password:hover {
        color: #5b73e8;
    }

    /* Notification Grid & Cards */
    .notification-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.75rem;
    }

    @media (max-width: 768px) {
        .notification-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .notification-tile {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0.85rem;
        background: #f8fafc;
        border: 1.5px solid #e9edf4;
        border-radius: 9px;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        margin-bottom: 0;
    }

    .notification-tile:hover {
        background: #f0f4f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }

    .notification-tile.is-active {
        background: #eff6ff;
        border-color: #5b73e8;
        box-shadow: 0 2px 6px rgba(91, 115, 232, 0.12);
    }

    .notif-tile-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .notif-tile-content {
        flex-grow: 1;
        min-width: 0;
    }

    .notif-tile-title {
        font-size: 0.82rem;
        font-weight: 600;
        color: #334155;
        line-height: 1.2;
    }

    .notif-tile-desc {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notif-tile-check {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 1.5px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: transparent;
        background: #ffffff;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .notification-tile.is-active .notif-tile-check {
        background: #5b73e8;
        border-color: #5b73e8;
        color: #ffffff;
    }

    /* Checklist style for Select2 results - scoped only to dropdown */
    .select2-results__option {
        padding: 8px 12px !important;
        display: flex !important;
        align-items: center !important;
    }

    .select2-results__option::before {
        content: "";
        display: inline-block;
        width: 16px;
        height: 16px;
        margin-right: 10px;
        border: 2px solid #ced4da;
        border-radius: 3px;
        flex-shrink: 0;
        background-color: #fff;
    }

    .select2-results__option[aria-selected=true]::before,
    .select2-results__option.select2-results__option--selected::before {
        content: "✓";
        color: #fff;
        background-color: var(--bs-primary, #5b73e8);
        border-color: var(--bs-primary, #5b73e8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected],
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: var(--bs-primary, #5b73e8) !important;
        color: #ffffff !important;
    }

    /* Selection summary appearance */
    .select2-selection--multiple.has-summary .select2-selection__choice {
        display: none !important;
    }

    .selection-summary-container {
        color: var(--bs-body-color, #495057);
        font-weight: 500;
    }
</style>

<!-- Modal Header -->
<div class="modal-header user-modal-header d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <div class="user-avatar-badge">
            <i class="mdi <?= $isEdit ? 'mdi-account-edit-outline' : 'mdi-account-plus-outline' ?>"></i>
        </div>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="modal-title font-size-16 fw-bold mb-0 text-dark" id="uyeIslemModalLabel">
                    <?= $isEdit ? htmlspecialchars($user->adi_soyadi ?? 'Kullanıcı Düzenle', ENT_QUOTES, 'UTF-8') : 'Yeni Kullanıcı Tanımla' ?>
                </h5>
                <?php if ($isEdit): ?>
                    <?php if (($user->durum ?? 'Aktif') === 'Aktif'): ?>
                        <span class="badge bg-soft-success text-success font-size-11 px-2 py-1"><i class="mdi mdi-circle-medium"></i> Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-soft-danger text-danger font-size-11 px-2 py-1"><i class="mdi mdi-circle-medium"></i> Pasif</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <p class="text-muted font-size-12 mb-0 mt-1">
                <?= $isEdit ? 'Kullanıcı hesap bilgileri, erişim yetkileri ve bildirim tercihlerini güncelleyin.' : 'Sisteme yeni kullanıcı profili, yetki ve erişim sınırları tanımlayın.' ?>
            </p>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<!-- Modal Body -->
<div class="modal-body user-modal-body">
    <form id="userForm">
        <input type="hidden" name="user_id" id="user_id" value="<?php echo $_GET["id"] ?? 0; ?>">

        <!-- 1. BÖLÜM: TEMEL & HESAP BİLGİLERİ -->
        <div class="user-section-card">
            <div class="user-section-title">
                <i class="mdi mdi-account-circle-outline"></i>
                <span>Temel & Hesap Bilgileri</span>
                <span class="section-hint"><span class="text-danger">*</span> Zorunlu alanlar</span>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <?php echo Form::FormFloatInput("text", "adi_soyadi", $user->adi_soyadi ?? '', "", "Adı Soyadı *", "user"); ?>
                </div>
                <div class="col-md-6">
                    <?php echo Form::FormFloatInput("text", "user_name", $user->user_name ?? '', "", "Kullanıcı Adı *", "shield"); ?>
                </div>

                <div class="col-md-5">
                    <div class="password-field-wrapper">
                        <?php echo Form::FormFloatInput("password", "password", '', "", $isEdit ? "Yeni Şifre (İsteğe bağlı)" : "Şifre (Min. 8 karakter) *", "lock", "form-control pe-5", false, null, "new-password"); ?>
                        <button type="button" class="btn-toggle-password" tabindex="-1" title="Şifreyi Göster/Gizle">
                            <i class="mdi mdi-eye-outline font-size-18"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <?php echo Form::FormFloatInput("text", "gorevi", $user->gorevi ?? '', "", "Görevi / Pozisyonu *", "briefcase"); ?>
                </div>

                <div class="col-md-3">
                    <?php echo Form::FormSelect2(
                        name: "durum",
                        label: "Hesap Durumu",
                        options: ['Aktif' => 'Aktif', 'Pasif' => 'Pasif'],
                        selectedValue: $user->durum ?? 'Aktif',
                        icon: "activity"
                    ); ?>
                </div>

                <div class="col-md-6">
                    <?php echo Form::FormFloatInput("email", "email_adresi", $user->email_adresi ?? '', "", "E-Posta Adresi *", "mail"); ?>
                </div>

                <div class="col-md-6">
                    <?php echo Form::FormFloatInput("text", "telefon", $user->telefon ?? '', "", "Telefon Numarası *", "phone"); ?>
                </div>
            </div>
        </div>

        <!-- 2. BÖLÜM: YETKİ VE ORGANİZASYON KAPSAMI -->
        <div class="user-section-card">
            <div class="user-section-title">
                <i class="mdi mdi-shield-account-outline"></i>
                <span>Yetkilendirme & Organizasyon Kapsamı</span>
            </div>

            <div class="row g-2">
                <div class="col-md-6">
                    <?php echo Form::FormMultipleSelect2(
                        name: "roles",
                        options: $usergroups,
                        selectedValues: explode(',', $user->roles ?? ''),
                        label: "Yetki Grubu (Roller) *",
                        icon: "shield",
                        valueField: "id",
                        textField: "role_name",
                        attributes: 'data-selection-label="yetki"'
                    ); ?>
                </div>

                <div class="col-md-6">
                    <?php echo Form::FormMultipleSelect2(
                        name: "user_firms",
                        options: $firmalar,
                        selectedValues: $user_firmler,
                        label: "Yetkili Olduğu Şubeler / Firmalar *",
                        icon: "home",
                        valueField: "id",
                        textField: "firma_adi",
                        attributes: 'data-selection-label="şube"'
                    ); ?>
                </div>

                <div class="col-md-12">
                    <?php 
                    $db = (new \App\Core\Db())->db;
                    $deptsQuery = $db->query("SELECT DISTINCT departman FROM personel WHERE departman IS NOT NULL AND departman != '' ORDER BY departman");
                    $depts = [];
                    while($d = $deptsQuery->fetch(PDO::FETCH_ASSOC)) {
                        $depts[$d['departman']] = $d['departman'];
                    }
                    $rawDept = $user->yonetilen_departman ?? '';
                    $selectedDepts = [];
                    if ($rawDept !== '') {
                        if (strpos($rawDept, '|') !== false) {
                            $selectedDepts = explode('|', $rawDept);
                        } elseif (strpos($rawDept, ';') !== false) {
                            $selectedDepts = explode(';', $rawDept);
                        } else {
                            $selectedDepts = explode(',', $rawDept);
                        }
                    }
                    $selectedDepts = array_map('trim', $selectedDepts);

                    echo Form::FormMultipleSelect2(
                        name: "yonetilen_departman",
                        label: "Yönettiği Departmanlar (Opsiyonel)",
                        options: $depts,
                        selectedValues: $selectedDepts,
                        icon: "users",
                        attributes: 'data-selection-label="departman"'
                    ); ?>
                </div>
            </div>
        </div>

        <!-- 3. BÖLÜM: İZİN ONAY SÜRECİ -->
        <div class="user-section-card">
            <div class="user-section-title">
                <i class="mdi mdi-checkbox-marked-circle-outline"></i>
                <span>İzin Onay Süreci</span>
                <span class="section-hint">Personel izin akışı yapılandırması</span>
            </div>

            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <?php echo Form::FormSelect2(
                        name: "izin_onayi_yapacakmi",
                        label: "İzin Onay Yetkisi",
                        options: ['Hayır' => 'Hayır (Onay Yetkisi Yok)', 'Evet' => 'Evet (İzin Onaylayabilir)'],
                        selectedValue: $user->izin_onayi_yapacakmi ?? 'Hayır',
                        icon: "check-circle"
                    ); ?>
                </div>

                <div class="col-md-6" id="izinOnaySirasiWrapper">
                    <?php echo Form::FormFloatInput(
                        "number",
                        "izin_onay_sirasi",
                        $user->izin_onay_sirasi ?? '',
                        "",
                        "Onay Sırası / Kademesi (Örn: 1, 2)",
                        "hash",
                        "form-control",
                        false,
                        null,
                        "off",
                        false,
                        'min="1" max="10"'
                    ); ?>
                </div>
            </div>
        </div>

        <!-- 4. BÖLÜM: E-POSTA BİLDİRİM TERCİHLERİ -->
        <div class="user-section-card">
            <div class="user-section-title">
                <i class="mdi mdi-email-fast-outline"></i>
                <span>E-Posta Bildirim Tercihleri</span>
                <span class="section-hint">İlgili süreçlerde e-posta bilgilendirmesi</span>
            </div>

            <div class="notification-grid">
                <!-- Avans -->
                <label class="notification-tile <?= ($user->mail_avans_talep ?? 'Hayır') == 'Evet' ? 'is-active' : '' ?>" for="mail_avans_talep">
                    <input class="d-none notif-checkbox" type="checkbox" id="mail_avans_talep" name="mail_avans_talep"
                        value="Evet" <?= ($user->mail_avans_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                    <div class="notif-tile-icon bg-soft-success text-success">
                        <i class="mdi mdi-cash-multiple"></i>
                    </div>
                    <div class="notif-tile-content">
                        <div class="notif-tile-title">Avans Talebi</div>
                        <div class="notif-tile-desc">Avans bildirimleri</div>
                    </div>
                    <div class="notif-tile-check">✓</div>
                </label>

                <!-- İzin -->
                <label class="notification-tile <?= ($user->mail_izin_talep ?? 'Hayır') == 'Evet' ? 'is-active' : '' ?>" for="mail_izin_talep">
                    <input class="d-none notif-checkbox" type="checkbox" id="mail_izin_talep" name="mail_izin_talep"
                        value="Evet" <?= ($user->mail_izin_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                    <div class="notif-tile-icon bg-soft-primary text-primary">
                        <i class="mdi mdi-calendar-clock"></i>
                    </div>
                    <div class="notif-tile-content">
                        <div class="notif-tile-title">İzin Talebi</div>
                        <div class="notif-tile-desc">İzin süreçleri</div>
                    </div>
                    <div class="notif-tile-check">✓</div>
                </label>

                <!-- Genel -->
                <label class="notification-tile <?= ($user->mail_genel_talep ?? 'Hayır') == 'Evet' ? 'is-active' : '' ?>" for="mail_genel_talep">
                    <input class="d-none notif-checkbox" type="checkbox" id="mail_genel_talep" name="mail_genel_talep"
                        value="Evet" <?= ($user->mail_genel_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                    <div class="notif-tile-icon bg-soft-warning text-warning">
                        <i class="mdi mdi-message-text-outline"></i>
                    </div>
                    <div class="notif-tile-content">
                        <div class="notif-tile-title">Genel Talep</div>
                        <div class="notif-tile-desc">Şirket içi talepler</div>
                    </div>
                    <div class="notif-tile-check">✓</div>
                </label>

                <!-- Arıza -->
                <label class="notification-tile <?= ($user->mail_ariza_talep ?? 'Hayır') == 'Evet' ? 'is-active' : '' ?>" for="mail_ariza_talep">
                    <input class="d-none notif-checkbox" type="checkbox" id="mail_ariza_talep" name="mail_ariza_talep"
                        value="Evet" <?= ($user->mail_ariza_talep ?? 'Hayır') == 'Evet' ? 'checked' : '' ?>>
                    <div class="notif-tile-icon bg-soft-danger text-danger">
                        <i class="mdi mdi-alert-circle-outline"></i>
                    </div>
                    <div class="notif-tile-content">
                        <div class="notif-tile-title">Arıza Bildirimi</div>
                        <div class="notif-tile-desc">Arıza kayıtları</div>
                    </div>
                    <div class="notif-tile-check">✓</div>
                </label>
            </div>
        </div>

        <!-- 5. BÖLÜM: EK AÇIKLAMA -->
        <div class="user-section-card mb-0">
            <div class="user-section-title">
                <i class="mdi mdi-notebook-edit-outline"></i>
                <span>Açıklama & Ek Notlar</span>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php echo Form::FormFloatTextarea(
                        name: "aciklama",
                        value: $user->aciklama ?? '',
                        placeholder: "Kullanıcı hakkında ek notlar ve açıklamalar...",
                        label: "Açıklama",
                        icon: "file-text",
                        minHeight: "65px",
                        rows: 2
                    ); ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Footer -->
<div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between">
    <button type="button" class="btn btn-outline-secondary waves-effect" data-bs-dismiss="modal">
        <i class="mdi mdi-close me-1"></i>Vazgeç
    </button>
    <button type="button" id="userSaveBtn" class="btn btn-primary waves-effect waves-light px-4">
        <i class="mdi mdi-content-save-check-outline me-1"></i>Kaydet
    </button>
</div>