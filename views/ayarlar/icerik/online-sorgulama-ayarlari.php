<?php

use App\Helper\Form;
use App\Model\SettingsModel;

$Settings = new SettingsModel();

// Tüm ayarları al
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

// Online Sorgulama Ayarları
$online_sorgulama_aktif = ($allSettings['online_sorgulama_aktif'] ?? '0') === '1';
$online_sorgulama_endeks_saat = $allSettings['online_sorgulama_endeks_saat'] ?? '08:00';
$online_sorgulama_puantaj_saat = $allSettings['online_sorgulama_puantaj_saat'] ?? '08:30';
$online_sorgulama_firma_baslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? ($_SESSION['firma_kodu'] ?? '17');
$online_sorgulama_firma_bitis = $allSettings['online_sorgulama_firma_bitis'] ?? ($_SESSION['firma_kodu'] ?? '17');

// API Ayarları
$online_sorgulama_api_url = $allSettings['online_sorgulama_api_url'] ?? '';
$online_sorgulama_api_kullanici = $allSettings['online_sorgulama_api_kullanici'] ?? '';
$online_sorgulama_api_sifre = $allSettings['online_sorgulama_api_sifre'] ?? '';

// Son çalışma zamanları
$online_sorgulama_endeks_son_calistirma = $allSettings['online_sorgulama_endeks_son_calistirma'] ?? '08:15';
$online_sorgulama_puantaj_son_calistirma = $allSettings['online_sorgulama_puantaj_son_calistirma'] ?? '08:45';

// Saat seçenekleri oluştur (15 dakika aralıklarla)
$saatSecenekleri = [];
for ($saat = 0; $saat < 24; $saat++) {
    for ($dakika = 0; $dakika < 60; $dakika += 15) {
        $saatStr = sprintf('%02d:%02d', $saat, $dakika);
        $saatSecenekleri[$saatStr] = $saatStr;
    }
}

?>

