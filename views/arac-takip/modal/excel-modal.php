<!-- Excel Yükleme Modal -->
<div class="modal fade" id="excelModal" tabindex="-1" aria-labelledby="excelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="excelModalLabel"><i class="bx bx-file me-2"></i>Excel'den Yakıt Kaydı Yükle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info bg-info bg-opacity-10 border border-info border-opacity-25">
                    <div class="d-flex align-items-start">
                        <i class="bx bx-info-circle text-info me-2 mt-1 fs-5"></i>
                        <div>
                            <strong>Excel Dosyası Formatı</strong>
                            <p class="mb-0 small">Excel dosyanızda aşağıdaki sütunlar bulunmalıdır:</p>
                            <ul class="small mb-0 ps-3">
                                <li><strong>Plaka</strong> (Zorunlu)</li>
                                <li>Tarih</li>
                                <li>KM (Kilometre)</li>
                                <li>Litre (Yakıt miktarı)</li>
                                <li>Tutar (Toplam tutar)</li>
                                <li>İstasyon</li>
                                <li>Fatura No</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="excelUploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Excel Dosyası Seçin</label>
                        <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" required>
                        <div class="form-text">Sadece .xlsx ve .xls dosyaları kabul edilir.</div>
                    </div>
                </form>

                <div class="d-grid">
                    <a href="views/arac-takip/yakit-excel-sablon.php" class="btn btn-outline-success">
                        <i class="bx bx-download me-1"></i> Örnek Şablon İndir
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-dark" id="btnExcelYukle">
                    <i class="bx bx-upload me-1"></i> Yükle
                </button>
            </div>
        </div>
    </div>
</div>