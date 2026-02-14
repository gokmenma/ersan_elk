<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\TanimlamalarModel;

$Tanimlamalar = new TanimlamalarModel();

// Departman listesi (personel modülündeki aynı liste)
$departmanlar = [
    "BÜRO" => "BÜRO",
    'Kesme Açma' => 'Kesme Açma',
    'Kaçak Kontrol' => 'Kaçak Kontrol',
    'Endeks Okuma' => 'Endeks Okuma',
    'Sayaç Sökme Takma' => 'Sayaç Sökme Takma',
    'Mühürleme' => 'Mühürleme',
    'Kaçak Su Tespiti' => 'Kaçak Su Tespiti',
];

// Unvan ücret kayıtlarını getir
$unvanUcretler = $Tanimlamalar->getByGrup('unvan_ucret');

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Unvan / Ücret Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <style>
        .btn-label .label-icon svg {
            width: 18px !important;
            height: 18px !important;
        }

        .btn-label .label-icon {
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .badge-departman {
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 6px;
        }

        .ucret-badge {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(52, 195, 143, 0.1);
            color: #34c38f;
        }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">
                        <h4 class="card-title">Unvan / Ücret Tanımlamaları</h4>
                        <p class="card-title-desc">Departmanlara göre unvan/görev tanımlayabilir ve ücret
                            belirleyebilirsiniz.
                            Bu tanımlamalar personel kaydında departman ve görev seçildiğinde maaş tutarını otomatik
                            olarak belirleyecektir.
                        </p>
                    </div>

                    <div class="col-md-4">
                        <button type="button" id="actionEkle"
                            class="btn btn-primary waves-effect btn-label waves-light float-end" data-bs-toggle="modal"
                            data-bs-target="#actionModal"><i class="bx bx-save label-icon"></i>Yeni Ekle
                        </button>
                    </div>

                </div>

                <div class="card-body overflow-auto">

                    <!-- Filter by department -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select id="filterDepartman" class="form-select">
                                <option value="">Tüm Departmanlar</option>
                                <?php foreach ($departmanlar as $key => $value): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>">
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <table id="actionTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center">Sıra</th>
                                <th class="text-center">Departman</th>
                                <th class="text-center">Unvan / Görev</th>
                                <th class="text-center">Ücret Tutarı</th>
                                <th class="text-center">Açıklama</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $i = 0;
                            foreach ($unvanUcretler as $item) {
                                $i++;
                                $enc_id = Security::encrypt($item->id);
                                ?>
                                <tr id="row_<?php echo $item->id; ?>"
                                    data-departman="<?php echo htmlspecialchars($item->unvan_departman ?? ''); ?>">
                                    <td class="text-center">
                                        <?php echo $i ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-soft-primary text-primary badge-departman">
                                            <?php echo htmlspecialchars($item->unvan_departman ?? '---'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php echo htmlspecialchars($item->tur_adi ?? ''); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="ucret-badge">
                                            <?php echo Helper::formattedMoney($item->unvan_ucret ?? 0); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php echo htmlspecialchars($item->aciklama ?? ''); ?>
                                    </td>

                                    <td class="text-center" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    data-bs-boundary="viewport" aria-haspopup="true" aria-expanded="false">
                                                    <i class="bx bx-dots-vertical-rounded font-size-24 text-dark"></i>
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="#" class="dropdown-item duzenle"
                                                        data-id="<?php echo $enc_id; ?>"><span
                                                            class="mdi mdi-account-edit font-size-18"></span>
                                                        Düzenle</a>
                                                    <a href="#" class="dropdown-item sil" data-id="<?php echo $enc_id; ?>">
                                                        <span class="mdi mdi-delete font-size-18"></span>
                                                        Sil</a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->

</div> <!-- container-fluid -->

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Unvan / Ücret Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="unvan_ucret_id" id="unvan_ucret_id" class="form-control" value="0">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormSelect2(
                                    "unvan_departman",
                                    $departmanlar,
                                    "",
                                    "Departman",
                                    "grid",
                                    "key",
                                    "",
                                    "form-select select2",
                                    false,
                                    'width:100%',
                                    'data-placeholder="Departman Seçiniz"'
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "unvan_adi",
                                    "",
                                    "Unvan / Görev Adı giriniz",
                                    "Unvan / Görev Adı",
                                    "award",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "unvan_ucret",
                                    "",
                                    "Ücret Tutarı giriniz",
                                    "Ücret Tutarı (₺)",
                                    "dollar-sign",
                                    "form-control money"
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatTextarea(
                                    "aciklama",
                                    "",
                                    "Açıklama giriniz",
                                    "Açıklama",
                                    "file-text",
                                ); ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                <button type="button" id="actionKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                        class="bx bx-save label-icon"></i>Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/js/unvan-ucret.js"></script>