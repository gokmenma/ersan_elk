<?php
use App\Helper\Form; // Form helper'ınızın olduğunu varsayıyorum

// Bu değişkenlerin veritabanından veya bir yapılandırma dosyasından geldiğini varsayalım
// Örnek olması için boş veya örnek değerlerle başlatıyoruz
$antet_id = $antet_ayarlari->id ?? null; // Düzenleme modu için


$sol_logo_mevcut = $settings->sol_logo_yolu ?? null;
$sag_logo_mevcut = $settings->sag_logo_yolu ?? null;
$baslik_satir_1 = $settings->baslik_satir_1 ?? "T.C.";
$baslik_satir_2 = $settings->baslik_satir_2 ?? "ÖRNEK KURUM ADI";
$baslik_satir_3 = $settings->baslik_satir_3 ?? "Genel Müdürlüğü";
$baslik_satir_4 = $settings->baslik_satir_4 ?? "Evrak Yönetim Birimi";

$alt_bilgi_satir_1 = $settings->alt_bilgi_satir_1 ?? "Adres: Örnek Caddesi No:123, Başkent / TÜRKİYE";
$alt_bilgi_satir_2 = $settings->alt_bilgi_satir_2 ?? "Tel: (0312) 123 45 67 - Faks: (0312) 123 45 68";
$alt_bilgi_satir_3 = $settings->alt_bilgi_satir_3 ?? "E-posta: bilgi@ornekkurum.gov.tr - Web: www.ornekkurum.gov.tr";
$alt_bilgi_satir_4 = $settings->alt_bilgi_satir_4 ?? "Kep Adresi : cansaglik@hs03.kep.tr";

?>

<!-- Eğer bu alanlar başka bir formun parçasıysa, o formun içine yerleştirin. -->
<!-- Ayrı bir form olarak yönetilecekse: -->
<form action="" id="antetAyarlariForm" enctype="multipart/form-data">
    <input type="text" name="antet_id" id="antet_id_form" class="form-control d-none" value="<?php echo htmlspecialchars($antet_id, ENT_QUOTES, 'UTF-8'); ?>">

    <h5 class="mb-3 text-primary">Evrak Logoları</h5>

    <div class="row mb-5">
        <div class="col-md-6">
            <label for="sol_logo_input" class="form-label">Sol Logo Yükle</label>
            <?php echo Form::FormFileInput(
                "sol_logo_yeni", // Input name
                "", 
                icon: "upload", // İkon
                
            ) ?>
            <div class="form-text">Evrakın sol üst köşesi için logo. Max: 100x100px.</div>
            <?php if (!empty($sol_logo_mevcut)): ?>
                <div class="mt-2">
                    <label class="form-label small">Mevcut Sol Logo:</label><br>
                    <img src="<?php echo htmlspecialchars($sol_logo_mevcut, ENT_QUOTES, 'UTF-8'); ?>" alt="Mevcut Sol Logo" style="max-height: 60px; border: 1px solid #ddd; padding: 3px;">
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeSolLogoBtn">Kaldır</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <label for="sag_logo_input" class="form-label">Sağ Logo Yükle (Opsiyonel)</label>
            <?php echo Form::FormFileInput(
                "sag_logo_input", // Input name
                "", // Input ID
                icon: "upload", // İkon
                
            ) ?>
            <div class="form-text">Evrakın sağ üst köşesi için logo. Max: 100x100px.</div>
             <?php if (!empty($sag_logo_mevcut)): ?>
                <div class="mt-2">
                    <label class="form-label small">Mevcut Sağ Logo:</label><br>
                    <img src="<?php echo htmlspecialchars($sag_logo_mevcut, ENT_QUOTES, 'UTF-8'); ?>" alt="Mevcut Sağ Logo" style="max-height: 60px; border: 1px solid #ddd; padding: 3px;">
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeSagLogoBtn">Kaldır</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <h5 class="mb-3 text-primary">Evrak Antet (Üst Bilgi) Ayarları</h5>

    <div class="row mb-2">
        <div class="col-md-3">
            <?php
            echo Form::FormFloatTextarea(
                 "baslik_satir_1",
                $baslik_satir_1,
                "", // Hata mesajı
                "Başlık Satır 1",
                "file-text", // İkon
                "form-control" // Daha küçük input için sm class
            ); ?>
        </div>
        <div class="col-md-3">
            <?php
            echo Form::FormFloatTextarea(
                "baslik_satir_2",
                $baslik_satir_2,
                "",
                "Başlık Satır 2",
                "file-text",
                 "form-control"
            ); ?>
        </div>

        <div class="col-md-3">
            <?php
            echo Form::FormFloatTextarea(
                "baslik_satir_3",
                $baslik_satir_3,
                "",
                "Başlık Satır 3",
                "file-text",
                 "form-control"
            ); ?>
        </div>
        <div class="col-md-3">
            <?php
            echo Form::FormFloatTextarea(
                "baslik_satir_4",
                $baslik_satir_4,
                "",
                "Başlık Satır 4",
                'file-text',
                 "form-control"
            ); ?>
        </div>
    </div>

    <hr class="my-4">
    <h5 class="mb-3 text-primary">Evrak Alt Bilgi Ayarları</h5>

    <div class="row mb-2">
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "alt_bilgi_satir_1",
                $alt_bilgi_satir_1,
                "",
                "Alt Bilgi Satır 1 (Adres)",
                "map-pin",
                 "form-control"
            ); ?>
        </div>
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "alt_bilgi_satir_2",
                $alt_bilgi_satir_2,
                "",
                "Alt Bilgi Satır 2 (Tel/Faks)",
                "phone",
                 "form-control"
            ); ?>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "alt_bilgi_satir_3",
                $alt_bilgi_satir_3,
                "",
                "Alt Bilgi Satır 3 (E-posta/Web)",
                "globe",
                 "form-control"
            ); ?>
        </div>
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "alt_bilgi_satir_4",
                $alt_bilgi_satir_4,
                "",
                "Alt Bilgi Satır 4 (Dipnot)",
                "mail",
                 "form-control"
            ); ?>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12">
            <button type="button" id="saveAntetButton" class="btn btn-success waves-effect btn-label waves-light float-end">
                <i class="bx bx-check-double label-icon"></i> Antet Ayarlarını Kaydet
            </button>
        </div>
    </div>
