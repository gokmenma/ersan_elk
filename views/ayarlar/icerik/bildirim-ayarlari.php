<?php
use App\Helper\Form; // Form helper'ınız

// Bu değişkenlerin veritabanından veya kullanıcı ayarlarından geldiğini varsayalım
// Örnek olması için varsayılan değerlerle başlatıyoruz
$kullanici_id = $kullanici_ayarlari->id ?? $_SESSION['user_id'] ?? null; // Düzenleme modu için
$genel_sms_aktif = $kullanici_ayarlari->genel_sms_aktif ?? true;
$genel_eposta_aktif = $kullanici_ayarlari->genel_eposta_aktif ?? true;

$bildirim_eposta_adresi = $kullanici_ayarlari->bildirim_eposta ?? $mevcut_kullanici_epostasi ?? 'kullanici@example.com';
$bildirim_telefon_no = $kullanici_ayarlari->bildirim_telefon ?? $mevcut_kullanici_telefonu ?? '5xxxxxxxxx';

// Bildirim türleri ve kullanıcının mevcut tercihleri (örnek)
// Bu yapı veritabanından gelmeli: ['event_key' => ['sms' => true, 'email' => false]]
$bildirim_tercihleri = $kullanici_ayarlari->bildirim_tercihleri ?? [
    'yeni_mesaj' => ['sms' => true, 'email' => true, 'label' => 'Yeni Özel Mesaj Aldığında'],
    'gorev_atama' => ['sms' => false, 'email' => true, 'label' => 'Yeni Görev Atandığında'],
    'gorev_son_tarih' => ['sms' => true, 'email' => false, 'label' => 'Görev Son Tarihi Yaklaştığında'],
    'sistem_duyurusu' => ['sms' => false, 'email' => true, 'label' => 'Önemli Sistem Duyurularında'],
    'takvim_hatirlatma' => ['sms' => true, 'email' => true, 'label' => 'Takvim Etkinliği Hatırlatması'],
];

?>

