<?php use App\Helper\Form; ?>

<style>
    /* Modal Tasarım Düzeltmeleri */
    #evrakModal .modal-content {
        box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
        border-radius: 20px !important;
    }
    
    /* Ortak Floating Yapısı (Manual) */
    .custom-floating {
        position: relative;
        background: #fff;
        border-radius: 12px;
    }

    .custom-floating label {
        position: absolute;
        top: 8px;
        left: 55px;
        font-size: 11px;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        z-index: 5;
        pointer-events: none;
        letter-spacing: 0.3px;
    }

    .custom-floating .form-control, 
    .custom-floating .form-select,
    .custom-floating .select2-container--default .select2-selection--single {
        height: 62px !important;
        padding-top: 26px !important;
        padding-left: 55px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        transition: all 0.2s;
        background-color: #fff !important;
    }

    .custom-floating .form-control:focus {
        border-color: #1c84ee !important;
        box-shadow: 0 0 0 4px rgba(28, 132, 238, 0.1) !important;
    }

    .custom-floating .icon-box {
        position: absolute;
        left: 0;
        top: 0;
        width: 55px;
        height: 62px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 4;
        color: #94a3b8;
        border-right: 1px solid transparent;
    }

    .custom-floating .icon-box i,
    .custom-floating .icon-box svg {
        width: 20px;
        height: 20px;
    }

    /* Select2 Özel */
    .custom-floating .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        line-height: normal !important;
        margin-top: 0 !important;
        color: #334155 !important;
        font-weight: 500;
    }
    
    .custom-floating .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 25px !important;
    }

    /* Flatpickr Özel */
    .flatpickr-input[readonly] {
        background-color: #fff !important;
    }
    
    /* Gelen/Giden Badge Stil */
    .evrak-tip-container {
        background: #f8fafc;
        padding: 12px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }

    .border-end-md {
        border-right: 1px solid #f1f5f9;
    }
    
    @media (max-width: 767px) {
        .border-end-md { border-right: none; }
    }
</style>

<div class="modal fade" id="evrakModal" tabindex="-1" aria-labelledby="evrakModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark py-3 px-4">
                <h5 class="modal-title text-white fw-bold d-flex align-items-center" id="evrakModalLabel">
                    <i data-feather="plus" class="me-2 text-warning"></i>Yeni Evrak Kaydı
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="evrakForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="evrak_id" value="">
                    <input type="hidden" name="action" value="evrak-kaydet">

                    <div class="row g-4">
                        <!-- Sol Kolon -->
                        <div class="col-md-6 border-end-md">
                            <div class="d-flex align-items-center mb-4">
                                <span class="badge bg-primary-subtle text-primary p-2 rounded-3 me-2">
                                    <i data-feather="file-text" style="width: 16px; height: 16px;"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Evrak Detayları</h6>
                            </div>
                            
                            <div class="mb-4">
                                <label class="ps-1 mb-2 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">Evrak Tipi</label>
                                <div class="d-flex gap-3 evrak-tip-container">
                                    <div class="form-check form-radio-outline form-radio-success m-0">
                                        <input class="form-check-input" type="radio" name="evrak_tipi" id="tipGelen" value="gelen" checked>
                                        <label class="form-check-label fw-bold small" for="tipGelen">GELEN</label>
                                    </div>
                                    <div class="form-check form-radio-outline form-radio-danger m-0">
                                        <input class="form-check-input" type="radio" name="evrak_tipi" id="tipGiden" value="giden">
                                        <label class="form-check-label fw-bold small" for="tipGiden">GİDEN</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 custom-floating">
                                <label>Evrak Tarihi *</label>
                                <div class="icon-box"><i data-feather="calendar"></i></div>
                                <input type="text" name="tarih" id="tarih" class="form-control flatpickr" value="<?php echo date('d.m.Y'); ?>" required placeholder=" ">
                            </div>

                            <div class="mb-3 custom-floating">
                                <label>Evrak No / Kayıt No</label>
                                <div class="icon-box"><i data-feather="hash"></i></div>
                                <input type="text" name="evrak_no" id="evrak_no" class="form-control" placeholder=" ">
                            </div>

                            <div class="mb-3 custom-floating">
                                <label>Evrak Konusu *</label>
                                <div class="icon-box"><i data-feather="type"></i></div>
                                <input type="text" name="konu" id="konu" class="form-control" required placeholder=" ">
                            </div>
                        </div>

                        <!-- Sağ Kolon -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-4">
                                <span class="badge bg-info-subtle text-info p-2 rounded-3 me-2">
                                    <i data-feather="users" style="width: 16px; height: 16px;"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Kurum & Zimmet</h6>
                            </div>
                            
                            <div class="mb-3 custom-floating">
                                <label>Gelen / Giden Kurum Adı *</label>
                                <div class="icon-box"><i data-feather="home"></i></div>
                                <input type="text" name="kurum_adi" id="kurum_adi" class="form-control" required placeholder=" ">
                            </div>

                            <div class="mb-3 custom-floating">
                                <label>Zimmetlenen Personel</label>
                                <div class="icon-box"><i data-feather="user"></i></div>
                                <select name="personel_id" id="personel_id" class="form-select select2">
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($personeller as $per): ?>
                                        <option value="<?php echo $per->id; ?>"><?php echo $per->adi_soyadi; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3 custom-floating">
                                <label>Evrak Dosyası (PDF, Görsel)</label>
                                <div class="icon-box"><i data-feather="upload-cloud"></i></div>
                                <input type="file" name="dosya" id="evrak_dosya" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <div id="mevcutDosya" class="mt-2" style="display:none;">
                                    <a href="#" target="_blank" class="btn btn-sm btn-soft-info w-100 fw-bold border-dashed rounded-3">
                                        <i data-feather="eye" class="icon-xs me-1"></i> Mevcut Dosyayı Görüntüle
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <div class="mb-0 custom-floating">
                                <label>Evrak İçeriği ve Açıklama</label>
                                <div class="icon-box" style="height: 100px; display: flex; align-items: start; padding-top: 15px;"><i data-feather="file-text"></i></div>
                                <textarea name="aciklama" id="aciklama" class="form-control" style="min-height: 100px; padding-top: 30px !important;" placeholder=" "></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top-0 p-3 px-4">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none px-3" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" form="evrakForm" id="btnEvrakKaydet" class="btn btn-dark px-5 shadow-sm fw-bold rounded-pill">
                     Bilgileri Kaydet
                </button>
            </div>
        </div>
    </div>
</div>