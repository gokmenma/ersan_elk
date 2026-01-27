<?php use App\Helper\Form; ?>
<!-- Araç Ekleme/Düzenleme Modal -->
<div class="modal fade" id="aracModal" tabindex="-1" aria-labelledby="aracModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary bg-gradient text-white">
                <h5 class="modal-title" id="aracModalLabel"><i data-feather="truck" class="me-2"></i>Yeni Araç Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="aracForm">
                    <input type="hidden" name="id" value="">

                    <div class="row g-3">
                        <!-- Sol Kolon -->
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i data-feather="info" class="me-1"></i>
                                Temel Bilgiler</h6>

                            <div class="mb-3">
                                <?php echo Form::FormFloatInput('text', 'plaka', null, '34 ABC 123', 'Plaka *', 'credit-card', 'form-control', true, null, 'on', false, 'style="text-transform: uppercase;"'); ?>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'marka', null, 'Ford, Renault...', 'Marka', 'truck'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'model', null, 'Focus, Clio...', 'Model', 'truck'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'model_yili', null, '2024', 'Model Yılı', 'calendar', 'form-control', false, null, 'on', false, 'min="1990" max="2030"'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'renk', null, 'Beyaz, Siyah...', 'Renk', 'aperture'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <?php
                                    $aracTipleri = [
                                        'binek' => 'Binek',
                                        'kamyonet' => 'Kamyonet',
                                        'kamyon' => 'Kamyon',
                                        'minibus' => 'Minibüs',
                                        'otobus' => 'Otobüs',
                                        'motosiklet' => 'Motosiklet',
                                        'diger' => 'Diğer'
                                    ];
                                    echo Form::FormSelect2('arac_tipi', $aracTipleri, null, 'Araç Tipi', 'truck');
                                    ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php
                                    $yakitTipleri = [
                                        'dizel' => 'Dizel',
                                        'benzin' => 'Benzin',
                                        'lpg' => 'LPG',
                                        'elektrik' => 'Elektrik',
                                        'hibrit' => 'Hibrit'
                                    ];
                                    echo Form::FormSelect2('yakit_tipi', $yakitTipleri, null, 'Yakıt Tipi', 'droplet');
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Kolon -->
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i data-feather="file-text"
                                    class="me-1"></i> Evrak & KM
                                Bilgileri</h6>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'sase_no', null, 'VIN Numarası', 'Şase No', 'hash'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'motor_no', null, '', 'Motor No', 'settings'); ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php echo Form::FormFloatInput('text', 'ruhsat_sahibi', null, 'Ad Soyad veya Firma Adı', 'Ruhsat Sahibi', 'user'); ?>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'baslangic_km', null, '0', 'Başlangıç KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                                <div class="col-6 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'guncel_km', null, '0', 'Güncel KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                            </div>




                        </div>
                        <div class="col-12">
                            <div class="row">
                                <h6 class="text-warning border-bottom pb-2 mb-3 mt-3"><i data-feather="calendar"
                                        class="me-1"></i> Tarihler</h6>
                                <div class="col-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'muayene_tarihi', null, '', 'Muayene Tarihi', 'calendar', 'form-control flatpickr', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                                <div class="col-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'sigorta_bitis_tarihi', null, '', 'Sigorta Bitiş', 'calendar', 'form-control flatpickr', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                                <div class="col-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'kasko_bitis_tarihi', null, '', 'Kasko Bitiş', 'calendar', 'form-control flatpickr', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Full Width -->
                        <div class="col-12">
                            <div class="row">
                                <div class="col-md-9 mb-3">
                                    <?php echo Form::FormFloatTextarea('notlar', null, 'Araç hakkında notlar...', 'Notlar', 'file-text'); ?>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <?php
                                    $durumlar = ['1' => 'Aktif', '0' => 'Pasif'];
                                    echo Form::FormSelect2('aktif_mi', $durumlar, '1', 'Durum', 'check-circle');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x label-icon"></i> Vazgeç</button>
                <button type="button" id="btnAracKaydet" class="btn btn-primary waves-effect btn-label waves-light">
                    <i class="bx bx-save label-icon"></i> Kaydet
                </button>

            </div>
        </div>
    </div>
</div>