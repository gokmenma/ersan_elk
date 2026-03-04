<div class="modal fade" id="zimmetDetayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i data-feather="info" class="me-2"></i>Zimmet Detayları ve Geçmişi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Üst Bilgi Kartı -->
                <div class="card bg-light border-0 mb-3">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-6 border-end">
                                <h6 class="text-muted mb-1 small">Demirbaş Bilgileri</h6>
                                <h5 class="mb-1" id="detay_demirbas_adi">-</h5>
                                <p class="mb-0 text-muted small">
                                    <span id="detay_marka_model">-</span> |
                                    Seri No: <span id="detay_seri_no">-</span>
                                </p>
                            </div>
                            <div class="col-md-6 ps-md-4">
                                <h6 class="text-muted mb-1 small">Zimmet Sahibi</h6>
                                <h5 class="mb-1 text-primary" id="detay_personel_adi">-</h5>
                                <div id="detay_durum_badge"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statü Özet Kartları (Pro Max Minimal) -->
                <div class="row g-2 mb-4 mt-1" id="detay_ozet_kartlari">
                    <!-- Toplam Zimmet -->
                    <div class="col-md">
                        <div class="card ozet-filter-card border-0 bg-light rounded-3 h-100 shadow-sm border-start border-4 border-secondary"
                            data-filter="all" title="Tüm hareketleri göster">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle p-1 me-2 d-flex align-items-center justify-content-center"
                                        style="width: 20px; height: 20px;">
                                        <i data-feather="package" style="width: 12px; height: 12px;"></i>
                                    </div>
                                    <span class="text-secondary small fw-bold text-uppercase"
                                        style="font-size: 0.6rem; letter-spacing: 0.5px;">TOPLAM ZİMMET</span>
                                </div>
                                <div class="h5 mb-0 fw-bold text-dark" id="ozet_toplam">0</div>
                            </div>
                        </div>
                    </div>
                    <!-- İadeler -->
                    <div class="col-md">
                        <div class="card ozet-filter-card border-0 bg-light rounded-3 h-100 shadow-sm border-start border-4 border-info"
                            data-filter="iade" title="Sadece iade hareketlerini göster">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="bg-info bg-opacity-10 text-info rounded-circle p-1 me-2 d-flex align-items-center justify-content-center"
                                        style="width: 20px; height: 20px;">
                                        <i data-feather="corner-up-left" style="width: 12px; height: 12px;"></i>
                                    </div>
                                    <span class="text-info small fw-bold text-uppercase"
                                        style="font-size: 0.6rem; letter-spacing: 0.5px;">İADELER</span>
                                </div>
                                <div class="h5 mb-0 fw-bold text-info" id="ozet_iadeler">0</div>
                            </div>
                        </div>
                    </div>
                    <!-- Tüketilenler -->
                    <div class="col-md">
                        <div class="card ozet-filter-card border-0 bg-light rounded-3 h-100 shadow-sm border-start border-4 border-danger"
                            data-filter="depo_iade" title="Sadece depoya iade hareketlerini göster">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-1 me-2 d-flex align-items-center justify-content-center"
                                        style="width: 20px; height: 20px;">
                                        <i data-feather="corner-down-left" style="width: 12px; height: 12px;"></i>
                                    </div>
                                    <span class="text-danger small fw-bold text-uppercase"
                                        style="font-size: 0.6rem; letter-spacing: 0.5px;">DEPOYA İADE</span>
                                </div>
                                <div class="h5 mb-0 fw-bold text-danger" id="ozet_depo_iade">0</div>
                            </div>
                        </div>
                    </div>
                    <!-- Tüketilenler -->
                    <div class="col-md">
                        <div class="card ozet-filter-card border-0 bg-light rounded-3 h-100 shadow-sm border-start border-4 border-danger"
                            data-filter="sarf" title="Sadece tüketim hareketlerini göster">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-1 me-2 d-flex align-items-center justify-content-center"
                                        style="width: 20px; height: 20px;">
                                        <i data-feather="minus-circle" style="width: 12px; height: 12px;"></i>
                                    </div>
                                    <span class="text-danger small fw-bold text-uppercase"
                                        style="font-size: 0.6rem; letter-spacing: 0.5px;">TÜKETİLENLER</span>
                                </div>
                                <div class="h5 mb-0 fw-bold text-danger" id="ozet_tuketilen">0</div>
                            </div>
                        </div>
                    </div>
                    <!-- Kalan Miktar -->
                    <div class="col-md">
                        <div class="card ozet-filter-card border-0 bg-light rounded-3 h-100 shadow-sm border-start border-4 border-success"
                            data-filter="all" title="Tüm hareketleri göster">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center mb-1">
                                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-1 me-2 d-flex align-items-center justify-content-center"
                                        style="width: 20px; height: 20px;">
                                        <i data-feather="check-circle" style="width: 12px; height: 12px;"></i>
                                    </div>
                                    <span class="text-success small fw-bold text-uppercase"
                                        style="font-size: 0.6rem; letter-spacing: 0.5px;">KALAN MİKTAR</span>
                                </div>
                                <div class="h5 mb-0 fw-bold text-success" id="ozet_kalan">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hareket Detayları -->
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <h6 class="mb-0 text-primary"><i data-feather="repeat" class="me-1"></i> Zimmet
                        Hareketleri (Detay)</h6>
                    <button class="btn btn-sm btn-danger d-none" id="btnTopluHareketSil">
                        <i class="bx bx-trash me-1"></i> Seçilenleri Sil (<span id="seciliHareketSayisi">0</span>)
                    </button>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover table-bordered border-primary-subtle">
                        <thead class="table-primary fw-bold">
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <div class="form-check d-flex justify-content-center m-0">
                                        <input class="form-check-input" type="checkbox" id="checkAllZimmetHareket">
                                    </div>
                                </th>
                                <th>İşlem Tipi</th>
                                <th class="text-center">Miktar</th>
                                <th>Tarih</th>
                                <th>Açıklama / İş Emri</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetHareketBody">
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

<style>
    .ozet-filter-card {
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .ozet-filter-card:hover {
        transform: translateY(-1px);
    }

    .ozet-filter-card.ozet-filter-active {
        box-shadow: 0 0 0 2px rgba(85, 110, 230, .25) !important;
    }
</style>