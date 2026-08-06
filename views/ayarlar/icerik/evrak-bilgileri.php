<?php

use App\Helper\Form;
use App\Model\SettingsModel;

$Settings = new SettingsModel();

$firma_id = $_SESSION["firma_id"] ?? null;
$allSettings = $Settings->getAllSettingsAsKeyValue($firma_id);

$sol_logo_yolu = $allSettings['sol_logo_yolu'] ?? '';
$sag_logo_yolu = $allSettings['sag_logo_yolu'] ?? '';

$evrak_antet_baslik_1 = $allSettings['evrak_antet_baslik_1'] ?? '';
$evrak_antet_baslik_2 = $allSettings['evrak_antet_baslik_2'] ?? '';
$evrak_antet_baslik_3 = $allSettings['evrak_antet_baslik_3'] ?? '';
$evrak_antet_baslik_4 = $allSettings['evrak_antet_baslik_4'] ?? '';

$evrak_alt_bilgi_1 = $allSettings['evrak_alt_bilgi_1'] ?? '';
$evrak_alt_bilgi_2 = $allSettings['evrak_alt_bilgi_2'] ?? '';
$evrak_alt_bilgi_3 = $allSettings['evrak_alt_bilgi_3'] ?? '';
$evrak_alt_bilgi_4 = $allSettings['evrak_alt_bilgi_4'] ?? '';
$evrak_eimza_goster = $allSettings['evrak_eimza_goster'] ?? '1';

?>

