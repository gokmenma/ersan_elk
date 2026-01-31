<?php

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Helper\Form;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();

// Seçili yıl ve dönem
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;

// İlgili yıldaki Tüm dönemleri getir
$donemler = $BordroDonem->getAllDonems($selectedYil);

// Yılları çıkar
$yil_option = $BordroDonem->getYearsByDonem();

$donem_option = [];
$donemlerByYil = [];
foreach ($donemler as $donem) {
    $yil = date('Y', strtotime($donem->baslangic_tarihi));
    $donemlerByYil[$yil][] = $donem;
    $donem_option[$donem->id] = $donem->donem_adi;
}

// Eğer dönem yoksa seçili id'yi boşalt
if (!$donemler) {
    $selectedDonemId = null;
}

// Eğer seçili dönem yoksa null ata
if (!$selectedDonemId) {
    $selectedDonemId = null;
}

if ($selectedDonemId) {
    $_SESSION['selectedDonemId'] = $selectedDonemId;
}

// Eğer seçili dönem veritabanında yoksa seçili dönem id session'a ata
$seciliDonemKontrol = $BordroDonem->find($selectedDonemId);
if (!$seciliDonemKontrol) {
    $selectedDonemId = null;
}

// Eğer dönem seçilmemişse, seçili yıldaki ilk dönemi seç
if ((!$selectedDonemId) && isset($donemlerByYil[$selectedYil]) && !empty($donemlerByYil[$selectedYil])) {
    $selectedDonemId = $donemlerByYil[$selectedYil][0]->id;
}

$selectedDonem = null;
$personelSayisi = 0;

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);
        $personelSayisi = count($personeller);
    }
}

