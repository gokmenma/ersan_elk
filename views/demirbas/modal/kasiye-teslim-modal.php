<?php
use App\Helper\Form;
?>

<div class="modal fade" id="kasiyeTeslimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-danger">
                <div class="modal-title-section">
                    <div class="modal-icon-box">
                        <i class="bx bx-log-out"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title">Kaskiye Teslim</h5>
                        <p class="modal-subtitle">Hurda sayaç teslimatı ve stok düşümü</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="kasiyeTeslimForm">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="bx bx-info-circle me-1"></i>
                        <small>Hurda depodaki sayaçları Kaskiye teslim etmek için bu formu kullanın. Teslim edilen
                            sayaçlar stoktan kalıcı olarak düşecektir.</small>
                    </div>

                    <div class="mb-3">
                        <label for="kasiye_demirbas_id" class="form-label fw-medium">
                            <i class="bx bx-recycle text-danger me-1"></i>Hurda Sayaç Seçin *
                        </label>
                        <select class="form-select" id="kasiye_demirbas_id" name="demirbas_id" required>
                            <option value="">Seçiniz...</option>
                            <?php if (!empty($hurdaDemirbaslar)): ?>
                                <?php foreach ($hurdaDemirbaslar as $hd): ?>
                                    <option value="<?php echo $hd->id; ?>" data-kalan="<?php echo $hd->kalan_miktar; ?>">
                                        <?php echo htmlspecialchars($hd->demirbas_adi); ?>
                                        <?php echo $hd->seri_no ? ' (SN: ' . $hd->seri_no . ')' : ''; ?>
                                        - Depoda:
                                        <?php echo $hd->kalan_miktar; ?> adet
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('number', 'kasiye_miktar', '1', null, 'Teslim Miktarı *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'kasiye_tarihi', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea('kasiye_aciklama', null, 'Kaskiye teslim notu', 'Açıklama', 'file-text'); ?>
                    </div>

                    <div class="d-flex align-items-center gap-2 p-2 bg-light rounded-3" id="kasiyeDepoInfo"
                        style="display: none !important;">
                        <i class="bx bx-info-circle text-muted fs-5"></i>
                        <div>
                            <small class="text-muted">Depodaki mevcut stok:</small>
                            <strong id="kasiyeKalanText" class="text-danger ms-1">-</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="kasiyeTeslimKaydet" class="btn btn-danger shadow-danger px-4">
                        <i class="bx bx-log-out me-1"></i>Teslim Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>