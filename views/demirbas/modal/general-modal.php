<?php
use App\Helper\Form;

// Kategorileri al
use App\Model\DemirbasKategoriModel;
$Kategori = new DemirbasKategoriModel();
$kategoriler = $Kategori->getActiveCategories();
?>

<div class="modal fade" id="demirbasModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-package me-2"></i>Demirbaş Ekle/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="demirbasForm">
                <input type="hidden" name="demirbas_id" id="demirbas_id" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="demirbas_no" class="form-label">Demirbaş No</label>
                            <input type="text" class="form-control" id="demirbas_no" name="demirbas_no"
                                placeholder="Örn: DB-001">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="kategori_id" class="form-label">Kategori <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="kategori_id" name="kategori_id" required>
                                <option value="">Kategori Seçiniz</option>
                                <?php foreach ($kategoriler as $kat): ?>
                                    <option value="<?php echo $kat->id; ?>"><?php echo $kat->kategori_adi; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="demirbas_adi" class="form-label">Demirbaş Adı <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="demirbas_adi" name="demirbas_adi"
                                placeholder="Demirbaş adını giriniz" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="marka" class="form-label">Marka</label>
                            <input type="text" class="form-control" id="marka" name="marka" placeholder="Marka">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model" placeholder="Model">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="seri_no" class="form-label">Seri No</label>
                            <input type="text" class="form-control" id="seri_no" name="seri_no"
                                placeholder="Seri numarası">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="miktar" class="form-label">Toplam Miktar <span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="miktar" name="miktar" value="1" min="1"
                                required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edinme_tarihi" class="form-label">Edinme Tarihi</label>
                            <input type="text" class="form-control flatpickr" id="edinme_tarihi" name="edinme_tarihi"
                                value="<?php echo date('d.m.Y'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edinme_tutari" class="form-label">Edinme Tutarı</label>
                            <div class="input-group">
                                <input type="text" class="form-control money" id="edinme_tutari" name="edinme_tutari"
                                    placeholder="0,00">
                                <span class="input-group-text">₺</span>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                                <option value="arizali">Arızalı</option>
                                <option value="hurda">Hurda</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="2"
                                placeholder="Açıklama giriniz"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="demirbasKaydet" class="btn btn-success">
                        <i class="bx bx-save me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>