<form action="" id="evrakBilgileriAyarlariForm" enctype="multipart/form-data">
    <input type="hidden" name="firma_id" value="<?php echo htmlspecialchars((string) ($firma_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string) ($_SESSION["user_id"] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Evrak Logoları -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary fw-bold"><i class="ti ti-photo me-2"></i>Evrak Logoları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <label for="sol_logo" class="form-label fw-semibold">Sol Logo Yükle</label>
                    <div class="input-group mb-1">
                        <input type="file" class="form-control" id="sol_logo" name="sol_logo" accept="image/*">
                    </div>
                    <div class="form-text text-muted">Evrakın sol üst köşesi için logo. Max 100x100px.</div>

                    <?php if (!empty($sol_logo_yolu)): ?>
                        <div class="mt-3">
                            <div class="small fw-semibold text-muted mb-2">Mevcut Sol Logo:</div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="border rounded p-2 bg-white d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <img src="<?php echo htmlspecialchars($sol_logo_yolu, ENT_QUOTES, 'UTF-8'); ?>" alt="Sol Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-logo-btn" data-side="sol">
                                    <i class="ti ti-trash me-1"></i> Kaldır
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="sag_logo" class="form-label fw-semibold">Sağ Logo Yükle (Opsiyonel)</label>
                    <div class="input-group mb-1">
                        <input type="file" class="form-control" id="sag_logo" name="sag_logo" accept="image/*">
                    </div>
                    <div class="form-text text-muted">Evrakın sağ üst köşesi için logo. Max 100x100px.</div>

                    <?php if (!empty($sag_logo_yolu)): ?>
                        <div class="mt-3">
                            <div class="small fw-semibold text-muted mb-2">Mevcut Sağ Logo:</div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="border rounded p-2 bg-white d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <img src="<?php echo htmlspecialchars($sag_logo_yolu, ENT_QUOTES, 'UTF-8'); ?>" alt="Sağ Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm remove-logo-btn" data-side="sag">
                                    <i class="ti ti-trash me-1"></i> Kaldır
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Evrak Antet (Üst Bilgi) Ayarları -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary fw-bold"><i class="ti ti-heading me-2"></i>Evrak Antet (Üst Bilgi) Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <?php echo Form::FormFloatTextarea('evrak_antet_baslik_1', $evrak_antet_baslik_1, 'Başlık Satır 1', 'Başlık Satır 1', 'file-text', 'form-control', false, '85px'); ?>
                </div>
                <div class="col-md-3">
                    <?php echo Form::FormFloatTextarea('evrak_antet_baslik_2', $evrak_antet_baslik_2, 'Başlık Satır 2', 'Başlık Satır 2', 'file-text', 'form-control', false, '85px'); ?>
                </div>
                <div class="col-md-3">
                    <?php echo Form::FormFloatTextarea('evrak_antet_baslik_3', $evrak_antet_baslik_3, 'Başlık Satır 3', 'Başlık Satır 3', 'file-text', 'form-control', false, '85px'); ?>
                </div>
                <div class="col-md-3">
                    <?php echo Form::FormFloatTextarea('evrak_antet_baslik_4', $evrak_antet_baslik_4, 'Başlık Satır 4', 'Başlık Satır 4', 'file-text', 'form-control', false, '85px'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Evrak Alt Bilgi Ayarları -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary fw-bold"><i class="ti ti-layout-bottombar me-2"></i>Evrak Alt Bilgi Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6 mb-2">
                    <?php echo Form::FormFloatInput('text', 'evrak_alt_bilgi_1', $evrak_alt_bilgi_1, 'Adres bilgisi', 'Alt Bilgi Satır 1 (Adres)', 'map-pin', 'form-control'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <?php echo Form::FormFloatInput('text', 'evrak_alt_bilgi_2', $evrak_alt_bilgi_2, 'Telefon ve faks', 'Alt Bilgi Satır 2 (Tel/Faks)', 'phone', 'form-control'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <?php echo Form::FormFloatInput('text', 'evrak_alt_bilgi_3', $evrak_alt_bilgi_3, 'E-posta ve web adresi', 'Alt Bilgi Satır 3 (E-posta/Web)', 'globe', 'form-control'); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <?php echo Form::FormFloatInput('text', 'evrak_alt_bilgi_4', $evrak_alt_bilgi_4, 'KEP veya dipnot', 'Alt Bilgi Satır 4 (Dipnot)', 'mail', 'form-control'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- E-İmza Görünüm Ayarları -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-primary fw-bold"><i class="ti ti-certificate me-2"></i>E-İmza Görünüm Ayarları</h5>
        </div>
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border">
                <div>
                    <h6 class="mb-1 fw-bold text-dark">Çıktıda E-imza bilgilerini göster</h6>
                    <p class="text-muted small mb-0">İşaretli olduğunda alt bilgi alanındaki elektronik imza metni çıktıda görünür, işaretli değilse görünmez.</p>
                </div>
                <div class="form-check form-switch ms-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="evrak_eimza_goster" value="1" id="evrak_eimza_goster" style="width: 2.5em; height: 1.25em;" <?php echo ($evrak_eimza_goster !== '0') ? 'checked' : ''; ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4 mb-3">
        <div class="col-md-12 text-end">
            <button type="button" id="saveEvrakAyarlariButton" class="btn btn-success waves-effect btn-label waves-light">
                <i class="ti ti-check label-icon me-1"></i> Antet Ayarlarını Kaydet
            </button>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('evrakBilgileriAyarlariForm');
        const saveButton = document.getElementById('saveEvrakAyarlariButton');

        if (saveButton && form) {
            saveButton.addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...';

                const formData = new FormData(form);
                formData.append('action', 'save_evrak_ayarlari');

                fetch('views/ayarlar/api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı!',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.reload();
                            });
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
                        btn.innerHTML = '<i class="ti ti-check label-icon me-1"></i> Antet Ayarlarını Kaydet';
                    });
            });
        }

        // Logo kaldırma işlemleri
        document.querySelectorAll('.remove-logo-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const side = this.getAttribute('data-side');
                const sideText = side === 'sol' ? 'Sol' : 'Sağ';

                Swal.fire({
                    title: sideText + ' Logo Silinecek',
                    text: 'Bu logoyu kaldırmak istediğinize emin misiniz?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Evet, Kaldır',
                    cancelButtonText: 'Vazgeç'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'remove_logo');
                        formData.append('side', side);
                        formData.append('firma_id', '<?php echo htmlspecialchars((string) ($firma_id ?? ''), ENT_QUOTES, 'UTF-8'); ?>');

                        fetch('views/ayarlar/api.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Kaldırıldı!',
                                        text: data.message,
                                        timer: 1200,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Hata!', data.message || 'Logo kaldırılamadı.', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Logo silme hatası:', error);
                                Swal.fire('Sunucu Hatası!', 'Logo kaldırılırken bir hata oluştu.', 'error');
                            });
                    }
                });
            });
        });
    });
</script>
