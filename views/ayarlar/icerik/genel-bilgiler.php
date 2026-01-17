<?php

use App\Helper\Form; // Form helper'ınızın olduğunu varsayıyorum

// Bu değişkenlerin veritabanından veya bir yapılandırma dosyasından geldiğini varsayalım
// Örnek olması için boş veya örnek değerlerle başlatıyoruz
$sendika_id = $sendika->id ?? null; // Düzenleme modu için
$sendika_adi = $sendika->adi ?? "";
$sendika_kisa_adi = $sendika->kisa_adi ?? "";
$sendika_adresi = $sendika->adresi ?? "";
$sendika_telefon = $sendika->telefon ?? "";
$sendika_faks = $sendika->faks ?? "";
$sendika_email = $sendika->email ?? "";
$sendika_web_sitesi = $sendika->web_sitesi ?? "";
$mevcut_logo_yolu = $sendika->logo_yolu ?? null; // Örneğin: "uploads/logos/sendika_logo.png"

// Temsilcilik formu için değişkenler (kodunuzdan alındı)
$id = $id ?? null; // Temsilcilik ID'si
$temsilci_no = $temsilci_no ?? "";
// $City, $uye, $temsilcilik nesnelerinin tanımlı olduğunu varsayıyoruz.
// Örnek için bazılarını oluşturalım:
class Dummy
{
    public function __get($name)
    {
        return null;
    }
}
$uye = $uye ?? new Dummy();
$temsilcilik = $temsilcilik ?? new Dummy();
class CityHelper
{
    public function getCityList()
    {
        return [81 => 'Düzce', 34 => 'İstanbul'];
    }
} // Basit bir örnek
$City = $City ?? new CityHelper();
?>

<div class="row mb-3">
    
</div>


<!-- Sendika Bilgileri Formu -->
<form action="" id="sendikaBilgiForm" enctype="multipart/form-data"> <!-- Logo yükleme için enctype eklendi -->
    <input type="text" name="sendika_id" id="sendika_id_form" class="form-control d-none" value="<?php echo htmlspecialchars($sendika_id, ENT_QUOTES, 'UTF-8'); ?>">

    <h5 class="mb-3 text-primary">Sendika Bilgileri ve Logo</h5>
    <div class="row mb-3">
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "sendika_adi",
                $sendika_adi,
                "Sendika adını giriniz!",
                "Sendika Adı",
                "award", // İkon
                required: true
            ); ?>
        </div>
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "text",
                "sendika_kisa_adi",
                $sendika_kisa_adi,
                "Sendika kısa adını giriniz (örn: SENDİKA).",
                "Sendika Kısa Adı",
                "award" // İkon
            ); ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <?php
            echo Form::FormFloatTextarea( // Adres için textarea daha uygun olabilir
                "sendika_adresi",
                $sendika_adresi,
                "Sendika adresini giriniz.",
                "Sendika Adresi",
                "map", // İkon
                "form-control",

            ); ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <?php
            echo Form::FormFloatInput(
                "text",
                "sendika_telefon",
                $sendika_telefon,
                "Sendika telefon numarasını giriniz.",
                "Sendika Telefon",
                "phone-call" // İkon
            ); ?>
        </div>
        <div class="col-md-4">
            <?php
            echo Form::FormFloatInput(
                "text",
                "sendika_faks",
                $sendika_faks,
                "Sendika faks numarasını giriniz (opsiyonel).",
                "Sendika Faks",
                "printer" // İkon
            ); ?>
        </div>
        <div class="col-md-4">
            <?php
            echo Form::FormFloatInput(
                "email", // Tip email olarak değiştirildi
                "sendika_email",
                $sendika_email,
                "Sendika e-posta adresini giriniz.",
                "Sendika E-posta",
                "at-sign" // İkon
            ); ?>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <?php
            echo Form::FormFloatInput(
                "url", // Tip url olarak değiştirildi
                "sendika_web_sitesi",
                $sendika_web_sitesi,
                "Sendika web sitesi adresini giriniz (örn: https://www.sendika.org.tr).",
                "Sendika Web Sitesi",
                "globe" // İkon
            ); ?>
        </div>
    </div>

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <label for="sendika_logo_input" class="form-label">Sendika Logosu Yükle</label>
            <input class="form-control" type="file" id="sendika_logo_input" name="sendika_logo_yeni" accept="image/png, image/jpeg, image/svg+xml">
            <div class="form-text">Önerilen boyut: 200x100 piksel. Desteklenen formatlar: PNG, JPG, SVG.</div>
        </div>
        <div class="col-md-6">
            <?php if (!empty($mevcut_logo_yolu)): ?>
                <label class="form-label">Mevcut Logo:</label><br>
                <img src="<?php echo htmlspecialchars($mevcut_logo_yolu, ENT_QUOTES, 'UTF-8'); ?>" alt="Mevcut Sendika Logosu" style="max-height: 80px; border: 1px solid #ddd; padding: 5px;">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" value="1" id="logo_kaldir_checkbox" name="logo_kaldir">
                    <label class="form-check-label" for="logo_kaldir_checkbox">
                        Mevcut Logoyu Kaldır
                    </label>
                </div>
            <?php else: ?>
                <label class="form-label">Mevcut Logo:</label><br>
                <small class="text-muted">Henüz bir logo yüklenmemiş.</small>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-12">
        <button type="button" id="saveButton" class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                class="bx bx-save label-icon"></i> Kaydet</button>
    </div>
