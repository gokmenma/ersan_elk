<?php
/**
 * SGK Bildirge Raporu
 * Dönem bazlı personel SGK prim bildirge raporunu görüntüler
 */

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();

// Seçili dönem
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;

$selectedDonem = null;
$personeller = [];

// Özet değişkenleri
$toplamSgkMatrahi = 0;
$toplamIsciSgk = 0;
$toplamIsciIssizlik = 0;
$toplamIsverenSgk = 0;
$toplamIsverenIssizlik = 0;
$toplamGun = 0;
$toplamPrim = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);

        // Özet verilerini hesapla
        foreach ($personeller as $personel) {
            $isciSgk = floatval($personel->sgk_isci ?? 0);
            $isciIssizlik = floatval($personel->issizlik_isci ?? 0);
            $isverenSgk = floatval($personel->sgk_isveren ?? 0);
            $isverenIssizlik = floatval($personel->issizlik_isveren ?? 0);

            $toplamIsciSgk += $isciSgk;
            $toplamIsciIssizlik += $isciIssizlik;
            $toplamIsverenSgk += $isverenSgk;
            $toplamIsverenIssizlik += $isverenIssizlik;

            // SGK Matrahı ve Prim Gününü hesaplama_detay JSON'dan al
            $sgkMatrahi = floatval($personel->brut_maas ?? 0); // Varsayılan olarak brüt maaş
            $primGunu = 30; // Varsayılan 30 gün

            if (!empty($personel->hesaplama_detay)) {
                $detay = json_decode($personel->hesaplama_detay, true);
                if (isset($detay['matrahlar']['sgk_matrahi'])) {
                    $sgkMatrahi = floatval($detay['matrahlar']['sgk_matrahi']);
                }

                // Gün hesaplama
                $ucretsizIzinGunu = 0;
                $ucretliIzinGunu = 0;
                if (isset($detay['matrahlar']['ucretsiz_izin_gunu'])) {
                    $ucretsizIzinGunu = intval($detay['matrahlar']['ucretsiz_izin_gunu']);
                } elseif (isset($detay['matrahlar']['ucretsiz_izin_dusumu']) && isset($detay['matrahlar']['nominal_maas']) && $detay['matrahlar']['nominal_maas'] > 0) {
                    $gunlukUcret = $detay['matrahlar']['nominal_maas'] / 30;
                    $ucretsizIzinGunu = round($detay['matrahlar']['ucretsiz_izin_dusumu'] / $gunlukUcret);
                }
                if (isset($detay['matrahlar']['ucretli_izin_gunu'])) {
                    $ucretliIzinGunu = intval($detay['matrahlar']['ucretli_izin_gunu']);
                }
                $primGunu = max(0, 30 - $ucretsizIzinGunu - $ucretliIzinGunu);
            }

            $personel->sgk_matrahi = $sgkMatrahi;
            $personel->prim_gunu = $primGunu;
            $personel->toplam_prim_tutari = $isciSgk + $isciIssizlik + $isverenSgk + $isverenIssizlik;

            $toplamSgkMatrahi += $sgkMatrahi;
            $toplamGun += $primGunu;
            $toplamPrim += $personel->toplam_prim_tutari;
        }
    }
}

