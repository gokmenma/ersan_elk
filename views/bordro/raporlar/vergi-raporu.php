<?php
/**
 * Vergi Raporu
 * Dönem bazlı personel gelir vergisi ve damga vergisi raporunu görüntüler
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
$toplamGvMatrah = 0;
$toplamDvMatrah = 0;
$toplamGv = 0;
$toplamDv = 0;
$toplamOdenecek = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);

        // Özet verilerini hesapla
        foreach ($personeller as $personel) {
            $gvMatrah = 0;
            $dvMatrah = 0;

            if (!empty($personel->hesaplama_detay)) {
                $detay = json_decode($personel->hesaplama_detay, true);
                if (isset($detay['matrahlar']['gelir_vergisi_matrahi'])) {
                    $gvMatrah = floatval($detay['matrahlar']['gelir_vergisi_matrahi']);
                }
                if (isset($detay['matrahlar']['damga_vergisi_matrahi'])) {
                    $dvMatrah = floatval($detay['matrahlar']['damga_vergisi_matrahi']);
                }
            }
            
            $odenecekGv = floatval($personel->gelir_vergisi ?? 0);
            $odenecekDv = floatval($personel->damga_vergisi ?? 0);

            $toplamGvMatrah += $gvMatrah;
            $toplamDvMatrah += $dvMatrah;
            $toplamGv += $odenecekGv;
            $toplamDv += $odenecekDv;
            $toplamOdenecek += ($odenecekGv + $odenecekDv);
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
    $title = "Vergi Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-calculator text-danger me-2"></i>Vergi Raporu (GV & DV)
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span class="badge bg-danger text-white shadow-sm border border-danger"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectVergi',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>
                            
                            <?php echo Form::FormSelect2(
                                name: 'donemSelectVergi',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>
                            
                            <?php if ($selectedDonem): ?>
                                <a href="views/bordro/excel-vergi-export.php?donem_id=<?= $selectedDonemId ?>"
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
                            <!-- GV Matrahı -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bx-wallet fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">MATRAH (GV)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM GV MATRAHI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamGvMatrah, 2, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Ödenecek Gelir Vergisi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bxs-file-blank fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">KESİNTİ (GV)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">ÖDENECEK GELİR VERGİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-warning">
                                            <?= number_format($toplamGv, 2, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ödenecek Damga Vergisi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bxs-file fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">KESİNTİ (DV)</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">ÖDENECEK DAMGA VERGİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-danger">
                                            <?= number_format($toplamDv, 2, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplam Vergi -->
                            <div class="col-xl col-md-3">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #2a9d8f; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(42, 157, 143, 0.1);">
                                                <i class="bx bx-pie-chart fs-4 text-success"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">TOPLAM VERGİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">VERGİ KESİNTİLERİ TOPLAMI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading text-success">
                                            <?= number_format($toplamOdenecek, 2, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vergi Tablosu -->
                        <div class="table-responsive mt-3">
                            <table id="vergiRaporuTable" class="table table-hover table-bordered nowrap w-100 align-middle">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th style="width: 50px;" class="text-center" rowspan="2">#</th>
                                        <th rowspan="2">TC Kimlik No</th>
                                        <th rowspan="2">Ad Soyad</th>
                                        <th colspan="4" class="text-center border-bottom border-warning" style="background-color: #fff8e1;">GELİR VERGİSİ (GV)</th>
                                        <th colspan="3" class="text-center border-bottom border-danger" style="background-color: #ffebee;">DAMGA VERGİSİ (DV)</th>
                                        <th rowspan="2" class="text-end bg-light fw-bold text-success border-start align-middle">Toplam<br>Kesilen Vergi</th>
                                    </tr>
                                    <tr>
                                        <th class="text-end" style="background-color: #fffdf5;" title="Aylık Gelir Vergisi Matrahı">GV Matrahı</th>
                                        <th class="text-end" style="background-color: #fffdf5;" title="Kümülatif Gelir Vergisi Matrahı">Kümülatif M.</th>
                                        <th class="text-end" style="background-color: #fffdf5;" title="Uygulanan İstisna Tutarı">GV İstisnası</th>
                                        <th class="text-end fw-bold" style="background-color: #fff8e1;" title="Personelden Kesilen Net GV">Ödenen GV</th>
                                        <th class="text-end" style="background-color: #fff5f7;" title="Damga Vergisi Matrahı">DV Matrahı</th>
                                        <th class="text-end" style="background-color: #fff5f7;" title="Uygulanan İstisna Tutarı">DV İstisnası</th>
                                        <th class="text-end fw-bold" style="background-color: #ffebee;" title="Personelden Kesilen Net DV">Ödenen DV</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sira = 1;
                                    foreach ($personeller as $personel): 
                                        $gvMatrah = 0;
                                        $kumulatifGv = 0;
                                        $gvIstisna = 0;
                                        
                                        $dvMatrah = 0;
                                        $dvIstisna = 0;

                                        if (!empty($personel->hesaplama_detay)) {
                                            $detay = json_decode($personel->hesaplama_detay, true);
                                            $gvMatrah = floatval($detay['matrahlar']['gelir_vergisi_matrahi'] ?? 0);
                                            $kumulatifGv = floatval($detay['matrahlar']['kumulatif_vergi_matrahi_ay_basi'] ?? 0);
                                            $gvIstisna = floatval($detay['istisnalar']['gv_istisnasi'] ?? 0);
                                            
                                            $dvMatrah = floatval($detay['matrahlar']['damga_vergisi_matrahi'] ?? 0);
                                            $dvIstisna = floatval($detay['istisnalar']['dv_istisnasi'] ?? 0);
                                        }
                                        
                                        $odenecekGv = floatval($personel->gelir_vergisi ?? 0);
                                        $odenecekDv = floatval($personel->damga_vergisi ?? 0);
                                        $personelToplamVergi = $odenecekGv + $odenecekDv;
                                    ?>
                                        <tr>
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <span class="text-muted" style="font-family: monospace;"><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($personel->adi_soyadi) ?></span>
                                            </td>
                                            <td class="text-end" style="background-color: #fffdf5;">
                                                <?= number_format($gvMatrah, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fffdf5;">
                                                <?= number_format($kumulatifGv, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end text-success" style="background-color: #fffdf5;">
                                                <?= number_format($gvIstisna, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end fw-bold text-danger" style="background-color: #fff8e1;">
                                                <?= number_format($odenecekGv, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end" style="background-color: #fff5f7;">
                                                <?= number_format($dvMatrah, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end text-success" style="background-color: #fff5f7;">
                                                <?= number_format($dvIstisna, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end fw-bold text-danger" style="background-color: #ffebee;">
                                                <?= number_format($odenecekDv, 2, ',', '.') ?>
                                            </td>
                                            <td class="text-end bg-light fw-bold text-success border-start">
                                                <?= number_format($personelToplamVergi, 2, ',', '.') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <th colspan="3" class="text-end">GENEL TOPLAMLAR:</th>
                                        <th class="text-end text-primary"><?= number_format($toplamGvMatrah, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end"></th>
                                        <th class="text-end"></th>
                                        <th class="text-end text-warning" style="background-color: #fff8e1;"><?= number_format($toplamGv, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-primary"><?= number_format($toplamDvMatrah, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end"></th>
                                        <th class="text-end text-danger" style="background-color: #ffebee;"><?= number_format($toplamDv, 2, ',', '.') ?> ₺</th>
                                        <th class="text-end text-success fs-5 border-start"><?= number_format($toplamOdenecek, 2, ',', '.') ?> ₺</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="bx bx-calculator"></i>
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
                            <p class="text-muted">Vergi raporunu görüntülemek için yukarıdan bir dönem seçiniz.</p>
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
    if (document.getElementById('vergiRaporuTable')) {
        $('#vergiRaporuTable').DataTable({
            language: {
                url: 'assets/libs/datatables/Turkish.json'
            },
            pageLength: 50,
            order: [[2, 'asc']], // Ad Soyad'a göre sırala
            dom: '<"row align-items-center mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>',
            buttons: [
                {
                    extend: 'print',
                    text: '<i class="bx bx-printer me-1"></i> Yazdır',
                    className: 'btn btn-sm btn-secondary',
                    title: 'Bordro Vergi Raporu - <?= $selectedDonem ? htmlspecialchars($selectedDonem->donem_adi) : '' ?>',
                    footer: true,
                    orientation: 'landscape',
                    customize: function (win) {
                        $(win.document.body).css('font-size', '10pt');
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                    }
                }
            ],
            scrollX: true, 
            responsive: false
        });
    }
    
    // Yıl değişince
    const yilSelect = document.querySelector('[name="yilSelectVergi"]');
    const donemSelect = document.querySelector('[name="donemSelectVergi"]');
    
    if (yilSelect) {
        yilSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/vergi-raporu&yil=' + this.value;
        });
    }
    
    // Dönem değişince
    if (donemSelect) {
        donemSelect.addEventListener('change', function() {
            window.location.href = 'index?p=bordro/raporlar/vergi-raporu&donem=' + this.value;
        });
    }
});
</script>
