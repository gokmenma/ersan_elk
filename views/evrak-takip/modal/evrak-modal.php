<?php 
use App\Helper\Form;
$araclar_list = $Evrak->getActiveVehicles();
?>

<div class="modal fade" id="evrakModal" tabindex="-1" aria-labelledby="evrakModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark py-3 px-1">
                <div class="modal-title-section ps-3">
                    <div class="modal-icon-box bg-warning-subtle text-warning">
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title text-white fw-bold" id="evrakModalLabel">Yeni Gelen Evrak Kaydı</h5>
                        <p class="modal-subtitle text-white-50">Evrak bilgilerini eksiksiz doldurunuz.</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white me-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="evrakForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="evrak_id" value="">
                    <input type="hidden" name="action" value="evrak-kaydet">

                    <div class="row g-4">
                        <!-- Sol Kolon: Evrak Temel Bilgileri -->
                        <div class="col-md-6 border-end">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-primary-subtle text-primary p-1 rounded-pill me-2">
                                    <i data-feather="info" style="width: 14px; height: 14px;"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Evrak Detayları</h6>
                            </div>
                            
                            <div class="mb-3">
                                <input type="hidden" name="evrak_tipi" id="tipGelen" value="gelen">
                                <div class="d-flex align-items-center gap-2 p-2 bg-success-subtle text-success rounded-3 border border-success-subtle">
                                    <i data-feather="download" style="width:16px"></i><span class="fw-bold small">GELEN EVRAK</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php echo Form::FormFloatInput('text', 'tarih', date('d.m.Y'), 'Evrak Tarihi', 'Evrak Tarihi *', 'calendar', 'form-control flatpickr', true); ?>
                            </div>

                            <div class="mb-3">
                                <?php echo Form::FormFloatInput('text', 'evrak_no', '', 'Evrak Numarası', 'Evrak No / Kayıt No', 'hash'); ?>
                            </div>

                            <div class="mb-3">
                                <?php 
                                $konu_options = [
                                    '' => 'Seçiniz veya Yazınız...',
                                    'İcra Yazısı' => 'İcra Yazısı',
                                    'Haciz Kaldırma Yazısı' => 'Haciz Kaldırma Yazısı',
                                    'Maaş Haczi' => 'Maaş Haczi',
                                    'Sigorta Giriş/Çıkış' => 'Sigorta Giriş/Çıkış',
                                    'Resmi Yazışma' => 'Resmi Yazışma'
                                ];
                                echo Form::FormSelect2('konu', $konu_options, '', 'Evrak Konusu *', 'type', 'key', '', 'form-select evrak-select2-tags', true); 
                                ?>
                            </div>

                            <div class="mb-3">
                                <?php echo Form::FormFloatInput('text', 'kurum_adi', '', 'Kurum / Firma Adı', 'Gelen / Giden Kurum Adı *', 'home', 'form-control', true); ?>
                            </div>
                        </div>

                        <!-- Sağ Kolon: Atama ve İşlem -->
                        <div class="col-md-6 text-dark">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-success-subtle text-success p-1 rounded-pill me-2">
                                    <i data-feather="user-check" style="width: 14px; height: 14px;"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Zimmet & Atama</h6>
                            </div>
                            
                            <div class="mb-3">
                                <?php 
                                $per_options_ofis = ['' => 'Seçiniz...'];
                                $per_options_ilgili = ['' => 'Seçiniz...'];
                                
                                foreach ($personeller as $per) {
                                    // Zimmetlenen (Ofis) için sadece BÜRO departmanı
                                    if ($per->departman == 'BÜRO') {
                                        $per_options_ofis[$per->id] = $per->adi_soyadi;
                                    }
                                    
                                    // İlgili personel için hepsi
                                    $per_options_ilgili[$per->id] = $per->adi_soyadi;
                                }
                                
                                echo Form::FormSelect2('personel_id', $per_options_ofis, '', 'Zimmetlenen Personel (Ofis)', 'user-check', 'key', '', 'form-select evrak-select2'); 
                                ?>
                            </div>

                            <div class="mb-3">
                                <?php 
                                echo Form::FormSelect2('ilgili_personel_id', $per_options_ilgili, '', 'İlgili Personel', 'user', 'key', '', 'form-select evrak-select2'); 
                                ?>
                            </div>

                            <div id="bildirimContainer" class="mb-3 px-3 d-none align-items-center justify-content-between bg-light rounded-3 py-2 border border-dashed">
                                <div class="d-flex align-items-center">
                                    <i data-feather="bell" class="text-warning me-2" style="width: 18px;"></i>
                                    <span class="small fw-bold text-muted">Personele Bildirim Gönder</span>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="personel_bildirim_durumu" value="1" id="personel_bildir">
                                </div>
                            </div>

                            <hr class="my-3 opacity-50">

                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-warning-subtle text-warning p-1 rounded-pill me-2">
                                    <i data-feather="check-square" style="width: 14px; height: 14px;"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">İşlem & Cevap</h6>
                            </div>

                            <!-- Trafik Cezası Bilgileri (Dinamik) -->
                            <div id="trafficFineSection" class="d-none border border-dashed border-primary rounded-3 p-3 mb-3" style="background-color: rgba(var(--bs-primary-rgb), 0.04);">
                                <div class="d-flex align-items-center mb-3 text-primary">
                                    <span class="badge bg-primary text-white p-1 rounded-pill me-2">
                                        <i data-feather="alert-circle" style="width: 14px; height: 14px;"></i>
                                    </span>
                                    <h6 class="mb-0 fw-bold">Trafik Cezası Detayları</h6>
                                </div>
                                <div class="row g-2">
                                    <div class="col-12 mb-2">
                                        <label class="ps-1 mb-2 fw-bold text-muted small">Ceza Kime / Neye Yazıldı?</label>
                                        <div class="d-flex gap-3 p-2 bg-light rounded-3 border">
                                            <div class="form-check m-0 ps-4">
                                                <input class="form-check-input" type="radio" name="ceza_hedef_tipi" id="cezaHedefArac" value="arac" checked>
                                                <label class="form-check-label fw-bold small" for="cezaHedefArac">ARAÇ</label>
                                            </div>
                                            <div class="form-check m-0 ps-4">
                                                <input class="form-check-input" type="radio" name="ceza_hedef_tipi" id="cezaHedefPersonel" value="personel">
                                                <label class="form-check-label fw-bold small" for="cezaHedefPersonel">PERSONEL</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="cezaAracContainer" class="col-md-12 mb-2">
                                        <?php 
                                        $arac_options = ['' => 'Plaka Seçiniz...'];
                                        foreach ($araclar_list as $ar) {
                                            $label_ar = $ar->plaka . " - " . $ar->marka . " " . $ar->model;
                                            $arac_options[$ar->plaka] = $label_ar;
                                        }
                                        echo Form::FormSelect2('plaka', $arac_options, '', 'Araç Plakası', 'truck', 'key', '', 'form-select select2'); 
                                        ?>
                                        <div id="plakaFeedback" class="small mt-1 px-1 fw-bold" style="display:none; font-size: 11px;"></div>
                                    </div>
                                    <div id="cezaPersonelContainer" class="col-md-12 mb-2 d-none">
                                        <?php
                                        echo Form::FormSelect2('ceza_personel_id', $per_options_ilgili, '', 'Ceza Yazılan Personel', 'user', 'key', '', 'form-select evrak-select2');
                                        ?>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <?php echo Form::FormFloatInput('number', 'ceza_tutari', '', 'Ceza Tutarı (TL)', 'Ceza Tutarı', 'credit-card', 'form-control'); ?>
                                    </div>
                                    <div class="col-md-6 mb-0">
                                        <?php echo Form::FormFloatInput('number', 'tutar', '', 'Kesilecek Tutar (TL)', 'Kesilecek Tutar', 'dollar-sign', 'form-control'); ?>
                                        <div class="small mt-1 px-1 text-muted" style="font-size: 10px;">Boş bırakılırsa Ceza Tutarı geçerli olur.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gelen Evrak için Cevap Alanı -->
                            <div id="gelenCevapSection">
                                <div class="mb-3 px-3 d-flex align-items-center justify-content-between bg-dark-subtle rounded-3 py-2 border">
                                    <div class="d-flex align-items-center">
                                        <i data-feather="message-circle" class="text-primary me-2" style="width: 18px;"></i>
                                        <span class="small fw-bold text-dark">Cevap Verildi mi?</span>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="cevap_verildi_mi" value="1" id="cevap_verildi">
                                    </div>
                                </div>

                                <div id="cevapTarihiContainer" class="mb-3 d-none">
                                    <?php echo Form::FormFloatInput('text', 'cevap_tarihi', '', 'Gelen Evraka Verilen Cevap Tarihi', 'Cevap Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                </div>
                            </div>

                            <!-- Giden Evrak için İlişkilendirme -->
                            <div id="gidenIliskiSection" class="d-none">
                                <div class="mb-3">
                                    <?php 
                                    $gelen_options = ['' => 'Bu cevap hangi evraka ait? (Seçiniz)'];
                                    foreach ($gelen_evraklar as $ge) {
                                        $label_ge = $ge->evrak_no . " - " . $ge->konu . " (" . date('d.m.Y', strtotime($ge->tarih)) . ")";
                                        $gelen_options[\App\Helper\Security::encrypt($ge->id)] = $label_ge;
                                    }
                                    echo Form::FormSelect2('ilgili_evrak_id', $gelen_options, '', 'İlişkili Gelen Evrak', 'link', 'key', '', 'form-select evrak-select2'); 
                                    ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php echo Form::FormFileInput('dosya', 'Evrak Dosyası', 'upload-cloud'); ?>
                                <div id="mevcutDosya" class="mt-2" style="display:none;">
                                    <a href="#" target="_blank" class="btn btn-sm btn-soft-info w-100 fw-bold border-dashed rounded-3">
                                        <i data-feather="eye" class="icon-xs me-1"></i> Mevcut Dosyayı Gör
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 mt-0">
                            <label for="evrak_aciklama" class="form-label fw-bold text-muted small">EVRAK İÇERİĞİ</label>
                            <textarea id="evrak_aciklama" name="aciklama" class="form-control evrak-summernote"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top-0 p-3 px-4">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none px-3" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" id="btnPdfOnizle" class="btn btn-outline-primary px-4 fw-bold rounded-pill">
                    <i data-feather="eye" class="icon-xs me-1"></i> Resmî Yazı Önizle
                </button>
                <button type="submit" form="evrakForm" id="btnEvrakKaydet" class="btn btn-dark px-5 shadow-sm fw-bold rounded-pill">
                     Bilgileri Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="evrakPdfModal" tabindex="-1" aria-labelledby="evrakPdfModalLabel" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="evrakPdfModalLabel">Resmî Yazı Önizleme</h5>
                <a id="evrakPdfYeniSekme" href="#" target="_blank" class="btn btn-sm btn-outline-light ms-auto me-2 d-none">Yeni Sekmede Aç</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="min-height: 72vh;">
                <div id="evrakPdfLoader" class="position-absolute top-0 start-0 w-100 h-100 bg-white d-flex align-items-center justify-content-center" style="z-index:2;">
                    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div>
                </div>
                <iframe id="evrakPdfFrame" title="Evrak PDF Önizleme" class="w-100 border-0" style="height:72vh; display:none;"></iframe>
            </div>
        </div>
    </div>
</div>
