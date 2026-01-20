<!-- KM Kaydı Modal -->
<div class="modal fade" id="kmModal" tabindex="-1" aria-labelledby="kmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info bg-gradient text-white">
                <h5 class="modal-title" id="kmModalLabel"><i class="bx bx-tachometer me-2"></i>KM Kaydı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="kmForm">
                    <input type="hidden" name="id" value="">

                    <div class="row">
                        <div class="col-8 mb-3">
                            <label class="form-label">Araç Seçin <span class="text-danger">*</span></label>
                            <select class="form-select select2" name="arac_id" id="kmAracSelect" required>
                                <option value="">Araç Seçin</option>
                                <?php foreach ($araclar as $arac): ?>
                                    <option value="<?php echo $arac->id; ?>" data-km="<?php echo $arac->guncel_km; ?>">
                                        <?php echo $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Tarih <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Başlangıç KM <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="baslangic_km" id="kmBaslangic" min="0"
                                required placeholder="Gün başı KM">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Bitiş KM <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="bitis_km" id="kmBitis" min="0" required
                                placeholder="Gün sonu KM">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Yapılan KM</label>
                        <input type="text" class="form-control" id="kmYapilan" readonly disabled
                            placeholder="Otomatik hesaplanır">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="2"
                            placeholder="Gün içi rotalar, notlar..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-info text-white" id="btnKmKaydet">
                    <i class="bx bx-save me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Araç seçildiğinde KM'yi otomatik doldur
    $(document).on('change', '#kmAracSelect', function () {
        const km = $(this).find(':selected').data('km');
        if (km) {
            $('#kmBaslangic').val(km);
        }
    });

    // Yapılan KM otomatik hesaplama
    $(document).on('input', '#kmBaslangic, #kmBitis', function () {
        const baslangic = parseInt($('#kmBaslangic').val()) || 0;
        const bitis = parseInt($('#kmBitis').val()) || 0;
        const yapilan = bitis - baslangic;
        $('#kmYapilan').val(yapilan > 0 ? yapilan + ' km' : '0 km');
    });
</script>