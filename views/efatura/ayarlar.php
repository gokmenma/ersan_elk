<?php
\App\Service\Gate::authorizeOrDie('efatura/ayarlar');
use App\Helper\Form;
use App\Model\EInvoiceSettingsModel;

$maintitle = 'E-Fatura & E-Arşiv';
$title = 'Entegratör ve Seri Ayarları';

$firmId = (int)($_SESSION['firm_id'] ?? $_SESSION['firma_id'] ?? 0);
$settingsModel = new EInvoiceSettingsModel();
$settings = $settingsModel->getSettings($firmId) ?: [];
$numaratorList = $settingsModel->getNumarators($firmId);

$envOptions = [
    'TEST' => 'TEST / SandBox (Geliştirme)',
    'LIVE' => 'CANLI / Production (Gerçek Gönderim)'
];
?>
<meta name="efatura-csrf" content="<?= htmlspecialchars(\App\Helper\Security::csrf(), ENT_QUOTES, 'UTF-8') ?>">
<script src="views/efatura/js/transport.js"></script>

<style>
.nav-pills-custom .nav-link {
    color: #64748b;
    font-weight: 600;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 10px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid transparent;
}
.nav-pills-custom .nav-link:hover {
    color: #1e293b;
    background: #f8fafc;
    border-color: #e2e8f0;
}
.nav-pills-custom .nav-link.active {
    color: #2563eb;
    background: #eff6ff;
    border-color: #bfdbfe;
}
.settings-card {
    border-radius: 16px;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
</style>

<div class="container-fluid pb-5">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Üst Özet Bilgi Kartları -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted font-size-11 fw-semibold text-uppercase">Çalışma Ortamı</span>
                        <h6 class="mb-0 fw-bold mt-1 <?= ($settings['environment'] ?? 'TEST') === 'LIVE' ? 'text-success' : 'text-warning' ?>">
                            <?= ($settings['environment'] ?? 'TEST') === 'LIVE' ? '<i class="bx bx-check-shield me-1"></i> CANLI (Production)' : '<i class="bx bx-vial me-1"></i> TEST (SandBox)' ?>
                        </h6>
                    </div>
                    <div class="rounded-3 p-2 <?= ($settings['environment'] ?? 'TEST') === 'LIVE' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>">
                        <i class="bx bx-server fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted font-size-11 fw-semibold text-uppercase">Gönderici Birim (GB)</span>
                        <h6 class="mb-0 fw-bold mt-1 text-dark font-monospace font-size-12 text-truncate" style="max-width: 170px;" title="<?= htmlspecialchars($settings['varsayilan_gonderici_alias'] ?? 'urn:mail:defaultgb', ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($settings['varsayilan_gonderici_alias'] ?? 'defaultgb', ENT_QUOTES, 'UTF-8') ?>
                        </h6>
                    </div>
                    <div class="rounded-3 p-2 bg-primary-subtle text-primary">
                        <i class="bx bx-mail-send fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted font-size-11 fw-semibold text-uppercase">Kayıtlı Seri / Sayaç</span>
                        <h6 class="mb-0 fw-bold mt-1 text-primary">
                            <span class="font-monospace fs-5" id="kpiSeriSayisi"><?= count($numaratorList) ?></span> <span class="font-size-12 text-muted fw-normal">Tanımlı Seri</span>
                        </h6>
                    </div>
                    <div class="rounded-3 p-2 bg-info-subtle text-info">
                        <i class="bx bx-list-ol fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted font-size-11 fw-semibold text-uppercase">Kalan Kontör Durumu</span>
                        <h6 class="mb-0 fw-bold mt-1" id="topKontorBadge">
                            <span class="text-muted font-size-12">Sorgulanmadı</span>
                        </h6>
                    </div>
                    <div class="rounded-3 p-2 bg-secondary-subtle text-secondary cursor-pointer" id="btnQuickCounter" title="Kontör Yenile">
                        <i class="bx bx-coin-stack fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm settings-card">
                
                <!-- Sekme Başlıkları -->
                <div class="card-header bg-transparent border-bottom p-3">
                    <ul class="nav nav-pills nav-pills-custom" id="settingsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-api-btn" data-bs-toggle="pill" data-bs-target="#tab-api" type="button" role="tab" aria-controls="tab-api" aria-selected="true">
                                <i class="bx bx-cog fs-5"></i> 1. EDM Bağlantı & API Ayarları
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-seriler-btn" data-bs-toggle="pill" data-bs-target="#tab-seriler" type="button" role="tab" aria-controls="tab-seriler" aria-selected="false">
                                <i class="bx bx-list-ol fs-5"></i> 2. Fatura Seri & Sayaç Yönetimi
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-rehber-btn" data-bs-toggle="pill" data-bs-target="#tab-rehber" type="button" role="tab" aria-controls="tab-rehber" aria-selected="false">
                                <i class="bx bx-help-circle fs-5"></i> 3. Numaralandırma & Sistem Rehberi
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- SEKME 1: EDM BAĞLANTI & API AYARLARI -->
                        <div class="tab-pane fade show active" id="tab-api" role="tabpanel" aria-labelledby="tab-api-btn">
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

                                    <!-- Gönderici Posta Kutusu Alias -->
                                    <div class="col-md-12">
                                        <?= Form::FormFloatInput(
                                            'text',
                                            'varsayilan_gonderici_alias',
                                            $settings['varsayilan_gonderici_alias'] ?? '',
                                            'urn:mail:defaultgb',
                                            'Varsayılan Gönderici Posta Kutusu (GB Alias)',
                                            'mail',
                                            'form-control font-monospace'
                                        ) ?>
                                        <small class="text-muted ms-1">EDM hesabındaki aktif gönderici birim etiketini bağlantı kontrolünden doğrulayabilirsiniz.</small>
                                    </div>

                                    <!-- Kontör Eşiği ve Otomatik Gönder -->
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput(
                                            'number',
                                            'kontor_esik',
                                            (string)($settings['kontor_esik'] ?? 100),
                                            '100',
                                            'Düşük Kontör Uyarı Eşiği',
                                            'coin-stack',
                                            'form-control',
                                            false
                                        ) ?>
                                    </div>

                                    <div class="col-md-6 d-flex align-items-center pt-3">
                                        <div class="form-check form-switch font-size-14">
                                            <input class="form-check-input cursor-pointer" type="checkbox" id="otomatik_gonder" name="otomatik_gonder" value="1" <?= !empty($settings['otomatik_gonder']) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold cursor-pointer text-dark" for="otomatik_gonder">
                                                Faturaları Kaydederken Otomatik Gönder
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 pt-3 border-top gap-2">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-info" id="btnTestConnection">
                                            <i class="bx bx-broadcast me-1"></i> Bağlantıyı Test Et
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="btnCounter">
                                            <i class="bx bx-coin-stack me-1"></i> Kontör Sorgula
                                        </button>
                                    </div>
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm" id="btnKaydetAyarlar">
                                        <i class="bx bx-check me-1"></i> API Ayarlarını Kaydet
                                    </button>
                                </div>
                            </form>

                            <div id="connectionResult" class="mt-4" role="status"></div>
                        </div>

                        <!-- SEKME 2: FATURA SERİ & SAYAÇ YÖNETİMİ -->
                        <div class="tab-pane fade" id="tab-seriler" role="tabpanel" aria-labelledby="tab-seriler-btn">
                            
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1 font-size-14">
                                        <i class="bx bx-list-ol text-primary me-1"></i> Fatura Seri & Numaratör Sayaç Tablosu
                                    </h6>
                                    <p class="text-muted mb-0 font-size-12">
                                        EDM Portal'daki tüm seriler ve sistemde kaydedilen en son sayaç numaraları.
                                    </p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" id="btnSyncEdmSerials">
                                        <i class="bx bx-sync me-1"></i> EDM'den Serileri & Sayaçları Senkronize Et
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btnNewNumaratorModal">
                                        <i class="bx bx-plus me-1"></i> Yeni Sayaç / Seri Tanımla
                                    </button>
                                </div>
                            </div>

                            <!-- Sayaç Tablosu -->
                            <div class="table-responsive bg-white rounded-3 border p-2">
                                <table class="table table-bordered table-striped dt-responsive nowrap w-100 align-middle mb-0 font-size-13" id="tblNumaratorler" style="width:100% !important;">
                                    <thead class="table-light">
                                        <tr>
                                            <th data-filter="select" style="width: 75px;">Yıl</th>
                                            <th data-filter="select" style="width: 110px;">Belge Türü</th>
                                            <th data-filter="string" style="width: 100px;">Seri Öneki</th>
                                            <th data-filter="string" style="width: 130px;">Son Fatura / Sayaç</th>
                                            <th data-filter="string">Sıradaki Tahmini No</th>
                                            <th data-filter="date" style="width: 130px;">Son Güncelleme</th>
                                            <th class="text-end" style="width: 80px;" data-orderable="false">İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody id="numaratorTableBody">
                                        <?php if (empty($numaratorList)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4 text-muted">
                                                    <i class="bx bx-info-circle fs-4 d-block mb-1 text-secondary"></i>
                                                    Kayıtlı sayaç bulunmuyor. EDM'den serileri çekmek için <strong>"EDM'den Senkronize Et"</strong> butonuna tıklayın.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($numaratorList as $num): ?>
                                                <?php
                                                $nextNo = ((int)$num['son_numara']) + 1;
                                                $formattedNext = sprintf('%s%04d%09d', $num['seri'], $num['yil'], $nextNo);
                                                $isEarsiv = $num['belge_turu'] === 'EARSIV';
                                                ?>
                                                <tr>
                                                    <td><span class="badge bg-light text-dark font-monospace fw-bold"><?= (int)$num['yil'] ?></span></td>
                                                    <td>
                                                        <?= $isEarsiv ? '<span class="badge bg-primary-subtle text-primary">e-Arşiv</span>' : '<span class="badge bg-success-subtle text-success">e-Fatura</span>' ?>
                                                    </td>
                                                    <td>
                                                        <strong class="font-monospace text-dark font-size-14"><?= htmlspecialchars($num['seri'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="font-monospace fw-bold text-dark font-size-13"><?= (int)$num['son_numara'] ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-primary font-monospace font-size-12 border border-primary-subtle"><?= $formattedNext ?></span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?= !empty($num['updated_at']) ? date('d.m.Y H:i', strtotime($num['updated_at'])) : '-' ?></small>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm btn-edit-numarator" 
                                                            data-type="<?= htmlspecialchars($num['belge_turu'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-year="<?= (int)$num['yil'] ?>"
                                                            data-series="<?= htmlspecialchars($num['seri'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-last="<?= (int)$num['son_numara'] ?>"
                                                            title="Sayacı Düzenle">
                                                            <i class="bx bx-edit"></i> Düzenle
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-light border mt-3 font-size-12 mb-0 text-muted">
                                <i class="bx bx-bulb text-warning me-1 font-size-14"></i>
                                <strong>Bilgi:</strong> EDM ile entegrasyon sırasında sistemimiz faturayı onaylatırken EDM'deki son sayaç ile yerel sayacı otomatik eşitler (`GREATEST`). Manuel sayaç değişikliği yalnızca sıra başlatma veya aralık belirleme amaçlı kullanılmalıdır.
                            </div>
                        </div>

                        <!-- SEKME 3: NUMARALANDIRMA & SİSTEM REHBERİ -->
                        <div class="tab-pane fade" id="tab-rehber" role="tabpanel" aria-labelledby="tab-rehber-btn">
                            <div class="p-2">
                                <h6 class="fw-bold text-dark mb-3"><i class="bx bx-book-open text-primary me-1"></i> GİB ve EDM E-Fatura Numaralandırma Standartları</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border bg-light h-100 p-3 rounded-3">
                                            <h6 class="fw-bold text-dark font-size-13"><i class="bx bx-check-shield text-success me-1"></i> 16 Haneli Numara Yapısı</h6>
                                            <p class="font-size-12 text-muted mb-2">
                                                Gelir İdaresi Başkanlığı (GİB) formatına göre e-Fatura ve e-Arşiv numaraları 16 karakterden oluşur:
                                            </p>
                                            <div class="p-2 bg-white rounded border font-monospace font-size-13 text-center text-primary fw-bold mb-2">
                                                [SERİ: 3 Hane] + [YIL: 4 Hane] + [SIRA NO: 9 Hane]
                                            </div>
                                            <small class="text-muted">Örnek: <code>KK12026000000183</code> (KK1 serisi, 2026 yılı, 183. fatura).</small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card border bg-light h-100 p-3 rounded-3">
                                            <h6 class="fw-bold text-dark font-size-13"><i class="bx bx-lock-alt text-danger me-1"></i> Müteselsil Sıra Kuralı</h6>
                                            <p class="font-size-12 text-muted mb-0">
                                                Her seri ve her takvim yılı için sıra numaraları 1'den başlar ve ardışık olarak ilerler. Sıra numarası atlanamaz veya mükerrer kullanılamaz. Taslak faturalarda sıra yakılmaz; fatura EDM'ye gönderildiğinde resmi sıra atanır.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Sayaç Düzenleme / Ekleme Modalı -->
<div class="modal fade" id="modalNumarator" tabindex="-1" aria-labelledby="modalNumaratorLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-bottom">
                <h6 class="modal-title fw-bold text-dark" id="modalNumaratorLabel">
                    <i class="bx bx-edit text-primary me-1"></i> Seri Sayacını Belirle / Düzenle
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formNumaratorDuzenle">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-bold text-dark mb-1">Belge Türü *</label>
                            <select class="form-select select2" id="num_belge_turu" name="belge_turu" required>
                                <option value="EFATURA">e-Fatura</option>
                                <option value="EARSIV">e-Arşiv Fatura</option>
                                <option value="EIRSALIYE">e-İrsaliye</option>
                                <option value="ESMM">e-SMM</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-bold text-dark mb-1">Fatura Yılı *</label>
                            <input type="number" class="form-control font-monospace" id="num_yil" name="yil" value="<?= date('Y') ?>" min="2020" max="2099" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-bold text-dark mb-1">Seri Öneki (3 Hane) *</label>
                            <input type="text" class="form-control font-monospace text-uppercase fw-bold" id="num_seri" name="seri" maxlength="3" placeholder="Örn: KK1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label font-size-12 fw-bold text-dark mb-1">Son Numara (Sayaç) *</label>
                            <input type="number" class="form-control font-monospace fw-bold" id="num_son_numara" name="son_numara" min="0" max="999999999" value="0" required>
                        </div>
                        <div class="col-12">
                            <div class="p-2 bg-light rounded border font-size-12 text-muted">
                                <span>Sıradaki kesilecek fatura numarası: </span>
                                <strong class="text-primary font-monospace" id="lblNumaratorPreview">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="btnSaveNumarator">
                        <i class="bx bx-check me-1"></i> Sayacı Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.select2').select2({
        dropdownAutoWidth: true,
        width: '100%',
        dropdownParent: $('#modalNumarator:visible').length ? $('#modalNumarator') : null
    });

    // Form API Ayarları Kaydet
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
    $('#btnTestConnection').on('click', async function() {
        Swal.fire({title: 'Kayıtlı EDM ayarları kontrol ediliyor', text: 'Lütfen bekleyin...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
        try {
            const result = await (await fetch('api/efatura-api.php?action=connection_info', {method: 'POST'})).json();
            if (result.status !== 'success') throw new Error(result.message);
            const c = result.data;
            
            let html = `
                <div class="card border border-success-subtle bg-success-subtle bg-opacity-10 mt-3 p-3 rounded-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bx bx-check-circle text-success fs-4 me-2"></i>
                        <h6 class="mb-0 fw-bold text-success">EDM Bağlantısı Başarılı</h6>
                    </div>
                    <div class="row g-2 font-size-13 mb-3">
                        <div class="col-md-6"><strong>Firma Ünvanı:</strong> ${c.UNVAN || '-'}</div>
                        <div class="col-md-6"><strong>VKN / TCKN:</strong> ${c.VKN || '-'}</div>
                        <div class="col-md-6"><strong>Gönderici Birim (GB):</strong> <span class="badge bg-secondary-subtle text-dark font-monospace">${c.GB || 'Tanımsız'}</span></div>
                        <div class="col-md-6"><strong>Posta Kutusu (PK):</strong> <span class="badge bg-secondary-subtle text-dark font-monospace">${c.PK || 'Tanımsız'}</span></div>
                        <div class="col-md-6"><strong>e-Fatura Durumu:</strong> ${c.EFATURA === 70 || c.EFATURA === '70' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'}</div>
                        <div class="col-md-6"><strong>e-Arşiv Durumu:</strong> ${c.EARSIV === 70 || c.EARSIV === '70' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Pasif</span>'}</div>
                    </div>
                </div>
            `;

            $('#connectionResult').html(html);
            Swal.fire('Bağlantı Başarılı', 'Kayıtlı EDM firma bilgileri başarıyla alındı.', 'success');
        } catch (e) { 
            $('#connectionResult').html(`<div class="alert alert-danger mt-3 mb-0 font-size-13"><i class="bx bx-error-circle me-1"></i>${e.message}</div>`);
            Swal.fire('Bağlantı Hatası', e.message, 'error'); 
        }
    });

    // Kontör Sorgulama
    async function checkCounter() {
        $('#topKontorBadge').html('<span class="spinner-border spinner-border-sm text-primary"></span>');
        try {
            const result = await (await fetch('api/efatura-api.php?action=counter_info', {method: 'POST'})).json();
            if (result.status !== 'success') throw new Error(result.message);
            const remaining = result.data.remaining;
            if (remaining !== null && remaining !== undefined) {
                const badgeClass = result.data.low ? 'text-danger' : 'text-success';
                $('#topKontorBadge').html(`<span class="${badgeClass} font-monospace">${remaining} Kontör</span>`);
            } else {
                $('#topKontorBadge').html('<span class="text-muted">Bilinmiyor</span>');
            }
        } catch (e) {
            $('#topKontorBadge').html('<span class="text-danger">Hata</span>');
        }
    }

    $('#btnCounter, #btnQuickCounter').on('click', checkCounter);

    // EDM Serilerini Senkronize Et
    $('#btnSyncEdmSerials').on('click', async function() {
        Swal.fire({
            title: 'EDM Serileri Çekiliyor',
            text: 'EDM portalindeki tüm seriler ve sayaçlar senkronize ediliyor...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const res = await (await fetch('api/efatura-api.php?action=sync_serials', {method: 'POST'})).json();
            if (res.status !== 'success') throw new Error(res.message);
            
            renderNumaratorTable(res.data.numarators);
            Swal.fire('Senkronizasyon Başarılı', res.message, 'success');
        } catch (e) {
            Swal.fire('Hata', e.message, 'error');
        }
    });

    let numDataTable = null;

    function initNumaratorTable() {
        if ($.fn.DataTable.isDataTable('#tblNumaratorler')) {
            $('#tblNumaratorler').DataTable().destroy();
        }
        let baseOptions = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
        let options = {
            ...baseOptions,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tümü"]],
            order: [[0, 'desc'], [1, 'asc'], [2, 'asc']],
            responsive: true,
            autoWidth: false
        };
        if (typeof applyLengthStateSave === 'function') {
            options = applyLengthStateSave(options);
        }
        numDataTable = $('#tblNumaratorler').DataTable(options);
    }

    // İlk yüklemede DataTables başlat
    initNumaratorTable();

    // Sekme değiştiğinde DataTables kolon genişliklerini yeniden hesapla
    $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'tab-seriler-btn') {
            setTimeout(function() {
                if (numDataTable) {
                    numDataTable.columns.adjust().responsive.recalc();
                } else {
                    initNumaratorTable();
                }
            }, 60);
        }
    });

    // Numaratör Tablosunu Çiz
    function renderNumaratorTable(list) {
        if ($.fn.DataTable.isDataTable('#tblNumaratorler')) {
            $('#tblNumaratorler').DataTable().destroy();
        }
        const tbody = $('#numaratorTableBody');
        tbody.empty();

        if (!list || list.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bx bx-info-circle fs-4 d-block mb-1 text-secondary"></i>
                        Kayıtlı sayaç bulunmuyor.
                    </td>
                </tr>
            `);
        } else {
            list.forEach(num => {
                const nextNo = parseInt(num.son_numara, 10) + 1;
                const padNext = String(nextNo).padStart(9, '0');
                const formattedNext = num.seri + String(num.yil) + padNext;
                const isEarsiv = num.belge_turu === 'EARSIV';

                tbody.append(`
                    <tr>
                        <td><span class="badge bg-light text-dark font-monospace fw-bold">${num.yil}</span></td>
                        <td>${isEarsiv ? '<span class="badge bg-primary-subtle text-primary">e-Arşiv</span>' : '<span class="badge bg-success-subtle text-success">e-Fatura</span>'}</td>
                        <td><strong class="font-monospace text-dark font-size-14">${num.seri}</strong></td>
                        <td><span class="font-monospace fw-bold text-dark font-size-13">${num.son_numara}</span></td>
                        <td><span class="badge bg-light text-primary font-monospace font-size-12 border border-primary-subtle">${formattedNext}</span></td>
                        <td><small class="text-muted">${num.updated_at || '-'}</small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-edit-numarator" 
                                data-type="${num.belge_turu}"
                                data-year="${num.yil}"
                                data-series="${num.seri}"
                                data-last="${num.son_numara}"
                                title="Sayacı Düzenle">
                                <i class="bx bx-edit"></i> Düzenle
                            </button>
                        </td>
                    </tr>
                `);
            });
        }

        initNumaratorTable();
        $('#kpiSeriSayisi').text(list ? list.length : 0);
    }

    // Modal Önizleme Hesaplama
    function updateModalNumaratorPreview() {
        const series = ($('#num_seri').val() || '').toUpperCase().trim();
        const year = $('#num_yil').val() || new Date().getFullYear();
        const lastNo = parseInt($('#num_son_numara').val(), 10) || 0;
        const nextNo = lastNo + 1;
        const formatted = series ? (series + String(year) + String(nextNo).padStart(9, '0')) : '-';
        $('#lblNumaratorPreview').text(formatted);
    }

    $('#num_seri, #num_yil, #num_son_numara').on('input change', updateModalNumaratorPreview);

    // Yeni Sayaç Modalı Aç
    $('#btnNewNumaratorModal').on('click', function() {
        $('#formNumaratorDuzenle')[0].reset();
        $('#num_yil').val(new Date().getFullYear());
        $('#num_son_numara').val(0);
        $('#num_seri').prop('readonly', false);
        $('#num_belge_turu').prop('disabled', false).trigger('change');
        $('#modalNumaratorLabel').html('<i class="bx bx-plus text-primary me-1"></i> Yeni Seri Sayacı Tanımla');
        updateModalNumaratorPreview();
        const modal = new bootstrap.Modal(document.getElementById('modalNumarator'));
        modal.show();
    });

    // Sayaç Düzenle Butonu
    $(document).on('click', '.btn-edit-numarator', function() {
        const btn = $(this);
        $('#num_belge_turu').val(btn.data('type')).trigger('change');
        $('#num_yil').val(btn.data('year'));
        $('#num_seri').val(btn.data('series')).prop('readonly', true);
        $('#num_son_numara').val(btn.data('last'));
        $('#modalNumaratorLabel').html('<i class="bx bx-edit text-primary me-1"></i> ' + btn.data('series') + ' Sayacını Düzenle');
        updateModalNumaratorPreview();
        const modal = new bootstrap.Modal(document.getElementById('modalNumarator'));
        modal.show();
    });

    // Sayaç Formunu Kaydet
    $('#formNumaratorDuzenle').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();

        Swal.showLoading();
        fetch('api/efatura-api.php?action=save_numarator', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('modalNumarator')).hide();
                renderNumaratorTable(res.data);
                Swal.fire('Kaydedildi', res.message, 'success');
            } else {
                Swal.fire('Hata', res.message, 'error');
            }
        });
    });
});
</script>
