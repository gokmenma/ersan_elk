<div class="modal fade" id="kasiyeDetayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header modal-header-dark"
                style="background: rgba(33, 37, 41, 0.05); border-bottom: 2px solid #212529;">
                <div class="modal-title-section">
                    <div class="modal-icon-box" style="background: rgba(33, 37, 41, 0.1);">
                        <i class="bx bx-info-circle text-dark"></i>
                    </div>
                    <div class="modal-title-group">
                        <h5 class="modal-title text-dark">Kaskiye Teslim Detayı</h5>
                        <p class="modal-subtitle">Bu sayaç Kaskiye'ye teslim edilerek stoktan düşülmüştür.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <div class="avatar-title bg-soft-info text-info display-4 rounded-circle">
                            <i class="bx bx-check-double border-3 border border-info rounded-circle p-2"></i>
                        </div>
                    </div>
                    <h5 id="detaySayacAdi" class="fw-bold mb-1">-</h5>
                    <p id="detaySeriNo" class="text-muted small mb-0">-</p>
                </div>

                <div class="card bg-light border-0 shadow-none mb-0">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0 me-2">
                                        <i class="bx bx-user fs-4 text-primary opacity-75"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-0 small uppercase fw-bold" style="font-size:0.65rem;">
                                            Teslim Eden</p>
                                        <h6 id="detayTeslimEden" class="mb-0 fw-bold small text-dark">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center border-start ps-3">
                                    <div class="flex-shrink-0 me-2">
                                        <i class="bx bx-calendar fs-4 text-success opacity-75"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <p class="text-muted mb-0 small uppercase fw-bold" style="font-size:0.65rem;">
                                            Teslim Tarihi</p>
                                        <h6 id="detayTarih" class="mb-0 fw-bold small text-dark">-</h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-3 pt-3 border-top">
                                <p class="text-muted mb-1 small uppercase fw-bold" style="font-size:0.65rem;">
                                    <i class="bx bx-message-square-detail me-1"></i> Açıklama / Not
                                </p>
                                <p id="detayAciklama" class="text-dark small mb-0 fst-italic">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-dark fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>