// Rapor listesi
$raporlar = [
    [
        'id' => 'icmal',
        'baslik' => 'İcmal Raporu',
        'aciklama' => 'Dönem bazlı personel maaş özet raporu. Brüt maaş, kesintiler, ek ödemeler ve net maaş bilgilerini içerir.',
        'icon' => 'bx-file',
        'renk' => 'primary',
        'url' => 'index?p=bordro/raporlar/icmal&donem=',
        'download_url' => 'views/bordro/export-excel.php?donem=',
        'download_type' => 'excel'
    ],
    [
        'id' => 'bordro',
        'baslik' => 'Bordro',
        'aciklama' => 'Personel bazlı detaylı bordro çıktısı. Yazdırılabilir ve PDF formatında indirilebilir.',
        'icon' => 'bx-receipt',
        'renk' => 'success',
        'url' => 'index?p=bordro/raporlar/bordro&donem=',
        'download_url' => 'views/bordro/bordro-yazdir.php?donem=',
        'download_type' => 'print'
    ],
    [
        'id' => 'banka-listesi',
        'baslik' => 'Banka Listesi',
        'aciklama' => 'Bankaya gönderilecek ödeme listesi. IBAN, hesap numarası ve ödeme tutarlarını içerir.',
        'icon' => 'bxs-bank',
        'renk' => 'info',
        'url' => 'index?p=bordro/raporlar/banka-listesi&donem=',
        'download_url' => 'views/bordro/excel-banka-export.php?donem_id=',
        'download_type' => 'excel'
    ],
    [
        'id' => 'sgk-bildirge',
        'baslik' => 'SGK Bildirge',
        'aciklama' => 'SGK prim bildirge raporu. Personel SGK prim tutarlarını ve işveren paylarını içerir.',
        'icon' => 'bx-shield-quarter',
        'renk' => 'warning',
        'url' => 'index?p=bordro/raporlar/sgk-bildirge&donem=',
        'download_url' => null,
        'download_type' => null
    ],
    [
        'id' => 'vergi-raporu',
        'baslik' => 'Vergi Raporu',
        'aciklama' => 'Gelir vergisi ve damga vergisi detaylı raporu. Vergi matrahları ve kesinti tutarlarını içerir.',
        'icon' => 'bx-calculator',
        'renk' => 'danger',
        'url' => 'index?p=bordro/raporlar/vergi-raporu&donem=',
        'download_url' => null,
        'download_type' => null
    ],
    [
        'id' => 'maliyet-raporu',
        'baslik' => 'Maliyet Raporu',
        'aciklama' => 'İşveren maliyet analizi raporu. Toplam personel maliyetini ve detaylı dağılımını gösterir.',
        'icon' => 'bx-pie-chart-alt-2',
        'renk' => 'secondary',
        'url' => 'index?p=bordro/raporlar/maliyet-raporu&donem=',
        'download_url' => null,
        'download_type' => null
    ]
];
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $title = "Raporlar";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <!-- Sol Panel - Dönem Seçimi -->
        <div class="col-lg-3 col-md-4">
            <div class="card sticky-top" style="top: 80px; z-index: 100;">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-calendar me-2"></i>Dönem Seçimi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <?php echo Form::FormSelect2(
                            name: 'yilSelectRapor',
                            options: $yil_option,
                            selectedValue: $selectedYil,
                            label: 'Yıl Seçiniz',
                            icon: 'calendar',
                            style: 'width: 100%;'
                        ); ?>
                    </div>
                    
                    <div class="mb-3">
                        <?php echo Form::FormSelect2(
                            name: 'donemSelectRapor',
                            options: $donem_option,
                            selectedValue: $selectedDonemId,
                            label: 'Dönem Seçiniz',
                            icon: 'calendar',
                            style: 'width: 100%;'
                        ); ?>
                    </div>

                    <?php if ($selectedDonem): ?>
                        <div class="alert alert-light border mb-0">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bx bx-info-circle text-primary fs-4 me-2"></i>
                                <strong class="text-primary"><?= htmlspecialchars($selectedDonem->donem_adi) ?></strong>
                            </div>
                            <hr class="my-2">
                            <div class="small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Başlangıç:</span>
                                    <span class="fw-medium"><?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Bitiş:</span>
                                    <span class="fw-medium"><?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Personel:</span>
                                    <span class="fw-medium text-success"><?= $personelSayisi ?> kişi</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Durum:</span>
                                    <?php if ($selectedDonem->kapali_mi): ?>
                                        <span class="badge bg-danger"><i class="bx bx-lock me-1"></i>Kapalı</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="bx bx-lock-open me-1"></i>Açık</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bx bx-error-circle me-1"></i>
                            Henüz dönem oluşturulmamış.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sağ Panel - Rapor Listesi -->
        <div class="col-lg-9 col-md-8">
            <?php if ($selectedDonem): ?>
                <div class="row">
                    <?php foreach ($raporlar as $rapor): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 border-<?= $rapor['renk'] ?> border-opacity-25 rapor-card" 
                                 data-url="<?= $rapor['url'] . $selectedDonemId ?>">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="avatar-sm me-3">
                                            <span class="avatar-title bg-<?= $rapor['renk'] ?> bg-opacity-10 text-<?= $rapor['renk'] ?> rounded-circle fs-3">
                                                <i class="bx <?= $rapor['icon'] ?>"></i>
                                            </span>
                                        </div>
                                        <h5 class="card-title mb-0 text-<?= $rapor['renk'] ?>"><?= $rapor['baslik'] ?></h5>
                                    </div>
                                    <p class="card-text text-muted small"><?= $rapor['aciklama'] ?></p>
                                </div>
                                <div class="card-footer bg-transparent border-top-0 pt-0">
                                    <div class="d-flex gap-2">
                                        <a href="<?= $rapor['url'] . $selectedDonemId ?>" 
                                           class="btn btn-<?= $rapor['renk'] ?> btn-sm flex-grow-1">
                                            <i class="bx bx-show me-1"></i> Görüntüle
                                        </a>
                                        <?php if (!empty($rapor['download_url'])): ?>
                                            <a href="<?= $rapor['download_url'] . $selectedDonemId ?>" 
                                               class="btn btn-outline-<?= $rapor['renk'] ?> btn-sm"
                                               <?= $rapor['download_type'] === 'print' ? 'target="_blank"' : '' ?>
                                               title="<?= $rapor['download_type'] === 'excel' ? 'Excel İndir' : ($rapor['download_type'] === 'print' ? 'Yazdır' : 'İndir') ?>">
                                                <i class="bx <?= $rapor['download_type'] === 'excel' ? 'bx-download' : ($rapor['download_type'] === 'print' ? 'bx-printer' : 'bx-download') ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hızlı İşlemler -->
                <div class="card mt-2">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-download me-2"></i>Hızlı İndirme
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="views/bordro/export-excel.php?donem=<?= $selectedDonemId ?>" 
                                   class="btn btn-outline-success w-100">
                                    <i class="bx bx-spreadsheet me-2"></i>
                                    İcmal (Excel)
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="views/bordro/excel-banka-export.php?donem=<?= $selectedDonemId ?>" 
                                   class="btn btn-outline-info w-100">
                                    <i class="bx bxs-bank me-2"></i>
                                    Banka Listesi (Excel)
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="views/bordro/bordro-yazdir.php?donem=<?= $selectedDonemId ?>" 
                                   target="_blank"
                                   class="btn btn-outline-primary w-100">
                                    <i class="bx bx-printer me-2"></i>
                                    Tüm Bordroları Yazdır
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bx bx-calendar-x display-1 text-muted"></i>
                        <h5 class="mt-3">Dönem Seçilmedi</h5>
                        <p class="text-muted">Rapor görüntülemek için sol panelden bir dönem seçin veya yeni dönem oluşturun.</p>
                        <a href="index?p=bordro/list" class="btn btn-primary">
                            <i class="bx bx-plus me-1"></i> Bordro Yönetimine Git
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.rapor-card {
    transition: all 0.3s ease;
    cursor: pointer;
}
.rapor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.avatar-sm {
    width: 3rem;
    height: 3rem;
}
.avatar-title {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Yıl değişince dönemleri güncelle
    const yilSelect = document.querySelector('[name="yilSelectRapor"]');
    const donemSelect = document.querySelector('[name="donemSelectRapor"]');
    
    if (yilSelect) {
        yilSelect.addEventListener('change', function() {
            const yil = this.value;
            window.location.href = 'index?p=bordro/raporlar&yil=' + yil;
        });
    }
    
    // Dönem değişince sayfayı yenile
    if (donemSelect) {
        donemSelect.addEventListener('change', function() {
            const donemId = this.value;
            const yil = yilSelect ? yilSelect.value : '<?= $selectedYil ?>';
            window.location.href = 'index?p=bordro/raporlar&yil=' + yil + '&donem=' + donemId;
        });
    }
    
    // Kart tıklama
    document.querySelectorAll('.rapor-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Eğer buton tıklanmadıysa
            if (!e.target.closest('a')) {
                const url = this.dataset.url;
                if (url) {
                    window.location.href = url;
                }
            }
        });
    });
});
</script>
