<?php

use App\Helper\Form;
use App\Model\SettingsModel;

$Settings = new SettingsModel();

 // Tüm ayarları al
 $allSettings = $Settings->getAllSettingsAsKeyValue();
// Bu değişkenlerin veritabanından veya güvenli bir yapılandırma dosyasından geldiğini varsayalım
// Örnek olması için boş veya varsayılan değerlerle başlatıyoruz
$config_id = $iletisim_servis_ayarlari->id ?? 1; // Genellikle tek bir kayıt olur (örn: ID=1)

// E-posta Ayarları
$email_gonderim_aktif = $iletisim_servis_ayarlari->email_gonderim_aktif ?? true;
$smtp_host = $iletisim_servis_ayarlari->smtp_host ?? 'smtp.example.com';
$smtp_port = $iletisim_servis_ayarlari->smtp_port ?? 587;
$smtp_kullanici = $iletisim_servis_ayarlari->smtp_kullanici ?? 'user@example.com';
$smtp_sifre = $iletisim_servis_ayarlari->smtp_sifre ?? ''; // Şifreler genellikle gösterilmez, sadece yeni girilirse güncellenir
$smtp_guvenlik = $iletisim_servis_ayarlari->smtp_guvenlik ?? 'tls'; // 'ssl', 'tls', 'none'
$gonderen_eposta = $iletisim_servis_ayarlari->gonderen_eposta ?? 'noreply@example.com';
$gonderen_adi = $iletisim_servis_ayarlari->gonderen_adi ?? 'Sistem Bildirimleri';

// SMS Ayarları
$sms_gonderim_aktif = $iletisim_servis_ayarlari->sms_gonderim_aktif ?? true;
$sms_servis_saglayici = $allSettings['sms_servis_saglayici'] ?? ''; // Örnek: 'netgsm', 'mutlucell', 'iletimerkezi', 'twilio', 'verimor', 'custom_api'
$sms_api_kullanici = $allSettings['sms_api_kullanici'] ?? ''; // API kullanıcı adı veya numarası
$sms_api_sifre = $allSettings['sms_api_sifre'] ?? ''; // API şifresi veya token
$sms_baslik = $allSettings['sms_baslik']; // SMS başlığı (originator)




?>

