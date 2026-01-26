<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\PuantajModel;
use App\Model\PersonelModel;

$Puantaj = new PuantajModel();
$Personel = new PersonelModel();

$startDate = $_GET['start_date'] ?? Date::firstDayOfThisMonth();
$endDate = $_GET['end_date'] ?? Date::today();
$ekipKodu = $_GET['ekip_kodu'] ?? '';
$workType = $_GET['work_type'] ?? '';


//Helper::dd([$startDate, $endDate, $ekipKodu, $workType]);

$records = $Puantaj->getFiltered($startDate, $endDate, $ekipKodu, $workType);
$personeller = $Personel->all();

/**İlk başına boş option ekle */
$personeller = ['' => 'Seçiniz'] + $personeller;


$workTypes = $Puantaj->getWorkTypes();



//Helper::dd($personelOptions);

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

                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'start_date',
                                    value: $startDate,
                                    placeholder: '',
                                    label: "Başlangıç Tarihi",
                                    icon: "calendar"
                                ); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo Form::FormFloatInput(
                                    type: 'text',
                                    name: 'end_date',
                                    value: $endDate,
                                    placeholder: '',
                                    label: "Bitiş Tarihi",
                                    icon: "calendar"
                                ); ?>
                            </div>
                            <div class="col-md-3">
                            <?php echo Form::FormSelect2('ekip_kodu', $personeller, null, 'Personel Adı Soyadı', 'grid', 'id', 'adi_soyadi', 'form-select select2', true); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo Form::FormSelect2(
                                    name: 'work_type',
                                    options: $workTypes,
                                    selectedValue:"id",
                                    textField: "is_emri_tipi",
                                    label: "Yapılan İş",
                                    icon:"users"
                                ); ?>
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