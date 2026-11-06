<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';

$maintitle = 'İşe Alım';
$title = 'İşe Alım Dashboard';
?>
<script>try { document.documentElement.classList.toggle('ikdash-summary-hidden', localStorage.getItem('ikdash_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.ikdash-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.ik-mini-liste a { text-decoration: none; color: inherit; }
.ik-mini-liste .satir { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; }
.ik-mini-liste .satir:last-child { border-bottom: 0; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-pie-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">İşe Alım Dashboard</h4>
                <p class="text-muted mb-0 font-size-12">Başvuru akışı, yapay zekâ ön değerlendirme sonuçları ve dönüşüm</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <select class="form-select form-select-sm" id="dashGun" style="width: 130px;" aria-label="Dönem">
                <option value="7">Son 7 gün</option>
                <option value="30" selected>Son 30 gün</option>
                <option value="90">Son 90 gün</option>
                <option value="365">Son 1 yıl</option>
            </select>
            <a href="index.php?p=ik-ise-alim/ilanlar" class="btn btn-primary top-action-btn shadow-sm text-white"><i class="bx bx-briefcase-alt-2 font-size-16"></i> İlanlar</a>
            <a href="index.php?p=ik-ise-alim/basvurular" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm"><i class="bx bx-user-check font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Başvurular</span></a>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet kartlarını göster veya gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">TOPLAM BAŞVURU</span>
                    <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-group"></i></div></div>
                <h3 class="summary-kpi-value my-1" id="k_toplam">0</h3>
                <span class="summary-kpi-subtext" id="k_toplam_alt">Son 7 gün: 0</span>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">DEĞERLENDİRME BEKLEYEN</span>
                    <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-time-five"></i></div></div>
                <h3 class="summary-kpi-value my-1" id="k_bekleyen">0</h3>
                <span class="summary-kpi-subtext" id="k_bekleyen_alt">AI hata/bekleyen: 0</span>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">AI OLUMLU ADAY</span>
                    <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-like"></i></div></div>
                <h3 class="summary-kpi-value my-1 text-success" id="k_olumlu">0</h3>
                <span class="summary-kpi-subtext" id="k_olumlu_alt">Ortalama puan: -</span>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">PERSONELE DÖNÜŞEN</span>
                    <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-id-card"></i></div></div>
                <h3 class="summary-kpi-value my-1" id="k_donusen">0</h3>
                <span class="summary-kpi-subtext" id="k_donusen_alt">Dönüşüm oranı: %0</span>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2">Günlük Başvuru Trendi</h5>
                <div id="chartGunluk" style="min-height: 280px;"></div>
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2">AI Etiket Dağılımı</h5>
                <div id="chartEtiket" style="min-height: 280px;"></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2">İlan Bazlı Başvuru ve Olumlu Aday</h5>
                <div id="chartIlan" style="min-height: 300px;"></div>
            </div></div>
        </div>
        <div class="col-xl-6">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2">AI Puan Dağılımı</h5>
                <div id="chartPuan" style="min-height: 300px;"></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2"><i class="bx bx-trophy text-warning me-1"></i> Değerlendirme Bekleyen Olumlu Adaylar</h5>
                <div class="ik-mini-liste" id="listeEnIyi"></div>
            </div></div>
        </div>
        <div class="col-xl-5">
            <div class="card summary-kpi-card h-100 mb-0"><div class="card-body">
                <h5 class="font-size-14 fw-bold mb-2"><i class="bx bx-error text-danger me-1"></i> Yapay Zekâ Sorunlu / Bekleyen</h5>
                <div class="ik-mini-liste" id="listeSorunlu"></div>
            </div></div>
        </div>
    </div>
</div>

<script src="views/ik-ise-alim/js/dashboard.js?v=<?php echo time(); ?>"></script>
