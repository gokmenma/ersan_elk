<div class="modal fade" id="topluIadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-info"><i data-feather="corner-down-left" class="me-2"></i>Toplu Demirbaş İade Al</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="topluIadeForm">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-info border-0 d-flex align-items-center mb-4">
                        <i data-feather="info" class="me-3 text-info"></i>
                        <div class="text-info small">
                            Seçilen <span id="toplu_iade_sayisi" class="fw-bold">0</span> adet zimmet kaydı için toplu iade bilgilerini doldurun. Bu işlem seçilen tüm kayıtların tamamını iade alacaktır.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="text" name="iade_tarihi" id="toplu_iade_tarihi" class="form-control flatpickr" value="<?php echo date('d.m.Y'); ?>" required>
                                <label for="toplu_iade_tarihi">İade Tarihi *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="calendar"></i>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <textarea name="aciklama" id="toplu_iade_aciklama" class="form-control" style="height: 100px" placeholder="Açıklama"></textarea>
                                <label for="toplu_iade_aciklama">İade ile ilgili notlar (Opsiyonel)</label>
                                <div class="form-floating-icon">
                                    <i data-feather="file-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="btnTopluIadeKaydet" class="btn btn-info">
                        <i data-feather="check-square" class="me-1"></i>İade Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