</form>

<script>




  $(document).on('click', '#saveAntetButton', function(e) {

    e.preventDefault();

    // Dosya upload için FormData şart (aksi halde $_FILES boş gelir)
    const formEl = document.getElementById('antetAyarlariForm');
    const fd = new FormData(formEl);

    // action bilgisini ekle
    fd.append('action', 'save');

    // Text/checkbox alanlarını settings[...] formatında ekle.
    // (File inputlar otomatik olarak FormData içinde kalır)
    let settings = {};
    $('#antetAyarlariForm').find('input, textarea, select').each(function() {
        const name = $(this).attr('name');
        if (!name) return;

        if ($(this).attr('type') === 'file') {
            return;
        }

        if ($(this).attr('type') === 'checkbox') {
            settings[name] = $(this).is(':checked') ? 1 : 0;
        } else {
            settings[name] = $(this).val();
        }
    });

    Object.keys(settings).forEach(function(k) {
        fd.append('settings[' + k + ']', settings[k]);
    });

    $.ajax({
        url: 'views/ayarlar/api.php',
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                var data = response.data;
                swal.fire(
                    'Başarılı!',
                    'Antet ayarları başarıyla kaydedildi.',
                    'success'
                )

            } else {
                alert(response.message);
            }
        },
        error: function(xhr) {
            console.log('Upload/Save error', xhr);
            alert('İşlem sırasında bir hata oluştu. (Network/Server)');
        }
    });
});

// Logo kaldırma butonları (yeni settings alanı eklemez)
$(document).on('click', '#removeSolLogoBtn', function(e){
    e.preventDefault();
    $.post('views/ayarlar/api.php', { action: 'remove_logo', side: 'sol' }, function(resp){
        if (resp.status === 'success') {
            $('#removeSolLogoBtn').closest('.mt-2').find('img').attr('src','').hide();
            $('#removeSolLogoBtn').remove();
            swal.fire('Başarılı!', 'Sol logo kaldırıldı.', 'success');
        } else {
            alert(resp.message);
        }
    }, 'json');
});

$(document).on('click', '#removeSagLogoBtn', function(e){
    e.preventDefault();
    $.post('views/ayarlar/api.php', { action: 'remove_logo', side: 'sag' }, function(resp){
        if (resp.status === 'success') {
            $('#removeSagLogoBtn').closest('.mt-2').find('img').attr('src','').hide();
            $('#removeSagLogoBtn').remove();
            swal.fire('Başarılı!', 'Sağ logo kaldırıldı.', 'success');
        } else {
            alert(resp.message);
        }
    }, 'json');
});

