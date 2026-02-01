<?php

use App\Helper\Form;
use App\Model\SettingsModel;

$Settings = new SettingsModel();

// Tüm ayarları al
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

// E-posta Ayarları
$email_gonderim_aktif = ($allSettings['email_gonderim_aktif'] ?? '0') === '1';
$smtp_host = $allSettings['smtp_host'] ?? 'smtp.example.com';
$smtp_port = $allSettings['smtp_port'] ?? 587;
$smtp_kullanici = $allSettings['smtp_kullanici'] ?? 'user@example.com';
$smtp_sifre = $allSettings['smtp_sifre'] ?? '';
$smtp_guvenlik = $allSettings['smtp_guvenlik'] ?? 'tls'; // 'ssl', 'tls', 'none'
$gonderen_eposta = $allSettings['gonderen_eposta'] ?? 'noreply@example.com';
$gonderen_adi = $allSettings['gonderen_adi'] ?? 'Sistem Bildirimleri';

// SMS Ayarları
$sms_gonderim_aktif = ($allSettings['sms_gonderim_aktif'] ?? '0') === '1';
$sms_servis_saglayici = $allSettings['sms_servis_saglayici'] ?? '';
$sms_api_kullanici = $allSettings['sms_api_kullanici'] ?? '';
$sms_api_sifre = $allSettings['sms_api_sifre'] ?? '';
$sms_baslik = $allSettings['sms_baslik'] ?? '';

?>

