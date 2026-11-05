<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Model\AracModel;
use App\Model\AracZimmetModel;
use App\Model\AracYakitModel;
use App\Model\AracKmModel;
use App\Model\PersonelModel;
use App\Model\AracServisModel;
use App\Model\AracKmBildirimModel;

$Arac = new AracModel();
$Zimmet = new AracZimmetModel();
$Yakit = new AracYakitModel();
$Km = new AracKmModel();
$Personel = new PersonelModel();
$Servis = new AracServisModel();
$KmBildirim = new AracKmBildirimModel();

$personeller = $Personel->all(false, 'arac');
$departmanlar = $Arac->getDepartmanlar();
$aracStats = $Arac->getStats();
$evrakStats = $Arac->getAracEvrakStats(30);
$zimmetliSayi = $Arac->getZimmetliAracSayisi();

// Aylık istatistikler (mevcut ay)
$yakitStats = $Yakit->getStats(date('Y'), date('m'));
$kmStats = $Km->getStats(date('Y'), date('m'));

$turkishMonths = [
    1 => 'Ocak',
    2 => 'Şubat',
    3 => 'Mart',
    4 => 'Nisan',
    5 => 'Mayıs',
    6 => 'Haziran',
    7 => 'Temmuz',
    8 => 'Ağustos',
    9 => 'Eylül',
    10 => 'Ekim',
    11 => 'Kasım',
    12 => 'Aralık',
];
$currentMonthLabel = $turkishMonths[(int) date('n')] . ' ' . date('Y');

$activeTab = $_GET['tab'] ?? 'arac';
$filter = $_GET['filter'] ?? null;

$listeBasliklari = [
    'muayene' => "Muayenesi Biten Araçlar",
    'muayene_yaklasan' => "Muayenesi Yaklaşan Araçlar",
    'sigorta' => "Sigortası Biten Araçlar",
    'sigorta_yaklasan' => "Sigortası Yaklaşan Araçlar",
    'kasko' => "Kaskosu Biten Araçlar",
    'kasko_yaklasan' => "Kaskosu Yaklaşan Araçlar",
    'zimmetli' => "Zimmetli Araçlar",
    'bosta' => "Boşta Olan Araçlar",
    'serviste' => "Servisteki Araçlar",
    'ikame' => "İkame Araçlar",
    'pasif' => "Pasif Araçlar",
];
if (isset($listeBasliklari[$filter])) {
    $title = $listeBasliklari[$filter];
}

$aktifAraclar = $Arac->getAktifAraclar();
$araclar = $aktifAraclar;

// Yakıt işlemlerindeki araç kaynağı, araç listesi sekmesinin aktif filtresinden
// bağımsızdır. İkame araçlar ana listede bilinçli olarak gizlense de yakıt
// filtresinde ve yakıt kayıt formunda seçilebilir olmalıdır.
$yakitAraclar = [];
foreach (array_merge($aktifAraclar, $Arac->getIkameAraclar()) as $yakitArac) {
    $yakitAraclar[(int) $yakitArac->id] = $yakitArac;
}
usort($yakitAraclar, static function ($a, $b) {
    return strcasecmp((string) $a->plaka, (string) $b->plaka);
});

?>

