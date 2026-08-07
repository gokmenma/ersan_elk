<?php use App\Helper\Form; ?>

<div class="modal fade" id="aracZimmetDuzenleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold text-white"><i class="bx bx-edit me-2"></i>Araç Zimmet Kaydı Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="aracZimmetDuzenleForm" enctype="multipart/form-data">
                <input type="hidden" name="zimmet_id" id="arac_duzenle_zimmet_id" value="0">
                <div class="modal-body p-4">
                    <!-- Araç ve Zimmet Özet Kartı -->
                    <div class="bg-light rounded p-3 mb-4 border">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Araç / Plaka</label>
                                <div id="arac_duzenle_plaka" class="fw-bold text-dark fs-6">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Personel</label>
                                <div id="arac_duzenle_personel" class="fw-bold text-dark fs-6">-</div>
                            </div>
                            <div class="col-md-4">
                                <label class="text-muted small text-uppercase fw-bold mb-1 d-block">Durum</label>
                                <div id="arac_duzenle_durum" class="fw-bold">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Zimmet Teslim Bilgileri -->
                    <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bx bx-car me-1"></i>Zimmet Teslim Bilgileri</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="text" name="zimmet_tarihi" id="arac_duzenle_zimmet_tarihi" class="form-control flatpickr" required>
                                <label for="arac_duzenle_zimmet_tarihi">Zimmet Tarihi *</label>
                                <div class="form-floating-icon">
                                    <i data-feather="calendar"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-floating form-floating-custom">
                                <input type="number" name="teslim_km" id="arac_duzenle_teslim_km" class="form-control" min="0">
                                <label for="arac_duzenle_teslim_km">Teslim KM</label>
                                <div class="form-floating-icon">
                                    <i data-feather="speedometer"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- İade Bilgileri -->
                    <div id="arac_duzenle_iade_section" class="d-none">
                        <h6 class="text-info border-bottom pb-2 mb-3 mt-2"><i class="bx bx-undo me-1"></i>İade Bilgileri</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-floating form-floating-custom">
                                    <input type="text" name="iade_tarihi" id="arac_duzenle_iade_tarihi" class="form-control flatpickr">
                                    <label for="arac_duzenle_iade_tarihi">İade Tarihi</label>
                                    <div class="form-floating-icon">
                                        <i data-feather="calendar"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-floating form-floating-custom">
                                    <input type="number" name="iade_km" id="arac_duzenle_iade_km" class="form-control" min="0">
                                    <label for="arac_duzenle_iade_km">İade KM</label>
                                    <div class="form-floating-icon">
                                        <i data-feather="speedometer"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notlar -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-floating form-floating-custom">
                                <textarea name="notlar" id="arac_duzenle_notlar" class="form-control" style="height: 80px" placeholder="Notlar"></textarea>
                                <label for="arac_duzenle_notlar">Zimmet / İade Notları</label>
                                <div class="form-floating-icon">
                                    <i data-feather="file-text"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fotoğraf Yükleme Alanları -->
                    <h6 class="text-secondary border-bottom pb-2 mb-3 mt-2"><i class="bx bx-camera me-1"></i>Zimmet & İade Fotoğrafları</h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white h-100">
                                <label class="form-label small fw-semibold text-muted mb-1"><i class="bx bx-upload me-1"></i>Yeni Teslim Fotoğrafı Ekle</label>
                                <input type="file" class="form-control form-control-sm" name="teslim_fotograflari[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                <small class="text-muted d-block mt-1">Teslim anındaki kilometre/araç durum fotoğrafları.</small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="p-3 border rounded bg-white h-100">
                                <label class="form-label small fw-semibold text-muted mb-1"><i class="bx bx-upload me-1"></i>Yeni İade Fotoğrafı Ekle</label>
                                <input type="file" class="form-control form-control-sm" name="iade_fotograflari[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                <small class="text-muted d-block mt-1">İade anındaki kilometre/araç durum fotoğrafları.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Mevcut Fotoğraflar -->
                    <div class="p-3 border rounded bg-light">
                        <label class="form-label small fw-bold text-dark mb-2">Yüklü Fotoğraflar</label>
                        <div id="arac_duzenle_mevcut_fotolar" class="d-flex flex-wrap gap-2">
                            <span class="text-muted small">Yükleniyor...</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" id="btnAracZimmetDuzenleKaydet" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>Değişiklikleri Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
