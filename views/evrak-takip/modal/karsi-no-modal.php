<div class="modal fade" id="modalKarsiNo" tabindex="-1" aria-labelledby="modalKarsiNoTitle" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-size-15 fw-bold d-flex align-items-center" id="modalKarsiNoTitle">
                    <i class="bx bx-link-alt me-2 fs-5 text-success"></i> Karşı Kurum Evrak No
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="bg-light p-3 rounded-3 mb-3 border font-size-13">
                    <div><strong>Evrak:</strong> <span id="karsiNoKonu" class="text-primary fw-semibold"></span></div>
                    <div class="text-muted mt-1"><strong>Kurum:</strong> <span id="karsiNoKurum"></span> &middot; <strong>Bizim No:</strong> <span id="karsiNoBizimNo"></span></div>
                </div>
                <form id="karsiNoForm" autocomplete="off">
                    <input type="hidden" id="karsiNoEvrakId" value="">
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput('text', 'karsi_evrak_no', '', 'Karşı kurumun verdiği evrak numarası', 'Karşı Kurum Evrak No *', 'hash', 'form-control', true, 100, 'off'); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatInput('text', 'karsi_evrak_tarihi', '', 'Karşı kurum evrak tarihi (opsiyonel)', 'Karşı Kurum Evrak Tarihi', 'calendar', 'form-control flatpickr', false, 10, 'off'); ?>
                    </div>
                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea('karsi_evrak_aciklama', '', 'Açıklama (opsiyonel)', 'Açıklama', 'file-text', 'form-control', false, '80px', 3, 'maxlength="500"'); ?>
                    </div>
                    <div class="text-muted font-size-12">
                        İmzalı evrakın içeriği değişmez; yalnızca bu bilgi kaydedilir. Her değişiklik tarihçeye yazılır.
                    </div>
                </form>
                <div class="mt-4">
                    <div class="fw-bold font-size-13 mb-2"><i class="bx bx-history me-1"></i>Değişiklik Geçmişi</div>
                    <div id="karsiNoGecmis"></div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" form="karsiNoForm" id="btnKarsiNoKaydet" class="btn btn-primary px-4 fw-bold rounded-pill">Kaydet</button>
            </div>
        </div>
    </div>
</div>
