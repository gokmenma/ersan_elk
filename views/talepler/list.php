<?php

use App\Model\TalepModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\TalepDashboardModel;
use App\Helper\Form;
use App\Service\Gate;

// Yetki değişikliği yapıldıysa önbelleği temizle
if (isset($_SESSION['user_id'])) {
    unset($_SESSION['permission_cache'][$_SESSION['user_id']]);
}

$canAvans = Gate::allows('avans_talepleri');
$canIzin = Gate::allows('izin_talepleri');
$canAriza = Gate::allows('ariza_talepleri');
$canTalepler = Gate::allows('talepler');

// Eğer hiçbir yetki yoksa (güvenlik için)
if (!$canAvans && !$canIzin && !$canAriza && !$canTalepler) {
    Gate::authorizeOrDie('talepler'); 
}

$talepModel = new TalepModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$dashboardModel = new TalepDashboardModel();
$dashboardData = $dashboardModel->getYoneticiOzeti($canAvans, $canIzin, $canAriza);

// URL parametresi ile görünüm tipi
$showApproved = isset($_GET['show']) && $_GET['show'] === 'approved';
$showDeleted = isset($_GET['show']) && $_GET['show'] === 'deleted';

// Bekleyen talep sayıları
$avansCount = $avansModel->getBekleyenAvansSayisi();

try {
    $izinCount = $izinModel->getBekleyenIzinSayisi();
} catch (\Exception $e) {
    $izinCount = 0;
}

$talepCount = $talepModel->getBekleyenTalepSayisi();
$toplamCount = 0;
if ($canAvans) $toplamCount += $avansCount;
if ($canIzin) $toplamCount += $izinCount;
if ($canAriza) $toplamCount += $talepCount;

// Hangi tabın aktif olacağını belirle:
// Eğer URL'de tab belirtilmemişse, bekleyen kaydı olan sekmeye veya Dashboard'a yönlendir
$currentTab = $_GET['tab'] ?? '';
if (empty($currentTab)) {
    if ($avansCount > 0 && $canAvans) {
        $currentTab = 'avans';
    } elseif ($izinCount > 0 && $canIzin) {
        $currentTab = 'izin';
    } elseif ($talepCount > 0 && $canAriza) {
        $currentTab = 'talepler';
    } else {
        $currentTab = 'dashboard';
    }
} else {
    // Yetki kontrolü
    if (($currentTab == 'dashboard' && !$canAvans && !$canIzin && !$canAriza) ||
        ($currentTab == 'avans' && !$canAvans) || 
        ($currentTab == 'izin' && !$canIzin) || 
        ($currentTab == 'talepler' && !$canAriza)) {
        $currentTab = 'dashboard';
    }
}