<style>
    .arac-list-preloader {
        position: absolute;
        inset: 0;
        z-index: 50;
        background: rgba(248, 250, 252, 0.72);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        border-radius: 0 0 12px 12px;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 120px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    #aracContent .table-responsive {
        overflow-x: auto !important;
    }

    #aracTable_wrapper {
        overflow-x: visible !important;
    }

    .arac-list-preloader.show {
        opacity: 1;
        visibility: visible;
        pointer-events: all;
    }

    .arac-list-preloader .loader-content {
        background: #ffffff;
        padding: 1.5rem 2rem;
        border-radius: 14px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        border: 1px solid rgba(226, 232, 240, 0.9);
        text-align: center;
        min-width: 260px;
    }

    html[data-theme-preset="macos-dark"] .arac-list-preloader,
    [data-bs-theme="dark"] .arac-list-preloader {
        background: rgba(18, 20, 26, 0.75);
    }

    html[data-theme-preset="macos-dark"] .arac-list-preloader .loader-content,
    [data-bs-theme="dark"] .arac-list-preloader .loader-content {
        background: rgba(30, 32, 40, 0.95);
        border-color: rgba(255, 255, 255, 0.12);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
        color: #f3f4f8;
    }

    html[data-theme-preset="macos-dark"] .arac-list-preloader .loader-content h5,
    [data-bs-theme="dark"] .arac-list-preloader .loader-content h5 {
        color: #f3f4f8;
    }

    .arac-info-box .plaka-label {
        font-size: 14px;
        font-weight: 700;
        color: #333;
    }

    .arac-info-box .model-label {
        font-size: 11px;
        color: #666;
        display: block;
    }

    /* Zimmetli Personel Column Styling */
    .zimmetli-personel-container {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .zimmetli-avatar-circle {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        color: #ffffff;
        flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .zimmetli-avatar-img {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .zimmetli-info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        line-height: 1.2;
    }

    .zimmetli-name {
        font-size: 13px;
        font-weight: 700;
        color: var(--bs-body-color, #212529);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .zimmetli-name:hover {
        color: var(--bs-primary, #556ee6);
    }

    .zimmetli-dept {
        font-size: 11px;
        color: #858d98;
        margin-top: 1px;
    }

    /* Evrak Badges (Muayene, Kasko, Sigorta) Column Styling */
    .evrak-badges-wrapper {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .evrak-badge-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 5px 9px;
        border-radius: 6px;
        min-width: 68px;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }

    .evrak-badge-box .evrak-badge-title {
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        line-height: 1;
        margin-bottom: 3px;
        opacity: 0.85;
    }

    .evrak-badge-box .evrak-badge-date {
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
    }

    /* Evrak Badge Light Mode Colors */
    .evrak-badge-success {
        background-color: rgba(32, 201, 151, 0.12);
        border-color: rgba(32, 201, 151, 0.3);
        color: #107c41;
    }
    .evrak-badge-success .evrak-badge-title,
    .evrak-badge-success .evrak-badge-date {
        color: #107c41;
    }

    .evrak-badge-warning {
        background-color: rgba(246, 194, 62, 0.15);
        border-color: rgba(246, 194, 62, 0.4);
        color: #b78103;
    }
    .evrak-badge-warning .evrak-badge-title,
    .evrak-badge-warning .evrak-badge-date {
        color: #b78103;
    }

    .evrak-badge-danger {
        background-color: rgba(244, 106, 106, 0.15);
        border-color: rgba(244, 106, 106, 0.4);
        color: #e63946;
    }
    .evrak-badge-danger .evrak-badge-title,
    .evrak-badge-danger .evrak-badge-date {
        color: #e63946;
    }

    .evrak-badge-muted {
        background-color: rgba(108, 117, 125, 0.08);
        border-color: rgba(108, 117, 125, 0.2);
        color: #6c757d;
    }
    .evrak-badge-muted .evrak-badge-title,
    .evrak-badge-muted .evrak-badge-date {
        color: #6c757d;
    }

    /* Dark Mode Overrides for Evrak Badges */
    [data-bs-theme="dark"] .evrak-badge-success,
    body[data-bs-theme="dark"] .evrak-badge-success,
    [data-topbar="dark"] .evrak-badge-success {
        background-color: rgba(32, 201, 151, 0.15);
        border-color: rgba(32, 201, 151, 0.35);
    }
    [data-bs-theme="dark"] .evrak-badge-success .evrak-badge-title,
    [data-bs-theme="dark"] .evrak-badge-success .evrak-badge-date,
    body[data-bs-theme="dark"] .evrak-badge-success .evrak-badge-title,
    body[data-bs-theme="dark"] .evrak-badge-success .evrak-badge-date {
        color: #20c997;
    }

    [data-bs-theme="dark"] .evrak-badge-warning,
    body[data-bs-theme="dark"] .evrak-badge-warning,
    [data-topbar="dark"] .evrak-badge-warning {
        background-color: rgba(246, 194, 62, 0.15);
        border-color: rgba(246, 194, 62, 0.35);
    }
    [data-bs-theme="dark"] .evrak-badge-warning .evrak-badge-title,
    [data-bs-theme="dark"] .evrak-badge-warning .evrak-badge-date,
    body[data-bs-theme="dark"] .evrak-badge-warning .evrak-badge-title,
    body[data-bs-theme="dark"] .evrak-badge-warning .evrak-badge-date {
        color: #f6c23e;
    }

    [data-bs-theme="dark"] .evrak-badge-danger,
    body[data-bs-theme="dark"] .evrak-badge-danger,
    [data-topbar="dark"] .evrak-badge-danger {
        background-color: rgba(244, 106, 106, 0.15);
        border-color: rgba(244, 106, 106, 0.35);
    }
    [data-bs-theme="dark"] .evrak-badge-danger .evrak-badge-title,
    [data-bs-theme="dark"] .evrak-badge-danger .evrak-badge-date,
    body[data-bs-theme="dark"] .evrak-badge-danger .evrak-badge-title,
    body[data-bs-theme="dark"] .evrak-badge-danger .evrak-badge-date {
        color: #f46a6a;
    }

    [data-bs-theme="dark"] .evrak-badge-muted,
    body[data-bs-theme="dark"] .evrak-badge-muted,
    [data-topbar="dark"] .evrak-badge-muted {
        background-color: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
    }
    [data-bs-theme="dark"] .evrak-badge-muted .evrak-badge-title,
    [data-bs-theme="dark"] .evrak-badge-muted .evrak-badge-date,
    body[data-bs-theme="dark"] .evrak-badge-muted .evrak-badge-title,
    body[data-bs-theme="dark"] .evrak-badge-muted .evrak-badge-date {
        color: #74788d;
    }
</style>

<?php
$maintitle = "Araç Takip";
$viewTitle = $title ?? "Araç Takip & Yakıt Yönetimi";
$title = $viewTitle;
$esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$evrakBitenToplam = (int) ($evrakStats->muayene_biten ?? 0) + (int) ($evrakStats->sigorta_biten ?? 0) + (int) ($evrakStats->kasko_biten ?? 0);
$evrakYaklasanToplam = (int) ($evrakStats->muayene_yaklasan ?? 0) + (int) ($evrakStats->sigorta_yaklasan ?? 0) + (int) ($evrakStats->kasko_yaklasan ?? 0);
$zimmetliServistekiSayi = $Arac->getZimmetliServistekiAracSayisi();
?>
<script>try { document.documentElement.classList.toggle('arac-summary-hidden', localStorage.getItem('arac_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.arac-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#aracTabContent .dataTables_wrapper > .dt-buttons { display: none; }
#aracTab #geciciBekleyenSayi { background-color: #ffe8a3 !important; color: #6b4a00 !important; min-width: 22px; font-weight: 700; }
#aracTab .nav-link.active #geciciBekleyenSayi { background-color: #ffffff !important; color: #1f2937 !important; }
#zimmetContent, #geciciContent { padding-top: .75rem; }
#aracContent .status-filter-group + .table-responsive, #aracContent #aracTable_wrapper { margin-top: 0; }
#summaryCardsContainer .summary-kpi-card, .arac-kpi-row .summary-kpi-card { background-color: #f3f5f8; border: 1px solid #e4e8ee; box-shadow: none; }
html[data-theme-preset="macos-dark"] #summaryCardsContainer .summary-kpi-card, [data-bs-theme="dark"] #summaryCardsContainer .summary-kpi-card, .arac-kpi-row .summary-kpi-card { background-color: rgba(255, 255, 255, 0.04); border-color: rgba(255, 255, 255, 0.08); }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">

    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-car fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Araç Takip</h4>
                <p class="text-muted mb-0 font-size-12">Araç, zimmet, yakıt, KM ve servis kayıtlarının yönetimi ve takibi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button class="btn btn-primary top-action-btn shadow-sm text-white" type="button" id="btnYeniEkle">
                <i class="bx bx-plus font-size-16"></i> Yeni Ekle
            </button>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li><a class="dropdown-item" href="#" id="btnExceleAktar">
                            <i class="bx bx-export me-2 text-success"></i> Excele Aktar
                        </a></li>
                    <li id="liExcelYakitYukle" style="display: none;"><a class="dropdown-item" href="#"
                            data-bs-toggle="modal" data-bs-target="#excelModal">
                            <i class="bx bx-upload me-2"></i> Excel'den Yakıt Yükle
                        </a></li>
                    <li id="liExcelAracYukle" <?php echo $activeTab === 'arac' ? '' : 'style="display: none;"'; ?>><a class="dropdown-item" href="#" data-bs-toggle="modal"
                            data-bs-target="#aracExcelModal">
                            <i class="bx bx-upload me-2"></i> Excel'den Araç Yükle
                        </a></li>
                    <li id="liExcelKmYukle" <?php echo $activeTab === 'km' ? '' : 'style="display: none;"'; ?>><a class="dropdown-item" href="#" data-bs-toggle="modal"
                            data-bs-target="#kmExcelYukleModal">
                            <i class="bx bx-upload me-2"></i> Excel'den KM Yükle
                        </a></li>
                </ul>
            </div>

            <?php foreach (['arac' => 'aracTable', 'zimmet' => 'zimmetTable', 'gecici' => 'geciciTable', 'yakit' => 'yakitTable', 'km' => 'kmTable', 'servis' => 'servisTable'] as $colmanTab => $colmanTable): ?>
                <span class="d-inline-block aracColmanSlot <?php echo $activeTab === $colmanTab ? '' : 'd-none'; ?>" data-colman-tab="<?php echo $colmanTab; ?>" data-dt-colman-for="<?php echo $colmanTable; ?>" data-dt-colman-style="outline"></span>
            <?php endforeach; ?>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <?php if (\App\Service\Gate::allows('ai_is_ajani_arac_takip')): ?>
        <?php include dirname(__DIR__, 2) . '/views/layouts/components/ai_agent_widget.php'; ?>
    <?php endif; ?>

    <div class="card summary-kpi-card mb-3" id="aracListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <ul class="nav nav-pills" id="aracTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'arac' ? 'active' : ''; ?>"
                        id="arac-tab" data-bs-toggle="tab" data-bs-target="#aracContent" type="button" role="tab">
                        <i class="bx bx-car me-1"></i> Araçlar
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'zimmet' ? 'active' : ''; ?>"
                        id="zimmet-tab" data-bs-toggle="tab" data-bs-target="#zimmetContent" type="button" role="tab">
                        <i class="bx bx-transfer me-1"></i> Zimmetler
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'gecici' ? 'active' : ''; ?>"
                        id="gecici-tab" data-bs-toggle="tab" data-bs-target="#geciciContent" type="button" role="tab">
                        <i class="bx bx-transfer-alt me-1"></i> Geçici Teslimler
                        <span class="badge rounded-pill ms-1" id="geciciBekleyenSayi">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'yakit' ? 'active' : ''; ?>"
                        id="yakit-tab" data-bs-toggle="tab" data-bs-target="#yakitContent" type="button" role="tab">
                        <i class="bx bx-gas-pump me-1"></i> Yakıt Kayıtları
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'km' ? 'active' : ''; ?>" id="km-tab"
                        data-bs-toggle="tab" data-bs-target="#kmContent" type="button" role="tab">
                        <i class="bx bx-tachometer me-1"></i> KM Kayıtları
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'servis' ? 'active' : ''; ?>"
                        id="servis-tab" data-bs-toggle="tab" data-bs-target="#servisContent" type="button" role="tab">
                        <i class="bx bx-wrench me-1"></i> Servis Kayıtları
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3 pt-0 position-relative">
            <div class="arac-list-preloader show" id="arac-list-loader">
                <div class="loader-content">
                    <div class="spinner-border text-primary m-1" role="status" style="width: 2.2rem; height: 2.2rem; border-width: 0.22em;">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <h5 class="mt-2 mb-1 font-size-14 fw-bold">Araçlar Yükleniyor...</h5>
                    <p class="text-muted font-size-11 mb-0">Araç listesi hazırlanıyor...</p>
                </div>
            </div>
            <div class="tab-content" id="aracTabContent">

                        <!-- =============================================
                             ARAÇLAR TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'arac' ? 'show active' : ''; ?>"
                            id="aracContent" role="tabpanel">

            <div class="row g-3 mt-1 mb-3 summary-cards-group" id="summaryCardsContainer">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card summary-kpi-card h-100 mb-0">
                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-label">TOPLAM ARAÇ</span>
                                <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                    <i class="bx bx-car"></i>
                                </div>
                            </div>
                            <h3 class="summary-kpi-value my-1"><?php echo (int) ($aracStats->toplam_arac ?? 0); ?> Adet</h3>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-subtext">Pasif: <?php echo (int) ($aracStats->pasif_arac ?? 0); ?> | İkame: <?php echo (int) ($aracStats->ikame_arac ?? 0); ?></span>
                                <a href="index.php?p=arac-takip/list&filter=tumu" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 d-flex align-items-center gap-1 summary-pill-btn <?php echo $filter === 'tumu' ? 'active' : ''; ?>">
                                    <i class="bx bx-layer"></i> Tümü
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card summary-kpi-card h-100 mb-0">
                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-label">AKTİF ARAÇ</span>
                                <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                    <i class="bx bx-check-circle"></i>
                                </div>
                            </div>
                            <h3 class="summary-kpi-value my-1 text-success"><?php echo (int) ($aracStats->aktif_arac ?? 0); ?> Adet</h3>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-subtext text-success fw-semibold">Zimmetli: <?php echo (int) $zimmetliSayi; ?> | Boşta: <?php echo (int) ($aracStats->bosta_arac ?? 0); ?></span>
                                <a href="index.php?p=arac-takip/list" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 d-flex align-items-center gap-1 summary-pill-btn <?php echo empty($filter) ? 'active' : ''; ?>">
                                    <i class="bx bx-check"></i> Aktif
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card summary-kpi-card h-100 mb-0">
                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-label">SÜRESİ GEÇEN EVRAK</span>
                                <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bx bx-error-circle"></i>
                                </div>
                            </div>
                            <h3 class="summary-kpi-value my-1 text-danger"><?php echo $evrakBitenToplam; ?> Adet</h3>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-subtext">
                                    <a href="index.php?p=arac-takip/list&filter=muayene" class="text-danger text-decoration-none">Muayene: <?php echo (int) ($evrakStats->muayene_biten ?? 0); ?></a> |
                                    <a href="index.php?p=arac-takip/list&filter=sigorta" class="text-danger text-decoration-none">Sigorta: <?php echo (int) ($evrakStats->sigorta_biten ?? 0); ?></a> |
                                    <a href="index.php?p=arac-takip/list&filter=kasko" class="text-danger text-decoration-none">Kasko: <?php echo (int) ($evrakStats->kasko_biten ?? 0); ?></a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card summary-kpi-card h-100 mb-0">
                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-label">YAKLAŞAN EVRAK (30 GÜN)</span>
                                <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                    <i class="bx bx-time-five"></i>
                                </div>
                            </div>
                            <h3 class="summary-kpi-value my-1 text-warning"><?php echo $evrakYaklasanToplam; ?> Adet</h3>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="summary-kpi-subtext">
                                    <a href="index.php?p=arac-takip/list&filter=muayene_yaklasan" class="text-warning text-decoration-none">Muayene: <?php echo (int) ($evrakStats->muayene_yaklasan ?? 0); ?></a> |
                                    <a href="index.php?p=arac-takip/list&filter=sigorta_yaklasan" class="text-warning text-decoration-none">Sigorta: <?php echo (int) ($evrakStats->sigorta_yaklasan ?? 0); ?></a> |
                                    <a href="index.php?p=arac-takip/list&filter=kasko_yaklasan" class="text-warning text-decoration-none">Kasko: <?php echo (int) ($evrakStats->kasko_yaklasan ?? 0); ?></a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2 status-filter-group">
                                <a href="index.php?p=arac-takip/list&filter=tumu" class="btn btn-sm btn-soft-primary <?php echo $filter === 'tumu' ? 'active' : ''; ?>">
                                    <i class="bx bx-car me-1"></i> Araç <span class="badge bg-primary ms-1"><?php echo (int) ($aracStats->toplam_arac ?? 0); ?></span>
                                </a>
                                <a href="index.php?p=arac-takip/list" class="btn btn-sm btn-soft-success <?php echo empty($filter) ? 'active' : ''; ?>">
                                    <i class="bx bx-check-circle me-1"></i> Aktif <span class="badge bg-success ms-1"><?php echo (int) ($aracStats->aktif_arac ?? 0); ?></span>
                                </a>
                                <a href="index.php?p=arac-takip/list&filter=pasif" class="btn btn-sm btn-soft-danger <?php echo $filter === 'pasif' ? 'active' : ''; ?>">
                                    <i class="bx bx-x-circle me-1"></i> Pasif <span class="badge bg-danger ms-1"><?php echo (int) ($aracStats->pasif_arac ?? 0); ?></span>
                                </a>
                                <a href="index.php?p=arac-takip/list&filter=zimmetli" class="btn btn-sm btn-soft-warning <?php echo $filter === 'zimmetli' ? 'active' : ''; ?>">
                                    <i class="bx bx-transfer me-1"></i> Zimmetli <span class="badge bg-warning ms-1"><?php echo (int) $zimmetliSayi; ?></span>
                                </a>
                                <a href="index.php?p=arac-takip/list&filter=bosta" class="btn btn-sm btn-soft-info <?php echo $filter === 'bosta' ? 'active' : ''; ?>">
                                    <i class="bx bx-user-x me-1"></i> Boşta <span class="badge bg-info ms-1"><?php echo (int) ($aracStats->bosta_arac ?? 0); ?></span>
                                </a>
                                <a href="index.php?p=arac-takip/list&filter=serviste" id="badge-servisteki-arac" class="btn btn-sm btn-soft-danger <?php echo $filter === 'serviste' ? 'active' : ''; ?>">
                                    <i class="bx bx-wrench me-1"></i> Servisteki <span class="badge bg-danger ms-1"><?php echo (int) $Arac->getServistekiAracSayisi(); ?></span><?php if ($zimmetliServistekiSayi > 0): ?> <small>(<?php echo (int) $zimmetliServistekiSayi; ?>'i Zimmetli)</small><?php endif; ?>
                                </a>
                                <a href="index.php?p=arac-takip/list&filter=ikame" class="btn btn-sm btn-soft-warning <?php echo $filter === 'ikame' ? 'active' : ''; ?>">
                                    <i class="bx bx-repost me-1"></i> İkame <span class="badge bg-warning ms-1"><?php echo (int) ($aracStats->ikame_arac ?? 0); ?></span>
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table id="aracTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:3%">Sıra</th>
                                            <th style="width:6%" class="text-center" data-filter="string">Tip</th>
                                            <th style="width:13%" data-filter="string">Plaka / Araç</th>
                                            <th style="width:9%" data-filter="string">Departman</th>
                                            <th style="width:7%" data-filter="string">Mülkiyet</th>
                                            <th style="width:14%" data-filter="string">Zimmetli Personel</th>
                                            <th style="width:10%" class="text-center" data-filter="date">Zimmet Tarihi</th>
                                            <th style="width:6%" class="text-center" data-filter="string">Durum</th>
                                            <th style="width:6%" class="text-center" data-filter="string">Yakıt</th>
                                            <th style="width:7%" class="text-end" data-filter="number">KM</th>
                                            <th style="width:7%" class="text-center" data-filter="date">Muayene Bitiş</th>
                                            <th style="width:7%" class="text-center" data-filter="date">Sigorta Bitiş</th>
                                            <th style="width:7%" class="text-center" data-filter="date">Kasko Bitiş</th>
                                            <th style="width:5%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             ZİMMETLER TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'zimmet' ? 'show active' : ''; ?>"
                            id="zimmetContent" role="tabpanel">
                            <div class="table-responsive">
                                <table id="zimmetTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:18%" data-filter="string">Araç</th>
                                            <th style="width:18%" data-filter="string">Personel</th>
                                            <th style="width:12%" data-filter="date">Zimmet Tarihi</th>
                                            <th style="width:12%" data-filter="date">İade Tarihi</th>
                                            <th style="width:10%" class="text-center" data-filter="string">Durum</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="zimmetTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade <?php echo $activeTab === 'gecici' ? 'show active' : ''; ?>"
                            id="geciciContent" role="tabpanel">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 status-filter-group" id="geciciDurumFiltre">
                                <button type="button" class="btn btn-sm btn-soft-primary active" data-durum="">Tümü <span class="badge bg-primary ms-1" data-ozet="toplam">0</span></button>
                                <button type="button" class="btn btn-sm btn-soft-warning" data-durum="beklemede">Onay Bekleyen <span class="badge bg-warning ms-1" data-ozet="beklemede">0</span></button>
                                <button type="button" class="btn btn-sm btn-soft-info" data-durum="aktif">Teslimde <span class="badge bg-info ms-1" data-ozet="aktif">0</span></button>
                                <button type="button" class="btn btn-sm btn-soft-secondary" data-durum="kapanan">Kapanan <span class="badge bg-secondary ms-1" data-ozet="kapanan">0</span></button>
                                <button type="button" class="btn btn-sm btn-soft-danger" data-durum="iptal_red">Red / İptal <span class="badge bg-danger ms-1" data-ozet="iptal_red">0</span></button>
                                <small class="text-muted ms-auto">Asıl zimmet kayıtları değişmez; burada yalnızca personeller arası geçici teslimler listelenir.</small>
                            </div>
                            <div class="table-responsive">
                                <table id="geciciTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th data-filter="string">Araç</th>
                                            <th data-filter="string">Asıl Zimmet Sahibi</th>
                                            <th data-filter="string">Teslim Eden</th>
                                            <th data-filter="string">Teslim Alan</th>
                                            <th data-filter="string">Talep / Teslim</th>
                                            <th data-filter="string">Geri Alma</th>
                                            <th class="text-center">KM</th>
                                            <th class="text-center" data-filter="string">Kaynak</th>
                                            <th class="text-center" data-filter="string">Durum</th>
                                            <th class="text-center" style="width:8%">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="geciciTableBody">
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-4">Yükleniyor...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             YAKIT KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'yakit' ? 'show active' : ''; ?>"
                            id="yakitContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row g-3 mt-1 mb-3 arac-kpi-row">
                                <div class="col-12 col-sm-6 col-xl-3">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM YAKIT</span>
                                                <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bx bx-gas-pump"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1" id="yakit-toplam-litre"><?php echo number_format($yakitStats->toplam_litre ?? 0, 0, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600;">L</span></h3>
                                            <span class="summary-kpi-subtext">Seçili dönem toplam litre</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-3">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM NET MALİYET</span>
                                                <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="bx bx-money"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-primary" id="yakit-toplam-maliyet"><?php echo number_format($yakitStats->toplam_tutar ?? 0, 2, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span></h3>
                                            <span class="summary-kpi-subtext">İskonto sonrası toplam tutar</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-3">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">ORT. BİRİM FİYAT</span>
                                                <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                                    <i class="bx bx-purchase-tag"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1" id="yakit-ortalama-fiyat"><?php echo number_format($yakitStats->ortalama_birim_fiyat ?? 0, 2, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span></h3>
                                            <span class="summary-kpi-subtext">Litre başına ortalama</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-3">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM KAYIT</span>
                                                <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                                    <i class="bx bx-list-ul"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-warning" id="yakit-kayit-sayisi"><?php echo (int) ($yakitStats->toplam_kayit ?? 0); ?></h3>
                                            <span class="summary-kpi-subtext">Yakıt kayıt sayısı</span>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-3 arac-filter-card">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-xl-2 col-md-4">
                                            <div class="date-filter-mode-container w-100">
                                                <input type="radio" name="yakitDateMode" id="yakitDateModeRange"
                                                    value="range" class="date-filter-mode-input" checked>
                                                <label for="yakitDateModeRange" class="date-filter-mode-label">Tarih
                                                    Aralığı</label>

                                                <input type="radio" name="yakitDateMode" id="yakitDateModePeriod"
                                                    value="period" class="date-filter-mode-input">
                                                <label for="yakitDateModePeriod"
                                                    class="date-filter-mode-label">Dönem</label>
                                            </div>
                                        </div>

                                        <div class="col-xl-2 col-md-4 yakit-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4 yakit-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4 yakit-period-field d-none">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'yakit-filtre-donem', $currentMonthLabel, '', 'Dönem (Ay.Yıl)', 'calendar', 'form-control month-picker'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4">

                                            <?php
                                            $aracOptions = ['' => 'Tüm Araçlar'];
                                            foreach ($yakitAraclar as $arac) {
                                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                            }
                                            echo App\Helper\Form::FormSelect2('yakit-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>


                                        </div>
                                        <div class="col-xl-2 col-md-4">
                                            <?php
                                            $departmanOptions = ['' => 'Tüm Departmanlar'];
                                            foreach ($departmanlar as $departman) {
                                                $departmanOptions[$departman] = $departman;
                                            }
                                            echo App\Helper\Form::FormSelect2('yakit-filtre-departman', $departmanOptions, '', 'Departman', 'codepen', 'key', '', 'form-select select2');
                                            ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4">
                                            <?php
                                            $personelOptions = ['' => 'Tüm Personeller'];
                                            echo App\Helper\Form::FormSelect2('yakit-filtre-personel', $personelOptions, '', 'Personel', 'user', 'key', '', 'form-select select2');
                                            ?>
                                        </div>
                                        <div class="col-12 d-flex align-items-center justify-content-end gap-2 mt-2">
                                            <button type="button" class="btn btn-danger" id="btnYakitTopluSil" style="display: none;">
                                                <i class="bx bx-trash me-1"></i> Seçilenleri Sil (<span id="selectedYakitCount">0</span>)
                                            </button>
                                            <button type="button" class="btn btn-dark" id="btnYakitFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info text-white" id="btnYakitIstatistik"
                                                data-bs-toggle="modal" data-bs-target="#istatistikModal"
                                                data-type="yakit">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                            <button type="button" class="btn btn-light border" id="btnYakitTemizle">
                                                <i class="bx bx-reset me-1"></i> Temizle
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="yakitTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:30px">
                                                <input type="checkbox" class="form-check-input" id="checkAllYakit" title="Tümünü Seç">
                                            </th>
                                            <th class="text-center" style="width:4%">Sıra</th>
                                            <th style="width:10%" data-filter="string">Plaka</th>
                                            <th style="width:11%" data-filter="string">Zimmetli Personel</th>
                                            <th style="width:9%" data-filter="date">Yakıt Tarihi</th>
                                            <th style="width:11%" data-filter="date">Kayıt Tarihi</th>
                                            <th style="width:8%" class="text-end" data-filter="number">KM</th>
                                            <th style="width:8%" class="text-end" data-filter="number">Miktar (L)</th>
                                            <th style="width:8%" class="text-end" data-filter="number">Birim Fiyat</th>
                                            <th style="width:8%" class="text-end" data-filter="number">Brüt Tutar</th>
                                            <th style="width:7%" class="text-end" data-filter="number">İskonto%</th>
                                            <th style="width:10%" class="text-end" data-filter="number">Net Tutar</th>
                                            <th style="width:12%" data-filter="string">İstasyon</th>
                                            <th style="width:6%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="yakitTableBody">
                                        <tr>
                                            <td colspan="14" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- =============================================
                             KM KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'km' ? 'show active' : ''; ?>"
                            id="kmContent" role="tabpanel">
                            <!-- Aylık Özet Kartları -->
                            <div class="row g-3 mt-1 mb-3 arac-kpi-row">
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM YOL</span>
                                                <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                                    <i class="bx bx-trending-up"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1" id="km-toplam-yol"><?php echo number_format($kmStats->toplam_km ?? 0, 0, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600;">km</span></h3>
                                            <span class="summary-kpi-subtext">Seçili dönem toplam yol</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">GÜNLÜK ORT. YOL</span>
                                                <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                                    <i class="bx bx-stats"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-success" id="km-ortalama-yol"><?php echo number_format($kmStats->ortalama_gunluk_km ?? 0, 1, ',', '.'); ?> <span style="font-size: 0.85rem; font-weight: 600;">km</span></h3>
                                            <span class="summary-kpi-subtext">Günlük ortalama</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM KAYIT</span>
                                                <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                                    <i class="bx bx-list-check"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-warning" id="km-kayit-sayisi"><?php echo (int) ($kmStats->toplam_kayit ?? 0); ?></h3>
                                            <span class="summary-kpi-subtext">KM kayıt sayısı</span>
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-3 arac-filter-card">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-xl-2 col-md-4">
                                            <div class="date-filter-mode-container w-100">
                                                <input type="radio" name="kmDateMode" id="kmDateModeRange" value="range"
                                                    class="date-filter-mode-input" checked>
                                                <label for="kmDateModeRange" class="date-filter-mode-label">Tarih
                                                    Aralığı</label>

                                                <input type="radio" name="kmDateMode" id="kmDateModePeriod"
                                                    value="period" class="date-filter-mode-input">
                                                <label for="kmDateModePeriod"
                                                    class="date-filter-mode-label">Dönem</label>
                                            </div>
                                        </div>
                                        <div class="col-xl-2 col-md-4 km-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4 km-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4 km-period-field d-none">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'km-filtre-donem', $currentMonthLabel, '', 'Dönem (Ay.Yıl)', 'calendar', 'form-control month-picker'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4">
                                            <?php
                                            echo App\Helper\Form::FormSelect2('km-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>







                                        </div>
                                        <div class="col-xl-2 col-md-4">
                                            <?php
                                            $departmanOptions = ['' => 'Tüm Departmanlar'];
                                            foreach ($departmanlar as $departman) {
                                                $departmanOptions[$departman] = $departman;
                                            }
                                            echo App\Helper\Form::FormSelect2('km-filtre-departman', $departmanOptions, '', 'Departman', 'codepen', 'key', '', 'form-select select2');
                                            ?>
                                        </div>
                                        <div class="col-xl-2 col-md-12 d-flex align-items-center gap-2">
                                            <button type="button" class="btn btn-primary w-100" id="btnKmFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                            <button type="button" class="btn btn-info w-100" id="btnKmIstatistik"
                                                data-bs-toggle="modal" data-bs-target="#istatistikModal" data-type="km">
                                                <i class="bx bx-stats me-1"></i> İstatistikler
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="kmTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%" data-filter="string">Plaka</th>
                                            <th style="width:18%" data-filter="string">Zimmetli Personel</th>
                                            <th style="width:11%" data-filter="date">Tarih</th>
                                            <th style="width:14%" class="text-end" data-filter="number">Başlangıç KM</th>
                                            <th style="width:14%" class="text-end" data-filter="number">Bitiş KM</th>
                                            <th style="width:16%" class="text-end" data-filter="number">Yapılan KM</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kmTableBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Yükleniyor...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>



                        <!-- =============================================
                             SERVİS KAYITLARI TAB
                             ============================================= -->
                        <div class="tab-pane fade <?php echo $activeTab === 'servis' ? 'show active' : ''; ?>"
                            id="servisContent" role="tabpanel">
                            <!-- Servis Özet Kartları -->
                            <div class="row g-3 mt-1 mb-3 arac-kpi-row">
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM SERVİS KAYDI</span>
                                                <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                                    <i class="bx bx-wrench"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1" id="servis-toplam-kayit">0</h3>
                                            <span class="summary-kpi-subtext">Seçili dönem servis kaydı</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">SERVİSTEKİ ARAÇLAR</span>
                                                <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                                    <i class="bx bx-time"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-warning" id="servis-servisteki-arac">0</h3>
                                            <span class="summary-kpi-subtext">Güncel durum</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="card summary-kpi-card h-100 mb-0">
                                        <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="summary-kpi-label">TOPLAM SERVİS MALİYETİ</span>
                                                <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                                    <i class="bx bx-wallet"></i>
                                                </div>
                                            </div>
                                            <h3 class="summary-kpi-value my-1 text-danger" id="servis-toplam-maliyet">0.00 <span style="font-size: 0.85rem; font-weight: 600;">₺</span></h3>
                                            <span class="summary-kpi-subtext">Toplam servis tutarı</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtreler -->
                            <div class="card border shadow-none mb-3 arac-filter-card">
                                <div class="card-body p-3">
                                    <div class="row g-3">

                                        <div class="col-xl-2 col-md-4">
                                            <div class="date-filter-mode-container w-100">
                                                <input type="radio" name="servisDateMode" id="servisDateModeRange"
                                                    value="range" class="date-filter-mode-input" checked>
                                                <label for="servisDateModeRange" class="date-filter-mode-label">Tarih
                                                    Aralığı</label>

                                                <input type="radio" name="servisDateMode" id="servisDateModePeriod"
                                                    value="period" class="date-filter-mode-input">
                                                <label for="servisDateModePeriod"
                                                    class="date-filter-mode-label">Dönem</label>
                                            </div>
                                        </div>

                                        <div class="col-xl-2 col-md-4 servis-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'servis-filtre-baslangic', date('01.m.Y'), '', 'Başlangıç Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>
                                        <div class="col-xl-2 col-md-4 servis-range-field">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'servis-filtre-bitis', date('t.m.Y'), '', 'Bitiş Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                        </div>

                                        <div class="col-xl-2 col-md-4 servis-period-field d-none">
                                            <?php echo App\Helper\Form::FormFloatInput('text', 'servis-filtre-donem', $currentMonthLabel, '', 'Dönem (Ay.Yıl)', 'calendar', 'form-control month-picker'); ?>
                                        </div>

                                        <div class="col-xl-2 col-md-4">
                                            <?php
                                            $aracOptions = ['' => 'Tüm Araçlar'];
                                            foreach ($araclar as $arac) {
                                                $aracOptions[$arac->id] = $arac->plaka . ' - ' . ($arac->marka ?? '') . ' ' . ($arac->model ?? '');
                                            }
                                            echo App\Helper\Form::FormSelect2('servis-filtre-arac', $aracOptions, '', 'Plaka', 'truck', 'key', '', 'form-select select2');
                                            ?>
                                        </div>
                                        <div class="col-xl-2 col-md-12 d-flex align-items-center">
                                            <button type="button" class="btn btn-primary w-100" id="btnServisFiltrele">
                                                <i class="bx bx-filter-alt me-1"></i> Filtrele
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table id="servisTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:5%">Sıra</th>
                                            <th style="width:12%" data-filter="string">Plaka</th>
                                            <th style="width:12%" data-filter="date">Servis Giriş</th>
                                            <th style="width:12%" data-filter="date">Servis Çıkış</th>
                                            <th style="width:10%" class="text-end" data-filter="number">Giriş KM</th>
                                            <th style="width:10%" class="text-end" data-filter="number">Çıkış KM</th>
                                            <th style="width:20%" data-filter="string">Servis Nedeni</th>
                                            <th style="width:15%" data-filter="string">İşlem Sonucu</th>
                                            <th style="width:10%" class="text-center" data-filter="string">İkame Araç</th>
                                            <th style="width:10%" class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody id="servisTableBody">
                                        <tr>
                                            <td class="text-center py-4 text-muted">
                                                <div class="spinner-border spinner-border-sm text-primary"
                                                    role="status"></div>
                                            </td>
                                            <td class="py-4 text-muted">Yükleniyor...</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
</div>

<style>
    #yakitTable tbody tr,
    #kmTable tbody tr,
    #servisTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #yakitTable tbody tr:hover,
    #kmTable tbody tr:hover,
    #servisTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .nav-pills .nav-link.active {
        background-color: #556ee6;
    }

    .nav-pills .nav-link {
        color: #495057;
    }

    .date-filter-mode-container {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        background: #f8f9fc;
        border: 1px solid #dfe3eb;
        border-radius: 12px;
        padding: 4px;
        gap: 4px;
    }

    .date-filter-mode-input {
        display: none;
    }

    .date-filter-mode-label {
        text-align: center;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 7px 6px;
        color: #5a6270;
        border-radius: 9px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .date-filter-mode-input:checked+.date-filter-mode-label {
        background: #556ee6;
        color: #fff;
        box-shadow: 0 2px 6px rgba(85, 110, 230, 0.25);
    }

    .card.bg-gradient {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
</style>

<!-- Modaller -->
<?php include_once "modal/arac-modal.php"; ?>
<?php include_once "modal/zimmet-modal.php"; ?>
<?php include_once "modal/zimmet-iade-modal.php"; ?>
<?php include_once "modal/zimmet-devir-modal.php"; ?>

<!-- Shadcn Style Context Menu -->
<div id="arac-context-menu" class="dropdown-menu shadow-sm border" style="display: none; position: fixed; z-index: 9999; min-width: 200px; border-radius: 8px; padding: 4px; background: #ffffff; border-color: #e2e8f0 !important;">
    <div class="dropdown-header py-2 px-2 mb-1 border-bottom" style="font-size: 12px; font-weight: 500; color: #64748b; letter-spacing: -0.01em;">
        <div class="fw-bold text-dark d-flex align-items-center">
            <i class="bx bx-layer text-primary me-1-5"></i>
            <span id="context-arac-plaka">İşlemler</span>
        </div>
        <div id="context-arac-user-info" class="text-muted font-size-11 mt-1 d-none d-flex align-items-center" style="font-weight: normal;">
            <i class="bx bx-user-circle text-primary me-1 font-size-13"></i>
            <span id="context-arac-user-text"></span>
        </div>
    </div>
    <div id="context-menu-items">
        <!-- JS ile doldurulacak -->
    </div>
</div>

<style>
    #arac-context-menu {
        animation: shadcnIn 0.1s ease-out;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -1px rgb(0 0 0 / 0.06) !important;
    }
    @keyframes shadcnIn {
        from { opacity: 0; transform: scale(0.98); }
        to { opacity: 1; transform: scale(1); }
    }
    #arac-context-menu .dropdown-item {
        padding: 6px 8px;
        font-size: 13px;
        display: flex;
        align-items: center;
        border-radius: 4px;
        margin: 0;
        transition: background-color 0.1s;
        color: #1e293b !important;
        font-weight: 400;
        cursor: pointer;
    }
    #arac-context-menu .dropdown-item:hover {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
    }
    #arac-context-menu .dropdown-item i {
        font-size: 15px;
        margin-right: 10px;
        width: 16px;
        text-align: center;
        color: #64748b !important; /* Neutral icons */
    }
    /* Danger Action (Delete) */
    #arac-context-menu .dropdown-item.text-danger {
        color: #ef4444 !important;
    }
    #arac-context-menu .dropdown-item.text-danger i {
        color: #ef4444 !important;
    }
    #arac-context-menu .dropdown-item.text-danger:hover {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
    }
    #arac-context-menu .dropdown-divider {
        margin: 4px;
        border-top: 1px solid #e2e8f0;
        opacity: 1;
    }
    /* Satır vurgulama - Shadcn tarzı daha hafif */
    .context-menu-active {
        background-color: #f8fafc !important;
    }
</style>
<link rel="stylesheet" href="assets/libs/glightbox/css/glightbox.min.css">
<script src="assets/libs/glightbox/js/glightbox.min.js"></script>
<?php include_once "modal/zimmet-gecmisi-modal.php"; ?>
<?php include_once "modal/arac-islem-gecmisi-modal.php"; ?>
<?php include_once "modal/zimmet-duzenle-modal.php"; ?>
<?php include_once "modal/yakit-modal.php"; ?>
<?php include_once "modal/km-modal.php"; ?>
<?php include_once "modal/excel-modal.php"; ?>
<?php include_once "modal/arac-excel-modal.php"; ?>
<?php include_once "modal/servis-modal.php"; ?>
<?php include_once "modal/km-excel-yukle-modal.php"; ?>

<!-- İstatistik Modal -->
<div class="modal fade" id="istatistikModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-stats me-2"></i>İstatistik Detayları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="istatistikModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2">Yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- İkame Araç Detay Modal -->
<div class="modal fade" id="ikameDetayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold"><i class="bx bx-transfer me-2"></i>İkame Araç Bilgileri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                            <i class="bx bx-car text-warning fs-1"></i>
                        </div>
                        <div>
                            <h4 class="mb-0 fw-bold" id="ikame-detay-plaka">-</h4>
                            <p class="text-muted mb-0" id="ikame-detay-marka-model">-</p>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="text-muted small fw-bold mb-1">ALIŞ TARİHİ</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-calendar me-2 text-primary"></i>
                                <span class="fw-semibold" id="ikame-detay-alis-tarih">-</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small fw-bold mb-1">İADE TARİHİ</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-calendar-check me-2 text-success"></i>
                                <span class="fw-semibold" id="ikame-detay-iade-tarih">-</span>
                            </div>
                        </div>
                        <div class="col-6 border-top pt-3">
                            <label class="text-muted small fw-bold mb-1">TESLİM KM</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-tachometer me-2 text-info"></i>
                                <span class="fw-semibold" id="ikame-detay-teslim-km">-</span>
                            </div>
                        </div>
                        <div class="col-6 border-top pt-3">
                            <label class="text-muted small fw-bold mb-1">İADE KM</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-tachometer me-2 text-danger"></i>
                                <span class="fw-semibold" id="ikame-detay-iade-km">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var KEY = 'arac_summary_cards_state';
    var root = document.documentElement;
    var btn = document.getElementById('btnToggleSummaryCards');
    var cards = document.getElementById('summaryCardsContainer');
    function sync() {
        var hidden = root.classList.contains('arac-summary-hidden');
        if (!btn) return;
        btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        btn.setAttribute('title', hidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle');
        var i = btn.querySelector('i');
        if (i) i.className = hidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
    }
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            root.classList.toggle('arac-summary-hidden');
            try { localStorage.setItem(KEY, root.classList.contains('arac-summary-hidden') ? 'hidden' : 'visible'); } catch (err) {}
            sync();
        });
    }
    sync();
    document.addEventListener('shown.bs.tab', function (e) {
        if (!e.target || e.target.closest('#aracTab') === null) return;
        var isArac = e.target.getAttribute('data-bs-target') === '#aracContent';
        if (btn) btn.classList.toggle('d-none', !isArac);
        var tabKey = (e.target.getAttribute('data-bs-target') || '').replace('#', '').replace('Content', '');
        document.querySelectorAll('.aracColmanSlot').forEach(function (slot) {
            slot.classList.toggle('d-none', slot.getAttribute('data-colman-tab') !== tabKey);
        });
    });
    if (btn && '<?php echo $activeTab; ?>' !== 'arac') btn.classList.add('d-none');
})();
</script>
<script src="assets/libs/pdfjs/pdf.min.js"></script>
<script src="assets/libs/tesseract.js/tesseract.min.js"></script>
<script src="<?php echo \App\Helper\Helper::assetVersion('views/arac-takip/js/ruhsat-ocr.js'); ?>"></script>
<script src="<?php echo \App\Helper\Helper::assetVersion('views/arac-takip/js/ruhsat-ayristirici.js'); ?>"></script>
<script src="<?php echo \App\Helper\Helper::assetVersion('views/arac-takip/js/arac-takip.js'); ?>"></script>
<script src="<?php echo \App\Helper\Helper::assetVersion('views/arac-takip/js/ai-agent.js'); ?>"></script>
