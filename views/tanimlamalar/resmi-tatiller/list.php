<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';

use App\Helper\Form;
use App\Helper\Helper;
use App\Service\Gate;

if (!Gate::allows('resmi_tatil_tanimlari')) {
    echo '<div class="container-fluid"><div class="alert alert-danger mt-3">Bu sayfa için yetkiniz bulunmamaktadır.</div></div>';
    return;
}

$maintitle = "Tanımlamalar";
$title = "Resmi Tatil Tanımları";
$currentYear = (int) date('Y');
$yearOptions = [];
for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
    $yearOptions[$y] = (string) $y;
}
$typeOptions = ['tam' => 'Tam gün resmi tatil (RTÇ)', 'yarim' => 'Yarım gün / arife (fazla mesai)'];
?>
<script>try { document.documentElement.classList.toggle('resmitatil-summary-hidden', localStorage.getItem('resmitatil_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.resmitatil-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
#holidayModal .yarim-only { display: none; }
.personel-action-toolbar .dropdown-toggle.show { color: var(--bs-secondary-color) !important; }
#holidayModal.is-yarim .yarim-only { display: block; }
</style>

<?php include dirname(__DIR__, 3) . '/layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-calendar-star fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Resmi Tatil Tanımları</h4>
                <p class="text-muted mb-0 font-size-12">Puantajda otomatik RTÇ ve arife fazla mesaisi bu takvime göre üretilir</p>
            </div>
        </div>

        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <div style="width: 130px;">
                <?php echo Form::FormSelect2('filter_yil', $yearOptions, $currentYear, 'Yıl', 'calendar'); ?>
            </div>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnNewHoliday">
                <i class="bx bx-plus me-1"></i> Yeni Tatil Günü
            </button>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary bg-white top-action-btn dropdown-toggle shadow-sm" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-cog me-1"></i> İşlemler
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item py-2" href="javascript:void(0);" id="btnExcelTemplate"><i class="bx bx-download me-2 text-success"></i> Excel Şablonu İndir</a></li>
                    <li><a class="dropdown-item py-2" href="javascript:void(0);" id="btnExcelImport"><i class="bx bx-upload me-2 text-primary"></i> Excelden Yükle</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <?php
        $cards = [
            ['TOPLAM TATİL GÜNÜ', 'toplam', 'primary', 'bx-calendar', 'all', 'Tümü', 'btn-subtle-primary', 'active'],
            ['TAM GÜN (RTÇ)', 'tam_gun', 'success', 'bx-calendar-check', 'tam', 'Tam gün', 'btn-subtle-success', ''],
            ['YARIM GÜN / ARİFE', 'yarim_gun', 'info', 'bx-time-five', 'yarim', 'Arife', 'btn-subtle-info', ''],
            ['SIRADAKİ TATİL', 'siradaki', 'warning', 'bx-bell', 'gelecek', 'Gelecek', 'btn-subtle-warning', ''],
        ];
        foreach ($cards as [$label, $key, $color, $icon, $filter, $btnText, $btnClass, $active]): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-label"><?= $label ?></span>
                        <div class="summary-kpi-icon bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle">
                            <i class="bx <?= $icon ?>"></i>
                        </div>
                    </div>
                    <h3 class="summary-kpi-value my-1 <?= $key === 'tam_gun' ? 'text-success' : '' ?>" id="stat_<?= $key ?>">0</h3>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="summary-kpi-subtext" id="stat_sub_<?= $key ?>">&nbsp;</span>
                        <button type="button" class="btn btn-sm <?= $btnClass ?> rounded-pill px-2 py-0 status-quick-filter d-flex align-items-center gap-1 summary-pill-btn <?= $active ?>" data-status="<?= $filter ?>">
                            <i class="bx bx-filter-alt"></i> <?= $btnText ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card summary-kpi-card mb-3" id="resmiTatilListCard">
        <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Tatil Takvimi</h5>
                    <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;">Genel tanımlar tüm firmalarda, firma tanımları yalnız bu firmada geçerlidir</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>

        <div class="card-body p-3 pt-0">
            <div class="table-responsive" style="overflow-x: auto !important;">
                <table id="resmiTatilTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="string" style="width: 110px;">TARİH</th>
                            <th data-filter="none" style="width: 90px;">GÜN</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="none" style="width: 150px;" class="text-center">TÜR</th>
                            <th data-filter="none" style="width: 160px;" class="text-center">OTOMATİK İŞLEM</th>
                            <th data-filter="none" style="width: 100px;" class="text-center">KAPSAM</th>
                            <th data-filter="none" style="width: 120px;" class="text-center">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold" id="holidayModalTitle">Resmi Tatil Günü</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="holidayForm" novalidate>
                    <input type="hidden" name="id" id="holiday_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php echo Form::FormDate('tatil_tarihi', '', 'Tarih', 'calendar'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormSelect2('tur', $typeOptions, 'tam', 'Tür', 'layers'); ?>
                        </div>
                        <div class="col-12">
                            <?php echo Form::FormFloatInput('text', 'aciklama', '', 'Örn: Cumhuriyet Bayramı', 'Açıklama', 'edit-3', 'form-control', true, 100); ?>
                        </div>
                        <div class="col-md-6 yarim-only">
                            <?php echo Form::FormFloatInput('time', 'yarim_gun_saati', '13:00', '', 'Tatilin Başladığı Saat', 'clock'); ?>
                        </div>
                        <div class="col-md-6 yarim-only">
                            <?php echo Form::FormFloatInput('number', 'fazla_mesai_saat', '4', '', 'Eklenecek Fazla Mesai (saat)', 'plus-circle', 'form-control', false, null, 'off', false, 'min="0.5" max="12" step="0.5"'); ?>
                        </div>
                        <?php if (Gate::isSuperAdmin()): ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="genel_tanim" name="genel_tanim" value="1">
                                <label class="form-check-label" for="genel_tanim">Tüm firmalar için genel tanım</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="btnSaveHoliday"><i class="bx bx-save me-1"></i> Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="holidayImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Excelden Tatil Günü Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="holidayImportForm" enctype="multipart/form-data" novalidate>
                    <div class="alert alert-info font-size-12 mb-3">
                        Sütunlar: <strong>Tarih</strong> (GG.AA.YYYY), <strong>Açıklama</strong>, <strong>Tür</strong> (Tam / Yarım),
                        <strong>Yarım Gün Saati</strong>, <strong>Fazla Mesai Saati</strong>. Aynı tarih tanımlıysa güncellenir, yoksa eklenir.
                        <a href="javascript:void(0);" class="fw-semibold" id="btnExcelTemplateInline">Şablonu indir</a>
                    </div>
                    <input type="file" class="form-control" id="holidayExcelFile" name="excel_file" accept=".xlsx,.xls" required>
                    <?php if (Gate::isSuperAdmin()): ?>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="import_genel_tanim" name="genel_tanim" value="1">
                        <label class="form-check-label" for="import_genel_tanim">Tüm firmalar için genel tanım olarak yükle</label>
                    </div>
                    <?php endif; ?>
                    <div id="holidayImportErrors" class="alert alert-warning font-size-12 mt-3 mb-0 d-none" style="max-height: 180px; overflow-y: auto;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnHolidayImport"><i class="bx bx-upload me-1"></i> Yükle</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo Helper::assetVersion('views/tanimlamalar/resmi-tatiller/js/list.js'); ?>"></script>
