<?php
use App\Model\PuantajModel;
use App\Model\PersonelModel;
use App\Helper\Helper;

$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$ekipKodu = $_GET['ekip_kodu'] ?? null;
$workType = $_GET['work_type'] ?? null;

$records = $Puantaj->getFiltered($startDate, $endDate, $ekipKodu, $workType);
$personeller = $Personel->all();
$workTypes = $Puantaj->getWorkTypes();

?>
<div class="container-fluid">
    <?php
    $maintitle = "Puantaj";
    $title = "İş Takip Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Filtreleme</h4>
                    <form method="GET" action="">
                        <input type="hidden" name="p" value="puantaj/list">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" name="start_date" class="form-control flatpickr" autocomplete="off"
                                    value="<?= $startDate ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" name="end_date" class="form-control flatpickr" autocomplete="off"
                                    value="<?= $endDate ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Personel (Ekip)</label>
                                <select name="ekip_kodu" class="form-control select2">
                                    <option value="">Tümü</option>
                                    <?php foreach ($personeller as $personel): ?>
                                        <option value="<?= $personel->adi_soyadi ?>" <?= $ekipKodu == $personel->adi_soyadi ? 'selected' : '' ?>>
                                            <?= $personel->adi_soyadi ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Yapılan İş</label>
                                <select name="work_type" class="form-control select2">
                                    <option value="">Tümü</option>
                                    <?php foreach ($workTypes as $wt): ?>
                                        <option value="<?= $wt ?>" <?= $workType == $wt ? 'selected' : '' ?>>
                                            <?= $wt ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="index.php?p=puantaj/list" class="btn btn-secondary">Temizle</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">İş Listesi</h4>
                    <div>
                        <a href="index.php?p=puantaj/upload" class="btn btn-success">
                            <i class="bx bxs-file-import"></i> Excel Yükle
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table id="puantajTable" class="table datatable table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Firma</th>
                                <th>İş Emri Tipi</th>
                                <th>Ekip (Personel)</th>
                                <th>İş Emri Sonucu</th>
                                <th>Sonuçlanmış</th>
                                <th>Açık Olanlar</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= $record->firma ?></td>
                                    <td><?= $record->is_emri_tipi ?></td>
                                    <td><?= $record->ekip_kodu ?></td>
                                    <td><?= $record->is_emri_sonucu ?></td>
                                    <td><?= $record->sonuclanmis ?></td>
                                    <td><?= $record->acik_olanlar ?></td>
                                    <td><?= $record->tarih ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Excel Modal -->
<div class="modal fade" id="importExcelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Excel'den Yükle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Excel yükleme özelliği bir sonraki adımda eklenecektir.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        // $('#puantajTable').DataTable();
        // Initialize select2 if available
        if ($.fn.select2) {
            $('.select2').select2();
        }
    });
</script>