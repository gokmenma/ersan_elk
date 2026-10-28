<?php
use App\Helper\Form;
$canManagePttKep = !empty($canSendKep);
$pttKepHesapSecenekler = ['' => 'Seçilmedi'];
if ($canManagePttKep) {
    foreach ((new \App\Model\KepSettingsModel())->getAccountUsers((int) ($_SESSION['firma_id'] ?? 0)) as $hesapKullanici) {
        $pttKepHesapSecenekler[\App\Helper\Security::encrypt((int) $hesapKullanici->id)] = (string) $hesapKullanici->adi_soyadi;
    }
}
?>
<style>
/* ==========================================================================
   UETS VE PTT KEP ENTEGRASYON AYARLARI MODALİ - MODERN BENTO & SAAS UI
   ========================================================================== */
#modalUetsAyarlar .modal-dialog {
    max-width: 860px;
}

#modalUetsAyarlar .modal-content {
    border-radius: 20px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: #f8fafc;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.18), 0 0 0 1px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

#modalUetsAyarlar .modal-header {
    padding: 1.15rem 1.65rem;
    background: #ffffff;
    border-bottom: 1px solid #eef2f6;
}

#modalUetsAyarlar .uets-header-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 6px 16px -2px rgba(2, 132, 199, 0.35);
    flex-shrink: 0;
}

/* ==========================================================================
   SEKMELER (YÜKSEK OKUNURLUK & CONTRAST SEGMENTED CARDS)
   ========================================================================== */
#modalUetsAyarlar .uets-tabs-container {
    display: flex !important;
    gap: 12px;
    margin-bottom: 1.25rem;
    padding: 0;
    background: transparent;
    list-style: none;
    border: none !important;
}

#modalUetsAyarlar .uets-tabs-container .nav-item {
    flex: 1 1 0;
    margin: 0;
}

#modalUetsAyarlar .uets-tab-btn.nav-link {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 14px !important;
    border: 2px solid #e2e8f0 !important;
    background: #ffffff !important;
    color: #334155 !important;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    text-decoration: none !important;
}

#modalUetsAyarlar .uets-tab-btn.nav-link:hover:not(.active) {
    border-color: #cbd5e1 !important;
    background: #f1f5f9 !important;
    color: #1e293b !important;
    transform: translateY(-1px);
}

#modalUetsAyarlar .uets-tab-btn-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

#modalUetsAyarlar .uets-tab-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #f1f5f9;
    color: #0284c7;
    display: grid;
    place-items: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

#modalUetsAyarlar .uets-tab-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
    margin-bottom: 2px;
}

#modalUetsAyarlar .uets-tab-desc {
    font-size: 11px;
    color: #64748b;
    line-height: 1.2;
}

#modalUetsAyarlar .uets-tab-badge {
    font-size: 10.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
    transition: all 0.2s ease;
}

/* AKTİF SEKME: YÜKSEK KONTRAST MAVİ GRADYAN - ASLA YAZI KAYBOLMAZ */
#modalUetsAyarlar .uets-tab-btn.nav-link.active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    border-color: #0284c7 !important;
    color: #ffffff !important;
    box-shadow: 0 8px 20px -2px rgba(2, 132, 199, 0.35) !important;
    transform: translateY(-1px);
}

#modalUetsAyarlar .uets-tab-btn.nav-link.active .uets-tab-icon {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

#modalUetsAyarlar .uets-tab-btn.nav-link.active .uets-tab-title {
    color: #ffffff !important;
}

#modalUetsAyarlar .uets-tab-btn.nav-link.active .uets-tab-desc {
    color: rgba(255, 255, 255, 0.85) !important;
}

#modalUetsAyarlar .uets-tab-btn.nav-link.active .uets-tab-badge {
    background: rgba(255, 255, 255, 0.25) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.35) !important;
}

/* ==========================================================================
   BENTO BÖLÜM KARTLARI
   ========================================================================== */
