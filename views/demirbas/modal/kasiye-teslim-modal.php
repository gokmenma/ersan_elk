<?php
use App\Helper\Form;
?>

<div class="modal fade" id="kasiyeTeslimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-dark"
                style="background: rgba(33, 37, 41, 0.05); border-bottom: 2px solid #212529;">
                <div class="modal-title-section">
                    <div class="modal-icon-box" style="background: rgba(33, 37, 41, 0.1);">
                        <i class="bx bx-log-out text-dark"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title text-dark">Kaskiye Teslim Et</h5>
                        <p class="modal-subtitle">Sayacı Kaskiye teslim ederek stoğu sıfırla</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kasiyeTeslimForm">
                <div class="modal-body">
                    <div class="alert alert-warning border-0 bg-soft-warning mb-3" role="alert">
                        <div class="d-flex">
                            <i class="bx bx-info-circle fs-4 me-2"></i>
                            <div class="small fw-medium">
                                Sayaçları Kaskiye teslim etmek için bu formu kullanın. Teslim edilen sayaçlar stoktan
                                kalıcı olarak düşecektir.
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="kasiye_demirbas_id" name="demirbas_id" required>

                    <div class="mb-4">
                        <div class="p-3 bg-light rounded-3 border border-dark border-opacity-10 text-center">
                            <h5 class="mb-1 text-dark fw-bold" id="kasiyeSayacAdi">Sayaç Adı</h5>
                            <div class="text-muted"><i class="bx bx-barcode me-1"></i><span id="kasiyeSeriNo"
                                    class="fw-medium"></span></div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatInput('text', 'tarih', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                    </div>

                    <div class="mb-1">
                        <?php echo Form::FormFloatTextarea('aciklama', null, 'Teslime dair notlar', 'Açıklama', 'file-text'); ?>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border-top: 1px solid rgba(0,0,0,.05);">
                    <button type="button" class="btn btn-outline-secondary fw-medium px-4"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-dark fw-bold px-4" id="btnKasiyeKaydet">
                        <i class="bx bx-check-circle me-1"></i> Teslimi Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>