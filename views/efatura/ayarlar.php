<?php
\App\Service\Gate::authorizeOrDie('efatura/ayarlar');
use App\Helper\Form;
use App\Model\EInvoiceSettingsModel;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Entegratör Ayarları';

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$settingsModel = new EInvoiceSettingsModel();
$settings = $settingsModel->getSettings($firmId) ?: [];

$envOptions = [
    'TEST' => 'TEST / SandBox (Geliştirme)',
    'LIVE' => 'CANLI / Production (Gerçek Gönderim)'
];
?>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js"></script>


<div class="container-fluid pb-5">
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; border: 1px solid rgba(226, 232, 240, 0.8) !important;">
                <div class="card-header bg-transparent border-bottom p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-subtle text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                            <i class="bx bx-cog fs-4"></i>
                        </div>
                        <div>
                            <span class="fw-bold">EDM bağlantı bilgileri</span>
                            <small class="text-muted">Firma bazlı e-fatura/e-arşiv web servis erişim bilgileri.</small>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form id="formEfaturaAyarlar">
                        <div class="row g-3">
                            <!-- Entegratör ve Ortam -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'entegrator',
                                    'EDM Bilişim',
                                    'Entegratör',
                                    'Entegratör',
                                    'server',
                                    'form-control fw-semibold',
                                    false,
                                    null,
                                    'off',
                                    true
                                ) ?>
                            </div>

                            <div class="col-md-6">
                                <?= Form::FormSelect2(
                                    'environment',
                                    $envOptions,
                                    $settings['environment'] ?? 'TEST',
                                    'Çalışma Ortamı *',
                                    'shield',
                                    'key',
                                    '',
                                    'form-select select2',
                                    true,
                                    'width:100%',
                                    '',
                                    'environment'
                                ) ?>
                            </div>

                            <!-- API Kullanıcı Adı & Şifre -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'api_username',
                                    $settings['api_username'] ?? '',
                                    'Örn: 1234567890',
                                    'EDM API Kullanıcı Adı (VKN / TCKN) *',
                                    'user',
                                    'form-control fw-bold',
                                    true
                                ) ?>
                            </div>

                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'password',
                                    'api_password',
                                    '',
                                    !empty($settings['api_password']) ? '•••••••••••• (Mevcut şifre korunuyor)' : 'API Parolası',
                                    'EDM API Parolası *',
                                    'lock',
                                    'form-control',
                                    false,
                                    null,
                                    'new-password'
                                ) ?>
                                <small class="text-muted ms-1">Şifrenizi değiştirmek istemiyorsanız boş bırakabilirsiniz.</small>
                            </div>

                            <!-- Seri Kodları -->
                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'efatura_seri',
                                    $settings['efatura_seri'] ?? 'ERS',
                                    'Örn: ERS',
                                    'E-Fatura Seri Öneki (3 Hane) *',
                                    'file-text',
                                    'form-control text-uppercase fw-bold',
                                    true,
                                    3
                                ) ?>
                            </div>

                            <div class="col-md-6">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'earsiv_seri',
                                    $settings['earsiv_seri'] ?? 'ERA',
                                    'Örn: ERA',
                                    'E-Arşiv Seri Öneki (3 Hane) *',
                                    'archive',
                                    'form-control text-uppercase fw-bold',
                                    true,
                                    3
                                ) ?>
                            </div>

                            <!-- Gönderici Posta Kutusu Alias -->
                            <div class="col-md-12">
                                <?= Form::FormFloatInput(
                                    'text',
                                    'varsayilan_gonderici_alias',
                                    $settings['varsayilan_gonderici_alias'] ?? '',
                                    'urn:mail:defaultgb',
                                    'Varsayılan Gönderici Posta Kutusu (GB Alias)',
                                    'mail',
                                    'form-control'
                                ) ?>
                                <small class="text-muted ms-1">EDM hesabındaki aktif gönderici birim etiketini bağlantı kontrolünden doğrulayın.</small>
                            </div>
                        </div>

                        <div class="mt-3"><label for="kontor_esik">Düşük kontör uyarı eşiği</label><input type="number" min="0" class="form-control" id="kontor_esik" name="kontor_esik" value="<?= htmlspecialchars((string)($settings['kontor_esik'] ?? 100), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="mt-3"><button type="button" class="btn btn-outline-secondary" id="btnCounter">Kontör Sorgula</button><span id="counterResult" class="ms-2">Henüz sorgulanmadı</span></div>
                        <div id="connectionResult" class="mt-3" role="status"></div>
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-outline-info" id="btnTestConnection">
                                <i class="bx bx-broadcast me-1"></i> Bağlantıyı Test Et
                            </button>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnKaydetAyarlar">
                                <i class="bx bx-check me-1"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.select2').select2({
        dropdownAutoWidth: true,
        width: '100%'
    });

    // Form Kaydet
    $('#formEfaturaAyarlar').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();

        Swal.showLoading();
        fetch('api/efatura-api.php?action=save_settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Başarılı', res.message, 'success');
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        });
    });

    $('#btnTestConnection').on('click', async function() {
        Swal.fire({title: 'Kayıtlı EDM ayarları kontrol ediliyor', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
        try {
            const result = await (await fetch('api/efatura-api.php?action=connection_info', {method: 'POST'})).json();
            if (result.status !== 'success') throw new Error(result.message);
            const c = result.data;
            $('#connectionResult').empty().append($('<p>').text(c.UNVAN + ' / ' + c.VKN), $('<p>').text('GB: ' + (c.GB || 'Yok') + ' | PK: ' + (c.PK || 'Yok')), $('<p>').text('e-Fatura: ' + (c.EFATURA === 70 ? 'Aktif' : 'Pasif') + ' | e-Arşiv: ' + (c.EARSIV === 70 ? 'Aktif' : 'Pasif')));
            c.SERIALS.forEach(serial => $('#connectionResult').append($('<p>').text(serial.series + ' / ' + serial.year + ' / ' + (serial.earchive ? 'e-Arşiv' : 'e-Fatura') + ' / son numara: ' + serial.last + ' / ' + (serial.active === 1 ? 'Aktif' : 'Pasif'))));
            Swal.fire('Bağlantı başarılı', 'Kayıtlı firma ve seri bilgileri alındı.', 'success');
        } catch (e) { Swal.fire('Bağlantı kontrolü', e.message, 'error'); }
    });
    $('#btnCounter').on('click', async function() {
        $('#counterResult').text('Sorgulanıyor…');
        try {
            const result = await (await fetch('api/efatura-api.php?action=counter_info', {method: 'POST'})).json();
            if (result.status !== 'success') throw new Error(result.message);
            $('#counterResult').text(result.data.remaining === null ? 'Kontör bilgisi bulunmuyor' : 'Kalan: ' + result.data.remaining).toggleClass('text-danger', result.data.low);
        } catch (e) { $('#counterResult').text(e.message); }
    });
});
</script>
