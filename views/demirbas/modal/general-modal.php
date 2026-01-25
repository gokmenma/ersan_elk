<?php
use App\Helper\Form;

// Kategorileri al
use App\Model\DemirbasKategoriModel;
$Kategori = new DemirbasKategoriModel();
$kategoriler = $Kategori->getActiveCategories();
?>

<div class="modal fade" id="demirbasModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i data-feather="package" class="me-2"></i>Demirbaş Ekle/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="demirbasForm">
                <input type="hidden" name="demirbas_id" id="demirbas_id" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <?php echo Form::FormFloatInput('text', 'demirbas_no', null, 'Örn: DB-001', 'Demirbaş No', 'hash'); ?>
                        </div>
                        <div class="col-md-8 mb-3">
                            <?php
                            echo Form::FormSelect2('kategori_id', $kategoriler, null, 'Kategori *', 'grid', 'id', 'kategori_adi', 'form-select select2', true);
                            ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatInput('text', 'demirbas_adi', null, 'Demirbaş adını giriniz', 'Demirbaş Adı *', 'box', 'form-control', true); ?>
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
                            <?php echo Form::FormFloatInput('text', 'seri_no', null, 'Seri numarası', 'Seri No', 'cpu'); ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <?php echo Form::FormFloatInput('number', 'miktar', '1', null, 'Toplam Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <?php echo Form::FormFloatInput('text', 'edinme_tarihi', date('d.m.Y'), null, 'Edinme Tarihi', 'calendar', 'form-control flatpickr'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <?php echo Form::FormFloatInput('text', 'edinme_tutari', null, '0,00', 'Edinme Tutarı', 'dollar-sign', 'form-control money'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <?php
                            $durumlar = [
                                'aktif' => 'Aktif',
                                'pasif' => 'Pasif',
                                'arizali' => 'Arızalı',
                                'hurda' => 'Hurda'
                            ];
                            echo Form::FormSelect2('durum', $durumlar, 'aktif', 'Durum', 'activity');
                            ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <?php echo Form::FormFloatTextarea('aciklama', null, 'Açıklama giriniz', 'Açıklama', 'file-text'); ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="demirbasKaydet" class="btn btn-success">
                        <i data-feather="save" class="me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>