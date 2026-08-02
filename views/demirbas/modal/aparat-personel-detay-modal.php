<!-- Aparat Personel Detay Modal -->
<div class="modal fade" id="aparatPersonelDetailModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-soft-info border-bottom">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;">
                        <i class="bx bx-user text-info fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-info mb-0 fw-bold" id="aparatSeciliPersonel">Personel Detayı</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.7rem;">Gün gün aparat hareketleri</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-md">
                        <div class="border rounded p-2 text-center bg-light">
                            <small class="text-muted">Verilen</small>
                            <div class="fw-bold fs-6 text-primary" id="ap_verilen">0</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="border rounded p-2 text-center bg-light">
                            <small class="text-muted">Tüketilen</small>
                            <div class="fw-bold fs-6 text-warning" id="ap_tuketilen">0</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="border rounded p-2 text-center bg-light">
                            <small class="text-muted">Depo İade</small>
                            <div class="fw-bold fs-6 text-info" id="ap_depo_iade">0</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="border rounded p-2 text-center bg-light">
                            <small class="text-muted">Kayıp</small>
                            <div class="fw-bold fs-6 text-danger" id="ap_kayip">0</div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="border rounded p-2 text-center bg-light">
                            <small class="text-muted">Kalan</small>
                            <div class="fw-bold fs-6 text-success" id="ap_kalan">0</div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="aparatPersonelHistoryTable" class="table table-sm table-bordered table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th class="text-center">Verilen</th>
                                <th class="text-center">Tüketilen</th>
                                <th class="text-center">Depo İade</th>
                                <th class="text-center">Kayıp</th>
                                <th class="text-center">Net</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
