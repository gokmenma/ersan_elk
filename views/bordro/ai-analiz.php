<?php

use App\Model\BordroDonemModel;
use App\Model\BordroParametreModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$BordroParametre = new BordroParametreModel();

$selectedYil = $_GET['yil'] ?? date('Y');
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;
$compareDonemId = $_GET['compare_donem'] ?? null;

// Tüm dönemler
$donemler = $BordroDonem->getAllDonemsForFilter();
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
$compare_donem_option = ['' => 'Otomatik (Bir Önceki Dönem)'];

foreach ($donemler as $d) {
    $donem_option[$d->id] = $d->donem_adi;
    $compare_donem_option[$d->id] = $d->donem_adi;
}

if (!$selectedDonemId && !empty($donemler)) {
    $selectedDonemId = $donemler[0]->id;
}

$selectedDonem = $selectedDonemId ? $BordroDonem->getDonemById($selectedDonemId) : null;
?>

<!-- Breadcrumb -->
<?php
$pageTitle = "Yapay Zeka Bordro Denetimi & Karşılaştırmalı Risk Analizi";
$breadcrumbs = [
    ['title' => 'Bordro', 'url' => 'index.php?p=bordro/list'],
    ['title' => 'AI Risk Analizi & Denetim', 'active' => true]
];
include 'layouts/breadcrumb.php';
?>

<style>
    /* Tablo ve Sayfa İçi Tasarım İyileştirmeleri */
    .ai-audit-container {
        padding: 0 15px 30px 15px;
    }
    .status-filter-group .btn {
        font-weight: 500;
        font-size: 0.82rem;
        padding: 0.35rem 0.75rem;
    }
    .status-filter-group .btn.active {
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    #auditPersonelTable th {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        background-color: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    #auditPersonelTable td {
        font-size: 0.84rem;
    }
    .card-kpi {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .card-kpi:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.06) !important;
    }
</style>

