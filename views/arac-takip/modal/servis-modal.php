<?php use App\Helper\Form; ?>
<!-- Servis Kaydı Ekleme/Düzenleme Modal -->
<div class="modal fade" id="servisModal" tabindex="-1" aria-labelledby="servisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 12px;">
            <!-- Üst Header - Tam Genişlik -->
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="tool" class="text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="servisModalLabel">Servis Kaydı</h5>
                        <small class="text-muted">Aracın bakım ve onarım detaylarını girin</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="row g-0 p-3 flex-grow-1 bg-light bg-opacity-50">
                <!-- Sol Resim Alanı -->
                <div class="col-md-4 d-none d-md-block">
                    <div class="modal-image-panel h-100 rounded-3 overflow-hidden position-relative v-servis" style="min-height: 500px;">
                        <div class="modal-image-bg" style="background-image: url('assets/images/modals/servis_modal.png'); opacity: 0.7;"></div>
                        <div class="modal-image-overlay"></div>
                        <div class="modal-image-content d-flex flex-column justify-content-center align-items-center h-100 position-relative p-1" style="z-index: 3;">
                             <div class="p-4 shadow-lg text-dark text-center d-flex flex-column justify-content-center align-items-center" style="border-radius: 12px; width: 98%; height: 98%; background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px);">
                                 <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                     <i data-feather="tool" class="text-primary" style="width: 40px; height: 40px;"></i>
                                 </div>
                                 <h3 class="fw-bold mb-3">Bakım & Onarım</h3>
                                 <p class="text-muted px-3 mb-0" style="font-size: 1.1rem;">Araç servis geçmişini, periyodik bakımları ve onarım detaylarını profesyonelce kayıt altına alın.</p>
                             </div>
                             <div class="position-absolute bottom-0 w-100 text-center pb-4">
                                 <span class="text-white fs-6 fw-light opacity-50">Ersan Elektrik Filo</span>
                             </div>
                        </div>
                    </div>
                </div>
                <!-- Sağ Form Alanı -->
                <div class="col-md-8 ps-3">
                    <div class="d-flex flex-column shadow-sm bg-white rounded-3 overflow-hidden h-100">
                        <div class="modal-body p-4 pt-4 flex-grow-1">
                            <form id="servisForm" class="pe-0 pe-md-3">
                                <input type="hidden" name="id" value="">
                                
                                <!-- Tab Navigation -->
                                <ul class="nav nav-pills nav-justified mb-4 p-1 bg-light border rounded-3" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active fw-semibold" id="servis-giris-tab" data-bs-toggle="pill" data-bs-target="#servis-giris-bilgileri" type="button" role="tab" aria-controls="servis-giris-bilgileri" aria-selected="true" style="border-radius: 8px;">
                                            <i class="bx bx-log-in-circle me-1"></i> Giriş Bilgileri
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link fw-semibold" id="servis-cikis-tab" data-bs-toggle="pill" data-bs-target="#servis-cikis-bilgileri" type="button" role="tab" aria-controls="servis-cikis-bilgileri" aria-selected="false" style="border-radius: 8px;">
                                            <i class="bx bx-log-out-circle me-1"></i> Çıkış Bilgileri
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link fw-semibold" id="servis-ikame-tab" data-bs-toggle="pill" data-bs-target="#servis-ikame-bilgileri" type="button" role="tab" aria-controls="servis-ikame-bilgileri" aria-selected="false" style="border-radius: 8px;">
                                            <i class="bx bx-transfer me-1"></i> İkame Araç
                                        </button>
                                    </li>
                                </ul>

                                <!-- Tab Content -->
                                <div class="tab-content">
                                    <!-- Giriş Bilgileri Tab -->
                                    <div class="tab-pane fade show active" id="servis-giris-bilgileri" role="tabpanel" aria-labelledby="servis-giris-tab">
                                        <div class="row g-3">
                                            <div class="col-md-8">
                                                <?php
                                                $araclar = (new App\Model\AracModel())->getAktifAraclar();
                                                $aracOptions = ['' => 'Araç Seçin'];
                                                foreach ($araclar as $arac) {
                                                    $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                                }
                                                echo Form::FormSelect2('arac_id', $aracOptions, null, 'Araç *', 'truck', 'key', '', 'form-select select2', true);
                                                ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php echo Form::FormFloatInput('text', 'servis_tarihi', date('d.m.Y'), '', 'Servis Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                                            </div>

                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('number', 'giris_km', null, '0', 'Servis Giriş KM *', 'activity', 'form-control', true, null, 'on', false, 'min="0"'); ?>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'servis_adi', null, 'Servis/Usta Adı', 'Servis Noktası', 'bx bx-store'); ?>
                                            </div>

                                            <div class="col-12">
                                                <div class="p-3 border rounded bg-light bg-opacity-50 mt-1">
                                                    <div class="text-muted small mb-3 fw-semibold"><i class="bx bx-error-circle me-1"></i>Şikayet / Neden</div>
                                                    <?php echo Form::FormFloatTextarea('servis_nedeni', null, 'Bakım, Onarım, Kaza vs.', 'Servis Nedeni / Şikayet', 'help-circle', 'form-control', false, '100px'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Çıkış Bilgileri Tab -->
                                    <div class="tab-pane fade" id="servis-cikis-bilgileri" role="tabpanel" aria-labelledby="servis-cikis-tab">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'iade_tarihi', null, '', 'Servis Çıkış Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('number', 'cikis_km', null, '0', 'Servis Çıkış KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                            </div>

                                            <div class="col-12">
                                                <div class="p-3 border rounded bg-light bg-opacity-50 mt-1 mb-1">
                                                    <div class="text-muted small mb-3 fw-semibold"><i class="bx bx-wrench me-1"></i>Yapılan İşlemler</div>
                                                    <?php echo Form::FormFloatTextarea('yapilan_islemler', null, 'Yağ değişimi, fren balatası değiştirildi vs.', 'Yapılan İşlemler', 'bx bx-list-check', 'form-control', false, '100px'); ?>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'tutar', null, '0.00', 'Toplam Tutar (₺)', 'bx bx-purchase-tag', 'form-control masker-money'); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'fatura_no', null, '', 'Fatura/Fiş No', 'bx bx-receipt'); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- İkame Araç Tab -->
                                    <div class="tab-pane fade" id="servis-ikame-bilgileri" role="tabpanel" aria-labelledby="servis-ikame-tab">
                                        <div class="alert alert-info py-2 mb-3 small">
                                            <i class="bx bx-info-circle me-1"></i>
                                            <strong>Otomatik İşlemler:</strong> Servis tarafından verilen ikame araç bilgilerini girin. 
                                            Eğer servise giren araç zimmetliyse, ikame araç otomatik olarak aynı personele zimmetlenecektir.
                                            Servis çıkışında ikame araç otomatik iade edilecek ve asıl araç geri zimmetlenecektir.
                                        </div>
                                        <input type="hidden" name="ikame_arac_id" value="">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <?php echo Form::FormFloatInput('text', 'ikame_plaka', null, '34 XX 1234', 'İkame Araç Plaka', 'bx bx-car'); ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php echo Form::FormFloatInput('text', 'ikame_marka', null, 'Marka', 'İkame Araç Marka', 'bx bx-tag'); ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php echo Form::FormFloatInput('text', 'ikame_model', null, 'Model', 'İkame Araç Model', 'bx bx-tag'); ?>
                                            </div>

                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'ikame_alis_tarihi', null, '', 'İkame Alış Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('text', 'ikame_iade_tarihi', null, '', 'İkame İade Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('number', 'ikame_teslim_km', null, '0', 'İkame Teslim KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php echo Form::FormFloatInput('number', 'ikame_iade_km', null, '0', 'İkame İade KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-white p-3 border-top">
                            <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">Vazgeç</button>
                            <button type="button" id="btnServisKaydet" class="btn btn-dark px-4 shadow-sm">
                                <i class="bx bx-save me-1"></i> Kaydet
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>