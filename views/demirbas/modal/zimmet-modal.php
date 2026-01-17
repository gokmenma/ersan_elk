<?php
use App\Model\PersonelModel;
use App\Model\DemirbasModel;

$Personel = new PersonelModel();
$Demirbas = new DemirbasModel();

$personeller = $Personel->all();
$demirbaslar = $Demirbas->getInStock();
?>

<div class="modal fade" id="zimmetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bx bx-transfer me-2"></i>Demirbaş Zimmet Ver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="zimmetForm">
                <input type="hidden" name="zimmet_id" id="zimmet_id" value="0">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-1"></i>
                        <small>Personele demirbaş zimmetlemek için aşağıdaki formu doldurun. Zimmetlenen miktar stoktan
                            düşülecektir.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="demirbas_id_zimmet" class="form-label">Demirbaş Seçin <span
                                    class="text-danger">*</span></label>
                            <select class="form-select select2" id="demirbas_id_zimmet" name="demirbas_id" required>
                                <option value="">Demirbaş arayın...</option>
                                <?php foreach ($demirbaslar as $d): ?>
                                    <option value="<?php echo $d->id; ?>" data-kalan="<?php echo $d->kalan_miktar ?? 1; ?>">
                                        <?php echo ($d->demirbas_no ?? '-') . ' - ' . $d->demirbas_adi . ' (' . ($d->kategori_adi ?? 'Kategorisiz') . ') - Kalan: ' . ($d->kalan_miktar ?? 1); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="personel_id" class="form-label">Personel Seçin <span
                                    class="text-danger">*</span></label>
                            <select class="form-select select2" id="personel_id" name="personel_id" required>
                                <option value="">Personel arayın...</option>
                                <?php foreach ($personeller as $p): ?>
                                    <option value="<?php echo $p->id; ?>">
                                        <?php echo $p->adi_soyadi . ' - ' . ($p->cep_telefonu ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="teslim_miktar" class="form-label">Teslim Edilecek Miktar <span
                                    class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="teslim_miktar" name="teslim_miktar"
                                    value="1" min="1" required>
                                <span class="input-group-text">
                                    Kalan: <span id="kalanMiktarText" class="ms-1 fw-bold">-</span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="teslim_tarihi" class="form-label">Teslim Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control flatpickr" id="teslim_tarihi" name="teslim_tarihi"
                                value="<?php echo date('d.m.Y'); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="zimmet_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="zimmet_aciklama" name="aciklama" rows="2"
                                placeholder="Zimmet ile ilgili notlar..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="zimmetKaydet" class="btn btn-warning">
                        <i class="bx bx-transfer me-1"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>