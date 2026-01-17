<?php
use App\Helper\Form;
?>

<div class="row">
    <!-- Sol Kolon: Pozisyon ve Durum -->
    <div class="col-md-6">
        <div class="card border h-100">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-briefcase me-2"></i>Pozisyon & Durum</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <?php echo Form::FormFloatInput("text", "ise_giris_tarihi", $personel->ise_giris_tarihi ?? "", "İşe Giriş", "İşe Giriş Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo Form::FormFloatInput("text", "isten_cikis_tarihi", $personel->isten_cikis_tarihi ?? "", "İşten Çıkış", "İşten Çıkış Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>
                    <div class="col-md-4">
                         <?php echo Form::FormSelect2("aktif_mi", ['1' => 'Aktif', '0' => 'Pasif'], $personel->aktif_mi ?? '1', "Durum", "toggle-right"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <?php echo Form::FormSelect2("personel_sinifi", ['Beyaz Yaka' => 'Beyaz Yaka', 'Mavi Yaka' => 'Mavi Yaka'], $personel->personel_sinifi ?? '', "Sınıf", "users"); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo Form::FormFloatInput("text", "departman", $personel->departman ?? "", "Departman", "Departman", "grid"); ?>
                    </div>
                    <div class="col-md-4">
                        <?php echo Form::FormFloatInput("text", "gorev", $personel->gorev ?? "", "Görev", "Görev", "award"); ?>
                    </div>
                </div>

    
            </div>
        </div>
    </div>
    
    <!-- Sağ Kolon: Ekip Bilgileri -->
    <div class="col-md-6">
        <div class="card border h-100">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-group me-2"></i>Ekip Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo Form::FormSelect2("ekip_no", $ekip_kodlari_options, $personel->ekip_no ?? "", "Ekip Numarası", "hash"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>