<?php use App\Helper\Form; ?>
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
                    <input type="hidden" name="id" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php
                            $araclar = (new App\Model\AracModel())->getAktifAraclar();
                            $aracOptions = ['' => 'Araç Seçin'];
                            foreach ($araclar as $arac) {
                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                            }
                            echo Form::FormSelect2('arac_id', $aracOptions, null, 'Araç *', 'truck', 'key', '', 'form-select select2', true);
                            ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('text', 'servis_tarihi', date('d.m.Y'), '', 'Servis Giriş Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                        </div>

                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('number', 'giris_km', null, '0', 'Servis Giriş KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput('number', 'cikis_km', null, '0', 'Servis Çıkış KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatInput('text', 'servis_adi', null, 'Servis/Usta Adı', 'Servis Noktası', 'bx bx-store'); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatTextarea('servis_nedeni', null, 'Bakım, Onarım, Kaza vs.', 'Servis Nedeni / Şikayet', 'help-circle'); ?>
                        </div>

                        <div class="col-md-12">
                            <?php echo Form::FormFloatTextarea('yapilan_islemler', null, 'Yağ değişimi, fren balatası vs.', 'Yapılan İşlemler', 'bx bx-list-check'); ?>
                        </div>

                        <div class="col-md-4">
                            <?php echo Form::FormFloatInput('text', 'tutar', null, '0.00', 'Toplam Tutar (₺)', 'bx bx-purchase-tag', 'form-control masker-money'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo Form::FormFloatInput('text', 'fatura_no', null, '', 'Fatura/Fiş No', 'bx bx-receipt'); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo Form::FormFloatInput('text', 'iade_tarihi', null, '', 'Servis Çıkış Tarihi', 'calendar', 'form-control flatpickr'); ?>
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