<form action="" id="bildirimAyarlariForm">
    <input type="hidden" name="kullanici_id" value="<?php echo htmlspecialchars($kullanici_id, ENT_QUOTES, 'UTF-8'); ?>">

    <h5 class="mb-4 text-primary"><i class="ti ti-bell-ringing me-2"></i>Genel Bildirim Ayarları</h5>
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="form-check form-switch form-switch-lg">
                <input class="form-check-input" type="checkbox" id="genel_sms_aktif" name="genel_ayarlar[sms_aktif]" value="1" <?php echo $genel_sms_aktif ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genel_sms_aktif">Tüm SMS Bildirimlerini Aç/Kapat</label>
            </div>
            <div class="form-text ps-1">Bu ayar kapalıysa, aşağıdaki SMS tercihleri geçersiz olur.</div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="form-check form-switch form-switch-lg">
                <input class="form-check-input" type="checkbox" id="genel_eposta_aktif" name="genel_ayarlar[eposta_aktif]" value="1" <?php echo $genel_eposta_aktif ? 'checked' : ''; ?>>
                <label class="form-check-label" for="genel_eposta_aktif">Tüm E-posta Bildirimlerini Aç/Kapat</label>
            </div>
            <div class="form-text ps-1">Bu ayar kapalıysa, aşağıdaki e-posta tercihleri geçersiz olur.</div>
        </div>
    </div>

    <hr class="my-4">

    <h5 class="mb-3 text-primary"><i class="ti ti-mail-forward me-2"></i>Bildirim İletişim Bilgileri</h5>
    <div class="row mb-4">
        <div class="col-md-6">
            <?php
            echo Form::FormFloatInput(
                "email", // type
                "bildirim_eposta_adresi", // name
                $bildirim_eposta_adresi, // value
                "", // error message
                "Bildirim E-posta Adresi", // label
                "mail", // icon
                "form-control"
            ); ?>
        </div>
        <div class="col-md-6">
             <?php
            echo Form::FormFloatInput(
                "tel", // type
                "bildirim_telefon_no", // name
                $bildirim_telefon_no, // value
                "", // error
                "Bildirim Telefon Numarası (SMS için)", // label
                "smartphone", // icon
                "form-control"
                // HTML5 pattern
            ); ?>
            <div class="form-text">Lütfen numaranızı başında 0 olmadan giriniz (örn: 5321234567).</div>
        </div>
    </div>

    <hr class="my-4">

    <h5 class="mb-1 text-primary"><i class="ti ti-list-details me-2"></i>Detaylı Bildirim Tercihleri</h5>
    <p class="text-muted mb-4">Hangi durumlarda ve hangi kanallardan bildirim almak istediğinizi seçin.</p>

    <div class="table-responsive">
        <table class="table table-hover table-striped">
            <thead class="table-light">
                <tr>
                    <th style="width: 60%;">Bildirim Durumu</th>
                    <th class="text-center" style="width: 20%;">SMS ile Bildir</th>
                    <th class="text-center" style="width: 20%;">E-posta ile Bildir</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bildirim_tercihleri as $event_key => $settings): ?>
                    <tr>
                        <td>
                            <label class="fw-semibold mb-0" for="tercih_<?php echo $event_key; ?>_sms"><?php echo htmlspecialchars($settings['label'], ENT_QUOTES, 'UTF-8'); ?></label>
                            <?php if (isset($settings['description'])): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($settings['description'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox"
                                       id="tercih_<?php echo $event_key; ?>_sms"
                                       name="tercihler[<?php echo $event_key; ?>][sms]"
                                       value="1"
                                       <?php echo ($settings['sms'] ?? false) ? 'checked' : ''; ?>>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox"
                                       id="tercih_<?php echo $event_key; ?>_eposta"
                                       name="tercihler[<?php echo $event_key; ?>][email]"
                                       value="1"
                                       <?php echo ($settings['email'] ?? false) ? 'checked' : ''; ?>>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveBildirimAyarlariButton" class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Bildirim Ayarlarını Kaydet
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const saveButton = document.getElementById('saveBildirimAyarlariButton');
    const form = document.getElementById('bildirimAyarlariForm');

    // Genel SMS/E-posta switch'lerinin durumuna göre detaylı tercihleri enable/disable etme
    const genelSmsAktifSwitch = document.getElementById('genel_sms_aktif');
    const genelEpostaAktifSwitch = document.getElementById('genel_eposta_aktif');

    function toggleDetailedPreferences() {
        const smsCheckboxes = form.querySelectorAll('input[name^="tercihler"][name$="[sms]"]');
        const emailCheckboxes = form.querySelectorAll('input[name^="tercihler"][name$="[email]"]');

        smsCheckboxes.forEach(cb => {
            cb.disabled = !genelSmsAktifSwitch.checked;
            if (!genelSmsAktifSwitch.checked) {
                // cb.checked = false; // İsteğe bağlı: Genel kapalıysa detayları da kapat
            }
        });
        emailCheckboxes.forEach(cb => {
            cb.disabled = !genelEpostaAktifSwitch.checked;
            if (!genelEpostaAktifSwitch.checked) {
                // cb.checked = false; // İsteğe bağlı: Genel kapalıysa detayları da kapat
            }
        });
    }

    if (genelSmsAktifSwitch) {
        genelSmsAktifSwitch.addEventListener('change', toggleDetailedPreferences);
    }
    if (genelEpostaAktifSwitch) {
        genelEpostaAktifSwitch.addEventListener('change', toggleDetailedPreferences);
    }
    // Sayfa yüklendiğinde de durumu kontrol et
    toggleDetailedPreferences();


    if (saveButton) {
        saveButton.addEventListener('click', function() {
            const formData = new FormData(form);
            formData.append('action', 'kullanici_bildirim_ayarlarini_kaydet'); // Sunucu tarafı için action

            // Örnek: FormData içeriğini loglama
            console.log("Bildirim Ayarları Kaydet butonuna basıldı. FormData içeriği:");
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }

            // AJAX ile gönderme (fetch örneği)
            /*
            fetch('/path/to/your/api/save_notification_settings', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // SweetAlert veya benzeri bir bildirimle başarı mesajı
                    Swal.fire({
                        title: 'Başarılı!',
                        text: 'Bildirim ayarlarınız güncellendi.',
                        icon: 'success',
                        confirmButtonText: 'Tamam'
                    });
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: data.message || 'Ayarlar kaydedilemedi.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                }
            })
            .catch(error => {
                console.error('Kaydetme hatası:', error);
                Swal.fire({
                    title: 'Sunucu Hatası!',
                    text: 'Ayarlar kaydedilirken bir sorun oluştu. Lütfen daha sonra tekrar deneyin.',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
            });
            */
            alert('Bildirim ayarları konsola yazdırıldı (AJAX gönderme işlemi entegre edilmeli).');
        });
    }
});
</script>