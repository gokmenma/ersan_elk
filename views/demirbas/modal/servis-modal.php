<?php
use App\Helper\Form;
use App\Model\PersonelModel;

$PersonelModel = new PersonelModel();
$personeller = $PersonelModel->all();

$personelOptions = [];
foreach ($personeller as $p) {
    $personelOptions[$p->id] = $p->adi_soyadi;
}
?>
<!-- Servis Kaydı Ekleme/Düzenleme Modal -->
<div class="modal fade" id="servisModal" tabindex="-1" aria-labelledby="servisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning bg-gradient text-dark">
                <h5 class="modal-title" id="servisModalLabel"><i class="bx bx-wrench me-2"></i>Yeni Servis Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="servisForm">
                    <input type="hidden" name="id" id="servis_id" value="">

                    <div class="row g-3">
                        <div class="col-md-12" id="servis_demirbas_select_area">
                            <?php echo Form::FormSelect2('demirbas_id', [], null, 'Demirbaş Seçin *', 'package', 'key', '', 'form-select select2', true, 'width:100%', '', 'servis_demirbas_id'); ?>
                        </div>

                        <div class="col-md-12 d-none" id="servis_demirbas_info_area">
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <div class="avatar-title bg-soft-warning text-warning fs-4 rounded-circle">
                                            <i class="bx bx-package"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-1 fw-bold" id="servis_demirbas_adi_display">-</h6>
                                        <p class="text-muted small mb-0" id="servis_demirbas_no_display">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'servis_tarihi', date('d.m.Y'), '', 'Servis Giriş Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'iade_tarihi', null, '', 'Servis Çıkış Tarihi', 'calendar', 'form-control flatpickr'); ?>
                        </div>


                        <div class="col-md-12">
                            <?php echo Form::FormSelect2('teslim_eden_personel_id', $personelOptions, null, 'Servise Teslim Eden Personel *', 'user', 'form-control select2', true); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatInput('text', 'servis_adi', null, 'Servis/Usta Adı', 'Servis Noktası', 'bx bx-store'); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatTextarea('servis_nedeni', null, 'Arıza, Bakım, Onarım vs.', 'Servis Nedeni / Şikayet', 'help-circle'); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatTextarea('yapilan_islemler', null, 'Tamir edildi, parça değişti vs.', 'Yapılan İşlemler', 'bx bx-list-check'); ?>
                        </div>

                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'tutar', null, '0.00', 'Toplam Tutar (₺)', 'bx bx-purchase-tag', 'form-control masker-money'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'fatura_no', null, '', 'Fatura/Fiş No', 'bx bx-receipt'); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" id="btnServisKaydet" class="btn btn-warning">
                    <i class="bx bx-save me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>