<?php
/**
 * Personel Takip - Yönetici Paneli
 * Saha personellerinin konum bazlı giriş-çıkış takibi
 */

use App\Helper\Helper;
use App\Helper\Form;
use App\Service\Gate;
use App\Helper\Date;

// Yetki kontrolü
// if (Gate::canWithMessage("personel_takip")) {

$maintitle = "Personel Takip";
$title = "Saha Personel Takibi";

$db = (new \App\Core\Db())->db;
$stmt = $db->prepare("SELECT DISTINCT departman FROM personel WHERE silinme_tarihi IS NULL AND departman IS NOT NULL AND departman != '' AND (saha_takibi = 1 OR disardan_sigortali = 0) ORDER BY departman ASC");
$stmt->execute();
$departmanlar = $stmt->fetchAll(PDO::FETCH_COLUMN);

$departmanOptions = ['' => 'Tüm Departmanlar'];
foreach ($departmanlar as $dept) {
    $departmanOptions[$dept] = $dept;
}

$stmtPList = $db->prepare("SELECT id, adi_soyadi, departman FROM personel WHERE silinme_tarihi IS NULL AND aktif_mi = 1 AND (saha_takibi = 1 OR disardan_sigortali = 0 OR FIND_IN_SET('takip', gorunum_modulleri)) ORDER BY adi_soyadi ASC");
$stmtPList->execute();
$dashboardPersoneller = $stmtPList->fetchAll(PDO::FETCH_ASSOC);

$dashboardPersonelOptions = ['all' => 'Tüm Personeller'];
foreach ($dashboardPersoneller as $dp) {
    $dashboardPersonelOptions[$dp['id']] = $dp['adi_soyadi'] . ($dp['departman'] ? ' (' . $dp['departman'] . ')' : '');
}
?>

<!-- Leaflet CSS & ApexCharts CSS -->
<link rel="stylesheet" href="assets/libs/leaflet/leaflet.css" />
<link rel="stylesheet" href="assets/libs/apexcharts/apexcharts.css" />

