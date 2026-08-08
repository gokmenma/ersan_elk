<?php

use App\Model\SettingsModel;

$Settings = $Settings ?? new SettingsModel();
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

$avansTalepSerbest = ($allSettings['avans_talep_serbest'] ?? '0') === '1';

?>

<form action="" id="avansAyarlariForm">
    <input type="hidden" name="firma_id" value="<?php echo htmlspecialchars((string) ($_SESSION["firma_id"] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string) ($_SESSION["user_id"] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="ti ti-cash me-2"></i>Avans Talep Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-1">Tüm Personele Avans Talebi Serbest</h6>
                    <p class="text-muted mb-0 font-size-13">
                        Bu ayar <strong>kapalıyken</strong> sadece göreve başlama tarihinden itibaren hiç kapalı
                        bordro döneminde yer almamış (henüz maaş almamış) personel avans talebi oluşturabilir.
                        Diğer personel talep etmek istediğinde
                        <em>"İlk işe başlama haricinde avans uygulaması yapılmamaktadır."</em> uyarısını alır.
                        Ayar <strong>açıldığında</strong> bu kısıt kalkar ve tüm personel avans talebi oluşturabilir.
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="form-check form-switch form-switch-lg d-inline-block">
                        <input class="form-check-input" type="checkbox" role="switch" id="avans_talep_serbest"
                            name="avans_talep_serbest" value="1" <?php echo $avansTalepSerbest ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="avans_talep_serbest">
                            <span id="avans-durum-label"
                                class="badge <?php echo $avansTalepSerbest ? 'bg-success' : 'bg-danger'; ?> font-size-12">
                                <?php echo $avansTalepSerbest ? 'Açık' : 'Kapalı'; ?>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveAvansAyarlariButton"
                class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('avansAyarlariForm');
        const saveButton = document.getElementById('saveAvansAyarlariButton');
        const toggle = document.getElementById('avans_talep_serbest');
        const label = document.getElementById('avans-durum-label');

        if (toggle && label) {
            toggle.addEventListener('change', function () {
                label.textContent = this.checked ? 'Açık' : 'Kapalı';
                label.classList.toggle('bg-success', this.checked);
                label.classList.toggle('bg-danger', !this.checked);
            });
        }

        if (!saveButton || !form) {
            return;
        }

        saveButton.addEventListener('click', function () {
            const btn = this;
            const eskiIcerik = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';

            const formData = new FormData(form);
            formData.append('action', 'save_avans_ayarlari');

            fetch('views/ayarlar/api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message, timer: 2000, showConfirmButton: false });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                    }
                })
                .catch(() => {
                    Swal.fire({ icon: 'error', title: 'Hata', text: 'Ayarlar kaydedilirken bir hata oluştu.' });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = eskiIcerik;
                });
        });
    });
</script>
