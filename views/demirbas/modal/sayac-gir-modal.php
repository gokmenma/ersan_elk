<?php
use App\Helper\Form;
use App\Model\TanimlamalarModel;

$Tanimlamalar = new TanimlamalarModel();
$kategoriler = $Tanimlamalar->getDemirbasKategorileri();

// Sadece sayaç kategorilerini filtrele
$sayacKategorileri = [];
foreach ($kategoriler as $kat) {
    $katAdiLower = mb_strtolower($kat->tur_adi, 'UTF-8');
    if (str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac')) {
        $sayacKategorileri[] = $kat;
    }
}
?>

<div class="modal fade" id="sayacGirModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.06), rgba(16, 185, 129, 0.02));">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bx bx-plus-circle text-success fs-4"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0">Sayaç Gir</h6>
                        <small class="text-muted" style="font-size: 0.7rem;">Depoya yeni sayaç kaydı oluşturun.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sayacGirForm">
                <input type="hidden" name="demirbas_id" value="0">
                <input type="hidden" name="lokasyon" value="bizim_depo">
                <div class="modal-body p-4">
                    <!-- Kayıt Modu -->
                    <div class="d-flex align-items-center justify-content-end gap-3 p-2 bg-light rounded-3 mb-3">
                        <span class="small text-muted fw-bold text-uppercase" style="letter-spacing:0.5px">Kayıt Modu:</span>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="seri_mod" id="sayacModTekli" value="tekli" checked>
                            <label class="form-check-label small fw-medium" for="sayacModTekli">Tekli Kayıt</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" name="seri_mod" id="sayacModToplu" value="toplu">
                            <label class="form-check-label small fw-bold text-success" for="sayacModToplu">
                                <i class="bx bx-list-plus me-1"></i>Toplu Seri Girişi
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormSelect2('kategori_id', $sayacKategorileri, 
                                (!empty($sayacKategorileri) ? $sayacKategorileri[0]->id : null), 
                                'Kategori *', 'grid', 'id', 'tur_adi', 'form-select select2', true); ?>
                        </div>
                        <div class="col-md-8 mb-3">
                            <?php echo Form::FormFloatInput('text', 'demirbas_adi', null, 'Sayaç adını giriniz', 'Sayaç Adı *', 'box', 'form-control', true); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('text', 'marka', null, 'Marka', 'Marka', 'tag'); ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('text', 'model', null, 'Model', 'Model', 'layers'); ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <!-- Tekli Seri No -->
                            <div id="sayacSeriTekli">
                                <?php echo Form::FormFloatInput('text', 'seri_no', null, 'Seri numarası', 'Seri No', 'cpu'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Toplu Seri Girişi Alanları (başlangıçta gizli) -->
                    <div class="row" id="sayacSeriToplu" style="display:none;">
                        <div class="col-12 mb-2">
                            <div class="alert alert-soft-success d-flex align-items-center py-2 mb-2" style="font-size:0.82rem;">
                                <i class="bx bx-info-circle fs-5 me-2"></i>
                                <div><strong>Hızlı Giriş:</strong> Başlangıç seri ve adet girmeniz yeterlidir.</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('text', 'seri_baslangic', null, 'Örn: 2025100', 'Başlangıç Seri No *', 'skip-forward'); ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('text', 'seri_bitis', null, 'Örn: 2025110', 'Bitiş Seri No', 'skip-back'); ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('number', 'seri_adet', null, 'Adet', 'Adet', 'hash', 'form-control', false, null, 'on', false, 'min="1" max="500"'); ?>
                        </div>
                        <div class="col-12 mb-2" id="sayacSeriOnizleme" style="display:none;">
                            <div class="card border border-success border-opacity-25 mb-0 shadow-none bg-light">
                                <div class="card-header bg-success bg-opacity-10 py-2 d-flex align-items-center justify-content-between">
                                    <span class="fw-semibold text-success small"><i class="bx bx-list-check me-1"></i> Oluşturulacak Seriler</span>
                                    <span class="badge bg-success" id="sayacSeriToplamBadge">0 adet</span>
                                </div>
                                <div class="card-body py-2" style="max-height:100px;overflow-y:auto;">
                                    <div id="sayacSeriOnizlemeList" class="d-flex flex-wrap gap-1" style="font-size:0.75rem;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('number', 'miktar', '1', null, 'Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?php echo Form::FormFloatInput('text', 'edinme_tarihi', date('d.m.Y'), null, 'Edinme Tarihi', 'calendar', 'form-control flatpickr'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('aciklama', null, 'Açıklama giriniz', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="sayacGirKaydet" class="btn btn-success">
                        <i class="bx bx-check me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
