<?php
/**
 * Bordro Listesi Raporu
 * Personel bazlı detaylı bordro listesini görüntüler
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

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        // getPersonellerByDonem kullanarak personelleri alıyoruz
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);
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
    $title = "Bordro Çıktıları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title mb-0">
                                <i class="bx bx-receipt text-success me-2"></i>Bordro Çıktıları
                            </h5>
                            <?php if ($selectedDonem): ?>
                                <span class="badge bg-primary text-white shadow-sm border"><?= htmlspecialchars($selectedDonem->donem_adi) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'yilSelectBordro',
                                options: $yil_option,
                                selectedValue: $selectedYil,
                                label: 'Yıl',
                                icon: 'calendar',
                                style: 'min-width: 120px;'
                            ); ?>

                            <?php echo Form::FormSelect2(
                                name: 'donemSelectBordro',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem',
                                icon: 'calendar',
                                style: 'min-width: 180px;'
                            ); ?>

                            <?php if ($selectedDonem): ?>
                                <a target="_blank" href="views/bordro/bordro-yazdir.php?donem=<?= $selectedDonemId ?>"
                                    class="btn btn-success">
                                    <i class="bx bx-printer me-1"></i> Tümünü Yazdır
                                </a>
                            <?php endif; ?>

                            <a href="index?p=bordro/raporlar&donem=<?= $selectedDonemId ?>" class="btn btn-secondary shadow-sm">
                                <i class="bx bx-arrow-back me-1"></i> Raporlara Dön
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <?php if ($selectedDonem && !empty($personeller)): ?>
                        <!-- Bordro Listesi Tablosu -->
                        <div class="table-responsive">
                            <table id="bordroListesiTable" class="table table-hover table-bordered nowrap w-100 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Personel Bilgileri</th>
                                        <th>Departman / Görev</th>
                                        <th class="text-end">Brüt Maaş</th>
                                        <th class="text-end">Net Maaş</th>
                                        <th class="text-center" style="width: 120px;">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sira = 1;
                                    foreach ($personeller as $personel):
                                        $bordroId = $personel->id; // bordro_personel id
                                        $personelId = $personel->personel_id;
                                        ?>
                                        <tr>
                                            <td class="text-center fw-medium"><?= $sira++ ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                        alt="" class="rounded-circle avatar-sm me-3 border shadow-sm">
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?= htmlspecialchars($personel->adi_soyadi) ?></h6>
                                                        <span class="text-muted small">TC: <code class="text-dark bg-light px-1 rounded"><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></code></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark"><?= htmlspecialchars($personel->departman ?? '-') ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($personel->gorev ?? '-') ?></div>
                                            </td>
                                            <td class="text-end fw-medium text-secondary">
                                                <?= number_format(floatval($personel->brut_maas ?? 0), 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="text-end text-success fw-bold" style="font-size: 1.05rem;">
                                                <?= number_format(floatval($personel->net_maas ?? 0), 2, ',', '.') ?> ₺
                                            </td>
                                            <td class="text-center">
                                                <a href="views/bordro/bordro-yazdir.php?id=<?= $bordroId ?>&personel_id=<?= $personelId ?>" 
                                                   target="_blank" 
                                                   class="btn btn-sm btn-outline-primary fw-medium px-3 shadow-sm rounded-pill btn-hover-scale"
                                                   data-bs-toggle="tooltip" title="Bordroyu Yazdır/Görüntüle">
                                                    <i class="bx bx-printer fs-5 align-middle"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php elseif ($selectedDonem): ?>
                        <div class="text-center py-5">
                            <i class="bx bx-user-x display-1 text-muted opacity-50"></i>
                            <h5 class="mt-3">Personel Bulunamadı</h5>
                            <p class="text-muted">Bu dönemde henüz bordrosu hesaplanmış personel bulunmamaktadır.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bx bx-calendar-x display-1 text-muted opacity-50"></i>
                            <h5 class="mt-3">Dönem Seçimi Bekleniyor</h5>
                            <p class="text-muted">Bordro listesini görüntülemek için yukarıdan bir dönem seçiniz.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm { width: 2.5rem; height: 2.5rem; }
    .btn-hover-scale { transition: transform 0.2s ease; }
    .btn-hover-scale:hover { transform: scale(1.05); }
    table.dataTable.nowrap th, table.dataTable.nowrap td { white-space: normal; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Tooltip başlat
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
          return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // DataTable başlat
        if (document.getElementById('bordroListesiTable')) {
            destroyAndInitDataTable('#bordroListesiTable', {
                pageLength: 25,
                order: [[1, 'asc']],
                dom: '<"row align-items-center mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row align-items-center mt-3"<"col-md-6"i><"col-md-6"p>>',
                buttons: [
                    {
                        extend: 'print',
                        text: '<i class="bx bx-printer me-1"></i> Listeyi Yazdır',
                        className: 'btn btn-outline-secondary btn-sm',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4]
                        }
                    }
                ]
            });
        }

        const yilSelect = document.querySelector('[name="yilSelectBordro"]');
        const donemSelect = document.querySelector('[name="donemSelectBordro"]');

        if (yilSelect) {
            yilSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/bordro&yil=' + this.value;
            });
        }

        if (donemSelect) {
            donemSelect.addEventListener('change', function () {
                window.location.href = 'index?p=bordro/raporlar/bordro&donem=' + this.value;
            });
        }
    });
</script>
