<?php
use App\Helper\Form;
use App\Service\Gate;

if (!Gate::allows('personel_karsilama_mesaji')) {
    return;
}
$karsilamaSablonYetki = Gate::allows('personel_karsilama_sablon');
?>
<style>
    #karsilamaModal .wa-preview { background: #e5ddd5; border-radius: 14px; padding: 16px; max-height: 280px; overflow-y: auto; }
    #karsilamaModal .wa-bubble { background: #dcf8c6; border-radius: 10px 10px 2px 10px; padding: 10px 12px; font-size: 13px; line-height: 1.45; color: #111b21; white-space: pre-wrap; word-break: break-word; box-shadow: 0 1px 1px rgba(0, 0, 0, .12); margin-left: auto; max-width: 92%; }
    #karsilamaModal .wa-bubble-time { display: block; text-align: right; font-size: 10px; color: #667781; margin-top: 4px; }
    #karsilamaModal .karsilama-link-card { background: #fff; border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; border-left: 4px solid #25d366; font-size: 12px; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .wa-preview { background: #1d2a30; }
</style>
<div class="modal fade" id="karsilamaModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center rounded-3 bg-success-subtle text-success" style="width:38px;height:38px;">
                        <i class="bx bxl-whatsapp fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-size-14 fw-bold mb-0">Karşılama Mesajı</h5>
                        <div class="font-size-12 text-muted" id="karsilamaPersonelAdi"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-karsilama-kapat="atlandi" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div id="karsilamaDurumBilgi" class="alert alert-success py-2 font-size-12 d-none mb-3"></div>

                <div id="karsilamaMesajPaneli">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <?php echo Form::FormFloatInput('text', 'karsilama_telefon', '', 'Cep Telefonu', 'WhatsApp Telefonu', 'phone', 'form-control', false, 20, 'off'); ?>
                            <div class="form-text font-size-11" id="karsilamaTelefonUyari"></div>
                            <div class="mt-3">
                                <?php echo Form::FormFloatTextarea('karsilama_mesaj', '', 'Mesaj', 'Mesaj Metni', 'message-square', 'form-control', false, '190px', 8); ?>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="font-size-12 text-muted mb-1">WhatsApp önizleme</div>
                            <div class="wa-preview">
                                <div class="wa-bubble"><div class="karsilama-link-card d-none" id="karsilamaLinkKart"><b><i class="bx bx-list-check"></i> Bilgilendirme sayfası</b><br><span class="text-muted">Gerekli evraklar ve banka hesabı adımı</span></div><span id="karsilamaOnizleme"></span><span class="wa-bubble-time">Şimdi ✓✓</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($karsilamaSablonYetki): ?>
                <div id="karsilamaSablonPaneli" class="d-none">
                    <div class="row g-3">
                        <div class="col-12"><?php echo Form::FormFloatTextarea('sablon_mesaj_metni', '', 'Mesaj', 'Mesaj Şablonu ({ad_soyad}, {firma_adi}, {link})', 'message-square', 'form-control', false, '130px', 5); ?></div>
                        <div class="col-12"><?php echo Form::FormFloatInput('text', 'sablon_sayfa_baslik', '', 'Sayfa Başlığı', 'Sayfa Başlığı', 'file-text', 'form-control', false, 255); ?></div>
                        <div class="col-md-6"><?php echo Form::FormFloatTextarea('sablon_evraklar', '', 'Evraklar', 'Evraklar (her satır bir madde, "-" ile başlayan satır alt not)', 'check-square', 'form-control', false, '200px', 8); ?></div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatTextarea('sablon_banka_adimi', '', 'Banka', 'Banka Hesabı Adımı', 'credit-card', 'form-control', false, '80px', 3); ?>
                            <div class="mt-3"><?php echo Form::FormFloatTextarea('sablon_kase_bilgisi', '', 'Kaşe', 'Kaşe Bilgisi', 'briefcase', 'form-control', false, '110px', 4); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <?php if ($karsilamaSablonYetki): ?>
                        <button type="button" class="btn btn-outline-secondary" id="btnKarsilamaSablon"><i class="bx bx-edit me-1"></i> Şablonu Düzenle</button>
                        <button type="button" class="btn btn-primary d-none" id="btnKarsilamaSablonKaydet"><i class="bx bx-save me-1"></i> Şablonu Kaydet</button>
                        <button type="button" class="btn btn-light d-none" id="btnKarsilamaSablonGeri">Geri</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2" id="karsilamaAksiyonlar">
                    <button type="button" class="btn btn-light" data-karsilama-kapat="atlandi">Daha Sonra</button>
                    <button type="button" class="btn btn-success" id="btnKarsilamaGonder"><i class="bx bxl-whatsapp me-1"></i> WhatsApp'ta Gönder</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="views/personel/js/karsilama.js?v=<?= filemtime(__DIR__ . '/../../js/karsilama.js') ?>"></script>
