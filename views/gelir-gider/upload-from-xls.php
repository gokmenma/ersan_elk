<?php

use App\Helper\Helper;
use App\Helper\Route;
use App\Helper\Security;
use App\Helper\Form;
use App\Model\KasaModel;

$Kasa = new KasaModel();

$kasalar = [];
$kasalar = $Kasa->getKasaListByOwner($_SESSION['owner_id']);

//Helper::dd($kasalar);

$kasaOptions = [];
foreach ($kasalar as $kasa) {
    // FormSelect2 expects an associative array: value => label
    $kasaOptions[$kasa->id] = $kasa->kasa_adi;
}


?>
<div class="row mb-3">
    <div class="col-md-12">

        <div id="spinner" class="text-center p-3" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"></span>
            </div>
            <p class="">Veriler yüklenirken lütfen bekleyin</p>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex">
        <div class="col-md-6">

            <h3 class="card-title">Excel'den Veri Yükle</h3>
        </div>
        <div class="col-md-6">

            <div class="card-title">
                <a href="<?php Route::get('gelir-gider/list') ?>" type="button" id="saveButton"
                    class="btn btn-secondary waves-effect btn-label waves-light float-end">
                    <i class="bx bx-list-ul label-icon"></i>
                    Listeye Dön
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form id="uploadForm" method="post" enctype="multipart/form-data">

            <div class="form-group">

                <div class="row d-flex align-items-center">
                    <div class="col-md-7">
                        <label>Excel Dosyası Seçin (.xlsx, .xls)</label>
                        <?php echo Form::FormFloatInput('file', 'excelFile', null, 'Excel Dosyası Seçin', 'Excel Dosyası', 'file')   ?>

                    </div>
                    <div class="col-md-4">
                        <label>Kasa Seçin</label>
                        <?php echo Form::FormSelect2(
                            'kasa_id',
                            $kasaOptions,
                            null,
                            'Kasa Seçin',
                            'briefcase',
                            'key',
                            'Kasa Seçin'
                        ) ?>
                    </div>

                    <div class="col-md-1 ">

                        <button type="button" id="submitForm"
                            class="btn btn-primary waves-effect btn-label waves-light float-end d-block align-self-end mt-4">
                            <i class="bx bx-upload label-icon"></i>
                            Yükle
                        </button>
                    </div>
                    <span class="text-muted mt-1">Örnek Dosya indirmek için <a href="/files/gelir_gider_yukle.xlsx">
                            tıklayınız</a></span>

                </div>

            </div>



        </form>


    </div>
</div>
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <h3 class="card-title">Açıklamalar</h3>
        </div>
    </div>
    <div class="card-body">

        <div class="row">
            <div class="alert alert-secondary" role="alert">
                <h4 class="alert-heading">İşlem Tipi!</h4>
                <p>Kasaya eklenecek tutarlar için -> <strong>GELİR</strong></p>
                <p>Kasadan düşülecek tutarlar için -> <strong>GİDER</strong> yazınız</p>
            </div>
        </div>
    </div>
</div>



<script>
    $(document).ready(function() {
        $('#submitForm').on('click', function() {
            // Formu gönderirken dosya seçilip seçilmediğini kontrol et

            //spinner'i göster   
            event.preventDefault();
            const fileInput = $('input[name="excelFile"]');
            if (!fileInput.val()) {
                swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: 'Lütfen bir Excel dosyası seçin.'
                });
                return false;
            }
            if (!/(\.xlsx|\.xls)$/i.test(fileInput.val())) {
                swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: 'Lütfen geçerli bir Excel dosyası seçin (.xlsx veya .xls)'
                });
                //fileInputu temizle
                fileInput.val('');
                return false;
            }

            var formData = new FormData($('#uploadForm')[0]);

            $(this).prop('disabled', true);

            //Preloader
            $("#spinner").show();

            // Pace.restart();

            //return false; // Formun normal submit işlemini engelle
            let url = "views/gelir-gider/api.php";
            // Formu gönder

            formData.append("action", "gelir-gider-excel-kaydet");

            fetch(url, {
                method: 'POST',
                body: formData,

            }).then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            }).then(data => {
                // Yanıtı işleme
                if (data.status == "success") {
                    // Başarılı yükleme mesajı
                    swal.fire({
                        icon: 'success',
                        title: 'Başarılı',
                        text: data.message
                    });
                    $(this).prop('disabled', false);
                    $("#spinner").hide();

                } else {
                    // Hata mesajı
                    swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: data.message
                    });
                }
            }).catch(error => {
                $(this).prop('disabled', false);
                $("#spinner").hide();

                swal.fire({
                    icon: 'error',
                    title: 'Hata',
                    text: error.message
                });
            });





        });
    });
</script>