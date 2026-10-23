<?php

require_once 'vendor/autoload.php';

use App\Helper\Security;
use App\Service\Gate;

if (!Gate::isSuperAdmin()) {
    Gate::authorizeOrDie('superadmin', 'Veritabanı yedekleri yalnızca Süper Admin tarafından yönetilebilir.');
    return;
}

$maintitle = 'Yönetim';
$title = 'Veritabanı Yedekleri';
$csrfToken = Security::csrf();
?>
<script>try { document.documentElement.classList.toggle('yedekleme-summary-hidden', localStorage.getItem('yedekleme_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.yedekleme-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#yedeklemeTable tbody tr { cursor: pointer !important; }
#yedekIsKarti { display: none; }
#yedekIsKarti.aktif { display: block; }
.yedek-is-log { max-height: 260px; overflow-y: auto; background: #0f172a; color: #cbd5e1; border-radius: 10px; padding: 12px 14px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; line-height: 1.55; margin: 0; white-space: pre-wrap; word-break: break-word; }
.yedek-is-log .log-WARNING { color: #fbbf24; }
.yedek-is-log .log-ERROR { color: #f87171; }
.yedek-is-log .log-zaman { color: #64748b; }
.yedek-test-satir { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--bs-border-color, #e9ecef); font-size: 12px; }
.yedek-test-satir:last-child { border-bottom: 0; }
.yedek-test-bilgi { min-width: 0; flex: 1 1 auto; }
.yedek-test-satir .dosya { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11px; color: var(--bs-secondary-color, #6c757d); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.yedek-test-satir .hata { font-size: 11px; color: var(--bs-danger, #f46a6a); overflow-wrap: anywhere; }
.yedek-konum-badge + .yedek-konum-badge { margin-left: 4px; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid" id="yedeklemeSayfasi" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-data fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Veritabanı Yedekleri</h4>
                <p class="text-muted mb-0 font-size-12">Yerel ve Google Drive yedekleri, elle yedekleme ve geri yükleme testleri</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white yedek-is-btn" data-is="yedek_tam">
                <i class="bx bx-plus font-size-16"></i> Tam Yedek Al
            </button>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center yedek-is-btn" data-is="yedek_artimli">
                        <i class="bx bx-git-commit me-2 text-primary font-size-16"></i> Artımlı Yedek Al
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center yedek-is-btn" data-is="drive_senkron">
                        <i class="bx bxl-google me-2 text-success font-size-16"></i> Google Drive'a Gönder
                    </button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDriveListeYenile">
                        <i class="bx bx-refresh me-2 text-secondary font-size-16"></i> Drive Listesini Yenile
                    </button>
                </div>
            </div>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartları gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM YEDEK</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-data"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpiToplam">-</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpiToplamAlt">Tam: - | Artımlı: -</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SON YEDEK</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-time-five"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="kpiSonYedek">-</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpiSonYedekAlt">-</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="tam">
                            <i class="bx bx-check-circle"></i> Tam
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GOOGLE DRIVE</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bxl-google"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpiDrive">-</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="kpiDriveAlt">-</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="drive_eksik">
                            <i class="bx bx-cloud-upload"></i> Eksik
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SON GERİ YÜKLEME TESTİ</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-test-tube"></i></div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="kpiTest">-</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="kpiTestAlt">-</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" id="kpiTestRozet">Test yok</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning d-none align-items-center gap-2 font-size-13" id="yedekUyari" role="alert"></div>

    <div class="card summary-kpi-card mb-3" id="yedekIsKarti">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;" id="yedekIsIkon">
                    <i class="bx bx-loader-alt bx-spin font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark" id="yedekIsBaslik">İşlem</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;" id="yedekIsAlt">-</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <span class="badge rounded-pill px-2 py-1 font-size-11 fw-semibold" id="yedekIsRozet"></span>
                <button type="button" class="btn btn-sm btn-subtle-secondary rounded-3 d-none" id="btnIsKartiKapat" title="Kapat" aria-label="İşlem kartını kapat">
                    <i class="bx bx-x font-size-15"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-3 pt-0">
            <pre class="yedek-is-log" id="yedekIsLog" aria-live="polite"></pre>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="yedeklemeListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Yedek Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;" id="yedekListeAlt">Yerel yedek dizini ve Google Drive birlikte listelenir</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="yedeklemeTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="select" style="width: 90px;">TİP</th>
                            <th data-filter="date" style="width: 150px;">YEDEK ZAMANI</th>
                            <th data-filter="string">DOSYA</th>
                            <th data-filter="none" class="text-end" style="width: 100px;">BOYUT</th>
                            <th data-filter="select" style="width: 150px;">KONUM</th>
                            <th data-filter="select" style="width: 130px;">SON TEST</th>
                            <th data-filter="none" style="width: 130px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center gap-2">
            <div class="p-2 bg-warning-subtle text-warning rounded-3 border border-warning-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                <i class="bx bx-test-tube font-size-20"></i>
            </div>
            <div>
                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Geri Yükleme Testleri</h5>
                <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Yedekler ayrı test veritabanına yüklenip tablo satır sayıları doğrulanır; canlı veriye dokunulmaz</p>
            </div>
        </div>
        <div class="card-body p-3 pt-0" id="testGecmisi">
            <div class="text-muted font-size-12 py-2">Henüz test yapılmadı.</div>
        </div>
    </div>
</div>

<div class="modal fade" id="yedekDetayModal" tabindex="-1" aria-labelledby="yedekDetayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                        <i class="bx bx-data font-size-22"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="yedekDetayModalLabel">Yedek Detayı</h5>
                        <p class="text-muted small mb-0 text-break" id="yedekDetayDosya"></p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body px-4 pt-3" id="yedekDetayIcerik"></div>
        </div>
    </div>
</div>

<form id="yedekIndirForm" method="post" action="views/yedekleme/api.php" class="d-none">
    <input type="hidden" name="action" value="indir">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="dosya" value="">
</form>

<script src="views/yedekleme/js/yedekleme.js?v=<?= filemtime(__DIR__ . '/js/yedekleme.js') ?>"></script>
