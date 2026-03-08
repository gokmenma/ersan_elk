<?php
/**
 * Sodexo Raporu
 * Sodexo'ya gönderilecek yükleme listesini görüntüler
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
$toplamSodexoOdemesi = 0;
$sodexoKullaniciSayisi = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonemDetayli($selectedDonemId);

        // Toplam sodexo ödemesini hesapla
        foreach ($personeller as $personel) {
            $sodexoOdemesi = floatval($personel->sodexo_odemesi ?? 0);
            if ($sodexoOdemesi > 0) {
                $toplamSodexoOdemesi += $sodexoOdemesi;
                $sodexoKullaniciSayisi++;
            }
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
    $title = "Sodexo Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-restaurant text-warning me-2"></i>Sodexo Listesi
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectSodexo',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>

                            <?php echo Form::FormSelect2(
                                name: 'donemSelectSodexo',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>

                            <?php if ($selectedDonem): ?>
                                <a href="views/bordro/excel-sodexo-export.php?donem_id=<?= $selectedDonemId ?>"
                                    class="btn btn-success">
                                    <i class="bx bx-download me-1"></i> Excel İndir
                                </a>
                            <?php endif; ?>

                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>" class="btn btn-secondary">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($personeller) && $sodexoKullaniciSayisi > 0): ?>
                        <!-- Özet Bilgiler (Dashboard Stili) -->
                        <div class="row g-3 mb-4">
                            <!-- Sodexo Kullanan Personel -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-user-circle fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SAYI</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SODEXO ALAN PERSONEL</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= $sodexoKullaniciSayisi ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam Sodexo Ödemesi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #10b981; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(16, 185, 129, 0.1);">
                                                <i class="bx bx-restaurant fs-4 text-success"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TUTAR</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">YÜKLENECEK TOPLAM TUTAR</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamSodexoOdemesi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Dönem Başlangıç -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-calendar-check fs-4 text-success"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TARİH</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">DÖNEM BAŞLANGIÇ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Dönem Bitiş -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
                                                <i class="bx bx-calendar-x fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TARİH</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">DÖNEM BİTİŞ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Uyarılar -->
                        <?php
                        $kartNoEksikSayisi = 0;
                        foreach ($personeller as $p) {
                            if (floatval($p->sodexo_odemesi ?? 0) > 0 && empty($p->sodexo_kart_no)) {
                                $kartNoEksikSayisi++;
                            }
                        }
                        ?>
                        <?php if ($kartNoEksikSayisi > 0): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Bilgi:</strong> <?= $kartNoEksikSayisi ?> personelin sodexo kart numarası kayıtlı değil.
                                Bu personeller için <strong>TC Kimlik No</strong> kullanılacaktır.
                            </div>
                        <?php endif; ?>

                        <!-- Sodexo Listesi Tablosu -->
                        <div class="table-responsive mt-3">
                            <table id="sodexoListesiTable" class="table table-hover table-bordered nowrap w-100 align-middle datatable">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Ad Soyad</th>
                                        <th>TC Kimlik No</th>
                                        <th>Cep Telefonu</th>
                                        <th>Kart Numarası</th>
                                        <th class="text-end">Yükleme Tutarı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($personeller as $personel):
                                        $sodexoOdemesi = floatval($personel->sodexo_odemesi ?? 0);
                                        if ($sodexoOdemesi <= 0) continue;
                                        
                                        $kartNoDolu = !empty($personel->sodexo_kart_no);
                                        ?>
                                        <tr>
                                            <td class="text-center"><?= $sira++ ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                        alt="" class="rounded-circle avatar-xs me-2">
                                                    <span
                                                        class="fw-medium"><?= htmlspecialchars($personel->adi_soyadi) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></code>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($personel->cep_telefonu ?? '-') ?>
                                            </td>
                                            <td>
                                                <?php if ($kartNoDolu): ?>
                                                    <code class="text-primary"><?= htmlspecialchars($personel->sodexo_kart_no) ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted small">
                                                        <i class="bx bx-id-card me-1"></i>TC Kimlik kullanılacak
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold text-success">
                                                    <?= number_format($sodexoOdemesi, 2, ',', '.') ?> ₺
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="5" class="text-end">Toplam Yüklenecek Tutar:</th>
                                        <th class="text-end text-success fw-bold">
                                            <?= number_format($toplamSodexoOdemesi, 2, ',', '.') ?> ₺
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    <?php elseif ($selectedDonem && $sodexoKullaniciSayisi === 0): ?>
                        <div class="text-center py-5">
                            <i class="bx bx-restaurant display-1 text-muted"></i>
                            <h5 class="mt-3">Sodexo Ödemesi Bulunmuyor</h5>
                            <p class="text-muted">Bu dönemde hiçbir personelin hak ettiği hesaplanmış bir sodexo yükleme tutarı bulunmuyor.</p>
                        </div>
                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <i class="bx bx-user-x display-1 text-muted"></i>
                            <h5 class="mt-3">Personel Bulunamadı</h5>
                            <p class="text-muted">Bu dönemde henüz personel bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bx bx-calendar-x display-1 text-muted"></i>
                            <h5 class="mt-3">Dönem Seçilmedi</h5>
                            <p class="text-muted">Sodexo listesini görüntülemek için bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-xs {
        width: 2rem;
        height: 2rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Yıl değişince
        const yilSelect = document.querySelector('[name="yilSelectSodexo"]');
        const donemSelect = document.querySelector('[name="donemSelectSodexo"]');

        if (yilSelect) {
            yilSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/sodexo-raporu&yil=' + this.value;
            });
        }

        // Dönem değişince
        if (donemSelect) {
            donemSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/sodexo-raporu&donem=' + this.value;
            });
        }
    });
</script>