#modalUetsAyarlar .uets-bento-card {
    background: #ffffff;
    border: 1px solid #e5eaf2;
    border-radius: 16px;
    padding: 1.25rem 1.35rem;
    margin-bottom: 1rem;
    box-shadow: 0 3px 12px rgba(15, 23, 42, 0.03);
}

#modalUetsAyarlar .uets-bento-card-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}

#modalUetsAyarlar .uets-bento-card-title i {
    color: #0284c7;
    font-size: 17px;
    margin-right: 6px;
}

/* Şifre Alanı Toggle */
#modalUetsAyarlar .password-field-wrapper {
    position: relative;
}
#modalUetsAyarlar .btn-toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: #74788d;
    padding: 4px;
    cursor: pointer;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}
#modalUetsAyarlar .btn-toggle-password:hover {
    color: #0284c7;
}

/* Alt Çubuk (Sabit Modal Footer) */
#modalUetsAyarlar .modal-footer {
    position: sticky;
    bottom: 0;
    z-index: 10;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(14px);
    border-top: 1px solid #eef2f6;
    padding: 0.85rem 1.65rem;
    box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.05);
}

/* ==========================================================================
   DARK MODE / MACOS DARK DESTEĞİ
   ========================================================================== */
html[data-theme-preset="macos-dark"] #modalUetsAyarlar .modal-content,
[data-bs-theme="dark"] #modalUetsAyarlar .modal-content,
body[data-layout-mode="dark"] #modalUetsAyarlar .modal-content {
    background: #141720 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #e2e8f0 !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .modal-header,
[data-bs-theme="dark"] #modalUetsAyarlar .modal-header,
body[data-layout-mode="dark"] #modalUetsAyarlar .modal-header {
    background: #1a1e29 !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .modal-footer,
[data-bs-theme="dark"] #modalUetsAyarlar .modal-footer,
body[data-layout-mode="dark"] #modalUetsAyarlar .modal-footer {
    background: #1a1e29 !important;
    border-top-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .modal-title,
[data-bs-theme="dark"] #modalUetsAyarlar .modal-title,
body[data-layout-mode="dark"] #modalUetsAyarlar .modal-title {
    color: #f1f5f9 !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-tab-btn.nav-link,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link {
    background: #1a1e29 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #94a3b8 !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-tab-btn.nav-link:hover:not(.active),
