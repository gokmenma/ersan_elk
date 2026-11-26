<?php

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();

$selectedDonemId = $_GET['donem'] ?? null;
$requestedYil = isset($_GET['yil']) ? (int) $_GET['yil'] : 0;

if (!$selectedDonemId && $requestedYil > 0) {
    $yilDonemleri = $BordroDonem->getAllDonems($requestedYil);
    $selectedDonemId = $yilDonemleri[0]->id ?? null;
}
if (!$selectedDonemId) {
    $selectedDonemId = $_SESSION['selectedDonemId'] ?? null;
}

$selectedDonem = null;
$personeller = [];

$toplamGvMatrah = 0;
$toplamDvMatrah = 0;
$toplamGv = 0;
$toplamDv = 0;
$toplamOdenecek = 0;
$toplamGvIstisna = 0;
$toplamDvIstisna = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);

        foreach ($personeller as $personel) {
            $gvMatrah = 0;
            $dvMatrah = 0;
            $kumulatifGv = 0;
            $gvIstisna = 0;
            $dvIstisna = 0;

            if (!empty($personel->hesaplama_detay)) {
                $detay = json_decode($personel->hesaplama_detay, true);
                $gvMatrah = floatval($detay['matrahlar']['gelir_vergisi_matrahi'] ?? 0);
                $kumulatifGv = floatval($detay['matrahlar']['kumulatif_vergi_matrahi_ay_basi'] ?? 0);
                $gvIstisna = floatval($detay['istisnalar']['gv_istisnasi'] ?? 0);
                $dvMatrah = floatval($detay['matrahlar']['damga_vergisi_matrahi'] ?? 0);
                $dvIstisna = floatval($detay['istisnalar']['dv_istisnasi'] ?? 0);
            }

            $personel->gv_matrah = $gvMatrah;
            $personel->kumulatif_gv = $kumulatifGv;
            $personel->gv_istisna = $gvIstisna;
            $personel->dv_matrah = $dvMatrah;
            $personel->dv_istisna = $dvIstisna;

            $odenecekGv = floatval($personel->gelir_vergisi ?? 0);
            $odenecekDv = floatval($personel->damga_vergisi ?? 0);

            $toplamGvMatrah += $gvMatrah;
            $toplamDvMatrah += $dvMatrah;
            $toplamGvIstisna += $gvIstisna;
            $toplamDvIstisna += $dvIstisna;
            $toplamGv += $odenecekGv;
            $toplamDv += $odenecekDv;
            $toplamOdenecek += ($odenecekGv + $odenecekDv);
        }
    }
}

$selectedYil = $selectedDonem
    ? date('Y', strtotime($selectedDonem->baslangic_tarihi))
    : ($requestedYil > 0 ? $requestedYil : date('Y'));
$donemler = $BordroDonem->getAllDonems($selectedYil);
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
foreach ($donemler as $donem) {
    $donem_option[$donem->id] = $donem->donem_adi;
}

