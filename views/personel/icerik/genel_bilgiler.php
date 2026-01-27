<?php
use App\Helper\Form;
use App\Helper\Date;
?>

<div class="row">
    <!-- Sol Kolon: Kimlik ve Kişisel Bilgiler -->
    <div class="col-md-6">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-id-card me-2"></i>Kimlik Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "tc_kimlik_no", $personel->tc_kimlik_no ?? "", "11 Haneli TC", "TC Kimlik No", "user", "form-control", true, 11); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_tarihi", Date::dmy($personel->dogum_tarihi ?? null) ?? "", "Doğum Tarihi", "Doğum Tarihi", "calendar", "form-control flatpickr",'','',"off"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "adi_soyadi", $personel->adi_soyadi ?? "", "Ad Soyad", "Adı Soyadı", "user", "form-control", true); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                         <?php echo Form::FormSelect2("cinsiyet", ['Erkek' => 'Erkek', 'Kadın' => 'Kadın'], $personel->cinsiyet ?? '', "Cinsiyet", "users"); ?>
                    </div>
                </div>
                 <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                         <?php echo Form::FormSelect2("medeni_durum", ['Evli' => 'Evli', 'Bekar' => 'Bekar'], $personel->medeni_durum ?? '', "Medeni Durum", "heart"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "kan_grubu", $personel->kan_grubu ?? "", "Kan Gr.", "Kan Grubu", "activity"); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mt-3">
             <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-user-circle me-2"></i>Kişisel Detaylar</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "anne_adi", $personel->anne_adi ?? "", "Anne Adı", "Anne Adı", "user"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "baba_adi", $personel->baba_adi ?? "", "Baba Adı", "Baba Adı", "user"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_yeri_il", $personel->dogum_yeri_il ?? "", "Doğum Yeri İl", "Doğum Yeri İl", "map-pin"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_yeri_ilce", $personel->dogum_yeri_ilce ?? "", "Doğum Yeri İlçe", "Doğum Yeri İlçe", "map-pin"); ?>
                    </div>
                </div>
                 <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "ehliyet_sinifi", $personel->ehliyet_sinifi ?? "", "Ehliyet Sınıfı", "Ehliyet Sınıfı", "credit-card"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                         <?php echo Form::FormSelect2("seyahat_engeli", ['Var' => 'Var', 'Yok' => 'Yok'], $personel->seyahat_engeli ?? '', "Seyahat Engeli", "truck"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: İletişim ve Diğer -->
    <div class="col-md-6">
        <div class="card border">
             <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-phone me-2"></i>İletişim Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "cep_telefonu", $personel->cep_telefonu ?? "", "Cep Telefonu", "Cep Telefonu", "phone"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "cep_telefonu_2", $personel->cep_telefonu_2 ?? "", "2. Cep Telefonu", "2. Cep Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatInput("email", "email_adresi", $personel->email_adresi ?? "", "Email", "Email", "mail",autocomplete:"off"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatTextarea("adres", $personel->adres ?? "", "Adres", "Adres", "map", "form-control", false, "100px", 3); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mt-3">
             <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-body me-2"></i>Fiziksel & Diğer</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "ayakkabi_numarasi", $personel->ayakkabi_numarasi ?? "", "Ayakkabı", "Ayakkabı No", "target"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "ust_beden_no", $personel->ust_beden_no ?? "", "Üst", "Üst Beden", "target"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "alt_beden_no", $personel->alt_beden_no ?? "", "Alt", "Alt Beden", "target"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                         <?php echo Form::FormSelect2("esi_calisiyor_mu", ['Evet' => 'Evet', 'Hayır' => 'Hayır'], $personel->esi_calisiyor_mu ?? '', "Eşi Çalışıyor Mu?", "briefcase"); ?>
                    </div>
                    <div class="col-md-6 d-none">
                        <?php echo Form::FormFloatInput("text", "resim_yolu", $personel->resim_yolu ?? "", "Resim Yolu / URL", "Resim Yolu", "image"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>