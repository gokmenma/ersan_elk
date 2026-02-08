<div class="modal fade" id="zimmetDetayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i data-feather="info" class="me-2"></i>Zimmet Detayları ve Geçmişi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Üst Bilgi Kartı -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Demirbaş Bilgileri</h6>
                                <h5 class="mb-1" id="detay_demirbas_adi">-</h5>
                                <p class="mb-0 text-muted small">
                                    <span id="detay_marka_model">-</span> <br>
                                    Seri No: <span id="detay_seri_no">-</span>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="text-muted mb-2">Şu Anki Durum</h6>
                                <div id="detay_durum_badge" class="mb-2"></div>
                                <p class="mb-0">
                                    <strong>Personel:</strong> <span id="detay_personel">-</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hareket Detayları -->
                <h6 class="mb-3 border-bottom pb-2 text-primary"><i data-feather="repeat" class="me-1"></i> Zimmet
                    Hareketleri (Detay)</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover table-bordered border-primary-subtle">
                        <thead class="table-primary fw-bold">
                            <tr>
                                <th>İşlem Tipi</th>
                                <th class="text-center">Miktar</th>
                                <th>Tarih</th>
                                <th>Açıklama / İş Emri</th>
                                <th>Kaynak</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetHareketBody">
                            <!-- JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>

                <!-- Geçmiş Tablosu -->
                <h6 class="mb-3 border-bottom pb-2"><i data-feather="clock" class="me-1"></i> Personel Demirbaş Geçmişi
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Personel</th>
                                <th class="text-center">Miktar</th>
                                <th>Teslim Tarihi</th>
                                <th>İade Tarihi</th>
                                <th class="text-center">Durum</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetGecmisBody">
                            <!-- JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>