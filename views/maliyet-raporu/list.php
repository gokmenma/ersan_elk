<?php
/**
 * Maliyet Raporu – Birleşik Gider Görüntüleme & Manuel Gider Yönetimi
 *
 * Kaynaklar:
 *   Araç (yakıt, bakım, servis, sigorta)
 *   Demirbaş servis
 *   Personel bordro
 *   Manuel giderler
 */

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Security;
use App\Helper\Form;
use App\Model\MaliyetRaporuModel;
use App\Model\ManuelGiderModel;

$MaliyetRaporu = new MaliyetRaporuModel();
$ManuelGider   = new ManuelGiderModel();

/* -------- Filtre parametreleri -------- */
$selectedYil   = $_GET['yil']       ?? date('Y');
$selectedAy    = $_GET['ay']        ?? '';
$selectedKat   = $_GET['kategori']  ?? '';

// Tarih aralığı oluştur
if ($selectedAy && $selectedYil) {
    $baslangic = "{$selectedYil}-" . str_pad($selectedAy, 2, '0', STR_PAD_LEFT) . "-01";
    $bitis     = date('Y-m-t', strtotime($baslangic));
} elseif ($selectedYil) {
    $baslangic = "{$selectedYil}-01-01";
    $bitis     = "{$selectedYil}-12-31";
} else {
    $baslangic = null;
    $bitis     = null;
}

$kategoriFiltre = $selectedKat ?: null;

/* -------- Verileri çek -------- */
$tumGiderler     = $MaliyetRaporu->getAll($baslangic, $bitis, $kategoriFiltre);
$kategoriOzet    = $MaliyetRaporu->getCategorySummary($baslangic, $bitis);
$genelToplam     = $MaliyetRaporu->getGrandTotal($baslangic, $bitis);
$aylikToplam     = $MaliyetRaporu->getMonthlyTotals((int) $selectedYil);
$manuelGiderler  = $ManuelGider->getFiltered($baslangic, $bitis, $kategoriFiltre);

/* -------- Yıl ve Ay seçenekleri -------- */
$yilSecenekleri = [];
for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--) {
    $yilSecenekleri[$y] = (string) $y;
}

$aySecenekleri = [
    ''   => 'Tüm Yıl',
    '1'  => 'Ocak',   '2'  => 'Şubat',  '3'  => 'Mart',
    '4'  => 'Nisan',  '5'  => 'Mayıs',  '6'  => 'Haziran',
    '7'  => 'Temmuz', '8'  => 'Ağustos','9'  => 'Eylül',
    '10' => 'Ekim',   '11' => 'Kasım',  '12' => 'Aralık',
];

$kategoriSecenekleri = [
    ''           => 'Tüm Kategoriler',
    'Araç'       => 'Araç',
    'Personel'   => 'Personel',
    'Demirbaş'   => 'Demirbaş',
    'Operasyonel'=> 'Operasyonel',
    'Diğer'      => 'Diğer',
];

/* -------- Kategori renk haritası -------- */
$kategoriRenk = [
    'Araç'        => ['renk' => '#0ea5e9', 'ikon' => 'truck'],
    'Personel'    => ['renk' => '#f43f5e', 'ikon' => 'user'],
    'Demirbaş'    => ['renk' => '#8b5cf6', 'ikon' => 'tool'],
    'Operasyonel' => ['renk' => '#f59e0b', 'ikon' => 'settings'],
    'Diğer'       => ['renk' => '#64748b', 'ikon' => 'more-horizontal'],
];

/* -------- Ay ismi yardımcısı -------- */
$ayIsimleri = [1=>'Oca',2=>'Şub',3=>'Mar',4=>'Nis',5=>'May',6=>'Haz',7=>'Tem',8=>'Ağu',9=>'Eyl',10=>'Eki',11=>'Kas',12=>'Ara'];
?>