<div class="container-fluid">
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- Özet Kartları Sarmalayıcı -->
    <script>
        (function() {
            try {
                if (localStorage.getItem('personel_takip_summary_collapsed') === 'true') {
                    document.write('<style>#personelTakipOzetAlani { display: none !important; }</style>');
                }
            } catch(e) {}
        })();
    </script>
    <div id="personelTakipOzetAlani" class="takip-summary-wrapper">
        <div class="row g-3 mb-4">
            <div class="col-xl col-md-6">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                    style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body p-3">
                        <div class="icon-label-container">
                            <div class="icon-box" style="background: rgba(52, 195, 143, 0.1);">
                                <i class="bx bx-run fs-4 text-success"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SAHA</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">ŞU AN GÖREVDE
                        </p>
                        <h4 class="mb-0 fw-bold bordro-text-heading">
                            <span class="counter-value" id="stat-gorevde">0</span>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                    style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body p-3">
                        <div class="icon-label-container">
                            <div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
                                <i class="bx bx-check-circle fs-4 text-primary"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BAŞARI</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">GÖREVİ
                            TAMAMLADI</p>
                        <h4 class="mb-0 fw-bold bordro-text-heading">
                            <span class="counter-value" id="stat-tamamladi">0</span>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                    style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body p-3">
                        <div class="icon-label-container">
                            <div class="icon-box" style="background: rgba(241, 180, 76, 0.1);">
                                <i class="bx bx-time fs-4 text-warning"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">BEKLEYEN</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">HENÜZ
                            BAŞLAMADI</p>
                        <h4 class="mb-0 fw-bold bordro-text-heading">
                            <span class="counter-value" id="stat-baslamadi">0</span>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                    style="--card-color: #50a5f1; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body p-3">
                        <div class="icon-label-container">
                            <div class="icon-box" style="background: rgba(80, 165, 241, 0.1);">
                                <i class="bx bx-calendar-minus fs-4 text-info"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İSTATİSTİK</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BUGÜN İZİNLİ
                        </p>
                        <h4 class="mb-0 fw-bold bordro-text-heading">
                            <span class="counter-value" id="stat-izinli">0</span>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl col-md-6">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                    style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body p-3">
                        <div class="icon-label-container">
                            <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                                <i class="bx bx-alarm-exclamation fs-4 text-danger"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GECİKME</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">GEÇ KALANLAR
                        </p>
                        <h4 class="mb-0 fw-bold bordro-text-heading">
                            <span class="counter-value text-danger" id="stat-gec-kalan">0</span>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tabListe" role="tab">
                                <i class="bx bx-list-ul me-1"></i> Personel Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabHarita" role="tab"
                                onclick="setTimeout(initHarita, 200)">
                                <i class="bx bx-map me-1"></i> Harita Görünümü
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabRapor" role="tab"
                                onclick="loadCalismaRaporu()">
                                <i class="bx bx-bar-chart-alt-2 me-1"></i> Çalışma Süreleri
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabGecKalanlar" role="tab"
                                onclick="loadGecKalanlar()">
                                <i class="bx bx-alarm-exclamation me-1"></i> Geç Kalanlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tabDashboard" role="tab"
                                onclick="initDashboardTab()">
                                <i class="bx bx-pie-chart-alt-2 me-1"></i> Analiz & Dashboard
                            </a>
                        </li>
                    </ul>
                    <div class="ms-auto d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-none" 
                                id="btnToggleTakipOzet" 
                                onclick="toggleTakipSummary()" 
                                title="Özet kartlarını gizle / göster">
                            <i class="bx bx-chevron-up fs-5" id="iconToggleTakipOzet"></i>
                            <span class="small d-none d-md-inline" id="txtToggleTakipOzet">Özeti Gizle</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- PERSONEL LİSTESİ TAB -->
                        <div class="tab-pane fade show active" id="tabListe" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Bugünkü Personel Durumları</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width: 250px;">
                                        <?= Form::FormSelect2("mainDepartmanFilter", $departmanOptions, "", "Departman", "bx bx-buildings", "key", "", "form-select select2 form-select-sm", false, "width:100%", 'onchange="yenile()"') ?>
                                    </div>
                                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1" style="height: 56px;">
                                        <button type="button"
                                            class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                            onclick="yenile()">
                                            <i class="mdi mdi-refresh fs-5 me-1"></i> Yenile
                                        </button>
                                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center"
                                            id="exportExcel">
                                            <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel
                                        </button>
                                        <?php if (\App\Service\Gate::allows("personel_takip_deparmana_gore_ise_baslama_belirleme")): ?>
                                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-link btn-sm text-dark text-decoration-none px-2 d-flex align-items-center"
                                            onclick="openMesaiSettings()">
                                            <i class="bx bx-cog fs-5 me-1"></i> Ayarlar
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="personelTakipTable" class="table table-bordered table-hover nowrap w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">Foto</th>
                                            <th>Personel Adı</th>
                                            <th>Departman</th>
                                            <th style="width: 120px;">Durum</th>
                                            <th style="width: 100px;">Başlama</th>
                                            <th style="width: 100px;">Bitiş</th>
                                            <th style="width: 100px;">Konum</th>
                                            <th style="width: 100px;">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="personelTakipBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HARİTA TAB -->
                        <div class="tab-pane fade" id="tabHarita" role="tabpanel">
                            <div id="mapFullWrapper" class="d-flex flex-column h-100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <h5 class="mb-0">Personel Konum Haritası</h5>
                                        <div class="d-flex gap-2" id="mapLegendFilters">
                                            <div class="badge-filter" style="cursor: pointer;" onclick="toggleMapStatusFilter('aktif', this)">
                                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bx bxs-circle me-1"></i> Görevde</span>
                                            </div>
                                            <div class="badge-filter" style="cursor: pointer;" onclick="toggleMapStatusFilter('bitti', this)">
                                                <span class="badge bg-dark-subtle text-dark border border-dark px-2 py-1"><i class="bx bxs-circle me-1"></i> Tamamladı</span>
                                            </div>
                                            <div class="badge-filter" style="cursor: pointer;" onclick="toggleMapStatusFilter('baslamadi', this)">
                                                <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="bx bxs-circle me-1"></i> Başlamadı</span>
                                            </div>
                                            <div class="badge-filter" style="cursor: pointer;" onclick="toggleMapStatusFilter('izinli', this)">
                                                <span class="badge bg-info-subtle text-info border border-info px-2 py-1"><i class="bx bxs-circle me-1"></i> İzinli</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 200px;">
                                            <?= Form::FormFloatInput("text", "mapSearchInput", "", "Personel ara...", "Personel Ara", "bx bx-search", "form-control form-control-sm", false, null, "on", false, 'onkeyup="filterMapMarkers()"') ?>
                                        </div>
                                        <div style="width: 200px;">
                                            <?= Form::FormSelect2("mapDepartmanFilter", $departmanOptions, "", "Departman", "bx bx-buildings", "key", "", "form-select select2 form-select-sm", false, "width:100%", 'onchange="loadHaritaVerileri()"') ?>
                                        </div>
                                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1" style="height: 56px;">
                                            <div class="btn-group btn-group-sm h-100" role="group">
                                                <input type="radio" class="btn-check" name="haritaModu" id="modGorev" checked onchange="loadHaritaVerileri()">
                                                <label class="btn btn-outline-primary border-0 rounded d-flex align-items-center px-3" for="modGorev">
                                                    <i class="bx bx-briefcase me-1"></i> Görev
                                                </label>

                                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                                                <input type="radio" class="btn-check" name="haritaModu" id="modAnlik" onchange="loadHaritaVerileri(); autoIstekTumKonum()">
                                                <label class="btn btn-outline-danger border-0 rounded d-flex align-items-center px-3" for="modAnlik">
                                                    <i class="bx bx-target-lock me-1"></i> Anlık
                                                </label>
                                            </div>
                                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                            <button type="button" class="btn btn-soft-dark border-0 rounded d-flex align-items-center px-3 h-100" 
                                                    onclick="toggleMapFullscreen()" title="Tam Ekran">
                                                <i class="bx bx-fullscreen fs-4"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="personelHarita" style="height: 500px; border-radius: 8px;"></div>
                            </div>
                        </div>

                        <!-- ÇALIŞMA SÜRELERİ TAB -->
                        <div class="tab-pane fade" id="tabRapor" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Haftalık Çalışma Süreleri</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <div style="width: 160px;">
                                        <?= Form::FormFloatInput("text", "raporBaslangic", Date::dmY('-7 days'), "", "Başlangıç", "calendar", 'form-control flatpickr') ?>
                                    </div>
                                    <div style="width: 160px;">
                                        <?= Form::FormFloatInput("text", "raporBitis", Date::today(), "", "Bitiş", "calendar", 'form-control flatpickr') ?>
                                    </div>
                                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1"
                                        style="height: 56px; width: 160px;">
                                        <button type="button"
                                            class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center w-50 h-100"
                                            onclick="raporExcelIndir()" title="Excel İndir">
                                            <i class="bx bxs-file-export fs-4"></i>
                                        </button>
                                        <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                        <button type="button"
                                            class="btn btn-primary btn-sm px-2 fw-bold shadow-primary w-50 h-100"
                                            onclick="loadCalismaRaporu()" title="Filtrele">
                                            <i class="bx bx-filter-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped dt-responsive nowrap"
                                    id="calismaRaporuTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th class="text-center">Toplam Gün</th>
                                            <th class="text-center">Toplam Saat</th>
                                            <th class="text-center">Ort. Başlama</th>
                                            <th class="text-center">Ort. Bitiş</th>
                                            <th class="text-center">Geç Kalma</th>
                                        </tr>
                                    </thead>
                                    <tbody id="calismaRaporuBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- GEÇ KALANLAR TAB -->
                        <div class="tab-pane fade" id="tabGecKalanlar" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0" id="gecKalanlarBaslik">
                                    <i class="bx bx-alarm-exclamation text-danger me-1"></i>
                                    Geç Kalan Personeller
                                </h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <div style="width: 160px;">
                                        <?= Form::FormFloatInput("text", "gecKalmaTarih", Date::today(), "", "Tarih", "calendar", 'form-control flatpickr', ) ?>
                                    </div>
                                    <?php if (\App\Service\Gate::allows("personel_takip_deparmana_gore_ise_baslama_belirleme")): ?>
                                    <div style="width: 160px;">
                                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1" style="height: 56px;">
                                            <button type="button" class="btn btn-link text-dark text-decoration-none w-100 h-100 d-flex align-items-center justify-content-center" onclick="openMesaiSettings()">
                                                <i class="bx bx-cog fs-5 me-1"></i> Ayarlar
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div style="width: 160px;" class="ms-2">
                                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1"
                                            style="height: 56px;">
                                            <button type="button"
                                                class="btn btn-primary w-100 h-100 fw-bold shadow-primary d-flex align-items-center justify-content-center"
                                                onclick="loadGecKalanlar()">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <i class="bx bx-info-circle me-2 fs-4"></i>
                                <div>
                                    Belirtilen saatten sonra göreve başlayan veya hiç başlamayan personeller
                                    listelenmektedir.
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped dt-responsive nowrap"
                                    id="gecKalanlarTable" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Personel</th>
                                            <th>Departman</th>
                                            <th class="text-center">Limit</th>
                                            <th class="text-center">Başlama Saati</th>
                                            <th class="text-center">Gecikme Süresi</th>
                                            <th class="text-center">Durum</th>
                                            <th>Açıklama / Bilgi</th>
                                            <th class="text-center">İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody id="gecKalanlarBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ANALİZ & DASHBOARD TAB -->
                        <div class="tab-pane fade" id="tabDashboard" role="tabpanel">
                            <!-- Filtre ve Hızlı Tarih Çubuğu -->
                            <div class="card border border-light shadow-sm mb-4 bg-light-subtle">
                                <div class="card-body p-3">
                                    <div class="row g-2 align-items-center mb-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Hızlı Dönem:</span>
                                                <div class="btn-group btn-group-sm" role="group" id="dashQuickDateGroup">
                                                    <button type="button" class="btn btn-outline-primary" onclick="setDashQuickDate('today', this)">Bugün</button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="setDashQuickDate('yesterday', this)">Dün</button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="setDashQuickDate('week', this)">Bu Hafta</button>
                                                    <button type="button" class="btn btn-outline-primary active" onclick="setDashQuickDate('month', this)">Bu Ay</button>
                                                    <button type="button" class="btn btn-outline-primary" onclick="setDashQuickDate('last_month', this)">Geçen Ay</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1" id="dashActiveFilterLabel">
                                                <i class="bx bx-calendar me-1"></i> Bu Ay
                                            </span>
                                        </div>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-xl-2 col-md-3">
                                            <?= Form::FormFloatInput("text", "dashBaslangic", Date::dmY(date('Y-m-01')), "", "Başlangıç Tarihi", "calendar", 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-xl-2 col-md-3">
                                            <?= Form::FormFloatInput("text", "dashBitis", Date::today(), "", "Bitiş Tarihi", "calendar", 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-xl-3 col-md-3">
                                            <?= Form::FormSelect2("dashPersonelFilter", $dashboardPersonelOptions, "all", "Personel Seçimi", "bx bx-user", "key", "", "form-select select2 form-select-sm", false, "width:100%", 'onchange="loadDashboardAnaliz()"') ?>
                                        </div>
                                        <div class="col-xl-2 col-md-3">
                                            <?= Form::FormSelect2("dashDepartmanFilter", $departmanOptions, "", "Departman", "bx bx-buildings", "key", "", "form-select select2 form-select-sm", false, "width:100%", 'onchange="loadDashboardAnaliz()"') ?>
                                        </div>
                                        <div class="col-xl-3 col-md-12">
                                            <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2" style="height: 56px;">
                                                <button type="button" class="btn btn-primary flex-grow-1 h-100 fw-bold d-flex align-items-center justify-content-center" onclick="loadDashboardAnaliz()">
                                                    <i class="bx bx-filter-alt me-1"></i> Analiz Et
                                                </button>
                                                <button type="button" class="btn btn-outline-success h-100 d-flex align-items-center justify-content-center px-3" onclick="exportDashboardExcel()" title="Excel Çıktısı Al">
                                                    <i class="mdi mdi-file-excel fs-5 me-1"></i> Excel
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bireysel Personel Kartı (Sadece tek personel seçildiğinde açılır) -->
                            <div id="dashBireyselKarti" class="card border-primary border-top border-3 shadow-sm mb-4" style="display: none;">
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <img id="dashBireyselFoto" src="assets/images/users/user-dummy-img.jpg" class="rounded-circle avatar-md border p-1" style="object-fit: cover; width: 64px; height: 64px;" alt="Personel">
                                        </div>
                                        <div class="col">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <h5 class="mb-0 fw-bold" id="dashBireyselAd">-</h5>
                                                <span class="badge bg-secondary-subtle text-secondary" id="dashBireyselDept">-</span>
                                            </div>
                                            <div class="d-flex flex-wrap gap-4 text-muted small">
                                                <div><i class="bx bx-calendar-check text-primary me-1"></i> Çalışılan Gün: <b class="text-dark" id="dashBireyselGun">0</b></div>
                                                <div><i class="bx bx-time-five text-success me-1"></i> Toplam Mesai: <b class="text-dark" id="dashBireyselSure">0 sa</b></div>
                                                <div><i class="bx bx-log-in-circle text-info me-1"></i> Ort. Başlama: <b class="text-dark" id="dashBireyselOrtGiris">--:--</b></div>
                                                <div><i class="bx bx-alarm-exclamation text-danger me-1"></i> Toplam Gecikme: <b class="text-danger" id="dashBireyselGecikme">0 kez</b></div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#dashPersonelFilter').val('all').trigger('change');">
                                                <i class="bx bx-x me-1"></i> Genel Görünüme Dön
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI Kartları Satırı -->
                            <div class="row g-3 mb-4">
                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-bold opacity-75">TOPLAM ÇALIŞMA SÜRESİ</span>
                                                <div class="rounded p-2 bg-white bg-opacity-25">
                                                    <i class="bx bx-time-five fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h3 class="mb-1 fw-bold text-white" id="dashKpiToplamSure">0 sa 0 dk</h3>
                                            <div class="small opacity-75 d-flex justify-content-between">
                                                <span>Toplam Kayıt: <b id="dashKpiToplamKayit">0</b></span>
                                                <span>Fiilî Gün: <b id="dashKpiFiiliGun">0</b></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-bold opacity-75">ORTALAMA GİRİŞ & GÜNLÜK</span>
                                                <div class="rounded p-2 bg-white bg-opacity-25">
                                                    <i class="bx bx-log-in-circle fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h3 class="mb-1 fw-bold text-white" id="dashKpiOrtGiris">08:30</h3>
                                            <div class="small opacity-75 d-flex justify-content-between">
                                                <span>Ort. Günlük: <b id="dashKpiOrtGunluk">0 sa</b></span>
                                                <span>Hedef: <b>08:30</b></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-bold opacity-75">ZAMANINDA BAŞLAMA ORANI</span>
                                                <div class="rounded p-2 bg-white bg-opacity-25">
                                                    <i class="bx bx-check-shield fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h3 class="mb-1 fw-bold text-white" id="dashKpiZamanindaOran">%0</h3>
                                            <div class="progress mt-2 bg-white bg-opacity-25" style="height: 5px;">
                                                <div class="progress-bar bg-white" id="dashKpiZamanindaBar" role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white;">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="small fw-bold opacity-75">TOPLAM GECİKME & KAYIP</span>
                                                <div class="rounded p-2 bg-white bg-opacity-25">
                                                    <i class="bx bx-alarm-exclamation fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h3 class="mb-1 fw-bold text-white" id="dashKpiGecikmeSayisi">0 kez</h3>
                                            <div class="small opacity-75 d-flex justify-content-between">
                                                <span>Toplam Gecikme: <b id="dashKpiGecikmeDk">0 dk</b></span>
                                                <span>Gecikme Oranı: <b id="dashKpiGecikmeOran">%0</b></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grafikler Satırı 1 -->
                            <div class="row g-3 mb-4">
                                <div class="col-lg-8">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-line-chart text-primary me-1"></i> Günlük Çalışma Süresi & Personel Katılım Trendi
                                            </h6>
                                            <small class="text-muted">Gün bazlı toplam saat ve kişi sayısı</small>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="chartDashTrend" style="min-height: 320px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-pie-chart-alt text-warning me-1"></i> İşe Başlama Saati Dağılımı
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="chartDashSaat" style="min-height: 320px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Grafikler Satırı 2 -->
                            <div class="row g-3 mb-4">
                                <div class="col-lg-7">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-bar-chart text-info me-1"></i> Departman Bazlı Toplam Mesai (Saat)
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="chartDashDept" style="min-height: 280px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-doughnut-chart text-success me-1"></i> Görev / Kayıt Durum Dağılımı
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div id="chartDashDurum" style="min-height: 280px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Liderlik & Detay Tablosu Satırı -->
                            <div class="row g-3 mb-4">
                                <div class="col-xl-4 col-lg-5">
                                    <!-- En Çok Çalışanlar -->
                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-header bg-transparent border-bottom py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-trophy text-warning me-1"></i> En Çok Mesai Yapanlar (Top 5)
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="list-group list-group-flush" id="dashTopCalisanlarList">
                                                <div class="text-center text-muted p-3">Yükleniyor...</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- En Çok Gecikenler -->
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-transparent border-bottom py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-alarm-exclamation text-danger me-1"></i> En Çok Gecikenler (Top 5)
                                            </h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="list-group list-group-flush" id="dashTopGecikenlerList">
                                                <div class="text-center text-muted p-3">Yükleniyor...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-8 col-lg-7">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-2">
                                            <h6 class="card-title mb-0 fw-bold text-dark">
                                                <i class="bx bx-table text-primary me-1"></i> Personel Performans & Katılım Özeti
                                            </h6>
                                        </div>
                                        <div class="card-body p-3">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-striped align-middle dt-responsive nowrap w-100" id="dashPersonelPerformansTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Personel</th>
                                                            <th>Departman</th>
                                                            <th class="text-center">Çalışılan Gün</th>
                                                            <th class="text-center">Toplam Süre</th>
                                                            <th class="text-center">Ort. Günlük</th>
                                                            <th class="text-center">Gecikme</th>
                                                            <th class="text-center">Zamanında %</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="dashPersonelPerformansBody">
                                                        <tr>
                                                            <td colspan="7" class="text-center text-muted">Yükleniyor...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hareket Geçmişi Modalı -->
<div class="modal fade" id="gecmisModal" tabindex="-1" aria-labelledby="gecmisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="gecmisModalLabel">
                    <i class="bx bx-history me-2"></i>Hareket Geçmişi
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Personel Bilgisi -->
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                    <div class="flex-shrink-0">
                        <img id="gecmisPersonelFoto" src="assets/images/users/user-dummy-img.jpg" class="rounded-circle"
                            width="60" height="60" style="object-fit: cover;">
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-1" id="gecmisPersonelAd">-</h5>
                        <p class="text-muted mb-0" id="gecmisPersonelTarih">Son 7 günlük hareketler</p>
                    </div>
                </div>
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-md-5">
                        <?= Form::FormFloatInput("text", "gecmisBaslangic", Date::dmY('-7 days'), "", "Başlangıç Tarihi", "calendar", 'form-control flatpickr') ?>
                    </div>
                    <div class="col-md-5">
                        <?= Form::FormFloatInput("text", "gecmisBitis", Date::today(), "", "Bitiş Tarihi", "calendar", 'form-control flatpickr') ?>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1"
                            style="height: 56px;">
                            <button type="button" class="btn btn-primary w-100 h-100 fw-bold" onclick="filtreGecmis()">
                                <i class="bx bx-filter-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Hareketler Tablosu -->
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Tarih</th>
                                <th>Saat</th>
                                <th>İşlem</th>
                                <th>Konum</th>
                                <th>Hassasiyet</th>
                            </tr>
                        </thead>
                        <tbody id="gecmisTabloBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Yükleniyor...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
    </div>
</div>
</div>

<!-- Gecikme Açıklama Modalı -->
<div class="modal fade" id="aciklamaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-comment-detail me-2"></i> Bilgim Var / Açıklama Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="aciklamaForm">
                    <input type="hidden" id="aciklamaPersonelId">
                    <input type="hidden" id="aciklamaTarih">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Personel</label>
                        <div id="aciklamaPersonelAd" class="form-control-plaintext bg-light px-2 rounded"></div>
                    </div>
                    <div class="mb-3">
                        <label for="gecikmeAciklamaText" class="form-label fw-bold">Açıklama</label>
                        <textarea class="form-control" id="gecikmeAciklamaText" rows="4" placeholder="Gecikme sebebini yazınız..."></textarea>
                    </div>
                </form>

                <hr>
                <div class="mt-3">
                    <label class="form-label fw-bold text-muted small"><i class="bx bx-history me-1"></i> Son 10 Açıklama Geçmişi</label>
                    <div id="gecikmeHistoryList" class="overflow-auto" style="max-height: 200px;">
                        <div class="text-center p-3 text-muted">Geçmiş yükleniyor...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" onclick="saveAciklama()">
                    <i class="bx bx-save me-1"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Departman Mesai Ayarları Modalı -->
<div class="modal fade" id="mesaiAyarlariModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bx bx-cog me-2"></i> Departman Bazlı Mesai Saatleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bx bx-info-circle me-1"></i> Belirtilmeyen departmanlar için varsayılan olarak <b>08:30</b> kullanılacaktır.
                </div>
                <form id="mesaiAyarlariForm">
                    <div id="mesaiSettingsList" class="p-2" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center p-3 text-muted">Yükleniyor...</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveMesaiSettings()">
                    <i class="bx bx-save me-1"></i> Değişiklikleri Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 40px;
        height: 40px;
    }

    .avatar-xs {
        width: 32px;
        height: 32px;
    }

    #personelTakipTable tbody tr:hover {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
    }

    /* Leaflet popup styles */
    .leaflet-popup-content {
        margin: 10px;
    }

    .marker-popup {
        text-align: center;
        min-width: 150px;
    }

    .marker-popup img {
        border-radius: 50%;
        margin-bottom: 8px;
    }

    .marker-popup h6 {
        margin-bottom: 4px;
    }

    .personel-map-marker {
        display: flex;
        align-items: center;
        width: max-content;
        filter: drop-shadow(0 3px 6px rgba(30, 42, 56, 0.22));
        transition: transform 0.18s ease, filter 0.18s ease;
    }

    .personel-map-marker:hover {
        transform: translateY(-2px);
        filter: drop-shadow(0 5px 9px rgba(30, 42, 56, 0.3));
    }

    .personel-map-marker__pin {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        color: #fff;
        border-radius: 50%;
        box-shadow: inset 0 0 0 3px #fff;
    }

    .personel-map-marker--no-location .personel-map-marker__pin {
        opacity: 0.78;
        box-shadow: inset 0 0 0 2px #fff;
        border: 1px dashed rgba(255, 255, 255, 0.9);
    }

    .personel-map-marker__name {
        max-width: 190px;
        margin-left: -9px;
        padding: 6px 11px 6px 15px;
        overflow: hidden;
        color: #343a40;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(52, 58, 64, 0.1);
        border-left: 3px solid var(--marker-color);
        border-radius: 0 9px 9px 0;
        pointer-events: none;
        backdrop-filter: blur(4px);
    }

    .fullscreen-map-wrapper {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999 !important;
        background: #f8f9fa;
        padding: 20px !important;
    }


    .fullscreen-map-wrapper {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 9999 !important;
        background: #f8f9fa;
        padding: 20px !important;
    }

    .fullscreen-map-wrapper #personelHarita {
        height: calc(100vh - 100px) !important;
    }
