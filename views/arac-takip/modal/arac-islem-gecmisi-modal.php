<div class="modal fade" id="aracIslemGecmisiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0"><i class="bx bx-list-check me-2"></i>İşlem Geçmişi: <span id="islemGecmisiPlaka" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-2 h-100 bg-light-subtle">
                            <div class="font-size-11 text-muted"><i class="bx bx-user-plus me-1"></i>Ekleyen</div>
                            <div class="fw-semibold" id="islemGecmisiEkleyen">-</div>
                            <div class="font-size-11 text-muted" id="islemGecmisiEklemeTarihi"></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="border rounded-3 p-2 h-100 bg-light-subtle">
                            <div class="font-size-11 text-muted"><i class="bx bx-edit me-1"></i>Son Güncelleyen</div>
                            <div class="fw-semibold" id="islemGecmisiGuncelleyen">-</div>
                            <div class="font-size-11 text-muted" id="islemGecmisiGuncellemeTarihi"></div>
                        </div>
                    </div>
                </div>
                <div id="islemGecmisiListe">
                    <div class="text-center p-4 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div> Yükleniyor...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
