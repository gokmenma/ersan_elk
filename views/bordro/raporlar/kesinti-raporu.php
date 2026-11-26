<?php
use App\Model\BordroDonemModel;
use App\Model\PersonelKesintileriModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$KesintilerModel = new PersonelKesintileriModel();

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
$kesintiler = [];
$toplamTutar = 0;
$turSayi = ['icra' => 0, 'avans' => 0, 'diger' => 0];
$turTutar = ['icra' => 0, 'avans' => 0, 'diger' => 0];
$odenenSayi = 0;
$odenenTutar = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $kesintiler = $KesintilerModel->getDonemKesintileriRaporu($selectedDonemId, $selectedTur);
        foreach ($kesintiler as $k) {
            $tutarK = floatval($k->tutar ?? 0);
            $toplamTutar += $tutarK;
            $turKey = mb_strtolower(trim((string) $k->tur), 'UTF-8');
            $turKey = ($turKey === 'icra' || $turKey === 'i̇cra') ? 'icra' : ($turKey === 'avans' ? 'avans' : 'diger');
            $turSayi[$turKey]++;
            $turTutar[$turKey] += $tutarK;
            if (($k->odeme_durumu ?? '') === 'odendi') {
                $odenenSayi++;
                $odenenTutar += $tutarK;
            }
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

$tur_option = [
    '' => 'Tüm Kesintiler',
    'icra' => 'İcra Kesintileri',
    'avans' => 'Avans Kesintileri',
    'diger' => 'Diğer Kesintiler'
];

$kayitSayisi = count($kesintiler);
$fmt = fn($v) => number_format((float) $v, 2, ',', '.');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$donemAdi = $selectedDonem ? $selectedDonem->donem_adi : '';
?>
<script>try { document.documentElement.classList.toggle('kesintiraporu-summary-hidden', localStorage.getItem('kesintiraporu_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.kesintiraporu-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.rapor-period-select .form-floating { margin-bottom: 0; }
#kesintiRaporuTable tfoot th { background-color: #f8fafc !important; border-top: 2px solid #e2e8f0 !important; font-size: 13px; font-weight: 700; color: #1e293b; }
.copyable:hover { background-color: rgba(71, 85, 105, 0.08); border-radius: 4px; padding: 0 2px; transition: all 0.2s; }
@media print {
    .personel-action-toolbar, #summaryCardsContainer, #btnHeaderPrint { display: none !important; }
}
</style>
<?php
$maintitle = "Bordro";
$subtitle = "Raporlar";
$title = "Kesinti Raporu";
include 'layouts/breadcrumb.php';
?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-xl-4 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-cut fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Kesinti Raporu</h4>
                <p class="text-muted mb-0 font-size-12">Dönem bazlı icra, avans ve diğer personel kesintileri</p>
            </div>
            <?php if ($selectedDonem): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $e($donemAdi) ?></span>
            <?php endif; ?>
        </div>

        <div class="personel-action-toolbar col-xl-8 col-12 d-flex align-items-center justify-content-xl-end flex-wrap gap-2 mt-2 mt-xl-0">
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'turSelectRapor',
                    options: $tur_option,
                    selectedValue: $selectedTur,
                    label: 'Kesinti Türü',
                    icon: 'filter',
                    style: 'min-width: 170px;'
                ) ?>
            </div>
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'yilSelectRapor',
                    options: $yil_option,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width: 120px;'
                ) ?>
            </div>
            <div class="rapor-period-select">
                <?= Form::FormSelect2(
                    name: 'donemSelectRapor',
                    options: $donem_option,
                    selectedValue: $selectedDonemId,
                    label: 'Dönem',
                    icon: 'calendar',
                    style: 'min-width: 180px;'
                ) ?>
            </div>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnExportCustomExcel">
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

<?php if ($selectedDonem && !empty($kesintiler)): ?>
        <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">TOPLAM KESİNTİ</span><h3 class="summary-kpi-value my-1 text-danger"><?= $fmt($toplamTutar) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bx bx-cut"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $kayitSayisi ?> kayıt | Diğer <?= $fmt($turTutar['diger']) ?></span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Toplam</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">İCRA KESİNTİLERİ</span><h3 class="summary-kpi-value my-1 text-danger"><?= $fmt($turTutar['icra']) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-danger-subtle text-danger border border-danger-subtle">
                                <i class="bx bx-building"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $turSayi['icra'] ?> kayıt</span>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">İcra</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">AVANS KESİNTİLERİ</span><h3 class="summary-kpi-value my-1 text-warning"><?= $fmt($turTutar['avans']) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle">
                                <i class="bx bx-wallet"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $turSayi['avans'] ?> kayıt</span>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Avans</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card summary-kpi-card h-100 mb-0">
                    <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start">
                            <div><span class="summary-kpi-label">ÖDENEN KESİNTİ</span><h3 class="summary-kpi-value my-1 text-success"><?= $fmt($odenenTutar) ?> ₺</h3></div>
                            <div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle">
                                <i class="bx bx-check-circle"></i>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-kpi-subtext"><?= (int) $odenenSayi ?> / <?= (int) $kayitSayisi ?> kayıt ödendi</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Ödeme</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card summary-kpi-card mb-3" id="kesintiListCard">
            <div class="card-header bg-transparent border-0 px-3 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-ul font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Kesinti Listesi</h5>
                        <p class="text-muted mb-0 font-size-12" style="margin-top: 2px;"><?= $e($donemAdi) ?> dönemi kesinti, ödeme ve dekont takibi</p>
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
                            <table id="kesintiRaporuTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th data-filter="none" style="width: 50px;" class="text-center">#</th>
                                        <th data-filter="string">PERSONEL BİLGİLERİ</th>
                                        <th data-filter="select">KESİNTİ TÜRÜ</th>
                                        <th data-filter="string">AÇIKLAMA / DETAY</th>
                                        <th data-filter="string">BANKA BİLGİLERİ</th>
                                        <th data-filter="string">ÖDEME AÇIKLAMASI</th>
                                        <th data-filter="string" class="text-end">TUTAR</th>
                                        <th data-filter="none" class="text-center">ÖDEME DURUMU</th>
                                        <th data-filter="none" class="text-center">DEKONT</th>
                                        <th data-filter="select" class="text-center">ONAY</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($kesintiler as $k):
                                        // Tür Rozeti Ayarı
                                        $badgeBg = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                        $turAdi = 'Diğer';
                                        $normalizedTur = mb_strtolower(trim($k->tur), 'UTF-8');
                                        if ($normalizedTur === 'icra' || $normalizedTur === 'i̇cra') { // account for dotted i
                                            $badgeBg = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $turAdi = 'İcra';
                                        } else if ($normalizedTur === 'avans') {
                                            $badgeBg = 'bg-warning-subtle text-warning border border-warning-subtle';
                                            $turAdi = 'Avans';
                                        }

                                        // Durum Rozeti Ayarı
                                        $durumBg = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                        $durumIcon = 'bx-time';
                                        $durumMetin = 'Beklemede';
                                        if ($k->durum === 'onaylandi' || $k->tur === 'icra') {
                                            $durumBg = 'bg-success-subtle text-success border border-success-subtle';
                                            $durumIcon = 'bx-check-circle';
                                            $durumMetin = 'Onaylandı';
                                            if($k->tur === 'icra') $durumMetin = 'Kesinleşti'; // icra ise bekleme yoktur
                                        } elseif ($k->durum === 'reddedildi') {
                                            $durumBg = 'bg-danger-subtle text-danger border border-danger-subtle';
                                            $durumIcon = 'bx-x-circle';
                                            $durumMetin = 'Reddedildi';
                                        }
                                        ?>
                                        <tr class="<?= $k->durum === 'reddedildi' ? 'text-muted' : '' ?>">
                                            <td class="text-center"><span class="row-index-badge"><?= $sira++ ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-0 fw-medium text-dark font-size-13 text-nowrap"><?= $e($k->adi_soyadi ?? '-') ?></h6>
                                                        <span class="text-secondary font-size-12 text-nowrap">TC: <code><?= $e($k->tc_kimlik_no ?? '-') ?></code></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $badgeBg ?> rounded-pill px-2 py-1 font-size-11 fw-semibold"><?= $turAdi ?></span>
                                            </td>
                                            <td>
                                                <div class="text-wrap" style="max-width:250px;">
                                                    <span class="fw-medium text-dark d-block">
                                                        <?= htmlspecialchars($k->aciklama ?? '-') ?>
                                                    </span>
                                                    <?php if ($k->tur === 'icra' && !empty($k->dosya_no)): ?>
                                                        <span class="text-muted small d-block mt-1">
                                                            <i class="bx bx-buildings me-1"></i><?= htmlspecialchars($k->icra_dairesi ?? '-') ?> (<?= htmlspecialchars($k->dosya_no) ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($k->tur === 'icra'): ?>
                                                    <div class="text-wrap" style="max-width:200px; font-size: 0.85rem;">
                                                        <?php if(!empty($k->iban)): ?>
                                                            <div class="d-flex align-items-center mb-1">
                                                                <code class="text-primary me-1 copyable" title="Kopyalamak için tıkla" style="cursor:pointer;"><?= htmlspecialchars($k->iban) ?></code>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="mb-1">
                                                            <span class="copyable fw-medium text-dark" title="Hesap adını kopyalamak için tıkla" style="cursor:pointer;">
                                                                <i class="bx bx-user me-1"></i><?= htmlspecialchars($k->icra_dairesi ?? '-') ?>
                                                            </span>
                                                        </div>
                                                        <?php if(!empty($k->hesap_bilgileri)): ?>
                                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                                <i class="bx bx-info-circle me-1"></i><?= htmlspecialchars($k->hesap_bilgileri) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($k->tur === 'icra'): ?>
                                                    <?php 
                                                        $odemeAciklama = ($k->dosya_no ?? '...') . " dosya numaralı, " . ($k->tc_kimlik_no ?? '...') . " tc kimlik numaralı " . ($k->adi_soyadi ?? '...') . " isimli personele ait icra ödemesi";
                                                    ?>
                                                    <div class="text-wrap" style="max-width:250px; font-size: 0.8rem;">
                                                        <span class="copyable text-muted" title="Kopyalamak için tıkla" style="cursor:pointer;"><?= htmlspecialchars($odemeAciklama) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end text-nowrap fw-bold text-danger" data-order="<?= (float) ($k->tutar ?? 0) ?>">
                                                <span class="<?= $k->durum === 'reddedildi' ? 'text-decoration-line-through' : '' ?>">
                                                    <?= number_format(floatval($k->tutar ?? 0), 2, ',', '.') ?> ₺
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input odeme-durum-toggle" type="checkbox" role="switch" 
                                                           data-id="<?= $k->id ?>" <?= $k->odeme_durumu === 'odendi' ? 'checked' : '' ?>>
                                                </div>
                                                <small class="text-muted odeme-durum-text" id="odemeDurumText_<?= $k->id ?>">
                                                    <?= $k->odeme_durumu === 'odendi' ? 'Ödendi' : 'Ödenmedi' ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($k->dekont_dosyasi)): ?>
                                                    <div class="d-flex flex-column align-items-center gap-1">
                                                        <div class="btn-group">
                                                            <a href="uploads/kesintiler/<?= htmlspecialchars($k->dekont_dosyasi) ?>" target="_blank" 
                                                            class="btn btn-sm btn-outline-info" title="Dekontu Görüntüle">
                                                                <i class="bx bx-show"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-dekont-sil" data-id="<?= $k->id ?>" title="Dekontu Sil">
                                                                <i class="bx bx-trash"></i>
                                                            </button>
                                                        </div>
                                                        <small class="text-muted text-truncate" style="max-width: 100px;" title="<?= htmlspecialchars($k->dekont_dosyasi) ?>">
                                                            <?= htmlspecialchars($k->dekont_dosyasi) ?>
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-dekont-yukle" data-id="<?= $k->id ?>" title="Dekont Yükle">
                                                        <i class="bx bx-upload me-1"></i> Yükle
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $durumBg ?> rounded-pill px-2 py-1 font-size-11 fw-semibold text-nowrap">
                                                    <i class="bx <?= $durumIcon ?> me-1 align-middle"></i><?= $durumMetin ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-end">TOPLAM KESİNTİ TUTARI:</th>
                                        <th class="text-end text-nowrap text-danger">
                                            <?= number_format($toplamTutar, 2, ',', '.') ?> ₺
                                        </th>
                                        <th colspan="3"></th>
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
                    <i class="bx bx-cut fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Kayıt Bulunamadı</h5>
                <p class="text-muted font-size-12 mb-0">Bu dönem ve filtreleme kriterlerine uygun kesinti kaydı bulunamadı.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card summary-kpi-card mb-3">
            <div class="card-body text-center py-5">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                    <i class="bx bx-calendar-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark font-size-14">Dönem Seçimi Bekleniyor</h5>
                <p class="text-muted font-size-12 mb-0">Raporu görüntülemek için yukarıdan bir dönem seçiniz.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('kesintiRaporuTable')) {
            jQuery('#kesintiRaporuTable').DataTable(applyLengthStateSave({
                ...getDatatableOptions(),
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: [0, 7, 8], orderable: false },
                    { targets: 0, searchable: false }
                ]
            }));
        }
        const toggleBtn = document.getElementById('btnToggleSummaryCards');
        const root = document.documentElement;
        const updateToggleState = function () {
            const isHidden = root.classList.contains('kesintiraporu-summary-hidden');
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                const icon = toggleBtn.querySelector('i');
                if (icon) icon.className = isHidden ? 'bx bx-chevron-down' : 'bx bx-chevron-up';
            }
        };
        updateToggleState();
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const willHide = !root.classList.contains('kesintiraporu-summary-hidden');
                root.classList.toggle('kesintiraporu-summary-hidden', willHide);
                try { localStorage.setItem('kesintiraporu_summary_cards_state', willHide ? 'hidden' : 'visible'); } catch (e) {}
                updateToggleState();
            });
        }
        const printBtn = document.getElementById('btnHeaderPrint');
        if (printBtn) {
            printBtn.addEventListener('click', function () { window.print(); });
        }

        // Custom Excel Export from Filtered DataTable rows
        $('#btnExportCustomExcel').on('click', function() {
            var table = $('#kesintiRaporuTable').DataTable();
            
            var html = '<table border="1"><thead><tr>' +
                       '<th>Sira</th><th>Personel Bilgileri</th><th>Kesinti Turu</th><th>Aciklama / Detay</th><th>IBAN</th><th>Hesap Bilgileri</th><th>Odeme Aciklamasi</th><th>Tutar</th><th>Durum</th>' +
                       '</tr></thead><tbody>';
            
            table.rows({ search: 'applied' }).nodes().each(function(node, index) {
                var row = $(node);
                var sira = row.find('td:eq(0)').text().trim();
                var adSoyad = row.find('td:eq(1)').find('h6').text().trim();
                var tc = row.find('td:eq(1)').find('code').text().trim();
                var personel = adSoyad + (tc ? ' (TC: ' + tc + ')' : '');
                
                var kesintiTuru = row.find('td:eq(2)').text().trim();
                
                // Keep only visible text in Aciklama, removing small classes if we want or just text
                var aciklamaWrapper = row.find('td:eq(3)').clone();
                aciklamaWrapper.find('.small, i').remove();
                var aciklama = aciklamaWrapper.text().trim();
                
                var iban = row.find('td:eq(4)').find('code').text().trim();
                var hesapAdi = row.find('td:eq(4)').find('span.copyable').text().trim();
                var hesapDetay = row.find('td:eq(4)').find('.text-muted').text().trim();
                var odemeAciklama = row.find('td:eq(5)').text().trim();
                
                // Get original text of Tutar without span styles
                var tutarCell = row.find('td:eq(6)');
                var tutarText = tutarCell.text().trim().replace(/₺/g, '').trim(); 
                
                var durum = row.find('td:eq(7)').text().trim();
                
                html += '<tr>' +
                        '<td>' + sira + '</td>' +
                        '<td>' + personel + '</td>' +
                        '<td>' + kesintiTuru + '</td>' +
                        '<td>' + aciklama + '</td>' +
                        '<td>' + iban + '</td>' +
                        '<td>' + (hesapAdi + (hesapDetay ? ' - ' + hesapDetay : '')) + '</td>' +
                        '<td>' + odemeAciklama + '</td>' +
                        '<td>' + tutarText + '</td>' +
                        '<td>' + durum + '</td>' +
                        '</tr>';
            });
            html += '</tbody></table>';
            
            var uri = 'data:application/vnd.ms-excel;base64,';
            var template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><meta charset="utf-8"/><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>{table}</body></html>';
            var base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))); };
            var format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }); };
            var ctx = { worksheet: 'Kesinti Raporu', table: html };
            
            var link = document.createElement("a");
            link.download = "Kesinti_Raporu.xls";
            link.href = uri + base64(format(template, ctx));
            link.click();
        });

        // Kopyalama özelliği
        $(document).on('click', '.copyable', function() {
            var text = $(this).text().trim();
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(text).select();
            document.execCommand("copy");
            $temp.remove();
            
            // Toast veya bildirim gösterilebilir
            Swal.fire({
                icon: 'success',
                title: 'Kopyalandı!',
                text: 'Metin panoya kopyalandı.',
                timer: 1000,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        });

        const yilSelect = $('[name="yilSelectRapor"]');
        const donemSelect = $('[name="donemSelectRapor"]');
        const turSelect = $('[name="turSelectRapor"]');

        function goTo(params) {
            const q = Object.entries(params).filter(function (kv) { return kv[1]; }).map(function (kv) { return kv[0] + '=' + encodeURIComponent(kv[1]); });
            window.location.href = 'index?p=bordro/raporlar/kesinti-raporu' + (q.length ? '&' + q.join('&') : '');
        }

        yilSelect.on('change', function () { goTo({ yil: yilSelect.val(), tur: turSelect.val() }); });
        donemSelect.on('change', function () { goTo({ donem: donemSelect.val(), tur: turSelect.val() }); });
        turSelect.on('change', function () { goTo({ donem: donemSelect.val(), tur: turSelect.val() }); });

        // Ödeme Durumu Güncelleme
        $(document).on('change', '.odeme-durum-toggle', function() {
            const id = $(this).data('id');
            const durum = $(this).prop('checked') ? 'odendi' : 'odenmedi';
            const $text = $('#odemeDurumText_' + id);

            $.post('views/personel/ajax/kesinti-islemleri.php', {
                action: 'update_odeme_durumu',
                id: id,
                odeme_durumu: durum,
                personel_id: 1 // Bu AJAX dosyasında personel_id zorunlu tutulmuş, rapor sayfasında herhangi bir değer gönderebiliriz
            }, function(response) {
                if (response.success) {
                    $text.text(durum === 'odendi' ? 'Ödendi' : 'Ödenmedi');
                    Swal.fire({
                        icon: 'success',
                        title: 'Güncellendi',
                        text: 'Ödeme durumu güncellendi.',
                        timer: 1000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire('Hata', response.error || 'İşlem başarısız', 'error');
                }
            }, 'json');
        });

        // Dekont Yükleme
        $(document).on('click', '.btn-dekont-yukle', function() {
            const id = $(this).data('id');
            
            Swal.fire({
                title: 'Dekont Yükleme',
                html: `
                    <div class="p-3">
                        <div class="upload-container border-2 border-dashed border-primary rounded-3 p-4 text-center mb-3" 
                             style="background-color: #f8f9fa; cursor: pointer;"
                             onclick="document.getElementById('dekontFileInput').click()">
                            <i class="bx bx-cloud-upload display-4 text-primary mb-2"></i>
                            <h6 class="fw-bold">Ödeme Dekontu Seçin</h6>
                            <p class="text-muted small mb-0">PDF, JPG, JPEG veya PNG (Maks 5MB)</p>
                            <p class="text-info small mt-2 mb-0"><i class="bx bx-info-circle me-1"></i> Belge yüklendiğinde durum otomatik olarak <b>"Ödendi"</b> yapılacaktır.</p>
                            <div id="file-info" class="mt-3 d-none">
                                <span class="badge bg-success px-3 py-2 rounded-pill">
                                    <i class="bx bx-file me-1"></i> <span id="file-name">dosya.pdf</span>
                                </span>
                            </div>
                        </div>
                        <input type="file" id="dekontFileInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png" onchange="
                            if(this.files[0]) {
                                document.getElementById('file-info').classList.remove('d-none');
                                document.getElementById('file-name').innerText = this.files[0].name;
                            }
                        ">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="bx bx-upload me-1"></i> Yükle',
                cancelButtonText: 'Vazgeç',
                confirmButtonColor: '#34c38f',
                cancelButtonColor: '#f46a6a',
                customClass: {
                    confirmButton: 'btn btn-success px-4 rounded-pill',
                    cancelButton: 'btn btn-light px-4 rounded-pill'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    const file = document.getElementById('dekontFileInput').files[0];
                    if (!file) {
                        Swal.showValidationMessage('Lütfen bir dosya seçin');
                        return false;
                    }
                    return file;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'upload_dekont');
                    formData.append('id', id);
                    formData.append('dekont', result.value);
                    formData.append('personel_id', 1);

                    // Yükleme bildirimi
                    Swal.fire({
                        title: 'Yükleniyor...',
                        text: 'Lütfen bekleyin, dekont sisteme kaydediliyor.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'views/personel/ajax/kesinti-islemleri.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Başarılı',
                                    text: 'Dekont yüklendi ve ödeme durumu "Ödendi" olarak güncellendi.',
                                    timer: 2000
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Hata', response.error || 'Yükleme başarısız', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Hata', 'Sunucu hatası', 'error');
                        }
                    });
                }
            });
        });

        // Dekont Sil
        $(document).on('click', '.btn-dekont-sil', function() {
            const id = $(this).data('id');
            
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Yüklenmiş olan dekont dosyası kalıcı olarak silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f46a6a',
                cancelButtonColor: '#74788d',
                confirmButtonText: 'Evet, Sil!',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('views/personel/ajax/kesinti-islemleri.php', {
                        action: 'delete_dekont',
                        id: id,
                        personel_id: 1
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Silindi',
                                text: 'Dekont dosyası başarıyla silindi.',
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Hata', response.error || 'Silme işlemi başarısız', 'error');
                        }
                    }, 'json');
                }
            });
        });
    });
</script>
