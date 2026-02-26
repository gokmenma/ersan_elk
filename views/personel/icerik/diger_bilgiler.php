<?php
use App\Helper\Form;
?>

<div class="row">
    <!-- Sol Kolon: Referans -->
    <div class="col-md-6">

        <div class="card border mt-3">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-key me-2"></i>Giriş Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="position-relative">
                            <?php echo Form::FormFloatInput("password", "sifre", "", "Şifre (Değiştirmek için doldurun)", "Şifre", "lock", autocomplete: "new-password"); ?>
                            <button type="button"
                                class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted password-toggle"
                                style="z-index: 10; margin-right: 10px;">
                                <i class="bx bx-show fs-5"></i>
                            </button>
                        </div>

                        <span class="text-muted gap-2">Personelin programa giriş için kullandığı şifreyi
                            sıfırlayabilirsiniz</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mt-3">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-mobile-alt me-2"></i>Kaski APK Giriş Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatInput("text", "kaski_kullanici_adi", $personel->kaski_kullanici_adi ?? "", "Kaski Kullanıcı Adı", "Kaski Kullanıcı Adı", "user"); ?>
                    </div>
                    <div class="col-md-12">
                        <div class="position-relative">
                            <?php echo Form::FormFloatInput("password", "kaski_sifre", $personel->kaski_sifre ?? "", "Kaski Şifre", "Kaski Şifre", "key", autocomplete: "new-password"); ?>
                            <button type="button"
                                class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted password-toggle"
                                style="z-index: 10; margin-right: 10px;">
                                <i class="bx bx-show fs-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sağ Kolon: Acil Durum -->
    <div class="col-md-6">
        <div class="card border">



            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-group me-2"></i>Referans Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "referans_adi_soyadi", $personel->referans_adi_soyadi ?? "", "Referans Adı Soyadı", "Referans Adı Soyadı", "user"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "referans_telefonu", $personel->referans_telefonu ?? "", "Referans Telefonu", "Referans Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatInput("text", "referans_firma", $personel->referans_firma ?? "", "Referans Firma", "Referans Firma", "briefcase"); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-first-aid me-2"></i>Acil Durum Kişisi</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_adi_soyadi", $personel->acil_kisi_adi_soyadi ?? "", "Adı Soyadı", "Adı Soyadı", "user"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_telefonu", $personel->acil_kisi_telefonu ?? "", "Telefonu", "Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_yakinlik", $personel->acil_kisi_yakinlik ?? "", "Yakınlık Derecesi", "Yakınlık Derecesi", "users"); ?>
                    </div>
                </div>
            </div>
        </div>


    </div>
</div>

<script>
    $(document).ready(function () {
        $(document).on('click', '.password-toggle', function () {
            const btn = $(this);
            const input = btn.siblings('.form-floating-custom').find('input');
            const icon = btn.find('i');

            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bx-show').addClass('bx-hide');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bx-hide').addClass('bx-show');
            }
        });
    });
</script>