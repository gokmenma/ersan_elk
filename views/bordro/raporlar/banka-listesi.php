<?php

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\BordroParametreModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();
$BordroParametre = new BordroParametreModel();

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
$toplamBankaOdemesi = 0;
$bankaOdemeleri = [];

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);

        $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $selectedDonem->baslangic_tarihi) ?? 17002.12;

        foreach ($personeller as $personel) {
            $ortak = $BordroPersonel->hesaplaOrtakGosterimDegerleri($personel, $selectedDonem, floatval($asgariUcretNet));
            if (empty($ortak['hesaplanmis'])) {
                $bankaOdemeleri[$personel->id] = null;
                continue;
            }
            $bankaOdemesi = floatval($ortak['bankaOdemesi'] ?? 0);

            $bankaOdemeleri[$personel->id] = $bankaOdemesi;
            $toplamBankaOdemesi += $bankaOdemesi;
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

$hazirSayisi = 0;
$ibanEksikSayisi = 0;
$odemesizSayisi = 0;
$hazirTutar = 0;
$ibanEksikTutar = 0;
foreach ($personeller as $p) {
    $tutar = floatval($bankaOdemeleri[$p->id] ?? 0);
    if ($tutar > 0 && !empty($p->iban_numarasi)) {
        $hazirSayisi++;
        $hazirTutar += $tutar;
    } elseif ($tutar > 0) {
        $ibanEksikSayisi++;
        $ibanEksikTutar += $tutar;
    } else {
        $odemesizSayisi++;
    }
}

$personelSayisi = count($personeller);
$fmt = fn($v) => number_format((float) $v, 2, ',', '.');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$donemAdi = $selectedDonem ? $selectedDonem->donem_adi : '';
$donemIdQuery = $selectedDonemId ? urlencode((string) $selectedDonemId) : '';
?>
<script>try { document.documentElement.classList.toggle('bankalistesi-summary-hidden', localStorage.getItem('bankalistesi_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.bankalistesi-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.banka-period-select .form-floating { margin-bottom: 0; }
#bankaListesiTable tbody tr.banka-iban-eksik td { background-color: #fffbeb !important; }
#bankaListesiTable tfoot th { background-color: #f8fafc !important; border-top: 2px solid #e2e8f0 !important; font-size: 13px; font-weight: 700; color: #1e293b; }
@media print {
    .personel-action-toolbar, #summaryCardsContainer, #btnHeaderPrint, #btnHeaderExportExcel { display: none !important; }
}
</style>

<?php
$maintitle = "Bordro";
$subtitle = "Raporlar";
$title = "Banka Listesi";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-xl-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bxs-bank fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Banka Listesi</h4>
                <p class="text-muted mb-0 font-size-12">Bankaya gönderilecek dönem ödeme listesi ve IBAN durumu</p>
            </div>
            <?php if ($selectedDonem): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($donemAdi) ?></span>
            <?php endif; ?>
        </div>

        <div class="personel-action-toolbar col-xl-7 col-12 d-flex align-items-center justify-content-xl-end flex-wrap gap-2 mt-2 mt-xl-0">
            <div class="banka-period-select">
                <?= Form::FormSelect2(
                    name: 'yilSelectBanka',
                    options: $yil_option,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width: 120px;'
                ) ?>
            </div>
            <div class="banka-period-select">
                <?= Form::FormSelect2(
                    name: 'donemSelectBanka',
                    options: $donem_option,
                    selectedValue: $selectedDonemId,
                    label: 'Dönem',
                    icon: 'calendar',
                    style: 'min-width: 180px;'
                ) ?>
            </div>

            <?php if ($selectedDonem): ?>
                <a href="views/bordro/excel-banka-export.php?donem_id=<?= $e($donemIdQuery) ?>" class="btn btn-primary top-action-btn shadow-sm text-white">
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
                            <div><span class="summary-kpi-label">TOPLAM PERSONEL</span><h3 class="summary-kpi-value my-1"><?= (int) $personelSayisi ?></h3></div>
                            <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-user-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Ödemesi olmayan: <?= (int) $odemesizSayisi ?></span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Dönem Kadrosu</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">TOPLAM BANKA ÖDEMESİ</span><h3 class="summary-kpi-value my-1 text-success"><?= $fmt($toplamBankaOdemesi) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bxs-bank"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) ($hazirSayisi + $ibanEksikSayisi) ?> kişiye ödeme</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam Tutar</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">ÖDEMEYE HAZIR</span><h3 class="summary-kpi-value my-1 text-info"><?= (int) $hazirSayisi ?></h3></div>
                            <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                <i class="bx bx-check-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= $fmt($hazirTutar) ?> ₺</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">IBAN Tam</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">IBAN EKSİK</span><h3 class="summary-kpi-value my-1 <?= $ibanEksikSayisi > 0 ? 'text-warning' : '' ?>"><?= (int) $ibanEksikSayisi ?></h3></div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bx-error-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= $fmt($ibanEksikTutar) ?> ₺ bekliyor</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e(date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi))) ?> - <?= $e(date('d.m.Y', strtotime($selectedDonem->bitis_tarihi))) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($ibanEksikSayisi > 0): ?>
            <div class="alert alert-warning mb-3">
                <i class="bx bx-error-circle me-2"></i>
                <strong>Dikkat:</strong> <?= (int) $ibanEksikSayisi ?> personelin banka ödemesi var fakat IBAN bilgisi tanımlı değil. Lütfen personel bilgilerini güncelleyiniz.
            </div>
        <?php endif; ?>

        <div class="card summary-kpi-card mb-3" id="bankaListesiCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Banka Ödeme Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= $e($donemAdi) ?> dönemi personel banka ödemeleri</p>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                    <a href="views/bordro/excel-banka-export.php?donem_id=<?= $e($donemIdQuery) ?>" class="btn btn-sm btn-subtle-success px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderExportExcel" title="Excel'e Aktar">
                        <i class="bx bx-file font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Excel</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-subtle-secondary px-2.5 py-1.5 d-flex align-items-center gap-1 rounded-3 fw-semibold shadow-xs" id="btnHeaderPrint" title="Tabloyu Yazdır">
                        <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                    </button>
                </div>
            </div>

            <div class="card-body p-3 pt-0">
                <div class="table-responsive" style="overflow-x: auto !important;">
                    <table id="bankaListesiTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0 datatable-deferred">
                        <thead>
                            <tr>
                                <th data-filter="none" style="width: 50px;" class="text-center">#</th>
                                <th data-filter="string">AD SOYAD</th>
                                <th data-filter="string" style="width: 120px;">TC KİMLİK NO</th>
                                <th data-filter="string">IBAN</th>
                                <th data-filter="string" class="text-end">BANKA ÖDEMESİ</th>
                                <th data-filter="select" class="text-center" style="width: 130px;">DURUM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sira = 1;
                            foreach ($personeller as $personel):
                                $hesaplanmis = array_key_exists($personel->id, $bankaOdemeleri) && $bankaOdemeleri[$personel->id] !== null;
                                $bankaOdemesi = $hesaplanmis ? floatval($bankaOdemeleri[$personel->id]) : 0.0;
                                $ibanDolu = !empty($personel->iban_numarasi);
                                $avatar = (!empty($personel->resim_yolu) && is_file($personel->resim_yolu)) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg';
                                ?>
                                <tr class="<?= !$ibanDolu && $bankaOdemesi > 0 ? 'banka-iban-eksik' : '' ?>">
                                    <td class="text-center"><span class="row-index-badge"><?= $sira++ ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= $e($avatar) ?>" alt="" class="rounded-circle flex-shrink-0" style="width: 28px; height: 28px; object-fit: cover;">
                                            <span class="fw-medium text-dark text-nowrap"><?= $e($personel->adi_soyadi) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="text-muted text-nowrap" style="font-family: monospace;"><?= $e($personel->tc_kimlik_no ?? '-') ?></span></td>
                                    <td class="text-nowrap">
                                        <?php if ($ibanDolu): ?>
                                            <span class="text-primary" style="font-family: monospace;"><?= $e(App\Helper\Helper::formatIban($personel->iban_numarasi)) ?></span>
                                        <?php else: ?>
                                            <span class="text-danger"><i class="bx bx-error-circle me-1"></i>IBAN Tanımlı Değil</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap" data-order="<?= (float) $bankaOdemesi ?>">
                                        <?php if (!$hesaplanmis): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Hesaplanmadı</span>
                                        <?php elseif ($bankaOdemesi > 0): ?>
                                            <span class="fw-bold text-success"><?= $fmt($bankaOdemesi) ?> ₺</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($bankaOdemesi > 0 && $ibanDolu): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-check me-1"></i>Hazır</span>
                                        <?php elseif ($bankaOdemesi > 0): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-error me-1"></i>IBAN Eksik</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><i class="bx bx-minus me-1"></i>Ödeme Yok</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">TOPLAM BANKA ÖDEMESİ:</th>
                                <th class="text-end text-nowrap text-success"><?= $fmt($toplamBankaOdemesi) ?> ₺</th>
                                <th></th>
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
                    <i class="bx bx-user-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Personel Bulunamadı</h5>
                <p class="text-muted font-size-12 mb-0">Bu dönemde henüz personel bulunmamaktadır.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card summary-kpi-card mb-3">
            <div class="card-body text-center py-5">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bx bx-calendar-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Dönem Seçilmedi</h5>
                <p class="text-muted font-size-12 mb-0">Banka listesini görüntülemek için bir dönem seçiniz.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('btnToggleSummaryCards');
        const root = document.documentElement;
        const updateToggleState = function () {
            const isHidden = root.classList.contains('bankalistesi-summary-hidden');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
            }
        };
        updateToggleState();
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const willHide = !root.classList.contains('bankalistesi-summary-hidden');
                root.classList.toggle('bankalistesi-summary-hidden', willHide);
                try { localStorage.setItem('bankalistesi_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
                updateToggleState();
            });
        }

        const tableEl = document.getElementById('bankaListesiTable');
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

        const yilSelect = document.querySelector('[name="yilSelectBanka"]');
        const donemSelect = document.querySelector('[name="donemSelectBanka"]');

        if (yilSelect) {
            jQuery(yilSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/banka-listesi&yil=' + encodeURIComponent(this.value);
            });
        }
        if (donemSelect) {
            jQuery(donemSelect).on('change', function () {
                window.location.href = 'index?p=bordro/raporlar/banka-listesi&donem=' + encodeURIComponent(this.value);
            });
        }
    });
</script>