// Yılları ve dönemleri getir (dönem seçimi için)
$selectedYil = $selectedDonem ? date('Y', strtotime($selectedDonem->baslangic_tarihi)) : date('Y');
$donemler = $BordroDonem->getAllDonems($selectedYil);
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
foreach ($donemler as $donem) {
    $donem_option[$donem->id] = $donem->donem_adi;
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $subtitle = "Raporlar";
    $title = "SGK Bildirge Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-shield-quarter text-warning me-2"></i>SGK Bildirge Raporu
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span
                                    class="badge bg-warning text-dark shadow-sm border border-warning"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectSgk',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>

                            <?php echo Form::FormSelect2(
                                name: 'donemSelectSgk',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>

                            <?php if ($selectedDonem): ?>
                                <a href="views/bordro/excel-sgk-export.php?donem_id=<?= $selectedDonemId ?>"
                                    class="btn btn-success shadow-sm">
                                    <i class="bx bx-download me-1"></i> Excel İndir
                                </a>
                            <?php endif; ?>

                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>"
                                class="btn btn-secondary shadow-sm">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($personeller)): ?>
                        <!-- Özet Kartları -->
                        <div class="row g-3 mb-4">
                            <!-- SGK Matrahı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bx-money fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">MATRAH
                                                (PEK)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM SGK KAZANCI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamSgkMatrahi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- İşçi Payları Toplamı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-user fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞÇİ (SGK +
                                                İŞSİZLİK)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM İŞÇİ KESİNTİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamIsciSgk + $toplamIsciIssizlik, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- İşveren Payları Toplamı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bx-building fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">İŞVEREN (SGK
                                                + İŞSİZLİK)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM İŞVEREN MALİYETİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamIsverenSgk + $toplamIsverenIssizlik, 2, ',', '.') ?>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam Ödenecek Prim -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-pie-chart fs-4 text-success"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TOPLAM
                                                PRİM</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SGK BİLDİRGE TOPLAMI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-success">
                                            <?= number_format($toplamPrim, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SGK Bildirge Tablosu -->
                        <div class="table-responsive mt-3">
                            <table id="sgkBildirgeTable" class="table table-hover table-bordered nowrap w-100 align-middle datatable">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>TC Kimlik No</th>
                                        <th>Ad Soyad</th>
                                        <th class="text-center" title="Prim Ödeme Gün Sayısı">Gün</th>
                                        <th class="text-end text-primary" title="Prime Esas Kazanç">SGK Matrahı (PEK)</th>
                                        <th class="text-end" style="background-color: #fff8e1;">İşçi SGK<br><small
                                                class="text-muted">(%14)</small></th>
                                        <th class="text-end" style="background-color: #fff8e1;">İşçi İşsz.<br><small
                                                class="text-muted">(%1)</small></th>
                                        <th class="text-end" style="background-color: #ffebee;">İşv. SGK<br><small
                                                class="text-muted">(%20.5)</small></th>
                                        <th class="text-end" style="background-color: #ffebee;">İşv. İşsz.<br><small
                                                class="text-muted">(%2)</small></th>
                                        <th class="text-end bg-light fw-bold text-success">Toplam Prim</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($personeller as $personel):
                                        ?>
                                        <tr>
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <span class="text-muted"
                                                    style="font-family: monospace;"><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($personel->adi_soyadi) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?= $personel->prim_gunu ?></span>
                                            </td>
                                            <td class="text-end text-primary fw-medium">
                                                <?= number_format($personel->sgk_matrahi, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fffdf5;">
                                                <?= number_format(floatval($personel->sgk_isci ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fffdf5;">
                                                <?= number_format(floatval($personel->issizlik_isci ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fff5f7;">
                                                <?= number_format(floatval($personel->sgk_isveren ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fff5f7;">
                                                <?= number_format(floatval($personel->issizlik_isveren ?? 0), 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end bg-light fw-bold text-success">
                                                <?= number_format($personel->toplam_prim_tutari, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <th colspan="3" class="text-end">GENEL TOPLAMLAR:</th>
                                        <th class="text-center"><?= $toplamGun ?></th>
                                        <th class="text-end text-primary fs-6">
                                            <?= number_format($toplamSgkMatrahi, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-warning"><?= number_format($toplamIsciSgk, 2, ',', '.') ?>
                                            ₺</th>
                                        <th class="text-end text-warning">
                                            <?= number_format($toplamIsciIssizlik, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-danger">
                                            <?= number_format($toplamIsverenSgk, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-danger">
                                            <?= number_format($toplamIsverenIssizlik, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-success fs-5">
                                            <?= number_format($toplamPrim, 2, ',', '.') ?> ₺</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-shield-x"></i>
                                </div>
                            </div>
                            <h5 class="mt-3 text-secondary">Personel Bulunamadı</h5>
                            <p class="text-muted">Bu dönemde henüz maaşı hesaplanmış personel bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-calendar-x"></i>
                                </div>
                            </div>
                            <h5 class="mt-3 text-secondary">Dönem Seçimi Bekleniyor</h5>
                            <p class="text-muted">SGK Bildirge Raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // DataTable başlatılması vendor-scripts içerisinden datatables.init.js aracılığı ile ".datatable" sınıfı referans alınarak otomatik yapılmaktadır.

        // Yıl değişince
        const yilSelect = document.querySelector('[name="yilSelectSgk"]');
        const donemSelect = document.querySelector('[name="donemSelectSgk"]');

        if (yilSelect) {
            yilSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/sgk-bildirge&yil=' + this.value;
            });
        }

        // Dönem değişince
        if (donemSelect) {
            donemSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/sgk-bildirge&donem=' + this.value;
            });
        }
    });
</script>