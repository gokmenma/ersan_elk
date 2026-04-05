<?php
use App\Helper\Form;
?>

<div class="modal fade" id="kasiyeTeslimModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0 pe-4">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="kasiyeTeslimForm">
                <div class="modal-body pt-0 px-4 text-center">
                    <!-- Icon Section -->
                    <div class="mb-4 d-flex justify-content-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px; border: 3px solid #0dcaf0; background: rgba(13, 202, 240, 0.05);">
                            <i class="bx bx-question-mark text-info" style="font-size: 3.5rem;"></i>
                        </div>
                    </div>

                    <!-- Title Section -->
                    <h4 class="fw-bold text-dark mb-2">Emin misiniz?</h4>
                    <p class="text-muted mb-4 px-4" id="kasiyeOnayMesaji" style="font-size: 1.05rem;">
                        Seçili <span class="fw-bold text-dark" id="kasiyeTopluAdetV2">1</span> adet sayacı Kaskiye teslim etmek istiyor musunuz?
                    </p>

                    <input type="hidden" id="kasiye_is_toplu" value="0">
                    <input type="hidden" id="kasiye_demirbas_id" name="demirbas_id">
                    <input type="hidden" id="kasiye_toplu_ids" name="ids">

                    <!-- Form Fields -->
                    <div class="text-start px-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small mb-1" style="color: #64748b;">Teslim Tarihi:</label>
                            <div class="input-group input-group-merge border rounded-3 bg-white" style="border-color: #e2e8f0 !important;">
                                <span class="input-group-text bg-transparent border-0 pe-1">
                                    <i class="bx bx-calendar text-muted"></i>
                                </span>
                                <input type="text" name="tarih" id="kasiye_tarih" 
                                       class="form-control border-0 flatpickr py-2" 
                                       value="<?php echo date('d.m.Y'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-muted small mb-1" style="color: #64748b;">Açıklama (İsteğe bağlı):</label>
                            <div class="input-group border rounded-3 bg-white" style="border-color: #e2e8f0 !important;">
                                <span class="input-group-text bg-transparent border-0 pe-1 align-items-start pt-2">
                                    <i class="bx bx-file-blank text-muted"></i>
                                </span>
                                <textarea name="aciklama" id="kasiye_aciklama" rows="3" 
                                          class="form-control border-0 py-2 ps-0" 
                                          placeholder="Teslima dair notlar giriniz..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 justify-content-center pb-5 pt-0">
                    <button type="button" class="btn btn-outline-light text-dark fw-bold px-5 py-2 me-2" 
                            data-bs-dismiss="modal" style="border: 1px solid #e2e8f0; border-radius: 10px;">
                        İptal
                    </button>
                    <button type="submit" class="btn btn-dark fw-bold px-5 py-2" id="btnKasiyeKaydet" 
                            style="background: #2a2a2a; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                        Evet, Teslim Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#kasiyeTeslimModal .input-group:focus-within {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
#kasiyeTeslimModal .btn-dark:hover {
    background: #000 !important;
    transform: translateY(-1px);
}
#kasiyeTeslimModal .btn-outline-light:hover {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
}
</style>