[data-bs-theme="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link:hover:not(.active),
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link:hover:not(.active) {
    background: #202635 !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #f1f5f9 !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-tab-btn.nav-link .uets-tab-title,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link .uets-tab-title,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link .uets-tab-title {
    color: #f1f5f9 !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-tab-btn.nav-link.active,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link.active,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-tab-btn.nav-link.active {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    border-color: #0284c7 !important;
    color: #ffffff !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-bento-card,
html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-footer-bar,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-bento-card,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-footer-bar,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-bento-card,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-footer-bar {
    background: #1a1e29 !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
}

html[data-theme-preset="macos-dark"] #modalUetsAyarlar .uets-bento-card-title,
[data-bs-theme="dark"] #modalUetsAyarlar .uets-bento-card-title,
body[data-layout-mode="dark"] #modalUetsAyarlar .uets-bento-card-title {
    color: #f1f5f9 !important;
    border-bottom-color: rgba(255, 255, 255, 0.08) !important;
}

@media (max-width: 575.98px) {
    #modalUetsAyarlar .uets-tabs-container { flex-direction: column; }
}
</style>

<!-- UETS / KEP Ayarları Modalı -->
<div class="modal fade" id="modalUetsAyarlar" tabindex="-1" aria-labelledby="modalUetsAyarlarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <!-- Modal Header -->
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="uets-header-icon-box">
                        <i class="bx bx-slider-alt"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title font-size-15 fw-bold mb-0 text-dark" id="modalUetsAyarlarTitle">
                                UETS ve PTT KEP Entegrasyon Ayarları
                            </h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-10 px-2 py-0.5">PTT & Adalet</span>
                        </div>
                        <div class="small text-muted mt-1">Resmî elektronik tebligat ve kayıtlı elektronik posta servis yapılandırması</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-3 p-md-4">
                <?php if ($canManagePttKep): ?>
                <!-- YÜKSEK OKUNURLUKLU MODERN SEKME KARTLARI -->
                <ul class="nav uets-tabs-container mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link uets-tab-btn active" data-bs-toggle="tab" data-bs-target="#uetsSettingsPane" type="button" role="tab" aria-controls="uetsSettingsPane" aria-selected="true">
                            <div class="uets-tab-btn-content">
                                <div class="uets-tab-icon">
                                    <i class="bx bx-envelope-open"></i>
                                </div>
                                <div>
                                    <div class="uets-tab-title">UETS / E-Tebligat</div>
                                    <div class="uets-tab-desc">Ulusal Elektronik Tebligat Sistemi</div>
                                </div>
                            </div>
                            <span class="uets-tab-badge">Resmî Tebligat</span>
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button class="nav-link uets-tab-btn" data-bs-toggle="tab" data-bs-target="#pttKepSettingsPane" type="button" role="tab" aria-controls="pttKepSettingsPane" aria-selected="false">
                            <div class="uets-tab-btn-content">
                                <div class="uets-tab-icon">
                                    <i class="bx bx-paper-plane"></i>
                                </div>
                                <div>
                                    <div class="uets-tab-title">PTT KEP</div>
                                    <div class="uets-tab-desc">Kayıtlı Elektronik Posta &amp; EYP</div>
                                </div>
                            </div>
                            <span class="uets-tab-badge">Güvenli İleti</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                <!-- 1. UETS AYARLARI SEKMESİ -->
                <div class="tab-pane fade show active" id="uetsSettingsPane" role="tabpanel">
                <?php endif; ?>

                <form id="formUetsAyarlar">
                    <!-- Kart 1: API ve Hesap Parametreleri -->
                    <div class="uets-bento-card">
                        <div class="uets-bento-card-title">
                            <div><i class="bx bx-key"></i> UETS API ve Hesap Parametreleri</div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle font-size-10 px-2 py-0.5 rounded-pill">
                                <i class="bx bx-shield-check me-1"></i>ISO 27001 AES-256-GCM
                            </span>
                        </div>

                        <div class="alert alert-info border-info-subtle py-2 px-3 font-size-12 mb-3 rounded-3 d-flex align-items-center">
                            <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0 text-info"></i>
                            <div>
                                UETS API anahtarı PTT UETS Yönetim Paneli <strong>(Müşteri Kartları &gt; API Ayarları)</strong> üzerinden üretilmelidir.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <?php echo Form::FormFloatInput(
                                    'text',
                                    'uets_adresi',
                                    '',
                                    'Örn: 25059-56257-37271',
                                    'Kurumsal UETS Adresiniz (15 Haneli) *',
                                    'bx bx-id-card',
                                    'form-control font-monospace',
                                    true
                                ); ?>
                                <small class="text-muted font-size-11 mt-1 d-block">Kurumsal UETS hesap numaranız (tireli veya bitişik 15 haneli kod)</small>
                            </div>

                            <div class="col-md-6">
                                <?php
                                $envOptions = [
                                    'prod' => 'Canlı Ortam (api.etebligat.gov.tr)',
                                    'test' => 'Test / Ön Üretim (apipp.etebligat.gov.tr)',
                                ];
                                echo Form::FormSelect2(
                                    name: 'api_environment',
                                    options: $envOptions,
                                    selectedValue: 'prod',
                                    label: 'PTT API Sunucu Ortamı *',
                                    icon: 'bx bx-globe',
                                    valueField: 'key',
                                    textField: '',
                                    class: 'form-select select2',
                                    required: true,
                                    style: 'width:100%',
                                    attributes: '',
                                    id: 'api_environment'
                                );
                                ?>
                                <small class="text-muted font-size-11 mt-1 d-block">Entegrasyonun çalışacağı resmî PTT sunucu adresi</small>
                            </div>

                            <div class="col-12">
                                <div class="password-field-wrapper position-relative">
                                    <?php echo Form::FormFloatInput(
                                        'password',
                                        'api_key',
                                        '',
                                        'API anahtarı giriniz (değiştirmeyecekseniz boş bırakın)',
                                        'Statik API Anahtarı (Token) *',
                                        'bx bx-key',
                                        'form-control font-monospace pe-5',
                                        false,
                                        null,
                                        'new-password'
                                    ); ?>
                                    <button type="button" class="btn-toggle-password" id="btnToggleApiKeyVisibility" tabindex="-1" title="Göster / Gizle">
                                        <i class="bx bx-show font-size-18"></i>
                                    </button>
                                </div>
                                <small class="text-muted font-size-11 mt-1 d-block" id="apiKeyHelpText">Mevcut anahtar kayıtlı.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Kart 2: Senkronizasyon & Bağlantı Testi -->
                    <div class="uets-bento-card">
                        <div class="uets-bento-card-title">
                            <div><i class="bx bx-refresh"></i> Otomasyon & Canlı Servis Testi</div>
                        </div>

                        <div class="row align-items-center g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
                                    <input class="form-check-input ms-0 me-2" style="width:2.2rem;height:1.2rem;cursor:pointer" type="checkbox" role="switch" name="otomatik_senkronizasyon" id="uets_otomatik_sync" value="1" checked>
                                    <div>
                                        <label class="form-check-label fw-bold text-dark font-size-13 mb-0" for="uets_otomatik_sync" style="cursor:pointer">Otomatik Senkronizasyon (Cron)</label>
                                        <small class="text-muted d-block font-size-11">Her 15 dakikada bir yeni tebligatları otomatik sorgular</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 text-md-end">
                                <button type="button" class="btn btn-sm btn-outline-primary px-3" id="btnUetsTestConnection">
                                    <i class="bx bx-check-circle me-1"></i> Bağlantıyı Test Et
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info ms-2 px-3" id="btnUetsCheckBalance">
                                    <i class="bx bx-wallet me-1"></i> Bakiye Sorgula
                                </button>
                            </div>
                        </div>

                        <div id="uetsTestResult" class="mt-3 d-none"></div>
                    </div>
                </form>

                <?php if ($canManagePttKep): ?>
                </div>

                <!-- 2. PTT KEP AYARLARI SEKMESİ -->
                <div class="tab-pane fade" id="pttKepSettingsPane" role="tabpanel">
                    <form id="formPttKepAyarlar">
                        <!-- Kart 1: Kurumsal KEP ve Kimlik -->
                        <div class="uets-bento-card">
                            <div class="uets-bento-card-title">
                                <div><i class="bx bx-at"></i> Kurumsal KEP ve Yetkili Kimliği</div>
                                <span class="badge bg-info-subtle text-info border border-info-subtle font-size-10 px-2 py-0.5 rounded-pill">
                                    PTT KEP Web Servis
                                </span>
                            </div>

                            <div class="alert alert-info border-info-subtle py-2 px-3 font-size-12 mb-3 rounded-3 d-flex align-items-center">
                                <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0 text-info"></i>
                                <div>
                                    Kurumsal KEP adresi ortak kullanılabilir. TCKN, parola ve web servis şifresi yalnızca oturum açmış kullanıcıya özel şifreli saklanır.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <?php echo Form::FormFloatInput('text', 'ptt_kep_adresi', '', 'Örn: sirket@hs01.kep.tr', 'Kurumsal KEP Adresiniz *', 'bx bx-at', 'form-control font-monospace', true); ?>
                                    <small class="text-muted font-size-11 mt-1 d-block">PTT'den alınan kurumsal KEP adresiniz</small>
                                </div>
                                <div class="col-md-6">
                                    <?php echo Form::FormFloatInput('text', 'ptt_yetkili_tcno', '', 'Değiştirmek için 11 haneli TCKN girin', 'Yetkili T.C. Kimlik No', 'bx bx-id-card', 'form-control font-monospace', false, null, 'off', false, '', false, 11); ?>
                                    <small class="text-muted font-size-11 mt-1 d-block" id="pttKepTcHelpText">Bu kullanıcıya ait kayıtlı bilgi değiştirilmeyecekse boş bırakın.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Kart 2: Giriş Bilgileri ve Web Servis -->
                        <div class="uets-bento-card">
                            <div class="uets-bento-card-title">
                                <div><i class="bx bx-lock-alt"></i> Giriş Parolaları &amp; Servis Yapılandırması</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="password-field-wrapper position-relative">
                                        <?php echo Form::FormFloatInput('password', 'ptt_parola', '', 'Değiştirmek için yeni parolayı girin', 'KEP Web Giriş Parolası', 'bx bx-lock-alt', 'form-control font-monospace pe-5', false, null, 'new-password'); ?>
                                        <button type="button" class="btn-toggle-password" data-ptt-password-target="ptt_parola" tabindex="-1" title="Göster / Gizle"><i class="bx bx-show font-size-18"></i></button>
                                    </div>
                                    <small class="text-muted font-size-11 mt-1 d-block" id="pttKepParolaHelpText">Bu kullanıcıya ait kayıtlı parola değiştirilmeyecekse boş bırakın.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="password-field-wrapper position-relative">
                                        <?php echo Form::FormFloatInput('password', 'ptt_sifre', '', 'Değiştirmek için yeni şifreyi girin', 'Web Servis (API) Şifresi', 'bx bx-key', 'form-control font-monospace pe-5', false, null, 'new-password'); ?>
                                        <button type="button" class="btn-toggle-password" data-ptt-password-target="ptt_sifre" tabindex="-1" title="Göster / Gizle"><i class="bx bx-show font-size-18"></i></button>
                                    </div>
                                    <small class="text-muted font-size-11 mt-1 d-block" id="pttKepSifreHelpText">Bu kullanıcıya ait kayıtlı şifre değiştirilmeyecekse boş bırakın.</small>
                                </div>
                                <div class="col-md-8">
                                    <?php echo Form::FormFloatInput('text', 'ptt_wsdl_url', 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl', 'WSDL bağlantı adresi', 'WSDL Web Servis Adresi', 'bx bx-link', 'form-control font-monospace font-size-12'); ?>
                                </div>
                                <div class="col-md-4">
                                    <?php echo Form::FormSelect2(name: 'ptt_ortam', options: ['prod' => 'Canlı Ortam', 'test' => 'Test Ortamı'], selectedValue: 'prod', label: 'Servis Ortamı', icon: 'bx bx-globe', valueField: 'key', textField: '', class: 'form-select select2', required: true, style: 'width:100%', attributes: '', id: 'ptt_ortam'); ?>
                                </div>
                                <div class="col-12">
                                    <?php echo Form::FormSelect2(name: 'ptt_varsayilan_hesap', options: $pttKepHesapSecenekler, selectedValue: '', label: 'Varsayılan KEP gönderim hesabı', icon: 'bx bx-user', valueField: 'key', textField: '', class: 'form-select select2', required: false, style: 'width:100%', attributes: '', id: 'ptt_varsayilan_hesap'); ?>
                                    <small class="text-muted font-size-11 mt-1 d-block">Kendi KEP bilgisi tanımlı olmayan personelin evrakları bu hesapla gönderilir.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Kart 3: Gelen KEP ve Otomasyon -->
                        <div class="uets-bento-card">
                            <div class="uets-bento-card-title">
                                <div><i class="bx bx-envelope-open"></i> Gelen KEP Otomasyonu</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="ptt_gelen_okundu_otomatik">
                                        <label class="form-check-label font-size-12 fw-semibold" for="ptt_gelen_okundu_otomatik">Okundu (alındı) bilgisini otomatik gönder</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="ptt_gelen_otomatik_aktar">
                                        <label class="form-check-label font-size-12 fw-semibold" for="ptt_gelen_otomatik_aktar">Gelen KEP'leri otomatik sisteme aktar (15 dk)</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-2 bg-light rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2 font-size-12">
                                        <span><i class="bx bx-user me-1 text-primary"></i>Otomatik aktarım hesabı: <strong id="pttKepGelenHesap">-</strong></span>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnPttKepGelenHesapBana"><i class="bx bx-user-check me-1"></i> Hesabı ben olarak ayarla</button>
                                    </div>
                                    <small class="text-muted font-size-11 d-block mt-1" id="pttKepGelenHelpText">Otomatik aktarım bu kullanıcının bilgileriyle çalışır.</small>
                                </div>
                                <div class="col-12 text-end mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary px-3" id="btnPttKepTestConnection"><i class="bx bx-check-circle me-1"></i> Bağlantıyı ve Girişi Test Et</button>
                                    <button type="button" class="btn btn-sm btn-outline-info ms-2 px-3" id="btnPttKepCheckBalance"><i class="bx bx-wallet me-1"></i> Bakiye / Kontör Sorgula</button>
                                </div>
                            </div>

                            <div id="pttKepTestResult" class="mt-3 d-none"></div>
                        </div>
                    </form>
                </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- SABİT MODAL FOOTER (HER ZAMAN ALTA YAPIŞIK) -->
            <div class="modal-footer">
                <!-- UETS Sekmesi Aktifken Görünen Alt Çubuk -->
                <div id="uetsFooterBar" class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="font-size-12 text-muted">
                        <span id="uetsSonSyncText"><i class="bx bx-time-five me-1"></i>Son Senkronizasyon: Henüz yapılmadı</span>
                        <span class="ms-3 badge bg-light text-dark border" id="uetsBakiyeText"></span>
                    </div>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" form="formUetsAyarlar" class="btn btn-primary px-4 shadow-sm" id="btnSaveUetsSettings">
                            <i class="bx bx-save me-1"></i> Ayarları Kaydet
                        </button>
                    </div>
                </div>

                <!-- PTT KEP Sekmesi Aktifken Görünen Alt Çubuk -->
                <div id="pttKepFooterBar" class="d-none align-items-center justify-content-between w-100 flex-wrap gap-2">
                    <div class="font-size-12 text-muted">
                        <span id="pttKepBakiyeText"><i class="bx bx-wallet me-1"></i>Kayıtlı Kontör: -</span>
                        <span class="ms-2" id="pttKepSonSorguText"></span>
                    </div>
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" form="formPttKepAyarlar" class="btn btn-primary px-4 shadow-sm" id="btnSavePttKepSettings">
                            <i class="bx bx-save me-1"></i> KEP Ayarlarını Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $(document).on('shown.bs.tab', '#modalUetsAyarlar button[data-bs-toggle="tab"]', function (e) {
        var target = $(this).attr('data-bs-target');
        if (target === '#pttKepSettingsPane') {
            $('#uetsFooterBar').removeClass('d-flex').addClass('d-none');
            $('#pttKepFooterBar').removeClass('d-none').addClass('d-flex');
        } else {
            $('#pttKepFooterBar').removeClass('d-flex').addClass('d-none');
            $('#uetsFooterBar').removeClass('d-none').addClass('d-flex');
        }
    });

    $('#modalUetsAyarlar').on('show.bs.modal', function() {
        $('#pttKepFooterBar').removeClass('d-flex').addClass('d-none');
        $('#uetsFooterBar').removeClass('d-none').addClass('d-flex');
    });

    // Form dışında submit garantisi
    $(document).on('click', '#btnSaveUetsSettings', function (e) {
        $('#formUetsAyarlar').submit();
    });
    $(document).on('click', '#btnSavePttKepSettings', function (e) {
        $('#formPttKepAyarlar').submit();
    });
});
</script>

