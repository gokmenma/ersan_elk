<?php
$muhasebeDonemToken = \App\Helper\Security::encrypt((int) $selectedDonemId);
$eMuhasebe = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="modal fade" id="muhasebeKarsilastirmaModal" tabindex="-1" aria-labelledby="muhasebeKarsilastirmaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle"><i class="bx bx-git-compare font-size-20"></i></div>
                    <div><h5 class="modal-title font-size-14 fw-bold mb-1" id="muhasebeKarsilastirmaModalLabel">Muhasebe Excel Karşılaştırması</h5><p class="font-size-12 text-muted mb-0">Muhasebe bordrosundaki toplam, normal, hafta tatili, ücretli izin ve rapor günlerini; ortak ödeme alanlarıyla birlikte karşılaştırır.</p></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
        <form id="muhasebeKarsilastirmaForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $eMuhasebe(\App\Helper\Security::csrf()) ?>">
            <input type="hidden" name="donem" value="<?= $eMuhasebe($muhasebeDonemToken) ?>">
            <div class="row g-2 align-items-end">
                <div class="col-md-8"><?= \App\Helper\Form::FormFileInput('muhasebe_excel', 'Muhasebeden Gelen Maaş Bordrosu (.xlsx)', 'bx bx-spreadsheet', 'form-control', true, 'accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"', 'muhasebeExcel') ?></div>
                <div class="col-md-4"><button type="submit" id="btnMuhasebeKarsilastir" class="btn btn-info text-white w-100 top-action-btn shadow-sm" style="height:48px"><i class="bx bx-search-alt me-1"></i>Exceli Analiz Et ve Karşılaştır</button></div>
            </div>
        </form>
        <div id="muhasebeKarsilastirmaHata" class="alert alert-danger mt-3 mb-0 d-none"></div>
        <div id="muhasebeKarsilastirmaSonuc" class="d-none mt-3">
            <div class="row g-2 mb-3" id="muhasebeKarsilastirmaOzet"></div>
            <div class="border rounded-3 p-3 mb-3" id="muhasebeAlanGrafikKarti">
                <div class="fw-semibold font-size-13 mb-2">Alan Bazında Farklı Personel Sayısı</div>
                <div id="muhasebeAlanGrafik"></div>
            </div>
            <div class="d-flex gap-2 flex-wrap mb-2" id="muhasebeDurumFiltreleri"></div>
            <div class="table-responsive"><table id="muhasebeKarsilastirmaTable" class="table table-bordered table-hover align-middle w-100 mb-0">
                <thead><tr><th data-filter="string">Personel</th><th data-filter="string">T.C.</th><th data-filter="select">Durum</th><th data-filter="number">Fark Sayısı</th><th data-filter="string">Açıklama</th><th data-filter="none" class="text-center">Detay</th></tr></thead><tbody></tbody>
            </table></div>
        </div>
            </div>
            <div class="modal-footer">
                <span class="text-muted font-size-11 me-auto"><i class="bx bx-info-circle me-1"></i>Bu işlem bordro kayıtlarını değiştirmez.</span>
                <button type="button" class="btn btn-success d-none" id="btnMuhasebeKarsilastirmaExcel"><i class="mdi mdi-file-excel me-1"></i>Kişi Bazlı Karşılaştırmayı Excel'e İndir</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
<script src="views/bordro/js/muhasebe-karsilastirma.js?v=<?= filemtime(__DIR__ . '/../js/muhasebe-karsilastirma.js') ?>"></script>
