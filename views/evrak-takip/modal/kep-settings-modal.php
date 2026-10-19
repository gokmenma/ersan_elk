<?php
use App\Helper\Form;
?>
<style>
#modalKepAyarlar .password-field-wrapper {
    position: relative;
}
#modalKepAyarlar .btn-toggle-password {
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
#modalKepAyarlar .btn-toggle-password:hover {
    color: #5b73e8;
}
</style>

<!-- PTT KEP Firma Entegrasyon Ayarları Modalı -->
<div class="modal fade" id="modalKepAyarlar" tabindex="-1" aria-labelledby="modalKepAyarlarTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalKepAyarlarTitle">
                    <i class="bx bx-mail-send me-2 fs-5"></i> PTT KEP Entegrasyon Ayarları
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formKepAyarlar">
                    <div class="alert alert-info d-flex align-items-center mb-3 py-2 font-size-12">
                        <i class="bx bx-info-circle fs-4 me-2 flex-shrink-0"></i>
                        <div>
                            PTT KEP Web Servis erişim bilgileri PTTKEP başvurusunda yetkilendirilen hesap için geçerlidir.
                            Güvenliğiniz için T.C. Kimlik No, Parola ve Şifre ISO 27001 standardında AES-256-GCM ile şifrelenerek saklanır.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text',
                                'kep_adresi',
                                '',
                                'Örn: sirket@hs01.kep.tr',
                                'Kurumsal KEP Adresiniz *',
                                'bx bx-at',
                                'form-control font-monospace',
                                true
                            ); ?>
                            <small class="text-muted font-size-11 mt-1 d-block">PTT'den alınan kurumsal KEP e-posta adresiniz</small>
                        </div>

                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text',
                                'yetkili_tcno',
                                '',
                                '11 Haneli T.C. Kimlik No',
                                'Yetkili T.C. Kimlik No *',
                                'bx bx-id-card',
                                'form-control font-monospace',
                                false,
                                null,
                                'off',
                                false,
                                '',
                                false,
                                11
                            ); ?>
                            <small class="text-muted font-size-11 mt-1 d-block" id="kepTcHelpText">Hesaba tanımlı yetkilinin TCKN'si</small>
                        </div>

                        <div class="col-md-6">
                            <div class="password-field-wrapper position-relative">
                                <?php echo Form::FormFloatInput(
                                    'password',
                                    'parola',
                                    '',
                                    'PTT KEP Giriş Parolası',
                                    'KEP Parolası *',
                                    'bx bx-lock-alt',
                                    'form-control font-monospace pe-5',
                                    false,
                                    null,
                                    'new-password'
                                ); ?>
                                <button type="button" class="btn-toggle-password" data-target="parola" tabindex="-1" title="Göster / Gizle">
                                    <i class="bx bx-show font-size-18"></i>
                                </button>
                            </div>
                            <small class="text-muted font-size-11 mt-1 d-block" id="kepParolaHelpText">Mevcut parola kayıtlıysa boş bırakabilirsiniz.</small>
                        </div>

                        <div class="col-md-6">
                            <div class="password-field-wrapper position-relative">
                                <?php echo Form::FormFloatInput(
                                    'password',
                                    'sifre',
                                    '',
                                    'PTT KEP Web Servis Şifresi',
                                    'Web Servis Şifresi *',
                                    'bx bx-key',
                                    'form-control font-monospace pe-5',
                                    false,
                                    null,
                                    'new-password'
                                ); ?>
                                <button type="button" class="btn-toggle-password" data-target="sifre" tabindex="-1" title="Göster / Gizle">
                                    <i class="bx bx-show font-size-18"></i>
                                </button>
                            </div>
                            <small class="text-muted font-size-11 mt-1 d-block" id="kepSifreHelpText">Mevcut şifre kayıtlıysa boş bırakabilirsiniz.</small>
                        </div>

                        <div class="col-md-8">
                            <?php echo Form::FormFloatInput(
                                'text',
                                'wsdl_url',
                                'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl',
                                'WSDL Bağlantı Adresi',
                                'WSDL Web Servis Adresi',
                                'bx bx-link',
                                'form-control font-monospace font-size-12'
                            ); ?>
                        </div>

                        <div class="col-md-4">
                            <?php
                            $envOptions = [
                                'prod' => 'Canlı Ortam (eyazisma.hs01.kep.tr)',
                                'test' => 'Test Ortamı (testkep.pttkep.gov.tr)',
                            ];
                            echo Form::FormSelect2(
                                name: 'ortam',
                                options: $envOptions,
                                selectedValue: 'prod',
                                label: 'Servis Ortamı',
                                icon: 'bx bx-globe',
                                valueField: 'key',
                                textField: '',
                                class: 'form-select select2',
                                required: true,
                                style: 'width:100%',
                                attributes: '',
                                id: 'kep_ortam'
                            );
                            ?>
                        </div>

                        <div class="col-12 text-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnKepTestConnection">
                                <i class="bx bx-check-circle me-1"></i> Bağlantıyı ve Girişi Test Et
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info ms-1" id="btnKepCheckBalance">
                                <i class="bx bx-wallet me-1"></i> Bakiye / Kontör Sorgula
                            </button>
                        </div>
                    </div>

                    <div id="kepTestResult" class="mt-3 d-none"></div>

                    <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
                        <div class="font-size-12 text-muted">
                            <span id="kepBakiyeText">Kayıtlı Kontör: -</span>
                            <span class="ms-2" id="kepSonSorguText"></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="submit" class="btn btn-primary px-4" id="btnSaveKepSettings">
                                <i class="bx bx-save me-1"></i> Ayarları Kaydet
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
