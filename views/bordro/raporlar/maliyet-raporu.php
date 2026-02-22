<?php
/**
 * Maliyet Raporu
 * Personel bazlı işveren maliyet analizi raporunu görüntüler
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
$toplamBrutMaas = 0;
$toplamSgkIsveren = 0;
$toplamSecSgkIsveren = 0;
$toplamIssizlikIsveren = 0;
$genelToplamMaliyet = 0;
$toplamNetMaas = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonemDetayli($selectedDonemId);
        
        // Özet verilerini hesapla
        foreach ($personeller as $personel) {
            $toplamBrutMaas += floatval($personel->brut_maas ?? 0);
            $toplamSecSgkIsveren += floatval($personel->sgk_isveren ?? 0);
            $toplamIssizlikIsveren += floatval($personel->issizlik_isveren ?? 0);
            $genelToplamMaliyet += floatval($personel->toplam_maliyet ?? 0);
            $toplamNetMaas += floatval($personel->net_maas ?? 0);
        }
        $toplamSgkIsveren = $toplamSecSgkIsveren + $toplamIssizlikIsveren;
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
    $title = "Maliyet Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-pie-chart-alt-2 text-primary me-2"></i>Maliyet Raporu
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span class="badge bg-primary text-white shadow-sm border"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectMaliyet',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>
                            
                            <?php echo Form::FormSelect2(
                                name: 'donemSelectMaliyet',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>
                            
                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>" 
                               class="btn btn-secondary shadow-sm">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($personeller)): ?>
                        <!-- Özet Kartları (Dashboard Stili) -->
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
                                            <?= count($personeller) ?> <span style="font-size: 0.85rem; font-weight: 500;" class="text-muted">Kişi</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam Brüt Maaş -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bx-wallet fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">HAKEDİŞ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM BRÜT MAAŞ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamBrutMaas, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam SGK İşveren Payı -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-buildings fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GİDER</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SGK İŞVEREN PAYI (TOPLAM)</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamSgkIsveren, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Genel Toplam Maliyet -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-line-chart fs-4 text-success"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TOPLAM</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">GENEL TOPLAM MALİYET</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($genelToplamMaliyet, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maliyet Listesi Tablosu -->
                        <div class="table-responsive mt-2">
                            <table id="maliyetListesiTable" class="table table-hover table-bordered nowrap w-100 align-middle">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Personel</th>
                                        <th class="text-center">Maaş Tipi</th>
                                        <th class="text-end">Brüt Maaş</th>
                                        <th class="text-end">Net Maaş</th>
                                        <th class="text-end" title="SGK İşçi + İşsizlik İşçi Payı">İşçi SGK Payı</th>
                                        <th class="text-end" title="SGK İşveren Payı">İşveren SGK Payı</th>
                                        <th class="text-end" title="İşsizlik İşveren Payı">İşv. İşsizlik Payı</th>
                                        <th class="text-end bg-light fw-bold text-primary">Toplam Maliyet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sira = 1;
                                    foreach ($personeller as $personel): 
                                        $brutMaas = floatval($personel->brut_maas ?? 0);
                                        $netMaas = floatval($personel->net_maas ?? 0);
                                        $isciSgk = floatval($personel->sgk_isci ?? 0) + floatval($personel->issizlik_isci ?? 0);
                                        $isverenSgk = floatval($personel->sgk_isveren ?? 0);
                                        $isverenIssizlik = floatval($personel->issizlik_isveren ?? 0);
                                        $toplamMaliyet = floatval($personel->toplam_maliyet ?? 0);
                                        
                                        // json'dan maaş tipini çöz
                                        $maasDurumuStr = '-';
                                        if(!empty($personel->hesaplama_detay)){
                                            $detay = json_decode($personel->hesaplama_detay, true);
                                            $maasDurumuStr = $detay['maas_durumu'] ?? '-';
                                        }
                                    ?>
                                        <tr>
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-bold"><?= htmlspecialchars($personel->adi_soyadi) ?></span>
                                                    <span class="text-muted small" style="font-size: 11px;">TC: <?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border fw-medium px-2 py-1" style="font-size: 11px;">
                                                    <?= htmlspecialchars($maasDurumuStr) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-dark fw-medium"><?= number_format($brutMaas, 2, ',', '.') ?> ₺</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="text-success fw-medium"><?= number_format($netMaas, 2, ',', '.') ?> ₺</span>
                                            </td>
                                            <td class="text-end text-muted">
                                                <?= number_format($isciSgk, 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="text-end text-warning fw-medium">
                                                <?= number_format($isverenSgk, 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="text-end text-warning fw-medium">
                                                <?= number_format($isverenIssizlik, 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="text-end bg-light fw-bold text-primary">
                                                <span style="font-size: 14px;"><?= number_format($toplamMaliyet, 2, ',', '.') ?> ₺</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <th colspan="3" class="text-end">GENEL TOPLAMLAR:</th>
                                        <th class="text-end text-dark"><?= number_format($toplamBrutMaas, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-success"><?= number_format($toplamNetMaas, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end"></th>
                                        <th class="text-end text-warning"><?= number_format($toplamSecSgkIsveren, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-warning"><?= number_format($toplamIssizlikIsveren, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-primary fs-5"><?= number_format($genelToplamMaliyet, 2, ',', '.') ?> ₺</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-folder-open"></i>
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
                            <p class="text-muted">İşveren maliyet raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable başlat
    if (document.getElementById('maliyetListesiTable')) {
        $('#maliyetListesiTable').DataTable({
            language: {
                url: 'assets/libs/datatables/Turkish.json'
            },
            pageLength: 50,
            order: [[1, 'asc']], // Ad Soyad'a göre sırala
            dom: '<"row align-items-center mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="bx bx-spreadsheet me-1"></i> Excel',
                    className: 'btn btn-sm btn-success',
                    title: 'Maliyet Raporu - <?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : '' ?>',
                    footer: true,
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="bx bx-printer me-1"></i> Yazdır',
                    className: 'btn btn-sm btn-secondary',
                    title: 'Bordro İşveren Maliyet Raporu - <?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : '' ?>',
                    footer: true
                }
            ],
            responsive: true
        });
    }
    
    // Yıl değişince
    const yilSelect = document.querySelector('[name="yilSelectMaliyet"]');
    const donemSelect = document.querySelector('[name="donemSelectMaliyet"]');
    
    if (yilSelect) {
        yilSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/maliyet-raporu&yil=' + this.value;
        });
    }
    
    // Dönem değişince
    if (donemSelect) {
        donemSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/maliyet-raporu&donem=' + this.value;
        });
    }
});
</script>
