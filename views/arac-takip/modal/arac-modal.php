<!-- Araç Ekleme/Düzenleme Modal -->
<div class="modal fade" id="aracModal" tabindex="-1" aria-labelledby="aracModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary bg-gradient text-white">
                <h5 class="modal-title" id="aracModalLabel"><i class="bx bx-car me-2"></i>Yeni Araç Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="aracForm">
                    <input type="hidden" name="id" value="">

                    <div class="row g-3">
                        <!-- Sol Kolon -->
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bx bx-info-circle me-1"></i>
                                Temel Bilgiler</h6>

                            <div class="mb-3">
                                <label class="form-label">Plaka <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="plaka" required placeholder="34 ABC 123"
                                    style="text-transform: uppercase;">
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Marka</label>
                                    <input type="text" class="form-control" name="marka" placeholder="Ford, Renault...">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model" placeholder="Focus, Clio...">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Model Yılı</label>
                                    <input type="number" class="form-control" name="model_yili" min="1990" max="2030"
                                        placeholder="2024">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Renk</label>
                                    <input type="text" class="form-control" name="renk" placeholder="Beyaz, Siyah...">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Araç Tipi</label>
                                    <select class="form-select" name="arac_tipi">
                                        <option value="binek">Binek</option>
                                        <option value="kamyonet">Kamyonet</option>
                                        <option value="kamyon">Kamyon</option>
                                        <option value="minibus">Minibüs</option>
                                        <option value="otobus">Otobüs</option>
                                        <option value="motosiklet">Motosiklet</option>
                                        <option value="diger">Diğer</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Yakıt Tipi</label>
                                    <select class="form-select" name="yakit_tipi">
                                        <option value="dizel">Dizel</option>
                                        <option value="benzin">Benzin</option>
                                        <option value="lpg">LPG</option>
                                        <option value="elektrik">Elektrik</option>
                                        <option value="hibrit">Hibrit</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Sağ Kolon -->
                        <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="bx bx-file me-1"></i> Evrak & KM
                                Bilgileri</h6>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Şase No</label>
                                    <input type="text" class="form-control" name="sase_no" placeholder="VIN Numarası">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Motor No</label>
                                    <input type="text" class="form-control" name="motor_no">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ruhsat Sahibi</label>
                                <input type="text" class="form-control" name="ruhsat_sahibi"
                                    placeholder="Ad Soyad veya Firma Adı">
                            </div>

                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Başlangıç KM</label>
                                    <input type="number" class="form-control" name="baslangic_km" min="0"
                                        placeholder="0">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Güncel KM</label>
                                    <input type="number" class="form-control" name="guncel_km" min="0" placeholder="0">
                                </div>
                            </div>

                            <h6 class="text-warning border-bottom pb-2 mb-3 mt-3"><i
                                    class="bx bx-calendar-exclamation me-1"></i> Tarihler</h6>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label class="form-label">Muayene Tarihi</label>
                                    <input type="date" class="form-control" name="muayene_tarihi">
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">Sigorta Bitiş</label>
                                    <input type="date" class="form-control" name="sigorta_bitis_tarihi">
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">Kasko Bitiş</label>
                                    <input type="date" class="form-control" name="kasko_bitis_tarihi">
                                </div>
                            </div>
                        </div>

                        <!-- Full Width -->
                        <div class="col-12">
                            <div class="row">
                                <div class="col-md-9 mb-3">
                                    <label class="form-label">Notlar</label>
                                    <textarea class="form-control" name="notlar" rows="2"
                                        placeholder="Araç hakkında notlar..."></textarea>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Durum</label>
                                    <select class="form-select" name="aktif_mi">
                                        <option value="1">Aktif</option>
                                        <option value="0">Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="btnAracKaydet">
                    <i class="bx bx-save me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>