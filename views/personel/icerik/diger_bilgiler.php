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
                        <?php echo Form::FormFloatInput("password", "sifre", "", "Şifre (Değiştirmek için doldurun)", "Şifre", "lock"); ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Sağ Kolon: Acil Durum -->
    <div class="col-md-6">
        <div class="card border">



             <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-group me-2"></i>Referans Bilgileri kontrol</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <?php echo Form::FormFloatInput("text", "referans_adi_soyadi", $personel->referans_adi_soyadi ?? "", "Referans Adı Soyadı", "Referans Adı Soyadı", "user"); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo Form::FormFloatInput("text", "referans_telefonu", $personel->referans_telefonu ?? "", "Referans Telefonu", "Referans Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
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
                    <div class="col-md-6">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_adi_soyadi", $personel->acil_kisi_adi_soyadi ?? "", "Adı Soyadı", "Adı Soyadı", "user"); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_telefonu", $personel->acil_kisi_telefonu ?? "", "Telefonu", "Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row">
                     <div class="col-md-12">
                        <?php echo Form::FormFloatInput("text", "acil_kisi_yakinlik", $personel->acil_kisi_yakinlik ?? "", "Yakınlık Derecesi", "Yakınlık Derecesi", "users"); ?>
                    </div>
                </div>
            </div>
        </div>

        
    </div>
</div>