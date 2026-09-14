<?php
use App\Helper\Form;
?>

<style>
    #diger .other-overview {
        background: linear-gradient(135deg, rgba(85, 110, 230, .12), rgba(52, 195, 143, .05));
        border: 1px solid rgba(85, 110, 230, .18);
        border-radius: 14px;
    }
    #diger .other-overview-icon, #diger .other-section-icon { align-items: center; display: inline-flex; flex: 0 0 auto; justify-content: center; }
    #diger .other-overview-icon { background: rgba(85, 110, 230, .12); border-radius: 12px; color: #556ee6; height: 48px; width: 48px; }
    #diger .other-section-card {
        border: 1px solid rgba(128, 137, 150, .18) !important;
        border-radius: 14px;
        box-shadow: 0 .25rem .75rem rgba(18, 38, 63, .045);
        height: 100%;
        overflow: hidden;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    #diger .other-section-card:hover { border-color: rgba(85, 110, 230, .32) !important; box-shadow: 0 .45rem 1rem rgba(18, 38, 63, .075); }
    #diger .other-section-card .card-header { background: var(--bs-body-bg, #fff); padding: 1rem 1.25rem; }
    #diger .other-section-card .card-body { padding: 1.25rem; }
    #diger .other-section-icon { border-radius: 10px; height: 38px; width: 38px; }
    #diger .other-section-icon.security { background: rgba(85, 110, 230, .12); color: #556ee6; }
    #diger .other-section-icon.mobile { background: rgba(80, 165, 241, .12); color: #50a5f1; }
    #diger .other-section-icon.reference { background: rgba(241, 180, 76, .14); color: #d89d2f; }
    #diger .other-section-icon.emergency { background: rgba(244, 106, 106, .12); color: #f46a6a; }
    #diger .other-help { background: rgba(116, 120, 141, .055); border-radius: 9px; color: #74788d; font-size: .78rem; padding: .65rem .8rem; }
    #diger .password-toggle { height: 38px; margin-right: 8px; padding: .35rem .55rem; width: 38px; }
    #diger .form-floating-custom { margin-bottom: 0 !important; }
    [data-bs-theme="dark"] #diger .other-overview { background: linear-gradient(135deg, rgba(85, 110, 230, .2), rgba(52, 195, 143, .07)); }
    @media (max-width: 767.98px) {
        #diger .other-section-card .card-header, #diger .other-section-card .card-body { padding: 1rem; }
    }
</style>

<div class="other-overview p-3 p-lg-4 mb-3">
    <div class="d-flex align-items-start gap-3">
        <span class="other-overview-icon"><i class="bx bx-id-card fs-3"></i></span>
        <div>
            <h4 class="mb-1 text-dark">Diğer personel bilgileri</h4>
            <p class="text-muted mb-0">Sistem erişimi, saha uygulaması ve iletişim kurulacak kişileri bu alandan yönetin.</p>
        </div>
    </div>
   
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card other-section-card mb-0">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="other-section-icon security"><i class="bx bx-lock-alt fs-5"></i></span>
                    <div><h5 class="card-title mb-1 text-dark">Sistem Giriş Bilgileri</h5><div class="small text-muted">Personelin yönetim sistemi erişimini güncelleyin.</div></div>
                </div>
            </div>
            <div class="card-body">
                <div class="position-relative">
                    <?php echo Form::FormFloatInput("password", "sifre", empty($personel->id) ? "1234" : "", "Yeni Şifre", "Şifre", "lock", autocomplete: "new-password"); ?>
                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted password-toggle" aria-label="Şifreyi göster" aria-pressed="false"><i class="bx bx-show fs-5"></i></button>
                </div>
                <div class="other-help mt-3"><i class="bx bx-info-circle me-1"></i>Kayıtlı personelde yalnızca şifreyi değiştirmek istediğinizde bu alanı doldurun.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card other-section-card mb-0">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="other-section-icon mobile"><i class="bx bx-mobile-alt fs-5"></i></span>
                    <div><h5 class="card-title mb-1 text-dark">Kaski APK Giriş Bilgileri</h5><div class="small text-muted">Saha uygulamasında kullanılacak hesap bilgileri.</div></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12"><?php echo Form::FormFloatInput("text", "kaski_kullanici_adi", $personel->kaski_kullanici_adi ?? "", "Kaski Kullanıcı Adı", "Kaski Kullanıcı Adı", "user"); ?></div>
                    <div class="col-12">
                        <div class="position-relative">
                            <?php echo Form::FormFloatInput("password", "kaski_sifre", $personel->kaski_sifre ?? "", "Kaski Şifre", "Kaski Şifre", "key", autocomplete: "new-password"); ?>
                            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted password-toggle" aria-label="Şifreyi göster" aria-pressed="false"><i class="bx bx-show fs-5"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card other-section-card mb-0">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="other-section-icon reference"><i class="bx bx-user-voice fs-5"></i></span>
                    <div><h5 class="card-title mb-1 text-dark">Referans Bilgileri</h5><div class="small text-muted">İşe alım sürecindeki referans iletişim bilgileri.</div></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><?php echo Form::FormFloatInput("text", "referans_adi_soyadi", $personel->referans_adi_soyadi ?? "", "Referans Adı Soyadı", "Referans Adı Soyadı", "user"); ?></div>
                    <div class="col-md-6"><?php echo Form::FormFloatInput("text", "referans_telefonu", $personel->referans_telefonu ?? "", "Referans Telefonu", "Referans Telefonu", "phone"); ?></div>
                    <div class="col-12"><?php echo Form::FormFloatInput("text", "referans_firma", $personel->referans_firma ?? "", "Referans Firma", "Referans Firma", "briefcase"); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card other-section-card mb-0">
            <div class="card-header border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="other-section-icon emergency"><i class="bx bx-first-aid fs-5"></i></span>
                    <div><h5 class="card-title mb-1 text-dark">Acil Durum Kişisi</h5><div class="small text-muted">Gerektiğinde öncelikli olarak iletişim kurulacak kişi.</div></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><?php echo Form::FormFloatInput("text", "acil_kisi_adi_soyadi", $personel->acil_kisi_adi_soyadi ?? "", "Adı Soyadı", "Adı Soyadı", "user"); ?></div>
                    <div class="col-md-6"><?php echo Form::FormFloatInput("text", "acil_kisi_telefonu", $personel->acil_kisi_telefonu ?? "", "Telefonu", "Telefonu", "phone"); ?></div>
                    <div class="col-12"><?php echo Form::FormFloatInput("text", "acil_kisi_yakinlik", $personel->acil_kisi_yakinlik ?? "", "Yakınlık Derecesi", "Yakınlık Derecesi", "users"); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $(document).off('click.digerBilgiler', '#diger .password-toggle').on('click.digerBilgiler', '#diger .password-toggle', function () {
            const btn = $(this);
            const input = btn.siblings('.form-floating-custom').find('input');
            const icon = btn.find('i');
            const showPassword = input.attr('type') === 'password';

            input.attr('type', showPassword ? 'text' : 'password');
            icon.toggleClass('bx-show', !showPassword).toggleClass('bx-hide', showPassword);
            btn.attr('aria-label', showPassword ? 'Şifreyi gizle' : 'Şifreyi göster');
            btn.attr('aria-pressed', showPassword ? 'true' : 'false');
        });
    });
</script>
