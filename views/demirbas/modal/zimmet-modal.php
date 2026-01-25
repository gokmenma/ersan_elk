<?php
use App\Helper\Form;
use App\Model\PersonelModel;
use App\Model\DemirbasModel;

$Personel = new PersonelModel();
$Demirbas = new DemirbasModel();

$personeller = $Personel->all();
$demirbaslar = $Demirbas->getInStock();

// Personel seçeneklerini hazırla
$personelOptions = [];
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi . ' - ' . ($p->cep_telefonu ?? '');
}
?>

<div class="modal fade" id="zimmetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i data-feather="repeat" class="me-2"></i>Demirbaş Zimmet Ver</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="zimmetForm">
                <input type="hidden" name="zimmet_id" id="zimmet_id" value="0">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i data-feather="info" class="me-1"></i>
                        <small>Personele demirbaş zimmetlemek için aşağıdaki formu doldurun. Zimmetlenen miktar stoktan
                            düşülecektir.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <!-- Custom Select2 with data attributes to match Form class style -->
                            <div class="form-floating form-floating-custom">
                                <select class="form-select select2" id="demirbas_id_zimmet" name="demirbas_id" required
                                    style="width:100%">
                                    <option value="">Demirbaş arayın...</option>
                                    <?php foreach ($demirbaslar as $d): ?>
                                        <option value="<?php echo $d->id; ?>"
                                            data-kalan="<?php echo $d->kalan_miktar ?? 1; ?>">
                                            <?php echo ($d->demirbas_no ?? '-') . ' - ' . $d->demirbas_adi . ' (' . ($d->kategori_adi ?? 'Kategorisiz') . ') - Kalan: ' . ($d->kalan_miktar ?? 1); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="demirbas_id_zimmet">Demirbaş Seçin *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="package"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormSelect2('personel_id', $personelOptions, null, 'Personel Seçin *', 'users'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="input-group">
                                <?php echo Form::FormFloatInput('number', 'teslim_miktar', '1', null, 'Teslim Edilecek Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                                <span class="input-group-text">
                                    Kalan: <span id="kalanMiktarText" class="ms-1 fw-bold">-</span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'teslim_tarihi', date('d.m.Y'), null, 'Teslim Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('aciklama', null, 'Zimmet ile ilgili notlar...', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="zimmetKaydet" class="btn btn-warning">
                        <i data-feather="check-square" class="me-1"></i>Zimmet Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>