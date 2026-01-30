<?php
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Date;

/** Ekip Bölge */
$ekip_bolgeleri_raw = $TanimlamalarModel->getEkipBolgeleri();
$ekip_bolge_options = ['' => 'Seçiniz'];
foreach ($ekip_bolgeleri_raw as $bolge) {
    $ekip_bolge_options[$bolge] = $bolge;
}

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
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "ise_giris_tarihi", Date::dmY($personel->ise_giris_tarihi ?? Date::today()), "İşe Giriş", "İşe Giriş Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "isten_cikis_tarihi", Date::dmY($personel->isten_cikis_tarihi ?? null), "İşten Çıkış", "İşten Çıkış Tarihi", "calendar", "form-control flatpickr"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormSelect2("aktif_mi", ['1' => 'Aktif', '0' => 'Pasif'], $personel->aktif_mi ?? '1', "Durum", "toggle-right"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormSelect2("personel_sinifi", ['Beyaz Yaka' => 'Beyaz Yaka', 'Mavi Yaka' => 'Mavi Yaka'], $personel->personel_sinifi ?? '', "Sınıf", "users"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php
                        $departmanlar = [
                            "BÜRO" => "BÜRO",
                            'Kesme Açma' => 'Kesme Açma',
                            'Kaçak Kontrol' => 'Kaçak Kontrol',
                            'Endeks Okuma' => 'Endeks Okuma',
                            'Sayaç Sökme Takma' => 'Sayaç Sökme Takma',
                            'Mühürleme' => 'Mühürleme',
                            'Kaçak Su Tespiti' => 'Kaçak Su Tespiti',
                        ];
                        echo Form::FormSelect2("departman", $departmanlar, $personel->departman ?? '', "Departman", "grid");
                        ?>
                    </div>
                    <div class="col-md-4 mb-2">
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
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormSelect2("ekip_bolge", $ekip_bolge_options, $personel->ekip_bolge ?? "", "Ekip Bölge", "map-pin"); ?>
                    </div>
                    <div class="col-md-8 mb-2">
                        <?php echo Form::FormSelect2("ekip_no", $ekip_kodlari_options, $personel->ekip_no ?? "", "Ekip Numarası", "hash"); ?>
                    </div>
                    <div class="col-md-12 mb-2">
                        <div class="alert alert-info mt-2">
                            <i class="bx bx-info-circle"></i>
                            Ekip kodu boş geliyorsa eklemek için <a href="index?p=tanimlamalar/ekip-kodu"
                                target="_blank"> tıklayınız</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('#ekip_bolge').on('change', function () {
            var bolge = $(this).val();
            var personel_id = $('#personel_id').val();
            var $ekipSelect = $('#ekip_no');

            // Temizle ve yükleniyor göster
            $ekipSelect.empty().append('<option value="">Yükleniyor...</option>').trigger('change');

            if (bolge) {
                $.ajax({
                    url: 'views/personel/api.php',
                    type: 'POST',
                    data: {
                        action: 'get-ekip-kodlari-by-bolge',
                        bolge: bolge,
                        personel_id: personel_id
                    },
                    dataType: 'json',
                    success: function (response) {
                        var currentValue = $ekipSelect.val();
                        $ekipSelect.empty().append('<option value="">Seçiniz</option>');
                        if (response.status === 'success') {
                            $.each(response.data, function (index, item) {
                                var selected = (item.id == currentValue) ? 'selected' : '';
                                $ekipSelect.append('<option value="' + item.id + '" ' + selected + '>' + item.tur_adi + '</option>');
                            });
                        }
                        $ekipSelect.trigger('change');
                    },
                    error: function () {
                        $ekipSelect.empty().append('<option value="">Hata oluştu!</option>').trigger('change');
                    }
                });
            } else {
                $ekipSelect.empty().append('<option value="">Önce Bölge Seçin</option>').trigger('change');
            }
        });
    });
</script>