// Seçilen logo dosyasını anında önizle
function previewSelectedLogo(inputEl, previewId) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) return;

    if (!file.type || !file.type.startsWith('image/')) {
        alert('Lütfen bir resim dosyası seçin.');
        inputEl.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        let img = document.getElementById(previewId);
        if (!img) {
            img = document.createElement('img');
            img.id = previewId;
            img.style.maxHeight = '60px';
            img.style.border = '1px solid #ddd';
            img.style.padding = '3px';
            img.className = 'mt-2';
            inputEl.parentElement.appendChild(img);
        }
        img.src = e.target.result;
        img.style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
}

$(document).on('change', 'input[name="sol_logo_yeni"]', function(){
    previewSelectedLogo(this, 'sol_logo_preview_img');
});

$(document).on('change', 'input[name="sag_logo_input"]', function(){
    previewSelectedLogo(this, 'sag_logo_preview_img');
});
















// Bu script bloğu, ana sayfanızdaki veya bu bölümü içeren modal/tab içindeki
// genel script alanına eklenebilir.
// document.addEventListener('DOMContentLoaded', function () {
//     // Logo önizleme ve dosya adı gösterme (opsiyonel, geliştirilebilir)
//     function handleLogoInputChange(inputId, previewImgIdPrefix) {
//         const logoInput = document.getElementById(inputId);
//         if (logoInput) {
//             logoInput.addEventListener('change', function(event) {
//                 const file = event.target.files[0];
//                 if (file) {
//                     console.log(inputId + " için yeni logo seçildi:", file.name);
//                     // İsterseniz burada küçük bir önizleme de gösterebilirsiniz.
//                     // Örneğin:
//                     const reader = new FileReader();
//                     reader.onload = function(e) {
//                         let previewImg = document.getElementById(previewImgIdPrefix + '_preview_img');
//                         if (!previewImg) {
//                             // Mevcut logo yoksa veya kaldırıldıysa yeni img oluştur
//                             // Bu kısım biraz daha detaylı implementasyon gerektirir.
//                         previewImg = document.createElement('img');
//                         previewImg.id = previewImgIdPrefix + '_preview_img';
//                         previewImg.style.maxHeight = '60px';
//                         previewImg.style.border = '1px solid #ddd';
//                         previewImg.style.padding = '3px';
//                         const parentElement = logoInput.parentElement;
//                         if (parentElement) {
//                             parentElement.appendChild(previewImg);
//                         }
//                         previewImg.src = e.target.result;
//                         } else {
//                            previewImg.src = e.target.result;
//                         }
//                     }
//                     reader.readAsDataURL(file);
//                 }
//             });
//         }
//     }

//     handleLogoInputChange('sol_logo_input', 'sol_logo');
//     handleLogoInputChange('sag_logo_input', 'sag_logo');

//     // Kaydetme butonu için örnek bir handler
//     const saveAntetButton = document.getElementById('saveAntetButton');
//     if (saveAntetButton) {
//         saveAntetButton.addEventListener('click', function() {
//             const formData = new FormData(document.getElementById('antetAyarlariForm'));

//             // FormData'yı AJAX ile göndermek için:
//             // fetch('/path/to/save/antet_settings', {
//             //     method: 'POST',
//             //     body: formData
//             // })
//             // .then(response => response.json())
//             // .then(data => {
//             //     if (data.success) {
//             //         alert('Antet ayarları başarıyla kaydedildi!');
//             //         // Gerekirse sayfayı yenile veya mevcut logoları güncelle
//             //     } else {
//             //         alert('Hata: ' + (data.message || 'Ayarlar kaydedilemedi.'));
//             //     }
//             // })
//             // .catch(error => {
//             //     console.error('Kaydetme hatası:', error);
//             //     alert('Bir ağ hatası oluştu.');
//             // });

//             console.log("Antet Ayarları Kaydet butonuna basıldı. FormData içeriği:");
//             for (let [key, value] of formData.entries()) {
//                 if (value instanceof File) {
//                     console.log(key + ':', value.name, '(' + value.size + ' bytes)');
//                 } else {
//                     console.log(key + ':', value);
//                 }
//             }
//             alert('Antet ayarları konsola yazdırıldı (AJAX gönderme işlemi entegre edilmeli).');
//         });
//     }
// });
</script>