<!-- UETS Gelen Evrak Seçim Modalı -->
<div class="modal fade" id="modalUetsGelenSecim" tabindex="-1" aria-labelledby="modalUetsGelenSecimTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalUetsGelenSecimTitle">
                    <i class="bx bx-envelope-open me-2 fs-5"></i> UETS Gelen Evrakları
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0">
                <div class="alert alert-info rounded-0 border-0 mb-0 font-size-12 d-flex align-items-start">
                    <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0"></i>
                    <span>Yalnızca seçtiğiniz evraklar sisteme aktarılır. Daha önce aktarılanlar seçilemez; manuel kayıtla eşleşebilecek evraklar sarı uyarıyla gösterilir.</span>
                </div>
                <div class="alert alert-warning rounded-0 border-0 mb-0 font-size-12 d-none" id="uetsFetchWarning"></div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="uetsGelenSecimTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:48px"><input type="checkbox" class="form-check-input" id="uetsSelectAll" aria-label="Tüm uygun evrakları seç"></th>
                                <th style="width:110px">Tarih</th>
                                <th>Konu</th>
                                <th style="width:240px">Gönderen</th>
                                <th style="width:170px">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="uetsGelenSecimBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="text-muted font-size-12" id="uetsSelectionSummary">0 evrak seçildi</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn btn-primary" id="btnUetsSelectedImport" disabled>
                        <i class="bx bx-import me-1"></i> Seçilenleri Aktar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- KEP Gelen Kutusu Seçim Modalı -->