<form action="" id="iletisimServisAyarlariForm">
    <input type="hidden" name="config_id" value="<?php echo htmlspecialchars($config_id, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- E-POSTA AYARLARI BÖLÜMÜ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="ti ti-mail-cog me-2"></i>E-posta Gönderim Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch form-switch-lg">
                        <input class="form-check-input" type="checkbox" id="email_gonderim_aktif" name="email_gonderim_aktif" value="1" <?php echo $email_gonderim_aktif ? 'checked' : ''; ?>>
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
                    <div class="form-text">Mevcut şifre güvenlik nedeniyle gösterilmemektedir. Değiştirmek istemiyorsanız bu alanı boş bırakın.</div>
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
                    <button type="button" id="testEmailButton" class="btn btn-outline-info btn-sm waves-effect waves-light">
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
                        <input class="form-check-input" type="checkbox" id="sms_gonderim_aktif" name="sms_gonderim_aktif" value="1" <?php echo $sms_gonderim_aktif ? 'checked' : ''; ?>>
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
                        'netgsm' => 'NetGSM',
                        'mutlucell' => 'Mutlucell',
                        'iletimerkezi' => 'İleti Merkezi',
                        'twilio' => 'Twilio',
                        'verimor' => 'Verimor',
                        'custom_api' => 'Özel API Entegrasyonu'
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
                            $sms_api_kullanici ,
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
                        "5079432723",
                        "",
                        "Test SMS Numarası",
                        "smartphone",
                        "form-control mb-2"
                    ); ?>

                    <button type="button" id="testSmsButton" class="btn btn-sm btn-outline-secondary waves-effect waves-light">
                        <i class="ti ti-message-2-send me-1"></i> SMS Ayarlarını Test Et
                    </button>


                </div>
            </div>
        </div>
    </div>


    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveIletisimServisAyarlariButton" class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Tüm Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('iletisimServisAyarlariForm');
        const saveButton = document.getElementById('saveIletisimServisAyarlariButton');
        const testEmailButton = document.getElementById('testEmailButton');
        const testSmsButton = document.getElementById('testSmsButton');
        const smsServisSaglayiciSelect = document.getElementById('sms_servis_saglayici'); // Select elementinin ID'si Form helper'ınız tarafından oluşturulur, kontrol edin
        const customApiUrlRow = document.getElementById('customApiUrlRow');
        const smsApiCredentialsSection = document.getElementById('smsApiCredentialsSection');

        // SMS Servis Sağlayıcı seçimine göre ek alanları göster/gizle
        function toggleSmsProviderFields() {
            if (!smsServisSaglayiciSelect || !customApiUrlRow || !smsApiCredentialsSection) return;

            const selectedProvider = smsServisSaglayiciSelect.value;
            if (selectedProvider && selectedProvider !== '') {
                smsApiCredentialsSection.classList.remove('d-none');
                if (selectedProvider === 'custom_api') {
                    customApiUrlRow.classList.remove('d-none');
                } else {
                    customApiUrlRow.classList.add('d-none');
                }
            } else {
                smsApiCredentialsSection.classList.add('d-none');
                customApiUrlRow.classList.add('d-none');
            }
        }

        if (smsServisSaglayiciSelect) {
            smsServisSaglayiciSelect.addEventListener('change', toggleSmsProviderFields);
            toggleSmsProviderFields(); // Sayfa yüklendiğinde de çalıştır
        }


        // Kaydetme
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
                const formData = new FormData(form);
                formData.append('action', 'iletisim_servis_ayarlarini_kaydet');

                fetch('/api/settings/communication', { // API endpoint'inizi buraya girin
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Başarılı!', 'İletişim servis ayarları güncellendi.', 'success');
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

        // E-posta Test
        if (testEmailButton) {
            testEmailButton.addEventListener('click', function() {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Test ediliyor...';

                const testEmail = prompt("Test e-postasının gönderileceği adresi girin:", "<?php echo htmlspecialchars($gonderen_eposta, ENT_QUOTES, 'UTF-8'); ?>");
                if (!testEmail) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    return;
                }

                const formData = new FormData(form); // Mevcut form ayarlarını al
                formData.append('action', 'test_email_ayarlari');
                formData.append('test_email_adresi', testEmail);

                fetch('/api/test/email', { // Test API endpoint'i
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.fire(data.success ? 'Test Başarılı' : 'Test Başarısız', data.message, data.success ? 'success' : 'error');
                    })
                    .catch(error => Swal.fire('Hata!', 'E-posta testi sırasında bir sorun oluştu.', error.message))
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }

        // SMS Test
        if (testSmsButton) {
            testSmsButton.addEventListener('click', function() {
                const btn = this;
                const originalText = btn.innerHTML;
                const testNumarasiInput = form.querySelector('input[name="sms_test_numarasi"]');
                const smsApiKullaniciInput = form.querySelector('input[name="sms_api_kullanici"]'); // API kullanıcı adı
                const smsApiSifreInput = form.querySelector('input[name="sms_api_sifre_yeni"]'); // API şifresi
                const smsBaslikInput = form.querySelector('input[name="sms_baslik"]'); // SMS Başlığı

                if (!testNumarasiInput || !testNumarasiInput.value.trim()) {
                    Swal.fire('Eksik Bilgi', 'Lütfen test SMS\'i göndermek için bir telefon numarası girin.', 'warning');
                    if (testNumarasiInput) testNumarasiInput.focus();
                    return;
                }
                if (!/^5[0-9]{9}$/.test(testNumarasiInput.value.trim())) {
                    Swal.fire('Hatalı Numara', 'Lütfen geçerli bir telefon numarası girin (5xxxxxxxxx).', 'warning');
                    if (testNumarasiInput) testNumarasiInput.focus();
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Test ediliyor...';

                // Gönderilecek JSON payload'ını oluştur
                const payload = {
                    action: 'test_sms_ayarlari', // PHP tarafında bu action'a göre işlem yapabilirsiniz
                    message: "Bu bir test mesajıdır. Netgsm API ayarlarınız kontrol ediliyor.", // Test mesajı
                    recipients: [testNumarasiInput.value.trim()], // Alıcı numara (dizi içinde)
                    senderID: smsBaslikInput ? smsBaslikInput.value.trim() : 'CANSAGLKSEN', // Gönderen başlığı
                    username: smsApiKullaniciInput ? smsApiKullaniciInput.value.trim() : "",
                    password: smsApiSifreInput ? smsApiSifreInput.value.trim() : "" // API şifresi/token'ı, boşsa mevcut şifre kullanılacak gibi varsayıyoruz
                };

                fetch('views/mail-sms/api/sms.php', { // API endpoint'iniz
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json' // JSON gönderiyoruz
                        },
                        body: JSON.stringify(payload) // JavaScript objesini JSON string'ine çevir
                    })
                    .then(response => {
                        // Yanıt JSON değilse veya HTTP hatası varsa burada yakala
                        if (!response.ok) {
                            return response.text().then(text => {
                                throw new Error(`Sunucu Hatası: ${response.status} - ${text || response.statusText}`)
                            });
                        }
                        
                        const contentType = response.headers.get("content-type");
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            return response.text().then(text => {
                                throw new Error("Yanıt JSON formatında değil: " + text)
                            });
                        }
                    })
                    .then(data => {
                        // PHP API'nizin 'status' ve 'message' döndürdüğünü varsayıyorum,
                        // bir önceki API'nizdeki gibi. Eğer 'success' alanı varsa ona göre ayarlayın.
                        const isSuccess = data.status === 'success' || data.success === true;
                        Swal.fire(isSuccess ? 'Test Başarılı' : 'Test Başarısız', data.message, isSuccess ? 'success' : 'error');
                    })
                    .catch(error => {
                        console.error('SMS Test Fetch Hatası:', error);
                        Swal.fire('Hata!', 'SMS testi sırasında bir sorun oluştu: ' + error.message, 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }




        // if (testSmsButton) {
        //     testSmsButton.addEventListener('click', function() {
        //         const btn = this;
        //         const originalText = btn.innerHTML;
        //         const testNumarasiInput = form.querySelector('input[name="sms_test_numarasi"]');

        //         if (!testNumarasiInput || !testNumarasiInput.value.trim()) {
        //             Swal.fire('Eksik Bilgi', 'Lütfen test SMS\'i göndermek için bir telefon numarası girin.', 'warning');
        //             testNumarasiInput.focus();
        //             return;
        //         }
        //         // Basit bir numara format kontrolü
        //         if (!/^5[0-9]{9}$/.test(testNumarasiInput.value.trim())) {
        //             Swal.fire('Hatalı Numara', 'Lütfen geçerli bir telefon numarası girin (5xxxxxxxxx).', 'warning');
        //             testNumarasiInput.focus();
        //             return;
        //         }


        //         btn.disabled = true;
        //         btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Test ediliyor...';

        //         const formData = new FormData(form); // Mevcut form ayarlarını al
        //         formData.append('action', 'test_sms_ayarlari');
        //         formData.append('test_telefon_numarasi', testNumarasiInput.value.trim()); // Telefon numarasını ekle
        //         // test_numarasi zaten formda var: formData.append('test_telefon_numarasi', testNumarasiInput.value);
        //         const messagesPayload = [{
        //             "msgheader": "CANSAGLKSEN",
        //             "messages": [{
        //                 "gsm": testNumarasiInput.value.trim(),
        //                 "message": "Bu bir test mesajıdır."
        //             }],
        //             "encoding": "TR",
        //             "iysfilter": "",
        //             "partnercode": ""
        //         }];

        //         formData.append('messagesPayload', JSON.stringify(messagesPayload));

        //         fetch('views/mail-sms/api/sms.php', { // Test API endpoint'i
        //                 method: 'POST',
        //                 body: formData
        //             })
        //             .then(response => response.json())
        //             .then(data => {
        //                 Swal.fire(data.success ? 'Test Başarılı' : 'Test Başarısız', data.message, data.success ? 'success' : 'error');
        //             })
        //             .catch(error => Swal.fire('Hata!', 'SMS testi sırasında bir sorun oluştu.' + error.message, 'error'))
        //             .finally(() => {
        //                 btn.disabled = false;
        //                 btn.innerHTML = originalText;
        //             });
        //         });
        // }

    });
</script>