<div class="container-fluid ai-audit-container">
    <div class="row">
        <div class="col-12">
            <!-- 1. Üst Kontrol & Dönem Seçim Barı -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 14px;">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div style="min-width: 130px;">
                                <?php echo Form::FormSelect2(
                                    name: 'auditYilSelect',
                                    options: $yil_option,
                                    selectedValue: $selectedYil,
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div style="min-width: 200px;">
                                <?php echo Form::FormSelect2(
                                    name: 'auditDonemSelect',
                                    options: $donem_option,
                                    selectedValue: $selectedDonemId,
                                    label: 'Analiz Edilecek Dönem',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div style="min-width: 220px;">
                                <?php echo Form::FormSelect2(
                                    name: 'auditCompareDonemSelect',
                                    options: $compare_donem_option,
                                    selectedValue: $compareDonemId,
                                    label: 'Karşılaştırma Dönemi',
                                    icon: 'calendar-sync',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div>
                                <button type="button" class="btn btn-primary d-flex align-items-center gap-1 px-3 shadow-sm" id="btnRunPageAudit" style="height: 38px; margin-top: 18px;">
                                    <i class="mdi mdi-robot fs-5"></i> <span>Yeniden Analiz Et</span>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2" style="margin-top: 18px;">
                            <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 px-3" id="btnToggleAiReport">
                                <i class="mdi mdi-brain fs-5"></i> <span id="btnToggleAiReportText">AI Raporunu Göster</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="btnExportAuditExcel">
                                <i class="mdi mdi-file-excel text-success fs-5"></i> Excel İndir
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" onclick="window.print();">
                                <i class="mdi mdi-printer text-dark fs-5"></i> Yazdır / PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yükleniyor Placeholder -->
            <div id="pageAuditLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status" style="width: 3.5rem; height: 3.5rem;">
                    <span class="visually-hidden">Yapay Zeka Analiz Ediyor...</span>
                </div>
                <h5 class="mt-4 fw-semibold text-dark">Yapay Zeka Bordro Verilerini Derinlemesine Denetliyor...</h5>
                <p class="text-muted mb-0">Mevzuat kuralları, dağıtım tutarlılığı, işe giriş/çıkış gün kontrolleri ve karşılaştırmalı trendler taranıyor.</p>
            </div>

            <!-- Ana Dashboard İçeriği -->
            <div id="pageAuditContent" style="display: none;">
                <!-- 2. KPI Metrik Kartları -->
                <div class="row g-3 mb-4">
                    <!-- Sağlık Skoru -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-kpi h-100 border-0 shadow-sm rounded-3 bg-white">
                            <div class="card-body p-3 text-center d-flex flex-column justify-content-center">
                                <span class="text-muted fw-semibold small text-uppercase mb-1" style="letter-spacing: 0.5px;">Bordro Sağlık Skoru</span>
                                <div class="d-flex align-items-center justify-content-center gap-2 my-1">
                                    <div id="pageHealthScore" class="display-5 fw-bold text-success">100</div>
                                    <span class="fs-4 fw-bold text-muted">/ 100</span>
                                </div>
                                <div class="progress mt-2" style="height: 6px; border-radius: 4px;">
                                    <div id="pageHealthProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
                                </div>
                                <span class="text-muted small mt-2 fw-medium" id="pageHealthStatusText">Güvenli & Stabil</span>
                            </div>
                        </div>
                    </div>

                    <!-- Finansal Risk Tutarı -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-kpi h-100 border-0 shadow-sm rounded-3 bg-white border-start border-primary border-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-primary fw-semibold small text-uppercase" style="letter-spacing: 0.5px;">Tahmini Finansal Risk</span>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1"><i class="mdi mdi-cash-multiple"></i> Kaçak / Fazla</span>
                                </div>
                                <h3 class="fw-bold mb-1 text-primary" id="pageRiskAmount">0,00 ₺</h3>
                                <p class="text-muted small mb-0">Fazla/eksik gün ve dağıtım sapması hacmi</p>
                            </div>
                        </div>
                    </div>

                    <!-- Kritik Hatalar -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-kpi h-100 border-0 shadow-sm rounded-3 bg-white border-start border-danger border-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-danger fw-semibold small text-uppercase" style="letter-spacing: 0.5px;">Kritik Hatalar</span>
                                    <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="mdi mdi-alert-octagon"></i> Acil</span>
                                </div>
                                <h3 class="fw-bold mb-1 text-danger" id="pageCriticalCount">0</h3>
                                <p class="text-muted small mb-0">Eksi bakiye, dağıtım kaçağı, gün aşımı</p>
                            </div>
                        </div>
                    </div>

                    <!-- Karşılaştırma / Değişim Metriği -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-kpi h-100 border-0 shadow-sm rounded-3 bg-white border-start border-info border-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-info fw-semibold small text-uppercase" style="letter-spacing: 0.5px;">Önceki Döneme Göre Net Fark</span>
                                    <span class="badge bg-info-subtle text-info rounded-pill px-2 py-1" id="pageCompareLabel">Karşılaştırma</span>
                                </div>
                                <h3 class="fw-bold mb-1 text-dark" id="pageCompareDiff">0,00 ₺</h3>
                                <p class="text-muted small mb-0" id="pageComparePersonnelDiff">Personel sayısı değişimi: 0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. ÖNCELİKLİ TABLO: Personel Denetim ve Karşılaştırma Tablosu -->
                <div class="card border-0 shadow-sm rounded-3 bg-white mb-4">
                    <div class="card-header bg-white py-3 px-4 border-bottom">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <i class="mdi mdi-account-group text-primary fs-4"></i> Personel Bazlı Denetim & Karşılaştırma Listesi
                                </h5>
                            </div>

                            <!-- Filtreleme Buton Grubu -->
                            <div class="btn-group btn-group-sm status-filter-group" role="group" id="riskFilterButtonGroup">
                                <button type="button" class="btn btn-outline-secondary active" data-filter="all">Tümü (<span id="filterAllCount">0</span>)</button>
                                <button type="button" class="btn btn-outline-danger" data-filter="critical">🔴 Kritik Riskler (<span id="filterCriticalCount">0</span>)</button>
                                <button type="button" class="btn btn-outline-warning" data-filter="warning">🟡 Uyarılar (<span id="filterWarningCount">0</span>)</button>
                                <button type="button" class="btn btn-outline-success" data-filter="clean">🟢 Sorunsuzlar (<span id="filterCleanCount">0</span>)</button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="auditPersonelTable" style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th data-filter="string" style="width: 18%;">Personel</th>
                                        <th data-filter="select" style="width: 11%;">Çalışma / Gün</th>
                                        <th style="width: 20%;">Bu Dönem Dağılımı</th>
                                        <th style="width: 14%;">Önceki Dönem Kıyas</th>
                                        <th data-filter="select" style="width: 12%;">Risk Durumu</th>
                                        <th style="width: 25%;">Bulgu Detayı & Önerilen Aksiyon</th>
                                    </tr>
                                </thead>
                                <tbody id="auditPersonelTableBody">
                                    <!-- JS ile dinamik dolacak -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 4. YAPAY ZEKA RAPORU VE DAĞITIM KIYASLAMA ALANI (Tablonun Altında / İsteğe Bağlı) -->
                <div id="aiReportSection" class="row g-3 mb-4" style="display: none;">
                    <!-- Sol Kolon: Yapay Zeka Yönetici Notu -->
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white" style="border-left: 4px solid #4f46e5 !important;">
                            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center gap-2">
                                <div class="text-white rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                                    <i class="mdi mdi-brain fs-6"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-0">Yapay Zeka Yönetici Denetim Raporu</h6>
                            </div>
                            <div class="card-body p-4">
                                <div id="pageAiReportText" class="text-secondary lh-lg" style="font-size: 0.92rem;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Kolon: Karşılaştırmalı Ödeme Dağılım Kartı -->
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm rounded-3 h-100 bg-white">
                            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                    <i class="mdi mdi-chart-box-outline text-primary fs-5"></i> Ödeme Dağılımı & Karşılaştırma
                                </h6>
                                <span class="badge bg-light text-dark border" id="pageSummaryDonemName">-</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0 text-center">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ödeme Kanalı</th>
                                                <th>Seçili Dönem</th>
                                                <th>Karşılaştırılan Dönem</th>
                                                <th>Fark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-start fw-semibold text-primary"><i class="mdi mdi-bank me-1"></i> Banka Ödemesi</td>
                                                <td class="fw-bold" id="distCurrBanka">0 ₺</td>
                                                <td class="text-muted" id="distPrevBanka">0 ₺</td>
                                                <td class="fw-semibold" id="distDiffBanka">0 ₺</td>
                                            </tr>
                                            <tr>
                                                <td class="text-start fw-semibold text-success"><i class="mdi mdi-cash me-1"></i> Elden Ödeme</td>
                                                <td class="fw-bold" id="distCurrElden">0 ₺</td>
                                                <td class="text-muted" id="distPrevElden">0 ₺</td>
                                                <td class="fw-semibold" id="distDiffElden">0 ₺</td>
                                            </tr>
                                            <tr>
                                                <td class="text-start fw-semibold text-info"><i class="mdi mdi-food me-1"></i> Yemek Yardımı</td>
                                                <td class="fw-bold" id="distCurrYemek">0 ₺</td>
                                                <td class="text-muted" id="distPrevYemek">0 ₺</td>
                                                <td class="fw-semibold" id="distDiffYemek">0 ₺</td>
                                            </tr>
                                            <tr class="table-light">
                                                <td class="text-start fw-bold text-dark">TOPLAM NET HAKEDİŞ</td>
                                                <td class="fw-bold text-dark fs-6" id="distCurrTotal">0 ₺</td>
                                                <td class="fw-semibold text-muted" id="distPrevTotal">0 ₺</td>
                                                <td class="fw-bold" id="distDiffTotal">0 ₺</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4 p-3 bg-light rounded-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small fw-semibold text-muted">Personel Dağılımı:</span>
                                        <span class="badge bg-primary rounded-pill px-2 py-1" id="badgeTotalPersonnel">0 Personel</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div id="progCleanPersonnel" class="progress-bar bg-success" role="progressbar" style="width: 50%" title="Sorunsuz"></div>
                                        <div id="progCriticalPersonnel" class="progress-bar bg-danger" role="progressbar" style="width: 50%" title="Kritik Hatalı"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 small text-muted">
                                        <span><i class="mdi mdi-circle text-success me-1"></i> Sorunsuz: <strong id="lblCleanCount">0</strong></span>
                                        <span><i class="mdi mdi-circle text-danger me-1"></i> Riskli: <strong id="lblIssueCount">0</strong></span>
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

<script src="views/bordro/js/bordro-ai-analiz.js?v=<?= time() ?>"></script>
