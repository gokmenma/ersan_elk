<?php

use App\Helper\Form;
use App\Helper\Security;
use App\Model\MobilImzaModel;
use App\Model\SettingsModel;
use App\Service\TurkcellMobilImzaClient;

$Settings = $Settings ?? new SettingsModel();
$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

$mobilImzaAktif = ($allSettings['mobil_imza_aktif'] ?? '0') === '1';
$mobilImzaGosterimMetni = $allSettings['mobil_imza_gosterim_metni'] ?? '{evrak_no} sayılı evrakı imzalıyorsunuz';
$mobilImzaServisHazir = TurkcellMobilImzaClient::isConfigured();
$mobilImzaciler = (new MobilImzaModel())->getSigners((int) ($_SESSION['owner_id'] ?? 0), (int) ($firma_id ?? 0));
$mobilImzaKepVarsayilanId = (int) ($allSettings['mobil_imza_kep_varsayilan_kullanici'] ?? 0);
$mobilImzaKepSecenekler = ['' => 'Seçilmedi'];
$mobilImzaKepSecili = '';
foreach ($mobilImzaciler as $imzaci) {
    $sifreliId = Security::encrypt((int) $imzaci->id);
    $mobilImzaKepSecenekler[$sifreliId] = (string) $imzaci->adi_soyadi . ($imzaci->msisdn === '' ? ' (hat tanımsız)' : '');
    if ((int) $imzaci->id === $mobilImzaKepVarsayilanId) {
        $mobilImzaKepSecili = $sifreliId;
    }
}

?>

