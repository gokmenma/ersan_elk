<?php
/**
 * Nöbet Onay İşlemleri Sayfası
 * Planlanan nöbetlerin yönetici tarafından onaylanmasını sağlar.
 */

use App\Helper\Form;
use App\Model\NobetModel;

$maintitle = 'Nöbet Yönetimi';
$title = 'Nöbet Onay İşlemleri';

$Nobet = new NobetModel();
$stats = $Nobet->getOnayStats($_SESSION['firma_id'] ?? null);

$aylar = [
    0 => 'Tüm Aylar',
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
    5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
    9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

$yillar = [];
for ($y = (int) date('Y'); $y >= 2024; $y--) {
    $yillar[$y] = (string) $y;
}

$nobetTipleri = [
    '' => 'Tüm Nöbet Tipleri',
    'hafta_ici' => 'Hafta İçi',
    'hafta_sonu' => 'Hafta Sonu',
    'resmi_tatil' => 'Resmi Tatil'
];
?>
<script>try { document.documentElement.classList.toggle('nobet-onay-summary-hidden', localStorage.getItem('nobet_onay_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.nobet-onay-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }

/* Checkbox Tasarımı */
.custom-checkbox-container {
    position: relative;
    cursor: pointer;
    user-select: none;
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.custom-checkbox-input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.custom-checkbox-label {
    position: absolute;
    top: 0;
    left: 0;
    height: 18px;
    width: 18px;
    background-color: #fff;
    border: 2px solid #cbd5e1;
    border-radius: 5px;
    transition: all 0.2s ease;
}
.custom-checkbox-input:checked ~ .custom-checkbox-label {
    background-color: #556ee6;
    border-color: #556ee6;
}
.custom-checkbox-label:after {
    content: "";
    position: absolute;
    display: none;
    left: 5px;
    top: 2px;
    width: 5px;
    height: 9px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.custom-checkbox-input:checked ~ .custom-checkbox-label:after {
    display: block;
}

/* Tablo Satır Vurgusu */
#onayTable tbody tr {
    transition: background-color .15s ease;
    cursor: pointer;
}
#onayTable tbody tr.selected {
    background-color: rgba(85, 110, 230, 0.08) !important;
}

/* Kişi ve Avatar */
.report-person { display: flex; align-items: center; gap: 9px; min-width: 150px; }
.report-person-avatar { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; background: #e8edff; color: #4962d8; font-size: 11px; font-weight: 700; }
.report-person-name { color: #27334a; font-weight: 600; }
[data-bs-theme="dark"] .report-person-name { color: #e9edf5; }

/* Subtle Butonlar */
.table-action-btn {
    width: 27px;
    height: 27px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 13px;
}
.btn-subtle-primary { background: rgba(85, 110, 230, .12); color: #556ee6; border: 1px solid transparent; }
.btn-subtle-primary:hover { background: #556ee6; color: #fff; }
.btn-subtle-success { background: rgba(52, 195, 143, .12); color: #34c38f; border: 1px solid transparent; }
.btn-subtle-success:hover { background: #34c38f; color: #fff; }
.btn-subtle-warning { background: rgba(241, 180, 76, .12); color: #f1b44c; border: 1px solid transparent; }
.btn-subtle-warning:hover { background: #f1b44c; color: #fff; }
.btn-subtle-danger { background: rgba(244, 106, 106, .12); color: #f46a6a; border: 1px solid transparent; }
.btn-subtle-danger:hover { background: #f46a6a; color: #fff; }
.btn-subtle-info { background: rgba(80, 165, 241, .12); color: #50a5f1; border: 1px solid transparent; }
.btn-subtle-info:hover { background: #50a5f1; color: #fff; }
.btn-subtle-secondary { background: rgba(116, 120, 141, .12); color: #74788d; border: 1px solid transparent; }
.btn-subtle-secondary:hover { background: #74788d; color: #fff; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <!-- 1. Üst Başlık Satırı ve Aksiyon Araç Çubuğu -->
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-check-shield fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Nöbet Onay İşlemleri</h4>
                <p class="text-muted mb-0 font-size-12">Planlanan personel nöbetlerinin onaylanması, filtrelenmesi ve toplu yönetimi</p>
            </div>
        </div>
        
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <!-- Seçilenleri Onayla Butonu -->
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white" id="btnBulkApproveTop">
                <i class="bx bx-check-double font-size-16"></i> Seçilenleri Onayla
            </button>

            <!-- Seçilenleri Sil Butonu -->
            <button type="button" class="btn btn-outline-danger bg-white top-action-btn shadow-sm" id="btnBulkDeleteTop">
                <i class="bx bx-trash font-size-16"></i> Seçilenleri Sil
            </button>

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
                        <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bx bx-hourglass"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1" id="stat_toplam_bekleyen"><?= (int) ($stats->total_bekleyen ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_toplam">Onay bekleyen toplam nöbet</span>
                        <button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-filter-type="all">
                            <i class="bx bx-layer"></i> Tümü
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 2: HAFTA SONU BEKLEYEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">HAFTA SONU</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-calendar-week"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat_hs_bekleyen"><?= (int) ($stats->hafta_sonu_bekleyen ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Hafta sonu nöbetleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="hafta_sonu">
                            <i class="bx bx-time"></i> H. Sonu
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 3: RESMİ TATİL BEKLEYEN -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">RESMİ TATİL</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-flag"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat_rt_bekleyen"><?= (int) ($stats->resmi_tatil_bekleyen ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Resmi tatil nöbetleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="resmi_tatil">
                            <i class="bx bx-info-circle"></i> R. Tatil
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kart 4: HAFTA İÇİ / DİĞER -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">HAFTA İÇİ</span>
                        <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                            <i class="bx bx-calendar"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-info" id="stat_hi_bekleyen"><?= (int) ($stats->hafta_ici_bekleyen ?? 0) ?></h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext text-muted">Hafta içi nöbet kayıtları</span>
                        <button type="button" class="btn btn-sm btn-subtle-info rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-filter-type="hafta_ici">
                            <i class="bx bx-check-circle"></i> H. İçi
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Filtre Kartı -->
    <div class="card summary-kpi-card mb-3">
        <div class="card-body p-3">
            <form id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <?= Form::FormSelect2('filter-ay', $aylar, 0, 'Dönem Ay', 'calendar', '', '', 'form-select select2') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <?= Form::FormSelect2('filter-yil', $yillar, date('Y'), 'Dönem Yıl', 'calendar', '', '', 'form-select select2') ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <?= Form::FormSelect2('filter-tip', $nobetTipleri, '', 'Nöbet Tipi', 'filter', '', '', 'form-select select2') ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. DataTables Liste Kartı -->
    <div class="card summary-kpi-card mb-3" id="nobetOnayListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-check font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Onay Bekleyen Nöbet Listesi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Dönem bazlı nöbet listesi, toplu onaylama ve silme işlemleri</p>
                </div>
            </div>

            <!-- Sağ Araç Çubuğu -->
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <!-- Seçim Sayacı Rozeti -->
                <div id="selection-status-badge" class="d-none">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1.5 font-size-12 fw-semibold">
                        <i class="bx bx-check-square me-1"></i> <span id="selected-count">0</span> Seçildi
                    </span>
                </div>

                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="onayTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th data-filter="none" style="width: 38px;" class="text-center">
                                <div class="custom-checkbox-container">
                                    <input type="checkbox" id="checkAll" class="custom-checkbox-input">
                                    <label class="custom-checkbox-label" for="checkAll"></label>
                                </div>
                            </th>
                            <th data-filter="string">Personel</th>
                            <th data-filter="select">Departman</th>
                            <th data-filter="date" style="width: 110px;">Nöbet Tarihi</th>
                            <th data-filter="select" style="width: 120px;">Nöbet Tipi</th>
                            <th data-filter="string" style="width: 120px;">Saat Aralığı</th>
                            <th data-filter="string">Açıklama</th>
                            <th data-filter="none" style="width: 90px;" class="text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="onay-tbody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Yükleniyor...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let onayTable = null;
    var SUMMARY_STORAGE_KEY = 'nobet_onay_summary_cards_state';

    // Select2 Başlatma
    if ($.fn.select2) {
        $('#filter-ay, #filter-yil, #filter-tip').select2({ width: '100%' });
    }

    // Özet Kartları Açma/Kapama Standardı
    function updateToggleIcon(isHidden) {
        var $btn = $('#btnToggleSummaryCards');
        var $icon = $btn.find('i');
        if (isHidden) {
            $icon.removeClass('bx-chevron-up').addClass('bx-chevron-down');
            $btn.attr('title', 'Özet Kartları Göster').attr('aria-expanded', 'false');
        } else {
            $icon.removeClass('bx-chevron-down').addClass('bx-chevron-up');
            $btn.attr('title', 'Özet Kartları Gizle').attr('aria-expanded', 'true');
        }
    }
    updateToggleIcon(document.documentElement.classList.contains('nobet-onay-summary-hidden'));

    $('#btnToggleSummaryCards').on('click', function() {
        var isHidden = document.documentElement.classList.toggle('nobet-onay-summary-hidden');
        try {
            localStorage.setItem(SUMMARY_STORAGE_KEY, isHidden ? 'hidden' : 'visible');
        } catch (e) {}
        updateToggleIcon(isHidden);
    });

    function escapeHtml(value) {
        return $('<div>').text(value == null || value === '' ? '-' : value).html();
    }

    function formatDateShort(dateStr) {
        if (!dateStr) return '-';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }
        return dateStr;
    }

    function formatNobetTipiBadge(tip) {
        if (tip === 'hafta_sonu') {
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-time me-1"></i>Hafta Sonu</span>';
        } else if (tip === 'resmi_tatil') {
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-flag me-1"></i>Resmi Tatil</span>';
        }
        return '<span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-calendar me-1"></i>Hafta İçi</span>';
    }

    function getNobetTipiLabel(tip) {
        if (tip === 'hafta_sonu') return 'Hafta Sonu';
        if (tip === 'resmi_tatil') return 'Resmi Tatil';
        return 'Hafta İçi';
    }

    // Tabloyu ve İstatistikleri Yükle
    window.loadOnayBekleyenNobetler = function () {
        const ay = $('#filter-ay').val() || 0;
        const yil = $('#filter-yil').val() || new Date().getFullYear();
        const tbody = $('#onay-tbody');
        
        $('#checkAll').prop('checked', false);
        updateSelectionStatus(0);
        
        if (onayTable) {
            onayTable.destroy();
            onayTable = null;
        }
        tbody.html('<tr><td colspan="8" class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Yükleniyor...</td></tr>');

        $.ajax({
            url: 'views/nobet/api.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'get-onay-bekleyen-nobetler', ay: ay, yil: yil },
            success: function(data) {
                if (data.success && data.data && data.data.length > 0) {
                    let html = '';
                    let hsCount = 0;
                    let rtCount = 0;
                    let hiCount = 0;

                    data.data.forEach(n => {
                        const nobetTipi = n.nobet_tipi || 'hafta_ici';
                        if (nobetTipi === 'hafta_sonu') hsCount++;
                        else if (nobetTipi === 'resmi_tatil') rtCount++;
                        else hiCount++;

                        const initials = (n.personel_adi || '?').trim().split(/\s+/).slice(0, 2).map(p => p.charAt(0)).join('').toLocaleUpperCase('tr-TR');
                        const saatAraligi = (n.baslangic_saati ? n.baslangic_saati.substring(0, 5) : '00:00') + ' - ' + (n.bitis_saati ? n.bitis_saati.substring(0, 5) : '00:00');

                        html += `<tr data-id="${n.id}" data-nobet-tipi="${nobetTipi}">
                            <td class="text-center" onclick="event.stopPropagation()">
                                <div class="custom-checkbox-container">
                                    <input type="checkbox" class="custom-checkbox-input nobet-check" value="${n.id}" id="chk_${n.id}">
                                    <label class="custom-checkbox-label" for="chk_${n.id}"></label>
                                </div>
                            </td>
                            <td>
                                <div class="report-person">
                                    <span class="report-person-avatar">${escapeHtml(initials)}</span>
                                    <span class="report-person-name">${escapeHtml(n.personel_adi || '-')}</span>
                                </div>
                            </td>
                            <td>${escapeHtml(n.departman || '-')}</td>
                            <td><span class="text-dark fw-semibold"><i class="bx bx-calendar text-muted me-1"></i>${formatDateShort(n.nobet_tarihi)}</span></td>
                            <td>${formatNobetTipiBadge(nobetTipi)}</td>
                            <td><span class="text-muted"><i class="bx bx-time text-muted me-1"></i>${saatAraligi}</span></td>
                            <td><span class="text-muted small" title="${escapeHtml(n.aciklama || '')}">${escapeHtml(n.aciklama || '-')}</span></td>
                            <td class="text-center" onclick="event.stopPropagation()">
                                <div class="action-btn-group d-flex align-items-center justify-content-center gap-1">
                                    <button type="button" class="btn btn-subtle-success table-action-btn" onclick="approveNobet('${n.id}')" title="Onayla">
                                        <i class="bx bx-check font-size-15"></i>
                                    </button>
                                    <button type="button" class="btn btn-subtle-danger table-action-btn" onclick="deleteNobet('${n.id}')" title="Sil">
                                        <i class="bx bx-trash font-size-14"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                    });

                    tbody.html(html);

                    // İstatistikleri Güncelle
                    if (data.stats) {
                        $('#stat_toplam_bekleyen').text(data.stats.total_bekleyen || 0);
                        $('#stat_hs_bekleyen').text(data.stats.hafta_sonu_bekleyen || 0);
                        $('#stat_rt_bekleyen').text(data.stats.resmi_tatil_bekleyen || 0);
                        $('#stat_hi_bekleyen').text(data.stats.hafta_ici_bekleyen || 0);
                    } else {
                        $('#stat_toplam_bekleyen').text(data.data.length);
                        $('#stat_hs_bekleyen').text(hsCount);
                        $('#stat_rt_bekleyen').text(rtCount);
                        $('#stat_hi_bekleyen').text(hiCount);
                    }
                    
                    // DataTable Başlatma
                    let options = typeof getDatatableOptions === 'function' ? getDatatableOptions() : {};
                    options.order = [[3, 'desc']];
                    options.columnDefs = [
                        { targets: [0, -1], orderable: false, searchable: false }
                    ];

                    if (typeof applyLengthStateSave === 'function') {
                        options = applyLengthStateSave(options);
                    }

                    onayTable = $('#onayTable').DataTable(options);

                    // Tip filtresi varsa uygula
                    applyTipFilter();

                } else {
                    tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">Onay bekleyen nöbet kaydı bulunamadı.</td></tr>');
                    $('#stat_toplam_bekleyen').text(0);
                    $('#stat_hs_bekleyen').text(0);
                    $('#stat_rt_bekleyen').text(0);
                    $('#stat_hi_bekleyen').text(0);
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="8" class="text-center text-danger py-4">Sunucu ile iletişim kurulurken bir hata oluştu.</td></tr>');
            }
        });
    };

    // Nöbet Tipi Filtrelemesi
    function applyTipFilter() {
        if (!onayTable) return;
        const tipVal = $('#filter-tip').val();
        
        if (tipVal === 'hafta_sonu') {
            onayTable.column(4).search('Hafta Sonu').draw();
        } else if (tipVal === 'resmi_tatil') {
            onayTable.column(4).search('Resmi Tatil').draw();
        } else if (tipVal === 'hafta_ici') {
            onayTable.column(4).search('Hafta İçi').draw();
        } else {
            onayTable.column(4).search('').draw();
        }
    }

    $('#filter-ay, #filter-yil').on('change', function() {
        loadOnayBekleyenNobetler();
    });

    $('#filter-tip').on('change', function() {
        const val = $(this).val() || 'all';
        $('.status-quick-filter[data-filter-type]').removeClass('active');
        $('.status-quick-filter[data-filter-type="' + val + '"]').addClass('active');
        applyTipFilter();
    });

    // KPI Hızlı Filtre Butonları
    $(document).on('click', '.status-quick-filter[data-filter-type]', function(e) {
        e.preventDefault();
        const type = $(this).data('filter-type');
        $('.status-quick-filter[data-filter-type]').removeClass('active');
        $(this).addClass('active');

        const selectVal = type === 'all' ? '' : type;
        $('#filter-tip').val(selectVal).trigger('change.select2');
        applyTipFilter();
    });

    // Satır Tıklama ile Seçim
    $('#onayTable tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('button, .custom-checkbox-container, a').length) return;
        
        const checkbox = $(this).find('.nobet-check');
        if (!checkbox.length) return;

        checkbox.prop('checked', !checkbox.prop('checked'));
        $(this).toggleClass('selected', checkbox.prop('checked'));
        
        if (onayTable) {
            const totalFiltered = onayTable.rows({ filter: 'applied' }).count();
            const checkedInFiltered = onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').length;
            $('#checkAll').prop('checked', totalFiltered === checkedInFiltered && totalFiltered > 0);
            updateSelectionStatus(checkedInFiltered);
        }
    });

    // Checkbox Değişimi
    $('#onayTable tbody').on('change', '.nobet-check', function() {
        $(this).closest('tr').toggleClass('selected', this.checked);
        
        if (onayTable) {
            const totalFiltered = onayTable.rows({ filter: 'applied' }).count();
            const checkedInFiltered = onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').length;
            $('#checkAll').prop('checked', totalFiltered === checkedInFiltered && totalFiltered > 0);
            updateSelectionStatus(checkedInFiltered);
        }
    });

    // Tümünü Seç / Kaldır
    $('#checkAll').on('change', function() {
        const isChecked = this.checked;
        if (!onayTable) return;

        onayTable.rows({ filter: 'applied' }).nodes().to$().each(function() {
            $(this).find('.nobet-check').prop('checked', isChecked);
            $(this).toggleClass('selected', isChecked);
        });

        const checkedInFiltered = isChecked ? onayTable.rows({ filter: 'applied' }).count() : 0;
        updateSelectionStatus(checkedInFiltered);
    });

    function updateSelectionStatus(count) {
        const badge = $('#selection-status-badge');
        const countSpan = $('#selected-count');
        
        if (count > 0) {
            countSpan.text(count);
            badge.removeClass('d-none');
        } else {
            badge.addClass('d-none');
        }
    }

    // Tekil Onaylama
    window.approveNobet = function(id) {
        Swal.fire({
            title: 'Nöbeti Onayla',
            text: 'Bu nöbeti onaylamak istediğinize emin misiniz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-check me-1"></i> Evet, Onayla',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#34c38f',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('onayla-nobet', { nobet_id: id });
            }
        });
    };

    // Tekil Silme
    window.deleteNobet = function(id) {
        Swal.fire({
            title: 'Nöbeti Sil',
            text: 'Bu nöbeti silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#f46a6a',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('delete-nobet', { nobet_id: id });
            }
        });
    };

    // Toplu Onaylama
    window.bulkApprove = function() {
        const ids = [];
        if (!onayTable) return;

        onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire('Uyarı', 'Lütfen onaylamak için en az bir nöbet seçiniz.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Toplu Nöbet Onayı',
            text: ids.length + ' adet seçili nöbeti onaylamak istediğinize emin misiniz?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-check-double me-1"></i> Evet, Onayla',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#34c38f',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('bulk-onayla-nobet', { ids: ids });
            }
        });
    };

    // Toplu Silme
    window.bulkDelete = function() {
        const ids = [];
        if (!onayTable) return;

        onayTable.rows({ filter: 'applied' }).nodes().to$().find('.nobet-check:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            Swal.fire('Uyarı', 'Lütfen silmek için en az bir nöbet seçiniz.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Toplu Nöbet Silme',
            text: ids.length + ' adet seçili nöbeti silmek istediğinize emin misiniz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-trash me-1"></i> Evet, Sil',
            cancelButtonText: 'İptal',
            confirmButtonColor: '#f46a6a',
        }).then(result => {
            if (result.isConfirmed) {
                actionFetch('bulk-sil-nobet', { ids: ids });
            }
        });
    };

    $('#btnBulkApproveTop').on('click', bulkApprove);
    $('#btnBulkDeleteTop').on('click', bulkDelete);

    function actionFetch(action, extraData) {
        const formData = new FormData();
        formData.append('action', action);
        
        if (extraData) {
            Object.keys(extraData).forEach(key => {
                if (Array.isArray(extraData[key])) {
                    extraData[key].forEach(val => formData.append(key + '[]', val));
                } else {
                    formData.append(key, extraData[key]);
                }
            });
        }

        $.ajax({
            url: 'views/nobet/api.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(data) {
                if (data.success || data.status === 'success') {
                    Swal.fire('Başarılı!', data.message || 'İşlem başarıyla gerçekleştirildi.', 'success');
                    loadOnayBekleyenNobetler();
                } else {
                    Swal.fire('Hata!', data.message || 'İşlem başarısız oldu.', 'error');
                }
            },
            error: function() {
                Swal.fire('Hata!', 'Sunucu ile iletişim kurulurken bir sorun oluştu.', 'error');
            }
        });
    }

    // Excel Dışa Aktar
    $('#btnHeaderExportExcel').on('click', function(e) {
        e.preventDefault();
        if (!onayTable || !onayTable.data().any()) {
            Swal.fire('Uyarı!', 'Dışa aktarılacak nöbet verisi bulunamadı.', 'warning');
            return;
        }

        let csv = '\uFEFFPersonel;Departman;Nöbet Tarihi;Nöbet Tipi;Saat Aralığı;Açıklama\n';
        onayTable.rows({ filter: 'applied' }).nodes().to$().each(function() {
            const $cols = $(this).find('td');
            const personel = $cols.eq(1).text().trim();
            const departman = $cols.eq(2).text().trim();
            const tarih = $cols.eq(3).text().trim();
            const tip = $cols.eq(4).text().trim();
            const saat = $cols.eq(5).text().trim();
            const aciklama = $cols.eq(6).text().trim();
            csv += `"${personel}";"${departman}";"${tarih}";"${tip}";"${saat}";"${aciklama}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', 'Nobet_Onay_Listesi_' + new Date().toISOString().slice(0, 10) + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Tabloyu Yazdır
    $('#btnHeaderPrint').on('click', function(e) {
        e.preventDefault();
        const tableContainer = document.querySelector('#nobetOnayListCard .table-responsive');
        if (!tableContainer) {
            window.print();
            return;
        }

        const printWindow = window.open('', '_blank');
        const ayText = $('#filter-ay option:selected').text();
        const yilText = $('#filter-yil').val();
        const reportTitle = 'Nöbet Onay Listesi (' + ayText + ' ' + yilText + ')';
        const tableHtml = tableContainer.innerHTML;

        printWindow.document.write('<!DOCTYPE html><html><head><title>' + reportTitle + '</title>');
        printWindow.document.write('<link rel="stylesheet" href="assets/css/bootstrap.min.css">');
        printWindow.document.write('<style>body { font-family: sans-serif; padding: 20px; } table { width: 100%; border-collapse: collapse; margin-top: 15px; } th, td { border: 1px solid #dee2e6; padding: 6px 8px; font-size: 11px; text-align: left; } th { background-color: #f8f9fa !important; font-weight: bold; } th:first-child, td:first-child, th:last-child, td:last-child { display: none; } .report-person-avatar { display: none; } .badge { border: 1px solid #ccc; padding: 2px 4px; border-radius: 4px; font-size: 10px; }</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h4 style="margin-bottom: 5px;">' + reportTitle + '</h4>');
        printWindow.document.write('<p style="font-size: 12px; color: #666; margin-bottom: 15px;">Oluşturulma Tarihi: ' + new Date().toLocaleString('tr-TR') + '</p>');
        printWindow.document.write(tableHtml);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
        }, 500);
    });

    // İlk Yükleme
    loadOnayBekleyenNobetler();
});
</script>
