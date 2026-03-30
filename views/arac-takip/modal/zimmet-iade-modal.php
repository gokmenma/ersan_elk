<!-- Zimmet İade Modal -->
<div class="modal fade" id="zimmetIadeModal" tabindex="-1" aria-labelledby="zimmetIadeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="corner-up-left" class="text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="zimmetIadeModalLabel">Araç İadesi</h5>
                        <small class="text-muted" id="iade-arac-plaka">Araç plaka bilgisi</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4 bg-light bg-opacity-50">
                <form id="zimmetIadeForm">
                    <input type="hidden" name="zimmet_id" id="iade_zimmet_id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-soft-danger d-flex align-items-center" role="alert">
                                <i class="bx bx-error-circle fs-4 me-2"></i>
                                <div>
                                    <span id="iade-mesaj" class="fw-semibold">Aracı iade alırken lütfen güncel KM bilgisini giriniz.</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'iade_tarihi', date('d.m.Y'), '', 'İade Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>

                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('number', 'iade_km', '', 'Güncel KM', 'İade KM *', 'activity', 'form-control', true); ?>
                        </div>

                        <div class="col-12">
                            <?php echo \App\Helper\Form::FormFloatTextarea('notlar', '', 'İade notları (opsiyonel)', 'İade Notu', 'edit-3', 'form-control', false, '80px'); ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer bg-white p-3 border-top">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger px-4 shadow-sm" id="btnZimmetIadeKaydet">
                    <i class="bx bx-check me-1"></i> İadeyi Tamamla
                </button>
            </div>
        </div>
    </div>
</div>