<div class="modal fade" id="modalKepGelenSecim" tabindex="-1" aria-labelledby="modalKepGelenSecimTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalKepGelenSecimTitle">
                    <i class="bx bx-mail-send me-2 fs-5"></i> KEP Gelen Kutusu
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0">
                <div class="alert alert-info rounded-0 border-0 mb-0 font-size-12 d-flex align-items-start">
                    <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0"></i>
                    <span>Son 30 günde KEP adresinize gelen iletiler listelenir. Yalnızca seçtikleriniz Gelen Evrak olarak aktarılır; daha önce aktarılanlar seçilemez.</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="kepGelenSecimTable">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:48px"><input type="checkbox" class="form-check-input" id="kepSelectAll" aria-label="Tüm uygun iletileri seç"></th>
                                <th>Konu</th>
                                <th style="width:280px">Gönderen</th>
                                <th style="width:140px">Tür</th>
                                <th style="width:150px">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="kepGelenSecimBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted font-size-12" id="kepSelectionSummary">0 ileti seçildi</span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="kepAlindiOnay">
                        <label class="form-check-label font-size-12" for="kepAlindiOnay" title="Gönderene alındı bilgisi iletilir">Aktarırken alındı onayı gönder</label>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn btn-primary" id="btnKepSelectedImport" disabled>
                        <i class="bx bx-import me-1"></i> Seçilenleri Aktar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- UETS Delil ve İspat Belgeleri Modalı -->