$personelSayisi = count($personeller);
$fmt = fn($v) => number_format((float) $v, 2, ',', '.');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$donemAdi = $selectedDonem ? $selectedDonem->donem_adi : '';
$donemIdQuery = $selectedDonemId ? urlencode((string) $selectedDonemId) : '';
?>
<script>try { document.documentElement.classList.toggle('vergiraporu-summary-hidden', localStorage.getItem('vergiraporu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.vergiraporu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.vergi-period-select .form-floating { margin-bottom: 0; }
#vergiRaporuTable tbody td.vergi-col-gv { background-color: #fffdf5 !important; }
#vergiRaporuTable tbody td.vergi-col-dv { background-color: #fff5f7 !important; }
#vergiRaporuTable tbody td.vergi-col-toplam { background-color: #f8fafc !important; }
#vergiRaporuTable tfoot th { background-color: #f8fafc !important; border-top: 2px solid #e2e8f0 !important; font-size: 13px; font-weight: 700; color: #1e293b; }
@media print {
    .personel-action-toolbar, #summaryCardsContainer, #btnHeaderPrint, #btnHeaderExportExcel { display: none !important; }
}
</style>

<?php
$maintitle = "Bordro";
$subtitle = "Raporlar";
$title = "Vergi Raporu";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-xl-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-calculator fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Vergi Raporu (GV &amp; DV)</h4>
                <p class="text-muted mb-0 font-size-12">Dönem bazlı personel gelir vergisi ve damga vergisi kesintileri</p>
            </div>
            <?php if ($selectedDonem): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($donemAdi) ?></span>
            <?php endif; ?>
        </div>

        <div class="personel-action-toolbar col-xl-7 col-12 d-flex align-items-center justify-content-xl-end flex-wrap gap-2 mt-2 mt-xl-0">
            <div class="vergi-period-select">
                <?= Form::FormSelect2(
                    name: 'yilSelectVergi',
                    options: $yil_option,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width: 120px;'
                ) ?>
            </div>
            <div class="vergi-period-select">
                <?= Form::FormSelect2(
                    name: 'donemSelectVergi',
                    options: $donem_option,
                    selectedValue: $selectedDonemId,
                    label: 'Dönem',
                    icon: 'calendar',
                    style: 'min-width: 180px;'
                ) ?>
            </div>

            <?php if ($selectedDonem): ?>
                <a href="views/bordro/excel-vergi-export.php?donem_id=<?= $e($donemIdQuery) ?>" class="btn btn-primary top-action-btn shadow-sm text-white">
                    <i class="bx bx-download font-size-16"></i> Excel İndir
                </a>
            <?php endif; ?>

            <a href="index?p=bordro/raporlar&donem=<?= $e($donemIdQuery) ?>" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-arrow-back font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Raporlara Dön</span>
            </a>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <?php if ($selectedDonem && !empty($personeller)): ?>
        <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">TOPLAM GV MATRAHI</span><h3 class="summary-kpi-value my-1"><?= $fmt($toplamGvMatrah) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-wallet"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $personelSayisi ?> personel | İstisna <?= $fmt($toplamGvIstisna) ?></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Matrah (GV)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">ÖDENECEK GELİR VERGİSİ</span><h3 class="summary-kpi-value my-1 text-warning"><?= $fmt($toplamGv) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bxs-file-blank"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Personelden kesilen net GV</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kesinti (GV)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">ÖDENECEK DAMGA VERGİSİ</span><h3 class="summary-kpi-value my-1 text-danger"><?= $fmt($toplamDv) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bx bxs-file"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">DV matrahı <?= $fmt($toplamDvMatrah) ?></span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kesinti (DV)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">VERGİ KESİNTİLERİ TOPLAMI</span><h3 class="summary-kpi-value my-1 text-success"><?= $fmt($toplamOdenecek) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-pie-chart-alt-2"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">GV + DV toplamı</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam Vergi</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card summary-kpi-card mb-3" id="vergiRaporuListCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Vergi Kesinti Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= $e($donemAdi) ?> dönemi gelir ve damga vergisi dökümü</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <a href="views/bordro/excel-vergi-export.php?donem_id=<?= $e($donemIdQuery) ?>" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                        <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                        <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                    </button>
                </div>
            </div>

            <div class="card-body p-3 pt-0">
                <div class="table-responsive" style="overflow-x: auto !important;">
                    <table id="vergiRaporuTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="none" style="width: 50px;" class="text-center">#</th>
                                <th data-filter="string" style="width: 120px;">TC KİMLİK NO</th>
                                <th data-filter="string">AD SOYAD</th>
                                <th data-filter="string" class="text-end" title="Aylık Gelir Vergisi Matrahı">GV MATRAHI</th>
                                <th data-filter="string" class="text-end" title="Kümülatif Gelir Vergisi Matrahı">KÜMÜLATİF M.</th>
                                <th data-filter="string" class="text-end" title="Uygulanan GV İstisna Tutarı">GV İSTİSNASI</th>
                                <th data-filter="string" class="text-end" title="Personelden Kesilen Net GV">ÖDENEN GV</th>
                                <th data-filter="string" class="text-end" title="Damga Vergisi Matrahı">DV MATRAHI</th>
                                <th data-filter="string" class="text-end" title="Uygulanan DV İstisna Tutarı">DV İSTİSNASI</th>
                                <th data-filter="string" class="text-end" title="Personelden Kesilen Net DV">ÖDENEN DV</th>
                                <th data-filter="string" class="text-end">TOPLAM VERGİ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sira = 1; foreach ($personeller as $personel):
                                $odenecekGv = floatval($personel->gelir_vergisi ?? 0);
                                $odenecekDv = floatval($personel->damga_vergisi ?? 0);
                                ?>
                                <tr>
                                    <td class="text-center"><span class="row-index-badge"><?= $sira++ ?></span></td>
                                    <td><span class="text-muted text-nowrap" style="font-family: monospace;"><?= $e($personel->tc_kimlik_no ?? '-') ?></span></td>
                                    <td><span class="fw-medium text-dark text-nowrap"><?= $e($personel->adi_soyadi) ?></span></td>
                                    <td class="text-end text-nowrap vergi-col-gv" data-order="<?= (float) $personel->gv_matrah ?>"><?= $fmt($personel->gv_matrah) ?></td>
                                    <td class="text-end text-nowrap vergi-col-gv" data-order="<?= (float) $personel->kumulatif_gv ?>"><?= $fmt($personel->kumulatif_gv) ?></td>
                                    <td class="text-end text-nowrap text-success vergi-col-gv" data-order="<?= (float) $personel->gv_istisna ?>"><?= $fmt($personel->gv_istisna) ?></td>
                                    <td class="text-end text-nowrap fw-bold text-danger vergi-col-gv" data-order="<?= $odenecekGv ?>"><?= $fmt($odenecekGv) ?></td>
                                    <td class="text-end text-nowrap vergi-col-dv" data-order="<?= (float) $personel->dv_matrah ?>"><?= $fmt($personel->dv_matrah) ?></td>
                                    <td class="text-end text-nowrap text-success vergi-col-dv" data-order="<?= (float) $personel->dv_istisna ?>"><?= $fmt($personel->dv_istisna) ?></td>
                                    <td class="text-end text-nowrap fw-bold text-danger vergi-col-dv" data-order="<?= $odenecekDv ?>"><?= $fmt($odenecekDv) ?></td>
                                    <td class="text-end text-nowrap fw-bold text-success vergi-col-toplam" data-order="<?= $odenecekGv + $odenecekDv ?>"><?= $fmt($odenecekGv + $odenecekDv) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">GENEL TOPLAMLAR:</th>
                                <th class="text-end text-nowrap text-primary"><?= $fmt($toplamGvMatrah) ?> ₺</th>
                                <th></th>
                                <th class="text-end text-nowrap text-success"><?= $fmt($toplamGvIstisna) ?> ₺</th>
                                <th class="text-end text-nowrap text-warning"><?= $fmt($toplamGv) ?> ₺</th>
                                <th class="text-end text-nowrap text-primary"><?= $fmt($toplamDvMatrah) ?> ₺</th>
                                <th class="text-end text-nowrap text-success"><?= $fmt($toplamDvIstisna) ?> ₺</th>
                                <th class="text-end text-nowrap text-danger"><?= $fmt($toplamDv) ?> ₺</th>
                                <th class="text-end text-nowrap text-success"><?= $fmt($toplamOdenecek) ?> ₺</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($selectedDonem): ?>
        <div class="card summary-kpi-card mb-3">
            <div class="card-body text-center py-5">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bx bx-calculator fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Personel Bulunamadı</h5>
                <p class="text-muted font-size-12 mb-0">Bu dönemde henüz maaşı hesaplanmış personel bulunmamaktadır.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card summary-kpi-card mb-3">
            <div class="card-body text-center py-5">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bx bx-calendar-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Dönem Seçimi Bekleniyor</h5>
                <p class="text-muted font-size-12 mb-0">Vergi raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('btnToggleSummaryCards');
        const root = document.documentElement;
        const updateToggleState = function () {
            const isHidden = root.classList.contains('vergiraporu-summary-hidden');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
            }
        };
        updateToggleState();
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const willHide = !root.classList.contains('vergiraporu-summary-hidden');
                root.classList.toggle('vergiraporu-summary-hidden', willHide);
                try { localStorage.setItem('vergiraporu_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
                updateToggleState();
            });
        }

        const tableEl = document.getElementById('vergiRaporuTable');
        if (tableEl && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable(tableEl)) {
            jQuery(tableEl).DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[2, 'asc']],
                columnDefs: [
                    { targets: 0, orderable: false, searchable: false }
                ]
            }));
        }

        const printBtn = document.getElementById('btnHeaderPrint');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }

        const yilSelect = document.querySelector('[name="yilSelectVergi"]');
        const donemSelect = document.querySelector('[name="donemSelectVergi"]');

        if (yilSelect) {
            jQuery(yilSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/vergi-raporu&yil=' + encodeURIComponent(this.value);
            });
        }
        if (donemSelect) {
            jQuery(donemSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/vergi-raporu&donem=' + encodeURIComponent(this.value);
            });
        }
    });
</script>
