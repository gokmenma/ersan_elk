<?php

use App\Helper\Form;
use App\Model\SettingsModel;

$Settings = new SettingsModel();

// Tüm ayarları al
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

// SGK Vizite Ayarları
$sgk_kullanici_adi = $allSettings['sgk_kullanici_adi'] ?? '';
$sgk_isyeri_kodu = $allSettings['sgk_isyeri_kodu'] ?? '';
$sgk_isyeri_sifresi = $allSettings['sgk_isyeri_sifresi'] ?? '';
$sgk_otomatik_rapor_onaylama = ($allSettings['sgk_otomatik_rapor_onaylama'] ?? '0') === '1';

?>

<form action="" id="sgkViziteAyarlariForm">
    <input type="hidden" name="firma_id" value="<?php echo $_SESSION["firma_id"] ?? ''; ?>">
    <input type="hidden" name="user_id" value="<?php echo $_SESSION["user_id"] ?? ''; ?>">

    <!-- SGK VİZİTE AYARLARI BÖLÜMÜ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="ti ti-building-hospital me-2"></i>SGK Vizite Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <!-- Bilgi Mesajı -->
            <div class="alert alert-warning alert-border-left mb-4" role="alert">
                <i class="ti ti-lock me-2"></i>
                <strong>Bilgi!</strong> Şifreleriniz uçtan uca şifrelenmiş olup sizden başka kimse erişememektedir!
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "sgk_kullanici_adi",
                        $sgk_kullanici_adi,
                        "Tc Kimlik numarası",
                        "SGK Kullanıcı Adı",
                        "users",
                        "form-control"
                    ); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "sgk_isyeri_kodu",
                        $sgk_isyeri_kodu,
                        "-'den sonraki kod Örn: 2",
                        "SGK İşyeri Kodu",
                        "key",
                        "form-control"
                    ); ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "password",
                        "sgk_isyeri_sifresi_yeni",
                        "",
                        "İşyeri şifresi",
                        "SGK İşyeri Şifresi (Değiştirmek için doldurun)",
                        "key",
                        "form-control"
                    ); ?>
                    <div class="form-text">Mevcut şifre güvenlik nedeniyle gösterilmemektedir. Değiştirmek
                        istemiyorsanız bu alanı boş bırakın.</div>
                </div>
              
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="button" id="testSgkBaglantisiButton"
                        class="btn btn-outline-info btn-sm waves-effect waves-light">
                        <i class="ti ti-plug-connected me-1"></i> SGK Bağlantısını Test Et
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveSgkViziteAyarlariButton"
                class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('sgkViziteAyarlariForm');
        const saveButton = document.getElementById('saveSgkViziteAyarlariButton');
        const testButton = document.getElementById('testSgkBaglantisiButton');

        // Kaydetme
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
                const formData = new FormData(form);
                formData.append('action', 'save_sgk_ayarlari');

                fetch('views/ayarlar/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Başarılı!', data.message, 'success');
                        } else {
                            Swal.fire('Hata!', data.message || 'Ayarlar kaydedilemedi.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Kaydetme hatası:', error);
                        Swal.fire('Sunucu Hatası!', 'Ayarlar kaydedilemedi.', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ti ti-device-floppy label-icon me-1"></i> Ayarları Kaydet';
                    });
            });
        }

        // SGK Bağlantı Testi
        if (testButton) {
            testButton.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Test ediliyor...';

                const formData = new FormData(form);
                formData.append('action', 'test_sgk_baglantisi');

                fetch('views/ayarlar/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Bağlantı Başarılı!', data.message, 'success');
                        } else {
                            Swal.fire('Bağlantı Başarısız!', data.message || 'SGK bağlantısı kurulamadı.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Test hatası:', error);
                        Swal.fire('Sunucu Hatası!', 'Test sırasında bir hata oluştu.', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }
    });
</script>