</style>

<!-- Leaflet JS & ApexCharts JS -->
<script src="assets/libs/leaflet/leaflet.js"></script>
<script src="assets/libs/apexcharts/apexcharts.min.js"></script>

<script>
    var currentPersonelId = null;
    var personelTakipDT = null;

    function escapeMapHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    var haritaMap = null;
    var allMapData = []; // Store raw data for filtering
    var haritaMarkers = [];
    var activeStatusFilters = ['aktif', 'bitti', 'baslamadi', 'izinli']; // Başlangıçta hepsi aktif

    document.addEventListener('DOMContentLoaded', function () {
        // İstatistikleri yükle
        loadOzet();
        // Personel listesini yükle
        loadPersonelDurumlari();

        const formatDate = (date) => {
            const d = new Date(date);
            return String(d.getDate()).padStart(2, '0') + '.' +
                String(d.getMonth() + 1).padStart(2, '0') + '.' +
                d.getFullYear();
        };

        // Varsayılan tarih aralıkları
        var today = new Date();
        var weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

        document.getElementById('gecmisBaslangic').value = formatDate(weekAgo);
        document.getElementById('gecmisBitis').value = formatDate(today);
        document.getElementById('raporBaslangic').value = formatDate(weekAgo);
        document.getElementById('raporBitis').value = formatDate(today);
        document.getElementById('gecKalmaTarih').value = formatDate(today);

        // Her 60 saniyede otomatik yenile
        setInterval(function () {
            loadOzet();
            loadPersonelDurumlari();
        }, 60000);

        // Filtre senkronizasyonu
        $('#mainDepartmanFilter').on('change', function() {
            var val = $(this).val();
            if ($('#mapDepartmanFilter').val() !== val) {
                $('#mapDepartmanFilter').val(val).trigger('change.select2');
            }
        });

        $('#mapDepartmanFilter').on('change', function() {
            var val = $(this).val();
            if ($('#mainDepartmanFilter').val() !== val) {
                $('#mainDepartmanFilter').val(val).trigger('change.select2');
                yenile(); // Listeyi de tazele
            }
        });

        // Select2 Çakışma Önleyici
        if (typeof $.fn.select2 === 'undefined') {
            console.warn('Select2 not found, retrying...');
        } else {
            $('.select2').select2({ width: '100%' });
        }

        // Özet kartları buton durumunu localStorage'a göre senkronize et
        try {
            if (localStorage.getItem('personel_takip_summary_collapsed') === 'true') {
                const icon = document.getElementById('iconToggleTakipOzet');
                const txt = document.getElementById('txtToggleTakipOzet');
                if (icon) icon.className = 'bx bx-chevron-down fs-5';
                if (txt) txt.textContent = 'Özeti Göster';
            }
        } catch(e) {}

        // Sayfa yenilendiğinde aktif olan tabın verisini yükle
        setTimeout(function () {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const tabEl = document.querySelector('a[href="#' + tabParam + '"]');
                if (tabEl) {
                    const tab = new bootstrap.Tab(tabEl);
                    tab.show();

                    if (tabParam === 'tabHarita') initHarita();
                    else if (tabParam === 'tabRapor') loadCalismaRaporu();
                    else if (tabParam === 'tabGecKalanlar') loadGecKalanlar();
                    else if (tabParam === 'tabDashboard') initDashboardTab();
                }
            }

            const activeTab = document.querySelector('.nav-tabs .nav-link.active');
            if (activeTab) {
                const target = activeTab.getAttribute('href');
                if (target === '#tabHarita') {
                    initHarita();
                } else if (target === '#tabRapor') {
                    loadCalismaRaporu();
                } else if (target === '#tabGecKalanlar') {
                    loadGecKalanlar();
                } else if (target === '#tabDashboard') {
                    initDashboardTab();
                }
            }
        }, 300);
    });

    async function loadOzet() {
        try {
            const departman = document.getElementById('mainDepartmanFilter')?.value || '';
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getOzet&departman=' + encodeURIComponent(departman)
            });
            const result = await response.json();

            if (result.success && result.data) {
                document.getElementById('stat-gorevde').textContent = result.data.gorevde;
                document.getElementById('stat-tamamladi').textContent = result.data.tamamladi;
                document.getElementById('stat-baslamadi').textContent = result.data.baslamadi;
                document.getElementById('stat-izinli').textContent = result.data.izinli || 0;
                document.getElementById('stat-gec-kalan').textContent = result.data.gec_kalan || 0;
            }
        } catch (error) {
            console.error('Özet yüklenirken hata:', error);
        }
    }

    async function loadPersonelDurumlari() {
        try {
            const departman = document.getElementById('mainDepartmanFilter')?.value || '';
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getPersonelDurumlari&departman=' + encodeURIComponent(departman)
            });
            const result = await response.json();

            const tbody = document.getElementById('personelTakipBody');

            // Eğer DataTable varsa önce yok et ve DOM'u temizle
            if ($.fn.DataTable.isDataTable('#personelTakipTable')) {
                $('#personelTakipTable').DataTable().destroy();
                $('#personelTakipBody').empty();
            }

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    html += '<tr>';
                    html += '<td class="text-center">' + p.foto + '</td>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td>' + p.departman + '</td>';
                    html += '<td>' + p.durum + '</td>';
                    html += '<td class="text-center">' + p.baslama + '</td>';
                    html += '<td class="text-center">' + p.bitis + '</td>';
                    html += '<td class="text-center">' + p.konum + '</td>';
                    html += '<td>' + p.islemler + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Henüz kayıt bulunmuyor</td></tr>';
            }

            // DataTable'ı başlat (init fonksiyonunu kontrol et)
            var options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
            personelTakipDT = $('#personelTakipTable').DataTable(applyLengthStateSave(options));

            // Detay butonlarına event listener ekle
            document.querySelectorAll('.btn-detay').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const personelId = this.getAttribute('data-id');
                    showGecmis(personelId);
                });
            });
        } catch (error) {
            console.error('Personel durumları yüklenirken hata:', error);
        }
    }

    // ============ KONUM İSTEĞİ FONKSİYONU ============
    async function konumIste(personelId) {
        const result = await Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu personelden anlık konum talep edilecektir.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f46a6a',
            cancelButtonColor: '#74788d',
            confirmButtonText: 'Evet, Talep Et',
            cancelButtonText: 'İptal'
        });

        if (result.isConfirmed) {
            try {
                // UI feedback
                Swal.fire({
                    title: 'İşlem Yapılıyor...',
                    html: 'Lütfen bekleyiniz, talep iletiliyor.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                });

                const response = await fetch('views/personel-takip/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=istekKonum&personel_id=' + personelId
                });
                const apiResult = await response.json();

                if (apiResult.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: apiResult.message || 'Konum talebi iletildi. Cihaz konumu aldığında harita güncellenecektir.',
                        icon: 'success'
                    });
                } else {
                    Swal.fire({
                        title: 'Uyarı',
                        text: apiResult.message || 'Hata oluştu.',
                        icon: 'warning'
                    });
                }
            } catch (error) {
                console.error('Konum isteği hatası:', error);
                Swal.fire({
                    title: 'Hata!',
                    text: 'İstek gönderilirken bir bağlantı hatası oluştu.',
                    icon: 'error'
                });
            }
        }
    }

    async function autoIstekTumKonum() {
        try {
            // Arka planda sessizce istek gönder
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=istekTumKonum'
            });
            const res = await response.json();

            if (res.success && res.data.eklenen > 0) {
                // Sadece yeni istek eklendiyse ufak bir uyarı verebiliriz (isteğe bağlı)
                console.log(res.message);
                // 3 saniye sonra haritayı bir kez daha tazele ki ilk gelen yanıtlar görünsün
                setTimeout(loadHaritaVerileri, 3000);
            }
        } catch (error) {
            console.error('Anlık konum talebi hatası:', error);
        }
    }

    // ============ HARİTA FONKSİYONLARI ============
    // Kahramanmaraş merkez koordinatları
    var kahramanmarasLat = 37.5847;
    var kahramanmarasLng = 36.9371;

    function initHarita() {
        if (haritaMap) {
            haritaMap.invalidateSize();
            loadHaritaVerileri();
            return;
        }

        // Kahramanmaraş merkezi
        haritaMap = L.map('personelHarita').setView([kahramanmarasLat, kahramanmarasLng], 11);

        // OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(haritaMap);

        loadHaritaVerileri();
    }

    async function loadHaritaVerileri() {
        try {
            const viewType = document.getElementById('modAnlik').checked ? 'anlik' : 'gorev';
            const departman = document.getElementById('mapDepartmanFilter')?.value || '';

            // Tüm personelleri getir (konum olsun olmasın)
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getHaritaVerileri&tumPersoneller=1&viewType=' + viewType + '&departman=' + encodeURIComponent(departman)
            });
            const result = await response.json();
            allMapData = result.data || []; // Store for filtering

            renderMapMarkers(allMapData);
        } catch (error) {
            console.error('Harita verileri yüklenirken hata:', error);
        }
    }

    function toggleMapStatusFilter(status, el) {
        const index = activeStatusFilters.indexOf(status);
        if (index > -1) {
            activeStatusFilters.splice(index, 1);
            el.style.opacity = '0.4';
        } else {
            activeStatusFilters.push(status);
            el.style.opacity = '1';
        }
        filterMapMarkers();
    }

    function filterMapMarkers() {
        const searchText = document.getElementById('mapSearchInput').value.toLowerCase().trim();
        
        let filteredData = allMapData;

        // Status Filtreleme
        filteredData = filteredData.filter(p => activeStatusFilters.includes(p.durum));

        // Metin Filtreleme
        if (searchText) {
            filteredData = filteredData.filter(p => 
                p.adi_soyadi.toLowerCase().includes(searchText)
            );
        }

        renderMapMarkers(filteredData);
    }

    function renderMapMarkers(data) {
        if (!haritaMap) return;

        // Mevcut markerları temizle
        haritaMarkers.forEach(m => haritaMap.removeLayer(m));
        haritaMarkers = [];

        if (data && data.length > 0) {
            var bounds = [];

            data.forEach(function (p) {
                var personelAdi = escapeMapHtml(p.adi_soyadi || 'İsimsiz Personel');
                // Konum yoksa Kahramanmaraş merkez + rastgele offset
                var lat = p.lat || (kahramanmarasLat + (Math.random() - 0.5) * 0.05);
                var lng = p.lng || (kahramanmarasLng + (Math.random() - 0.5) * 0.05);
                var hasLocation = p.lat && p.lng;

                // Durum rengine göre marker
                var markerColor = '#f46a6a'; // Varsayılan: kırmızı (başlamadı)
                var statusIcon = 'bx-time-five';

                if (p.durum === 'aktif') {
                    markerColor = '#34c38f'; // Yeşil
                    statusIcon = 'bx-run';
                } else if (p.durum === 'bitti') {
                    markerColor = '#556ee6'; // Mavi
                    statusIcon = 'bx-check';
                } else if (p.durum === 'izinli') {
                    markerColor = '#74788d'; // Gri (İzinli)
                    statusIcon = 'bx-calendar-minus';
                }

                var icon = L.divIcon({
                    className: 'custom-marker',
                    html: '<div class="personel-map-marker' + (hasLocation ? '' : ' personel-map-marker--no-location') + '" style="--marker-color: ' + markerColor + ';">' +
                        '<div class="personel-map-marker__pin" style="background-color: ' + markerColor + ';"><i class="bx ' + statusIcon + '" style="font-size: 17px;"></i></div>' +
                        '<div class="personel-map-marker__name" title="' + personelAdi + '">' + personelAdi + '</div>' +
                        '</div>',
                    iconSize: [220, 34],
                    iconAnchor: [17, 17]
                });

                var marker = L.marker([lat, lng], { icon: icon }).addTo(haritaMap);

                // Popup içeriği
                var fotoHtml = p.foto
                    ? '<img src="' + escapeMapHtml(p.foto) + '" width="50" height="50" style="object-fit: cover; border-radius: 50%;">'
                    : '<div style="width:50px;height:50px;background:#556ee6;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:18px;">' + personelAdi.charAt(0) + '</div>';

                var konumInfo = hasLocation
                    ? '<small class="text-muted">Son konum: ' + new Date(p.son_zaman || Date.now()).toLocaleTimeString('tr-TR') + '</small>'
                    : '<small class="text-warning"><i class="bx bx-error-circle"></i> Konum bilgisi yok</small>';

                var badgeClass = p.durum === 'aktif' ? 'bg-success' : (p.durum === 'bitti' ? 'bg-primary' : (p.durum === 'izinli' ? 'bg-info' : 'bg-secondary'));

                marker.bindPopup(
                    '<div class="marker-popup" style="text-align:center; min-width: 150px;">' +
                    fotoHtml +
                    '<h6 class="mt-2 mb-1">' + personelAdi + '</h6>' +
                    '<span class="badge ' + badgeClass + ' mb-1">' + escapeMapHtml(p.durum_text) + '</span><br>' +
                    konumInfo +
                    '<div class="mt-2 pt-2 border-top">' +
                    '<button class="btn btn-sm btn-soft-danger w-100" onclick="konumIste(' + p.id + ')">' +
                    '<i class="bx bx-target-lock me-1"></i> Anlık Konum İste' +
                    '</button>' +
                    '</div>' +
                    '</div>'
                );

                haritaMarkers.push(marker);
                bounds.push([lat, lng]);
            });

            // Markerlar varsa bounds'a göre zoom, yoksa Kahramanmaraş'ta kal
            if (bounds.length > 0) {
                haritaMap.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
            }
        }
    }

    // ============ ÇALIŞMA RAPORU FONKSİYONLARI ============
    async function loadCalismaRaporu() {
        const baslangicRaw = document.getElementById('raporBaslangic').value;
        const bitisRaw = document.getElementById('raporBitis').value;

        // API için YYYY-MM-DD formatına çevir
        const normalizeToISO = (str) => {
            if (str && str.includes('.')) {
                const p = str.split('.');
                return `${p[2]}-${p[1]}-${p[0]}`;
            }
            return str;
        };

        const baslangic = normalizeToISO(baslangicRaw);
        const bitis = normalizeToISO(bitisRaw);
        const departman = document.getElementById('mainDepartmanFilter')?.value || '';

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getCalismaRaporu&baslangic=' + baslangic + '&bitis=' + bitis + '&departman=' + encodeURIComponent(departman)
            });
            const result = await response.json();

            const tbody = document.getElementById('calismaRaporuBody');

            // DataTable'ı temizle
            if ($.fn.DataTable.isDataTable('#calismaRaporuTable')) {
                $('#calismaRaporuTable').DataTable().destroy();
                $('#calismaRaporuBody').empty();
            }

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    html += '<tr>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td class="text-center">' + p.toplam_gun + ' gün</td>';
                    html += '<td class="text-center"><span class="badge bg-soft-primary text-primary">' + p.toplam_saat + ' saat</span></td>';
                    html += '<td class="text-center">' + p.ort_baslama + '</td>';
                    html += '<td class="text-center">' + p.ort_bitis + '</td>';
                    html += '<td class="text-center">' + (p.gec_kalma > 0 ? '<span class="badge bg-soft-danger text-danger">' + p.gec_kalma + ' gün</span>' : '<span class="badge bg-soft-success text-success">0</span>') + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = ''; // DataTables will show "EmptyTable" message
            }

            // DataTable'ı başlat
            var options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
            $('#calismaRaporuTable').DataTable(applyLengthStateSave({
                ...options, // Genel ayarları al
                order: [[2, 'desc']], // Varsayılan: Toplam saate göre sırala
                pageLength: 25,
                destroy: true // Varsa üzerine yaz
            }));

        } catch (error) {
            console.error('Çalışma raporu yüklenirken hata:', error);
            document.getElementById('calismaRaporuBody').innerHTML = '';
        }
    }

    function raporExcelIndir() {
        const baslangicRaw = document.getElementById('raporBaslangic').value;
        const bitisRaw = document.getElementById('raporBitis').value;

        const baslangic = (baslangicRaw && baslangicRaw.includes('.')) ? baslangicRaw.split('.').reverse().join('-') : baslangicRaw;
        const bitis = (bitisRaw && bitisRaw.includes('.')) ? bitisRaw.split('.').reverse().join('-') : bitisRaw;

        // Tabloyu Excel olarak indir
        var table = document.getElementById('calismaRaporuTable');
        var csv = [];

        for (var i = 0; i < table.rows.length; i++) {
            var row = table.rows[i];
            var rowData = [];
            for (var j = 0; j < row.cells.length; j++) {
                rowData.push(row.cells[j].textContent.trim());
            }
            csv.push(rowData.join(';'));
        }

        var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'calisma_raporu_' + baslangic + '_' + bitis + '.csv';
        link.click();
    }

    // ============ GEÇ KALANLAR FONKSİYONLARI ============
    async function loadGecKalanlar() {
        const gecKalmaSaati = ''; // Boş bırakıldığında API ayarlardan alır
        const gecKalmaTarihRaw = document.getElementById('gecKalmaTarih').value;

        // API için YYYY-MM-DD formatına çevir
        const normalizeToISO = (str) => {
            if (str && str.includes('.')) {
                const p = str.split('.');
                return `${p[2]}-${p[1]}-${p[0]}`;
            }
            return str;
        };

        const gecKalmaTarih = normalizeToISO(gecKalmaTarihRaw);
        const departman = document.getElementById('mainDepartmanFilter')?.value || '';

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getGecKalanlar&limit_saat=' + gecKalmaSaati + '&tarih=' + gecKalmaTarih + '&departman=' + encodeURIComponent(departman)
            });
            const result = await response.json();

            const tbody = document.getElementById('gecKalanlarBody');

            // DataTable'ı temizle
            if ($.fn.DataTable.isDataTable('#gecKalanlarTable')) {
                $('#gecKalanlarTable').DataTable().destroy();
                $('#gecKalanlarBody').empty();
            }

            // Başlık tarihini güncelle
            document.getElementById('gecKalanlarBaslik').innerHTML = '<i class="bx bx-alarm-exclamation text-danger me-1"></i> ' + (gecKalmaTarihRaw.includes('.') ? gecKalmaTarihRaw : gecKalmaTarih) + ' Geç Kalanlar';

            // Geç kalan sayısını güncelle (Sadece bugün ise)
            const today = new Date();
            const todayStr = String(today.getDate()).padStart(2, '0') + '.' + String(today.getMonth() + 1).padStart(2, '0') + '.' + today.getFullYear();

            if (gecKalmaTarihRaw === todayStr && result.success && Array.isArray(result.data)) {
                document.getElementById('stat-gec-kalan').textContent = result.data.length;
            }

            if (result.success && result.data && result.data.length > 0) {
                let html = '';
                result.data.forEach(function (p) {
                    let aciklamaHtml = '-';
                    if (p.aciklama) {
                        aciklamaHtml = '<div class="small">' +
                            '<strong>' + p.aciklama + '</strong><br>' +
                            '<span class="text-muted" style="font-size: 0.75rem;">' +
                            '<i class="bx bx-user me-1"></i>' + p.guncelleyen_ad + ' (' + p.guncellenme_tarihi + ')' +
                            '</span></div>';
                    }

                    html += '<tr>';
                    html += '<td><strong>' + p.adi_soyadi + '</strong></td>';
                    html += '<td>' + (p.departman || '-') + '</td>';
                    html += '<td class="text-center">' + (p.limit_saat || '-') + '</td>';
                    html += '<td class="text-center">' + p.baslama_saati + '</td>';
                    html += '<td class="text-center"><span class="badge bg-soft-danger text-danger">' + p.gecikme + '</span></td>';
                    html += '<td class="text-center">' + p.durum + '</td>';
                    html += '<td>' + aciklamaHtml + '</td>';
                    html += '<td class="text-center">' +
                        '<button class="btn btn-sm btn-soft-success" onclick="openAciklamaModal(' + p.personel_id + ', \'' + p.adi_soyadi + '\', \'' + (p.aciklama || '') + '\')">' +
                        '<i class="bx bx-check-double me-1"></i> Bilgim Var' +
                        '</button>' +
                        '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '';
            }

            // DataTable'ı başlat
            var options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
            $('#gecKalanlarTable').DataTable(applyLengthStateSave({
                ...options, // Genel ayarları al
                order: [[2, 'desc']], // Varsayılan: Gecikmeye göre
                pageLength: 25,
                destroy: true
            }));

        } catch (error) {
            console.error('Geç kalanlar yüklenirken hata:', error);
            document.getElementById('gecKalanlarBody').innerHTML = '';
        }
    }

    // ============ GEÇMİŞ FONKSİYONLARI ============
    async function showGecmis(personelId) {
        currentPersonelId = personelId;

        // Modal göster
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('gecmisModal'));
        modal.show();

        // Loading göster
        document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';

        await loadGecmis();
    }

    async function loadGecmis() {
        if (!currentPersonelId) return;

        const baslangicRaw = document.getElementById('gecmisBaslangic').value;
        const bitisRaw = document.getElementById('gecmisBitis').value;

        const normalizeToISO = (str) => {
            if (str && str.includes('.')) {
                const p = str.split('.');
                return `${p[2]}-${p[1]}-${p[0]}`;
            }
            return str;
        };

        const baslangic = normalizeToISO(baslangicRaw);
        const bitis = normalizeToISO(bitisRaw);

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getHareketGecmisi&personel_id=' + encodeURIComponent(currentPersonelId) +
                    '&baslangic=' + baslangic + '&bitis=' + bitis
            });
            const result = await response.json();

            if (result.success && result.data) {
                // Personel bilgilerini güncelle
                const personel = result.data.personel;
                document.getElementById('gecmisPersonelAd').textContent = personel.adi_soyadi;
                if (personel.foto) {
                    document.getElementById('gecmisPersonelFoto').src = 'uploads/personel/' + personel.foto;
                }

                // Hareketleri listele
                const hareketler = result.data.hareketler;
                const tbody = document.getElementById('gecmisTabloBody');

                if (hareketler.length > 0) {
                    let html = '';
                    hareketler.forEach(function (h) {
                        html += '<tr>';
                        html += '<td>' + h.tarih + '</td>';
                        html += '<td>' + h.saat + '</td>';
                        html += '<td>' + h.islem + '</td>';
                        html += '<td>' + h.konum + '</td>';
                        html += '<td>' + h.hassasiyet + '</td>';
                        html += '</tr>';
                    });
                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Bu tarih aralığında hareket bulunamadı</td></tr>';
                }
            } else {
                document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">' + (result.message || 'Hata oluştu') + '</td></tr>';
            }
        } catch (error) {
            console.error('Geçmiş yüklenirken hata:', error);
            document.getElementById('gecmisTabloBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Yüklenirken hata oluştu</td></tr>';
        }
    }

    function filtreGecmis() {
        loadGecmis();
    }

    function yenile() {
        loadOzet();
        loadPersonelDurumlari();

        // Yenile butonunda görsel feedback
        const btn = document.querySelector('[onclick="yenile()"]');
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Yenileniyor...';
        btn.disabled = true;

        setTimeout(function () {
            btn.innerHTML = '<i class="bx bx-refresh"></i> Yenile';
            btn.disabled = false;
        }, 1000);
    }
    function openAciklamaModal(personelId, adSoyad, mevcutAciklama) {
        document.getElementById('aciklamaPersonelId').value = personelId;
        document.getElementById('aciklamaPersonelAd').textContent = adSoyad;
        document.getElementById('gecikmeAciklamaText').value = mevcutAciklama;
        
        const tarihRaw = document.getElementById('gecKalmaTarih').value;
        const normalizeToISO = (str) => {
            if (str && str.includes('.')) {
                const p = str.split('.');
                return `${p[2]}-${p[1]}-${p[0]}`;
            }
            return str;
        };
        document.getElementById('aciklamaTarih').value = normalizeToISO(tarihRaw);

        loadGecikmeHistory(personelId);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('aciklamaModal')).show();
    }

    async function loadGecikmeHistory(personelId) {
        const historyList = document.getElementById('gecikmeHistoryList');
        historyList.innerHTML = '<div class="text-center p-3 text-muted"><i class="bx bx-loader-alt bx-spin me-1"></i> Yükleniyor...</div>';

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=getGecikmeGecmisi&personel_id=' + personelId
            });
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                let html = '<ul class="list-group list-group-flush">';
                result.data.forEach(function(h) {
                    html += `<li class="list-group-item p-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-soft-info text-info small">${h.tarih_format}</span>
                                    <span class="text-muted" style="font-size: 0.7rem;">${h.ekleyen_ad} | ${h.guncellenme_format}</span>
                                </div>
                                <div class="fw-bold small">${h.aciklama}</div>
                             </li>`;
                });
                html += '</ul>';
                historyList.innerHTML = html;
            } else {
                historyList.innerHTML = '<div class="text-center p-3 text-muted">Geçmiş açıklama bulunamadı.</div>';
            }
        } catch (error) {
            console.error('Geçmiş yükleme hatası:', error);
            historyList.innerHTML = '<div class="text-center p-3 text-danger">Yüklenirken hata oluştu.</div>';
        }
    }

    async function saveAciklama() {
        const personelId = document.getElementById('aciklamaPersonelId').value;
        const tarih = document.getElementById('aciklamaTarih').value;
        const aciklama = document.getElementById('gecikmeAciklamaText').value;

        if (!aciklama.trim()) {
            Swal.fire('Uyarı', 'Lütfen bir açıklama yazınız.', 'warning');
            return;
        }

        try {
            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=saveGecikmeAciklama&personel_id=' + personelId + 
                      '&tarih=' + tarih + 
                      '&aciklama=' + encodeURIComponent(aciklama)
            });
            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('aciklamaModal')).hide();
                Swal.fire({
                    title: 'Başarılı',
                    text: result.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
                loadGecKalanlar(); // Tabloyu yenile
            } else {
                Swal.fire('Hata', result.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            console.error('Açıklama kaydedilirken hata:', error);
            Swal.fire('Hata', 'Sunucu ile iletişim kurulurken bir hata oluştu.', 'error');
        }
    }

    function toggleMapFullscreen() {
        const mapWrapper = document.getElementById('mapFullWrapper');
        mapWrapper.classList.toggle('fullscreen-map-wrapper');
        
        if (haritaMap) {
            setTimeout(() => {
                haritaMap.invalidateSize();
            }, 300);
        }

        // ESC tuşu ile çıkış için dinleyici (sadece eklendiğinde aktif olsun)
        if (mapWrapper.classList.contains('fullscreen-map-wrapper')) {
            const escHandler = function(e) {
                if (e.key === "Escape") {
                    mapWrapper.classList.remove('fullscreen-map-wrapper');
                    if (haritaMap) haritaMap.invalidateSize();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        }
    }



    async function openMesaiSettings() {
        const modalEl = document.getElementById('mesaiAyarlariModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        const listDiv = document.getElementById('mesaiSettingsList');
        listDiv.innerHTML = '<div class="text-center p-3 text-muted">Yükleniyor...</div>';
        try {
            const response = await fetch('views/personel-takip/api.php?action=getDepartmanMesaileri');
            const result = await response.json();
            if (result.success && result.data) {
                let html = '<div class="row g-2 fw-bold text-muted small mb-2 border-bottom pb-1"><div class="col-8">Departman</div><div class="col-4">Başlangıç</div></div>';
                result.data.forEach(item => {
                    html += `<div class="row g-2 align-items-center mb-2"><div class="col-8"><span class="fw-medium">${item.departman}</span></div><div class="col-4"><input type="time" class="form-control form-control-sm dept-mesai-input" data-dept="${item.departman}" value="${item.mesai_saati}"></div></div>`;
                });
                listDiv.innerHTML = html;
            } else { listDiv.innerHTML = '<div class="alert alert-warning">Veriler alınamadı.</div>'; }
        } catch (error) {
            console.error('Mesai ayarları yüklenirken hata:', error);
            listDiv.innerHTML = '<div class="alert alert-danger">Bir hata oluştu.</div>';
        }
    }

    async function saveMesaiSettings() {
        const settings = {};
        document.querySelectorAll('.dept-mesai-input').forEach(input => {
            settings[input.getAttribute('data-dept')] = input.value;
        });
        const btn = document.querySelector('#mesaiAyarlariModal .modal-footer .btn-primary');
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...';
        try {
            const formData = new FormData();
            formData.append('action', 'saveDepartmanMesaileri');
            formData.append('settings', JSON.stringify(settings));
            const response = await fetch('views/personel-takip/api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                Swal.fire('Başarılı', result.message, 'success');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('mesaiAyarlariModal')).hide();
                loadOzet();
                if ($('#tabGecKalanlar').hasClass('active')) loadGecKalanlar();
            } else { Swal.fire('Hata', result.message, 'error'); }
        } catch (error) {
            console.error('Mesai ayarları kaydedilirken hata:', error);
            Swal.fire('Hata', 'Bağlantı hatası oluştu', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    // ==========================================
    // ÖZET KARTLARI TOGGLE
    // ==========================================
    function toggleTakipSummary() {
        const ozet = document.getElementById('personelTakipOzetAlani');
        const icon = document.getElementById('iconToggleTakipOzet');
        const txt = document.getElementById('txtToggleTakipOzet');
        if (!ozet) return;

        const isCurrentlyHidden = $(ozet).is(':hidden') || ozet.style.display === 'none';
        
        if (isCurrentlyHidden) {
            $(ozet).slideDown(250);
            localStorage.setItem('personel_takip_summary_collapsed', 'false');
            if (icon) icon.className = 'bx bx-chevron-up fs-5';
            if (txt) txt.textContent = 'Özeti Gizle';
        } else {
            $(ozet).slideUp(250);
            localStorage.setItem('personel_takip_summary_collapsed', 'true');
            if (icon) icon.className = 'bx bx-chevron-down fs-5';
            if (txt) txt.textContent = 'Özeti Göster';
        }
    }

    // ==========================================
    // ANALİZ & DASHBOARD MODÜLÜ
    // ==========================================
    var dashCharts = {
        trend: null,
        saat: null,
        dept: null,
        durum: null
    };
    var dashPerformansDT = null;
    var dashInitialLoaded = false;

    function initDashboardTab() {
        if (!dashInitialLoaded) {
            loadDashboardAnaliz();
            dashInitialLoaded = true;
        } else {
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 100);
        }
    }

    function setDashQuickDate(type, btn) {
        if (btn) {
            $('#dashQuickDateGroup .btn').removeClass('active');
            $(btn).addClass('active');
        }

        const now = new Date();
        let start = new Date();
        let end = new Date();
        let label = 'Özel Tarih';

        const formatDateStr = (d) => {
            return String(d.getDate()).padStart(2, '0') + '.' +
                   String(d.getMonth() + 1).padStart(2, '0') + '.' +
                   d.getFullYear();
        };

        if (type === 'today') {
            start = now;
            end = now;
            label = 'Bugün';
        } else if (type === 'yesterday') {
            start = new Date(now.getTime() - 24 * 60 * 60 * 1000);
            end = start;
            label = 'Dün';
        } else if (type === 'week') {
            const dayOfWeek = now.getDay() || 7; // Pazartesi 1, Pazar 7
            start = new Date(now.getTime() - (dayOfWeek - 1) * 24 * 60 * 60 * 1000);
            end = now;
            label = 'Bu Hafta';
        } else if (type === 'month') {
            start = new Date(now.getFullYear(), now.getMonth(), 1);
            end = now;
            label = 'Bu Ay';
        } else if (type === 'last_month') {
            start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            end = new Date(now.getFullYear(), now.getMonth(), 0);
            label = 'Geçen Ay';
        }

        document.getElementById('dashBaslangic').value = formatDateStr(start);
        document.getElementById('dashBitis').value = formatDateStr(end);
        document.getElementById('dashActiveFilterLabel').innerHTML = `<i class="bx bx-calendar me-1"></i> ${label}`;

        loadDashboardAnaliz();
    }

    async function loadDashboardAnaliz() {
        const baslangic = document.getElementById('dashBaslangic')?.value || '';
        const bitis = document.getElementById('dashBitis')?.value || '';
        const personelId = $('#dashPersonelFilter').val() || 'all';
        const departman = $('#dashDepartmanFilter').val() || '';

        try {
            const formData = new FormData();
            formData.append('action', 'getDashboardAnaliz');
            formData.append('baslangic', baslangic);
            formData.append('bitis', bitis);
            formData.append('personel_id', personelId);
            formData.append('departman', departman);

            const response = await fetch('views/personel-takip/api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success && result.data) {
                renderDashboardKPIs(result.data);
                renderDashboardCharts(result.data);
                renderTopLists(result.data);
                renderDashboardTable(result.data);
                renderBireyselCard(result.data);
            } else {
                console.error('Dashboard API hatası:', result.message);
            }
        } catch (error) {
            console.error('Dashboard analiz yüklenirken hata oluştu:', error);
        }
    }

    function renderDashboardKPIs(data) {
        const kpi = data.kpi || {};
        
        // Toplam Süre
        document.getElementById('dashKpiToplamSure').textContent = `${kpi.toplam_saat || 0} sa`;
        document.getElementById('dashKpiToplamKayit').textContent = kpi.aktif_calisan_sayisi || 0;
        document.getElementById('dashKpiFiiliGun').textContent = `${kpi.toplam_takip_personel || 0} kişi`;

        // Ortalama Giriş & Günlük
        document.getElementById('dashKpiOrtGiris').textContent = `${kpi.gunluk_ort_saat || 0} sa/gün`;
        document.getElementById('dashKpiOrtGunluk').textContent = `%${kpi.katilim_orani || 0} katılım`;

        // Zamanında Başlama Oranı
        const zamanindaOran = kpi.zamaninda_oran || 0;
        document.getElementById('dashKpiZamanindaOran').textContent = `%${zamanindaOran}`;
        document.getElementById('dashKpiZamanindaBar').style.width = `${zamanindaOran}%`;

        // Gecikme
        document.getElementById('dashKpiGecikmeSayisi').textContent = `${kpi.gec_kalan_sayisi || 0} kez`;
        document.getElementById('dashKpiGecikmeDk').textContent = `${kpi.toplam_gecikme_dk || 0} dk`;
        const toplamGiris = (kpi.gec_kalan_sayisi || 0) + Math.round((kpi.gec_kalan_sayisi || 0) * (zamanindaOran / (100 - zamanindaOran || 1)));
        const gecikmeOran = 100 - zamanindaOran;
        document.getElementById('dashKpiGecikmeOran').textContent = `%${gecikmeOran}`;
    }

    function renderBireyselCard(data) {
        const card = document.getElementById('dashBireyselKarti');
        if (!data.is_single_personel || !data.personel_performans || data.personel_performans.length === 0) {
            if (card) card.style.display = 'none';
            return;
        }

        const p = data.personel_performans[0];
        if (card) card.style.display = 'block';

        const fotoEl = document.getElementById('dashBireyselFoto');
        if (fotoEl) fotoEl.src = p.foto || 'assets/images/users/user-dummy-img.jpg';
        document.getElementById('dashBireyselAd').textContent = p.adi_soyadi || '-';
        document.getElementById('dashBireyselDept').textContent = p.departman || 'Genel';
        document.getElementById('dashBireyselGun').textContent = `${p.calistigi_gun || 0} gün`;
        document.getElementById('dashBireyselSure').textContent = `${p.toplam_saat || 0} sa (Ort. ${p.ort_saat || 0} sa)`;
        document.getElementById('dashBireyselOrtGiris').textContent = p.avg_baslama || '--:--';
        document.getElementById('dashBireyselGecikme').textContent = `${p.gec_sayisi || 0} kez (${p.toplam_gecikme_dk || 0} dk kayıp)`;
    }

    function renderDashboardCharts(data) {
        // 1. Trend Grafiği (Bar + Line)
        const trend = data.trend || {};
        const categories = Array.isArray(trend.categories) ? trend.categories : [];
        const saatSerisi = Array.isArray(trend.saat_serisi) ? trend.saat_serisi : [];
        const kisiSerisi = Array.isArray(trend.kisi_serisi) ? trend.kisi_serisi : [];

        const trendOptions = {
            series: [
                {
                    name: 'Toplam Mesai (Saat)',
                    type: 'column',
                    data: saatSerisi
                },
                {
                    name: 'Çalışan Kişi Sayısı',
                    type: 'line',
                    data: kisiSerisi
                }
            ],
            chart: {
                height: 320,
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            stroke: {
                width: [0, 3],
                curve: 'smooth'
            },
            plotOptions: {
                bar: {
                    columnWidth: '40%',
                    borderRadius: 4
                }
            },
            colors: ['#556ee6', '#34c38f'],
            xaxis: {
                categories: categories
            },
            yaxis: [
                {
                    title: { text: 'Saat' },
                    labels: {
                        formatter: val => val !== undefined && val !== null ? Number(val).toFixed(1) : '0'
                    }
                },
                {
                    opposite: true,
                    title: { text: 'Kişi' },
                    labels: {
                        formatter: val => val !== undefined && val !== null ? Math.round(val) : '0'
                    }
                }
            ],
            tooltip: {
                shared: true,
                intersect: false
            }
        };

        if (dashCharts.trend) {
            dashCharts.trend.updateOptions(trendOptions);
        } else {
            const el = document.querySelector("#chartDashTrend");
            if (el) {
                dashCharts.trend = new ApexCharts(el, trendOptions);
                dashCharts.trend.render();
            }
        }

        // 2. Saat Dağılımı Donut
        const saatBuckets = data.saat_dagilimi || {};
        const saatLabels = Object.keys(saatBuckets);
        const saatSeries = Object.values(saatBuckets);

        const saatOptions = {
            series: saatSeries.length ? saatSeries : [0],
            labels: saatLabels.length ? saatLabels : ['Veri Yok'],
            chart: {
                type: 'donut',
                height: 320,
                fontFamily: 'inherit'
            },
            colors: ['#34c38f', '#50a5f1', '#556ee6', '#f1b44c', '#f46a6a'],
            legend: {
                position: 'bottom'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Toplam Giriş',
                                formatter: () => saatSeries.reduce((a, b) => a + (Number(b) || 0), 0)
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false }
        };

        if (dashCharts.saat) {
            dashCharts.saat.updateOptions(saatOptions);
        } else {
            const el = document.querySelector("#chartDashSaat");
            if (el) {
                dashCharts.saat = new ApexCharts(el, saatOptions);
                dashCharts.saat.render();
            }
        }

        // 3. Departman Mesai Bar Chart
        const deptList = Array.isArray(data.departman_analiz) ? data.departman_analiz : [];
        const deptNames = deptList.map(d => d.departman || 'Genel');
        const deptSaatler = deptList.map(d => d.toplam_saat || 0);

        const deptOptions = {
            series: [{
                name: 'Toplam Mesai (Saat)',
                data: deptSaatler
            }],
            chart: {
                type: 'bar',
                height: 280,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                    barHeight: '50%'
                }
            },
            colors: ['#50a5f1'],
            dataLabels: {
                enabled: true,
                formatter: val => `${val} sa`
            },
            xaxis: {
                categories: deptNames
            }
        };

        if (dashCharts.dept) {
            dashCharts.dept.updateOptions(deptOptions);
        } else {
            const el = document.querySelector("#chartDashDept");
            if (el) {
                dashCharts.dept = new ApexCharts(el, deptOptions);
                dashCharts.dept.render();
            }
        }

        // 4. Durum Dağılımı Pie/Donut
        const durumObj = data.durum_dagilimi || {};
        const durumLabels = ['Zamanında Başlayan', 'Geç Başlayan', 'İzinli'];
        const durumSeries = [
            durumObj.zamaninda || 0,
            durumObj.gec || 0,
            durumObj.izinli || 0
        ];

        const durumOptions = {
            series: durumSeries,
            labels: durumLabels,
            chart: {
                type: 'pie',
                height: 280,
                fontFamily: 'inherit'
            },
            colors: ['#34c38f', '#f46a6a', '#50a5f1'],
            legend: {
                position: 'bottom'
            }
        };

        if (dashCharts.durum) {
            dashCharts.durum.updateOptions(durumOptions);
        } else {
            const el = document.querySelector("#chartDashDurum");
            if (el) {
                dashCharts.durum = new ApexCharts(el, durumOptions);
                dashCharts.durum.render();
            }
        }
    }

    function renderTopLists(data) {
        // En çok çalışanlar
        const calisanlar = Array.isArray(data.top_calisanlar) ? data.top_calisanlar : [];
        const calisanlarEl = document.getElementById('dashTopCalisanlarList');
        if (calisanlarEl) {
            if (calisanlar.length === 0) {
                calisanlarEl.innerHTML = '<div class="text-center text-muted p-3">Kayıt bulunamadı</div>';
            } else {
                let html = '';
                calisanlar.forEach((item, idx) => {
                    const medalColor = idx === 0 ? 'text-warning' : (idx === 1 ? 'text-secondary' : (idx === 2 ? 'text-bronze' : 'text-muted'));
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-2 py-2 border-0 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold ${medalColor}" style="width: 20px;">#${idx + 1}</span>
                                <div>
                                    <div class="fw-bold text-dark mb-0">${escapeMapHtml(item.adi_soyadi)}</div>
                                    <small class="text-muted">${escapeMapHtml(item.departman || 'Genel')} • ${item.calistigi_gun || 0} gün</small>
                                </div>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fw-bold fs-6">${item.toplam_saat} sa</span>
                        </div>
                    `;
                });
                calisanlarEl.innerHTML = html;
            }
        }

        // En çok gecikenler
        const gecikenler = Array.isArray(data.top_gecikenler) ? data.top_gecikenler : [];
        const gecikenlerEl = document.getElementById('dashTopGecikenlerList');
        if (gecikenlerEl) {
            if (gecikenler.length === 0) {
                gecikenlerEl.innerHTML = '<div class="text-center text-muted p-3">Gecikme kaydı bulunamadı</div>';
            } else {
                let html = '';
                gecikenler.forEach((item, idx) => {
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-2 py-2 border-0 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-danger" style="width: 20px;">#${idx + 1}</span>
                                <div>
                                    <div class="fw-bold text-dark mb-0">${escapeMapHtml(item.adi_soyadi)}</div>
                                    <small class="text-muted">${escapeMapHtml(item.departman || 'Genel')}</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-danger-subtle text-danger fw-bold">${item.gec_sayisi} kez</span>
                                <div class="text-muted small">${item.toplam_gecikme_dk} dk kayıp</div>
                            </div>
                        </div>
                    `;
                });
                gecikenlerEl.innerHTML = html;
            }
        }
    }

    function renderDashboardTable(data) {
        const tableBody = document.getElementById('dashPersonelPerformansBody');
        const list = Array.isArray(data.personel_performans) ? data.personel_performans : [];

        if (dashPerformansDT) {
            dashPerformansDT.destroy();
            dashPerformansDT = null;
        }

        if (list.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Kayıt bulunamadı.</td></tr>';
            return;
        }

        let html = '';
        list.forEach(p => {
            const skor = p.dakiklik_skor !== undefined ? p.dakiklik_skor : 100;
            const zamanindaClass = skor >= 85 ? 'text-success' : (skor >= 60 ? 'text-warning' : 'text-danger');
            html += `
                <tr>
                    <td class="fw-bold">${escapeMapHtml(p.adi_soyadi)}</td>
                    <td><span class="badge bg-light text-dark">${escapeMapHtml(p.departman || 'Genel')}</span></td>
                    <td class="text-center">${p.calistigi_gun || 0} gün</td>
                    <td class="text-center fw-bold text-primary">${p.toplam_saat || 0} sa</td>
                    <td class="text-center">${p.ort_saat || 0} sa</td>
                    <td class="text-center"><span class="badge ${p.gec_sayisi > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success'}">${p.gec_sayisi || 0} kez (${p.toplam_gecikme_dk || 0} dk)</span></td>
                    <td class="text-center fw-bold ${zamanindaClass}">%${skor}</td>
                </tr>
            `;
        });
        tableBody.innerHTML = html;

        if (typeof applyLengthStateSave !== 'undefined' && typeof getDatatableOptions !== 'undefined') {
            dashPerformansDT = $('#dashPersonelPerformansTable').DataTable(
                applyLengthStateSave({
                    ...getDatatableOptions(),
                    pageLength: 10,
                    order: [[3, 'desc']]
                })
            );
        } else {
            dashPerformansDT = $('#dashPersonelPerformansTable').DataTable({
                pageLength: 10,
                order: [[3, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
                }
            });
        }
    }

    function exportDashboardExcel() {
        const baslangic = document.getElementById('dashBaslangic')?.value || '';
        const bitis = document.getElementById('dashBitis')?.value || '';
        const departman = $('#dashDepartmanFilter').val() || '';
        window.location.href = `views/personel-takip/api.php?action=exportCalismaRaporu&baslangic=${encodeURIComponent(baslangic)}&bitis=${encodeURIComponent(bitis)}&departman=${encodeURIComponent(departman)}`;
    }
</script>

<?php // } ?>