if ($showApproved) {
    $avanslar = $avansModel->getIslenmisAvanslar(50);
    try {
        $izinler = $izinModel->getIslenmisIzinler(50);
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getCozulmusTalepler(50);
} elseif ($showDeleted) {
    $avanslar = $avansModel->getSilinmisAvanslar(50);
    try {
        $izinler = $izinModel->getSilinmisIzinler(50);
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getSilinmisTalepler(50);
} else {
    $avanslar = $avansModel->getButunBekleyenAvanslar();
    try {
        $izinler = $izinModel->getButunBekleyenIzinler();
    } catch (\Exception $e) {
        $izinler = [];
    }
    $talepler = $talepModel->getButunBekleyenTalepler();
}

$maintitle = "Talepler";
$title = "Talep Yönetimi";
?>
<script>try { document.documentElement.classList.toggle('talepler-summary-hidden', localStorage.getItem('talepler_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<link rel="stylesheet" href="views/talepler/assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-8 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-task fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Talep Yönetimi</h4>
                <p class="text-muted mb-0 font-size-12">Personel avans, izin ve genel destek talepleri, onay süreçleri ve analitik göstergeler</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-4 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM BEKLEYEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM BEKLEYEN</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-bell"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning"><?= (int) $toplamCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">İşlem Bekleyen Talepler</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 summary-pill-btn tab-switch-btn <?= $currentTab == 'dashboard' ? 'active' : '' ?>" data-tab-target="#tabDashboard">
                            <i class="bx bx-layer"></i> <span>Tümü</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: AVANS TALEPLERİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">AVANS TALEPLERİ</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-money"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success"><?= (int) $avansCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Bekleyen Avans Onayı</span>
                        <?php if ($canAvans): ?>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 summary-pill-btn tab-switch-btn <?= $currentTab == 'avans' ? 'active' : '' ?>" data-tab-target="#tabAvans">
                            <i class="bx bx-show"></i> <span>İncele</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: İZİN TALEPLERİ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">İZİN TALEPLERİ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-calendar-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-primary"><?= (int) $izinCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-primary fw-semibold">Bekleyen İzin Onayı</span>
                        <?php if ($canIzin): ?>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 summary-pill-btn tab-switch-btn <?= $currentTab == 'izin' ? 'active' : '' ?>" data-tab-target="#tabIzin">
                            <i class="bx bx-show"></i> <span>İncele</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: GENEL TALEPLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">GENEL TALEPLER</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-message-square-detail"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info"><?= (int) $talepCount ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold">Destek & Arıza Bildirimi</span>
                        <?php if ($canAriza): ?>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 summary-pill-btn tab-switch-btn <?= $currentTab == 'talepler' ? 'active' : '' ?>" data-tab-target="#tabTalepler">
                            <i class="bx bx-show"></i> <span>İncele</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Minimal DataTables Talep Listesi & Tab Kartı -->
    <div class="card summary-kpi-card mb-3" id="taleplerListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <!-- Sol: Renkli ve Net Okunur Tab Menüsü -->
            <ul class="nav nav-pills talep-nav-pills mb-0" id="talepTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link tab-dashboard <?= $currentTab == 'dashboard' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabDashboard" role="tab">
                        <i class="bx bx-grid-alt font-size-16"></i> <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($canAvans): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link tab-avans <?= $currentTab == 'avans' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabAvans" role="tab">
                        <i class="bx bx-money font-size-16"></i> <span>Avans Talepleri</span>
                        <?php if (!$showApproved && !$showDeleted && $avansCount > 0): ?>
                            <span class="badge ms-1"><?= $avansCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canIzin): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link tab-izin <?= $currentTab == 'izin' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabIzin" role="tab">
                        <i class="bx bx-calendar-check font-size-16"></i> <span>İzin Talepleri</span>
                        <?php if (!$showApproved && !$showDeleted && $izinCount > 0): ?>
                            <span class="badge ms-1"><?= $izinCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($canAriza): ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link tab-talepler <?= $currentTab == 'talepler' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tabTalepler" role="tab">
                        <i class="bx bx-message-square-detail font-size-16"></i> <span>Genel Talepler</span>
                        <?php if (!$showApproved && !$showDeleted && $talepCount > 0): ?>
                            <span class="badge ms-1"><?= $talepCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Sağ Araç Çubuğu: Durum Filtreleri (Bekleyen/İşlem/Silinen) + Excel + Yazdır -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <?php
                $currentTabForUrl = $currentTab;
                $pendingUrl = "index.php?p=talepler/list&tab=" . $currentTabForUrl;
                $approvedUrl = "index.php?p=talepler/list&show=approved&tab=" . $currentTabForUrl;
                $deletedUrl = "index.php?p=talepler/list&show=deleted&tab=" . $currentTabForUrl;
                ?>
                <div class="view-status-pills shadow-xs me-1">
                    <a href="<?= $pendingUrl ?>" id="btnShowPending" class="view-status-btn is-pending <?= !$showApproved && !$showDeleted ? 'active' : '' ?>" title="Bekleyen Talepler">
                        <i class="bx bx-time me-1"></i><span>Bekleyenler</span>
                    </a>
                    <a href="<?= $approvedUrl ?>" id="btnShowApproved" class="view-status-btn is-approved <?= $showApproved ? 'active' : '' ?>" title="İşlem Yapılanlar">
                        <i class="bx bx-check-circle me-1"></i><span>İşlem Yapılanlar</span>
                    </a>
                    <a href="<?= $deletedUrl ?>" id="btnShowDeleted" class="view-status-btn is-deleted <?= $showDeleted ? 'active' : '' ?>" title="Silinen Talepler">
                        <i class="bx bx-trash me-1"></i><span>Silinenler</span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Aktif Tabloyu Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <?php if ($showApproved): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3 rounded-3 py-2 px-3">
                    <i class="bx bx-check-circle font-size-18"></i>
                    <div>
                        <strong>İşlem Yapılmış Talepler:</strong> Onaylanan ve reddedilen son 50 talep kaydı listelenmektedir.
                    </div>
                </div>
            <?php elseif ($showDeleted): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 rounded-3 py-2 px-3">
                    <i class="bx bx-trash font-size-18"></i>
                    <div>
                        <strong>Silinmiş Kayıtlar:</strong> Sistemden kaldırılmış son 50 talep kaydı listelenmektedir.
                    </div>
                </div>
            <?php endif; ?>

            <div class="tab-content">
                <!-- 1. TAB: DASHBOARD -->
                <div class="tab-pane fade <?= $currentTab == 'dashboard' ? 'show active' : '' ?>" id="tabDashboard" role="tabpanel">
                    <div class="dashboard-metric-grid mb-3">
                        <article class="dashboard-metric metric-blue">
                            <div class="metric-icon"><i class="bx bx-layer"></i></div>
                            <div><span>6 Aylık Toplam</span><strong><?= (int) $dashboardData['total'] ?></strong><small>Tüm talep türleri</small></div>
                        </article>
                        <article class="dashboard-metric metric-amber">
                            <div class="metric-icon"><i class="bx bx-time-five"></i></div>
                            <div><span>Bekleyen</span><strong><?= (int) $dashboardData['pending'] ?></strong><small>En eski: <?= (int) $dashboardData['oldest_pending_days'] ?> gün</small></div>
                        </article>
                        <article class="dashboard-metric metric-green">
                            <div class="metric-icon"><i class="bx bx-check-shield"></i></div>
                            <div><span>Olumlu Sonuç</span><strong>%<?= number_format((float) $dashboardData['approval_rate'], 1, ',', '.') ?></strong><small><?= (int) $dashboardData['completed'] ?> tamamlanan işlem</small></div>
                        </article>
                        <article class="dashboard-metric metric-purple">
                            <div class="metric-icon"><i class="bx bx-stopwatch"></i></div>
                            <div><span>Ort. Sonuçlanma</span><strong><?= number_format((float) $dashboardData['avg_resolution_hours'], 1, ',', '.') ?> sa.</strong><small>Tamamlanan talepler</small></div>
                        </article>
                    </div>

                    <div class="row g-3">
                        <div class="col-xl-8">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Talep Trendi</h5><p>Son altı ayda açılan talepler</p></div><span class="dashboard-period"><i class="bx bx-calendar"></i> 6 Ay</span></div>
                                <div id="requestTrendChart" class="dashboard-chart"></div>
                            </section>
                        </div>
                        <div class="col-xl-4">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Tür Dağılımı</h5><p>Talep hacminin dağılımı</p></div></div>
                                <div id="requestTypeChart" class="dashboard-chart"></div>
                            </section>
                        </div>
                        <div class="col-xl-7">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Bekleyen İş Yükü</h5><p>En yoğun ilk beş departman</p></div></div>
                                <div class="department-load-list">
                                    <?php $maxDepartment = max(array_values($dashboardData['departments']) ?: [1]); ?>
                                    <?php foreach ($dashboardData['departments'] as $department => $count): ?>
                                        <div class="department-load-item">
                                            <div><span><?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?></span><strong><?= (int) $count ?></strong></div>
                                            <div class="department-load-track"><span style="width: <?= round(((int) $count / $maxDepartment) * 100, 1) ?>%"></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!$dashboardData['departments']): ?><div class="dashboard-empty">Bekleyen departman yükü bulunmuyor.</div><?php endif; ?>
                                </div>
                            </section>
                        </div>
                        <div class="col-xl-5">
                            <section class="dashboard-panel dashboard-insight-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Yönetici Notları</h5><p>Hızlı değerlendirme göstergeleri</p></div></div>
                                <div class="dashboard-insight"><i class="bx bx-wallet"></i><div><span>Bekleyen avans tutarı</span><strong><?= number_format((float) $dashboardData['pending_advance_amount'], 2, ',', '.') ?> ₺</strong></div></div>
                                <div class="dashboard-insight"><i class="bx bx-alarm-exclamation"></i><div><span>En uzun bekleme</span><strong><?= (int) $dashboardData['oldest_pending_days'] ?> gün</strong></div></div>
                                <div class="dashboard-insight"><i class="bx bx-check-double"></i><div><span>Sonuçlanan işlem</span><strong><?= (int) $dashboardData['completed'] ?> kayıt</strong></div></div>
                            </section>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-4">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>En Çok Talep Oluşturanlar</h5><p>Son altı aylık personel sıralaması</p></div><i class="bx bx-group dashboard-head-icon"></i></div>
                                <div class="requester-ranking">
                                    <?php $rank = 0; foreach ($dashboardData['top_requesters'] as $person => $count): $rank++; ?>
                                        <div class="requester-rank-item">
                                            <span class="rank-number"><?= $rank ?></span>
                                            <div class="rank-person"><strong><?= htmlspecialchars($person, ENT_QUOTES, 'UTF-8') ?></strong><small><?= (int) $count ?> talep</small></div>
                                            <span class="rank-value"><?= (int) $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (!$dashboardData['top_requesters']): ?><div class="dashboard-empty">Personel talep verisi bulunmuyor.</div><?php endif; ?>
                                </div>
                            </section>
                        </div>
                        <div class="col-xl-4">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Talep Nedenleri</h5><p>Açıklamalardan çıkarılan konu kümeleri</p></div><span class="analysis-badge"><i class="bx bx-brain"></i> Akıllı analiz</span></div>
                                <div id="requestThemeChart" class="dashboard-chart"></div>
                            </section>
                        </div>
                        <div class="col-xl-4">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Şikâyet Sinyalleri</h5><p>Metinlerde tekrarlanan sorun ifadeleri</p></div><i class="bx bx-message-square-error dashboard-head-icon text-danger"></i></div>
                                <div class="complaint-cloud">
                                    <?php foreach ($dashboardData['complaint_signals'] as $signal => $count): ?>
                                        <span><b><?= htmlspecialchars(ucfirst($signal), ENT_QUOTES, 'UTF-8') ?></b><em><?= (int) $count ?></em></span>
                                    <?php endforeach; ?>
                                    <?php if (!$dashboardData['complaint_signals']): ?><div class="dashboard-empty">Belirgin şikâyet sinyali bulunmuyor.</div><?php endif; ?>
                                </div>
                                <div class="analysis-note"><i class="bx bx-info-circle"></i> Analiz, açıklama ve başlıklardaki Türkçe bağlam sözcüklerine dayanır; karar desteği amaçlıdır.</div>
                            </section>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-xl-8">
                            <section class="dashboard-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Talep Kategorileri</h5><p>En sık kullanılan kategori ve izin nedenleri</p></div></div>
                                <div class="category-analysis-grid">
                                    <?php $maxCategory = max(array_values($dashboardData['categories']) ?: [1]); ?>
                                    <?php foreach ($dashboardData['categories'] as $category => $count): ?>
                                        <div class="category-analysis-item">
                                            <div><span><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></span><strong><?= (int) $count ?></strong></div>
                                            <div class="category-analysis-track"><span style="width: <?= round(((int) $count / $maxCategory) * 100, 1) ?>%"></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                        <div class="col-xl-4">
                            <section class="dashboard-panel dashboard-risk-panel h-100">
                                <div class="dashboard-panel-head"><div><h5>Risk Göstergeleri</h5><p>Yönetici müdahalesi gerektirebilecek alanlar</p></div></div>
                                <div class="risk-score"><span><?= (int) $dashboardData['high_priority'] ?></span><div><strong>Yüksek öncelikli talep</strong><small>Son altı ay</small></div></div>
                                <div class="risk-score"><span><?= (int) $dashboardData['oldest_pending_days'] ?></span><div><strong>En uzun bekleme</strong><small>Gün</small></div></div>
                                <div class="risk-score"><span><?= count($dashboardData['complaint_signals']) ?></span><div><strong>Farklı şikâyet sinyali</strong><small>Metin analizi</small></div></div>
                            </section>
                        </div>
                    </div>
                </div>

                <!-- 2. TAB: AVANS TALEPLERİ -->
                <?php if ($canAvans): ?>
                <div class="tab-pane fade <?= $currentTab == 'avans' ? 'show active' : '' ?>" id="tabAvans" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block" style="overflow-x: auto !important;">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable talep-table-custom"
                            id="avansTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">PERSONEL</th>
                                    <th data-filter="select" style="width: 110px;">TÜR</th>
                                    <th data-filter="number" class="text-end" style="width: 120px;">TUTAR</th>
                                    <th data-filter="date" style="width: 130px;">TALEP TARİHİ</th>
                                    <th data-filter="select" class="text-center" style="width: 120px;">DURUM</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <?php if ($showApproved || $showDeleted): ?>
                                        <th data-filter="string" style="width: 130px;">İŞLEM YAPAN</th>
                                        <th data-filter="string">SONUÇ AÇIKLAMASI</th>
                                    <?php endif; ?>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avanslar as $avans): ?>
                                    <tr data-id="<?= $avans->id ?>" data-tip="avans">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($avans->resim_yolu) ? $avans->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                    alt="" class="rounded-circle avatar-sm me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-0 font-size-13 fw-semibold">
                                                        <?= htmlspecialchars($avans->requester_name ?? '') ?>
                                                    </h6>
                                                    <small class="text-muted font-size-11">
                                                        <?= htmlspecialchars($avans->departman ?? '') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-money me-1"></i>Avans
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-success fw-bold font-size-13">
                                                <?= number_format($avans->tutar, 2, ',', '.') ?> ₺
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark font-size-12"><?= date('d.m.Y H:i', strtotime($avans->talep_tarihi)) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $durumClass = 'warning';
                                            $durumIcon = 'time-five';
                                            $durumText = ucfirst($avans->durum);
                                            if ($avans->durum == 'onaylandi') {
                                                $durumClass = 'success';
                                                $durumIcon = 'check-circle';
                                                $durumText = 'Onaylandı';
                                            } elseif ($avans->durum == 'reddedildi') {
                                                $durumClass = 'danger';
                                                $durumIcon = 'x-circle';
                                                $durumText = 'Reddedildi';
                                            } elseif ($avans->durum == 'iptal edildi' || $avans->durum == 'İptal Edildi') {
                                                $durumClass = 'secondary';
                                                $durumIcon = 'minus-circle';
                                                $durumText = 'İptal Edildi';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-<?= $durumIcon ?> me-1"></i><?= $durumText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted font-size-12"><?= htmlspecialchars($avans->aciklama ?? '-') ?></span>
                                        </td>
                                        <?php if ($showApproved || $showDeleted): ?>
                                            <td>
                                                <div class="fw-semibold font-size-12"><?= htmlspecialchars($avans->solver_name ?? '-') ?></div>
                                                <?php if (!empty($avans->islem_tarihi)): ?>
                                                    <small class="text-muted d-block font-size-10">
                                                        <?= date('d.m.Y H:i', strtotime($avans->islem_tarihi)) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-muted font-size-12"><?= htmlspecialchars($avans->onay_aciklama ?? '-') ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <div class="action-btn-group justify-content-center">
                                                <?php if (!$showApproved && !$showDeleted && $avans->durum == 'beklemede'): ?>
                                                    <button class="table-action-btn btn-subtle-success btn-avans-onayla" type="button"
                                                        data-id="<?= $avans->id ?>"
                                                        data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"
                                                        data-tutar="<?= $avans->tutar ?>" title="Talebi Onayla">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                    <button class="table-action-btn btn-subtle-danger btn-avans-reddet" type="button"
                                                        data-id="<?= $avans->id ?>"
                                                        data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"
                                                        title="Talebi Reddet">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="table-action-btn btn-subtle-info btn-avans-detay" type="button"
                                                    data-id="<?= $avans->id ?>" title="Detay İncele">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if (!$showDeleted && $avans->durum == 'beklemede'): ?>
                                                    <button class="table-action-btn btn-subtle-danger btn-sil" type="button"
                                                        data-id="<?= $avans->id ?>" data-tip="avans" title="Kaydı Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobil Görünüm (Avans) -->
                    <div class="d-block d-lg-none mt-2">
                        <div class="row g-3">
                            <?php foreach ($avanslar as $avans): ?>
                                <div class="col-12" data-id="<?= $avans->id ?>" data-tip="avans">
                                    <div class="card summary-kpi-card shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($avans->resim_yolu) ? $avans->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" style="width: 34px; height: 34px; object-fit: cover;" alt="">
                                                    <div>
                                                        <h6 class="mb-0 font-size-13 fw-bold"><?= htmlspecialchars($avans->requester_name ?? '') ?></h6>
                                                        <small class="text-muted font-size-11"><?= date('d.m.Y H:i', strtotime($avans->talep_tarihi)) ?></small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-11 px-2 py-1"><i class="bx bx-money me-1"></i>Avans</span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-2 py-1 border-top border-bottom border-light">
                                                <span class="text-muted small">Talep Tutarı:</span>
                                                <span class="text-success fw-bold font-size-14"><?= number_format($avans->tutar, 2, ',', '.') ?> ₺</span>
                                            </div>
                                            <?php
                                            $durumClass = 'warning';
                                            $durumText = ucfirst($avans->durum);
                                            if ($avans->durum == 'onaylandi') { $durumClass = 'success'; $durumText = 'Onaylandı'; }
                                            if ($avans->durum == 'reddedildi') { $durumClass = 'danger'; $durumText = 'Reddedildi'; }
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11"><?= $durumText ?></span>
                                            </div>
                                            
                                            <?php if ($showApproved || $showDeleted): ?>
                                            <div class="mb-2 bg-light p-2 rounded-3 small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">İşlem Yapan:</span>
                                                    <span class="fw-semibold"><?= htmlspecialchars($avans->solver_name ?? '-') ?></span>
                                                </div>
                                                <?php if (!empty($avans->onay_aciklama)): ?>
                                                <div class="text-muted mt-1 font-size-11"><?= htmlspecialchars($avans->onay_aciklama) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if (!$showApproved && !$showDeleted && $avans->durum == 'beklemede'): ?>
                                                    <button class="btn btn-sm btn-subtle-success flex-fill btn-avans-onayla py-1" type="button" data-id="<?= $avans->id ?>" data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>" data-tutar="<?= $avans->tutar ?>"><i class="bx bx-check"></i> Onayla</button>
                                                    <button class="btn btn-sm btn-subtle-danger flex-fill btn-avans-reddet py-1" type="button" data-id="<?= $avans->id ?>" data-personel="<?= htmlspecialchars($avans->requester_name ?? '') ?>"><i class="bx bx-x"></i> Red</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-subtle-info flex-fill btn-avans-detay py-1" type="button" data-id="<?= $avans->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($avanslar)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0 rounded-3">Kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 3. TAB: İZİN TALEPLERİ -->
                <?php if ($canIzin): ?>
                <div class="tab-pane fade <?= $currentTab == 'izin' ? 'show active' : '' ?>" id="tabIzin" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block" style="overflow-x: auto !important;">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable talep-table-custom"
                            id="izinTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">PERSONEL</th>
                                    <th data-filter="select" style="width: 100px;">TÜR</th>
                                    <th data-filter="select" style="width: 120px;">İZİN TÜRÜ</th>
                                    <th data-filter="date" style="width: 160px;">TARİH ARALIĞI</th>
                                    <th data-filter="number" class="text-center" style="width: 90px;">SÜRE</th>
                                    <th data-filter="select" class="text-center" style="width: 120px;">DURUM</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <?php if ($showApproved || $showDeleted): ?>
                                        <th data-filter="string" style="width: 130px;">İŞLEM YAPAN</th>
                                        <th data-filter="string">SONUÇ AÇIKLAMASI</th>
                                    <?php endif; ?>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($izinler as $izin):
                                    $gunSayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
                                    $izinTuruLabel = $izin->izin_tipi_adi ?? $izin->izin_tipi ?? 'Belirtilmemiş';
                                    ?>
                                    <tr data-id="<?= $izin->id ?>" data-tip="izin">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($izin->resim_yolu) ? $izin->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                    alt="" class="rounded-circle avatar-sm me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-0 font-size-13 fw-semibold">
                                                        <?= htmlspecialchars($izin->requester_name ?? '') ?>
                                                    </h6>
                                                    <small class="text-muted font-size-11">
                                                        <?= htmlspecialchars($izin->departman ?? '') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-calendar-check me-1"></i>İzin
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <?= htmlspecialchars($izinTuruLabel) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-size-12 text-dark">
                                                <?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <?= $gunSayisi ?> Gün
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $durumClass = 'warning';
                                            $durumIcon = 'time-five';
                                            $durumText = ucfirst($izin->onay_durumu);
                                            if ($izin->onay_durumu == 'Onaylandı') {
                                                $durumClass = 'success';
                                                $durumIcon = 'check-circle';
                                            } elseif ($izin->onay_durumu == 'Reddedildi') {
                                                $durumClass = 'danger';
                                                $durumIcon = 'x-circle';
                                            } elseif ($izin->onay_durumu == 'iptal edildi' || $izin->onay_durumu == 'İptal Edildi') {
                                                $durumClass = 'secondary';
                                                $durumIcon = 'minus-circle';
                                                $durumText = 'İptal Edildi';
                                            }
                                            ?>
                                            <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-<?= $durumIcon ?> me-1"></i><?= $durumText ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted font-size-12"><?= htmlspecialchars($izin->aciklama ?? '-') ?></span>
                                        </td>
                                        <?php if ($showApproved || $showDeleted): ?>
                                            <td>
                                                <div class="fw-semibold font-size-12"><?= htmlspecialchars($izin->solver_name ?? '-') ?></div>
                                                <?php if (!empty($izin->islem_tarihi)): ?>
                                                    <small class="text-muted d-block font-size-10">
                                                        <?= date('d.m.Y H:i', strtotime($izin->islem_tarihi)) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-muted font-size-12"><?= htmlspecialchars($izin->onay_aciklama ?? '-') ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <div class="action-btn-group justify-content-center">
                                                <?php if (!$showApproved && !$showDeleted && mb_strtolower($izin->onay_durumu, 'UTF-8') == 'beklemede'): ?>
                                                    <button class="table-action-btn btn-subtle-success btn-izin-onayla" type="button"
                                                        data-id="<?= $izin->id ?>"
                                                        data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                        data-tur="<?= htmlspecialchars($izinTuruLabel) ?>" data-gun="<?= $gunSayisi ?>"
                                                        data-baslangic="<?= $izin->baslangic_tarihi ?>"
                                                        data-bitis="<?= $izin->bitis_tarihi ?>"
                                                        title="İzni Onayla">
                                                        <i class="bx bx-check"></i>
                                                    </button>
                                                    <button class="table-action-btn btn-subtle-danger btn-izin-reddet" type="button"
                                                        data-id="<?= $izin->id ?>"
                                                        data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                        title="İzni Reddet">
                                                        <i class="bx bx-x"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="table-action-btn btn-subtle-secondary btn-izin-formu" type="button"
                                                    data-id="<?= $izin->id ?>"
                                                    data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                    title="İzin Formu (Önizle / Yazdır / Word İndir)">
                                                    <i class="bx bx-file"></i>
                                                </button>
                                                <button class="table-action-btn btn-subtle-info btn-izin-detay" type="button"
                                                    data-id="<?= $izin->id ?>" title="Detay İncele">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if (!$showDeleted && mb_strtolower($izin->onay_durumu, 'UTF-8') == 'beklemede'): ?>
                                                    <button class="table-action-btn btn-subtle-danger btn-sil" type="button"
                                                        data-id="<?= $izin->id ?>" data-tip="izin" title="Kaydı Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobil Görünüm (İzin) -->
                    <div class="d-block d-lg-none mt-2">
                        <div class="row g-3">
                            <?php foreach ($izinler as $izin): 
                                $gunSayisi = $izinModel->hesaplaIzinGunu($izin->baslangic_tarihi, $izin->bitis_tarihi);
                                $izinTuruLabel = $izin->izin_tipi_adi ?? $izin->izin_tipi ?? 'Belirtilmemiş';
                            ?>
                                <div class="col-12" data-id="<?= $izin->id ?>" data-tip="izin">
                                    <div class="card summary-kpi-card shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($izin->resim_yolu) ? $izin->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" style="width: 34px; height: 34px; object-fit: cover;" alt="">
                                                    <div>
                                                        <h6 class="mb-0 font-size-13 fw-bold"><?= htmlspecialchars($izin->requester_name ?? '') ?></h6>
                                                        <small class="text-muted"><span class="badge bg-info-subtle text-info rounded-pill font-size-10"><?= htmlspecialchars($izinTuruLabel) ?></span></small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill font-size-11 px-2 py-1"><?= $gunSayisi ?> Gün</span>
                                            </div>
                                            <div class="mb-2 text-muted small py-1 border-top border-bottom border-light">
                                                <i class="bx bx-calendar me-1"></i> <?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?>
                                            </div>
                                            <?php
                                            $durumClass = 'warning';
                                            $durumText = ucfirst($izin->onay_durumu);
                                            if ($izin->onay_durumu == 'Onaylandı') { $durumClass = 'success'; }
                                            if ($izin->onay_durumu == 'Reddedildi') { $durumClass = 'danger'; }
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11"><?= $durumText ?></span>
                                            </div>
                                            
                                            <?php if ($showApproved || $showDeleted): ?>
                                            <div class="mb-2 bg-light p-2 rounded-3 small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">İşlem Yapan:</span>
                                                    <span class="fw-semibold"><?= htmlspecialchars($izin->solver_name ?? '-') ?></span>
                                                </div>
                                                <?php if (!empty($izin->onay_aciklama)): ?>
                                                <div class="text-muted mt-1 font-size-11"><?= htmlspecialchars($izin->onay_aciklama) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if (!$showApproved && !$showDeleted && mb_strtolower($izin->onay_durumu, 'UTF-8') == 'beklemede'): ?>
                                                    <button class="btn btn-sm btn-subtle-success flex-fill btn-izin-onayla py-1" type="button" 
                                                        data-id="<?= $izin->id ?>" 
                                                        data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>" 
                                                        data-tur="<?= htmlspecialchars($izinTuruLabel) ?>" 
                                                        data-gun="<?= $gunSayisi ?>"
                                                        data-baslangic="<?= $izin->baslangic_tarihi ?>"
                                                        data-bitis="<?= $izin->bitis_tarihi ?>"><i class="bx bx-check"></i> Onayla</button>
                                                    <button class="btn btn-sm btn-subtle-danger flex-fill btn-izin-reddet py-1" type="button" data-id="<?= $izin->id ?>" data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"><i class="bx bx-x"></i> Red</button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-subtle-secondary flex-fill btn-izin-formu py-1" type="button" 
                                                    data-id="<?= $izin->id ?>" 
                                                    data-personel="<?= htmlspecialchars($izin->requester_name ?? '') ?>"
                                                    title="İzin Formu"><i class="bx bx-file"></i> Form</button>
                                                <button class="btn btn-sm btn-subtle-info flex-fill btn-izin-detay py-1" type="button" data-id="<?= $izin->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($izinler)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0 rounded-3">Kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 4. TAB: GENEL TALEPLER -->
                <?php if ($canAriza): ?>
                <div class="tab-pane fade <?= $currentTab == 'talepler' ? 'show active' : '' ?>" id="tabTalepler" role="tabpanel">
                    <div class="table-responsive d-none d-lg-block" style="overflow-x: auto !important;">
                        <table class="table datatables table-hover table-bordered nowrap align-middle w-100 datatable talep-table-custom"
                            id="taleplerTable" data-order="[]">
                            <thead class="table-light">
                                <tr>
                                    <th data-filter="string">PERSONEL</th>
                                    <th data-filter="select" style="width: 100px;">TÜR</th>
                                    <th data-filter="string">BAŞLIK</th>
                                    <th data-filter="select" class="text-center" style="width: 110px;">DURUM</th>
                                    <th data-filter="select" class="text-center" style="width: 100px;">ÖNCELİK</th>
                                    <th data-filter="date" style="width: 130px;">TARİH</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <?php if ($showApproved || $showDeleted): ?>
                                        <th data-filter="string" style="width: 130px;">İŞLEM YAPAN</th>
                                        <th data-filter="string">SONUÇ</th>
                                    <?php else: ?>
                                        <th data-filter="string">SONUÇ</th>
                                    <?php endif; ?>
                                    <th data-filter="none" class="text-center" style="width: 130px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($talepler as $talep):
                                    $durumClass = 'warning';
                                    $durumIcon = 'time-five';
                                    if ($talep->durum == 'islemde') {
                                        $durumClass = 'info';
                                        $durumIcon = 'play-circle';
                                    } elseif ($talep->durum == 'cozuldu' || $talep->durum == 'onaylandi') {
                                        $durumClass = 'success';
                                        $durumIcon = 'check-circle';
                                    } elseif ($talep->durum == 'reddedildi') {
                                        $durumClass = 'danger';
                                        $durumIcon = 'x-circle';
                                    } elseif ($talep->durum == 'iptal_edildi' || $talep->durum == 'iptal edildi' || $talep->durum == 'İptal Edildi') {
                                        $durumClass = 'secondary';
                                        $durumIcon = 'minus-circle';
                                    }

                                    $oncelikClass = 'secondary';
                                    $oncelikIcon = 'info-circle';
                                    if (isset($talep->oncelik)) {
                                        if ($talep->oncelik == 'yuksek') {
                                            $oncelikClass = 'danger';
                                            $oncelikIcon = 'error';
                                        } elseif ($talep->oncelik == 'orta') {
                                            $oncelikClass = 'warning';
                                            $oncelikIcon = 'error-circle';
                                        } elseif ($talep->oncelik == 'dusuk') {
                                            $oncelikClass = 'success';
                                            $oncelikIcon = 'check-double';
                                        }
                                    }
                                    ?>
                                    <tr data-id="<?= $talep->id ?>" data-tip="talep">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($talep->resim_yolu) ? $talep->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                    alt="" class="rounded-circle avatar-sm me-2 border" style="width: 32px; height: 32px; object-fit: cover;">
                                                <div>
                                                    <h6 class="mb-0 font-size-13 fw-semibold">
                                                        <?= htmlspecialchars($talep->requester_name ?? '') ?>
                                                    </h6>
                                                    <small class="text-muted font-size-11">
                                                        <?= htmlspecialchars($talep->departman ?? '') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-message-square-detail me-1"></i>Talep
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark font-size-13"><?= htmlspecialchars($talep->baslik ?? '-') ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $durumText = ucfirst($talep->durum);
                                            if ($talep->durum == 'cozuldu') $durumText = 'Çözüldü';
                                            if ($talep->durum == 'onaylandi') $durumText = 'Onaylandı';
                                            if ($talep->durum == 'reddedildi') $durumText = 'Reddedildi';
                                            if ($talep->durum == 'iptal_edildi' || $talep->durum == 'iptal edildi' || $talep->durum == 'İptal Edildi') $durumText = 'İptal Edildi';
                                            if ($talep->durum == 'islemde') $durumText = 'İşlemde';
                                            if ($talep->durum == 'beklemede') $durumText = 'Beklemede';
                                            ?>
                                            <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-<?= $durumIcon ?> me-1"></i><?= $durumText ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $oncelikClass ?>-subtle text-<?= $oncelikClass ?> border border-<?= $oncelikClass ?>-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                                <i class="bx bx-<?= $oncelikIcon ?> me-1"></i><?= ucfirst($talep->oncelik ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-size-12 text-dark"><?= date('d.m.Y H:i', strtotime($talep->olusturma_tarihi)) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted font-size-12"><?= htmlspecialchars($talep->aciklama ?? '-') ?></span>
                                        </td>
                                        <?php if ($showApproved || $showDeleted): ?>
                                            <td>
                                                <div class="fw-semibold font-size-12"><?= htmlspecialchars($talep->solver_name ?? '-') ?></div>
                                                <?php if (!empty($talep->islem_tarihi)): ?>
                                                    <small class="text-muted d-block font-size-10">
                                                        <?= date('d.m.Y H:i', strtotime($talep->islem_tarihi)) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="text-success font-size-12"><?= htmlspecialchars($talep->cozum_aciklama ?? '-') ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-btn-group justify-content-center">
                                                <?php if (!$showApproved && !$showDeleted): ?>
                                                    <?php if ($talep->durum == 'beklemede'): ?>
                                                        <button class="table-action-btn btn-subtle-warning btn-talep-isleme" type="button"
                                                            data-id="<?= $talep->id ?>" title="İşleme Al">
                                                            <i class="bx bx-play"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($talep->durum == 'beklemede' || $talep->durum == 'islemde'): ?>
                                                        <button class="table-action-btn btn-subtle-success btn-talep-cozuldu" type="button"
                                                            data-id="<?= $talep->id ?>"
                                                            data-personel="<?= htmlspecialchars($talep->requester_name ?? '') ?>"
                                                            data-baslik="<?= htmlspecialchars($talep->baslik ?? '') ?>"
                                                            title="Çözüldü Olarak İşaretle">
                                                            <i class="bx bx-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <button class="table-action-btn btn-subtle-info btn-talep-detay" type="button"
                                                    data-id="<?= $talep->id ?>" title="Detay İncele">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                <?php if (!$showDeleted && $talep->durum == 'beklemede'): ?>
                                                    <button class="table-action-btn btn-subtle-danger btn-sil" type="button"
                                                        data-id="<?= $talep->id ?>" data-tip="talep" title="Kaydı Sil">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobil Görünüm (Talepler) -->
                    <div class="d-block d-lg-none mt-2">
                        <div class="row g-3">
                            <?php foreach ($talepler as $talep): 
                                $durumClass = 'warning';
                                if ($talep->durum == 'islemde') $durumClass = 'info';
                                if ($talep->durum == 'cozuldu' || $talep->durum == 'onaylandi') $durumClass = 'success';
                                if ($talep->durum == 'reddedildi' || $talep->durum == 'iptal_edildi') $durumClass = 'danger';

                                $oncelikClass = 'secondary';
                                if (isset($talep->oncelik)) {
                                    if ($talep->oncelik == 'yuksek') $oncelikClass = 'danger';
                                    if ($talep->oncelik == 'orta') $oncelikClass = 'warning';
                                    if ($talep->oncelik == 'dusuk') $oncelikClass = 'success';
                                }
                            ?>
                                <div class="col-12" data-id="<?= $talep->id ?>" data-tip="talep">
                                    <div class="card summary-kpi-card shadow-sm h-100">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?= !empty($talep->resim_yolu) ? $talep->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" class="rounded-circle avatar-sm" style="width: 34px; height: 34px; object-fit: cover;" alt="">
                                                    <div>
                                                        <h6 class="mb-0 font-size-13 fw-bold"><?= htmlspecialchars($talep->requester_name ?? '') ?></h6>
                                                        <small class="text-muted"><span class="badge bg-<?= $oncelikClass ?>-subtle text-<?= $oncelikClass ?> rounded-pill font-size-10"><?= ucfirst($talep->oncelik ?? 'Normal') ?> Öncelik</span></small>
                                                    </div>
                                                </div>
                                                <span class="text-muted font-size-11"><?= date('d.m.Y H:i', strtotime($talep->olusturma_tarihi)) ?></span>
                                            </div>
                                            <div class="mb-2 py-1 border-top border-bottom border-light">
                                                <h6 class="mb-1 font-size-13 fw-bold text-dark"><?= htmlspecialchars($talep->baslik ?? '-') ?></h6>
                                                <p class="text-muted small mb-0 text-truncate"><?= htmlspecialchars($talep->aciklama ?? '-') ?></p>
                                            </div>
                                            <?php
                                            $durumText = ucfirst($talep->durum);
                                            if ($talep->durum == 'cozuldu') $durumText = 'Çözüldü';
                                            if ($talep->durum == 'onaylandi') $durumText = 'Onaylandı';
                                            if ($talep->durum == 'reddedildi') $durumText = 'Reddedildi';
                                            if ($talep->durum == 'iptal_edildi') $durumText = 'İptal Edildi';
                                            if ($talep->durum == 'islemde') $durumText = 'İşlemde';
                                            if ($talep->durum == 'beklemede') $durumText = 'Beklemede';
                                            ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-muted small">Durum:</span>
                                                <span class="badge bg-<?= $durumClass ?>-subtle text-<?= $durumClass ?> border border-<?= $durumClass ?>-subtle rounded-pill px-2 py-1 font-size-11"><?= $durumText ?></span>
                                            </div>

                                            <?php if ($showApproved || $showDeleted): ?>
                                            <div class="mb-2 bg-light p-2 rounded-3 small">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span class="text-muted">İşlem Yapan:</span>
                                                    <span class="fw-semibold"><?= htmlspecialchars($talep->solver_name ?? '-') ?></span>
                                                </div>
                                                <?php if (!empty($talep->cozum_aciklama)): ?>
                                                <div class="text-success mt-1 font-size-11"><?= htmlspecialchars($talep->cozum_aciklama) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-2 pt-2 border-top">
                                                <?php if (!$showApproved && !$showDeleted): ?>
                                                    <?php if ($talep->durum == 'beklemede'): ?>
                                                        <button class="btn btn-sm btn-subtle-warning flex-fill btn-talep-isleme py-1" type="button" data-id="<?= $talep->id ?>"><i class="bx bx-play"></i> İşleme Al</button>
                                                    <?php endif; ?>
                                                    <?php if ($talep->durum == 'beklemede' || $talep->durum == 'islemde'): ?>
                                                        <button class="btn btn-sm btn-subtle-success flex-fill btn-talep-cozuldu py-1" type="button" data-id="<?= $talep->id ?>" data-personel="<?= htmlspecialchars($talep->requester_name ?? '') ?>" data-baslik="<?= htmlspecialchars($talep->baslik ?? '') ?>"><i class="bx bx-check"></i> Çözüldü</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-subtle-info flex-fill btn-talep-detay py-1" type="button" data-id="<?= $talep->id ?>"><i class="bx bx-show"></i> Detay</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($talepler)): ?>
                                <div class="col-12"><div class="alert alert-info text-center mb-0 rounded-3">Kayıt bulunamadı.</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODALLAR
     ============================================ -->

<!-- Avans Onay Modal -->
<div class="modal fade" id="modalAvansOnay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-check-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">Avans Talebi Onayı</h5>
                        <p class="text-muted small mb-0">Personel avans talebini onaylayın veya tutarı düzenleyin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formAvansOnay">
                <input type="hidden" name="id" id="avans_onay_id">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-success-subtle text-success border border-success-subtle rounded-3 p-3 mb-3">
                        <strong id="avans_onay_personel"></strong> personelinin
                        <strong id="avans_onay_tutar" class="text-success fs-6"></strong> tutarındaki avans talebi onaylanacaktır.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Onay Tipi</label>
                        <div class="d-flex gap-3 bg-light p-2 rounded-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="onay_tipi" id="onayAyni" value="ayni" checked>
                                <label class="form-check-label font-size-13 fw-medium" for="onayAyni">Talep Edilen Tutarda Onayla</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="onay_tipi" id="onayFarkli" value="farkli">
                                <label class="form-check-label font-size-13 fw-medium" for="onayFarkli">Farklı Tutar Belirle</label>
                            </div>
                        </div>
                    </div>

                    <div id="farkliTutarAlani" class="mb-3 d-none">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Onaylanan Tutar (TL) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control fw-bold" name="farkli_tutar" id="farkli_tutar" placeholder="0,00">
                            <span class="input-group-text bg-light">₺</span>
                        </div>
                        <small class="text-muted font-size-11">Örn: 5000,00</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Onay Açıklaması (Opsiyonel)</label>
                        <textarea class="form-control font-size-13" name="aciklama" rows="2" placeholder="Personele iletilecek onay notu..."></textarea>
                    </div>

                    <div class="form-check p-2 bg-light rounded-3 border">
                        <input type="hidden" name="hesaba_isle" value="1">
                        <input class="form-check-input ms-1 me-2" type="checkbox" id="hesabaIsle" value="1" checked disabled>
                        <label class="form-check-label font-size-12 fw-semibold text-dark" for="hesabaIsle">
                            Avansı bordro modülüne kesinti olarak otomatik işle
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-check me-1"></i> Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Avans Red Modal -->
<div class="modal fade" id="modalAvansRed" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-x-circle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">Avans Talebi Reddi</h5>
                        <p class="text-muted small mb-0">Talebin reddedilme gerekçesini belirtin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formAvansRed">
                <input type="hidden" name="id" id="avans_red_id">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-danger-subtle text-danger border border-danger-subtle rounded-3 p-3 mb-3">
                        <strong id="avans_red_personel"></strong> personelinin avans talebini reddetmek üzeresiniz.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Red Açıklaması <span class="text-danger">*</span></label>
                        <textarea class="form-control font-size-13" name="aciklama" rows="3" placeholder="Personele iletilecek red gerekçesi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-x me-1"></i> Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İzin Onay Modal -->
<div class="modal fade" id="modalIzinOnay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-calendar-check fs-4 text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">İzin Talebi Onayı</h5>
                        <p class="text-muted small mb-0">İzin tarihlerini ve onay durumunu doğrulayın.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formIzinOnay">
                <input type="hidden" name="id" id="izin_onay_id">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-success-subtle text-success border border-success-subtle rounded-3 p-3 mb-3">
                        <strong id="izin_onay_personel"></strong> personelinin
                        <strong id="izin_onay_gun"></strong> günlük <strong id="izin_onay_tur"></strong> talebi onaylanacaktır.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Onay Tipi</label>
                        <div class="d-flex gap-3 bg-light p-2 rounded-3 border">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="onay_tipi" id="izinOnayAyni" value="ayni" checked>
                                <label class="form-check-label font-size-13 fw-medium" for="izinOnayAyni">Talep Edilen Tarihler</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="onay_tipi" id="izinOnayFarkli" value="farkli">
                                <label class="form-check-label font-size-13 fw-medium" for="izinOnayFarkli">Farklı Tarihler Belirle</label>
                            </div>
                        </div>
                    </div>

                    <div id="farkliIzinTarihAlani" class="mb-3 d-none">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label font-size-11 fw-bold text-muted mb-1">Başlangıç Tarihi</label>
                                <input type="date" class="form-control font-size-13" name="farkli_baslangic" id="farkli_baslangic">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label font-size-11 fw-bold text-muted mb-1">Bitiş Tarihi</label>
                                <input type="date" class="form-control font-size-13" name="farkli_bitis" id="farkli_bitis">
                            </div>
                        </div>
                        <div class="mt-2 text-primary font-size-12 fw-semibold">
                            <i class="bx bx-info-circle me-1"></i>Yeni Onaylanan Süre: <span id="farkli_izin_gun_metni" class="fw-bold">0</span> Gün
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Onay Açıklaması (Opsiyonel)</label>
                        <textarea class="form-control font-size-13" name="aciklama" id="izin_onay_aciklama" rows="2" placeholder="Personele iletilecek not..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-check me-1"></i> Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İzin Red Modal -->
<div class="modal fade" id="modalIzinRed" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-x-circle fs-4 text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">İzin Talebi Reddi</h5>
                        <p class="text-muted small mb-0">İzin reddedilme gerekçesini belirtin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formIzinRed">
                <input type="hidden" name="id" id="izin_red_id">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-danger-subtle text-danger border border-danger-subtle rounded-3 p-3 mb-3">
                        <strong id="izin_red_personel"></strong> personelinin izin talebini reddetmek üzeresiniz.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Red Açıklaması <span class="text-danger">*</span></label>
                        <textarea class="form-control font-size-13" name="aciklama" rows="3" placeholder="Personele iletilecek red gerekçesi..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-x me-1"></i> Reddet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Talep Çözüldü Modal -->
<div class="modal fade" id="modalTalepCozuldu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-check-circle fs-4 text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">Genel Talep Çözümü</h5>
                        <p class="text-muted small mb-0">Talebin çözüm detayını ve sonucunu kaydedin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formTalepCozuldu">
                <input type="hidden" name="id" id="talep_cozuldu_id">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-success-subtle text-success border border-success-subtle rounded-3 p-3 mb-3">
                        <strong id="talep_cozuldu_baslik"></strong> başlıklı talebi çözüldü olarak işaretlemektesiniz.
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Çözüm Açıklaması / Notu</label>
                        <textarea class="form-control font-size-13" name="aciklama" rows="3" placeholder="Uygulanan çözüm veya işlem hakkında bilgi veriniz..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-check me-1"></i> Çözüldü Olarak Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modern Talep Detayı Modal -->
<div class="modal fade" id="modalDetay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header border-bottom bg-white py-3 px-4 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px;">
                        <i class="bx bx-detail font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark font-size-15">Talep Detay Bilgileri</h5>
                        <p class="text-muted small mb-0 font-size-11">Personel talep ve onay geçmişi</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div id="detayContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-light py-2.5 px-4 justify-content-end">
                <button type="button" class="btn btn-secondary px-4 rounded-3 fw-semibold font-size-13 shadow-xs" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Modern İzin Formu Önizleme / Yazdır / Word İndir Modalı -->
<div class="modal fade" id="modalIzinFormu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-header border-bottom bg-white py-2.5 px-4 align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-xs" style="width: 38px; height: 38px;">
                        <i class="bx bx-file font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark font-size-15">İzin Talep Formu</h5>
                        <p class="text-muted small mb-0 font-size-11">Önizleme, yazdırma ve Word (.docx) indirme</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-subtle-success px-3 py-1.5 rounded-3 fw-semibold shadow-xs d-flex align-items-center gap-1" id="btnModalIzinDocxHeader" title="Word (.docx) İndir">
                        <i class="bx bx-download font-size-15"></i> <span class="d-none d-sm-inline">Word İndir (.docx)</span><span class="d-inline d-sm-none">Word</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary px-3 py-1.5 rounded-3 fw-semibold shadow-xs d-flex align-items-center gap-1 text-white" id="btnModalIzinYazdirHeader" title="Yazdır">
                        <i class="bx bx-printer font-size-15"></i> <span>Yazdır</span>
                    </button>
                    <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light" style="max-height: calc(100vh - 180px); overflow-y: auto;">
                <div id="izinFormuIcerik">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-white py-2.5 px-4 justify-content-between">
                <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold font-size-13 shadow-xs" data-bs-dismiss="modal">Kapat</button>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-success px-3.5 py-1.5 rounded-3 fw-semibold font-size-13 shadow-xs d-flex align-items-center gap-1" id="btnModalIzinDocx">
                        <i class="bx bx-download font-size-16"></i> <span>Word İndir (.docx)</span>
                    </button>
                    <button type="button" class="btn btn-primary px-3.5 py-1.5 rounded-3 fw-semibold font-size-13 shadow-xs d-flex align-items-center gap-1 text-white" id="btnModalIzinYazdir">
                        <i class="bx bx-printer font-size-16"></i> <span>Yazdır</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Silme Modal -->
<div class="modal fade" id="modalSil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 align-items-start">
                <div class="d-flex align-items-center">
                    <div class="bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px;">
                        <i class="bx bx-trash fs-4 text-danger"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-1 text-dark">Talep Kaydını Sil</h5>
                        <p class="text-muted small mb-0">Bu işlem kaydı silinmişler durumuna taşıyacaktır.</p>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formSil">
                <input type="hidden" name="id" id="sil_id">
                <input type="hidden" name="action" id="sil_action">
                <div class="modal-body px-4 pt-3 pb-2">
                    <div class="alert alert-warning-subtle text-warning border border-warning-subtle rounded-3 p-3 mb-3">
                        <i class="bx bx-error-circle me-1"></i> Bu talep kaydını silmek istediğinize emin misiniz?
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-size-12 fw-bold text-muted mb-1">Silme Gerekçesi <span class="text-danger">*</span></label>
                        <textarea class="form-control font-size-13" name="aciklama" rows="3" placeholder="Silme sebebini açıklayınız..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-2 pb-4 px-4 justify-content-between">
                    <button type="button" class="btn btn-light px-4 rounded-3 fw-semibold" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger px-4 rounded-3 shadow-sm fw-semibold">
                        <i class="bx bx-trash me-1"></i> Kaydı Sil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const API_URL = 'views/talepler/api.php';
    const summaryToggleButton = document.getElementById('btnToggleSummaryCards');
    const summaryStorageKey = 'talepler_summary_cards_state';

    // 1. Özet Kartları Açma / Kapama Mantığı (AGENTS.md Standardı)
    function updateSummaryToggleButton() {
        if (!summaryToggleButton) return;
        const isHidden = document.documentElement.classList.contains('talepler-summary-hidden');
        const icon = summaryToggleButton.querySelector('i');
        summaryToggleButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        summaryToggleButton.setAttribute('title', isHidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle');
        if (icon) {
            icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
        }
    }

    updateSummaryToggleButton();

    summaryToggleButton?.addEventListener('click', function () {
        const isHidden = document.documentElement.classList.toggle('talepler-summary-hidden');
        try {
            localStorage.setItem(summaryStorageKey, isHidden ? 'hidden' : 'visible');
        } catch (e) {}
        updateSummaryToggleButton();
    });

    // KPI Kartlarındaki Butonlara basınca ilgili sekmeye geçiş
    document.querySelectorAll('.tab-switch-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetSelector = this.getAttribute('data-tab-target');
            if (targetSelector) {
                const tabLink = document.querySelector(`.talep-nav-pills a[href="${targetSelector}"]`);
                if (tabLink) {
                    new bootstrap.Tab(tabLink).show();
                }
            }
        });
    });

    // 2. Dashboard Grafikleri Başlatma
    let dashboardChartsInitialized = false;
    const dashboardChartData = <?= json_encode($dashboardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function initializeDashboardCharts() {
        if (dashboardChartsInitialized) return;
        if (typeof ApexCharts === 'undefined') {
            if (!document.getElementById('taleplerApexChartsLoader')) {
                const chartScript = document.createElement('script');
                chartScript.id = 'taleplerApexChartsLoader';
                chartScript.src = 'assets/libs/apexcharts/apexcharts.min.js';
                chartScript.onload = initializeDashboardCharts;
                document.head.appendChild(chartScript);
            }
            return;
        }

        const trendElement = document.querySelector('#requestTrendChart');
        const typeElement = document.querySelector('#requestTypeChart');
        const themeElement = document.querySelector('#requestThemeChart');
        if (!trendElement || !typeElement || !themeElement) return;

        const months = dashboardChartData.months || [];
        const sharedOptions = {
            chart: { toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#64748b' },
            dataLabels: { enabled: false },
            grid: { borderColor: '#e7edf4', strokeDashArray: 4 },
            tooltip: { theme: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light' }
        };

        new ApexCharts(trendElement, {
            ...sharedOptions,
            chart: { ...sharedOptions.chart, type: 'area', height: 300, stacked: false },
            series: [
                { name: 'Avans', data: months.map(item => item.Avans) },
                { name: 'İzin', data: months.map(item => item['İzin']) },
                { name: 'Talep', data: months.map(item => item.Talep) }
            ],
            colors: ['#10b981', '#3b82f6', '#06b6d4'],
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
            xaxis: { categories: months.map(item => item.label), axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => Math.round(value) } },
            legend: { position: 'top', horizontalAlign: 'right' }
        }).render();

        new ApexCharts(typeElement, {
            ...sharedOptions,
            chart: { ...sharedOptions.chart, type: 'donut', height: 300 },
            series: ['Avans', 'İzin', 'Talep'].map(type => Number(dashboardChartData.types[type] || 0)),
            labels: ['Avans', 'İzin', 'Talep'],
            colors: ['#10b981', '#3b82f6', '#06b6d4'],
            stroke: { width: 3, colors: ['#ffffff'] },
            legend: { position: 'bottom' },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Toplam' } } } } },
            noData: { text: 'Gösterilecek veri bulunamadı' }
        }).render();

        const themeLabels = Object.keys(dashboardChartData.themes || {});
        new ApexCharts(themeElement, {
            ...sharedOptions,
            chart: { ...sharedOptions.chart, type: 'bar', height: 300 },
            series: [{ name: 'Talep', data: themeLabels.map(label => Number(dashboardChartData.themes[label] || 0)) }],
            colors: ['#8b5cf6'],
            plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '58%' } },
            xaxis: { categories: themeLabels, min: 0, labels: { formatter: value => Math.round(value) } },
            yaxis: { labels: { maxWidth: 125 } },
            grid: { ...sharedOptions.grid, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            noData: { text: 'Analiz edilecek açıklama bulunamadı' }
        }).render();

        dashboardChartsInitialized = true;
    }

    if (document.querySelector('#tabDashboard.show.active')) {
        initializeDashboardCharts();
    }

    // Tab geçişlerinde DataTables kolonlarını anlık ve tam düzeltme
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const targetHref = e.target.getAttribute('href');
        const isDashboard = targetHref === '#tabDashboard';
        
        if (isDashboard) {
            initializeDashboardCharts();
        } else {
            setTimeout(function() {
                $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
            }, 60);
        }

        // URL parametresini güncelle
        var tabName = '';
        if (targetHref === '#tabAvans') tabName = 'avans';
        else if (targetHref === '#tabIzin') tabName = 'izin';
        else if (targetHref === '#tabTalepler') tabName = 'talepler';
        else if (targetHref === '#tabDashboard') tabName = 'dashboard';

        if (tabName) {
            $('#btnShowPending').attr('href', 'index.php?p=talepler/list&tab=' + tabName);
            $('#btnShowApproved').attr('href', 'index.php?p=talepler/list&show=approved&tab=' + tabName);
            $('#btnShowDeleted').attr('href', 'index.php?p=talepler/list&show=deleted&tab=' + tabName);

            var newUrl = new URL(window.location.href);
            newUrl.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', newUrl);
        }
    });

    // Sayfa ilk yüklendiğinde de tabloları anlık hesapla
    setTimeout(function() {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
    }, 150);

    // 3. Excel Dışa Aktar Butonu
    document.getElementById('btnHeaderExportExcel')?.addEventListener('click', function() {
        const activeTabEl = document.querySelector('.talep-nav-pills .nav-link.active');
        if (!activeTabEl) return;
        const activeTabId = activeTabEl.getAttribute('href').replace('#', '');
        let tableId = '';

        if (activeTabId === 'tabAvans') tableId = 'avansTable';
        else if (activeTabId === 'tabIzin') tableId = 'izinTable';
        else if (activeTabId === 'tabTalepler') tableId = 'taleplerTable';

        if (tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
            const table = $('#' + tableId).DataTable();
            table.button('.buttons-excel').trigger();
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'Bilgi', text: 'Dışa aktarmak için bir talep sekmesi (Avans, İzin veya Talepler) seçmelisiniz.' });
            }
        }
    });

    // 4. Yazdır Butonu
    document.getElementById('btnHeaderPrint')?.addEventListener('click', function() {
        window.print();
    });

    // 5. Avans Modal ve Onay İşlemleri
    document.querySelectorAll('input[name="onay_tipi"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.name === 'onay_tipi' && this.id.startsWith('onay')) {
                const farkliTutarAlani = document.getElementById('farkliTutarAlani');
                const aciklamaField = document.querySelector('#formAvansOnay textarea[name="aciklama"]');
                const tutarVal = document.getElementById('farkli_tutar').value;

                if (this.value === 'farkli') {
                    farkliTutarAlani?.classList.remove('d-none');
                    const ft = document.getElementById('farkli_tutar');
                    if (ft) { ft.required = true; ft.focus(); }
                    if (tutarVal && aciklamaField) {
                        aciklamaField.value = "Avans talebiniz " + tutarVal + " TL olarak uygun görülmüştür.";
                    }
                } else {
                    farkliTutarAlani?.classList.add('d-none');
                    const ft = document.getElementById('farkli_tutar');
                    if (ft) { ft.required = false; }
                    if (aciklamaField) aciklamaField.value = "";
                }
            }
        });
    });

    document.getElementById('farkli_tutar')?.addEventListener('input', function() {
        const aciklamaField = document.querySelector('#formAvansOnay textarea[name="aciklama"]');
        if (this.value && aciklamaField) {
            aciklamaField.value = "Avans talebiniz " + this.value + " TL olarak uygun görülmüştür.";
        }
    });

    document.querySelectorAll('.btn-avans-onayla').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const personel = this.dataset.personel;
            const pureTutar = parseFloat(this.dataset.tutar);
            const tutarStr = pureTutar.toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';

            document.getElementById('avans_onay_id').value = id;
            document.getElementById('avans_onay_personel').textContent = personel;
            document.getElementById('avans_onay_tutar').textContent = tutarStr;
            
            const form = document.getElementById('formAvansOnay');
            form?.reset();
            document.getElementById('onayAyni').checked = true;
            document.getElementById('farkliTutarAlani').classList.add('d-none');
            document.getElementById('farkli_tutar').value = pureTutar.toLocaleString('tr-TR', { minimumFractionDigits: 2 });
            document.getElementById('farkli_tutar').required = false;

            new bootstrap.Modal(document.getElementById('modalAvansOnay')).show();
        });
    });

    document.querySelectorAll('.btn-avans-reddet').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('avans_red_id').value = this.dataset.id;
            document.getElementById('avans_red_personel').textContent = this.dataset.personel;
            new bootstrap.Modal(document.getElementById('modalAvansRed')).show();
        });
    });

    document.querySelectorAll('.btn-avans-detay').forEach(btn => {
        btn.addEventListener('click', function () {
            loadDetay('avans', this.dataset.id);
        });
    });

    // 6. İzin Modal ve Onay İşlemleri
    document.querySelectorAll('.btn-izin-onayla').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const personel = this.dataset.personel;
            const tur = this.dataset.tur;
            const gun = this.dataset.gun;
            const start = this.dataset.baslangic;
            const end = this.dataset.bitis;

            document.getElementById('izin_onay_id').value = id;
            document.getElementById('izin_onay_personel').textContent = personel;
            document.getElementById('izin_onay_tur').textContent = tur;
            document.getElementById('izin_onay_gun').textContent = gun;
            
            const form = document.getElementById('formIzinOnay');
            form?.reset();
            document.getElementById('izinOnayAyni').checked = true;
            document.getElementById('farkliIzinTarihAlani').classList.add('d-none');
            document.getElementById('farkli_baslangic').value = start || '';
            document.getElementById('farkli_bitis').value = end || '';
            
            new bootstrap.Modal(document.getElementById('modalIzinOnay')).show();
        });
    });

    document.querySelectorAll('input[name="onay_tipi"]').forEach(radio => {
        if (radio.id.startsWith('izinOnay')) {
            radio.addEventListener('change', function() {
                const alan = document.getElementById('farkliIzinTarihAlani');
                const aciklamaField = document.getElementById('izin_onay_aciklama');
                if (this.value === 'farkli') {
                    alan?.classList.remove('d-none');
                    updateIzinOtoMesaj();
                } else {
                    alan?.classList.add('d-none');
                    if (aciklamaField) aciklamaField.value = "";
                }
            });
        }
    });

    function updateIzinOtoMesaj() {
        const startStr = document.getElementById('farkli_baslangic').value;
        const endStr = document.getElementById('farkli_bitis').value;
        const aciklamaField = document.getElementById('izin_onay_aciklama');
        const gunMetni = document.getElementById('farkli_izin_gun_metni');

        if (startStr && endStr) {
            const start = new Date(startStr);
            const end = new Date(endStr);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (gunMetni) gunMetni.textContent = diffDays;
            const startFmt = startStr.split('-').reverse().join('.');
            const endFmt = endStr.split('-').reverse().join('.');
            
            if (aciklamaField) {
                aciklamaField.value = "İzin talebiniz " + startFmt + " - " + endFmt + " tarihleri arasında " + diffDays + " gün olarak uygun görülmüştür.";
            }
        }
    }

    document.getElementById('farkli_baslangic')?.addEventListener('change', updateIzinOtoMesaj);
    document.getElementById('farkli_bitis')?.addEventListener('change', updateIzinOtoMesaj);

    document.querySelectorAll('.btn-izin-reddet').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('izin_red_id').value = this.dataset.id;
            document.getElementById('izin_red_personel').textContent = this.dataset.personel;
            new bootstrap.Modal(document.getElementById('modalIzinRed')).show();
        });
    });

    document.querySelectorAll('.btn-izin-detay').forEach(btn => {
        btn.addEventListener('click', function () {
            loadDetay('izin', this.dataset.id);
        });
    });

    // İzin Talep Formu Önizleme / Yazdır / Word İndir Mantığı
    let activeIzinFormId = null;
    const modalIzinFormuEl = document.getElementById('modalIzinFormu');
    const modalIzinFormu = modalIzinFormuEl ? new bootstrap.Modal(modalIzinFormuEl) : null;
    const izinFormuIcerik = document.getElementById('izinFormuIcerik');

    function openIzinFormuModal(izinId) {
        if (!izinId) return;
        activeIzinFormId = izinId;
        
        if (izinFormuIcerik) {
            izinFormuIcerik.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <div class="text-muted mt-2 font-size-12">İzin talep formu hazırlanıyor...</div>
                </div>
            `;
        }
        
        if (modalIzinFormu) {
            modalIzinFormu.show();
        }

        const formData = new FormData();
        formData.append('action', 'izin-formu-onizle');
        formData.append('id', izinId);

        fetch(API_URL, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(resp => {
            if (resp.status === 'success' && resp.html) {
                if (izinFormuIcerik) {
                    izinFormuIcerik.innerHTML = resp.html;
                }
            } else {
                if (izinFormuIcerik) {
                    izinFormuIcerik.innerHTML = `
                        <div class="alert alert-danger rounded-3 p-3 text-center my-4">
                            <i class="bx bx-error-circle fs-3 d-block mb-1"></i>
                            <strong>Hata:</strong> ${resp.message || 'İzin formu verisi alınamadı.'}
                        </div>
                    `;
                }
            }
        })
        .catch(err => {
            if (izinFormuIcerik) {
                izinFormuIcerik.innerHTML = `
                    <div class="alert alert-danger rounded-3 p-3 text-center my-4">
                        <i class="bx bx-error-circle fs-3 d-block mb-1"></i>
                        <strong>Bağlantı Hatası:</strong> ${err.message}
                    </div>
                `;
            }
        });
    }

    function printIzinFormu() {
        const printArea = document.getElementById('izinFormPrintArea');
        if (!printArea) {
            window.print();
            return;
        }

        let printFrame = document.getElementById('izinPrintIframe');
        if (!printFrame) {
            printFrame = document.createElement('iframe');
            printFrame.id = 'izinPrintIframe';
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);
        }

        const frameDoc = printFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(`
            <!DOCTYPE html>
            <html lang="tr">
            <head>
                <meta charset="utf-8">
                <title>İzin Talep Formu</title>
                <style>
                    * { box-sizing: border-box !important; }
                    @page { size: A4 portrait; margin: 15mm 15mm 15mm 15mm; }
                    html, body {
                        width: 100%;
                        margin: 0;
                        padding: 0;
                        background: #ffffff;
                        color: #000000;
                        font-family: "Segoe UI", Arial, sans-serif;
                    }
                    .izin-form-print-wrapper {
                        width: 100% !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                    }
                    .izin-form-container {
                        width: 100% !important;
                        max-width: 100% !important;
                        margin: 0 auto !important;
                        padding: 0 !important;
                        box-shadow: none !important;
                        border: none !important;
                    }
                    .izin-form-title {
                        text-align: center;
                        font-size: 18px;
                        font-weight: 700;
                        letter-spacing: 0.5px;
                        margin-bottom: 16px;
                        color: #000000;
                        text-transform: uppercase;
                    }
                    .izin-table-section {
                        width: 100% !important;
                        border-collapse: collapse;
                        margin-bottom: 12px;
                    }
                    .izin-table-section td {
                        border: 1px solid #222222;
                        padding: 5px 8px;
                        vertical-align: middle;
                        font-size: 12px;
                    }
                    .izin-tbl-hdr {
                        font-weight: 700;
                        background-color: #f0f0f0;
                        text-transform: uppercase;
                        font-size: 12px;
                        padding: 6px 8px !important;
                        border: 1px solid #222222;
                    }
                    .izin-lbl-col {
                        width: 28%;
                        font-weight: 700;
                        color: #111111;
                        background-color: #ffffff;
                    }
                    .izin-sep-col {
                        width: 20px;
                        min-width: 20px;
                        max-width: 20px;
                        text-align: center;
                        font-weight: 700;
                        color: #111111;
                        padding: 5px 0 !important;
                    }
                    .izin-val-col {
                        width: auto;
                        color: #111111;
                    }
                    .izin-signatures-table {
                        width: 100% !important;
                        border-collapse: collapse;
                        margin-top: 14px;
                        table-layout: fixed;
                    }
                    .izin-signatures-table td {
                        border: 1px solid #222222;
                        padding: 8px 6px;
                        vertical-align: top;
                        font-size: 11px;
                        text-align: center;
                        width: 33.33%;
                    }
                    .sig-header {
                        font-weight: 700;
                        font-size: 11.5px;
                        text-decoration: underline;
                        margin-bottom: 6px;
                    }
                    .sig-name {
                        font-weight: 600;
                        margin-bottom: 16px;
                        min-height: 16px;
                    }
                    .sig-imza {
                        font-style: italic;
                        font-weight: 700;
                        margin-bottom: 20px;
                    }
                    .sig-date {
                        font-size: 10.5px;
                        color: #333333;
                        text-align: left;
                        padding-left: 2px;
                    }
                </style>
            </head>
            <body>
                ${printArea.outerHTML}
            </body>
            </html>
        `);
        frameDoc.close();

        setTimeout(() => {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }, 300);
    }

    function downloadIzinDocx() {
        if (!activeIzinFormId) {
            alert('Lütfen bir izin kaydı seçiniz.');
            return;
        }
        window.location.href = API_URL + '?action=izin-formu-docx-indir&id=' + encodeURIComponent(activeIzinFormId);
    }

    // Buton Tetikleyicileri (Event Delegation ile hem dinamik hem statik satırlar için)
    document.addEventListener('click', function (e) {
        const btnForm = e.target.closest('.btn-izin-formu');
        if (btnForm) {
            e.preventDefault();
            const izinId = btnForm.dataset.id;
            openIzinFormuModal(izinId);
        }
    });

    document.getElementById('btnModalIzinYazdir')?.addEventListener('click', printIzinFormu);
    document.getElementById('btnModalIzinYazdirHeader')?.addEventListener('click', printIzinFormu);
    document.getElementById('btnModalIzinDocx')?.addEventListener('click', downloadIzinDocx);
    document.getElementById('btnModalIzinDocxHeader')?.addEventListener('click', downloadIzinDocx);

    // 7. Genel Talep İşlemleri
    document.querySelectorAll('.btn-talep-isleme').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'İşleme Al',
                    text: 'Bu talebi işleme almak istediğinize emin misiniz?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Evet, İşleme Al',
                    cancelButtonText: 'İptal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        submitAction('talep-isleme-al', { id: id });
                    }
                });
            } else if (confirm('Bu talebi işleme almak istediğinize emin misiniz?')) {
                submitAction('talep-isleme-al', { id: id });
            }
        });
    });

    document.querySelectorAll('.btn-talep-cozuldu').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('talep_cozuldu_id').value = this.dataset.id;
            document.getElementById('talep_cozuldu_baslik').textContent = this.dataset.baslik;
            new bootstrap.Modal(document.getElementById('modalTalepCozuldu')).show();
        });
    });

    document.querySelectorAll('.btn-talep-detay').forEach(btn => {
        btn.addEventListener('click', function () {
            loadDetay('talep', this.dataset.id);
        });
    });

    // 8. Form Submit Olayları
    document.getElementById('formAvansOnay')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'avans-onayla');
        submitFormAction(formData, 'modalAvansOnay');
    });

    document.getElementById('formAvansRed')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'avans-reddet');
        submitFormAction(formData, 'modalAvansRed');
    });

    document.getElementById('formIzinOnay')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'izin-onayla');
        submitFormAction(formData, 'modalIzinOnay');
    });

    document.getElementById('formIzinRed')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'izin-reddet');
        submitFormAction(formData, 'modalIzinRed');
    });

    document.getElementById('formTalepCozuldu')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'talep-cozuldu');
        submitFormAction(formData, 'modalTalepCozuldu');
    });

    // 9. Silme Modal
    document.querySelectorAll('.btn-sil').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const tip = this.dataset.tip;
            document.getElementById('sil_id').value = id;
            document.getElementById('sil_action').value = tip + '-sil';
            document.getElementById('formSil').reset();
            new bootstrap.Modal(document.getElementById('modalSil')).show();
        });
    });

    document.getElementById('formSil')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        submitFormAction(formData, 'modalSil');
    });

    // 10. Ajax Helper Metotları
    function submitAction(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        for (const key in data) {
            formData.append(key, data[key]);
        }

        fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: data.message,
                            timer: 1400,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                    } else {
                        alert('Hata: ' + data.message);
                    }
                }
            })
            .catch(error => {
                alert('Bir hata oluştu: ' + error.message);
            });
    }

    function submitFormAction(formData, modalId) {
        fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById(modalId))?.hide();
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: data.message,
                            timer: 1400,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        alert(data.message);
                        location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Hata', text: data.message });
                    } else {
                        alert('Hata: ' + data.message);
                    }
                }
            })
            .catch(error => {
                alert('Bir hata oluştu: ' + error.message);
            });
    }

    function loadDetay(tip, id) {
        const detayContent = document.getElementById('detayContent');
        if (detayContent) {
            detayContent.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div><div class="mt-2 text-muted font-size-12">Detay bilgileri yükleniyor...</div></div>';
        }
        new bootstrap.Modal(document.getElementById('modalDetay')).show();

        const formData = new FormData();
        formData.append('action', 'get-' + tip + '-detay');
        formData.append('id', id);

        fetch(API_URL, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    if (detayContent) detayContent.innerHTML = renderDetay(tip, data.data);
                } else {
                    if (detayContent) detayContent.innerHTML = '<div class="alert alert-danger mb-0">' + data.message + '</div>';
                }
            })
            .catch(error => {
                if (detayContent) detayContent.innerHTML = '<div class="alert alert-danger mb-0">Bir hata oluştu: ' + error.message + '</div>';
            });
    }

    // Modern Kullanıcı Dostu Detay Modal Render Fonksiyonu
    function renderDetay(tip, data) {
        let typeBadge = '';
        let typeIcon = '';
        if (tip === 'avans') {
            typeBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-12 px-2.5 py-1 fw-bold"><i class="bx bx-money me-1"></i>Avans Talebi</span>';
            typeIcon = 'bx-money';
        } else if (tip === 'izin') {
            typeBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-12 px-2.5 py-1 fw-bold"><i class="bx bx-calendar-check me-1"></i>İzin Talebi</span>';
            typeIcon = 'bx-calendar-check';
        } else {
            typeBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-size-12 px-2.5 py-1 fw-bold"><i class="bx bx-message-square-detail me-1"></i>Genel Talep</span>';
            typeIcon = 'bx-message-square-detail';
        }

        let durumStr = data.durum || data.onay_durumu || 'beklemede';
        let durumClass = 'warning';
        let durumText = ucfirst(durumStr);
        let durumIcon = 'time-five';
        if (durumStr == 'onaylandi' || durumStr == 'Onaylandı' || durumStr == 'cozuldu') {
            durumClass = 'success';
            durumText = durumStr == 'cozuldu' ? 'Çözüldü' : 'Onaylandı';
            durumIcon = 'check-circle';
        } else if (durumStr == 'reddedildi' || durumStr == 'Reddedildi') {
            durumClass = 'danger';
            durumText = 'Reddedildi';
            durumIcon = 'x-circle';
        } else if (durumStr == 'islemde') {
            durumClass = 'info';
            durumText = 'İşlemde';
            durumIcon = 'play-circle';
        }

        let html = '';

        // 1. Hero Personel Kartı
        html += '<div class="detay-hero-card d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">';
        html += '  <div class="d-flex align-items-center gap-3">';
        html += '    <img src="' + (data.resim_yolu || 'assets/images/users/user-dummy-img.jpg') + '" class="rounded-circle border border-2 border-white shadow-sm" style="width:54px;height:54px;object-fit:cover;" onerror="this.src=\'assets/images/users/user-dummy-img.jpg\'">';
        html += '    <div>';
        html += '      <h5 class="mb-0 fw-bold text-dark font-size-15">' + (data.requester_name || 'Bilinmeyen Personel') + '</h5>';
        html += '      <p class="text-muted mb-0 font-size-12">' + (data.departman || 'Departman Belirtilmemiş') + (data.gorev ? ' • ' + data.gorev : '') + '</p>';
        html += '    </div>';
        html += '  </div>';
        html += '  <div class="d-flex align-items-center gap-2">';
        html += '    ' + typeBadge;
        html += '    <span class="badge bg-' + durumClass + '-subtle text-' + durumClass + ' border border-' + durumClass + '-subtle rounded-pill font-size-12 px-2.5 py-1 fw-bold"><i class="bx bx-' + durumIcon + ' me-1"></i>' + durumText + '</span>';
        html += '  </div>';
        html += '</div>';

        // 2. 4 Adet Minimal KPI Kutusu
        html += '<div class="row g-2 mb-3">';
        if (tip === 'avans') {
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Talep Tutarı</span><span class="detay-kpi-val text-success font-size-16">' + formatMoney(data.tutar) + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Talep Tarihi</span><span class="detay-kpi-val font-size-13">' + formatDate(data.talep_tarihi) + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Bordroya İşleme</span><span class="detay-kpi-val text-dark font-size-13"><i class="bx bx-check-double text-success me-1"></i>Otomatik</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Durum</span><span class="detay-kpi-val text-' + durumClass + ' font-size-13">' + durumText + '</span></div></div>';
        } else if (tip === 'izin') {
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">İzin Türü</span><span class="detay-kpi-val text-primary font-size-13">' + ucfirst(data.izin_tipi || '-') + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Süre</span><span class="detay-kpi-val text-dark font-size-15">' + (data.gun_sayisi ? data.gun_sayisi + ' Gün' : '-') + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Tarih Aralığı</span><span class="detay-kpi-val font-size-12">' + formatDateOnly(data.baslangic_tarihi) + ' - ' + formatDateOnly(data.bitis_tarihi) + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Talep Tarihi</span><span class="detay-kpi-val font-size-12">' + formatDate(data.talep_tarihi) + '</span></div></div>';
        } else if (tip === 'talep') {
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Öncelik</span><span class="detay-kpi-val text-' + (data.oncalik == 'yuksek' ? 'danger' : (data.oncelik == 'orta' ? 'warning' : 'success')) + ' font-size-13">' + ucfirst(data.oncelik || 'Normal') + '</span></div></div>';
            html += '<div class="col-sm-3 col-6"><div class="detay-kpi-box"><span class="detay-kpi-label">Kayıt Tarihi</span><span class="detay-kpi-val font-size-12">' + formatDate(data.olusturma_tarihi) + '</span></div></div>';
            html += '<div class="col-sm-6 col-12"><div class="detay-kpi-box"><span class="detay-kpi-label">Talep Başlığı</span><span class="detay-kpi-val text-dark font-size-13 text-truncate">' + (data.baslik || '-') + '</span></div></div>';
        }
        html += '</div>';

        // 3. Talep Açıklaması Kutusu
        html += '<div class="detay-note-box mb-3">';
        html += '  <div class="d-flex align-items-center gap-2 mb-1.5">';
        html += '    <i class="bx bx-message-square-dots text-primary font-size-16"></i>';
        html += '    <span class="fw-bold text-dark font-size-12">Personel Açıklaması</span>';
        html += '  </div>';
        html += '  <p class="mb-0 text-secondary font-size-13" style="line-height:1.5;">' + (data.aciklama ? nl2br(data.aciklama) : '<em class="text-muted">Açıklama girilmemiş.</em>') + '</p>';
        html += '</div>';

        // 4. İşlem & Onay Bilgileri
        if (data.solver_name || data.onay_aciklama || data.cozum_aciklama || data.islem_tarihi) {
            let calloutTheme = durumClass === 'success' ? 'success' : (durumClass === 'danger' ? 'danger' : 'info');
            html += '<div class="p-3 bg-' + calloutTheme + '-subtle border border-' + calloutTheme + '-subtle rounded-3 mb-3">';
            html += '  <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">';
            html += '    <div class="d-flex align-items-center gap-1.5">';
            html += '      <i class="bx bx-user-check text-' + calloutTheme + ' font-size-17"></i>';
            html += '      <span class="fw-bold text-' + calloutTheme + ' font-size-12">İşlem Yapan: ' + (data.solver_name || '-') + '</span>';
            html += '    </div>';
            if (data.islem_tarihi || data.onay_tarihi || data.cozum_tarihi) {
                html += '    <small class="text-muted font-size-11"><i class="bx bx-time me-1"></i>' + formatDate(data.islem_tarihi || data.onay_tarihi || data.cozum_tarihi) + '</small>';
            }
            html += '  </div>';
            let sonucMetni = data.onay_aciklama || data.cozum_aciklama || '';
            if (sonucMetni) {
                html += '  <div class="bg-white p-2.5 rounded-2 border border-' + calloutTheme + '-subtle text-dark font-size-12.5 fw-medium">' + nl2br(sonucMetni) + '</div>';
            }
            html += '</div>';
        }

        // 5. Ek Fotoğraf / Dosya
        if (data.foto) {
            html += '<div class="detay-note-box mb-2">';
            html += '  <div class="d-flex align-items-center justify-content-between mb-2">';
            html += '    <span class="fw-bold text-dark font-size-12"><i class="bx bx-image me-1 text-primary"></i>Ek Görsel / Belge</span>';
            html += '    <a href="' + data.foto + '" target="_blank" class="btn btn-sm btn-subtle-primary py-0 px-2 font-size-11 rounded-pill"><i class="bx bx-expand-alt me-1"></i>Büyük Gör</a>';
            html += '  </div>';
            html += '  <div class="text-center">';
            html += '    <img src="' + data.foto + '" class="img-fluid rounded-3 shadow-xs border" style="max-height:220px;cursor:pointer;" onclick="window.open(\'' + data.foto + '\', \'_blank\')" onerror="this.parentElement.style.display=\'none\'">';
            html += '  </div>';
            html += '</div>';
        }

        return html;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('tr-TR') + ' ' + date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDateOnly(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('tr-TR');
    }

    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2 }) + ' ₺';
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function nl2br(str) {
        if (!str) return '';
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
    }

    // 11. URL ID Odaklanma
    var urlParams = new URLSearchParams(window.location.search);
    var idParam = urlParams.get('id');
    if (idParam) {
        setTimeout(function () {
            var targetRow = document.querySelector('tr[data-id="' + idParam + '"]');
            if (targetRow) {
                targetRow.style.backgroundColor = 'rgba(37, 99, 235, 0.15)';
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 600);
    }
});
</script>