<form action="" id="iletisimServisAyarlariForm">
    <input type="hidden" name="firma_id" value="<?php echo $_SESSION["firma_id"] ?? ''; ?>">
    <input type="hidden" name="user_id" value="<?php echo $_SESSION["user_id"] ?? ''; ?>">

    <!-- E-POSTA AYARLARI BÖLÜMÜ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="ti ti-mail-cog me-2"></i>E-posta Gönderim Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch form-switch-lg">
                        <input class="form-check-input" type="checkbox" id="email_gonderim_aktif"
                            name="email_gonderim_aktif" value="1" <?php echo $email_gonderim_aktif ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="email_gonderim_aktif">E-posta Gönderimi Aktif</label>
                    </div>
                    <div class="form-text ps-1">Bu ayar kapalıysa sistem üzerinden e-posta gönderimi yapılmaz.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "smtp_host",
                        $smtp_host,
                        "",
                        "SMTP Sunucusu (Host)",
                        "server",
                        "form-control"
                    ); ?>
                </div>
                <div class="col-md-3 mb-3">
                    <?php echo Form::FormFloatInput(
                        "number",
                        "smtp_port",
                        $smtp_port,
                        "",
                        "SMTP Port",
                        "hash",
                        "form-control",
                    ); ?>
                </div>
                <div class="col-md-3 mb-3">
                    <?php echo Form::FormSelect2(
                        "smtp_guvenlik",
                        [
                            'none' => 'Yok (Önerilmez)',
                            'ssl' => 'SSL',
                            'tls' => 'TLS'
                        ],
                        $smtp_guvenlik,
                        "Güvenlik Türü",
                        "shield",
                        '',
                        ''
                    ); ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "smtp_kullanici",
                        $smtp_kullanici,
                        "",
                        "SMTP Kullanıcı Adı",
                        "user",
                        "form-control"
                    ); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "password",
                        "smtp_sifre_yeni",
                        "",
                        "",
                        "SMTP Şifresi (Değiştirmek için doldurun)",
                        "key",
                        "form-control"
                    ); ?>
                    <div class="form-text">Mevcut şifre güvenlik nedeniyle gösterilmemektedir. Değiştirmek
                        istemiyorsanız bu alanı boş bırakın.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "email",
                        "gonderen_eposta",
                        $gonderen_eposta,
                        "",
                        "Gönderen E-posta Adresi",
                        "at-sign",
                        "form-control"
                    ); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "gonderen_adi",
                        $gonderen_adi,
                        "",
                        "Gönderen Adı",
                        "user",
                        "form-control"
                    ); ?>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-12">
                    <button type="button" id="testEmailButton"
                        class="btn btn-outline-info btn-sm waves-effect waves-light">
                        <i class="ti ti-send me-1"></i> E-posta Ayarlarını Test Et
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SMS AYARLARI BÖLÜMÜ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="ti ti-message-cog me-2"></i>SMS Gönderim Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch form-switch-lg">
                        <input class="form-check-input" type="checkbox" id="sms_gonderim_aktif"
                            name="sms_gonderim_aktif" value="1" <?php echo $sms_gonderim_aktif ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sms_gonderim_aktif">SMS Gönderimi Aktif</label>
                    </div>
                    <div class="form-text ps-1">Bu ayar kapalıysa sistem üzerinden SMS gönderimi yapılmaz.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php
                    // SMS Servis Sağlayıcıları - Bu listeyi kendi kullandıklarınızla güncelleyin
                    $sms_saglayicilar = [
                        '' => 'Seçiniz...',
                        'netgsm' => 'NetGSM'
                    ];
                    echo Form::FormSelect2(
                        "sms_servis_saglayici",
                        $sms_saglayicilar,
                        $sms_servis_saglayici,
                        "SMS Servis Sağlayıcı",
                        "wifi",
                        '',
                        "",
                        "form-control select2",
                    );
                    ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "sms_baslik",
                        $sms_baslik,
                        "",
                        "SMS Başlığı (Originator)",
                        "award",
                        "form-control"
                    ); ?>
                    <div class="form-text">Operatör tarafından onaylanmış SMS başlığınız. Genellikle 11 karakter.</div>
                </div>
            </div>

            <div id="smsApiCredentialsSection">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <?php echo Form::FormFloatInput(
                            "text",
                            "sms_api_kullanici",
                            $sms_api_kullanici,
                            "",
                            "API Kullanıcı Adı / Numara / SID",
                            "hash",
                            "form-control"
                        ); ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <?php echo Form::FormFloatInput(
                            "password",
                            "sms_api_sifre_yeni",
                            "2386@41",
                            "",
                            "API Şifresi / Token (Değiştirmek için doldurun)",
                            "key",
                            "form-control"
                        ); ?>
                        <div class="form-text">Mevcut API şifresi/token'ı güvenlik nedeniyle gösterilmemektedir.</div>
                    </div>
                </div>
                <!-- Bazı API'ler için ek alanlar gerekebilir (örn: API Endpoint URL) -->
                <div class="row d-none" id="customApiUrlRow"> <!-- Başlangıçta gizli -->
                    <div class="col-md-12 mb-3">
                        <?php echo Form::FormFloatInput(
                            "url",
                            "sms_custom_api_url",
                            $iletisim_servis_ayarlari->sms_custom_api_url ?? '',
                            "",
                            "Özel API Endpoint URL",
                            "link"
                        ); ?>
                    </div>
                </div>
            </div>
            <div class="row mt-3">

                <div class="col-md-6">
                    <?php echo Form::FormFloatInput(
                        "tel",
                        "sms_test_numarasi",
                        $_SESSION["user"]->telefon,
                        "",
                        "Test SMS Numarası",
                        "smartphone",
                        "form-control mb-2"
                    ); ?>

                    <button type="button" id="testSmsButton"
                        class="btn btn-sm btn-outline-secondary waves-effect waves-light">
                        <i class="ti ti-message-2-send me-1"></i> SMS Ayarlarını Test Et
                    </button>


                </div>
            </div>
        </div>
    </div>


    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveIletisimServisAyarlariButton"
                class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Tüm Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('iletisimServisAyarlariForm');
        const saveButton = document.getElementById('saveIletisimServisAyarlariButton');
        const testEmailButton = document.getElementById('testEmailButton');
        const testSmsButton = document.getElementById('testSmsButton');
        const smsServisSaglayiciSelect = document.getElementById('sms_servis_saglayici'); // Select elementinin ID'si Form helper'ınız tarafından oluşturulur, kontrol edin
        const customApiUrlRow = document.getElementById('customApiUrlRow');
        const smsApiCredentialsSection = document.getElementById('smsApiCredentialsSection');

        // // SMS Servis Sağlayıcı seçimine göre ek alanları göster/gizle
        // function toggleSmsProviderFields() {
        //     if (!smsServisSaglayiciSelect || !customApiUrlRow || !smsApiCredentialsSection) return;

        //     const selectedProvider = smsServisSaglayiciSelect.value;
        //     if (selectedProvider && selectedProvider !== '') {
        //         smsApiCredentialsSection.classList.remove('d-none');
        //         if (selectedProvider === 'custom_api') {
        //             customApiUrlRow.classList.remove('d-none');
        //         } else {
        //             customApiUrlRow.classList.add('d-none');
        //         }
        //     } else {
        //         smsApiCredentialsSection.classList.add('d-none');
        //         customApiUrlRow.classList.add('d-none');
        //     }
        // }

        // if (smsServisSaglayiciSelect) {
        //     smsServisSaglayiciSelect.addEventListener('change', toggleSmsProviderFields);
        //     toggleSmsProviderFields(); // Sayfa yüklendiğinde de çalıştır
        // }


        // Kaydetme
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
                const formData = new FormData(form);
                formData.append('action', 'save');

                // for(let pair of formData.entries()) {
                //     console.log(pair[0] + ': ' + pair[1]);
                // }

                fetch('views/ayarlar/api.php', { // API endpoint'inizi buraya girin
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
                        btn.innerHTML = '<i class="ti ti-device-floppy label-icon me-1"></i> Tüm Ayarları Kaydet';
                    });
            });
        }

        if (testEmailButton) {
            testEmailButton.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;

                Swal.fire({
                    title: 'E-posta Testi',
                    text: 'Test mailinin gönderileceği adresi girin:',
                    input: 'email',
                    inputPlaceholder: 'Örn: adiniz@example.com',
                    inputValue: '<?php echo htmlspecialchars($gonderen_eposta, ENT_QUOTES, 'UTF-8'); ?>',
                    showCancelButton: true,
                    confirmButtonText: 'Test Maili Gönder',
                    cancelButtonText: 'İptal',
                    showLoaderOnConfirm: true,
                    preConfirm: (email) => {
                        if (!email) {
                            Swal.showValidationMessage('Lütfen geçerli bir e-posta adresi girin.');
                            return false;
                        }

                        const formData = new FormData(form);
                        formData.append('action', 'test_email_ayarlari');
                        formData.append('test_email_adresi', email);
                        formData.append('firma_id', '<?php echo $firma_id; ?>');

                        return fetch('views/ayarlar/api.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => {
                                if (!response.ok) throw new Error(response.statusText);
                                return response.json();
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`İstek hatası: ${error}`);
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value.status === 'success') {
                            Swal.fire('Başarılı!', result.value.message, 'success');
                        } else {
                            Swal.fire('Hata!', result.value.message || 'Test maili gönderilemedi.', 'error');
                        }
                    }
                });
            });
        }

        if (testSmsButton) {
            testSmsButton.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;

                Swal.fire({
                    title: 'SMS Testi',
                    text: 'Test mesajının gönderileceği numarayı girin (5xxxxxxxxx):',
                    input: 'tel',
                    inputPlaceholder: 'Örn: 5051234567',
                    inputValue: form.querySelector('input[name="sms_test_numarasi"]')?.value || '',
                    showCancelButton: true,
                    confirmButtonText: 'Test SMS Gönder',
                    cancelButtonText: 'İptal',
                    showLoaderOnConfirm: true,
                    preConfirm: (numara) => {
                        if (!numara || !/^5[0-9]{9}$/.test(numara.trim())) {
                            Swal.showValidationMessage('Lütfen geçerli bir telefon numarası girin (5xxxxxxxxx).');
                            return false;
                        }

                        const formData = new FormData(form);
                        formData.append('action', 'test_sms_ayarlari');
                        formData.append('sms_test_numarasi', numara.trim());
                        formData.append('firma_id', '<?php echo $firma_id; ?>');

                        return fetch('views/ayarlar/api.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => {
                                if (!response.ok) throw new Error(response.statusText);
                                return response.json();
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`İstek hatası: ${error}`);
                            });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (result.value.status === 'success') {
                            Swal.fire('Başarılı!', result.value.message, 'success');
                        } else {
                            Swal.fire('Hata!', result.value.message || 'SMS gönderilemedi.', 'error');
                        }
                    }
                });
            });
        }

    });
</script>