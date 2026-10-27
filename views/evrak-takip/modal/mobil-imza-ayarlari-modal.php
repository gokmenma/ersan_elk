<div class="modal fade" id="modalMobilImzaAyarlari" tabindex="-1" aria-labelledby="modalMobilImzaAyarlariLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center" style="width:38px;height:38px;">
                        <i class="bx bx-mobile-alt font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalMobilImzaAyarlariLabel">Mobil İmza Ayarları</h5>
                        <div class="small text-muted">Turkcell mobil imza hizmeti, imzacı hatları ve KEP varsayılan imzacısı</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body bg-light">
                <?php include dirname(__DIR__, 2) . '/ayarlar/icerik/mobil-imza-ayarlari.php'; ?>
            </div>
        </div>
    </div>
</div>
