<?php ?>

<div class="modal fade" id="iadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-undo me-2"></i>Demirbaş İade Al</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="iadeForm">
                <input type="hidden" name="iade_zimmet_id" id="iade_zimmet_id" value="0">
                <div class="modal-body">
                    <!-- Zimmet Bilgisi -->
                    <div class="alert alert-secondary mb-3">
                        <div class="row">
                            <div class="col-12">
                                <strong>Demirbaş:</strong> <span id="iade_demirbas_adi">-</span>
                            </div>
                            <div class="col-12">
                                <strong>Personel:</strong> <span id="iade_personel_adi">-</span>
                            </div>
                            <div class="col-12">
                                <strong>Teslim Miktarı:</strong> <span id="iade_teslim_miktar">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="iade_miktar" class="form-label">İade Miktarı <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="iade_miktar" name="iade_miktar" value="1"
                                min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="iade_tarihi" class="form-label">İade Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr" id="iade_tarihi" name="iade_tarihi"
                                value="<?php echo date('d.m.Y'); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="iade_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="iade_aciklama" name="iade_aciklama" rows="2"
                                placeholder="İade ile ilgili notlar..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="iadeKaydet" class="btn btn-info">
                        <i class="bx bx-undo me-1"></i>İade Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>