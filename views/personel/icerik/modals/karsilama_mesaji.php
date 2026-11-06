<?php
use App\Helper\Form;
use App\Service\Gate;

if (!Gate::allows('personel_karsilama_mesaji')) {
    return;
}
$karsilamaSablonYetki = Gate::allows('personel_karsilama_sablon');
$waSvg = '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2Zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.23 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.1-.23-.16-.48-.29Z"/></svg>';
?>
<style>
    #karsilamaModal .km-header { background: linear-gradient(135deg, #0f7a6d 0%, #25d366 100%); color: #fff; border: 0; padding: 18px 24px; }
    #karsilamaModal .km-avatar { width: 52px; height: 52px; border-radius: 50%; background: rgba(255, 255, 255, .22); border: 2px solid rgba(255, 255, 255, .55); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; flex: none; letter-spacing: .02em; }
    #karsilamaModal .km-title { font-size: 17px; font-weight: 700; margin: 0; color: #fff; line-height: 1.2; }
    #karsilamaModal .km-sub { font-size: 12.5px; opacity: .9; margin-top: 2px; }
    #karsilamaModal .km-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 255, 255, .18); border-radius: 99px; padding: 3px 12px; font-size: 12px; margin-top: 6px; }
    #karsilamaModal .km-close { filter: invert(1) grayscale(1) brightness(2); opacity: .9; }
    #karsilamaModal .modal-body { padding: 22px 24px; background: var(--bs-body-bg); }
    #karsilamaModal .km-section-title { font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7686; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
    #karsilamaModal .km-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    #karsilamaModal .km-tool { border: 1px solid #d5dbe5; background: transparent; border-radius: 99px; padding: 5px 14px; font-size: 12.5px; color: inherit; display: inline-flex; align-items: center; gap: 6px; transition: all .15s; min-height: 34px; }
    #karsilamaModal .km-tool:hover { border-color: #25d366; color: #0f7a6d; background: #e9fbf0; }
    #karsilamaModal .km-tool.km-ok { border-color: #25d366; background: #25d366; color: #fff; }
    #karsilamaModal .wa-phone { border-radius: 22px; overflow: hidden; border: 1px solid #d5dbe5; box-shadow: 0 10px 30px rgba(15, 30, 60, .12); background: #e5ddd5; }
    #karsilamaModal .wa-topbar { background: #075e54; color: #fff; padding: 10px 14px; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; }
    #karsilamaModal .wa-topbar .km-mini-avatar { width: 32px; height: 32px; border-radius: 50%; background: rgba(255, 255, 255, .25); display: flex; align-items: center; justify-content: center; font-size: 12px; }
    #karsilamaModal .wa-topbar small { display: block; font-weight: 400; opacity: .8; font-size: 11px; }
    #karsilamaModal .wa-preview { padding: 16px 14px; min-height: 340px; max-height: 420px; overflow-y: auto; background-image: radial-gradient(rgba(0, 0, 0, .05) 1px, transparent 1px); background-size: 14px 14px; }
    #karsilamaModal .wa-bubble { background: #dcf8c6; border-radius: 12px 12px 2px 12px; padding: 8px 10px 6px; font-size: 13.5px; line-height: 1.5; color: #111b21; word-break: break-word; box-shadow: 0 1px 1px rgba(0, 0, 0, .13); margin-left: auto; max-width: 94%; }
    #karsilamaModal #karsilamaOnizleme, #karsilamaModal #kmSablonOnizleme { display: block; white-space: pre-wrap; }
    #karsilamaModal .wa-bubble-time { display: block; text-align: right; font-size: 10.5px; color: #667781; margin-top: 3px; }
    #karsilamaModal .karsilama-link-card { background: #fff; border-radius: 8px; padding: 9px 11px; margin-bottom: 8px; border-left: 4px solid #25d366; font-size: 12.5px; white-space: normal; }
    #karsilamaModal .km-note { font-size: 12px; color: #6b7686; margin-top: 6px; }
    #karsilamaModal .km-sablon-tabs { display: flex; gap: 6px; background: rgba(120, 130, 150, .12); padding: 5px; border-radius: 14px; margin-bottom: 18px; }
    #karsilamaModal .km-sablon-tabs .nav-link { flex: 1; text-align: center; border-radius: 10px; font-size: 13px; font-weight: 600; color: #6b7686; padding: 9px 10px; border: 0; background: transparent; }
    #karsilamaModal .km-sablon-tabs .nav-link:hover { color: #0f7a6d; }
    #karsilamaModal .km-sablon-tabs .nav-link.active { background: #fff !important; color: #0f7a6d !important; box-shadow: 0 2px 8px rgba(15, 30, 60, .1); }
    #karsilamaModal .km-evrak-row { display: flex; gap: 8px; align-items: flex-start; background: rgba(120, 130, 150, .08); border: 1px solid rgba(120, 130, 150, .2); border-radius: 12px; padding: 8px 10px; margin-bottom: 8px; }
    #karsilamaModal .km-evrak-row .km-no { width: 26px; height: 26px; border-radius: 50%; background: #e9fbf0; color: #0f7a6d; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; margin-top: 5px; }
    #karsilamaModal .km-evrak-row .form-control { font-size: 13.5px; }
    #karsilamaModal .km-evrak-row .km-not { font-size: 12.5px; margin-top: 6px; }
    #karsilamaModal .km-mini-btn { border: 0; background: transparent; width: 32px; height: 32px; border-radius: 8px; color: #6b7686; display: inline-flex; align-items: center; justify-content: center; }
    #karsilamaModal .km-mini-btn:hover { background: rgba(120, 130, 150, .2); color: #1c2530; }
    #karsilamaModal .km-mini-btn.km-del:hover { background: #fde8e8; color: #dc3545; }
    #karsilamaModal .km-chip-var { border: 1px dashed #25d366; background: #e9fbf0; color: #0f7a6d; border-radius: 99px; padding: 3px 12px; font-size: 12px; font-weight: 600; }
    #karsilamaModal .km-kase-prev { border: 1px dashed #9aa5b5; border-radius: 12px; padding: 12px 14px; font-size: 13.5px; line-height: 1.6; }
    #karsilamaModal .km-checklist-prev li { display: flex; gap: 8px; padding: 5px 0; font-size: 13.5px; list-style: none; }
    #karsilamaModal .km-checklist-prev { padding: 0; margin: 0; }
    #karsilamaModal .km-checklist-prev i { width: 18px; height: 18px; border: 2px solid #c3cbd8; border-radius: 6px; flex: none; margin-top: 2px; }
    #karsilamaModal .km-checklist-prev small { display: block; color: #6b7686; font-size: 12px; }
    #karsilamaModal .modal-footer { padding: 14px 24px; background: rgba(120, 130, 150, .06); }
    #karsilamaModal .btn-wa { background: #25d366; border-color: #25d366; color: #fff; font-weight: 600; padding: 10px 22px; border-radius: 12px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 16px rgba(37, 211, 102, .35); }
    #karsilamaModal .btn-wa:hover:not(:disabled) { background: #1fb457; border-color: #1fb457; color: #fff; }
    #karsilamaModal .btn-wa:disabled { opacity: .5; box-shadow: none; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .km-sablon-tabs .nav-link.active { background: #2b3340 !important; color: #4cd98a !important; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .km-tool { border-color: #3a4352; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .wa-phone { border-color: #3a4352; background: #0b141a; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .wa-preview { background-color: #0b141a; }
    html[data-theme-preset="macos-dark"] #karsilamaModal .km-evrak-row .km-no,
    html[data-theme-preset="macos-dark"] #karsilamaModal .km-chip-var { background: #173326; color: #4cd98a; }
    @media (max-width: 767.98px) { #karsilamaModal .modal-body { padding: 16px; } #karsilamaModal .km-header { padding: 14px 16px; } }
</style>
<div class="modal fade no-upgrade" id="karsilamaModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header km-header">
                <div class="d-flex align-items-center gap-3 flex-grow-1">
                    <div class="km-avatar" id="karsilamaAvatar">?</div>
                    <div>
                        <h5 class="km-title" id="karsilamaBaslik">Karşılama Mesajı</h5>
                        <div class="km-sub" id="karsilamaPersonelAdi"></div>
                        <div class="km-chip"><?php echo $waSvg; ?> <span id="karsilamaAltBilgi">WhatsApp ile işe alım bilgilendirmesi</span></div>
                    </div>
                </div>
                <button type="button" class="btn-close km-close" data-karsilama-kapat="atlandi" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div id="karsilamaDurumBilgi" class="alert alert-success py-2 font-size-12 d-none mb-3"></div>

                <div id="karsilamaMesajPaneli">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="km-section-title">Alıcı</div>
                            <?php echo Form::FormFloatInput('text', 'karsilama_telefon', '', 'Cep Telefonu', 'WhatsApp Telefonu', 'phone', 'form-control', false, 20, 'off'); ?>
                            <div class="km-note" id="karsilamaTelefonUyari"></div>

                            <div class="km-section-title mt-4"><span>Mesaj</span><span class="fw-normal text-muted" id="karsilamaKarakter"></span></div>
                            <?php echo Form::FormFloatTextarea('karsilama_mesaj', '', 'Mesaj', 'Mesaj Metni', 'message-square', 'form-control', false, '250px', 10); ?>
                            <div class="km-tools">
                                <button type="button" class="km-tool" id="btnKarsilamaMesajKopyala"><i class="bx bx-copy"></i> <span>Mesajı Kopyala</span></button>
                                <button type="button" class="km-tool" id="btnKarsilamaLinkKopyala"><i class="bx bx-link"></i> <span>Linki Kopyala</span></button>
                                <a class="km-tool text-decoration-none" id="btnKarsilamaSayfaAc" href="#" target="_blank" rel="noopener"><i class="bx bx-show"></i> <span>Sayfayı Gör</span></a>
                                <button type="button" class="km-tool" id="btnKarsilamaMesajSifirla"><i class="bx bx-reset"></i> <span>Metni Sıfırla</span></button>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="km-section-title">WhatsApp önizleme</div>
                            <div class="wa-phone">
                                <div class="wa-topbar">
                                    <div class="km-mini-avatar" id="karsilamaMiniAvatar">?</div>
                                    <div><span id="karsilamaOnizlemeAd"></span><small>çevrimiçi</small></div>
                                </div>
                                <div class="wa-preview">
                                    <div class="wa-bubble">
                                        <div class="karsilama-link-card d-none" id="karsilamaLinkKart"><b><i class="bx bx-list-check"></i> Bilgilendirme sayfası</b><br><span class="text-muted">Gerekli evraklar, maaş hesabı adımı ve firma bilgileri</span></div>
                                        <span id="karsilamaOnizleme"></span>
                                        <span class="wa-bubble-time">Şimdi ✓✓</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($karsilamaSablonYetki): ?>
                <div id="karsilamaSablonPaneli" class="d-none">
                    <ul class="nav km-sablon-tabs" role="tablist">
                        <li class="nav-item flex-fill" role="presentation"><button type="button" class="nav-link active w-100" data-bs-toggle="tab" data-bs-target="#kmTabMesaj" role="tab"><i class="bx bx-message-detail me-1"></i> Mesaj</button></li>
                        <li class="nav-item flex-fill" role="presentation"><button type="button" class="nav-link w-100" data-bs-toggle="tab" data-bs-target="#kmTabEvrak" role="tab"><i class="bx bx-list-check me-1"></i> Evrak Listesi</button></li>
                        <li class="nav-item flex-fill" role="presentation"><button type="button" class="nav-link w-100" data-bs-toggle="tab" data-bs-target="#kmTabBanka" role="tab"><i class="bx bx-building me-1"></i> Banka &amp; Kaşe</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="kmTabMesaj" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="km-section-title">Mesaj şablonu</div>
                                    <?php echo Form::FormFloatTextarea('sablon_mesaj_metni', '', 'Mesaj', 'Mesaj Şablonu', 'message-square', 'form-control', false, '230px', 9); ?>
                                    <div class="km-note mt-2">Değişken eklemek için tıklayın:</div>
                                    <div class="km-tools mt-1">
                                        <button type="button" class="km-chip-var" data-km-var="{ad_soyad}">+ Personel adı</button>
                                        <button type="button" class="km-chip-var" data-km-var="{firma_adi}">+ Firma adı</button>
                                        <button type="button" class="km-chip-var" data-km-var="{link}">+ Bilgilendirme linki</button>
                                    </div>
                                    <div class="km-note">Link değişkeni unutulursa mesajın sonuna otomatik eklenir.</div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="km-section-title">Örnek önizleme</div>
                                    <div class="wa-phone"><div class="wa-preview"><div class="wa-bubble"><span id="kmSablonOnizleme"></span><span class="wa-bubble-time">Şimdi ✓✓</span></div></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="kmTabEvrak" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-lg-7">
                                    <?php echo Form::FormFloatInput('text', 'sablon_sayfa_baslik', '', 'Sayfa Başlığı', 'Bilgilendirme Sayfası Başlığı', 'file-text', 'form-control', false, 255); ?>
                                    <div class="km-section-title mt-4"><span>Gerekli evraklar</span><span class="fw-normal text-muted" id="kmEvrakSayisi"></span></div>
                                    <div id="kmEvrakListe"></div>
                                    <button type="button" class="km-tool mt-1" id="btnKmEvrakEkle"><i class="bx bx-plus"></i> Evrak Ekle</button>
                                </div>
                                <div class="col-lg-5">
                                    <div class="km-section-title">Personelin göreceği liste</div>
                                    <div class="km-kase-prev"><ul class="km-checklist-prev" id="kmEvrakOnizleme"></ul></div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="kmTabBanka" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="km-section-title">Maaş hesabı adımı</div>
                                    <?php echo Form::FormFloatTextarea('sablon_banka_adimi', '', 'Banka', 'Banka Hesabı Açılış Açıklaması', 'credit-card', 'form-control', false, '110px', 3); ?>
                                    <div class="km-section-title mt-4">Kaşe bilgisi</div>
                                    <?php echo Form::FormFloatTextarea('sablon_kase_bilgisi', '', 'Kaşe', 'Kaşe Bilgisi (ilk satır firma unvanı)', 'briefcase', 'form-control', false, '140px', 5); ?>
                                </div>
                                <div class="col-lg-6">
                                    <div class="km-section-title">Önizleme</div>
                                    <div class="km-kase-prev mb-3" id="kmBankaOnizleme"></div>
                                    <div class="km-kase-prev" id="kmKaseOnizleme"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="d-flex gap-2">
                    <?php if ($karsilamaSablonYetki): ?>
                        <button type="button" class="btn btn-outline-secondary" id="btnKarsilamaSablon"><i class="bx bx-slider-alt me-1"></i> Şablonu Düzenle</button>
                        <button type="button" class="btn btn-light d-none" id="btnKarsilamaSablonGeri"><i class="bx bx-arrow-back me-1"></i> Geri</button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btnKarsilamaSablonVarsayilan"><i class="bx bx-reset me-1"></i> Varsayılana Dön</button>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2" id="karsilamaAksiyonlar">
                    <button type="button" class="btn btn-light" data-karsilama-kapat="atlandi">Daha Sonra</button>
                    <button type="button" class="btn btn-wa" id="btnKarsilamaGonder"><?php echo $waSvg; ?> WhatsApp'ta Gönder</button>
                </div>
                <?php if ($karsilamaSablonYetki): ?>
                <div class="d-flex gap-2 d-none" id="karsilamaSablonAksiyonlar">
                    <button type="button" class="btn btn-wa" id="btnKarsilamaSablonKaydet"><i class="bx bx-save"></i> Şablonu Kaydet</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="views/personel/js/karsilama.js?v=<?= filemtime(__DIR__ . '/../../js/karsilama.js') ?>"></script>
