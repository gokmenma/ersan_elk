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

$toplamBrutMaas = 0;
$toplamSgkIsveren = 0;
$toplamSecSgkIsveren = 0;
$toplamIssizlikIsveren = 0;
$genelToplamMaliyet = 0;
$toplamNetMaas = 0;
$toplamIsciPayi = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonemDetayli($selectedDonemId);

        foreach ($personeller as $personel) {
            $toplamBrutMaas += floatval($personel->brut_maas ?? 0);
            $toplamSecSgkIsveren += floatval($personel->sgk_isveren ?? 0);
            $toplamIssizlikIsveren += floatval($personel->issizlik_isveren ?? 0);
            $genelToplamMaliyet += floatval($personel->toplam_maliyet ?? 0);
            $toplamNetMaas += floatval($personel->net_maas ?? 0);
            $toplamIsciPayi += floatval($personel->sgk_isci ?? 0) + floatval($personel->issizlik_isci ?? 0);
        }
        $toplamSgkIsveren = $toplamSecSgkIsveren + $toplamIssizlikIsveren;
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
?>
<script>try { document.documentElement.classList.toggle('maliyetraporu-summary-hidden', localStorage.getItem('maliyetraporu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.maliyetraporu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.maliyet-period-select .form-floating { margin-bottom: 0; }
#maliyetListesiTable tbody td.maliyet-col-toplam { background-color: #f8fafc !important; }
#maliyetListesiTable tfoot th { background-color: #f8fafc !important; border-top: 2px solid #e2e8f0 !important; font-size: 13px; font-weight: 700; color: #1e293b; }
@media print {
    .personel-action-toolbar, #summaryCardsContainer, #btnHeaderPrint { display: none !important; }
}
</style>

<?php
$maintitle = "Bordro";
$subtitle = "Raporlar";
$title = "Maliyet Raporu";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-xl-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-pie-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Maliyet Raporu</h4>
                <p class="text-muted mb-0 font-size-12">Personel bazlı işveren maliyet analizi</p>
            </div>
            <?php if ($selectedDonem): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($donemAdi) ?></span>
            <?php endif; ?>
        </div>

        <div class="personel-action-toolbar col-xl-7 col-12 d-flex align-items-center justify-content-xl-end flex-wrap gap-2 mt-2 mt-xl-0">
            <div class="maliyet-period-select">
                <?= Form::FormSelect2(
                    name: 'yilSelectMaliyet',
                    options: $yil_option,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width: 120px;'
                ) ?>
            </div>
            <div class="maliyet-period-select">
                <?= Form::FormSelect2(
                    name: 'donemSelectMaliyet',
                    options: $donem_option,
                    selectedValue: $selectedDonemId,
                    label: 'Dönem',
                    icon: 'calendar',
                    style: 'min-width: 180px;'
                ) ?>
            </div>

            <a href="index?p=bordro/raporlar&donem=<?= $e($selectedDonemId) ?>" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
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
                            <div><span class="summary-kpi-label">TOPLAM PERSONEL</span><h3 class="summary-kpi-value my-1"><?= (int) $personelSayisi ?></h3></div>
                            <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-user-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Net maaş toplamı <?= $fmt($toplamNetMaas) ?></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kişi</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">TOPLAM BRÜT MAAŞ</span><h3 class="summary-kpi-value my-1 text-info"><?= $fmt($toplamBrutMaas) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                <i class="bx bx-wallet"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">İşçi SGK payı <?= $fmt($toplamIsciPayi) ?></span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Hakediş</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">SGK İŞVEREN PAYI (TOPLAM)</span><h3 class="summary-kpi-value my-1 text-warning"><?= $fmt($toplamSgkIsveren) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bx-buildings"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">SGK <?= $fmt($toplamSecSgkIsveren) ?> | İşsizlik <?= $fmt($toplamIssizlikIsveren) ?></span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Gider</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">GENEL TOPLAM MALİYET</span><h3 class="summary-kpi-value my-1 text-success"><?= $fmt($genelToplamMaliyet) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-line-chart"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">İşverene toplam yük</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card summary-kpi-card mb-3" id="maliyetListCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Personel Maliyet Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= $e($donemAdi) ?> dönemi işveren maliyet dökümü</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                        <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                    </button>
                </div>
            </div>

            <div class="card-body p-3 pt-0">
                <div class="table-responsive" style="overflow-x: auto !important;">
                    <table id="maliyetListesiTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="none" style="width: 50px;" class="text-center">#</th>
                                <th data-filter="string">PERSONEL</th>
                                <th data-filter="select" class="text-center">MAAŞ TİPİ</th>
                                <th data-filter="string" class="text-end">BRÜT MAAŞ</th>
                                <th data-filter="string" class="text-end">NET MAAŞ</th>
                                <th data-filter="string" class="text-end" title="SGK İşçi + İşsizlik İşçi Payı">İŞÇİ SGK PAYI</th>
                                <th data-filter="string" class="text-end" title="SGK İşveren Payı">İŞVEREN SGK PAYI</th>
                                <th data-filter="string" class="text-end" title="İşsizlik İşveren Payı">İŞV. İŞSİZLİK PAYI</th>
                                <th data-filter="string" class="text-end">TOPLAM MALİYET</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sira = 1; foreach ($personeller as $personel):
                                $brutMaas = floatval($personel->brut_maas ?? 0);
                                $netMaas = floatval($personel->net_maas ?? 0);
                                $isciSgk = floatval($personel->sgk_isci ?? 0) + floatval($personel->issizlik_isci ?? 0);
                                $isverenSgk = floatval($personel->sgk_isveren ?? 0);
                                $isverenIssizlik = floatval($personel->issizlik_isveren ?? 0);
                                $toplamMaliyet = floatval($personel->toplam_maliyet ?? 0);

                                $maasDurumuStr = '-';
                                if (!empty($personel->hesaplama_detay)) {
                                    $detay = json_decode($personel->hesaplama_detay, true);
                                    $maasDurumuStr = $detay['maas_durumu'] ?? '-';
                                }
                                ?>
                                <tr>
                                    <td class="text-center"><span class="row-index-badge"><?= $sira++ ?></span></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium text-dark text-nowrap"><?= $e($personel->adi_soyadi) ?></span>
                                            <span class="text-secondary font-size-12 text-nowrap">TC: <?= $e($personel->tc_kimlik_no ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><span class="badge-group"><?= $e($maasDurumuStr) ?></span></td>
                                    <td class="text-end text-nowrap fw-medium text-dark" data-order="<?= $brutMaas ?>"><?= $fmt($brutMaas) ?> ₺</td>
                                    <td class="text-end text-nowrap fw-medium text-success" data-order="<?= $netMaas ?>"><?= $fmt($netMaas) ?> ₺</td>
                                    <td class="text-end text-nowrap text-muted" data-order="<?= $isciSgk ?>"><?= $fmt($isciSgk) ?> ₺</td>
                                    <td class="text-end text-nowrap fw-medium text-warning" data-order="<?= $isverenSgk ?>"><?= $fmt($isverenSgk) ?> ₺</td>
                                    <td class="text-end text-nowrap fw-medium text-warning" data-order="<?= $isverenIssizlik ?>"><?= $fmt($isverenIssizlik) ?> ₺</td>
                                    <td class="text-end text-nowrap fw-bold text-primary maliyet-col-toplam" data-order="<?= $toplamMaliyet ?>"><?= $fmt($toplamMaliyet) ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">GENEL TOPLAMLAR:</th>
                                <th class="text-end text-nowrap text-dark"><?= $fmt($toplamBrutMaas) ?> ₺</th>
                                <th class="text-end text-nowrap text-success"><?= $fmt($toplamNetMaas) ?> ₺</th>
                                <th class="text-end text-nowrap"><?= $fmt($toplamIsciPayi) ?> ₺</th>
                                <th class="text-end text-nowrap text-warning"><?= $fmt($toplamSecSgkIsveren) ?> ₺</th>
                                <th class="text-end text-nowrap text-warning"><?= $fmt($toplamIssizlikIsveren) ?> ₺</th>
                                <th class="text-end text-nowrap text-primary"><?= $fmt($genelToplamMaliyet) ?> ₺</th>
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
                    <i class="bx bx-folder-open fs-2"></i>
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
                <p class="text-muted font-size-12 mb-0">İşveren maliyet raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('btnToggleSummaryCards');
        const root = document.documentElement;
        const updateToggleState = function () {
            const isHidden = root.classList.contains('maliyetraporu-summary-hidden');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
            }
        };
        updateToggleState();
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const willHide = !root.classList.contains('maliyetraporu-summary-hidden');
                root.classList.toggle('maliyetraporu-summary-hidden', willHide);
                try { localStorage.setItem('maliyetraporu_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
                updateToggleState();
            });
        }

        const tableEl = document.getElementById('maliyetListesiTable');
        if (tableEl && window.jQuery && jQuery.fn.DataTable && !jQuery.fn.DataTable.isDataTable(tableEl)) {
            jQuery(tableEl).DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: 0, orderable: false, searchable: false }
                ]
            }));
        }

        const printBtn = document.getElementById('btnHeaderPrint');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }

        const yilSelect = document.querySelector('[name="yilSelectMaliyet"]');
        const donemSelect = document.querySelector('[name="donemSelectMaliyet"]');

        if (yilSelect) {
            jQuery(yilSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/maliyet-raporu&yil=' + encodeURIComponent(this.value);
            });
        }
        if (donemSelect) {
            jQuery(donemSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/maliyet-raporu&donem=' + encodeURIComponent(this.value);
            });
        }
    });
</script>
