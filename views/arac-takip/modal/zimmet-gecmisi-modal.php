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
                                <th class="text-center">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetGecmisiTableBody">
                            <tr>
                                <td colspan="8" class="text-center p-4 text-muted">
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