<div class="modal fade" id="modalUetsDeliller" tabindex="-1" aria-labelledby="modalUetsDelillerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalUetsDelillerTitle">
                    <i class="bx bx-shield-quarter me-2 fs-5 text-warning"></i> UETS Tebligat İspat Delilleri
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 mb-3 border">
                    <div class="row g-2 font-size-13">
                        <div class="col-md-6"><strong>Tebligat ID:</strong> <span id="delilModalUetsId" class="text-muted font-monospace"></span></div>
                        <div class="col-md-6"><strong>Barkod / Ref:</strong> <span id="delilModalBarcode" class="text-muted font-monospace"></span></div>
                        <div class="col-md-6"><strong>Gönderen:</strong> <span id="delilModalSender" class="text-dark"></span></div>
                        <div class="col-md-6"><strong>Alıcı:</strong> <span id="delilModalRecipient" class="text-dark"></span></div>
                        <div class="col-12"><strong>Konu:</strong> <span id="delilModalSubject" class="text-primary fw-semibold"></span></div>
                    </div>
                </div>

                <h6 class="fw-bold font-size-14 text-dark mb-3"><i class="bx bx-history me-1 text-primary"></i> Delil Olay Akışı (PTT Kayıtları)</h6>
                <div id="delilTimelineContainer" class="position-relative ps-2">
                    <div class="text-center py-4 text-muted"><i class="bx bx-loader-alt bx-spin fs-4"></i> Deliller yükleniyor...</div>
                </div>

                <div class="border-top pt-3 mt-4 d-flex justify-content-between align-items-center">
                    <a href="#" id="btnDownloadEypPackage" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="bx bx-archive me-1"></i> Orijinal EYP Paketini İndir
                    </a>
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUetsSevk" tabindex="-1" aria-labelledby="modalUetsSevkTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalUetsSevkTitle">
                    <i class="bx bx-share me-2 fs-5 text-warning"></i> UETS Evrakını Sevk Et
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="uetsSevkEvrakId" value="">
                <div class="bg-light p-3 rounded-3 mb-3 border font-size-13">
                    <strong>Evrak:</strong> <span id="uetsSevkKonu" class="text-primary fw-semibold"></span>
                </div>
                <div class="mb-3">
                    <?php echo Form::FormSelect2(name: 'uets_sevk_alan', options: [], selectedValue: '', label: 'Sevk Edilecek Personel', icon: 'bx bx-user', valueField: 'key', textField: '', class: 'form-select select2', required: true, style: 'width:100%', attributes: '', id: 'uets_sevk_alan'); ?>
                </div>
                <div class="mb-1">
                    <?php echo Form::FormFloatTextarea('uets_sevk_notu', '', 'Sevk notu', 'Sevk Notu (isteğe bağlı)', 'bx bx-comment-detail', 'form-control', false, '90px', 3, 'maxlength="500"'); ?>
                </div>
                <div class="font-size-12 text-muted mt-2">Sevk edilen personel evrakı görüntüleyebilir ve başka bir personele iletebilir. Tüm işlemler tarihçeye kaydedilir.</div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-warning text-white" id="btnUetsSevkKaydet"><i class="bx bx-share me-1"></i> Sevk Et</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUetsTarihce" tabindex="-1" aria-labelledby="modalUetsTarihceTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalUetsTarihceTitle">
                    <i class="bx bx-history me-2 fs-5 text-warning"></i> Sevk ve Erişim Tarihçesi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 mb-3 border font-size-13">
                    <strong>Evrak:</strong> <span id="uetsTarihceKonu" class="text-primary fw-semibold"></span>
                </div>
                <h6 class="font-size-13 fw-bold mb-2">Sevk Zinciri</h6>
                <div id="uetsSevkZinciri" class="mb-4"></div>
                <div id="uetsDenetimBlok">
                    <h6 class="font-size-13 fw-bold mb-2">Erişim ve İşlem Kayıtları</h6>
                    <div id="uetsDenetimIzi"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOnayTarihce" tabindex="-1" aria-labelledby="modalOnayTarihceTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalOnayTarihceTitle">
                    <i class="bx bx-history me-2 fs-5 text-warning"></i> Onay Tarihçesi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 mb-3 border font-size-13">
                    <strong>Evrak:</strong> <span id="onayTarihceKonu" class="text-primary fw-semibold"></span>
                </div>
                <div id="onayTarihceListe"></div>
            </div>
        </div>
    </div>
</div>
