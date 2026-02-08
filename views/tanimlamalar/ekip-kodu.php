<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Service\Gate;
use App\Model\TanimlamalarModel;
use App\Model\SettingsModel;

$Tanimlamalar = new TanimlamalarModel();
$Settings = new SettingsModel();

$ekipler = $Tanimlamalar->getEkipKodlari();
$bolgeler = $Tanimlamalar->getEkipBolgeleri();

// Bölge kurallarını al
$bolgeKurallariJson = $Settings->getSettings('ekip_kodu_bolge_kurallari') ?? '{}';
$bolgeKurallari = json_decode($bolgeKurallariJson, true) ?: [];

// Admin yetkisi kontrolü
$canManageRules = Gate::allows('ekip_kodu_kurallari');

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Ekip Kodu Tanımlamalar";
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
    </style>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Ekip Kodları Listesi</h4>
                        <p class="card-title-desc">Ekip kodlarını görüntüleyebilir ve yeni ekip kodu
                            ekleyebilirsiniz.
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


                    <table id="actionTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center">Sıra</th>

                                <th class="text-center">Bölge</th>
                                <th class="text-center">Ekip Kodu</th>
                                <th class="text-center">Açıklama</th>
                                <th class="text-center">Durum</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($ekipler as $ekip) {
                                $i++;
                                $enc_id = Security::encrypt($ekip->id);
                                ?>
                                <tr id="row_<?php echo $ekip->id; ?>">
                                    <td class="text-center">
                                        <?php echo $i ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $ekip->ekip_bolge ?>
                                    </td>
                                    <td class="text-center" style="width: 30%;">
                                        <?php echo $ekip->tur_adi ?>
                                    </td>
                                    <td class="text-center " style="width: 30%;">
                                        <?php echo $ekip->aciklama ?>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        <?php
                                        echo $ekip->kullanim_sayisi > 0 ? '<span class="badge bg-danger">Dolu</span>' : '<span class="badge bg-success">Boşta</span>';
                                        if (!empty($ekip->personel_isimleri)) {
                                            echo '<br><small class="text-muted">' . $ekip->personel_isimleri . '</small>';
                                        }
                                        ?>
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
    <div class="modal-dialog <?php echo $canManageRules ? 'modal-lg' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Ekip Kodu İşlemleri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($canManageRules): ?>
                    <!-- Sekmeler -->
                    <ul class="nav nav-tabs nav-tabs-custom nav-justified mb-3" id="ekipKoduTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="ekipKodu-tab" data-bs-toggle="tab"
                                data-bs-target="#ekipKoduContent" type="button" role="tab" aria-controls="ekipKoduContent"
                                aria-selected="true">
                                <i data-feather="briefcase" class="me-1" style="width:16px;height:16px;"></i> Ekip Kodu
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bolgeKurallari-tab" data-bs-toggle="tab"
                                data-bs-target="#bolgeKurallariContent" type="button" role="tab"
                                aria-controls="bolgeKurallariContent" aria-selected="false">
                                <i data-feather="shield" class="me-1" style="width:16px;height:16px;"></i> Bölge Kuralları
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="ekipKoduTabContent">
                        <!-- Ekip Kodu Sekmesi -->
                        <div class="tab-pane fade show active" id="ekipKoduContent" role="tabpanel"
                            aria-labelledby="ekipKodu-tab">
                        <?php endif; ?>

                        <form id="actionForm">
                            <input type="hidden" name="ekip_id" id="ekip_id" class="form-control" value="0">

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <?php echo
                                        Form::FormSelect2(
                                            "ekip_bolge",
                                            $bolgeler,
                                            "",
                                            "Ekip Bölge",
                                            "map-pin",
                                            "id",
                                            "",
                                            "form-select select2",
                                            false,
                                            'width:100%',
                                            'data-placeholder="Bölge Seçiniz veya Yazınız"'
                                        ); ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <?php echo
                                        Form::FormFloatInput(
                                            "text",
                                            "ekip_kodu",
                                            "",
                                            "Ekip Kodu giriniz!",
                                            "Ekip Kodu",
                                            "briefcase",
                                            "form-control"
                                        ); ?>
                                    <small class="text-muted" id="ekipKoduInfo"></small>
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
                                            "map-pin",
                                        ); ?>
                                </div>
                            </div>
                        </form>

                        <?php if ($canManageRules): ?>
                        </div>

                        <!-- Bölge Kuralları Sekmesi -->
                        <div class="tab-pane fade" id="bolgeKurallariContent" role="tabpanel"
                            aria-labelledby="bolgeKurallari-tab">
                            <div class="alert alert-info fade show d-flex align-items-start" role="alert">
                                <i data-feather="info" class="me-2 flex-shrink-0" style="width:18px;height:18px;"></i>
                                <div>
                                    <strong>Bilgi:</strong> Bölge kuralları tanımlayarak ekip kodlarının belirli aralıklarda
                                    olmasını zorunlu kılabilirsiniz.
                                    <br><small class="text-muted">Örnek: "ANDIRIN" bölgesi için Ekip-10 ile Ekip-20 arasında
                                        olması gerekiyorsa, Min: 10, Maks: 20 girin.</small>
                                </div>
                            </div>

                            <form id="bolgeKurallariForm">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" id="bolgeKurallariTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 40%;">Bölge</th>
                                                <th style="width: 25%;">Min Ekip No</th>
                                                <th style="width: 25%;">Maks Ekip No</th>
                                                <th style="width: 10%;">İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bolgeKurallariBody">
                                            <?php if (!empty($bolgeKurallari)): ?>
                                                <?php foreach ($bolgeKurallari as $bolge => $kural): ?>
                                                    <tr data-bolge="<?php echo htmlspecialchars($bolge); ?>">
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm bolge-input"
                                                                value="<?php echo htmlspecialchars($bolge); ?>" readonly>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm min-input"
                                                                value="<?php echo intval($kural['min'] ?? 0); ?>" min="0">
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm max-input"
                                                                value="<?php echo intval($kural['max'] ?? 999); ?>" min="0">
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button"
                                                                class="btn btn-sm btn-danger kural-sil d-inline-flex align-items-center justify-content-center">
                                                                <i data-feather="trash-2" style="width:14px;height:14px;"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Yeni Kural Ekleme -->
                                <div class="card bg-light border-0 mt-3">
                                    <div class="card-body py-3">
                                        <h6 class="card-title mb-3"><i data-feather="plus-circle" class="me-1"
                                                style="width:16px;height:16px;"></i> Yeni Kural Ekle</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <?php
                                                // Kuralı tanımlanmamış bölgeleri filtrele
                                                $kuralOlmayanBolgeler = array_filter($bolgeler, function ($b) use ($bolgeKurallari) {
                                                    return !isset($bolgeKurallari[$b]);
                                                });
                                                echo Form::FormSelect2(
                                                    "yeniBolge",
                                                    $kuralOlmayanBolgeler,
                                                    "",
                                                    "Bölge",
                                                    "map-pin",
                                                    "val", // 'key' dışında bir değer verildiğinde dizideki metni değer olarak kullanır
                                                    "",
                                                    "form-select",
                                                    false,
                                                    'width:100%',
                                                    'data-placeholder="Bölge Seçin"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php echo Form::FormFloatInput(
                                                    "number",
                                                    "yeniMin",
                                                    "",
                                                    "Ör: 10",
                                                    "Min Ekip No",
                                                    "hash",
                                                    "form-control",
                                                    false,
                                                    null,
                                                    "off",
                                                    false,
                                                    'min="0"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <?php echo Form::FormFloatInput(
                                                    "number",
                                                    "yeniMax",
                                                    "",
                                                    "Ör: 20",
                                                    "Maks Ekip No",
                                                    "hash",
                                                    "form-control",
                                                    false,
                                                    null,
                                                    "off",
                                                    false,
                                                    'min="0"'
                                                ); ?>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button"
                                                    class="btn btn-success waves-effect btn-label waves-light w-100"
                                                    id="kuralEkle">
                                                    <i data-feather="plus"
                                                        class="label-icon d-flex align-items-center justify-content-center"
                                                        style="height: 100%; top: 0; width: 38px;"></i> Ekle
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary waves-effect btn-label waves-light float-start"
                    data-bs-dismiss="modal">
                    <i class="bx bx-x label-icon"></i>Kapat
                </button>

                <?php if ($canManageRules): ?>
                    <button type="button" id="kuralKaydet" class="btn btn-success waves-effect btn-label waves-light"
                        style="display: none;">
                        <i class="bx bx-save label-icon"></i>Kuralları Kaydet
                    </button>
                <?php endif; ?>

                <button type="button" id="actionKaydet"
                    class="btn btn-primary waves-effect btn-label waves-light float-end">
                   <i class="bx bx-save label-icon"></i>Kaydet
                </button>
  
            </div>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/js/ekip-kodu.js"></script>