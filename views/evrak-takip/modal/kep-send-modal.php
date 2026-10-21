<?php $kepSettings = (new \App\Model\KepSettingsModel())->getSettings((int) ($_SESSION['firma_id'] ?? 0)); ?>
<style>
#modalKepGonderisi .modal-dialog{height:calc(100vh - 1rem);max-height:calc(100vh - 1rem);margin:.5rem auto}
#modalKepGonderisi .modal-content{height:100%;max-height:100%;overflow:hidden;border-radius:18px}
#modalKepGonderisi #formKepGonderisi{display:flex;flex-direction:column;min-height:0;height:100%}
#modalKepGonderisi .modal-header{flex:0 0 auto;padding:1rem 1.5rem;background:#fff}
#modalKepGonderisi .modal-footer{flex:0 0 auto;padding:.85rem 1.5rem;background:#fff;box-shadow:0 -8px 24px rgba(31,41,55,.05)}
#modalKepGonderisi .modal-body{flex:1 1 auto;min-height:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable;background:#fff;padding:1.25rem 1.5rem!important}
#modalKepGonderisi .kep-header-account{margin-left:auto;margin-right:1rem;text-align:right;line-height:1.2}
#modalKepGonderisi .kep-header-account strong{font-size:12px}#modalKepGonderisi .kep-header-account .badge{font-size:10px}
#modalKepGonderisi .kep-compose-card{padding:.2rem 0 1.1rem;margin-bottom:1.1rem;border-bottom:1px solid #edf0f4}
#modalKepGonderisi .kep-compose-card:last-of-type{border-bottom:0;margin-bottom:0}
#modalKepGonderisi .kep-section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:.8rem}
#modalKepGonderisi .kep-section-title strong{font-size:13px;color:#26364d}#modalKepGonderisi .kep-section-title small{font-size:11px;color:#8a96a8}
#modalKepGonderisi .form-floating-custom,#modalKepGonderisi .form-floating-select2{margin-bottom:0}
#modalKepGonderisi .note-editor{margin-bottom:0;border-color:#dfe6ef!important;border-radius:10px;overflow:hidden}
#modalKepGonderisi .note-editable{min-height:135px!important;max-height:185px;overflow-y:auto;background:#fff}
#modalKepGonderisi #modalKepDogrulamaSonuc:empty{display:none}
#modalKepGonderisi #modalKepDogrulamaSonuc{padding:.55rem .7rem;margin:.65rem 0 0!important;background:#f8fafc;border-radius:8px}
#modalKepGonderisi #btnModalKepGonder{min-width:170px;border-radius:9px;box-shadow:0 6px 14px rgba(52,195,143,.2)}
#modalKepGonderisi .kep-type-switch{display:flex;gap:6px;padding:4px;background:#f4f6f9;border:1px solid #e1e7ef;border-radius:10px;height:58px}
#modalKepGonderisi .kep-type-switch .btn{display:flex;align-items:center;justify-content:center;flex:1;margin:0;border:0;border-radius:7px;color:#69778b;font-size:12px;font-weight:600}
#modalKepGonderisi .kep-type-switch .btn-check:checked+.btn{background:#fff;color:#26364d;box-shadow:0 2px 7px rgba(31,41,55,.1)}
@media(max-width:767.98px){#modalKepGonderisi .kep-header-account{display:none}#modalKepGonderisi .kep-recipient-grid{row-gap:12px}}
@media(max-width:575.98px){#modalKepGonderisi .modal-dialog{height:100vh;max-height:100vh;margin:0}#modalKepGonderisi .modal-content{border-radius:0!important}#modalKepGonderisi .modal-body{padding:1rem!important}#modalKepGonderisi .modal-header,#modalKepGonderisi .modal-footer{padding:.85rem 1rem}}
</style>
<div class="modal fade" id="modalKepGonderisi" tabindex="-1" aria-labelledby="modalKepGonderisiLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <form id="formKepGonderisi" enctype="multipart/form-data">
                <div class="modal-header border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <span class="d-grid place-items-center bg-info-subtle text-info rounded-circle" style="width:44px;height:44px"><i class="bx bx-mail-send fs-4"></i></span>
                        <div><h5 class="modal-title fw-bold mb-0" id="modalKepGonderisiLabel">KEP İletisi Oluştur</h5><div class="small text-muted">PTT KEP üzerinden doğrudan güvenli ileti gönderin</div></div>
                    </div>
                    <?php if ($kepSettings && !empty($kepSettings['kep_adresi'])): ?>
                    <div class="kep-header-account">
                        <div class="text-muted font-size-10 mb-1">Gönderen KEP</div>
                        <strong class="font-monospace text-dark"><?php echo htmlspecialchars((string) $kepSettings['kep_adresi'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span id="modalKepBalanceBadge" class="badge bg-secondary-subtle text-secondary border ms-2">Kontrol ediliyor</span>
                    </div>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (!$kepSettings || empty($kepSettings['kep_adresi']) || empty($kepSettings['has_user_credentials'])): ?>
                        <div class="alert alert-warning d-flex align-items-center"><i class="bx bx-error-circle fs-4 me-2"></i><div><strong>KEP kullanıcı ayarları eksik.</strong><div class="small">Gönderimden önce İşlemler → UETS / KEP Ayarları bölümünden kendi KEP giriş bilgilerinizi tamamlayın.</div></div></div>
                    <?php endif; ?>

                    <section class="kep-compose-card">
                    <div class="kep-section-title"><div><strong><i class="bx bx-user-check text-info me-1"></i>Alıcı Bilgileri</strong><small class="d-block mt-1">KEP adresini yazın veya adres defterinden seçin</small></div></div>
                    <div class="row kep-recipient-grid align-items-stretch">
                    <div class="col-md-8 position-relative">
                        <?php echo \App\Helper\Form::FormFloatTextarea('modal_kep_alicilar', '', 'kurum@hs01.kep.tr; ikinci@hs01.kep.tr', 'Alıcılar', 'users', 'form-control font-monospace pe-5', true, '72px', 2, 'data-form-name="kep_alicilar"'); ?>
                        <button type="button" id="btnModalKepAliciDogrula" class="btn btn-sm btn-outline-info position-absolute end-0 top-0 mt-2 me-3" title="PTT rehberinde doğrula"><i class="bx bx-search"></i> Doğrula</button>
                    </div>
                    <div class="col-md-4">
                        <?php echo \App\Helper\Form::FormSelect2('modal_kep_adres_defteri', [], '', 'Adres Defterinden Ekle', 'book-open', 'key', '', 'form-select select2', false, 'width:100%', 'data-placeholder="Kayıtlı KEP adresi seçin"'); ?>
                    </div>
                    </div>
                    <div id="modalKepDogrulamaSonuc" class="small mb-0"></div>
                    </section>

                    <section class="kep-compose-card">
                    <div class="kep-section-title"><div><strong><i class="bx bx-envelope text-primary me-1"></i>İleti Bilgileri</strong><small class="d-block mt-1">Konu ve kısa referans bilgisini girin</small></div></div>
                    <div class="row g-3">
                        <div class="col-md-8"><?php echo \App\Helper\Form::FormFloatInput('text', 'modal_kep_konu', '', 'İleti konusu', 'Konu', 'type', 'form-control', true, 255, 'off', false, 'data-form-name="kep_konu"'); ?></div>
                        <div class="col-md-4">
                            <input type="hidden" id="modal_kep_ileti_tipi" value="standart" data-form-name="kep_ileti_tipi">
                            <div class="kep-type-switch" role="radiogroup" aria-label="İleti tipi">
                                <input class="btn-check" type="radio" name="modal_kep_ileti_tipi_secim" id="kepTipStandart" value="standart" checked><label class="btn" for="kepTipStandart"><i class="bx bx-envelope me-1"></i>Standart</label>
                                <input class="btn-check" type="radio" name="modal_kep_ileti_tipi_secim" id="kepTipResmi" value="resmi"><label class="btn" for="kepTipResmi"><i class="bx bx-file me-1"></i>Resmî</label>
                            </div>
                        </div>
                        <div class="col-12"><?php echo \App\Helper\Form::FormFloatInput('text', 'modal_kep_ileti_detayi', '', 'Dosya/başvuru referansı veya kısa açıklama', 'İleti Detayı', 'info', 'form-control', false, 500, 'off', false, 'data-form-name="kep_ileti_detayi"'); ?></div>
                    </div>
                    </section>

                    <section class="kep-compose-card">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="kep-section-title"><div><strong><i class="bx bx-edit text-primary me-1"></i>İleti Metni</strong><small class="d-block mt-1">Alıcıya iletilecek açıklamayı yazın</small></div></div>
                            <textarea id="modal_kep_icerik" class="form-control" rows="8" data-form-name="kep_icerik" required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="border rounded-3 p-3 bg-light-subtle mt-1">
                                <div class="d-flex align-items-center justify-content-between mb-2"><div><strong><i class="bx bx-paperclip me-1"></i>Ekler</strong><div class="small text-muted">En fazla 20 dosya; PTT alıcılarında 100 MB, diğer KEP sağlayıcılarında 25 MB.</div></div><span id="modalKepEkOzeti" class="badge bg-white text-muted border">0 dosya · 0 KB</span></div>
                                <input type="file" class="form-control" id="modal_kep_ekler" name="kep_ekler[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.odt,.ods,.zip,.eyp">
                            </div>
                        </div>
                    </div>
                    </section>
                    <input type="hidden" data-form-name="kep_paket_turu" value="Standart">
                </div>
                <div class="modal-footer border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div id="modalKepAgentStatusWrapper" class="d-flex align-items-center gap-2">
                        <span id="modalKepAgentBadge" class="badge bg-secondary-subtle text-secondary border font-size-11 py-1 px-2">
                            <i class="bx bx-loader-alt bx-spin me-1"></i> E-İmza Kontrol Ediliyor...
                        </span>
                        <span id="modalKepAgentDetails" class="small text-muted font-size-11 d-none d-sm-inline"></span>
                        <a href="downloads/ErsanEImza.jar?v=1.2.2" download="ErsanEImza.jar" class="btn btn-sm btn-outline-primary py-0 px-2 font-size-11 rounded-pill" id="btnDownloadAgentDirect" title="Java E-İmza Uygulamasını İndir">
                            <i class="bx bxs-download me-1"></i>Uygulamayı İndir (.jar)
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 font-size-11 rounded-pill" id="btnAgentHelpModal" title="Yardım">
                            <i class="bx bx-help-circle"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-success px-4" id="btnModalKepGonder" data-settings-ready="<?php echo ($kepSettings && !empty($kepSettings['kep_adresi']) && !empty($kepSettings['has_user_credentials'])) ? '1' : '0'; ?>" disabled><i class="bx bx-paper-plane me-1"></i> İmzala ve Gönder</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- E-İmza Yerel Ajanı Yardım Modalı -->
<div class="modal fade" id="modalEimzaAgentHelp" tabindex="-1" aria-labelledby="modalEimzaAgentHelpLabel" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-grid place-items-center bg-primary-subtle text-primary rounded-circle" style="width:36px;height:36px">
                        <i class="bx bx-chip fs-5"></i>
                    </span>
                    <h6 class="modal-title fw-bold mb-0" id="modalEimzaAgentHelpLabel">E-İmza Yerel Köprü Ajanı</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="bx bx-info-circle me-1"></i>
                    Tarayıcınızın USB E-İmza kartınızla (SafeNet, Akis, E-Tuğra vb.) güvenli iletişim kurabilmesi için UYAP ve e-Devlet sistemlerinde olduğu gibi Java imza köprüsü çalışmalıdır.
                </div>

                <div class="text-center my-3">
                    <a href="downloads/ErsanEImza.jar?v=1.2.2" download="ErsanEImza.jar" class="btn btn-primary btn-lg w-100 shadow-sm py-2">
                        <i class="bx bxs-download me-2 fs-5 align-middle"></i><strong>Ersan E-İmza Java Uygulamasını İndir</strong>
                        <div class="font-size-11 opacity-75 mt-1">ErsanEImza.jar (Çift tıklayarak çalıştırın)</div>
                    </a>
                </div>
                
                <h6 class="fw-semibold font-size-13 mb-2"><i class="bx bx-check-shield text-success me-1"></i>Kullanım Adımları:</h6>
                <ol class="small text-muted ps-3 mb-3">
                    <li class="mb-2">USB E-İmza cihazınızın bilgisayarınıza takılı olduğunu doğrulayın.</li>
                    <li class="mb-2">Yukarıdaki butondan <strong>ErsanEImza.jar</strong> dosyasını indirin ve <strong>çift tıklayarak açın</strong>.</li>
                    <li class="mb-1">Uygulama arka planda sessizce başlayacak ve web sayfamız e-imza kartınızı otomatik algılayacaktır.</li>
                </ol>

                <div class="p-2 bg-light rounded text-center small text-muted">
                    <i class="bx bx-chip me-1"></i>Uyumlu Cihazlar: SafeNet (TÜRKKEP / E-GÜVEN), AKİS (KamuSM), E-Tuğra
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnCheckAgentAgain">
                    <i class="bx bx-refresh me-1"></i>Bağlantıyı Yeniden Kontrol Et
                </button>
            </div>
        </div>
    </div>
</div>
