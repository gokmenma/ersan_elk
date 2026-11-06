<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';

$maintitle = 'İşe Alım';
$title = 'Başvurular';
?>
<script>try { document.documentElement.classList.toggle('ikbasvuru-summary-hidden', localStorage.getItem('ikbasvuru_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.ikbasvuru-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-user-check fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Aday Başvuruları</h4>
                <p class="text-muted mb-0 font-size-12">Yapay zekâ ön değerlendirmesi ile gelen başvurular</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <a href="index.php?p=ik-ise-alim/ilanlar" class="btn btn-primary top-action-btn shadow-sm text-white">
                <i class="bx bx-briefcase-alt-2 font-size-16"></i> İlanlar
            </a>
            <a href="index.php?p=ik-ise-alim/dashboard" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-pie-chart-alt-2 font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Dashboard</span>
            </a>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">TOPLAM BAŞVURU</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-group"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_toplam">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_toplam">Son 7 gün: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all"><i class="bx bx-layer"></i> Tümü</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">DEĞERLENDİRME BEKLEYEN</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-time-five"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_bekleyen">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_bekleyen">AI hata/bekleyen: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="bekleyen"><i class="bx bx-time"></i> Bekleyen</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">AI OLUMLU ADAY</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-like"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1 text-success" id="stat_ai_olumlu">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_ai">Belirsiz: 0 | Olumsuz: 0</span>
                    <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 etiket-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-etiket="olumlu"><i class="bx bx-check-circle"></i> Olumlu</button>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-label">PERSONELE DÖNÜŞEN</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-id-card"></i></div>
                </div>
                <h3 class="summary-kpi-value my-1" id="stat_donusen">0</h3>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="summary-kpi-subtext" id="stat_sub_puan">Ort. AI puanı: -</span>
                    <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="personele_donustu"><i class="bx bx-user-plus"></i> Dönüşen</button>
                </div>
            </div></div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="ikbasvuruListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Başvuru Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Yapay zekâ puanı yalnızca öneridir; nihai karar İK'ya aittir</p>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive">
                <table id="ikbasvuruTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th data-filter="string">ADAY</th>
                            <th data-filter="string">İLAN</th>
                            <th data-filter="string" style="width: 130px;">TELEFON</th>
                            <th data-filter="string" style="width: 120px;">ŞEHİR</th>
                            <th data-filter="number" class="text-center" style="width: 90px;">AI PUAN</th>
                            <th data-filter="select" data-filter-options="Olumlu|Belirsiz|Olumsuz" class="text-center" style="width: 110px;">AI ETİKET</th>
                            <th data-filter="select" data-filter-options="Yeni|Değerlendirildi|Görüşme|Olumlu|Olumsuz|Personele Dönüştü" style="width: 140px;">DURUM</th>
                            <th data-filter="date" style="width: 120px;">BAŞVURU</th>
                            <th data-filter="none" style="width: 110px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="views/ik-ise-alim/js/basvurular.js?v=<?php echo time(); ?>"></script>
