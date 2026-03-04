<div class="modal fade" id="aparatPersonelOzetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-primary">
                    <i data-feather="bar-chart-2" class="me-2"></i>Personel Aparat Özetleri
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">TOPLAM İŞLEM SATIRI</small>
                                <div class="h5 mb-0" id="aparat_ozet_islem">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">TOPLAM VERİLEN</small>
                                <div class="h5 mb-0 text-primary" id="aparat_ozet_verilen">0</div>
                            </div>
                        </div>
                    </div>
                   
                    <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">İADE ALINAN</small>
                                <div class="h5 mb-0 text-info" id="aparat_ozet_iade">0</div>
                            </div>
                        </div>
                    </div>
                     <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">TOPLAM KULLANILAN</small>
                                <div class="h5 mb-0 text-danger" id="aparat_ozet_tuketilen">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">DEPOYA İADE</small>
                                <div class="h5 mb-0 text-danger" id="aparat_ozet_depo_iade">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <small class="text-muted fw-bold">ŞU AN ELİNDE KALAN</small>
                                <div class="h5 mb-0 text-success" id="aparat_ozet_kalan">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Personel</th>
                                <th class="text-center">İşlem</th>
                                <th class="text-center">Verilen</th>
                                <th class="text-center">Kullanılan</th>
                                <th class="text-center">İade Alınan</th>
                                <th class="text-center">Depoya İade</th>
                                <th class="text-center">Kalan</th>
                            </tr>
                        </thead>
                        <tbody id="aparatPersonelOzetBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">Veri bekleniyor...</td>
                            </tr>
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
