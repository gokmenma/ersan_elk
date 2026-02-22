<?php
/**
 * İcmal Raporu
 * Dönem bazlı personel maaş özet raporunu görüntüler
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
$toplamBrut = 0;
$toplamEkOdeme = 0;
$toplamIsverenSgk = 0;
$toplamIsverenIssizlik = 0;

$toplamGelirVergisi = 0;
$toplamDamgaVergisi = 0;
$toplamIsciSgk = 0;
$toplamIsciIssizlik = 0;
$toplamDigerKesinti = 0;
$toplamNet = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        // getPersonellerByDonem tüm temel hesaplama verilerini getirir
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);

        // Özet verilerini hesapla
        foreach ($personeller as $personel) {
            $toplamBrut += floatval($personel->brut_maas ?? 0);
            $toplamNet += floatval($personel->net_maas ?? 0);
            
            $toplamGelirVergisi += floatval($personel->gelir_vergisi ?? 0);
            $toplamDamgaVergisi += floatval($personel->damga_vergisi ?? 0);
            $toplamIsciSgk += floatval($personel->sgk_isci ?? 0);
            $toplamIsciIssizlik += floatval($personel->issizlik_isci ?? 0);
            $toplamIsverenSgk += floatval($personel->sgk_isveren ?? 0);
            $toplamIsverenIssizlik += floatval($personel->issizlik_isveren ?? 0);

            // Ek ödeme ve diğer kesintiler (hesaplama_detay üzerinden)
            $hesaplananEkOdeme = 0;
            $digerKesinti = 0;
            if (!empty($personel->hesaplama_detay)) {
                $detay = json_decode($personel->hesaplama_detay, true);
                if (isset($detay['ek_odemeler']) && is_array($detay['ek_odemeler'])) {
                    foreach ($detay['ek_odemeler'] as $eo) {
                        $hesaplananEkOdeme += floatval($eo['net_etki'] ?? $eo['tutar'] ?? 0);
                    }
                }
                
                // Diğer kesintiler
                if (isset($detay['kesintiler']) && is_array($detay['kesintiler'])) {
                    foreach ($detay['kesintiler'] as $kes) {
                        $digerKesinti += floatval($kes['tutar'] ?? 0);
                    }
                }
            } else {
                $hesaplananEkOdeme += floatval($personel->guncel_toplam_ek_odeme ?? 0);
                $tumYasalHesap = floatval($personel->gelir_vergisi ?? 0) + floatval($personel->damga_vergisi ?? 0) + floatval($personel->sgk_isci ?? 0) + floatval($personel->issizlik_isci ?? 0);
                $digerKesinti = max(0, floatval($personel->guncel_toplam_kesinti ?? 0) - $tumYasalHesap);
            }
            
            $toplamEkOdeme += $hesaplananEkOdeme;
            $toplamDigerKesinti += $digerKesinti;
        }
    }
}

$genelIsverenMaliyeti = $toplamBrut + $toplamEkOdeme + $toplamIsverenSgk + $toplamIsverenIssizlik;
$genelNetToplam = $toplamGelirVergisi + $toplamDamgaVergisi + $toplamIsciSgk + $toplamIsverenSgk + $toplamIsciIssizlik + $toplamIsverenIssizlik + $toplamDigerKesinti + $toplamNet;

// Ay-Yıl Formatı
$aylar = [
    '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
    '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
    '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
];
$donemAyYil = "";
if ($selectedDonem) {
    $ay = date('m', strtotime($selectedDonem->baslangic_tarihi));
    $yil = date('Y', strtotime($selectedDonem->baslangic_tarihi));
    $donemAyYil = $aylar[$ay] . ' - ' . $yil;
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
    $title = "İcmal Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-file text-primary me-2"></i>İcmal Raporu
                            </h5>
                            <?php if ($selectedDonem): ?>
                                    <span class="badge bg-primary text-white shadow-sm border"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectIcmal',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>
                            
                            <?php echo Form::FormSelect2(
                                name: 'donemSelectIcmal',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>
                            
                            <?php if ($selectedDonem): ?>
                                    <a href="views/bordro/export-excel.php?donem_id=<?= $selectedDonemId ?>"
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
                            <!-- Bordro İcmal Tablosu (Görsel Birebir Yansıma) -->
                            <div class="row justify-content-center">
                                <div class="col-xl-9 col-lg-10">
                                    <div class="border border-dark bg-white p-4" id="icmalReportTbl">
                                        <div class="text-center mb-4">
                                            <h4 class="fw-bold mb-3" style="color: #d32f2f; border: 2px solid #d32f2f; display: inline-block; padding: 10px 40px; margin-bottom: 5px;">
                                                AYLIK BORDRO İCMAL RAPORU
                                            </h4>
                                            <div class="fw-bold fs-5">
                                                ( &nbsp;&nbsp;&nbsp;&nbsp; <?= $donemAyYil ?> &nbsp;&nbsp;&nbsp;&nbsp; )
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4 d-flex align-items-center">
                                            <table class="table-borderless">
                                                <tbody>
                                                    <tr>
                                                        <td style="width: 120px;" class="fw-bold text-dark pb-1 text-nowrap">Firma Ünvanı</td>
                                                        <td class="fw-bold text-dark pb-1 px-2">:</td>
                                                        <td class="pb-1"><?= htmlspecialchars($_SESSION['firma_unvan'] ?? 'ERSAN ELEKTRİK') ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <table class="table table-bordered border-dark table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50%; font-size: 14px;" class="py-2 px-3 border-dark bg-transparent text-dark fw-bold">
                                                        BRÜT ÖDEMELER 
                                                        <span class="float-end">TUTAR</span>
                                                    </th>
                                                    <th style="width: 50%; font-size: 14px;" class="py-2 px-3 border-dark bg-transparent text-dark fw-bold">
                                                        NET ÖDEMELER
                                                        <span class="float-end">TUTAR</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <!-- Sol Kolon: Brüt Ödemeler -->
                                                    <td class="p-0 border-dark align-top">
                                                        <table class="table table-borderless mb-0">
                                                            <tbody>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">Brüt Ödemeler</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamBrut, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">Ek Ödemeler</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamEkOdeme, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşveren SSK</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsverenSgk, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşveren İşsizlik</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsverenIssizlik, 2, ',', '.') ?></td></tr>
                                                            </tbody>
                                                        </table>
                                                    </td>

                                                    <!-- Sağ Kolon: Net Ödemeler / Kesintiler -->
                                                    <td class="p-0 border-dark align-top">
                                                        <table class="table table-borderless mb-0">
                                                            <tbody>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">Gelir Vergisi</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamGelirVergisi, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">Damga Vergisi</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamDamgaVergisi, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşçi SSK</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsciSgk, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşveren SSK</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsverenSgk, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşçi İşsizlik</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsciIssizlik, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">İşveren İşsizlik</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamIsverenIssizlik, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent">Diğer Kesintiler</td><td class="text-end pe-3 bg-transparent"><?= number_format($toplamDigerKesinti, 2, ',', '.') ?></td></tr>
                                                                <tr><td class="ps-3">&nbsp;</td><td class="text-end pe-3">&nbsp;</td></tr>
                                                                <tr><td class="ps-3 fw-bold bg-transparent" style="color: #d32f2f;">Ödenecek Net Tutar</td><td class="text-end pe-3 fw-bold bg-transparent" style="color: #d32f2f;"><?= number_format($toplamNet, 2, ',', '.') ?></td></tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="border-dark border-top-0">
                                                <tr>
                                                    <td class="border-dark px-0 py-0 border-top">
                                                        <table class="table table-borderless mb-0 fw-bold">
                                                            <tr>
                                                                <td class="ps-3 bg-transparent">TOPLAM</td>
                                                                <td class="text-end pe-3 bg-transparent"><?= number_format($genelIsverenMaliyeti, 2, ',', '.') ?></td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                    <td class="border-dark px-0 py-0 border-top">
                                                        <table class="table table-borderless mb-0 fw-bold">
                                                            <tr>
                                                                <td class="ps-3 bg-transparent">TOPLAM</td>
                                                                <td class="text-end pe-3 bg-transparent"><?= number_format($genelNetToplam, 2, ',', '.') ?></td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <div class="mt-4 text-end d-print-none">
                                        <button onclick="yazdirIcmal()" class="btn btn-primary shadow-sm">
                                            <i class="bx bx-printer me-1"></i> Raporu Yazdır
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                            function yazdirIcmal() {
                                const icerik = document.getElementById('icmalReportTbl').innerHTML;
                                const originalContents = document.body.innerHTML;

                                document.body.innerHTML = 
                                    '<div style="font-family: Arial, sans-serif; padding: 20px;">' + icerik + '</div>';

                                window.print();

                                // Restore after printing
                                setTimeout(() => {
                                    document.body.innerHTML = originalContents;
                                    window.location.reload(); // Reload events
                                }, 500);
                            }
                            </script>

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
                                <p class="text-muted">İcmal raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    
    // Yıl değişince
    const yilSelect = document.querySelector('[name="yilSelectIcmal"]');
    const donemSelect = document.querySelector('[name="donemSelectIcmal"]');
    
    if (yilSelect) {
        yilSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/icmal&yil=' + this.value;
        });
    }
    
    // Dönem değişince
    if (donemSelect) {
        donemSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/icmal&donem=' + this.value;
        });
    }
});
</script>
