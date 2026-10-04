<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Service\Gate;
use App\Model\TanimlamalarModel;
use App\Model\SettingsModel;

$Tanimlamalar = new TanimlamalarModel();
$Settings = new SettingsModel();

$ekipler = $Tanimlamalar->getEkipKodlari();
$bolgeler = $Tanimlamalar->getEkipBolgeleri();

// Bölge kurallarını al
$bolgeKurallariJson = $Settings->getSettings('ekip_kodu_bolge_kurallari') ?? '{}';
$bolgeKurallari = json_decode($bolgeKurallariJson, true) ?: [];

// Admin yetkisi kontrolü
$canManageRules = Gate::allows('ekip_kodu_kurallari');

// İstatistikleri hesapla
$totalEkipler = count($ekipler);
$doluEkipler = 0;
$bostaEkipler = 0;
$cokluEkipler = 0;
$bolgeSet = [];

foreach ($ekipler as $ekip) {
    if ($ekip->kullanim_sayisi > 0) {
        $doluEkipler++;
    } else {
        $bostaEkipler++;
    }
    if (!empty($ekip->birden_fazla_personel_kullanabilir) && $ekip->birden_fazla_personel_kullanabilir == 1) {
        $cokluEkipler++;
    }
    if (!empty($ekip->ekip_bolge)) {
        $bolgeSet[$ekip->ekip_bolge] = true;
    }
}
$toplamBolgeSayisi = count($bolgeSet);

