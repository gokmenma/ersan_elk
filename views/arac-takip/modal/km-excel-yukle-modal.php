<?php
/**
 * KM Excel Yükleme Modalı
 */
?>
<div class="modal fade" id="kmExcelYukleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 560px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10"
                        style="width:40px;height:40px;">
                        <i class="mdi mdi-table-arrow-up text-warning fs-4"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 fw-bold">KM Kaydı Excel Yükle</h5>
                        <p class="text-muted small mb-0">Toplu KM verisi aktarın</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <!-- Upload Zone -->
                <div id="kmUploadZone" class="border-2 border-dashed rounded-3 p-4 text-center position-relative"
                    style="border-color: #dee2e6; cursor: pointer; transition: all 0.2s; min-height: 140px;">
                    <input type="file" id="kmExcelFile" accept=".xlsx,.xls"
                        class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
                        style="cursor:pointer; z-index:2;">
                    <div id="kmUploadDefault">
                        <i class="mdi mdi-file-excel text-success" style="font-size: 2.5rem;"></i>
                        <p class="fw-semibold mt-2 mb-0">Excel dosyasını sürükle veya tıkla</p>
                        <p class="text-muted small mb-0">.xlsx veya .xls • Maks. 10MB</p>
                    </div>
                    <div id="kmUploadSelected" class="d-none">
                        <i class="mdi mdi-check-circle text-success" style="font-size: 2.5rem;"></i>
                        <p class="fw-semibold mt-2 mb-0" id="kmUploadFileName">—</p>
                        <p class="text-muted small mb-0">Hazır — Yüklemek için butona tıklayın</p>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-3 align-items-center text-muted small">
                    <i class="mdi mdi-information-outline text-info"></i>
                    <span>Şablonda sadece <strong>BİTİŞ KM</strong> sütununu doldurmanız yeterlidir. Başlangıç KM
                        otomatik gelir.</span>
                </div>

                <!-- Progress -->
                <div id="kmUploadProgress" class="d-none mt-3">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="spinner-border spinner-border-sm text-warning"></div>
                        <span class="small fw-semibold">Yükleniyor ve işleniyor...</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning w-100"></div>
                    </div>
                </div>

                <!-- Result -->
                <div id="kmUploadResult" class="d-none mt-3"></div>
            </div>
            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                <a href="views/arac-takip/km-excel-sablon.php" target="_blank" download="km_yukleme_sablonu.xlsx"
                    class="btn btn-link text-info text-decoration-none p-0 d-flex align-items-center">
                    <i class="mdi mdi-download fs-5 me-1"></i> Şablon İndir
                </a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-warning fw-bold" id="btnKmExcelYukleSubmit" disabled>
                        <i class="mdi mdi-upload me-1"></i> Yükle
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #kmUploadZone:hover,
    #kmUploadZone.dragover {
        border-color: #f0ad4e !important;
        background: rgba(240, 173, 78, 0.05);
    }
</style>