<form action="" id="mobilImzaAyarlariForm">
    <input type="hidden" name="firma_id" value="<?php echo htmlspecialchars((string) ($firma_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="bx bx-mobile-alt me-2"></i>Turkcell Mobil İmza Hizmeti</h5>
        </div>
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h6 class="mb-1">Giden evrakların mobil imza ile imzalanması</h6>
                    <p class="text-muted mb-2 font-size-13">
                        Açık olduğunda imza sırası gelen yetkili, evrakı telefonuna gelen Turkcell Mobil İmza mesajını
                        onaylayıp PIN girerek PAdES (nitelikli elektronik imza) olarak imzalar. Akıllı kart ile atılan imzayla
                        aynı doğrulama ve arşiv sürecinden geçer.
                    </p>
                    <span class="badge <?php echo $mobilImzaServisHazir ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?> rounded-pill px-2 py-1 font-size-11 fw-semibold">
                        <?php echo $mobilImzaServisHazir ? 'Servis kimlik bilgileri (.env) tanımlı' : 'Servis kimlik bilgileri (.env) eksik'; ?>
                    </span>
                </div>
                <div class="col-md-4 text-end">
                    <div class="form-check form-switch form-switch-lg d-inline-block">
                        <input class="form-check-input" type="checkbox" role="switch" id="mobil_imza_aktif"
                            name="mobil_imza_aktif" value="1" <?php echo $mobilImzaAktif ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mobil_imza_aktif">
                            <span id="mobil-imza-durum-label"
                                class="badge <?php echo $mobilImzaAktif ? 'bg-success' : 'bg-danger'; ?> font-size-12">
                                <?php echo $mobilImzaAktif ? 'Açık' : 'Kapalı'; ?>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="col-12">
                    <?php echo Form::FormFloatInput('text', 'mobil_imza_gosterim_metni', $mobilImzaGosterimMetni, '{evrak_no} sayılı evrakı imzalıyorsunuz', 'Telefonda gösterilecek metin ({evrak_no}, {konu}, {kurum})', 'bx bx-message-detail', 'form-control', false, 100); ?>
                    <div class="form-text">En fazla 100 karakter. İmzacı bu metni telefonundaki onay ekranında görür.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="bx bx-id-card me-2"></i>İmzacı Mobil İmza Hatları</h5>
        </div>
        <div class="card-body p-4">
            <?php if ($mobilImzaciler === []): ?>
                <p class="text-muted mb-0">Evrak imza yetkisi verilmiş aktif kullanıcı bulunmuyor. Kullanıcı düzenleme ekranından “Evrak İmza Yetkisi” verilen kullanıcılar burada listelenir.</p>
            <?php else: ?>
                <p class="text-muted font-size-13">Her imzacının mobil imza hizmeti tanımlı Turkcell hattını giriniz. “Profil Sorgula” ile hattın mobil imza sertifikası Turkcell üzerinden doğrulanır.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>İmzacı</th>
                                <th style="width: 320px;">Mobil İmza Hattı</th>
                                <th class="text-center" style="width: 160px;">Kontrol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mobilImzaciler as $index => $imzaci): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars((string) $imzaci->adi_soyadi, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="font-size-12 text-muted"><?php echo htmlspecialchars((string) ($imzaci->imza_unvani ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <input type="hidden" name="kullanici_ids[<?php echo (int) $index; ?>]" value="<?php echo htmlspecialchars(Security::encrypt((int) $imzaci->id), ENT_QUOTES, 'UTF-8'); ?>">
                                    </td>
                                    <td>
                                        <?php echo Form::FormFloatInput('tel', 'msisdn[' . (int) $index . ']', $imzaci->msisdn, '05XXXXXXXXX', 'Turkcell hattı', 'bx bx-phone', 'form-control mobil-imza-msisdn', false, 16, 'off'); ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-mobil-imza-profil" data-index="<?php echo (int) $index; ?>">
                                            <i class="bx bx-search-alt me-1"></i>Profil Sorgula
                                        </button>
                                        <div class="font-size-11 mt-1 mobil-imza-profil-sonuc" data-index="<?php echo (int) $index; ?>"></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary"><i class="bx bx-mail-send me-2"></i>KEP Gönderisi Varsayılan İmzacı</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <?php echo Form::FormSelect2('mobil_imza_kep_varsayilan', $mobilImzaKepSecenekler, $mobilImzaKepSecili, 'Varsayılan mobil imza kullanıcısı', 'user', 'key', '', 'form-select select2', false); ?>
                </div>
                <div class="col-12">
                    <div class="form-text">Sistemde oturum açan kullanıcının mobil imza hattı tanımlı olmadığında KEP iletisi bu kullanıcının mobil imzasıyla imzalanır. Telefona gelen onay isteği bu kullanıcıya gider.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveMobilImzaAyarlariButton" class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-device-floppy label-icon me-1"></i> Ayarları Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('mobilImzaAyarlariForm');
        const saveButton = document.getElementById('saveMobilImzaAyarlariButton');
        const toggle = document.getElementById('mobil_imza_aktif');
        const label = document.getElementById('mobil-imza-durum-label');
        if (!form || !saveButton) {
            return;
        }

        toggle.addEventListener('change', function () {
            label.textContent = this.checked ? 'Açık' : 'Kapalı';
            label.classList.toggle('bg-success', this.checked);
            label.classList.toggle('bg-danger', !this.checked);
        });

        if (window.jQuery && jQuery.fn.select2) {
            const kepSelect = jQuery('#mobil_imza_kep_varsayilan');
            const kepModal = jQuery('#modalMobilImzaAyarlari');
            kepSelect.select2({ width: '100%', dropdownParent: kepModal.length ? kepModal : jQuery(document.body) });
        }

        const post = formData => fetch('views/ayarlar/api.php', { method: 'POST', body: formData }).then(response => response.json());

        saveButton.addEventListener('click', function () {
            const btn = this;
            const eskiIcerik = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';
            const formData = new FormData(form);
            formData.append('action', 'save_mobil_imza_ayarlari');
            post(formData)
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message, timer: 2000, showConfirmButton: false });
                        if (document.getElementById('modalMobilImzaAyarlari')) {
                            setTimeout(() => window.location.reload(), 1200);
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                    }
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Hata', text: 'Ayarlar kaydedilirken bir hata oluştu.' }))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = eskiIcerik;
                });
        });

        form.querySelectorAll('.btn-mobil-imza-profil').forEach(button => {
            button.addEventListener('click', function () {
                const index = this.dataset.index;
                const input = form.querySelector(`[name="msisdn[${index}]"]`);
                const result = form.querySelector(`.mobil-imza-profil-sonuc[data-index="${index}"]`);
                const formData = new FormData();
                formData.append('action', 'test_mobil_imza_profil');
                formData.append('msisdn', input ? input.value : '');
                this.disabled = true;
                result.className = 'font-size-11 mt-1 mobil-imza-profil-sonuc text-muted';
                result.textContent = 'Sorgulanıyor...';
                post(formData)
                    .then(data => {
                        result.className = 'font-size-11 mt-1 mobil-imza-profil-sonuc ' + (data.status === 'success' ? 'text-success' : 'text-danger');
                        result.textContent = data.message || '';
                    })
                    .catch(() => {
                        result.className = 'font-size-11 mt-1 mobil-imza-profil-sonuc text-danger';
                        result.textContent = 'Sorgu tamamlanamadı.';
                    })
                    .finally(() => { this.disabled = false; });
            });
        });
    });
</script>
