<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Helper;
use App\Helper\Form;
use App\Service\Gate;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$isTurleri = $Tanimlamalar->getIsTurleri();
$isTuruAdlari = $Tanimlamalar->getIsTurleriAdlari();
$canManageIsTuruUcret = Gate::allows('is_turu_ucret_tanimla');

// İstatistikleri hesapla
$totalIsTurleri = count($isTurleri);
$ucretliSayisi = 0;
$ucretsizSayisi = 0;
$okumaSayisi = 0;
$kesmeSayisi = 0;
$sokmeDigerSayisi = 0;

foreach ($isTurleri as $item) {
    if (floatval($item->is_turu_ucret ?? 0) > 0 || floatval($item->aracli_personel_is_turu_ucret ?? 0) > 0 || floatval($item->okuma_is_turu_ucret ?? 0) > 0) {
        $ucretliSayisi++;
    } else {
        $ucretsizSayisi++;
    }

    $sekme = strtolower(trim($item->rapor_sekmesi ?? ''));
    if ($sekme === 'okuma') {
        $okumaSayisi++;
    } elseif ($sekme === 'kesme') {
        $kesmeSayisi++;
    } else {
        $sokmeDigerSayisi++;
    }
}

$maintitle = "Ana Sayfa";
$title = "İş Türü Tanımlamaları";
?>
<script>try { document.documentElement.classList.toggle('isturu-summary-hidden', localStorage.getItem('isturu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.isturu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık ve Aksiyon Araç Çubuğu (Yeni Sayfa Standardı) -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-briefcase fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">İş Türü Tanımlamaları</h4>
                <p class="text-muted mb-0 font-size-12">Saha iş emirleri, birim fiyatlar ve departman ücret tarifeleri</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- 1. Yeni İş Türü Ekle Butonu -->
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="actionEkle" data-bs-toggle="modal" data-bs-target="#actionModal">
                <i class="bx bx-plus font-size-16"></i> Yeni İş Türü Ekle
            </button>

            <!-- 2. İşlemler Dropdown -->
            <div class="dropdown d-inline-block">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-cog font-size-16 text-primary"></i> İşlemler
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <button type="button" class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#excelModal">
                        <i class="bx bx-upload me-2 font-size-16 text-success"></i> Excel'den Yükle
                    </button>
                    <a class="dropdown-item d-flex align-items-center" href="views/tanimlamalar/is-turu-excel-sablon.php">
                        <i class="bx bx-download me-2 font-size-16 text-info"></i> Excel'e Aktar
                    </a>
                    <div class="dropdown-divider"></div>
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
        <!-- Kart 1: TOPLAM İŞ TÜRÜ -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">TOPLAM İŞ TÜRÜ</span>
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-briefcase"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_isturu"><?php echo $totalIsTurleri; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_ucret">Ücretli: <?php echo $ucretliSayisi; ?> | Ücretsiz: <?php echo $ucretsizSayisi; ?></span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-rapor="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: ENDEKS OKUMA -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">ENDEKS OKUMA</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-book-open"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_okuma_sayi"><?php echo $okumaSayisi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-info fw-semibold">Endeks Okuma İşlemleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-rapor="okuma">
                            <i class="bx bx-check-circle"></i> Okuma
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: KESME / AÇMA -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">KESME / AÇMA</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-cut"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_kesme_sayi"><?php echo $kesmeSayisi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-warning fw-semibold">Kesme/Açma İşlemleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-rapor="kesme">
                            <i class="bx bx-check-circle"></i> Kesme
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: SÖKME / DİĞER İŞLEMLER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SÖKME / DİĞER</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-wrench"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat_sokme_sayi"><?php echo $sokmeDigerSayisi; ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-success fw-semibold">Sayaç & Kontrol İşlemleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-rapor="sokme">
                            <i class="bx bx-layer"></i> Sökme/Diğer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Standart DataTables Liste Kartı -->
    <div class="card summary-kpi-card mb-3" id="isTuruListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">İş Türleri Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Birim fiyatlar, araçlı/okuma personeli tarifeleri ve rapor kategorileri</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <a href="views/tanimlamalar/is-turu-excel-sablon.php" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel Şablonu İndir">
                    <i class="bx bx-download font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel İndir</span>
                </a>
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
                            <th data-filter="string">İŞ TÜRÜ</th>
                            <th data-filter="string">İŞ EMRİ SONUCU</th>
                            <th data-filter="number" class="text-end">İŞ TÜRÜ ÜCRETİ</th>
                            <th data-filter="number" class="text-end">ARAÇLI PERS. ÜCRETİ</th>
                            <th data-filter="number" class="text-end">OKUMA PERS. ÜCRETİ</th>
                            <th data-filter="select" class="text-center">RAPOR SEKMESİ</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="none" class="text-center" style="width: 120px;" data-orderable="false">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ($isTurleri as $isTuru) {
                            $i++;
                            $enc_id = Security::encrypt($isTuru->id);
                            $sekmeRaw = strtolower(trim($isTuru->rapor_sekmesi ?? ''));
                        ?>
                            <tr id="row_<?php echo $isTuru->id; ?>">
                                <td class="text-center text-muted fw-semibold font-size-12">
                                    <?php echo $isTuru->id; ?>
                                </td>
                                <td class="fw-bold text-dark font-size-13">
                                    <a href="javascript:void(0);" class="duzenle text-dark text-decoration-none" data-id="<?php echo $enc_id; ?>" title="Düzenlemek için tıklayın">
                                        <i class="bx bx-briefcase text-primary me-1"></i><?php echo htmlspecialchars($isTuru->tur_adi, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td class="font-size-12 fw-medium">
                                    <?php echo htmlspecialchars($isTuru->is_emri_sonucu ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="text-end font-size-13 fw-semibold text-dark">
                                    <?php echo Helper::formattedMoney($isTuru->is_turu_ucret ?? 0); ?>
                                </td>
                                <td class="text-end font-size-13 fw-semibold text-dark">
                                    <?php echo Helper::formattedMoney($isTuru->aracli_personel_is_turu_ucret ?? 0); ?>
                                </td>
                                <td class="text-end font-size-13 fw-semibold text-dark">
                                    <?php echo Helper::formattedMoney($isTuru->okuma_is_turu_ucret ?? 0); ?>
                                </td>
                                <td class="text-center text-nowrap">
                                    <?php if ($sekmeRaw === 'okuma'): ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Endeks Okuma</span>
                                    <?php elseif ($sekmeRaw === 'kesme'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kesme/Açma</span>
                                    <?php elseif ($sekmeRaw === 'sokme_takma'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Sökme Takma</span>
                                    <?php elseif ($sekmeRaw === 'muhurleme'): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Mühürleme</span>
                                    <?php elseif ($sekmeRaw === 'kacakkontrol'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kaçak Kontrol</span>
                                    <?php elseif (!empty($isTuru->rapor_sekmesi)): ?>
                                        <span class="badge bg-light text-dark border rounded-pill px-2 py-1 font-size-11 fw-semibold text-capitalize"><?php echo htmlspecialchars($isTuru->rapor_sekmesi, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted font-size-12 fst-italic">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted font-size-12">
                                    <?php echo htmlspecialchars($isTuru->aciklama ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="text-center">
                                    <div class="action-btn-group">
                                        <?php if ($canManageIsTuruUcret): ?>
                                            <button type="button" class="btn btn-sm btn-subtle-info table-action-btn ucret-gecmisi"
                                                data-id="<?php echo $enc_id; ?>"
                                                data-name="<?php echo htmlspecialchars($isTuru->tur_adi, ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Ücret Geçmişi">
                                                <i class="bx bx-history font-size-15"></i>
                                            </button>
                                        <?php endif; ?>
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

.btn-subtle-info {
    background-color: #f0f9ff;
    color: #0284c7;
    border: 1px solid #bae6fd;
    transition: all 0.18s ease;
}
.btn-subtle-info:hover, .btn-subtle-info:focus, .btn-subtle-info.active {
    background-color: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    box-shadow: 0 2px 5px rgba(2, 132, 199, 0.25);
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

<!-- İş Türü Ekle / Düzenle Modal -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-briefcase font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="actionModalLabel">İş Türü İşlemleri</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="actionForm">
                    <input type="hidden" name="is_turu_id" id="is_turu_id" class="form-control" value="0">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormSelect2(
                                    "is_turu",
                                    $isTuruAdlari,
                                    "",
                                    "İş Türü",
                                    "briefcase",
                                    "id",
                                    "",
                                    "form-select select2",
                                    false,
                                    'width:100%',
                                    'data-placeholder="İş Türü Seçiniz veya Yazınız"'
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "is_emri_sonucu",
                                    "",
                                    "İş Emri Sonucu giriniz!",
                                    "İş Emri Sonucu",
                                    "check-circle",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "is_turu_ucret",
                                    "",
                                    "İş Türü Ücreti giriniz!",
                                    "İş Türü Ücreti",
                                    "dollar-sign",
                                    "form-control money"
                                ); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "aracli_personel_is_turu_ucret",
                                    "",
                                    "Araçlı Personel Ücreti giriniz!",
                                    "Araçlı Pers. Ücreti",
                                    "truck",
                                    "form-control money"
                                ); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "okuma_is_turu_ucret",
                                    "",
                                    "Okuma Departmanı Ücreti giriniz!",
                                    "Okuma Dept. Ücreti",
                                    "book-open",
                                    "form-control money"
                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "ucret_gecerlilik_baslangic",
                                    date('d.m.Y'),
                                    "Ücret geçerlilik başlangıç tarihi seçiniz!",
                                    "Ücret Geçerlilik Başlangıç",
                                    "calendar",
                                    "form-control flatpickr"
                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php
                            $raporSekmeleri = [
                                '' => 'Seçiniz',
                                'okuma' => 'Endeks Okuma',
                                'kesme' => 'Kesme/Açma İşlm.',
                                'sokme_takma' => 'Sayaç Sökme Takma',
                                'muhurleme' => 'Mühürleme',
                                'kacakkontrol' => 'Kaçak Kontrol'
                            ];
                            echo Form::FormSelect2(
                                "rapor_sekmesi",
                                $raporSekmeleri,
                                "",
                                "Rapor Sekmesi",
                                "layers",
                                "key",
                                "",
                                "form-control select2"
                            );
                            ?>
                        </div>
                    </div>

                    <div class="row mb-0">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
                                    "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "edit"
                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                <button type="button" class="btn btn-subtle-secondary top-action-btn shadow-xs" data-bs-dismiss="modal">
                    <i class="bx bx-x font-size-16"></i> Kapat
                </button>
                <button type="button" id="actionKaydet" class="btn btn-primary top-action-btn text-white shadow-sm">
                    <i class="bx bx-save font-size-16"></i> Kaydet
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Ücret Geçmişi Modal -->
<div class="modal fade" id="ucretGecmisModal" tabindex="-1" aria-labelledby="ucretGecmisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-info-subtle text-info rounded-3 border border-info-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-history font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="ucretGecmisModalLabel">İş Türü Ücret Geçmişi</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="ucretGecmisYetki" value="<?php echo $canManageIsTuruUcret ? 1 : 0; ?>">
                <input type="hidden" id="gecmis_is_turu_id" value="">

                <?php if ($canManageIsTuruUcret): ?>
                    <div class="row g-2 mb-3 border rounded-3 p-3 bg-light align-items-center">
                        <input type="hidden" id="gecmis_id" value="0">
                        <div class="col-md-2">
                            <?php echo Form::FormFloatInput(
                                "text",
                                "gecmis_baslangic",
                                date('d.m.Y'),
                                "Başlangıç",
                                "Başlangıç",
                                "calendar",
                                "form-control flatpickr"
                            ); ?>
                        </div>
                        <div class="col-md-2">
                            <?php echo Form::FormFloatInput(
                                "text",
                                "gecmis_bitis",
                                "",
                                "Bitiş (Opsiyonel)",
                                "Bitiş",
                                "calendar",
                                "form-control flatpickr"
                            ); ?>
                        </div>
                        <div class="col-md-2">
                            <?php echo Form::FormFloatInput(
                                "text",
                                "gecmis_ucret",
                                "",
                                "0,00 ₺",
                                "İş Türü Ücreti",
                                "dollar-sign",
                                "form-control money"
                            ); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo Form::FormFloatInput(
                                "text",
                                "gecmis_aracli_ucret",
                                "",
                                "0,00 ₺",
                                "Araçlı Pers. Ücreti",
                                "truck",
                                "form-control money"
                            ); ?>
                        </div>
                        <div class="col-md-3">
                            <?php echo Form::FormFloatInput(
                                "text",
                                "gecmis_okuma_ucret",
                                "",
                                "0,00 ₺",
                                "Okuma Dept. Ücreti",
                                "book-open",
                                "form-control money"
                            ); ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-subtle-secondary top-action-btn shadow-xs" id="ucretGecmisTemizle">
                                <i class="bx bx-rotate-left font-size-15"></i> Temizle
                            </button>
                            <button type="button" class="btn btn-sm btn-primary top-action-btn text-white shadow-sm" id="ucretGecmisKaydet">
                                <i class="bx bx-save font-size-15"></i> Ücret Kaydet
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning border-0 rounded-3 mb-3 d-flex align-items-center gap-2">
                        <i class="bx bx-error font-size-18"></i>
                        <span>Bu alanda işlem yapmak için <strong>is_turu_ucret_tanimla</strong> yetkisi gereklidir.</span>
                    </div>
                <?php endif; ?>

                <div class="table-responsive border rounded-3">
                    <table class="table table-bordered table-hover nowrap align-middle mb-0" id="ucretGecmisTable">
                        <thead class="table-light">
                            <tr>
                                <th class="font-size-11 fw-bold text-dark text-center" style="width: 140px;">BAŞLANGIÇ</th>
                                <th class="font-size-11 fw-bold text-dark text-center" style="width: 140px;">BİTİŞ</th>
                                <th class="font-size-11 fw-bold text-dark text-end">İŞ TÜRÜ ÜCRETİ</th>
                                <th class="font-size-11 fw-bold text-dark text-end">ARAÇLI PERS. ÜCRETİ</th>
                                <th class="font-size-11 fw-bold text-dark text-end">OKUMA DEPT. ÜCRETİ</th>
                                <?php if ($canManageIsTuruUcret): ?>
                                    <th class="font-size-11 fw-bold text-dark text-center" style="width: 80px;">İŞLEM</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody></tbody>
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

<!-- Excel Yükle Modal -->
<div class="modal fade" id="excelModal" tabindex="-1" aria-labelledby="excelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-light px-4 py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-success-subtle text-success rounded-3 border border-success-subtle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bx bx-spreadsheet font-size-18"></i>
                    </div>
                    <h5 class="modal-title font-size-15 fw-bold text-dark mb-0" id="excelModalLabel">Excel'den İş Türlerini Güncelle</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="formExcelYukle" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="alert alert-success border-0 rounded-3 mb-3 bg-success-subtle text-success p-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bx bx-download fs-4 text-success flex-shrink-0 mt-0.5"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold text-success font-size-13">Mevcut İş Türlerini İndirin</h6>
                                <p class="mb-2 font-size-12 text-muted">
                                    Dosyada değişikliklerinizi yapın; ID sütununu değiştirmeden aynı dosyayı tekrar yükleyin.
                                </p>
                                <a href="views/tanimlamalar/is-turu-excel-sablon.php" class="btn btn-sm btn-success top-action-btn text-white shadow-xs">
                                    <i class="bx bx-download font-size-15"></i> Şablonu İndir
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="excelFile" class="form-label font-size-13 fw-semibold text-dark">Excel Dosyası (.xlsx, .xls) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="excelFile" name="excel_file" accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-2.5 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-subtle-secondary top-action-btn shadow-xs" data-bs-dismiss="modal">
                        <i class="bx bx-x font-size-16"></i> İptal
                    </button>
                    <button type="submit" class="btn btn-success top-action-btn text-white shadow-sm">
                        <i class="bx bx-upload font-size-16"></i> Yükle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo \App\Helper\Helper::assetVersion('views/tanimlamalar/js/is-turu.js'); ?>"></script>