<div class="container-fluid">
    <?php
    $maintitle = "Raporlar";
    $title     = "Maliyet Raporu";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <!-- ======== FİLTRE KARTI ======== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Yıl -->
                <?php echo Form::FormSelect2(
                    name: 'yilSelect',
                    options: $yilSecenekleri,
                    selectedValue: $selectedYil,
                    label: 'Yıl',
                    icon: 'calendar',
                    style: 'min-width:120px'
                ); ?>

                <!-- Ay -->
                <?php echo Form::FormSelect2(
                    name: 'aySelect',
                    options: $aySecenekleri,
                    selectedValue: $selectedAy,
                    label: 'Ay',
                    icon: 'calendar',
                    style: 'min-width:150px'
                ); ?>

                <!-- Kategori -->
                <?php echo Form::FormSelect2(
                    name: 'kategoriSelect',
                    options: $kategoriSecenekleri,
                    selectedValue: $selectedKat,
                    label: 'Kategori',
                    icon: 'filter',
                    style: 'min-width:180px'
                ); ?>

                <button type="button" id="manuelGiderEkle"
                        class="btn btn-primary waves-effect btn-label waves-light ms-auto"
                        data-bs-toggle="modal" data-bs-target="#manuelGiderModal">
                    <i data-feather="plus" class="label-icon"></i> Manuel Gider Ekle
                </button>
            </div>
        </div>
    </div>

    <!-- ======== ÖZET KARTLARI ======== -->
    <div class="row g-3 mb-4">
        <!-- Genel Toplam -->
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100"
                 style="border-bottom: 3px solid #2a9d8f !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle p-2 me-2" style="background: rgba(42,157,143,0.1);">
                            <i data-feather="trending-up" class="fs-4 text-success"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size:0.65rem;">GENEL</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing:0.5px;opacity:0.7;">TOPLAM GİDER</p>
                    <h4 class="mb-0 fw-bold">
                        <?= number_format($genelToplam, 2, ',', '.') ?>
                        <span style="font-size:0.85rem;font-weight:600;">₺</span>
                    </h4>
                </div>
            </div>
        </div>

        <!-- Kategori kartları -->
        <?php foreach ($kategoriOzet as $ko):
            $cfg = $kategoriRenk[$ko->kategori] ?? $kategoriRenk['Diğer'];
        ?>
        <div class="col-xl col-md-6">
            <div class="card border-0 shadow-sm h-100"
                 style="border-bottom: 3px solid <?= $cfg['renk'] ?> !important;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle p-2 me-2" style="background: <?= $cfg['renk'] ?>1a;">
                            <i data-feather="<?= $cfg['ikon'] ?>" class="fs-4" style="color:<?= $cfg['renk'] ?>;"></i>
                        </div>
                        <span class="text-muted small fw-bold" style="font-size:0.65rem;">KATEGORİ</span>
                    </div>
                    <p class="text-muted mb-1 small fw-bold" style="letter-spacing:0.5px;opacity:0.7;">
                        <?= htmlspecialchars(mb_strtoupper($ko->kategori, 'UTF-8')) ?>
                    </p>
                    <h4 class="mb-0 fw-bold">
                        <?= number_format((float)$ko->toplam, 2, ',', '.') ?>
                        <span style="font-size:0.85rem;font-weight:600;">₺</span>
                    </h4>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ======== AYLIK GRAFİK TABLOSU ======== -->
    <?php if (!empty($aylikToplam)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-bottom bg-white">
            <h5 class="card-title mb-0">
                <i data-feather="bar-chart-2" class="text-primary me-2"></i>Aylık Gider Dağılımı – <?= htmlspecialchars($selectedYil) ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">Kategori</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th><?= $ayIsimleri[$m] ?></th>
                            <?php endfor; ?>
                            <th class="bg-light fw-bold">TOPLAM</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Aylık verileri matrise dönüştür
                        $matris = [];
                        foreach ($aylikToplam as $row) {
                            $matris[$row->kategori][(int)$row->ay] = (float)$row->toplam;
                        }
                        foreach ($matris as $kat => $aylar):
                            $satToplam = array_sum($aylar);
                            $cfg = $kategoriRenk[$kat] ?? $kategoriRenk['Diğer'];
                        ?>
                        <tr>
                            <td class="text-start fw-medium">
                                <i data-feather="<?= $cfg['ikon'] ?>" class="me-1" style="color:<?= $cfg['renk'] ?>"></i>
                                <?= htmlspecialchars($kat) ?>
                            </td>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <td class="<?= isset($aylar[$m]) ? '' : 'text-muted' ?>">
                                    <?= isset($aylar[$m]) ? number_format($aylar[$m], 0, ',', '.') : '-' ?>
                                </td>
                            <?php endfor; ?>
                            <td class="bg-light fw-bold"><?= number_format($satToplam, 2, ',', '.') ?> ₺</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="text-start">TOPLAM</td>
                            <?php
                            $ayToplamlar = [];
                            foreach ($matris as $aylar) {
                                for ($m = 1; $m <= 12; $m++) {
                                    $ayToplamlar[$m] = ($ayToplamlar[$m] ?? 0) + ($aylar[$m] ?? 0);
                                }
                            }
                            for ($m = 1; $m <= 12; $m++):
                            ?>
                                <td><?= ($ayToplamlar[$m] ?? 0) > 0 ? number_format($ayToplamlar[$m], 0, ',', '.') : '-' ?></td>
                            <?php endfor; ?>
                            <td class="text-primary fs-6"><?= number_format($genelToplam, 2, ',', '.') ?> ₺</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======== TÜM GİDERLER TABLOSU ======== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-bottom bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">
                    <i data-feather="list" class="text-primary me-2"></i>Tüm Gider Kayıtları
                    <span class="badge bg-secondary ms-2"><?= count($tumGiderler) ?></span>
                </h5>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($tumGiderler)): ?>
            <div class="table-responsive">
                <table id="maliyetDetayTable" class="table table-hover table-bordered nowrap w-100 align-middle datatable">
                    <thead class="table-light text-muted">
                        <tr>
                            <th style="width:50px;" class="text-center">#</th>
                            <th>Tarih</th>
                            <th>Kategori</th>
                            <th>Alt Kategori</th>
                            <th class="text-end">Tutar</th>
                            <th>Kaynak</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sira = 1;
                        foreach ($tumGiderler as $g):
                            $cfg = $kategoriRenk[$g->kategori] ?? $kategoriRenk['Diğer'];
                        ?>
                        <tr>
                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                            <td><?= htmlspecialchars($g->tarih) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $cfg['renk'] ?>1a;color:<?= $cfg['renk'] ?>;">
                                    <i data-feather="<?= $cfg['ikon'] ?>" class="me-1"></i><?= htmlspecialchars($g->kategori) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($g->alt_kategori ?? '-') ?></td>
                            <td class="text-end fw-medium"><?= number_format((float)$g->tutar, 2, ',', '.') ?> ₺</td>
                            <td>
                                <span class="badge bg-light text-dark border" style="font-size:11px;">
                                    <?= htmlspecialchars($g->kaynak_tablo) ?>
                                </span>
                            </td>
                            <td class="text-truncate" style="max-width:300px;"
                                title="<?= htmlspecialchars($g->aciklama ?? '') ?>">
                                <?= htmlspecialchars($g->aciklama ?? '-') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <th colspan="4" class="text-end">GENEL TOPLAM:</th>
                            <th class="text-end text-primary fs-6"><?= number_format($genelToplam, 2, ',', '.') ?> ₺</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div class="avatar-lg mx-auto mb-3">
                    <div class="avatar-title bg-light text-muted rounded-circle fs-2">
                        <i data-feather="folder"></i>
                    </div>
                </div>
                <h5 class="mt-3 text-secondary">Kayıt Bulunamadı</h5>
                <p class="text-muted">Seçilen filtre kriterlerine göre gider kaydı bulunmamaktadır.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ======== MANUEL GİDERLER TABLOSU ======== -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-bottom bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0">
                    <i data-feather="edit-3" class="text-warning me-2"></i>Manuel Giderler
                    <span class="badge bg-warning text-dark ms-2"><?= count($manuelGiderler) ?></span>
                </h5>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($manuelGiderler)): ?>
            <div class="table-responsive">
                <table id="manuelGiderTable" class="table table-hover table-bordered nowrap w-100 align-middle datatable">
                    <thead class="table-light text-muted">
                        <tr>
                            <th style="width:50px;" class="text-center">#</th>
                            <th>Tarih</th>
                            <th>Kategori</th>
                            <th>Alt Kategori</th>
                            <th class="text-end">Tutar</th>
                            <th>Belge No</th>
                            <th>Açıklama</th>
                            <th style="width:5%" class="text-center no-export">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sira = 1;
                        foreach ($manuelGiderler as $mg):
                            $enc_id = Security::encrypt($mg->id);
                            $cfg = $kategoriRenk[$mg->kategori] ?? $kategoriRenk['Diğer'];
                        ?>
                        <tr id="mgider_<?= $mg->id ?>">
                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                            <td><?= htmlspecialchars($mg->tarih) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $cfg['renk'] ?>1a;color:<?= $cfg['renk'] ?>;">
                                    <i data-feather="<?= $cfg['ikon'] ?>" class="me-1"></i><?= htmlspecialchars($mg->kategori) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($mg->alt_kategori ?? '-') ?></td>
                            <td class="text-end fw-medium"><?= number_format((float)$mg->tutar, 2, ',', '.') ?> ₺</td>
                            <td><?= htmlspecialchars($mg->belge_no ?? '-') ?></td>
                            <td class="text-truncate" style="max-width:250px;"
                                title="<?= htmlspecialchars($mg->aciklama ?? '') ?>">
                                <?= htmlspecialchars($mg->aciklama ?? '-') ?>
                            </td>
                            <td class="text-center no-export">
                                <div class="dropdown">
                                    <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        <i data-feather="more-vertical" class="font-size-24 text-dark"></i>
                                    </a>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item manuel-gider-duzenle" href="#" data-id="<?= $enc_id ?>">
                                            <span class="mdi mdi-account-edit font-size-18"></span> Düzenle
                                        </a>
                                        <a class="dropdown-item manuel-gider-sil" href="#" data-id="<?= $enc_id ?>">
                                            <span class="mdi mdi-delete font-size-18"></span> Sil
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <p class="text-muted mb-0">Henüz manuel gider kaydı eklenmemiştir.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======== MANUEL GİDER MODAL ======== -->
<div class="modal fade" id="manuelGiderModal" tabindex="-1" aria-labelledby="manuelGiderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manuelGiderModalLabel">Yeni Manuel Gider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="manuelGiderForm">
                    <input type="hidden" name="manuel_gider_id" id="manuel_gider_id" value="0">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormSelect2(
                                name: 'kategori',
                                options: ManuelGiderModel::KATEGORILER,
                                selectedValue: '',
                                label: 'Kategori',
                                icon: 'filter',
                            ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text', 'alt_kategori', '', 'Alt kategori', 'Alt Kategori', 'tag',
                            ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text', 'tutar', '', 'Tutar giriniz', 'Tutar (₺)', 'dollar-sign',
                                'form-control money',
                            ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo Form::FormFloatInput(
                                'text', 'tarih', '', 'Tarih seçiniz', 'Tarih', 'calendar',
                                'form-control flatpickr',
                            ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo Form::FormFloatInput(
                                'text', 'belge_no', '', 'Fatura / belge no', 'Belge No', 'file',
                            ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo Form::FormFloatTextarea(
                                'aciklama', '', 'Açıklama giriniz', 'Açıklama', 'edit',
                                'form-control', false, '80px', 3,
                            ); ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="save" class="me-1"></i> Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script src="views/maliyet-raporu/js/maliyet-raporu.js"></script>

<script>
$(document).ready(function () {
    var baseUrl = 'index?p=maliyet-raporu/list';

    function applyFilter() {
        var yil = $('[name="yilSelect"]').val();
        var ay  = $('[name="aySelect"]').val();
        var kat = $('[name="kategoriSelect"]').val();
        var url = baseUrl + '&yil=' + yil;
        if (ay)  url += '&ay=' + ay;
        if (kat) url += '&kategori=' + encodeURIComponent(kat);
        window.location.href = url;
    }

    $('[name="yilSelect"]').on('change', applyFilter);
    $('[name="aySelect"]').on('change', applyFilter);
    $('[name="kategoriSelect"]').on('change', applyFilter);

    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});
</script>
