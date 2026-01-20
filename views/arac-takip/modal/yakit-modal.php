<!-- Yakıt Kaydı Modal -->
<div class="modal fade" id="yakitModal" tabindex="-1" aria-labelledby="yakitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success bg-gradient text-white">
                <h5 class="modal-title" id="yakitModalLabel"><i class="bx bx-gas-pump me-2"></i>Yakıt Kaydı Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="yakitForm">
                    <input type="hidden" name="id" value="">

                    <div class="row">
                        <div class="col-8 mb-3">
                            <label class="form-label">Araç Seçin <span class="text-danger">*</span></label>
                            <select class="form-select select2" name="arac_id" id="yakitAracSelect" required>
                                <option value="">Araç Seçin</option>
                                <?php foreach ($araclar as $arac): ?>
                                    <option value="<?php echo $arac->id; ?>" data-km="<?php echo $arac->guncel_km; ?>"
                                        data-yakit="<?php echo $arac->yakit_tipi; ?>">
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
                            <label class="form-label">Güncel KM <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="km" id="yakitKm" min="0" required
                                placeholder="Güncel kilometre">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Yakıt Tipi</label>
                            <select class="form-select" name="yakit_tipi" id="yakitTipi">
                                <option value="dizel">Dizel</option>
                                <option value="benzin">Benzin</option>
                                <option value="lpg">LPG</option>
                                <option value="elektrik">Elektrik</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Miktar (L) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="yakit_miktari" id="yakitMiktari"
                                min="0" required placeholder="0.00">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Birim Fiyat (₺)</label>
                            <input type="number" step="0.01" class="form-control" name="birim_fiyat"
                                id="yakitBirimFiyat" min="0" placeholder="0.00">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Toplam Tutar (₺) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="toplam_tutar" id="yakitTutar"
                                min="0" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">İstasyon</label>
                            <input type="text" class="form-control" name="istasyon"
                                placeholder="Akaryakıt istasyonu adı">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Fiş/Fatura No</label>
                            <input type="text" class="form-control" name="fatura_no" placeholder="Fiş numarası">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tam_depo_mu" value="1" id="tamDepo">
                            <label class="form-check-label" for="tamDepo">Tam Depo</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="2" placeholder="Ek notlar..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" id="btnYakitKaydet">
                    <i class="bx bx-save me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Araç seçildiğinde KM ve yakıt tipini otomatik doldur
    $(document).on('change', '#yakitAracSelect', function () {
        const selected = $(this).find(':selected');
        const km = selected.data('km');
        const yakitTipi = selected.data('yakit');

        if (km) $('#yakitKm').val(km);
        if (yakitTipi) $('#yakitTipi').val(yakitTipi);
    });
</script>