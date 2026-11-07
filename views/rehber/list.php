<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Service\Gate;

$maintitle = "Rehber";
$title = "Telefon Listesi";
?>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Standart) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bxs-contact fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Telefon Rehberi</h4>
                <p class="text-muted mb-0 font-size-12">Kişi ve kurumlara ait iletişim bilgileri, telefon numaraları ve adresler</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="yeniEkle" data-bs-toggle="modal" data-bs-target="#rehberModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Kayıt Ekle
            </button>
        </div>
    </div>

    <!-- 2. DataTables Liste Kartı (Cari / Standart Formatı) -->
    <div class="card summary-kpi-card mb-3" id="rehberListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Rehber Kayıtları</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Canlı arama, sütun filtreleme ve hızlı iletişim yönetimi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="rehberTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="none" style="width: 50px;" class="text-center">SIRA</th>
                            <th data-filter="string">ADI SOYADI</th>
                            <th data-filter="string">KURUM / FİRMA</th>
                            <th data-filter="string" style="width: 140px;">TELEFON</th>
                            <th data-filter="string">E-POSTA</th>
                            <th data-filter="string">ADRES</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="date" style="width: 120px;" class="text-center">KAYIT TARİHİ</th>
                            <th data-filter="none" style="width: 110px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<?php include_once __DIR__ . '/modal/general-modal.php'; ?>
