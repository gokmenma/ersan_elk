<div class="modal fade" id="zimmetGecmisiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-history me-2"></i>Zimmet Geçmişi: <span id="gecmisAracPlaka" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                                <th class="text-center">Durum</th>
                            </tr>
                        </thead>
                        <tbody id="zimmetGecmisiTableBody">
                            <tr>
                                <td colspan="7" class="text-center p-4 text-muted">
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
