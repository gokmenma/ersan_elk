<!-- Demirbaş / Malzeme İşlem Geçmişi Modal -->
<div class="modal fade" id="demirbasGecmisModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-soft-info border-bottom">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px;">
                        <i class="bx bx-history text-info fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-info mb-0 fw-bold">Demirbaş İşlem Geçmişi</h6>
                        <p class="text-muted small mb-0" id="gecmisDemirbasAdi" style="font-size: 0.7rem;">-</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table id="demirbasGecmisTable"
                        class="table table-hover table-striped dt-responsive nowrap w-100 mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>İşlem Tipi</th>
                                <th class="text-center">Miktar</th>
                                <th>Tarih</th>
                                <th>İlgili Personel</th>
                                <th>Açıklama</th>
                                <th class="text-end">İşlem Yapan</th>
                            </tr>
                        </thead>
                        <tbody id="demirbasGecmisBody">
                            <!-- JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-4"
                    data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
