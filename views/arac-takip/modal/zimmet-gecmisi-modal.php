<div class="modal fade" id="zimmetGecmisiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header d-flex align-items-center justify-content-between">
                <h5 class="modal-title mb-0"><i class="bx bx-history me-2"></i>Zimmet Geçmişi: <span id="gecmisAracPlaka" class="text-primary"></span></h5>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-success px-3 shadow-sm me-3" id="btnZimmetGecmisiExcel" data-arac-id="">
                        <i class="bx bx-spreadsheet me-1"></i> Excel'e Aktar
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="text-center" style="width: 5%;">#</th>
                                <th>Personel</th>
                                <th class="text-center">Zimmet Tarihi</th>
                                <th class="text-center">İade Tarihi</th>
                                <th class="text-center">Teslim KM</th>
                                <th class="text-center">İade KM</th>
                                <th class="text-center">İşlem Yapan</th>
                                <th class="text-center">Fotoğraf</th>
                                <th class="text-center">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetGecmisiTableBody">
                            <tr>
                                <td colspan="9" class="text-center p-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div> Yükleniyor...
                                </td>
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

<!-- Zimmet Fotoğraf Görüntüleme Modal -->
<div class="modal fade" id="zimmetFotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0"><i class="bx bx-camera me-2"></i>Zimmet Fotoğrafları
                    <span class="badge bg-success bg-opacity-10 text-success border border-success ms-1"><i class="bx bx-lock-alt"></i> Şifreli</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="zimmetFotoModalBody">
                <div class="text-center p-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div> Yükleniyor...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Resim Önizleme Modal -->
<div class="modal fade" id="zimmetGecmisFotoOnizlemeModal" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow-none text-white bg-dark border-0 rounded-circle p-2" data-bs-dismiss="modal" aria-label="Close" style="z-index: 10; opacity: 0.8; width: 35px; height: 35px;"></button>
            </div>
            <div class="modal-body p-0 text-center">
                <img id="gecmisFotoOnizlemeImg" src="" class="img-fluid rounded shadow" style="max-height: 85vh; max-width: 100%; object-fit: contain; background: rgba(0,0,0,0.2);">
            </div>
        </div>
    </div>
</div>
