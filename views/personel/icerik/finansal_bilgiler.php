<?php
use App\Helper\Form;
?>

<div class="row">
    <div class="col-md-12">
        <div class="card border">
             <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-money me-2"></i>Maaş & Banka Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <?php echo Form::FormFloatInput("text", "iban_numarasi", $personel->iban_numarasi ?? "", "IBAN Numarası", "IBAN Numarası", "credit-card"); ?>
                    </div>
                   <div class="col-md-2">
                        <?php echo Form::FormSelect2("bes_kesintisi_varmi", ['Evet' => 'Evet', 'Hayır' => 'Hayır'], $personel->bes_kesintisi_varmi ?? '', "Bes Kesintisi Var mı?", "dollar-sign"); ?>
                    </div>
                    <div class="col-md-2">
                        <?php echo Form::FormSelect2("maas_durumu", ['Brüt' => 'Brüt', 'Net' => 'Net'], $personel->maas_durumu ?? '', "Maaş Tipi", "dollar-sign"); ?>
                    </div>
                    <div class="col-md-2">
                        <?php echo Form::FormFloatInput("text", "maas_tutari", $personel->maas_tutari ?? "", "Maaş Tutarı", "Maaş Tutarı", "dollar-sign"); ?>
                    </div>
                    <div class="col-md-3">
                        <?php echo Form::FormFloatInput("text", "maas_birim_saat", $personel->maas_birim_saat ?? "", "Birim Saat Ücreti", "Birim Saat Ücreti", "clock"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>