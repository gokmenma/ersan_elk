<?php use App\Helper\Form; ?>

<div class="modal fade" id="iadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i data-feather="corner-down-left" class="me-2"></i>Demirbaş İade Al</h5>
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
                            <?php echo Form::FormFloatInput('number', 'iade_miktar', '1', null, 'İade Miktarı *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'iade_tarihi', date('d.m.Y'), null, 'İade Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('iade_aciklama', null, 'İade ile ilgili notlar...', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="iadeKaydet" class="btn btn-info">
                        <i data-feather="check-square" class="me-1"></i>İade Al
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>