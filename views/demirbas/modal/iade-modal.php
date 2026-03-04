<?php use App\Helper\Form; ?>

<div class="modal fade" id="iadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-info" id="iadeModalTitle"><i data-feather="corner-down-left" class="me-2"></i>Demirbaş İade Al</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="iadeForm">
                <input type="hidden" name="iade_zimmet_id" id="iade_zimmet_id" value="0">
                <input type="hidden" name="is_aparat" id="iade_is_aparat" value="0">
                <input type="hidden" name="islem_turu" id="iade_islem_turu" value="iade">
                <div class="modal-body p-4">
                    <div class="alert alert-soft-info border-0 d-flex align-items-center mb-4">
                        <i data-feather="info" class="me-3 text-info"></i>
                        <div class="text-info small" id="iadeModalInfoText">
                            Seçilen zimmet kaydı için iade bilgilerini doldurun. Kısmi iade yapılabilir.
                        </div>
                    </div>

                    <!-- Zimmet Bilgisi Özeti -->
                    <div class="bg-light rounded p-3 mb-4 border">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Demirbaş</label>
                                <div id="iade_demirbas_adi" class="fw-bold text-dark">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Personel</label>
                                <div id="iade_personel_adi" class="fw-bold text-dark">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Mevcut/Teslim Miktar</label>
                                <div class="fw-bold text-dark"><span id="iade_teslim_miktar">-</span> Adet</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="number" name="iade_miktar" id="iade_miktar" class="form-control" value="1" min="1" required>
                                <label for="iade_miktar" id="iadeMiktarLabel">İade Miktarı *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="hash"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="text" name="iade_tarihi" id="iade_tarihi" class="form-control flatpickr" value="<?php echo date('d.m.Y'); ?>" required>
                                <label for="iade_tarihi" id="iadeTarihLabel">İade Tarihi *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="calendar"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <textarea name="iade_aciklama" id="iade_aciklama" class="form-control" style="height: 100px" placeholder="Açıklama"></textarea>
                                <label for="iade_aciklama" id="iadeAciklamaLabel">İade ile ilgili notlar...</label>
                                <div class="form-floating-icon">
                                    <i data-feather="file-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="iadeKaydet" class="btn btn-info">
                        <i data-feather="check-square" class="me-1"></i><span id="iadeKaydetText">İade Al</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>