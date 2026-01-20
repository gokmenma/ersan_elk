<!-- Zimmet Modal -->
<div class="modal fade" id="zimmetModal" tabindex="-1" aria-labelledby="zimmetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning bg-gradient text-dark">
                <h5 class="modal-title" id="zimmetModalLabel"><i class="bx bx-transfer me-2"></i>Araç Zimmet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="zimmetForm">
                    <div class="mb-3">
                        <label class="form-label">Araç Seçin <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="arac_id" id="zimmetAracSelect" required>
                            <option value="">Araç Seçin</option>
                            <?php foreach ($araclar as $arac): ?>
                                <?php if (empty($arac->zimmetli_personel_id)): ?>
                                    <option value="<?php echo $arac->id; ?>" data-km="<?php echo $arac->guncel_km; ?>">
                                        <?php echo $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? ''); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Personel Seçin <span class="text-danger">*</span></label>
                        <select class="form-select select2" name="personel_id" id="zimmetPersonelSelect" required>
                            <option value="">Personel Seçin</option>
                            <?php foreach ($personeller as $personel): ?>
                                <option value="<?php echo $personel->id; ?>">
                                    <?php echo $personel->adi_soyadi; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Zimmet Tarihi <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="zimmet_tarihi"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Teslim KM</label>
                            <input type="number" class="form-control" name="teslim_km" id="zimmetTeslimKm" min="0"
                                placeholder="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="2"
                            placeholder="Zimmet notları..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-warning" id="btnZimmetKaydet">
                    <i class="bx bx-transfer me-1"></i> Zimmet Ver
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).on('change', '#zimmetAracSelect', function () {
        const km = $(this).find(':selected').data('km');
        if (km) {
            $('#zimmetTeslimKm').val(km);
        }
    });
</script>