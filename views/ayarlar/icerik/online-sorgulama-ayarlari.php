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

// Çoklu saat değerlerini diziye çevir
$endeks_saatleri = array_filter(array_map('trim', explode(',', $online_sorgulama_endeks_saat)));
$puantaj_saatleri = array_filter(array_map('trim', explode(',', $online_sorgulama_puantaj_saat)));
$online_sorgulama_firma_baslangic = $allSettings['online_sorgulama_firma_baslangic'] ?? ($_SESSION['firma_kodu'] ?? '17');
$online_sorgulama_firma_bitis = $allSettings['online_sorgulama_firma_bitis'] ?? ($_SESSION['firma_kodu'] ?? '17');

// Endeks Okuma API Ayarları
$api_endeks_url = $allSettings['api_endeks_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_okuma_secure.php?action=getData';
$api_endeks_kullanici = $allSettings['api_endeks_kullanici'] ?? '';
$api_endeks_sifre = $allSettings['api_endeks_sifre'] ?? 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';

// Kesme/Açma API Ayarları
$api_puantaj_url = $allSettings['api_puantaj_url'] ?? 'https://yonetim.maraskaski.gov.tr/api/api_isemri_secure.php?action=getIsEmri';
$api_puantaj_kullanici = $allSettings['api_puantaj_kullanici'] ?? '';
$api_puantaj_sifre = $allSettings['api_puantaj_sifre'] ?? 'sk_live_DSOSTjHN195B4NUpEaB9NdYtW7xQ8EVjZD2p2ssW';

// Son çalışma zamanları
$online_sorgulama_endeks_son_calistirma = $allSettings['online_sorgulama_endeks_son_calistirma'] ?? '08:15';
$online_sorgulama_puantaj_son_calistirma = $allSettings['online_sorgulama_puantaj_son_calistirma'] ?? '08:45';

// Saat seçenekleri oluştur (tam saat aralıklarla - cron 15 dk'da bir çalışsa bile sadece tam saatlerde tetiklenir)
$saatSecenekleri = [];
for ($saat = 0; $saat < 24; $saat++) {
    $saatStr = sprintf('%02d:00', $saat);
    $saatSecenekleri[$saatStr] = $saatStr;
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
                    <?php echo Form::FormMultipleSelect2(
                        "online_sorgulama_endeks_saat_select",
                        $saatSecenekleri,
                        $endeks_saatleri,
                        "Sorgulama Saatleri (En fazla 4)",
                        "clock"
                    ); ?>
                    <input type="hidden" name="online_sorgulama_endeks_saat" id="online_sorgulama_endeks_saat"
                        value="<?php echo htmlspecialchars($online_sorgulama_endeks_saat); ?>">
                    <div class="form-text">
                        <i data-feather="info" style="width:14px;height:14px" class="me-1"></i>
                        En fazla <strong>4 saat</strong> seçilebilir, saatler arasında en az <strong>1 saat</strong>
                        fark olmalıdır.
                    </div>
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
                    <?php echo Form::FormMultipleSelect2(
                        "online_sorgulama_puantaj_saat_select",
                        $saatSecenekleri,
                        $puantaj_saatleri,
                        "Sorgulama Saatleri (En fazla 4)",
                        "clock"
                    ); ?>
                    <input type="hidden" name="online_sorgulama_puantaj_saat" id="online_sorgulama_puantaj_saat"
                        value="<?php echo htmlspecialchars($online_sorgulama_puantaj_saat); ?>">
                    <div class="form-text">
                        <i data-feather="info" style="width:14px;height:14px" class="me-1"></i>
                        En fazla <strong>4 saat</strong> seçilebilir, saatler arasında en az <strong>1 saat</strong>
                        fark olmalıdır.
                    </div>
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

    <!-- ENDEKS API AYARLARI -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="link" class="me-2"></i>Endeks Okuma API Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php echo Form::FormFloatInput(
                        "url",
                        "api_endeks_url",
                        $api_endeks_url,
                        "",
                        "Endeks API URL",
                        "link",
                        "form-control"
                    ); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php echo Form::FormFloatInput(
                        "password",
                        "api_endeks_sifre_yeni",
                        "",
                        "",
                        "Endeks API Key / Şifre (Değiştirmek için doldurun)",
                        "key",
                        "form-control"
                    ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- KESME/AÇMA API AYARLARI -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="link" class="me-2"></i>Kesme/Açma API Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php echo Form::FormFloatInput(
                        "url",
                        "api_puantaj_url",
                        $api_puantaj_url,
                        "",
                        "Kesme/Açma API URL",
                        "link",
                        "form-control"
                    ); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <?php echo Form::FormFloatInput(
                        "password",
                        "api_puantaj_sifre_yeni",
                        "",
                        "",
                        "Kesme/Açma API Key / Şifre (Değiştirmek için doldurun)",
                        "key",
                        "form-control"
                    ); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CRON BİLGİSİ -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i data-feather="terminal" class="me-2"></i>Cron Job Kurulumu</h5>
        </div>
        <div class="card-body p-4">
            <p>Otomatik sorgulama için sunucunuzda aşağıdaki <strong>2 ayrı</strong> cron job'u eklemeniz gerekmektedir:
            </p>

            <?php 
            $basePath = realpath(dirname(__DIR__, 2)); 
            // Windows-style path'leri Linux formatına çevir (eğer lazımsa, genellikle PHP ikisini de anlar ama temiz görünmesi için)
            $endeksPath = str_replace('\\', '/', $basePath . '/views/cron/endeks_okuma_cron.php');
            $kesmePath = str_replace('\\', '/', $basePath . '/views/cron/kesme_acma_cron.php');
            $logPath = str_replace('\\', '/', $basePath . '/views/cron/logs/cron.log');
            ?>
            <h6 class="text-primary mt-3 mb-2"><i data-feather="activity" class="me-1"
                    style="width:16px;height:16px"></i> 1. Endeks Okuma Cron</h6>
            <div class="bg-dark text-light p-3 rounded mb-3">
                <code class="text-warning" style="word-break:break-all;">
                */15 * * * * /usr/local/bin/php -q <?= $endeksPath ?> >> <?= $logPath ?> 2>&1
                </code>
            </div>

            <h6 class="text-success mt-3 mb-2"><i data-feather="scissors" class="me-1"
                    style="width:16px;height:16px"></i> 2. Kesme/Açma Cron</h6>
            <div class="bg-dark text-light p-3 rounded mb-3">
                <code class="text-warning" style="word-break:break-all;">
                */15 * * * * /usr/local/bin/php -q <?= $kesmePath ?> >> <?= $logPath ?> 2>&1
                </code>
            </div>

            <p class="mb-0 text-muted">
                <i data-feather="info" class="me-1"></i>
                Her iki cron da 15 dakikada bir çalışır ve yukarıda ayarlanan saatlere denk geldiğinde sorgulama yapar.
                Her sorgulama türü için günde en fazla <strong>4 farklı saat</strong> belirlenebilir.
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

        // ========== Çoklu Saat Seçimi İşlevleri (Select2) ==========

        /**
         * Saatin dakika cinsinden değerini döndürür
         */
        function saatToDakika(saat) {
            const [h, m] = saat.split(':').map(Number);
            return h * 60 + (m || 0);
        }

        /**
         * Seçilen saatler arasında en az 60 dakika (1 saat) fark olup olmadığını kontrol eder
         */
        function saatlerGecerliMi(saatler) {
            if (saatler.length <= 1) return { gecerli: true };
            const dakikalar = saatler.map(saatToDakika).sort((a, b) => a - b);
            for (let i = 1; i < dakikalar.length; i++) {
                const fark = dakikalar[i] - dakikalar[i - 1];
                if (fark < 60) {
                    const s1 = saatler.find(s => saatToDakika(s) === dakikalar[i - 1]);
                    const s2 = saatler.find(s => saatToDakika(s) === dakikalar[i]);
                    return {
                        gecerli: false,
                        mesaj: `${s1} ile ${s2} arasında en az 1 saat olmalıdır (şu an ${fark} dakika).`
                    };
                }
            }
            return { gecerli: true };
        }

        /**
         * Select2 çoklu seçim için event handler ve hidden input senkronizasyonu
         */
        function setupSelect2Handler(selectId, hiddenId) {
            const $select = $('#' + selectId);
            const hiddenEl = document.getElementById(hiddenId);
            if (!$select.length || !hiddenEl) return;

            // Select2 başlat (zaten init olmuş olabilir, kontrol et)
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    placeholder: 'Saat seçiniz...',
                    allowClear: true,
                    maximumSelectionLength: 4
                });
            }

            // Select2 change event
            $select.on('change', function () {
                const secilen = $(this).val() || [];

                // Minimum 1 saat fark kontrolü
                if (secilen.length > 1) {
                    const sonuc = saatlerGecerliMi(secilen);
                    if (!sonuc.gecerli) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Saat Aralığı Hatası',
                            text: sonuc.mesaj,
                            confirmButtonText: 'Tamam'
                        });
                        // Son eklenen saati kaldır
                        const previousVal = hiddenEl.value ? hiddenEl.value.split(',').filter(v => v) : [];
                        $select.val(previousVal).trigger('change.select2');
                        return;
                    }
                }

                // Geçerli, hidden input'u güncelle
                hiddenEl.value = secilen.join(',');
            });
        }

        // Select2 handler'ları başlat
        setupSelect2Handler('online_sorgulama_endeks_saat_select', 'online_sorgulama_endeks_saat');
        setupSelect2Handler('online_sorgulama_puantaj_saat_select', 'online_sorgulama_puantaj_saat');

        // ========== Kaydetme ==========
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                // Kaydetmeden önce validasyon
                const endeksSaatler = document.getElementById('online_sorgulama_endeks_saat').value;
                const puantajSaatler = document.getElementById('online_sorgulama_puantaj_saat').value;

                if (endeksSaatler) {
                    const arr = endeksSaatler.split(',');
                    if (arr.length > 4) {
                        Swal.fire('Hata', 'Endeks Okuma için en fazla 4 saat seçebilirsiniz.', 'error');
                        return;
                    }
                    const sonuc = saatlerGecerliMi(arr);
                    if (!sonuc.gecerli) {
                        Swal.fire('Hata', 'Endeks Okuma: ' + sonuc.mesaj, 'error');
                        return;
                    }
                }
                if (puantajSaatler) {
                    const arr = puantajSaatler.split(',');
                    if (arr.length > 4) {
                        Swal.fire('Hata', 'Kesme/Açma için en fazla 4 saat seçebilirsiniz.', 'error');
                        return;
                    }
                    const sonuc = saatlerGecerliMi(arr);
                    if (!sonuc.gecerli) {
                        Swal.fire('Hata', 'Kesme/Açma: ' + sonuc.mesaj, 'error');
                        return;
                    }
                }

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

        // ========== Manuel Puantaj Sorgulama ==========
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

        // ========== Manuel Endeks Sorgulama ==========
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