<form action="" id="onlineSorgulamaAyarlariForm">
    <input type="hidden" name="firma_id" value="<?php echo $_SESSION["firma_id"] ?? ''; ?>">
    <input type="hidden" name="user_id" value="<?php echo $_SESSION["user_id"] ?? ''; ?>">

    <!-- GENEL AYARLAR -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="settings" class="me-2"></i>Online Sorgulama Genel Ayarları
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch form-switch-lg">
                        <input class="form-check-input" type="checkbox" id="online_sorgulama_aktif"
                            name="online_sorgulama_aktif" value="1" <?php echo $online_sorgulama_aktif ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="online_sorgulama_aktif">Otomatik Online Sorgulama
                            Aktif</label>
                    </div>
                    <div class="form-text ps-1">Bu ayar aktifken, belirlenen saatlerde otomatik olarak online sorgulama
                        yapılır.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "number",
                        "online_sorgulama_firma_baslangic",
                        $online_sorgulama_firma_baslangic,
                        "",
                        "İlk Firma (Defter) Kodu",
                        "briefcase",
                        "form-control"
                    ); ?>
                    <div class="form-text">Sorgulamaya başlanacak ilk firma kodu</div>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "number",
                        "online_sorgulama_firma_bitis",
                        $online_sorgulama_firma_bitis,
                        "",
                        "Son Firma (Defter) Kodu",
                        "briefcase",
                        "form-control"
                    ); ?>
                    <div class="form-text">Sorgulamanın biteceği son firma kodu</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ENDEKS OKUMA ZAMANLAMA -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="activity" class="me-2"></i>Endeks Okuma Sorgulama Zamanlaması
            </h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormSelect2(
                        "online_sorgulama_endeks_saat",
                        $saatSecenekleri,
                        $online_sorgulama_endeks_saat,
                        "Sorgulama Saati",
                        "clock",
                        '',
                        "",
                        "form-control select2"
                    ); ?>
                    <div class="form-text">Her gün bu saatte Endeks Okuma sorgulanacak</div>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormSelect2(
                        "online_sorgulama_endeks_son_calistirma",
                        $saatSecenekleri,
                        $online_sorgulama_endeks_son_calistirma,
                        "Son Çalıştırma Saati",
                        "clock",
                        '',
                        "",
                        "form-control select2"
                    ); ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <button type="button" id="btnManuelEndeksSorgula" class="btn btn-outline-primary btn-sm">
                        <i data-feather="play" class="me-1"></i> Manuel Sorgula (Test)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KESME/AÇMA İŞLEMLERİ ZAMANLAMA -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="scissors" class="me-2"></i>Kesme/Açma İşlemleri Sorgulama
                Zamanlaması</h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormSelect2(
                        "online_sorgulama_puantaj_saat",
                        $saatSecenekleri,
                        $online_sorgulama_puantaj_saat,
                        "Sorgulama Saati",
                        "clock",
                        '',
                        "",
                        "form-control select2"
                    ); ?>
                    <div class="form-text">Her gün bu saatte Kesme/Açma İşlemleri sorgulanacak</div>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormSelect2(
                        "online_sorgulama_puantaj_son_calistirma",
                        $saatSecenekleri,
                        $online_sorgulama_puantaj_son_calistirma,
                        "Son Çalıştırma Saati",
                        "clock",
                        '',
                        "",
                        "form-control select2"
                    ); ?>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-12">
                    <button type="button" id="btnManuelPuantajSorgula" class="btn btn-outline-primary btn-sm">
                        <i data-feather="play" class="me-1"></i> Manuel Sorgula (Test)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- API AYARLARI -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="link" class="me-2"></i>API Bağlantı Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="alert alert-warning">
                <i data-feather="info" class="me-2"></i>
                API bağlantı bilgileri henüz sağlanmadığı için şu an test verileri kullanılmaktadır.
                API hazır olduğunda bu alanları doldurun.
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php echo Form::FormFloatInput(
                        "url",
                        "online_sorgulama_api_url",
                        $online_sorgulama_api_url,
                        "",
                        "API URL",
                        "link",
                        "form-control"
                    ); ?>
                    <div class="form-text">Örn:
                        http://10.185.0.52:9090/webBase/faces/jsfPage/report/gelir/su/Isemri...</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "text",
                        "online_sorgulama_api_kullanici",
                        $online_sorgulama_api_kullanici,
                        "",
                        "API Kullanıcı Adı",
                        "user",
                        "form-control"
                    ); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <?php echo Form::FormFloatInput(
                        "password",
                        "online_sorgulama_api_sifre_yeni",
                        "",
                        "",
                        "API Şifresi (Değiştirmek için doldurun)",
                        "key",
                        "form-control"
                    ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CRON BİLGİSİ -->
    <div class="card shadow-sm mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10">
            <h5 class="mb-0 text-info"><i data-feather="terminal" class="me-2"></i>Cron Job Kurulumu</h5>
        </div>
        <div class="card-body p-4">
            <p>Otomatik sorgulama için sunucunuzda aşağıdaki cron job'u eklemeniz gerekmektedir:</p>
            <div class="bg-dark text-light p-3 rounded mb-3">
                <code class="text-warning">
                # Her 15 dakikada bir cron kontrolü yapar<br>
                */15 * * * * php <?php echo dirname(__DIR__, 2); ?>/cron/online_sorgulama_cron.php >> <?php echo dirname(__DIR__, 2); ?>/cron/logs/cron.log 2>&1
            </code>
            </div>
            <p class="mb-0 text-muted">
                <i data-feather="info" class="me-1"></i>
                Cron her 15 dakikada bir çalışır ve ayarlanan saatlere denk geldiğinde sorgulama yapar.
            </p>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveOnlineSorgulamaAyarlariButton"
                class="btn btn-success waves-effect btn-label waves-light">
                <i data-feather="save" class="label-icon me-1"></i> Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Feather icons'ları yeniden render et
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        const form = document.getElementById('onlineSorgulamaAyarlariForm');
        const saveButton = document.getElementById('saveOnlineSorgulamaAyarlariButton');
        const btnManuelPuantajSorgula = document.getElementById('btnManuelPuantajSorgula');
        const btnManuelEndeksSorgula = document.getElementById('btnManuelEndeksSorgula');

        // Kaydetme
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
                const formData = new FormData(form);
                formData.append('action', 'save');

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
                        btn.innerHTML = '<i class="bx bx-save label-icon me-1"></i> Ayarları Kaydet';
                    });
            });
        }

        // Manuel Puantaj Sorgulama
        if (btnManuelPuantajSorgula) {
            btnManuelPuantajSorgula.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sorgulanıyor...';

                const firmaBaslangic = form.querySelector('input[name="online_sorgulama_firma_baslangic"]').value || 17;
                const firmaBitis = form.querySelector('input[name="online_sorgulama_firma_bitis"]').value || 17;

                const formData = new FormData();
                formData.append('action', 'online-puantaj-sorgula');
                formData.append('ilk_firma', firmaBaslangic);
                formData.append('son_firma', firmaBitis);
                formData.append('baslangic_tarihi', '<?php echo date('d.m.Y'); ?>');
                formData.append('bitis_tarihi', '<?php echo date('d.m.Y'); ?>');

                fetch('/views/puantaj/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            let message = data.yeni_kayit + ' adet yeni kayıt eklendi.';
                            if (data.guncellenen_kayit > 0) {
                                message += '\n' + data.guncellenen_kayit + ' adet kayıt güncellendi.';
                            }
                            if (data.mevcut_kayitlar && data.mevcut_kayitlar.length > 0) {
                                message += '\n\nDaha önce çekilmiş ' + data.mevcut_kayitlar.length + ' kayıt var.';
                            }
                            Swal.fire('Sorgulama Tamamlandı', message, 'success');
                        } else {
                            Swal.fire('Hata', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Hata', 'Sorgulama sırasında bir hata oluştu.', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }

        // Manuel Endeks Sorgulama
        if (btnManuelEndeksSorgula) {
            btnManuelEndeksSorgula.addEventListener('click', function () {
                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sorgulanıyor...';

                const firmaBaslangic = form.querySelector('input[name="online_sorgulama_firma_baslangic"]').value || 17;
                const firmaBitis = form.querySelector('input[name="online_sorgulama_firma_bitis"]').value || 17;

                const formData = new FormData();
                formData.append('action', 'online-icmal-sorgula');
                formData.append('ilk_firma', firmaBaslangic);
                formData.append('son_firma', firmaBitis);
                formData.append('baslangic_tarihi', '<?php echo date('d.m.Y'); ?>');
                formData.append('bitis_tarihi', '<?php echo date('d.m.Y'); ?>');

                fetch('/views/puantaj/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            let message = data.yeni_kayit + ' adet yeni kayıt eklendi.';
                            if (data.guncellenen_kayit > 0) {
                                message += '\n' + data.guncellenen_kayit + ' adet kayıt güncellendi.';
                            }
                            if (data.mevcut_kayitlar && data.mevcut_kayitlar.length > 0) {
                                message += '\n\nDaha önce çekilmiş ' + data.mevcut_kayitlar.length + ' kayıt var.';
                            }
                            Swal.fire('Sorgulama Tamamlandı', message, 'success');
                        } else {
                            Swal.fire('Hata', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Hata', 'Sorgulama sırasında bir hata oluştu.', 'error');
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
            });
        }
    });
</script>