</form>

<script>
    // Flatpickr'ı başlatmak için (eğer kullanıyorsanız ve Form helper'ınız bunu otomatik yapmıyorsa)
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof flatpickr !== "undefined") {
            flatpickr(".flatpickr", {
                dateFormat: "d.m.Y", // Türkiye formatı
                // Diğer flatpickr ayarları...
            });
        }

        // İl seçildiğinde ilçeleri getirme (örnek fonksiyon)
        const ilSelect = document.getElementById('temsilci_il');
        const ilceSelect = document.getElementById('temsilci_ilce');

        if (ilSelect && ilceSelect) {
            ilSelect.addEventListener('change', function() {
                const ilId = this.value;
                ilceSelect.innerHTML = '<option value="">Yükleniyor...</option>'; // Yükleniyor mesajı

                if (!ilId) {
                    ilceSelect.innerHTML = '<option value="">Önce İl Seçiniz</option>';
                    return;
                }

                // Gerçek uygulamada burası AJAX isteği olmalı
                // Örnek: fetch(`/api/ilceler?il_id=${ilId}`)
                //         .then(response => response.json())
                //         .then(data => { ... });

                // Simülasyon
                setTimeout(() => {
                    let ilceler = {
                        "81": [{
                            id: "merkez_duzce",
                            ad: "Merkez (Düzce)"
                        }, {
                            id: "akcakoca",
                            ad: "Akçakoca"
                        }],
                        "34": [{
                            id: "kadikoy",
                            ad: "Kadıköy"
                        }, {
                            id: "besiktas",
                            ad: "Beşiktaş"
                        }]
                    };

                    ilceSelect.innerHTML = '<option value="">İlçe Seçiniz</option>';
                    if (ilceler[ilId]) {
                        ilceler[ilId].forEach(ilce => {
                            const option = document.createElement('option');
                            option.value = ilce.id;
                            option.textContent = ilce.ad;
                            ilceSelect.appendChild(option);
                        });
                    } else {
                        ilceSelect.innerHTML = '<option value="">İlçe Bulunamadı</option>';
                    }
                }, 500);
            });
            // Sayfa yüklendiğinde, eğer bir il seçiliyse ilçeleri yükle
            if (ilSelect.value) {
                ilSelect.dispatchEvent(new Event('change'));
                // Daha sonra seçili ilçeyi de set etmeniz gerekebilir (eğer düzenleme modundaysa)
                // ilceSelect.value = "<?php echo $uye->ilce ?? ''; ?>";
            }
        }

        // Logo önizleme (opsiyonel)
        const logoInput = document.getElementById('sendika_logo_input');
        if (logoInput) {
            logoInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Önizleme için bir <img> etiketi oluşturup gösterebilirsiniz
                        // Örneğin: document.getElementById('logoPreview').src = e.target.result;
                        // Şimdilik basit tutuyoruz.
                        console.log("Yeni logo seçildi:", file.name);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
</script>