$maintitle = "Ana Sayfa";
$title = "Ekip Kodu Tanımlamaları";
?>
<script>try { document.documentElement.classList.toggle('ekipkodu-summary-hidden', localStorage.getItem('ekipkodu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.ekipkodu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Sayfa Standardı) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-id-card fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Ekip Kodları</h4>
                <p class="text-muted mb-0 font-size-12">Saha ekipleri, bölge dağılımları ve personel zimmet durumları</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni Ekip Kodu Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="actionEkle" data-bs-toggle="modal" data-bs-target="#actionModal">
                <i class="bx bx-plus font-size-16"></i> Yeni Ekip Kodu
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <?php if ($canManageRules): ?>
                        <button type="button" class="dropdown-item d-flex align-items-center" id="btnOpenBolgeKurallari">
                            <i class="bx bx-shield-quarter me-2 font-size-16 text-warning"></i> Bölge Kuralları
                        </button>
                        <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownPrint">
                        <i class="bx bx-printer me-2 font-size-16 text-secondary"></i> Tabloyu Yazdır
                    </button>
                    <button type="button" class="dropdown-item d-flex align-items-center" id="btnDropdownRefresh">
                        <i class="bx bx-refresh me-2 font-size-16 text-primary"></i> Listeyi Yenile
                    </button>
                </div>
            </div>

            <!-- 3. Özet Kartları Açma/Kapama Butonu -->
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <!-- 2. 4 Adet Minimal Özet KPI Kartı -->
    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <!-- Kart 1: TOPLAM EKİP KODU -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM EKİP KODU</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-id-card"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_ekip"><?php echo $totalEkipler; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_durum">Dolu: <?php echo $doluEkipler; ?> | Boşta: <?php echo $bostaEkipler; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-status="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: DOLU EKİPLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">DOLU EKİPLER</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-user-check"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_dolu_ekip"><?php echo $doluEkipler; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-danger fw-semibold" id="stat_sub_dolu_sayi">Personel Atanmış</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="dolu">
                            <i class="bx bx-minus-circle"></i> Dolu
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: BOŞTA EKİPLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BOŞTA EKİPLER</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-user-plus"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_bosta_ekip"><?php echo $bostaEkipler; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold" id="stat_sub_bosta_sayi">Atamaya Hazır</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-status="bosta">
                            <i class="bx bx-check-circle"></i> Boşta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: TOPLAM BÖLGE / ÇOKLU KULLANIM -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM BÖLGE</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-map-pin"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_toplam_bolge"><?php echo $toplamBolgeSayisi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted" id="stat_sub_coklu_sayi">Çoklu: <?php echo $cokluEkipler; ?> Ekip</span>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                            <?php echo $toplamBolgeSayisi; ?> Bölge
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Liste Kartı -->
    <div class="card summary-kpi-card mb-3" id="ekipKoduListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Ekip Kodları Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Bölge bazlı canlı arama, sütun filtreleme ve durum takibi</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-primary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderRefresh" title="Listeyi Yenile">
                    <i class="bx bx-refresh font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yenile</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="actionTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="select" class="text-center">BÖLGE</th>
                            <th data-filter="string">EKİP KODU</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="string">ZİMMETLİ PERSONEL</th>
                            <th data-filter="select" class="text-center" style="width: 140px;">DURUM</th>
                            <th data-filter="none" class="text-center" style="width: 120px;" data-orderable="false">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ($ekipler as $ekip) {
                            $i++;
                            $enc_id = Security::encrypt($ekip->id);
                            $isDolu = ($ekip->kullanim_sayisi > 0);
                            $isCoklu = ($ekip->birden_fazla_personel_kullanabilir == 1);
                        ?>
                            <tr id="row_<?php echo $ekip->id; ?>">
                                <td class="text-center text-muted fw-semibold font-size-12">
                                    <?php echo $i; ?>
                                </td>
                                <td class="text-center font-size-12 fw-medium">
                                    <?php echo htmlspecialchars($ekip->ekip_bolge ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="fw-bold text-dark font-size-13">
                                    <a href="javascript:void(0);" class="text-dark duzenle text-decoration-none" data-id="<?php echo $enc_id; ?>" title="Düzenlemek için tıklayın">
                                        <i class="bx bx-id-card text-primary me-1"></i><?php echo htmlspecialchars($ekip->tur_adi, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td class="text-muted font-size-12">
                                    <?php echo htmlspecialchars($ekip->aciklama ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($ekip->personel_isimleri)): ?>
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="bx bx-user text-primary font-size-14"></i>
                                            <span class="text-dark font-size-12 fw-medium"><?php echo htmlspecialchars($ekip->personel_isimleri, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted font-size-12 fst-italic">Atama yok</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if ($isDolu): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                            <i class="bx bx-check-circle me-1"></i>Dolu
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">
                                            <i class="bx bx-check me-1"></i>Boşta
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($isCoklu): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold ms-1" title="Birden fazla personel atanabilir">
                                            <i class="bx bx-group me-1"></i>Çoklu
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="action-btn-group">
                                        <button type="button" class="btn btn-sm btn-subtle-primary table-action-btn gecmis" data-id="<?php echo $enc_id; ?>" title="Atama Geçmişi">
                                            <i class="bx bx-history font-size-15"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-subtle-warning table-action-btn duzenle" data-id="<?php echo $enc_id; ?>" title="Düzenle">
                                            <i class="bx bx-edit font-size-15"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn sil" data-id="<?php echo $enc_id; ?>" title="Sil">
                                            <i class="bx bx-trash font-size-15"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Tablo Tipografi ve Okunabilirlik İyileştirmeleri */
#actionTable {
    font-size: 13px !important;
}
#actionTable thead th {
    font-size: 11.5px !important;
    font-weight: 700 !important;
    color: #334155 !important;
    letter-spacing: 0.3px;
    background-color: #f8fafc !important;
    vertical-align: middle !important;
}
#actionTable tbody td {
    padding: 8px 12px !important;
    vertical-align: middle !important;
    color: #0f172a !important;
}

/* Muhasebe ve Tablo Satır Butonları */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.table-action-btn:hover {
    transform: translateY(-1px);
}
.action-btn-group {
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

/* Modern Kart ve KPI Stilleri */
.summary-kpi-card {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.summary-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05) !important;
}
.summary-kpi-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.summary-kpi-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    line-height: 1;
}
.summary-kpi-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.summary-kpi-subtext {
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
}
.summary-pill-btn {
    font-size: 11px !important;
    height: 24px !important;
    line-height: 1 !important;
    padding: 0 10px !important;
    font-weight: 600 !important;
    border-radius: 20px !important;
    transition: all 0.2s ease;
}

/* Modern Subtle Renkli Butonlar */
.btn-subtle-primary {
    background-color: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    transition: all 0.18s ease;
}
.btn-subtle-primary:hover, .btn-subtle-primary:focus, .btn-subtle-primary.active {
    background-color: #2563eb !important;
    color: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.25);
}

.btn-subtle-success {
    background-color: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    transition: all 0.18s ease;
}
.btn-subtle-success:hover, .btn-subtle-success:focus, .btn-subtle-success.active {
    background-color: #16a34a !important;
    color: #ffffff !important;
    border-color: #16a34a !important;
    box-shadow: 0 2px 5px rgba(22, 163, 74, 0.25);
}

.btn-subtle-danger {
    background-color: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    transition: all 0.18s ease;
}
.btn-subtle-danger:hover, .btn-subtle-danger:focus, .btn-subtle-danger.active {
    background-color: #dc2626 !important;
    color: #ffffff !important;
    border-color: #dc2626 !important;
    box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25);
}

.btn-subtle-warning {
    background-color: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
    transition: all 0.18s ease;
}
.btn-subtle-warning:hover, .btn-subtle-warning:focus, .btn-subtle-warning.active {
    background-color: #d97706 !important;
    color: #ffffff !important;
    border-color: #d97706 !important;
    box-shadow: 0 2px 5px rgba(217, 119, 6, 0.25);
}

.btn-subtle-secondary {
    background-color: #f8fafc;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.18s ease;
}
.btn-subtle-secondary:hover, .btn-subtle-secondary:focus {
    background-color: #475569;
    color: #ffffff !important;
    border-color: #475569;
    box-shadow: 0 2px 5px rgba(71, 85, 105, 0.25);
}

.top-action-btn {
    font-size: 13px;
    padding: 7px 14px;
    border-radius: 8px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.top-icon-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.15rem;
    color: #475569;
    border: 1px solid #cbd5e1;
    transition: all 0.2s ease;
}
.top-icon-btn:hover {
    background-color: #f8fafc;
    border-color: #94a3b8;
    color: #1e293b;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
}
</style>

<!-- Ekip Kodu Ekle / Düzenle Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog <?php echo $canManageRules ? 'modal-lg' : ''; ?> modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-id-card font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="actionModalLabel">Ekip Kodu İşlemleri</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($canManageRules): ?>
                    <!-- Sekmeler -->
                    <ul class="nav nav-pills nav-justified mb-3 p-1 bg-light rounded-3" id="ekipKoduTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-3 py-2 font-size-13 fw-semibold" id="ekipKodu-tab" data-bs-toggle="tab"
                                data-bs-target="#ekipKoduContent" type="button" role="tab" aria-controls="ekipKoduContent"
                                aria-selected="true">
                                <i class="bx bx-briefcase me-1"></i> Ekip Kodu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-3 py-2 font-size-13 fw-semibold" id="bolgeKurallari-tab" data-bs-toggle="tab"
                                data-bs-target="#bolgeKurallariContent" type="button" role="tab"
                                aria-controls="bolgeKurallariContent" aria-selected="false">
                                <i class="bx bx-shield-quarter me-1"></i> Bölge Kuralları
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="ekipKoduTabContent">
                        <!-- Ekip Kodu Sekmesi -->
                        <div class="tab-pane fade show active" id="ekipKoduContent" role="tabpanel" aria-labelledby="ekipKodu-tab">
                <?php endif; ?>

                        <form id="actionForm">
                            <input type="hidden" name="ekip_id" id="ekip_id" class="form-control" value="0">

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <?php echo
                                        Form::FormSelect2(
                                            "ekip_bolge",
                                            $bolgeler,
                                            "",
                                            "Ekip Bölge",
                                            "map-pin",
                                            "id",
                                            "",
                                            "form-select select2",
                                            false,
                                            'width:100%',
                                            'data-placeholder="Bölge Seçiniz veya Yazınız"'
                                        ); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <?php echo
                                        Form::FormFloatInput(
                                            "text",
                                            "ekip_kodu",
                                            "",
                                            "Ekip Kodu giriniz (örn: ER-SAN ELEKTRİK EKİP-10)",
                                            "Ekip Kodu",
                                            "briefcase",
                                            "form-control"
                                        ); ?>
                                    <div class="mt-1 font-size-12" id="ekipKoduInfo"></div>
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
                                            "edit",
                                        ); ?>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-12">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <div class="form-check form-switch form-switch-md mb-0 d-flex align-items-center gap-2">
                                            <input class="form-check-input ms-0" type="checkbox" name="birden_fazla_personel_kullanabilir" id="birden_fazla_personel_kullanabilir" value="1">
                                            <label class="form-check-label fw-semibold text-dark font-size-13 mb-0" for="birden_fazla_personel_kullanabilir">
                                                Bu ekip kodu birden fazla personele atanabilir (Çoklu Kullanım)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                <?php if ($canManageRules): ?>
                        </div>

                        <!-- Bölge Kuralları Sekmesi -->
                        <div class="tab-pane fade" id="bolgeKurallariContent" role="tabpanel" aria-labelledby="bolgeKurallari-tab">
                            <div class="alert alert-info border-0 shadow-xs fade show d-flex align-items-start rounded-3 mb-3" role="alert">
                                <i class="bx bx-info-circle me-2 flex-shrink-0 font-size-18 mt-0.5 text-info"></i>
                                <div class="font-size-12">
                                    <strong class="font-size-13">Bölge Kodu Aralık Kuralları</strong><br>
                                    Bölge kuralları tanımlayarak ekip numaralarının belirli sayı aralıklarında olmasını zorunlu kılabilirsiniz.
                                    <br><span class="text-muted">Örnek: "ANDIRIN" bölgesi için Ekip-10 ile Ekip-20 arasında olması gerekiyorsa, Min: 10, Maks: 20 girin.</span>
                                </div>
                            </div>

                            <form id="bolgeKurallariForm">
                                <div class="table-responsive border rounded-3">
                                    <table class="table table-bordered table-sm align-middle mb-0" id="bolgeKurallariTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40%; font-size: 11.5px;" class="fw-bold text-dark">BÖLGE</th>
                                                <th style="width: 25%; font-size: 11.5px;" class="fw-bold text-dark text-center">MİN EKİP NO</th>
                                                <th style="width: 25%; font-size: 11.5px;" class="fw-bold text-dark text-center">MAKS EKİP NO</th>
                                                <th style="width: 10%; font-size: 11.5px;" class="fw-bold text-dark text-center">İŞLEM</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bolgeKurallariBody">
                                            <?php if (!empty($bolgeKurallari)): ?>
                                                <?php foreach ($bolgeKurallari as $bolge => $kural): ?>
                                                    <tr data-bolge="<?php echo htmlspecialchars($bolge); ?>">
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm bolge-input bg-light fw-medium font-size-12"
                                                                value="<?php echo htmlspecialchars($bolge); ?>" readonly>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm min-input text-center font-size-12"
                                                                value="<?php echo intval($kural['min'] ?? 0); ?>" min="0">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm max-input text-center font-size-12"
                                                                value="<?php echo intval($kural['max'] ?? 999); ?>" min="0">
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn kural-sil" title="Kuralı Sil">
                                                                <i class="bx bx-trash font-size-14"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Yeni Kural Ekleme -->
                                <div class="card bg-light border mt-3 rounded-3 mb-0">
                                    <div class="card-body p-3">
                                        <h6 class="card-title font-size-13 fw-bold text-dark mb-2 d-flex align-items-center gap-1">
                                            <i class="bx bx-plus-circle text-success"></i> Yeni Bölge Kuralı Ekle
                                        </h6>
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-4">
                                                <?php
                                                $kuralOlmayanBolgeler = array_filter($bolgeler, function ($b) use ($bolgeKurallari) {
                                                    return !isset($bolgeKurallari[$b]);
                                                });
                                                echo Form::FormSelect2(
                                                    "yeniBolge",
                                                    $kuralOlmayanBolgeler,
                                                    "",
                                                    "Bölge",
                                                    "map-pin",
                                                    "val",
                                                    "",
                                                    "form-select",
                                                    false,
                                                    'width:100%',
                                                    'data-placeholder="Bölge Seçin"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php echo Form::FormFloatInput(
                                                    "number",
                                                    "yeniMin",
                                                    "",
                                                    "Ör: 10",
                                                    "Min No",
                                                    "hash",
                                                    "form-control",
                                                    false,
                                                    null,
                                                    "off",
                                                    false,
                                                    'min="0"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php echo Form::FormFloatInput(
                                                    "number",
                                                    "yeniMax",
                                                    "",
                                                    "Ör: 20",
                                                    "Maks No",
                                                    "hash",
                                                    "form-control",
                                                    false,
                                                    null,
                                                    "off",
                                                    false,
                                                    'min="0"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-success top-action-btn text-white w-100 justify-content-center shadow-xs" id="kuralEkle">
                                                    <i class="bx bx-plus font-size-15"></i> Ekle
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-subtle-secondary top-action-btn shadow-xs" data-bs-dismiss="modal">
                    <i class="bx bx-x font-size-16"></i> Kapat
                </button>

                <div class="d-flex gap-2">
                    <?php if ($canManageRules): ?>
                        <button type="button" id="kuralKaydet" class="btn btn-success top-action-btn text-white shadow-sm" style="display: none;">
                            <i class="bx bx-save font-size-16"></i> Kuralları Kaydet
                        </button>
                    <?php endif; ?>

                    <button type="button" id="actionKaydet" class="btn btn-primary top-action-btn text-white shadow-sm">
                        <i class="bx bx-save font-size-16"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Geçmiş Modal -->
<div class="modal fade" id="gecmisModal" tabindex="-1" aria-labelledby="gecmisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-history font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="gecmisModalLabel">Ekip Kodu Atama Geçmişi</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive border rounded-3">
                    <table class="table table-bordered table-hover nowrap align-middle mb-0" id="gecmisTable">
                        <thead class="table-light">
                            <tr>
                                <th class="font-size-11 fw-bold text-dark">PERSONEL</th>
                                <th class="font-size-11 fw-bold text-dark text-center" style="width: 140px;">BAŞLANGIÇ TARİHİ</th>
                                <th class="font-size-11 fw-bold text-dark text-center" style="width: 140px;">BİTİŞ TARİHİ</th>
                                <th class="font-size-11 fw-bold text-dark text-center" style="width: 120px;">DURUM</th>
                            </tr>
                        </thead>
                        <tbody id="gecmisBody">
                            <!-- Veriler JS ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-2.5 border-top">
                <button type="button" class="btn btn-subtle-secondary top-action-btn shadow-xs" data-bs-dismiss="modal">
                    <i class="bx bx-x font-size-16"></i> Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo \App\Helper\Helper::assetVersion('views/tanimlamalar/js/ekip-kodu.js'); ?>"></script>