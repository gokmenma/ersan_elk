<?php use App\Helper\Form; ?>
<!-- Araç Ekleme/Düzenleme Modal -->
<div class="modal fade" id="aracModal" tabindex="-1" aria-labelledby="aracModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 12px;">
            <!-- Üst Header - Tam Genişlik -->
            <div class="modal-header bg-white p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i data-feather="truck" class="text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold text-dark" id="aracModalLabel">Yeni Araç Ekle</h5>
                        <small class="text-muted">Yeni kayıt oluşturmak için bilgileri doldurun.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="row g-0 p-3 flex-grow-1 bg-light bg-opacity-50">
                <!-- Sol Resim Alanı - Header Altında -->
                <div class="col-md-4 d-none d-md-block">
                    <div class="modal-image-panel h-100 rounded-3 overflow-hidden position-relative" style="min-height: 550px;">
                        <div class="modal-image-bg" style="background-image: url('assets/images/modals/arac_modal.png'); opacity: 0.7;"></div>
                        <div class="modal-image-overlay"></div>
                        <div class="modal-image-content d-flex flex-column justify-content-center align-items-center h-100 position-relative p-1" style="z-index: 3;">
                             <div class="p-4 shadow-lg text-dark text-center d-flex flex-column justify-content-center align-items-center" style="border-radius: 12px; width: 98%; height: 98%; background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px);">
                                 <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                     <i data-feather="truck" class="text-primary" style="width: 40px; height: 40px;"></i>
                                 </div>
                                 <h3 class="fw-bold mb-3">Araç Kartı</h3>
                                 <p class="text-muted px-3 mb-0" style="font-size: 1.1rem;">Filo araç bilgilerini, teknik detayları ve evrak takibini tek noktadan profesyonelce yönetin.</p>
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
                        <div class="modal-body p-0 flex-grow-1">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs nav-tabs-custom nav-justified bg-light bg-opacity-50 border-bottom" id="aracModalTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active py-3" data-bs-toggle="tab" data-no-url-update="true" href="#tab-genel" role="tab">
                                    <i class="bx bx-info-circle fs-5 d-block mb-1"></i>
                                    <span>Genel Bilgiler</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-3" data-bs-toggle="tab" data-no-url-update="true" href="#tab-teknik" role="tab">
                                    <i class="bx bx-cog fs-5 d-block mb-1"></i>
                                    <span>Teknik & KM</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link py-3" data-bs-toggle="tab" data-no-url-update="true" href="#tab-tarih" role="tab">
                                    <i class="bx bx-calendar fs-5 d-block mb-1"></i>
                                    <span>Evraklar & Notlar</span>
                                </a>
                            </li>
                        </ul>

                        <form id="aracForm" class="p-4 pe-5">
                            <input type="hidden" name="id" value="">
                            <div class="tab-content mt-2">
                                <!-- Tab 1: Genel Bilgiler -->
                                <div class="tab-pane active" id="tab-genel" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'plaka', null, '34 ABC 123', 'Plaka *', 'credit-card', 'form-control fw-bold', true, null, 'on', false, 'style="text-transform: uppercase; font-size: 1.1rem; color: #556ee6;"'); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormSelect2('aktif_mi', ['1' => 'Aktif', '0' => 'Pasif'], '1', 'Durum', 'check-circle'); ?>
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'marka', null, 'Ford, Renault...', 'Marka', 'truck'); ?>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'model', null, 'Focus, Clio...', 'Model', 'truck'); ?>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <?php echo Form::FormFloatInput('number', 'model_yili', null, date('Y'), 'Model Yılı', 'calendar', 'form-control', false, null, 'on', false, 'min="1990" max="2030"'); ?>
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'renk', null, 'Beyaz, Siyah...', 'Renk', 'aperture'); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormSelect2('departmani', \App\Helper\Helper::DEPARTMAN, null, 'Departman', 'briefcase'); ?>
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <?php
                                            $aracTipleri = ['binek' => 'Binek', 'kamyonet' => 'Kamyonet', 'kamyon' => 'Kamyon', 'minibus' => 'Minibüs', 'otobus' => 'Otobüs', 'motosiklet' => 'Motosiklet', 'diger' => 'Diğer'];
                                            echo Form::FormSelect2('arac_tipi', $aracTipleri, null, 'Araç Tipi', 'truck');
                                            ?>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <?php
                                            $yakitTipleri = ['dizel' => 'Dizel', 'benzin' => 'Benzin', 'lpg' => 'LPG', 'elektrik' => 'Elektrik', 'hibrit' => 'Hibrit'];
                                            echo Form::FormSelect2('yakit_tipi', $yakitTipleri, null, 'Yakıt Tipi', 'droplet');
                                            ?>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <?php
                                            $mulkiyetTipleri = ['Şirket Aracı' => 'Şirket Aracı', 'Personel Aracı' => 'Personel Aracı', 'Kiralık Araç' => 'Kiralık Araç'];
                                            echo Form::FormSelect2('mulkiyet', $mulkiyetTipleri, null, 'Mülkiyet Durumu', 'key');
                                            ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab 2: Teknik & KM -->
                                <div class="tab-pane" id="tab-teknik" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-info border-0 bg-info bg-opacity-10 d-flex align-items-center mb-4" role="alert">
                                                <i class="bx bx-info-circle fs-4 me-2 text-info"></i>
                                                <div class="small">Araç şase ve motor bilgilerini ruhsat üzerinden kontrol ederek giriniz.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'sase_no', null, 'VIN Numarası', 'Şase No', 'hash'); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'motor_no', null, '', 'Motor No', 'settings'); ?>
                                        </div>
                                        <div class="col-12 mb-2">
                                            <?php echo Form::FormFloatInput('text', 'ruhsat_sahibi', null, 'Ad Soyad veya Firma Adı', 'Ruhsat Sahibi', 'user'); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('number', 'baslangic_km', null, '0', 'Başlangıç KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <?php echo Form::FormFloatInput('number', 'guncel_km', null, '0', 'Güncel KM', 'activity', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tab 3: Evrak ve Tarihler -->
                                <div class="tab-pane" id="tab-tarih" role="tabpanel">
                                    <div class="row g-3">
                                        <div class="col-md-4 mb-2">
                                            <div class="p-2 border rounded bg-light bg-opacity-50">
                                                <div class="text-muted small mb-1 fw-semibold"><i class="bx bx-shield-quarter me-1"></i>Muayene</div>
                                                <?php echo Form::FormFloatInput('text', 'muayene_bitis_tarihi', null, '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr', false, null, 'on', false); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="p-2 border rounded bg-light bg-opacity-50">
                                                <div class="text-muted small mb-1 fw-semibold"><i class="bx bx-check-shield me-1"></i>Sigorta</div>
                                                <?php echo Form::FormFloatInput('text', 'sigorta_bitis_tarihi', null, '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr', false, null, 'on', false); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <div class="p-2 border rounded bg-light bg-opacity-50">
                                                <div class="text-muted small mb-1 fw-semibold"><i class="bx bx-lock-shield me-1"></i>Kasko</div>
                                                <?php echo Form::FormFloatInput('text', 'kasko_bitis_tarihi', null, '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr', false, null, 'on', false); ?>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <?php echo Form::FormFloatTextarea('notlar', null, 'Araç hakkında notlar...', 'Kayda Ait Notlar', 'file-text', 'form-control', false, '150px'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer bg-white p-3 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4 me-2" data-bs-dismiss="modal">
                            <i class="bx bx-x me-1"></i> Vazgeç
                        </button>
                        <button type="button" id="btnAracKaydet" class="btn btn-dark px-4 shadow-sm">
                            <i class="bx bx-save me-1"></i> Kaydet
                        </button>
                    </div>
                    </div> <!-- NEW: Closing div for form wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Premium Design System */
    .nav-tabs-custom .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: #495057;
        font-weight: 500;
        transition: all 0.3s ease;
        text-align: center;
    }

    .nav-tabs-custom .nav-link.active {
        color: #556ee6;
        background-color: transparent;
        border-bottom: 2px solid #556ee6;
    }

    .modal-header .btn-close {
        background-color: rgba(0, 0, 0, 0.05);
        padding: 0.5rem;
        border-radius: 50%;
        opacity: 0.5;
    }

    /* Modal Image Animation Effects */
    .modal-image-panel {
        background-color: #1a1d21;
        overflow: hidden;
    }

    .modal-image-bg {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background-size: cover;
        background-position: center;
        transition: transform 1.2s cubic-bezier(0.25, 1, 0.5, 1), filter 1.2s ease;
        filter: blur(8px) brightness(0.6);
        transform: scale(1.15);
        z-index: 1;
    }

    .modal.show .modal-image-bg {
        filter: blur(0px) brightness(1);
        transform: scale(1);
    }

    .modal-image-overlay {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 2;
        opacity: 0.4;
        transition: opacity 1s ease;
        background: linear-gradient(180deg, rgba(85, 110, 230, 0.2) 0%, rgba(20, 20, 30, 0.8) 100%);
    }

    .modal.show .modal-image-overlay {
        opacity: 0.25;
    }

    .modal-image-content {
        transition: all 1s ease;
        transform: translateY(20px);
        opacity: 0;
    }

    .modal.show .modal-image-content {
        transform: translateY(0);
        opacity: 1;
    }
</style>