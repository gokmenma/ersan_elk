<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;

$maintitle = 'Site Yönetimi';
$title = 'Projeler';

$durumOptions = ['taslak' => 'Taslak', 'yayinda' => 'Yayında'];
?>
<script>try { document.documentElement.classList.toggle('siteproje-summary-hidden', localStorage.getItem('siteproje_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.siteproje-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.spm-thumb { width: 54px; height: 38px; object-fit: cover; border-radius: 8px; border: 1px solid var(--bs-border-color, #e3e8ef); background: var(--bs-secondary-bg, #eef1f5); }
.spm-onizleme { width: 100%; max-height: 190px; object-fit: cover; border-radius: 12px; border: 1px solid var(--bs-border-color, #e3e8ef); }
.spm .modal-content { border-radius: 18px; overflow: hidden; }
.spm .spm-hint { font-size: 12px; color: #74788d; margin: 6px 2px 0; }
.spm .spm-sec { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #74788d; margin: 4px 0 -4px; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-briefcase-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Site Projeleri</h4>
                <p class="text-muted mb-0 font-size-12">Kurumsal sitede yayınlanan projeleri yönetin</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnYeniProje">
                <i class="bx bx-plus font-size-16"></i> Yeni Proje
            </button>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM PROJE</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-briefcase-alt-2"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_toplam">Taslak: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all"><i class="bx bx-layer"></i> Tümü</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">YAYINDAKİ</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-broadcast"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_yayinda">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold">Sitede görünüyor</span>
                    <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="yayinda"><i class="bx bx-check-circle"></i> Yayında</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TASLAK</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-edit"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_taslak">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Henüz yayınlanmadı</span>
                    <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="taslak"><i class="bx bx-edit"></i> Taslak</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">GÖRSELİ OLMAYAN</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-image"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_gorselsiz">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-muted">Görsel eklenmeli</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Eksik</span>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="siteprojeListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Proje Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Sıra numarası sitedeki sıralamayı belirler</p>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table id="siteprojeTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th data-filter="string">PROJE</th>
                            <th data-filter="string">KATEGORİ</th>
                            <th data-filter="number" class="text-center" style="width: 80px;">SIRA</th>
                            <th data-filter="select" data-filter-options="Taslak|Yayında" style="width: 110px;">DURUM</th>
                            <th data-filter="date" style="width: 130px;">GÜNCELLEME</th>
                            <th data-filter="none" style="width: 130px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade spm no-upgrade" id="siteprojeModal" tabindex="-1" aria-labelledby="siteprojeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="d-flex align-items-center gap-3 px-4 pt-4">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;"><i class="bx bx-briefcase-alt-2 fs-4"></i></div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fw-bold" id="siteprojeModalLabel">Yeni Proje</h5>
                    <p class="text-muted font-size-12 mb-0">Kaydettiğinizde ve "Yayında" olduğunda sitede görünür.</p>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="siteprojeForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" value="proje-kaydet">
                <input type="hidden" name="id" id="proje_id" value="">
                <input type="hidden" name="gorsel_kaldir" id="gorsel_kaldir" value="0">
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="row g-3">
                        <div class="col-md-8"><?= Form::FormFloatInput("text", "baslik", "", "Örn: İskenderun Endeks Okuma", "Proje Başlığı *", "briefcase", "form-control fw-semibold", true, 200) ?></div>
                        <div class="col-md-4"><?= Form::FormSelect2("durum", $durumOptions, "taslak", "Durum", "activity") ?></div>
                        <div class="col-md-8"><?= Form::FormFloatInput("text", "slug", "", "ornek-proje-adresi", "Sayfa Adresi (slug)", "link", "form-control", false, 150) ?><p class="spm-hint">Boş bırakırsanız başlıktan otomatik üretilir.</p></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("number", "sira", "0", "0", "Sıra", "hash", "form-control", false, 5) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "kategori", "", "Örn: Sayaç Okuma", "Kategori", "tag", "form-control", false, 100) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "musteri", "", "Müşteri / kurum", "Müşteri", "users", "form-control", false, 150) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("text", "konum", "", "İl / ilçe", "Konum", "map-pin", "form-control", false, 150) ?></div>
                        <div class="col-md-4"><?= Form::FormFloatInput("number", "proje_yili", "", "2026", "Proje Yılı", "calendar", "form-control", false, 4) ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("kisa_aciklama", "", "Kartlarda görünen kısa özet", "Kısa Açıklama", "align-left", "form-control", false, "80px") ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("aciklama", "", "Proje detay sayfasındaki anlatım", "Açıklama", "file-text", "form-control", false, "150px") ?></div>
                        <div class="col-12">
                            <div class="spm-sec mb-2">Görsel <span class="text-muted text-lowercase fw-normal">(JPEG veya PNG, en fazla 5 MB)</span></div>
                            <div id="gorselOnizlemeKutu" class="mb-2 d-none">
                                <img id="gorselOnizleme" class="spm-onizleme" alt="Proje görseli">
                                <button type="button" class="btn btn-sm btn-subtle-danger mt-2" id="btnGorselKaldir"><i class="bx bx-trash me-1"></i> Görseli kaldır</button>
                            </div>
                            <?= Form::FormFileInput("gorsel", "Görsel Seç", "image", "form-control", false, 'accept="image/jpeg,image/png"') ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary text-white" id="btnProjeKaydet"><i class="bx bx-save me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/site-yonetimi/js/projeler.js?v=<?php echo time(); ?>"></script>
