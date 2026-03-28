<?php use App\Helper\Form; ?>

<div class="modal fade" id="evrakModal" tabindex="-1" aria-labelledby="evrakModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-dark py-3 px-1">
                <div class="modal-title-section ps-3">
                    <div class="modal-icon-box bg-warning-subtle text-warning">
                        <i data-feather="file-text"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title text-white fw-bold" id="evrakModalLabel">Yeni Evrak Kaydı</h5>
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
                                <label class="ps-1 mb-2 fw-bold text-muted small text-uppercase" style="letter-spacing: 0.5px;">Evrak Tipi</label>
                                <div class="d-flex gap-3 p-2 bg-light rounded-3 border">
                                    <div class="form-check form-radio-outline form-radio-success m-0 ps-4">
                                        <input class="form-check-input tip-radio" type="radio" name="evrak_tipi" id="tipGelen" value="gelen" checked>
                                        <label class="form-check-label fw-bold small" for="tipGelen">GELEN</label>
                                    </div>
                                    <div class="form-check form-radio-outline form-radio-danger m-0 ps-4">
                                        <input class="form-check-input tip-radio" type="radio" name="evrak_tipi" id="tipGiden" value="giden">
                                        <label class="form-check-label fw-bold small" for="tipGiden">GİDEN</label>
                                    </div>
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
                                $per_options = ['' => 'Seçiniz...'];
                                foreach ($personeller as $per) {
                                    $per_options[$per->id] = $per->adi_soyadi;
                                }
                                echo Form::FormSelect2('personel_id', $per_options, '', 'Zimmetlenen Personel (Ofis)', 'user-check', 'key', '', 'form-select evrak-select2'); 
                                ?>
                            </div>

                            <div class="mb-3">
                                <?php 
                                echo Form::FormSelect2('ilgili_personel_id', $per_options, '', 'İlgili Personel', 'user', 'key', '', 'form-select evrak-select2'); 
                                ?>
                            </div>

                            <div id="bildirimContainer" class="mb-3 px-3 d-none align-items-center justify-content-between bg-light rounded-3 py-2 border border-dashed">
                                <div class="d-flex align-items-center">
                                    <i data-feather="bell" class="text-warning me-2" style="width: 18px;"></i>
                                    <span class="small fw-bold text-muted">İlgili Personele Bildir</span>
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
                                        $gelen_options[$ge->id] = $label_ge;
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
                            <?php echo Form::FormFloatTextarea('aciklama', '', 'Açıklama / İçerik', 'Açıklama / İçerik', 'file-text', 'form-control', false, '80px'); ?>
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