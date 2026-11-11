<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Form;
use App\Model\TanimlamalarModel;

$maintitle = "Tanımlamalar";
$title = "Demirbaş Kategori Tanımlamaları";

$Tanimlamalar = new TanimlamalarModel();
$summary = $Tanimlamalar->demirbasKategoriSummary((int) ($_SESSION['firma_id'] ?? 0));
?>
<script>try { document.documentElement.classList.toggle('dmbkat-summary-hidden', localStorage.getItem('dmbkat_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.dmbkat-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#kategoriTable tbody tr { cursor: pointer; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-category fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Demirbaş Kategorileri</h4>
                <p class="text-muted mb-0 font-size-12">Demirbaşların sınıflandırıldığı kategoriler ve kullanım durumları</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="actionEkle" data-bs-toggle="modal" data-bs-target="#actionModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Kategori
            </button>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM KATEGORİ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-layer"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_toplam"><?php echo (int) $summary->toplam; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpi_sub_toplam">Kullanımda: <?php echo (int) $summary->kullanimda; ?> | Boş: <?php echo (int) $summary->bos; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-list-ul"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KULLANIMDA</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="kpi_kullanimda"><?php echo (int) $summary->kullanimda; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Demirbaşı olan kategoriler</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="kullanimda">
                            <i class="bx bx-check"></i> Kullanımda
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KULLANILMAYAN</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-folder-open"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="kpi_bos"><?php echo (int) $summary->bos; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold">Silinebilir kategoriler</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="bos">
                            <i class="bx bx-minus-circle"></i> Boş
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM DEMİRBAŞ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-package"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpi_demirbas"><?php echo (int) $summary->toplam_demirbas; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Kategorilere bağlı kayıt</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Demirbaş</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0" id="kategoriListCard">
        <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-18"></i>
                </div>
                <div>
                    <h5 class="card-title font-size-14 fw-bold mb-0 text-dark">Kategori Listesi</h5>
                    <p class="text-muted font-size-11 mb-0">Anlık filtreleme ve sıralama; satıra tıklayarak düzenleyebilirsiniz</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary bg-white shadow-sm" id="btnHeaderPrint" title="Yazdır">
                    <i class="bx bx-printer font-size-14 me-1"></i> Yazdır
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="kategoriTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 50px;" data-filter="none">Sıra</th>
                            <th data-filter="string">Kategori Adı</th>
                            <th data-filter="string">Açıklama</th>
                            <th class="text-center" style="width: 150px;" data-filter="none">Kullanım</th>
                            <th class="text-center" style="width: 140px;" data-filter="none">Kayıt Tarihi</th>
                            <th class="text-center" style="width: 100px;" data-filter="none">İşlem</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="actionModalLabel"><i class="bx bx-list-ul me-2"></i>Kategori İşlemleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="kategori_id" id="kategori_id" class="form-control" value="0">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "kategori_adi",
                                    "",
                                    "Kategori adı giriniz!",
                                    "Kategori Adı",
                                    "file-text",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
                                    "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "file-text"
                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="actionKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/js/demirbas-kategorileri.js?v=<?php echo time(); ?>"></script>