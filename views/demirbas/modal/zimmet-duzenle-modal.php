<?php use App\Helper\Form; ?>

<div class="modal fade" id="zimmetDuzenleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold text-white"><i class="bx bx-edit me-2"></i>Zimmet Kaydı Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="zimmetDuzenleForm" enctype="multipart/form-data">
                <input type="hidden" name="zimmet_id" id="duzenle_zimmet_id" value="0">
                <div class="modal-body p-4">
                    <!-- Zimmet Özet Kartı -->
                    <div class="bg-light rounded p-3 mb-4 border">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Demirbaş</label>
                                <div id="duzenle_demirbas_adi" class="fw-bold text-dark">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Personel</label>
                                <div id="duzenle_personel_adi" class="fw-bold text-dark">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Durum</label>
                                <div id="duzenle_durum_badge" class="fw-bold">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Zimmet Teslim Bilgileri -->
                    <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bx bx-calendar-event me-1"></i>Zimmet Teslim Bilgileri</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="text" name="teslim_tarihi" id="duzenle_teslim_tarihi" class="form-control flatpickr" required>
                                <label for="duzenle_teslim_tarihi">Teslim Tarihi *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="calendar"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="number" name="teslim_miktar" id="duzenle_teslim_miktar" class="form-control" min="1" required>
                                <label for="duzenle_teslim_miktar">Teslim Miktarı *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="hash"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <textarea name="aciklama" id="duzenle_aciklama" class="form-control" style="height: 80px" placeholder="Zimmet Açıklaması"></textarea>
                                <label for="duzenle_aciklama">Zimmet Açıklaması / Notlar</label>
                                <div class="form-floating-icon">
                                    <i data-feather="file-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- İade Bilgileri (İade Edilmiş Kayıtlar İçin) -->
                    <div id="duzenle_iade_section" class="d-none">
                        <h6 class="text-info border-bottom pb-2 mb-3 mt-2"><i class="bx bx-undo me-1"></i>İade / Tüketim Bilgileri</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating form-floating-custom">
                                    <input type="text" name="iade_tarihi" id="duzenle_iade_tarihi" class="form-control flatpickr">
                                    <label for="duzenle_iade_tarihi">İade Tarihi</label>
                                    <div class="form-floating-icon">
                                        <i data-feather="calendar"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-floating form-floating-custom">
                                    <textarea name="iade_aciklama" id="duzenle_iade_aciklama" class="form-control" style="height: 80px" placeholder="İade Notu"></textarea>
                                    <label for="duzenle_iade_aciklama">İade Açıklaması / Notlar</label>
                                    <div class="form-floating-icon">
                                        <i data-feather="file-text"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fotoğraflar Yükleme ve Mevcut Fotoğraflar -->
                    <h6 class="text-secondary border-bottom pb-2 mb-3 mt-2"><i class="bx bx-camera me-1"></i>Zimmet & İade Fotoğrafları</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white h-100">
                                <label class="form-label small fw-semibold text-muted mb-1"><i class="bx bx-upload me-1"></i>Yeni Teslim Fotoğrafı Ekle</label>
                                <input type="file" class="form-control form-control-sm" name="teslim_fotograflari[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                <small class="text-muted d-block mt-1">Zimmet verilirken çekilen fotoğraflar (JPG, PNG, WEBP, PDF).</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white h-100">
                                <label class="form-label small fw-semibold text-muted mb-1"><i class="bx bx-upload me-1"></i>Yeni İade Fotoğrafı Ekle</label>
                                <input type="file" class="form-control form-control-sm" name="iade_fotograflari[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                <small class="text-muted d-block mt-1">İade alınırken çekilen fotoğraflar (JPG, PNG, WEBP, PDF).</small>
                            </div>
                        </div>
                    </div>

                    <!-- Mevcut Fotoğraflar Listesi -->
                    <div class="p-3 border rounded bg-light">
                        <label class="form-label small fw-bold text-dark mb-2">Yüklü Fotoğraflar</label>
                        <div id="duzenle_mevcut_fotolar" class="d-flex flex-wrap gap-2">
                            <span class="text-muted small">Yükleniyor...</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="btnZimmetDuzenleKaydet" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>Değişiklikleri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
