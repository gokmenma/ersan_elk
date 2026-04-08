<?php
use App\Helper\Form;

$teslimEdenOptions = [];
if (isset($personeller) && is_array($personeller)) {
    foreach ($personeller as $p) {
        $teslimEdenOptions[$p->id] = $p->adi_soyadi;
    }
}
?>

<div class="modal fade" id="kasiyeTeslimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom" style="background: linear-gradient(135deg, rgba(6, 182, 212, 0.06), rgba(6, 182, 212, 0.02));">
                <div class="d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bx bx-upload text-info fs-4"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Kaskiye Teslim Et</h6>
                        <small class="text-muted" style="font-size: 0.7rem;" id="kasiyeModalAltBilgi">Seçili sayaçları KASKİ'ye iade edin.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kasiyeTeslimForm">
                <input type="hidden" id="kasiye_is_toplu" name="is_toplu" value="0">
                <input type="hidden" id="kasiye_demirbas_id" name="demirbas_id">
                <input type="hidden" id="kasiye_toplu_ids" name="ids">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-info border-0 d-flex align-items-center mb-4">
                        <i class="bx bx-info-circle me-3 text-info fs-5"></i>
                        <div class="text-dark small">
                            Seçili <strong id="kasiyeTopluAdetV2">1</strong> adet sayacı Kaskiye teslim etmek üzeresiniz.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'tarih', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormSelect2('teslim_eden', $teslimEdenOptions, null, 'Teslim Eden', 'user'); ?>
                        </div>
                    </div>

                    <div class="row" id="kasiyeAdetRow" style="display: none;">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatInput('number', 'adet', '1', null, 'İade Sayısı (Adet) *', 'hash', 'form-control', false, null, 'off', false, 'min="1"'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('aciklama', null, 'Teslima dair notlar giriniz...', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info text-white" id="btnKasiyeKaydet">
                        <i class="bx bx-check me-1"></i>Evet, Teslim Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>