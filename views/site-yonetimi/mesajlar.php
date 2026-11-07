<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;

$maintitle = 'Site Yönetimi';
$title = 'Mesajlar';

$durumOptions = ['yeni' => 'Yeni', 'okundu' => 'Okundu', 'cevaplandi' => 'Cevaplandı'];
?>
<script>try { document.documentElement.classList.toggle('sitemesaj-summary-hidden', localStorage.getItem('sitemesaj_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.sitemesaj-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.smm .modal-content { border-radius: 18px; overflow: hidden; }
.smm .smm-mesaj { white-space: pre-wrap; word-break: break-word; background: var(--bs-secondary-bg, #f3f5f9); border-radius: 12px; padding: 14px 16px; font-size: 14px; line-height: 1.6; }
.smm .smm-sec { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #74788d; margin: 2px 0 6px; }
.smm .smm-iletisim a { text-decoration: none; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-envelope fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Site Mesajları</h4>
                <p class="text-muted mb-0 font-size-12">Kurumsal sitedeki iletişim formundan gelen mesajlar</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM MESAJ</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-envelope"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_toplam">Okundu: 0 | Cevaplandı: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all"><i class="bx bx-layer"></i> Tümü</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">YENİ (OKUNMAMIŞ)</span>
                    <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-bell"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-danger" id="stat_yeni">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-danger fw-semibold">Yanıt bekliyor</span>
                    <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="yeni"><i class="bx bx-bell"></i> Yeni</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">OKUNDU</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-show"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_okundu">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext">Henüz yanıtlanmadı</span>
                    <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="okundu"><i class="bx bx-show"></i> Okundu</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">CEVAPLANDI</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-double"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_cevaplandi">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold">Tamamlanan</span>
                    <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="cevaplandi"><i class="bx bx-check-circle"></i> Cevaplandı</button>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="sitemesajListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Mesaj Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Satıra tıklayarak mesajı açın; açılan mesaj otomatik olarak okundu işaretlenir</p>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table id="sitemesajTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th data-filter="string">GÖNDEREN</th>
                            <th data-filter="string">KONU</th>
                            <th data-filter="string">MESAJ</th>
                            <th data-filter="select" data-filter-options="Yeni|Okundu|Cevaplandı" style="width: 120px;">DURUM</th>
                            <th data-filter="date" style="width: 140px;">TARİH</th>
                            <th data-filter="none" style="width: 100px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade smm no-upgrade" id="sitemesajModal" tabindex="-1" aria-labelledby="sitemesajModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="d-flex align-items-center gap-3 px-4 pt-4">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;"><i class="bx bx-envelope fs-4"></i></div>
                <div class="flex-grow-1">
                    <h5 class="mb-0 fw-bold" id="sitemesajModalLabel">Mesaj</h5>
                    <p class="text-muted font-size-12 mb-0" id="mesajTarih"></p>
                </div>
                <button type="button" class="btn-close align-self-start" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="sitemesajForm" novalidate>
                <input type="hidden" id="mesaj_id" value="">
                <div class="modal-body px-4 pt-4 pb-2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="smm-sec">Gönderen</div>
                            <div class="fw-semibold" id="mesajAd"></div>
                            <div class="smm-iletisim font-size-13 mt-1" id="mesajIletisim"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="smm-sec">Konu</div>
                            <div class="fw-semibold" id="mesajKonu"></div>
                        </div>
                        <div class="col-12">
                            <div class="smm-sec">Mesaj</div>
                            <div class="smm-mesaj" id="mesajMetin"></div>
                        </div>
                        <div class="col-md-4"><?= Form::FormSelect2("durum", $durumOptions, "yeni", "Durum", "activity") ?></div>
                        <div class="col-md-8"><?= Form::FormFloatTextarea("yonetici_notu", "", "Yalnızca yöneticiler görür", "Yönetici Notu", "edit-3", "form-control", false, "90px") ?></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary text-white" id="btnMesajKaydet"><i class="bx bx-save me-1"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/site-yonetimi/js/mesajlar.js?v=<?php echo time(); ?>"></script>
