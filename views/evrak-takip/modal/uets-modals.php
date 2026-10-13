<?php
use App\Helper\Form;
?>
<style>
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
    color: #5b73e8;
}
</style>

<!-- UETS / KEP Ayarları Modalı -->
<div class="modal fade" id="modalUetsAyarlar" tabindex="-1" aria-labelledby="modalUetsAyarlarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalUetsAyarlarTitle">
                    <i class="bx bx-envelope-open me-2 fs-5"></i> UETS / KEP Entegrasyon Ayarları
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formUetsAyarlar">
                    <div class="alert alert-info d-flex align-items-center mb-3 py-2 font-size-12">
                        <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0"></i>
                        <div>
                            UETS API anahtarı PTT UETS Yönetim Paneli (Müşteri Kartları &gt; API Ayarları) üzerinden oluşturulmalıdır.
                            Güvenliğiniz için API anahtarı ISO 27001 standardında AES-256-GCM ile şifrelenerek saklanır.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text',
                                'uets_adresi',
                                '',
                                'Örn: 25059-56257-37271',
                                'UETS / KEP Adresiniz *',
                                'bx bx-id-card',
                                'form-control font-monospace',
                                true
                            ); ?>
                            <small class="text-muted font-size-11 mt-1 d-block">Kurumsal UETS hesap numaranız (15 haneli kod)</small>
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
                                label: 'API Ortamı *',
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
                            <small class="text-muted font-size-11 mt-1 d-block">Entegrasyonun çalışacağı PTT sunucu adresi</small>
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

                        <div class="col-md-6 d-flex align-items-center mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" name="otomatik_senkronizasyon" id="uets_otomatik_sync" value="1" checked>
                                <label class="form-check-label fw-semibold" for="uets_otomatik_sync">Otomatik Senkronizasyon (Cron)</label>
                            </div>
                        </div>

                        <div class="col-md-6 text-md-end mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnUetsTestConnection">
                                <i class="bx bx-check-circle me-1"></i> Bağlantıyı Test Et
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info ms-1" id="btnUetsCheckBalance">
                                <i class="bx bx-wallet me-1"></i> Bakiye Sorgula
                            </button>
                        </div>
                    </div>

                    <div id="uetsTestResult" class="mt-3 d-none"></div>

                    <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
                        <div class="font-size-12 text-muted">
                            <span id="uetsSonSyncText">Son Senkronizasyon: Henüz yapılmadı</span>
                            <span class="ms-2" id="uetsBakiyeText"></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary px-4" id="btnSaveUetsSettings">
                                <i class="bx bx-save me-1"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </form>
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
