<?php
use App\Helper\Form;
use App\Model\EInvoiceSettingsModel;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Entegratör Ayarları';

$firmId = (int)($_SESSION['firm_id'] ?? 1);
$settingsModel = new EInvoiceSettingsModel();
$settings = $settingsModel->getSettings($firmId) ?: [];

$envOptions = [
    'TEST' => 'TEST / SandBox (Geliştirme)',
    'LIVE' => 'CANLI / Production (Gerçek Gönderim)'
];
?>

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
                            <h5 class="mb-0 fw-bold">EDM Bilişim API Yapılandırması</h5>
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
                                    $settings['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb',
                                    'urn:mail:defaultgb',
                                    'Varsayılan Gönderici Posta Kutusu (GB Alias)',
                                    'mail',
                                    'form-control'
                                ) ?>
                                <small class="text-muted ms-1">EDM Bilişim üzerinde tanımlı Gönderici Birim etiketi (Varsayılan: urn:mail:defaultgb)</small>
                            </div>
                        </div>

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

    // Bağlantı Testi
    $('#btnTestConnection').on('click', function() {
        const username = $('input[name="api_username"]').val().trim();
        if (!username) {
            Swal.fire('Uyarı', 'Lütfen önce API kullanıcı adını girin.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Bağlantı Test Ediliyor',
            text: 'EDM Bilişim SOAP servisine erişim deneniyor...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch('api/efatura-api.php?action=check_taxpayer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `vkn_tckn=${encodeURIComponent(username)}`
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Bağlantı Başarılı!', 'EDM Bilişim SOAP servisi ile başarıyla iletişim kuruldu.', 'success');
            } else {
                Swal.fire('Bağlantı Hatası', res.message || 'Servis yanıt vermedi.', 'error');
            }
        })
        .catch(err => {
            Swal.fire('Hata', 'İstek gönderilirken hata oluştu: ' + err.message, 'error');
        });
    });
});
</script>
