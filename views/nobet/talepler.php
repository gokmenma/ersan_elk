<?php
use App\Model\PersonelModel;
use App\Helper\Form;

$Personel = new PersonelModel();
$personeller = $Personel->all(true, 'nobet');

$maintitle = "Nöbet Yönetimi";
$title = 'Talepler & Mazeretler';

$aylar = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
$yillar = [];
for ($y = (int) date('Y'); $y >= 2024; $y--) {
    $yillar[$y] = $y;
}
?>
<script>try { document.documentElement.classList.toggle('talep-summary-hidden', localStorage.getItem('talep_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.talep-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#summaryCardsContainer .summary-kpi-card,
#talepListCard {
    background-color: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
}
html[data-theme-preset="macos-dark"] #summaryCardsContainer .summary-kpi-card,
html[data-theme-preset="macos-dark"] #talepListCard,
[data-bs-theme="dark"] #summaryCardsContainer .summary-kpi-card,
[data-bs-theme="dark"] #talepListCard {
    background-color: #2a3042 !important;
    border-color: #32394e !important;
}
.status-filter-group {
    background: #f8fafc; padding: 4px; border-radius: 50px;
    border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 2px;
}
[data-bs-theme="dark"] .status-filter-group,
html[data-theme-preset="macos-dark"] .status-filter-group { background: #2a3042; border-color: #32394e; }
.status-filter-group .nav-link {
    border: none !important; border-radius: 100px !important;
    font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #64748b;
    transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
}
[data-bs-theme="dark"] .status-filter-group .nav-link,
html[data-theme-preset="macos-dark"] .status-filter-group .nav-link { color: #a6b0cf; }
.status-filter-group .nav-link:hover { background: rgba(0, 0, 0, 0.04); color: #1e293b; }
[data-bs-theme="dark"] .status-filter-group .nav-link:hover,
html[data-theme-preset="macos-dark"] .status-filter-group .nav-link:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
.status-filter-group .nav-link.active {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    color: white !important;
}
.status-filter-group .nav-link.active[href="#degisim-talepler"] { background: #f59e0b !important; }
.status-filter-group .nav-link.active[href="#mazeret-bildirimleri"] { background: #ef4444 !important; }
.status-filter-group .nav-link.active[href="#nobet-talepleri"] { background: #3b82f6 !important; }
.status-filter-group .nav-link i { font-size: 1rem; }
.status-filter-group .nav-link .badge { font-size: 0.7rem; padding: 0.25em 0.6em; }
.status-filter-group .nav-link.active .badge { background: #ffffff !important; color: #1e293b !important; }
.talep-period-filter { width: 140px; }
.talep-period-filter.yil { width: 125px; }
#talepListCard table.dataTable tbody tr { cursor: default; }
.dataTables_wrapper .dataTables_paginate { display: flex !important; justify-content: flex-end !important; align-items: center; }
.dataTables_wrapper .dataTables_info { padding-top: 0 !important; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">

    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-transfer-alt fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Talepler &amp; Mazeretler</h4>
                <p class="text-muted mb-0 font-size-12">Nöbet değişim talepleri, mazeret bildirimleri ve personel nöbet isteklerinin yönetimi</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <div class="talep-period-filter">
                <?php echo Form::FormSelect2('filter-ay', $aylar, (int) date('n'), 'Ay', 'calendar'); ?>
            </div>
            <div class="talep-period-filter yil">
                <?php echo Form::FormSelect2('filter-yil', $yillar, (int) date('Y'), 'Yıl', 'calendar'); ?>
            </div>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">BEKLEYEN DEĞİŞİM</span>
                        <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                            <i class="bx bx-transfer-alt"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-warning" id="stat-degisim-bekleyen">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Onay bekleyen değişim talepleri</span>
                        <button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn active" data-tab="#degisim-talepler">
                            <i class="bx bx-list-ul"></i> Göster
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">MAZERET BİLDİRİMİ</span>
                        <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                            <i class="bx bx-error-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-danger" id="stat-mazeret-bekleyen">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">İşlem bekleyen mazeretler</span>
                        <button type="button" class="btn btn-sm btn-subtle-danger rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn" data-tab="#mazeret-bildirimleri">
                            <i class="bx bx-list-ul"></i> Göster
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SEÇİLİ AY ONAYLANAN</span>
                        <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-success" id="stat-onaylanan">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Başarıyla tamamlanan talepler</span>
                        <button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 d-flex align-items-center gap-1 summary-pill-btn" id="btnQuickGecmis">
                            <i class="bx bx-history"></i> Geçmiş
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label">SEÇİLİ AY REDDEDİLEN</span>
                        <div class="summary-kpi-icon bg-secondary-subtle text-secondary border border-secondary-subtle">
                            <i class="bx bx-x-circle"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 text-secondary" id="stat-reddedilen">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext">Reddedilen talepler</span>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="talepListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="status-filter-group nav nav-pills" role="tablist" id="talepTabs">
                <a class="nav-link active" data-bs-toggle="tab" href="#degisim-talepler" role="tab" id="degisim-tab">
                    <i class="bx bx-transfer-alt"></i>
                    <span>Değişim Talepleri</span>
                    <span class="badge bg-warning ms-1" id="degisim-badge">0</span>
                </a>
                <a class="nav-link" data-bs-toggle="tab" href="#mazeret-bildirimleri" role="tab" id="mazeret-tab">
                    <i class="bx bx-error-circle"></i>
                    <span>Mazeretler</span>
                    <span class="badge bg-danger ms-1" id="mazeret-badge">0</span>
                </a>
                <a class="nav-link" data-bs-toggle="tab" href="#nobet-talepleri" role="tab" id="nobet-talep-tab">
                    <i class="bx bx-calendar-plus"></i>
                    <span>Talep ve İstekler</span>
                    <span class="badge bg-info ms-1" id="nobet-talep-badge">0</span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <button type="button" class="btn btn-sm btn-subtle-info px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btn-toggle-gecmis" title="Geçmiş işlemleri göster">
                    <i class="bx bx-history font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Geçmiş İşlemler</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnExportExcel" title="Excel'e Aktar">
                    <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="tab-content" id="talepTabContent">
                <div class="tab-pane fade show active" id="degisim-talepler" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="degisim-table">
                            <thead>
                                <tr>
                                    <th data-filter="string">TALEP EDEN</th>
                                    <th data-filter="string">TALEP EDİLEN</th>
                                    <th data-filter="date" class="text-center">NÖBET TARİHİ</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <th data-filter="select" class="text-center">DURUM</th>
                                    <th data-filter="date" class="text-center">TALEP TARİHİ</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="mazeret-bildirimleri" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="mazeret-table">
                            <thead>
                                <tr>
                                    <th data-filter="string">PERSONEL</th>
                                    <th data-filter="date" class="text-center">NÖBET TARİHİ</th>
                                    <th data-filter="string" class="text-center">NÖBET SAATİ</th>
                                    <th data-filter="string">MAZERET AÇIKLAMASI</th>
                                    <th data-filter="date" class="text-center">BİLDİRİM TARİHİ</th>
                                    <th data-filter="none" class="text-center" style="width: 130px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="nobet-talepleri" role="tabpanel">
                    <div class="table-responsive" style="overflow-x: auto !important;">
                        <table class="table table-bordered table-hover nowrap align-middle w-100 mb-0" id="nobet-talep-table">
                            <thead>
                                <tr>
                                    <th data-filter="string">PERSONEL</th>
                                    <th data-filter="date" class="text-center">TALEP EDİLEN TARİH</th>
                                    <th data-filter="string">AÇIKLAMA</th>
                                    <th data-filter="select" class="text-center">DURUM</th>
                                    <th data-filter="date" class="text-center">TALEP TARİHİ</th>
                                    <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Segmented Control CSS -->
<style>
    .segmented-control {
        display: flex;
        width: 100%;
        background-color: #f1f5f9;
        border-radius: 0.5rem;
        padding: 0.25rem;
        position: relative;
        border: 1px solid #e2e8f0;
    }
    .segmented-control input[type="radio"] { display: none; }
    .segmented-control label {
        flex: 1;
        text-align: center;
        padding: 0.5rem 1rem;
        cursor: pointer;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        color: #64748b;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        user-select: none;
        margin-bottom: 0;
        line-height: 1.2;
    }
    .segmented-control input[type="radio"]:checked+label {
        background-color: #ffffff;
        color: #2563eb;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        font-weight: 600;
    }
    .segmented-control label:hover:not(:active) { color: #1e293b; }
</style>

<!-- Nöbet Devir Modal -->
<div class="modal fade" id="devirModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header bg-light border-bottom">
                <div class="modal-title-section d-flex align-items-center">
                    <div class="avatar-xs me-2 rounded bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i data-feather="layers" class="text-primary" style="width:18px;height:18px;"></i>
                    </div>
                    <div>
                        <h6 class="modal-title text-dark mb-0 fw-bold">Nöbeti Devret</h6>
                        <p class="text-muted small mb-0" style="font-size: 0.7rem;">Lütfen formu eksiksiz doldurun.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="devir-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="nobet_id" id="devir-nobet-id">
                    <div class="mb-3">
                        <?php echo Form::FormFloatInput('text', 'devir-mevcut-personel', '', null, 'Mevcut Personel', 'user', 'form-control', false, null, 'on', true, '', true); ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block fw-bold text-muted small text-uppercase mb-2">Personel Seçimi</label>
                        <div class="segmented-control mb-3">
                            <input type="radio" name="devir_personel_turu" id="devirPersonelTuruKesmeAcma" value="kesme_acma" checked>
                            <label for="devirPersonelTuruKesmeAcma">
                                <i data-feather="scissors" style="width:16px;height:16px;"></i> Kesme Açma
                            </label>
                            <input type="radio" name="devir_personel_turu" id="devirPersonelTuruTum" value="all">
                            <label for="devirPersonelTuruTum">
                                <i data-feather="users" style="width:16px;height:16px;"></i> Tüm Personeller
                            </label>
                        </div>
                        <?php
                        $kesmeAcmaPersoneller = array_filter($personeller, function ($p) {
                            return stripos($p->departman ?? '', 'Kesme') !== false || stripos($p->departman ?? '', 'Açma') !== false;
                        });
                        $personelOptions = [];
                        foreach ($kesmeAcmaPersoneller as $p) {
                            $personelOptions[\App\Helper\Security::encrypt($p->id)] = $p->adi_soyadi . ' - ' . ($p->departman ?? '');
                        }
                        echo Form::FormSelect2('yeni_personel_id', $personelOptions, null, 'Personel Seçin', 'users', 'key', '', 'form-select select2', true, 'width:100%', '', 'devir-yeni-personel');
                        ?>
                    </div>
                    <div class="mb-3">
                        <?php echo Form::FormFloatTextarea('aciklama', '', 'Devir sebebi...', 'Açıklama', 'file-text'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        İptal
                    </button>
                    <button type="submit" class="btn btn-dark">
                        <i data-feather="shuffle" style="width:14px;height:14px;" class="me-1"></i> Devret
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const devirModal = new bootstrap.Modal(document.getElementById('devirModal'));
        const API_URL = 'views/nobet/api.php';
        let showHistory = false;
        const tables = {};

        const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const formatDateShort = (dateStr) => {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-';
            return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        };

        const apiPost = (params) => fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params)
        }).then(r => r.json());

        const showToast = (type, message) => {
            Toastify({
                text: message, duration: 3000, gravity: 'top', position: 'center',
                style: { background: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#000000', borderRadius: '8px', boxShadow: '0 4px 12px rgba(0,0,0,0.15)' },
                stopOnFocus: true
            }).showToast();
        };

        const avatar = (name, tone) => `<div class="d-flex align-items-center gap-2">
            <span class="avatar-title bg-${tone}-subtle text-${tone} rounded-circle d-inline-flex align-items-center justify-content-center fw-bold" style="width:28px;height:28px;font-size:12px;">${esc((name ? name[0] : '?').toUpperCase())}</span>
            <strong class="font-size-13">${esc(name || '-')}</strong></div>`;

        const dateBadge = (d, tone) => `<span class="badge bg-${tone}-subtle text-${tone} border border-${tone}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">${formatDateShort(d)}</span>`;

        const statusBadge = (tone, icon, text) => `<span class="badge bg-${tone}-subtle text-${tone} border border-${tone}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx ${icon} me-1"></i>${text}</span>`;

        const degisimDurum = (durum) => ({
            'beklemede': statusBadge('secondary', 'bx-time-five', 'Personel Onayı Bekleniyor'),
            'personel_onayladi': statusBadge('warning', 'bx-hourglass', 'Yönetici Onayı Bekleniyor'),
            'onaylandi': statusBadge('success', 'bx-check-circle', 'Onaylandı'),
            'reddedildi': statusBadge('danger', 'bx-x-circle', 'Reddedildi'),
            'iptal': statusBadge('secondary', 'bx-block', 'İptal')
        }[durum] || statusBadge('secondary', 'bx-info-circle', esc(durum)));

        const talepDurum = (durum) => ({
            'talep_edildi': statusBadge('warning', 'bx-time-five', 'Beklemede'),
            'beklemede': statusBadge('warning', 'bx-time-five', 'Beklemede'),
            'onaylandi': statusBadge('success', 'bx-check-circle', 'Onaylandı'),
            'cozuldu': statusBadge('success', 'bx-check-circle', 'Onaylandı'),
            'reddedildi': statusBadge('danger', 'bx-x-circle', 'Reddedildi'),
            'iptal': statusBadge('secondary', 'bx-block', 'İptal')
        }[durum] || statusBadge('secondary', 'bx-info-circle', esc(durum)));

        const actionBtn = (cls, action, id, icon, title, extra = '') =>
            `<button type="button" class="btn btn-sm table-action-btn ${cls}" data-act="${action}" data-id="${esc(id)}" ${extra} title="${title}" aria-label="${title}"><i class="bx ${icon}"></i></button>`;

        const muted = (v, fallback = '-') => v ? esc(v) : `<span class="text-muted">${fallback}</span>`;

        const columnDefs = {
            '#degisim-table': [
                { data: 'talep_eden_adi', render: (d, t) => t === 'display' ? avatar(d, 'primary') : (d || '') },
                { data: 'talep_edilen_adi', render: (d, t) => t === 'display' ? avatar(d, 'success') : (d || '') },
                { data: 'nobet_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? dateBadge(d, 'info') : (d || '') },
                { data: 'aciklama', render: (d, t) => t === 'display' ? muted(d) : (d || '') },
                { data: 'durum', className: 'text-center', render: (d, t) => t === 'display' ? degisimDurum(d) : ({ beklemede: 'Personel Onayı Bekleniyor', personel_onayladi: 'Yönetici Onayı Bekleniyor', onaylandi: 'Onaylandı', reddedildi: 'Reddedildi', iptal: 'İptal' }[d] || d || '') },
                { data: 'talep_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? formatDateShort(d) : (d || '') },
                {
                    data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (d, t, row) => {
                        if (row.durum === 'personel_onayladi' && !showHistory) {
                            return `<div class="action-btn-group d-inline-flex gap-1">${actionBtn('btn-subtle-success', 'degisim-onayla', row.id, 'bx-check', 'Onayla')}${actionBtn('btn-subtle-danger', 'degisim-reddet', row.id, 'bx-x', 'Reddet')}</div>`;
                        }
                        return `<span class="text-muted font-size-12">${formatDateShort(row.guncelleme_tarihi || row.talep_tarihi)}</span>`;
                    }
                }
            ],
            '#mazeret-table': [
                { data: 'personel_adi', render: (d, t) => t === 'display' ? avatar(d, 'danger') : (d || '') },
                { data: 'nobet_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? dateBadge(d, 'warning') : (d || '') },
                { data: null, className: 'text-center', render: (d, t, row) => `${esc(row.baslangic_saati || '18:00')} - ${esc(row.bitis_saati || '23:00')}` },
                { data: 'mazeret_aciklama', render: (d, t) => t === 'display' ? muted(d, 'Açıklama yok') : (d || '') },
                { data: 'mazeret_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? formatDateShort(d) : (d || '') },
                {
                    data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (d, t, row) => {
                        if (showHistory) {
                            return statusBadge('success', 'bx-check', 'İşlem Yapıldı');
                        }
                        return `<div class="action-btn-group d-inline-flex gap-1">${actionBtn('btn-subtle-primary', 'mazeret-devret', row.id, 'bx-user-plus', 'Başka Personele Devret', `data-personel="${esc(row.personel_adi)}"`)}${actionBtn('btn-subtle-warning', 'mazeret-reddet', row.id, 'bx-x-circle', 'Mazereti Reddet')}${actionBtn('btn-subtle-danger', 'mazeret-iptal', row.id, 'bx-trash', 'Nöbeti İptal Et')}</div>`;
                    }
                }
            ],
            '#nobet-talep-table': [
                { data: 'personel_adi', render: (d, t) => t === 'display' ? avatar(d, 'info') : (d || '') },
                { data: 'nobet_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? dateBadge(d, 'primary') : (d || '') },
                { data: 'aciklama', render: (d, t) => t === 'display' ? muted(d, 'Açıklama yok') : (d || '') },
                { data: 'durum', className: 'text-center', render: (d, t) => t === 'display' ? talepDurum(d) : ({ talep_edildi: 'Beklemede', beklemede: 'Beklemede', onaylandi: 'Onaylandı', cozuldu: 'Onaylandı', reddedildi: 'Reddedildi', iptal: 'İptal' }[d] || d || '') },
                { data: 'olusturma_tarihi', className: 'text-center', render: (d, t) => t === 'display' ? formatDateShort(d) : (d || '') },
                {
                    data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (d, t, row) => {
                        if (row.durum === 'talep_edildi' && !showHistory) {
                            return `<div class="action-btn-group d-inline-flex gap-1">${actionBtn('btn-subtle-success', 'talep-onayla', row.id, 'bx-check', 'Onayla')}${actionBtn('btn-subtle-danger', 'talep-reddet', row.id, 'bx-x', 'Reddet')}</div>`;
                        }
                        return `<span class="text-muted font-size-12">${formatDateShort(row.olusturma_tarihi)}</span>`;
                    }
                }
            ]
        };

        const initTable = (selector) => {
            tables[selector] = $(selector).DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                data: [],
                columns: columnDefs[selector],
                order: []
            }));
        };

        Object.keys(columnDefs).forEach(initTable);

        const fillTable = (selector, rows) => {
            const dt = tables[selector];
            dt.clear();
            dt.rows.add(rows).draw();
            dt.columns.adjust();
        };

        const period = () => ({
            ay: document.getElementById('filter-ay').value,
            yil: document.getElementById('filter-yil').value
        });

        const setCount = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        const loadList = (action, selector, onDone) => {
            const p = period();
            apiPost({ action, is_gecmis: showHistory ? '1' : '0', ay: p.ay, yil: p.yil })
                .then(data => {
                    const rows = (data.success && Array.isArray(data.data)) ? data.data : [];
                    fillTable(selector, rows);
                    if (!showHistory) onDone(rows);
                })
                .catch(e => {
                    console.error('Liste yüklenemedi:', e);
                    showToast('error', 'Liste yüklenirken bir hata oluştu');
                });
        };

        window.loadTaleplerVeMazeretler = function () {
            loadList('get-degisim-talepleri', '#degisim-table', rows => {
                const n = rows.filter(r => r.durum === 'personel_onayladi' || r.durum === 'beklemede').length;
                setCount('degisim-badge', n);
                setCount('stat-degisim-bekleyen', n);
            });
            loadList('get-mazeret-bildirimleri', '#mazeret-table', rows => {
                setCount('mazeret-badge', rows.length);
                setCount('stat-mazeret-bekleyen', rows.length);
            });
            loadList('get-personel-nobet-talepleri', '#nobet-talep-table', rows => {
                setCount('nobet-talep-badge', rows.length);
            });
            const p = period();
            apiPost({ action: 'get-talep-stats', ay: p.ay, yil: p.yil }).then(data => {
                if (data.success) {
                    setCount('stat-onaylanan', data.data.onaylanan);
                    setCount('stat-reddedilen', data.data.reddedilen);
                }
            }).catch(e => console.error('İstatistikler yüklenemedi:', e));
        };

        const syncHistoryButton = () => {
            const btn = document.getElementById('btn-toggle-gecmis');
            btn.classList.toggle('btn-subtle-info', !showHistory);
            btn.classList.toggle('btn-info', showHistory);
            btn.classList.toggle('text-white', showHistory);
            btn.innerHTML = showHistory
                ? '<i class="bx bx-list-ul font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Bekleyenler</span>'
                : '<i class="bx bx-history font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Geçmiş İşlemler</span>';
            btn.title = showHistory ? 'Bekleyen işlemleri göster' : 'Geçmiş işlemleri göster';
        };

        const toggleGecmis = () => {
            showHistory = !showHistory;
            syncHistoryButton();
            window.loadTaleplerVeMazeretler();
        };

        $('#btn-toggle-gecmis').on('click', toggleGecmis);
        $('#btnQuickGecmis').on('click', function () {
            if (!showHistory) toggleGecmis();
        });

        $('#filter-ay, #filter-yil').on('change', window.loadTaleplerVeMazeretler);

        $('#btnHeaderPrint').on('click', () => window.print());

        $('#btnExportExcel').on('click', function () {
            const activeTab = document.querySelector('#talepTabs .nav-link.active').id;
            const selector = activeTab === 'degisim-tab' ? '#degisim-table' : (activeTab === 'mazeret-tab' ? '#mazeret-table' : '#nobet-talep-table');
            const dt = tables[selector];
            if (dt && dt.button('.buttons-excel').length) {
                dt.button('.buttons-excel').trigger();
                showToast('success', 'Veriler Excel\'e aktarılıyor...');
            } else {
                showToast('error', 'Tablo henüz yüklenmedi veya veri bulunamadı.');
            }
        });

        const toggleBtn = $('#btnToggleSummaryCards');
        const updateToggleState = () => {
            const isHidden = $('html').hasClass('talep-summary-hidden');
            toggleBtn.attr('aria-expanded', !isHidden);
            toggleBtn.find('i').attr('class', isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up');
            toggleBtn.attr('title', isHidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle');
            toggleBtn.attr('aria-label', isHidden ? 'Özet Kartları Göster' : 'Özet Kartları Gizle');
        };
        updateToggleState();
        toggleBtn.on('click', function () {
            const willHide = !$('html').hasClass('talep-summary-hidden');
            $('html').toggleClass('talep-summary-hidden', willHide);
            try { localStorage.setItem('talep_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
            updateToggleState();
        });

        $('.status-quick-filter').on('click', function () {
            const target = $(this).data('tab');
            if (target) {
                bootstrap.Tab.getOrCreateInstance(document.querySelector(`#talepTabs a[href="${target}"]`)).show();
            }
        });

        $('#talepTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const target = $(e.target).attr('href');
            $('.status-quick-filter').removeClass('active');
            $(`.status-quick-filter[data-tab="${target}"]`).addClass('active');
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });

        if (window.location.hash === '#talepler') {
            bootstrap.Tab.getOrCreateInstance(document.getElementById('nobet-talep-tab')).show();
        }

        const confirmAction = (opts, action, params, successMsg) => {
            Swal.fire(opts).then(r => {
                if (!r.isConfirmed) return;
                apiPost({ action, ...params(r) }).then(data => {
                    if (data.success || data.status === 'success') {
                        showToast('success', data.message || successMsg);
                        window.loadTaleplerVeMazeretler();
                    } else {
                        showToast('error', data.message || 'Bir hata oluştu');
                    }
                }).catch(() => showToast('error', 'İşlem sırasında bir hata oluştu'));
            });
        };

        const confirmBase = { showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d', cancelButtonText: 'İptal' };
        const rejectBase = { ...confirmBase, input: 'textarea', confirmButtonColor: '#dc3545', confirmButtonText: 'Reddet' };

        const handlers = {
            'degisim-onayla': (id) => confirmAction(
                { ...confirmBase, title: 'Değişim Talebini Onayla', text: 'Bu değişim talebini onaylamak istediğinize emin misiniz? Nöbet ataması değiştirilecektir.', icon: 'question', confirmButtonText: 'Evet, Onayla' },
                'onayla-amir-talebi', () => ({ talep_id: id }), 'Değişim talebi onaylandı'),
            'degisim-reddet': (id) => confirmAction(
                { ...rejectBase, title: 'Değişim Talebini Reddet', inputLabel: 'Red Sebebi (Opsiyonel)', inputPlaceholder: 'Neden reddedildiğini açıklayabilirsiniz...' },
                'reddet-talebi', (r) => ({ talep_id: id, red_nedeni: r.value || '' }), 'Değişim talebi reddedildi'),
            'talep-onayla': (id) => confirmAction(
                { ...confirmBase, title: 'Nöbet Talebini Onayla', text: 'Bu talebi onayladığınızda personelin seçtiği tarihe nöbet ataması yapılacaktır. Emin misiniz?', icon: 'question', confirmButtonText: 'Evet, Onayla' },
                'onayla-personel-nobet-talebi', () => ({ talep_id: id }), 'Talep onaylandı'),
            'talep-reddet': (id) => confirmAction(
                { ...rejectBase, title: 'Nöbet Talebini Reddet', inputLabel: 'Red Sebebi', inputPlaceholder: 'Neden reddedildiğini açıklayın...' },
                'reddet-personel-nobet-talebi', (r) => ({ talep_id: id, red_nedeni: r.value || '' }), 'Talep reddedildi'),
            'mazeret-reddet': (id) => confirmAction(
                { ...rejectBase, title: 'Mazereti Reddet', inputLabel: 'Red Sebebi', inputPlaceholder: 'Mazeretin neden reddedildiğini açıklayın...' },
                'reddet-mazeret', (r) => ({ nobet_id: id, red_nedeni: r.value || '' }), 'Mazeret reddedildi'),
            'mazeret-iptal': (id) => confirmAction(
                { ...confirmBase, title: 'Nöbeti İptal Et', text: 'Bu nöbeti tamamen iptal etmek istediğinize emin misiniz?', icon: 'warning', confirmButtonColor: '#dc3545', confirmButtonText: 'Evet, İptal Et', cancelButtonText: 'Vazgeç' },
                'delete-nobet', () => ({ nobet_id: id }), 'Nöbet iptal edildi'),
            'mazeret-devret': (id, btn) => {
                document.getElementById('devir-nobet-id').value = id;
                document.getElementById('devir-mevcut-personel').value = btn.dataset.personel || '';
                document.getElementById('devir-yeni-personel').value = '';
                devirModal.show();
            }
        };

        $('#talepListCard').on('click', 'button[data-act]', function (e) {
            e.stopPropagation();
            const fn = handlers[this.dataset.act];
            if (fn) fn(this.dataset.id, this);
        });

        document.querySelectorAll('input[name="devir_personel_turu"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const select = document.getElementById('devir-yeni-personel');
                apiPost({ action: 'get-personel-list', type: this.value }).then(data => {
                    if (data.success && data.data) {
                        if ($(select).data('select2')) {
                            $(select).select2('destroy');
                        }
                        select.innerHTML = '<option value="">Personel Seçin</option>';
                        data.data.forEach(p => {
                            const opt = document.createElement('option');
                            opt.value = p.id;
                            opt.textContent = p.adi_soyadi + ' - ' + (p.departman || '');
                            select.appendChild(opt);
                        });
                        $(select).select2({ dropdownParent: $('#devirModal'), placeholder: 'Personel Seçin', allowClear: true });
                    }
                });
            });
        });

        document.getElementById('devir-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            apiPost({ action: 'devir-yap', nobet_id: fd.get('nobet_id'), personel_id: fd.get('yeni_personel_id') }).then(data => {
                if (data.success || data.status === 'success') {
                    showToast('success', data.message || 'Nöbet başarıyla devredildi');
                    devirModal.hide();
                    window.loadTaleplerVeMazeretler();
                } else {
                    showToast('error', data.message || 'Bir hata oluştu');
                }
            }).catch(e => {
                console.error('Devir hatası:', e);
                showToast('error', 'İşlem sırasında bir hata oluştu');
            });
        });

        document.getElementById('devirModal').addEventListener('shown.bs.modal', function () {
            if (typeof feather !== 'undefined') feather.replace();
            const select = document.getElementById('devir-yeni-personel');
            if (select && !$(select).data('select2')) {
                $(select).select2({ dropdownParent: $('#devirModal'), placeholder: 'Personel Seçin', allowClear: true });
            }
        });

        $('#filter-ay, #filter-yil').each(function () {
            if (!$(this).data('select2')) $(this).select2({ width: '100%' });
        });

        window.loadTaleplerVeMazeretler();
    });
</script>
