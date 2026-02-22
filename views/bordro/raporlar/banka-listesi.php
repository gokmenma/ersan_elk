<?php
/**
 * Banka Listesi Raporu
 * Bankaya gönderilecek ödeme listesini görüntüler
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
$toplamBankaOdemesi = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonemDetayli($selectedDonemId);

        // Toplam banka ödemesini hesapla
        foreach ($personeller as $personel) {
            $toplamBankaOdemesi += floatval($personel->banka_odemesi ?? 0);
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
    $title = "Banka Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bxs-bank text-info me-2"></i>Banka Listesi
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectBanka',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>

                            <?php echo Form::FormSelect2(
                                name: 'donemSelectBanka',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>

                            <?php if ($selectedDonem): ?>
                                <a href="views/bordro/excel-banka-export.php?donem_id=<?= $selectedDonemId ?>"
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
                    <?php if ($selectedDonem && !empty($personeller)): ?>
                        <!-- Özet Bilgiler -->
                        <!-- Özet Bilgiler (Dashboard Stili) -->
                        <div class="row g-3 mb-4">
                            <!-- Toplam Personel -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bx-user-circle fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">SAYI</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM PERSONEL</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= count($personeller) ?>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam Banka Ödemesi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bxs-bank fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TUTAR</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BANKA ÖDEMESİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamBankaOdemesi, 2, ',', '.') ?> <span
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
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-calendar-x fs-4 text-warning"></i>
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
                        $ibanEksikSayisi = 0;
                        foreach ($personeller as $p) {
                            if (floatval($p->banka_odemesi ?? 0) > 0 && empty($p->iban_numarasi)) {
                                $ibanEksikSayisi++;
                            }
                        }
                        ?>
                        <?php if ($ibanEksikSayisi > 0): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="bx bx-error-circle me-2"></i>
                                <strong>Dikkat:</strong> <?= $ibanEksikSayisi ?> personelin banka ödemesi var fakat IBAN bilgisi
                                tanımlı değil.
                                Lütfen personel bilgilerini güncelleyiniz.
                            </div>
                        <?php endif; ?>

                        <!-- Banka Listesi Tablosu -->
                        <div class="table-responsive">
                            <table id="bankaListesiTable" class="table table-hover table-bordered nowrap w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Ad Soyad</th>
                                        <th>TC Kimlik No</th>
                                        <th>IBAN</th>
                                        <th class="text-end">Banka Ödemesi</th>
                                        <th class="text-center">Durum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($personeller as $personel):
                                        $bankaOdemesi = floatval($personel->banka_odemesi ?? 0);
                                        $ibanDolu = !empty($personel->iban_numarasi);
                                        ?>
                                        <tr class="<?= !$ibanDolu && $bankaOdemesi > 0 ? 'table-warning' : '' ?>">
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
                                                <?php if ($ibanDolu): ?>
                                                    <code
                                                        class="text-primary"><?= htmlspecialchars($personel->iban_numarasi) ?></code>
                                                <?php else: ?>
                                                    <span class="text-danger">
                                                        <i class="bx bx-error-circle me-1"></i>IBAN Tanımlı Değil
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($bankaOdemesi > 0): ?>
                                                    <span class="fw-bold text-success">
                                                        <?= number_format($bankaOdemesi, 2, ',', '.') ?> ₺
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($bankaOdemesi > 0 && $ibanDolu): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bx bx-check me-1"></i>Hazır
                                                    </span>
                                                <?php elseif ($bankaOdemesi > 0 && !$ibanDolu): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bx bx-error me-1"></i>IBAN Eksik
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bx bx-minus me-1"></i>Ödeme Yok
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="4" class="text-end">Toplam Banka Ödemesi:</th>
                                        <th class="text-end text-success fw-bold">
                                            <?= number_format($toplamBankaOdemesi, 2, ',', '.') ?> ₺
                                        </th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
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
                            <p class="text-muted">Banka listesini görüntülemek için bir dönem seçiniz.</p>
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
        // DataTable başlat
        if (document.getElementById('bankaListesiTable')) {
            $('#bankaListesiTable').DataTable({
                language: {
                    url: 'assets/libs/datatables/Turkish.json'
                },
                pageLength: 25,
                order: [[1, 'asc']],
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'print',
                        text: '<i class="bx bx-printer me-1"></i> Yazdır',
                        className: 'btn btn-sm btn-outline-secondary',
                        title: 'Banka Listesi - <?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : '' ?>'
                    }
                ]
            });
        }

        // Yıl değişince
        const yilSelect = document.querySelector('[name="yilSelectBanka"]');
        const donemSelect = document.querySelector('[name="donemSelectBanka"]');

        if (yilSelect) {
            yilSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/banka-listesi&yil=' + this.value;
            });
        }

        // Dönem değişince
        if (donemSelect) {
            donemSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/banka-listesi&donem=' + this.value;
            });
        }
    });
</script>