<?php
use App\Model\BordroDonemModel;
use App\Model\PersonelEkOdemelerModel;
use App\Model\BordroParametreModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$EkOdemelerModel = new PersonelEkOdemelerModel();
$BordroParametreModel = new BordroParametreModel();

$selectedDonemId = $_GET['donem'] ?? null;
$requestedYil = isset($_GET['yil']) ? (int) $_GET['yil'] : 0;
$selectedTur = $_GET['tur'] ?? '';

if (!$selectedDonemId && $requestedYil > 0) {
    $yilDonemleri = $BordroDonem->getAllDonems($requestedYil);
    $selectedDonemId = $yilDonemleri[0]->id ?? null;
}
if (!$selectedDonemId) {
    $selectedDonemId = $_SESSION['selectedDonemId'] ?? null;
}

$selectedDonem = null;
$ekOdemeler = [];
$toplamTutar = 0;
$durumSayi = ['onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0];
$surekliSayi = 0;
$oranliSayi = 0;
$personelIdleri = [];

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $ekOdemeler = $EkOdemelerModel->getDonemEkOdemelerRaporu($selectedDonemId, $selectedTur);
        foreach ($ekOdemeler as $k) {
            $toplamTutar += floatval($k->tutar ?? 0);
            $durumKey = in_array($k->durum, ['onaylandi', 'reddedildi'], true) ? $k->durum : 'beklemede';
            $durumSayi[$durumKey]++;
            if (($k->tekrar_tipi ?? 'tek_sefer') === 'surekli') {
                $surekliSayi++;
            }
            if (($k->hesaplama_tipi ?? 'sabit') !== 'sabit') {
                $oranliSayi++;
            }
            $personelIdleri[$k->personel_id ?? $k->tc_kimlik_no ?? $k->id] = true;
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

// Gelir türlerini al (Filtre için)
$gelir_turleri = $BordroParametreModel->getGelirTurleri();
$tur_option = ['' => 'Tüm Ek Ödemeler'];
foreach ($gelir_turleri as $tur) {
    $tur_option[$tur->kod] = $tur->etiket;
}

$kayitSayisi = count($ekOdemeler);
$hesapEtiketleri = ['sabit' => 'Sabit', 'oran_net' => 'Net %', 'oran_brut' => 'Brüt %'];
$durumEtiketleri = ['onaylandi' => 'Onaylandı', 'beklemede' => 'Beklemede', 'reddedildi' => 'Reddedildi'];
$gruplar = [];
foreach ($ekOdemeler as $k) {
    $grupTur = $k->parametre_adi ?? ucfirst($k->tur ?? 'Diğer');
    $grupKey = ($k->personel_id ?? $k->tc_kimlik_no ?? $k->id) . '|' . $grupTur;
    if (!isset($gruplar[$grupKey])) {
        $gruplar[$grupKey] = [
            'adi_soyadi' => $k->adi_soyadi ?? '-',
            'tc' => $k->tc_kimlik_no ?? '-',
            'departman' => $k->departman ?? '-',
            'tur' => $grupTur,
            'toplam' => 0.0,
            'oranli' => 0,
            'durumlar' => ['onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0],
            'detay' => [],
        ];
    }
    $hTip = $k->hesaplama_tipi ?? 'sabit';
    $dKey = in_array($k->durum, ['onaylandi', 'reddedildi'], true) ? $k->durum : 'beklemede';
    $gruplar[$grupKey]['durumlar'][$dKey]++;
    if ($hTip === 'sabit') {
        $gruplar[$grupKey]['toplam'] += floatval($k->tutar ?? 0);
        $degerMetin = number_format(floatval($k->tutar ?? 0), 2, ',', '.') . ' ₺';
    } else {
        $gruplar[$grupKey]['oranli']++;
        $degerMetin = '%' . number_format(floatval($k->oran ?? 0), 2, ',', '.');
    }
    $gruplar[$grupKey]['detay'][] = [
        'aciklama' => (string) ($k->aciklama ?? '-'),
        'surekli' => ($k->tekrar_tipi ?? 'tek_sefer') === 'surekli',
        'hesaplama' => $hesapEtiketleri[$hTip] ?? 'Sabit',
        'deger' => $degerMetin,
        'durum' => $durumEtiketleri[$dKey],
        'durum_key' => $dKey,
    ];
}
$grupSayisi = count($gruplar);
$personelSayisi = count($personelIdleri);
$fmt = fn($v) => number_format((float) $v, 2, ',', '.');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$donemAdi = $selectedDonem ? $selectedDonem->donem_adi : '';
?>
<script>try { document.documentElement.classList.toggle('ekodemeraporu-summary-hidden', localStorage.getItem('ekodemeraporu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.ekodemeraporu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.rapor-period-select .form-floating { margin-bottom: 0; }
#ekOdemelerRaporuTable tfoot th { background-color: #f8fafc !important; border-top: 2px solid #e2e8f0 !important; font-size: 13px; font-weight: 700; color: #1e293b; }
.copyable:hover { background-color: rgba(71, 85, 105, 0.08); border-radius: 4px; padding: 0 2px; transition: all 0.2s; }
@media print {
    .personel-action-toolbar, #summaryCardsContainer, #btnHeaderPrint { display: none !important; }
}
</style>
<?php
$maintitle = "Bordro";
$subtitle = "Raporlar";
$title = "Ek Ödemeler Raporu";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-xl-4 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-money fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Ek Ödemeler Raporu</h4>
                <p class="text-muted mb-0 font-size-12">Dönem bazlı personel ek ödemeleri, hesaplama tipi ve onay durumu</p>
            </div>
            <?php if ($selectedDonem): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($donemAdi) ?></span>
            <?php endif; ?>
        </div>

        <div class="personel-action-toolbar col-xl-8 col-12 d-flex align-items-center justify-content-xl-end flex-wrap gap-2 mt-2 mt-xl-0">
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'turSelectEkOdeme',
                    options: $tur_option,
                    selectedValue: $selectedTur,
                    label: 'Ek Ödeme Türü',
                    icon: 'filter',
                    style: 'min-width: 190px;'
                ) ?>
            </div>
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'yilSelectEkOdeme',
                    options: $yil_option,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width: 120px;'
                ) ?>
            </div>
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'donemSelectEkOdeme',
                    options: $donem_option,
                    selectedValue: $selectedDonemId,
                    label: 'Dönem',
                    icon: 'calendar',
                    style: 'min-width: 180px;'
                ) ?>
            </div>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnExportCustomExcelEkOdeme">
                <i class="bx bx-file font-size-16"></i> Excel'e Aktar
            </button>

            <a href="index?p=bordro/raporlar&donem=<?= $e($selectedDonemId) ?>" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm">
                <i class="bx bx-arrow-back font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Raporlara Dön</span>
            </a>

            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet Kartları Göster/Gizle" aria-label="Özet Kartları Göster/Gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

<?php if ($selectedDonem && !empty($ekOdemeler)): ?>
        <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">TOPLAM EK ÖDEME (SABİT)</span><h3 class="summary-kpi-value my-1 text-success"><?= $fmt($toplamTutar) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-money"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Oranlı kayıt: <?= (int) $oranliSayi ?></span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Tutar</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">KAYIT SAYISI</span><h3 class="summary-kpi-value my-1 "><?= (int) $kayitSayisi ?></h3></div>
                            <div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bx bx-list-ul"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $personelSayisi ?> personel</span>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kayıt</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">ONAYLANAN</span><h3 class="summary-kpi-value my-1 text-info"><?= (int) $durumSayi['onaylandi'] ?></h3></div>
                            <div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle">
                                <i class="bx bx-check-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Beklemede <?= (int) $durumSayi['beklemede'] ?> | Reddedilen <?= (int) $durumSayi['reddedildi'] ?></span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Onay</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">SÜREKLİ ÖDEME</span><h3 class="summary-kpi-value my-1 text-warning"><?= (int) $surekliSayi ?></h3></div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bx-refresh"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext">Tek sefer: <?= (int) ($kayitSayisi - $surekliSayi) ?></span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Tekrar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card summary-kpi-card mb-3" id="ekOdemeListCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Ek Ödeme Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= $e($donemAdi) ?> dönemi personel ek ödemeleri (<?= (int) $grupSayisi ?> grup, <?= (int) $kayitSayisi ?> kayıt)</p>
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
                            <table id="ekOdemelerRaporuTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th data-filter="none" style="width: 70px;" class="text-center">#</th>
                                        <th data-filter="string">PERSONEL BİLGİLERİ</th>
                                        <th data-filter="string">DEPARTMAN</th>
                                        <th data-filter="select">ÖDEME TÜRÜ</th>
                                        <th data-filter="string" class="text-center">KAYIT</th>
                                        <th data-filter="select">HESAPLAMA</th>
                                        <th data-filter="string" class="text-end">TOPLAM TUTAR</th>
                                        <th data-filter="select" class="text-center">DURUM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $sira = 1; foreach ($gruplar as $g):
                                        $detayJson = json_encode($g['detay'], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
                                        $kayitAdet = count($g['detay']);
                                        ?>
                                        <tr class="ek-grup-row" data-detay="<?= $e($detayJson) ?>">
                                            <td class="text-center text-nowrap">
                                                <button type="button" class="btn btn-sm btn-subtle-primary table-action-btn ek-grup-toggle" title="Kayıtları göster/gizle" aria-expanded="false"><i class="bx bx-chevron-right"></i></button>
                                                <span class="row-index-badge ms-1"><?= $sira++ ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <h6 class="mb-0 fw-medium text-dark font-size-13 text-nowrap"><?= $e($g['adi_soyadi']) ?></h6>
                                                    <span class="text-secondary font-size-12 text-nowrap">TC: <code><?= $e($g['tc']) ?></code></span>
                                                </div>
                                            </td>
                                            <td><?= $e($g['departman']) ?></td>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($g['tur']) ?></span></td>
                                            <td class="text-center" data-order="<?= (int) $kayitAdet ?>"><span class="badge-stat-tag"><?= (int) $kayitAdet ?></span></td>
                                            <td class="text-nowrap"><?= $g['oranli'] > 0 ? ($g['oranli'] === $kayitAdet ? 'Oranlı' : 'Sabit + Oranlı') : 'Sabit' ?></td>
                                            <td class="text-end text-nowrap fw-bold text-success" data-order="<?= (float) $g['toplam'] ?>">
                                                <?php if ($g['toplam'] > 0 || $g['oranli'] === 0): ?>
                                                    <?= $fmt($g['toplam']) ?> ₺
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php foreach ($g['durumlar'] as $dKey => $dAdet): if ($dAdet < 1) continue;
                                                    $dCls = $dKey === 'onaylandi' ? 'bg-success-subtle text-success border border-success-subtle' : ($dKey === 'reddedildi' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'); ?>
                                                    <span class="badge <?= $dCls ?> rounded-pill px-2 py-1 font-size-11 fw-semibold text-nowrap"><?= $e($durumEtiketleri[$dKey]) ?><?= $kayitAdet > 1 ? ' · ' . (int) $dAdet : '' ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-end">TOPLAM EK ÖDEME TUTARI (YALNIZCA SABİT TUTARLAR):</th>
                                        <th class="text-end text-nowrap text-success"><?= $fmt($toplamTutar) ?> ₺</th>
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
                    <i class="bx bx-pie-chart-alt-2 fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Kayıt Bulunamadı</h5>
                <p class="text-muted font-size-12 mb-0">Bu dönemde / kriterde henüz oluşturulmuş ek ödeme kaydı bulunmamaktadır.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card summary-kpi-card mb-3">
            <div class="card-body text-center py-5">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bx bx-calendar-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Dönem Seçimi Bekleniyor</h5>
                <p class="text-muted font-size-12 mb-0">Ek ödemeler raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        const toggleBtn = document.getElementById('btnToggleSummaryCards');
        const root = document.documentElement;
        const updateToggleState = function () {
            const isHidden = root.classList.contains('ekodemeraporu-summary-hidden');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
            }
        };
        updateToggleState();
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const willHide = !root.classList.contains('ekodemeraporu-summary-hidden');
                root.classList.toggle('ekodemeraporu-summary-hidden', willHide);
                try { localStorage.setItem('ekodemeraporu_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
                updateToggleState();
            });
        }
        const printBtn = document.getElementById('btnHeaderPrint');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }

        const escHtml = function (v) {
            return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        };
        const detayOku = function (node) {
            try { return JSON.parse(node.getAttribute('data-detay') || '[]'); } catch (e) { return []; }
        };
        const detayHtml = function (items) {
            let html = '<div class="p-2 ps-5"><table class="table table-sm table-bordered align-middle mb-0 bg-white"><thead><tr class="text-secondary font-size-12"><th>AÇIKLAMA / DETAY</th><th style="width:130px;">HESAPLAMA</th><th class="text-end" style="width:150px;">TUTAR / ORAN</th><th class="text-center" style="width:130px;">DURUM</th></tr></thead><tbody>';
            items.forEach(function (it) {
                const cls = it.durum_key === 'onaylandi' ? 'bg-success-subtle text-success border border-success-subtle' : (it.durum_key === 'reddedildi' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle');
                html += '<tr' + (it.durum_key === 'reddedildi' ? ' class="text-muted"' : '') + '>'
                    + '<td class="font-size-13">' + escHtml(it.aciklama) + (it.surekli ? ' <span class="text-secondary font-size-12"><i class="bx bx-refresh"></i> Sürekli</span>' : '') + '</td>'
                    + '<td class="font-size-13">' + escHtml(it.hesaplama) + '</td>'
                    + '<td class="text-end text-nowrap fw-bold text-success font-size-13">' + escHtml(it.deger) + '</td>'
                    + '<td class="text-center"><span class="badge ' + cls + ' rounded-pill px-2 py-1 font-size-11 fw-semibold">' + escHtml(it.durum) + '</span></td>'
                    + '</tr>';
            });
            return html + '</tbody></table></div>';
        };

        let dt = null;
        if (document.getElementById('ekOdemelerRaporuTable')) {
            dt = jQuery('#ekOdemelerRaporuTable').DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: 0, orderable: false, searchable: false }
                ]
            }));

            $('#ekOdemelerRaporuTable tbody').on('click', '.ek-grup-toggle', function (ev) {
                ev.stopPropagation();
                const tr = $(this).closest('tr');
                const row = dt.row(tr);
                const icon = $(this).find('i');
                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    icon.attr('class', 'bx bx-chevron-right');
                    $(this).attr('aria-expanded', 'false');
                } else {
                    row.child(detayHtml(detayOku(tr[0]))).show();
                    tr.addClass('shown');
                    icon.attr('class', 'bx bx-chevron-down');
                    $(this).attr('aria-expanded', 'true');
                }
            });
        }

        $('#btnExportCustomExcelEkOdeme').on('click', function () {
            if (!dt || !dt.data().any()) {
                Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Dışa aktarılacak veri bulunamadı.', confirmButtonText: 'Tamam' });
                return;
            }

            let html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1"><tr>'
                + '<th>SNO</th><th>TC Kimlik No</th><th>Ad Soyad</th><th>Departman</th><th>Ödeme Türü</th><th>Açıklama Detay</th><th>Hesaplama</th><th>Tutar / Oran</th><th>Durum</th></tr>';

            let sira = 1;
            dt.rows({ filter: 'applied' }).every(function () {
                const node = this.node();
                const tc = $(node).find('td:eq(1) code').text().trim();
                const adSoyad = $(node).find('td:eq(1) h6').text().trim();
                const departman = $(node).find('td:eq(2)').text().trim();
                const tur = $(node).find('td:eq(3)').text().trim();
                detayOku(node).forEach(function (it) {
                    html += '<tr><td>' + sira++ + '</td>'
                        + '<td style="mso-number-format:\'@\';">' + escHtml(tc) + '</td>'
                        + '<td>' + escHtml(adSoyad) + '</td>'
                        + '<td>' + escHtml(departman) + '</td>'
                        + '<td>' + escHtml(tur) + '</td>'
                        + '<td>' + escHtml(it.aciklama) + '</td>'
                        + '<td>' + escHtml(it.hesaplama) + '</td>'
                        + '<td>' + escHtml(it.deger) + '</td>'
                        + '<td>' + escHtml(it.durum) + '</td></tr>';
                });
            });
            html += '</table></body></html>';

            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'Ek_Odemeler_Raporu_<?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : date("Y_m_d") ?>.xls';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        });

        function goTo(params) {
            const q = Object.entries(params).filter(function (kv) { return kv[1]; }).map(function (kv) { return kv[0] + '=' + encodeURIComponent(kv[1]); });
            window.location.href = 'index?p=bordro/raporlar/ek-odemeler-raporu' + (q.length ? '&' + q.join('&') : '');
        }

        $('[name="yilSelectEkOdeme"]').on('change', function () { goTo({ yil: this.value, tur: $('[name="turSelectEkOdeme"]').val() }); });
        $('[name="donemSelectEkOdeme"]').on('change', function () { goTo({ donem: this.value, tur: $('[name="turSelectEkOdeme"]').val() }); });
        $('[name="turSelectEkOdeme"]').on('change', function () { goTo({ donem: $('[name="donemSelectEkOdeme"]').val(), tur: this.value }); });
    });
</script>
