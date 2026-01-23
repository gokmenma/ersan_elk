<?php

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Helper\Form;
use App\Helper\Helper;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();


// Seçili yıl ve dönem
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;

/**Eğer bir kere dönem seçilmişse onu session'a ata */
if ($selectedDonemId) {
    $_SESSION['selectedDonemId'] = $selectedDonemId;
}

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

// Eğer dönem seçilmemişse, seçili yıldaki ilk dönemi seç
if (!$selectedDonemId && isset($donemlerByYil[$selectedYil]) && !empty($donemlerByYil[$selectedYil])) {
    $selectedDonemId = $donemlerByYil[$selectedYil][0]->id;
}

$selectedDonem = null;
$personeller = [];

if ($selectedDonemId) {
    $selectedDonem = $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);
    }
}

// Dönem kapalı mı kontrolü
$donemKapali = $selectedDonem ? ($selectedDonem->kapali_mi ?? 0) : 0;

$kesinti_turleri = [
    '' => "Seçiniz",
    'icra' => 'İcra',
    'avans' => 'Avans',
    'nafaka' => 'Nafaka',
    'izin_kesinti' => 'Ücretsiz İzin',
    'diger' => 'Diğer'
];

$ek_odeme_turleri = [
    '' => "Seçiniz",
    'prim' => 'Prim',
    'mesai' => 'Fazla Mesai',
    'ikramiye' => 'İkramiye',
    'yol' => 'Yol Yardımı',
    'yemek' => 'Yemek Yardımı',
    'diger' => 'Diğer'
];
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $title = "Bordro Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <?php echo Form::FormSelect2(
                            name: 'yilSelect',
                            options: $yil_option,
                            selectedValue: $selectedYil,
                            label: 'Yıl Seçiniz',
                            icon: 'calendar',
                            style: 'min-width: 200px;'
                        ); ?>

                        <div class="d-flex align-items-center gap-2">
                            <?php echo Form::FormSelect2(
                                name: 'donemSelect',
                                options: $donem_option,
                                selectedValue: $selectedDonemId,
                                label: 'Dönem Seçiniz',
                                icon: 'calendar',
                                style: 'min-width: 200px;'
                            ); ?>

                            <?php if ($selectedDonem): ?>
                                <div class="form-check form-switch ms-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchDonemDurum"
                                        <?= $donemKapali ? 'checked' : '' ?>>
                                    <label
                                        class="form-check-label small <?= $donemKapali ? 'text-danger' : 'text-success' ?>"
                                        for="switchDonemDurum">
                                        <?= $donemKapali ? '<i class="bx bx-lock"></i> Kapalı' : '<i class="bx bx-lock-open"></i> Açık' ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="vr mx-2 d-none d-md-block"></div>

                        <button type="button" class="btn btn-outline-success waves-effect waves-light"
                            data-bs-toggle="modal" data-bs-target="#yeniDonemModal">
                            <i class="bx bx-plus"></i>
                        </button>
                        <?php if (!$donemKapali) { ?>
                            <button type="button" id="donemSil" class="btn btn-outline-danger waves-effect waves-light">
                                <i class="bx bx-trash"></i>
                            </button>
                        <?php } ?>

                        <div class="d-flex flex-wrap gap-2 ms-auto">
                            <?php if ($selectedDonem): ?>
                                <button type="button" class="btn btn-info waves-effect waves-light" id="btnRefreshPersonel"
                                    <?= $donemKapali ? 'disabled' : '' ?>>
                                    <i class="bx bx-refresh me-1"></i> Personel Güncelle
                                </button>
                                <button type="button" class="btn btn-warning waves-effect waves-light" id="btnHesapla"
                                    <?= $donemKapali ? 'disabled' : '' ?>>
                                    <i class="bx bx-calculator me-1"></i> Maaş Hesapla
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-secondary dropdown-toggle waves-effect waves-light"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bx bx-menu me-1"></i> İşlemler
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="javascript:void(0);" id="btnExportExcel">
                                                <i class="bx bx-download me-2 text-success"></i> Excel'e İndir
                                            </a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="javascript:void(0);" id="btnExportExcelBanka">
                                                <i class="bx bxs-bank me-2 text-primary"></i> Excel'e İndir (Banka)
                                            </a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li>
                                            <a class="dropdown-item <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#gelirEkleModal">
                                                <i class="bx bx-plus-circle me-2 text-primary"></i> Gelir Ekle (Excel)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#kesintiEkleModal">
                                                <i class="bx bx-minus-circle me-2 text-danger"></i> Kesinti Ekle (Excel)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#odemeEkleModal">
                                                <i class="bx bx-wallet me-2 text-info"></i> Ödeme Dağıt (Excel)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($selectedDonem): ?>
                        <div class="alert alert-primary d-flex align-items-center mb-4" role="alert">
                            <i class="bx bx-info-circle me-2 fs-4"></i>
                            <div>
                                <strong><?= htmlspecialchars($selectedDonem->donem_adi) ?></strong> dönemine ait
                                <strong><?= count($personeller) ?></strong> personel listeleniyor.
                                <span class="ms-2 text-muted">
                                    (<?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?> -
                                    <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>)
                                </span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="bordroTable" class="table datatable table-hover table-bordered nowrap w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20px;">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="selectAll">
                                            </div>
                                        </th>
                                        <th style="width: 20px;">TC Kimlik No</th>
                                        <th>Personel</th>
                                        <th class="text-center">Çalışma Günü</th>
                                        <th class="text-end">Top. Ek Ödeme</th>
                                        <th class="text-end">Top. Kesinti</th>
                                        <th class="text-end">Net Maaş</th>
                                        <th class="text-end">Banka</th>
                                        <th class="text-end">Sodexo</th>
                                        <th class="text-end">Elden</th>
                                        <th class="text-center">Durum</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($personeller)): ?>
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-4">
                                                <i class="bx bx-user-x fs-1 d-block mb-2"></i>
                                                Bu döneme henüz personel eklenmemiş.<br>
                                                <small>"Personelleri Güncelle" butonuna tıklayarak personelleri
                                                    ekleyebilirsiniz.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($personeller as $personel): ?>
                                            <?php
                                            $eldenOdeme = ($personel->net_maas ?? 0) - ($personel->banka_odemesi ?? 0) - ($personel->sodexo_odemesi ?? 0) - ($personel->diger_odeme ?? 0);

                                            // Ücretsiz izin gün sayısını hesapla
                                            $ucretsizIzinGunu = 0;
                                            if (!empty($personel->hesaplama_detay)) {
                                                $detay = json_decode($personel->hesaplama_detay, true);
                                                if (isset($detay['matrahlar']['ucretsiz_izin_kesinti']) && isset($detay['matrahlar']['brut_maas']) && $detay['matrahlar']['brut_maas'] > 0) {
                                                    $gunlukUcret = $detay['matrahlar']['brut_maas'] / 30;
                                                    $ucretsizIzinGunu = round($detay['matrahlar']['ucretsiz_izin_kesinti'] / $gunlukUcret);
                                                }
                                            }
                                            $calismaGunu = 30 - $ucretsizIzinGunu;
                                            ?>
                                            <tr data-id="<?= $personel->id ?>">
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input personel-check"
                                                            value="<?= $personel->id ?>">
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                            alt="" class="rounded-circle avatar-sm me-2">
                                                        <span class="fw-medium">
                                                            <a
                                                                href="index?p=personel/manage&id=<?= $personel->personel_id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></a></span>
                                                    </div>
                                                </td>

                                                <td
                                                    class="text-center <?= $ucretsizIzinGunu > 0 ? 'text-warning fw-bold' : 'text-secondary' ?>">
                                                    <?= $calismaGunu ?> gün
                                                    <?php if ($ucretsizIzinGunu > 0): ?>
                                                        <small class="d-block text-danger">(-<?= $ucretsizIzinGunu ?> izin)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end text-success">
                                                    <?= $personel->guncel_toplam_ek_odeme > 0 ? number_format($personel->guncel_toplam_ek_odeme, 2, ',', '.') . ' ₺' : '-' ?>
                                                    <i class="bx bx-list-ul ms-1 text-primary cursor-pointer btn-detail-ekodeme"
                                                        data-id="<?= $personel->personel_id ?>"
                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                        title="Detayları Gör"></i>
                                                </td>
                                                <td class="text-end text-danger">
                                                    <?= $personel->guncel_toplam_kesinti > 0 ? number_format($personel->guncel_toplam_kesinti, 2, ',', '.') . ' ₺' : '-' ?>
                                                    <i class="bx bx-list-ul ms-1 text-danger cursor-pointer btn-detail-kesinti"
                                                        data-id="<?= $personel->personel_id ?>"
                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                        title="Detayları Gör"></i>
                                                </td>
                                                <td class="text-end fw-bold text-success">
                                                    <?= $personel->net_maas ? number_format($personel->net_maas, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td class="text-end text-primary">
                                                    <?= $personel->banka_odemesi ? number_format($personel->banka_odemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td class="text-end text-info">
                                                    <?= $personel->sodexo_odemesi ? number_format($personel->sodexo_odemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td class="text-end text-warning fw-bold">
                                                    <?= $eldenOdeme > 0 ? number_format($eldenOdeme, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td style="width: 60px;" class="text-center text-wrap">
                                                    <?php if ($personel->hesaplama_tarihi): ?>
                                                        <span class="badge bg-success">Hesaplandı</span>
                                                        <small><?= $personel->hesaplama_tarihi ?></small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Bekliyor</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            <i class="bx bx-dots-vertical-rounded"></i>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item btn-odeme<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);" data-id="<?= $personel->id ?>"
                                                                    data-net="<?= $personel->net_maas ?? 0 ?>"
                                                                    data-banka="<?= $personel->banka_odemesi ?? 0 ?>"
                                                                    data-sodexo="<?= $personel->sodexo_odemesi ?? 0 ?>"
                                                                    data-diger="<?= $personel->diger_odeme ?? 0 ?>"
                                                                    data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                    <i class="bx bx-wallet me-2"></i> Ödeme Dağıt
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-detail" href="javascript:void(0);"
                                                                    data-id="<?= $personel->id ?>">
                                                                    <i class="bx bx-show me-2"></i> Detay
                                                                </a>
                                                            </li>
                                                            <li>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-gelir-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);"
                                                                    data-id="<?= $personel->personel_id ?>"
                                                                    data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                    <i class="bx bx-plus-circle me-2 text-success"></i> Gelir Ekle
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-kesinti-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);"
                                                                    data-id="<?= $personel->personel_id ?>"
                                                                    data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                    <i class="bx bx-minus-circle me-2 text-danger"></i> Kesinti Ekle
                                                                </a>
                                                            </li>
                                                            <li>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-remove text-danger<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);" data-id="<?= $personel->id ?>">
                                                                    <i class="bx bx-trash me-2"></i> Dönemden Çıkar
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bx bx-calendar-x display-1 text-muted"></i>
                            <h5 class="mt-3">Henüz Dönem Oluşturulmamış</h5>
                            <p class="text-muted">Bordro işlemlerine başlamak için yeni bir dönem oluşturun.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#yeniDonemModal">
                                <i class="bx bx-plus me-1"></i> İlk Dönemi Oluştur
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Yeni Dönem Modal -->
<div class="modal fade" id="yeniDonemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-calendar-plus me-2"></i>Yeni Dönem Oluştur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formYeniDonem">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="donem_adi" class="form-label">Dönem Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="donem_adi" name="donem_adi"
                            placeholder="Örn: Ocak 2026" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi"
                                required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" required>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-1"></i>
                        <small>Dönem oluşturulduğunda, belirlenen tarih aralığında çalışan personeller otomatik olarak
                            döneme eklenecektir.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Dönem Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ödeme Dağıt Modal -->
<div class="modal fade" id="odemeDagitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-wallet me-2"></i>Ödeme Dağıt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formOdemeDagit">
                <input type="hidden" name="id" id="odeme_bordro_id">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong id="odeme_personel_ad"></strong><br>
                        Net Maaş: <strong class="text-success" id="odeme_net_maas"></strong>
                    </div>

                    <div class="mb-3">
                        <label for="banka_odemesi" class="form-label">
                            <i class="bx bx-credit-card me-1 text-primary"></i> Banka Ödemesi
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="banka_odemesi" name="banka_odemesi"
                                step="0.01" min="0" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="sodexo_odemesi" class="form-label">
                            <i class="bx bx-food-menu me-1 text-info"></i> Sodexo/Yemek Kartı
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="sodexo_odemesi" name="sodexo_odemesi"
                                step="0.01" min="0" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="diger_odeme" class="form-label">
                            <i class="bx bx-money me-1 text-secondary"></i> Diğer Ödemeler
                        </label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="diger_odeme" name="diger_odeme" step="0.01"
                                min="0" value="0">
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bx bx-wallet me-1 text-warning"></i> <strong>Elden
                                Ödenecek:</strong></span>
                        <span class="fs-5 fw-bold text-warning" id="elden_odeme_goster">0,00 ₺</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Gelir Ekle Modal -->
<div class="modal fade" id="gelirEkleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-plus-circle me-2"></i>Gelir Ekle (Excel)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formGelirEkle" enctype="multipart/form-data">
                <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                <div class="modal-body">
                    <div
                        class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bx bx-download fs-4 me-2 text-success"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><strong>Şablon Dosyasını İndirin</strong></h6>
                                <p class="mb-2 small text-muted">
                                    Tanımladığınız gelir parametrelerine göre hazırlanan Excel şablonunu indirin.
                                </p>
                                <a href="views/bordro/excel-sablon-olustur.php?tip=gelir&donem=<?= $selectedDonemId ?>"
                                    class="btn btn-sm btn-success">
                                    <i class="bx bx-download me-1"></i>Gelir Şablonunu İndir
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="gelirExcelFile" class="form-label">Excel Dosyası <span
                                class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="gelirExcelFile" name="excel_file"
                            accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-upload me-1"></i>Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Kesinti Ekle Modal -->
<div class="modal fade" id="kesintiEkleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bx bx-minus-circle me-2"></i>Kesinti Ekle (Excel)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formKesintiEkle" enctype="multipart/form-data">
                <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                <div class="modal-body">
                    <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bx bx-download fs-4 me-2 text-danger"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><strong>Şablon Dosyasını İndirin</strong></h6>
                                <p class="mb-2 small text-muted">
                                    Tanımladığınız kesinti parametrelerine göre hazırlanan Excel şablonunu indirin.
                                </p>
                                <a href="views/bordro/excel-sablon-olustur.php?tip=kesinti&donem=<?= $selectedDonemId ?>"
                                    class="btn btn-sm btn-danger">
                                    <i class="bx bx-download me-1"></i>Kesinti Şablonunu İndir
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="kesintiExcelFile" class="form-label">Excel Dosyası <span
                                class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="kesintiExcelFile" name="excel_file"
                            accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger"><i class="bx bx-upload me-1"></i>Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ödeme Dağıt (Excel) Modal -->
<div class="modal fade" id="odemeEkleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bx bx-wallet me-2"></i>Ödeme Dağıt (Excel)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formOdemeEkle" enctype="multipart/form-data">
                <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                <div class="modal-body">
                    <div class="alert alert-info bg-info bg-opacity-10 border border-info border-opacity-25 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bx bx-download fs-4 me-2 text-info"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><strong>Şablon Dosyasını İndirin</strong></h6>
                                <p class="mb-2 small text-muted">
                                    Mevcut personeller ve net maaş dağılımları için hazırlanan Excel şablonunu indirin.
                                </p>
                                <a href="views/bordro/excel-sablon-olustur.php?tip=odeme&donem=<?= $selectedDonemId ?>"
                                    class="btn btn-sm btn-info text-white">
                                    <i class="bx bx-download me-1"></i>Ödeme Şablonunu İndir
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="odemeExcelFile" class="form-label">Excel Dosyası <span
                                class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="odemeExcelFile" name="excel_file"
                            accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info text-white"><i
                            class="bx bx-upload me-1"></i>Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Personel Gelir Ekle Modal -->
<div class="modal fade" id="modalPersonelGelirEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bx bx-plus-circle me-2"></i>Personel Gelir Yönetimi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="alert alert-success mb-3">
                    <strong id="gelir_personel_ad"></strong> için gelir yönetimi.
                </div>

                <!-- Yeni Gelir Ekle Accordion -->
                <div class="accordion mb-3" id="accordionGelirEkle">
                    <div class="accordion-item border-0 shadow-sm">
                        <h2 class="accordion-header" id="headingGelir">
                            <button class="accordion-button collapsed fw-medium" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseGelir" aria-expanded="false" aria-controls="collapseGelir">
                                <i class="bx bx-plus me-2 text-success"></i> Yeni Gelir Ekle
                            </button>
                        </h2>
                        <div id="collapseGelir" class="accordion-collapse collapse" aria-labelledby="headingGelir"
                            data-bs-parent="#accordionGelirEkle">
                            <div class="accordion-body bg-white">
                                <form id="formPersonelGelirEkle">
                                    <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                                    <input type="hidden" name="personel_id" id="gelir_personel_id">
                                    <input type="hidden" name="id" id="gelir_edit_id" value="0">

                                    <div class="mb-3">
                                        <?= Form::FormSelect2(
                                            name: "ek_odeme_tur",
                                            options: $ek_odeme_turleri,
                                            selectedValue: '',
                                            label: "Ek Ödeme Türü",
                                            icon: "list",
                                            valueField: '',
                                            textField: '',
                                            required: true
                                        ) ?>
                                    </div>

                                    <div class="mb-3">
                                        <?= Form::FormFloatInput("number", "tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" id="gelir_tutar"') ?>
                                    </div>

                                    <div class="mb-3">
                                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "message-square", "form-control", false, null, "off", false, 'id="gelir_aciklama"') ?>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-success"><i
                                                class="bx bx-save me-1"></i>Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="listPersonelGelirler" class="mt-3">
                    <!-- Gelir listesi buraya yüklenecek -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Personel Kesinti Ekle Modal -->
<div class="modal fade" id="modalPersonelKesintiEkle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bx bx-minus-circle me-2"></i>Personel Kesinti Yönetimi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div
                    class="alert alert-danger mb-3 bg-danger bg-opacity-10 text-danger border-danger border-opacity-25">
                    <strong id="kesinti_personel_ad"></strong> için kesinti yönetimi.
                </div>

                <!-- Yeni Kesinti Ekle Accordion -->
                <div class="accordion mb-3" id="accordionKesintiEkle">
                    <div class="accordion-item border-0 shadow-sm">
                        <h2 class="accordion-header" id="headingKesinti">
                            <button class="accordion-button collapsed fw-medium" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseKesinti" aria-expanded="false" aria-controls="collapseKesinti">
                                <i class="bx bx-minus me-2 text-danger"></i> Yeni Kesinti Ekle
                            </button>
                        </h2>
                        <div id="collapseKesinti" class="accordion-collapse collapse" aria-labelledby="headingKesinti"
                            data-bs-parent="#accordionKesintiEkle">
                            <div class="accordion-body bg-white">
                                <form id="formPersonelKesintiEkle">
                                    <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                                    <input type="hidden" name="personel_id" id="kesinti_personel_id">
                                    <input type="hidden" name="id" id="kesinti_edit_id" value="0">

                                    <div class="mb-3">
                                        <?= Form::FormSelect2(
                                            name: "kesinti_tur",
                                            options: $kesinti_turleri,
                                            selectedValue: '',
                                            label: "Kesinti Türü",
                                            icon: "list",
                                            valueField: '',
                                            textField: '',
                                            required: true
                                        ) ?>
                                    </div>

                                    <div class="mb-3">
                                        <?= Form::FormFloatInput("number", "tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" id="kesinti_tutar"') ?>
                                    </div>

                                    <div class="mb-3">
                                        <?= Form::FormFloatInput("text", "aciklama", "", "Açıklama giriniz", "Açıklama", "message-square", "form-control", false, null, "off", false, 'id="kesinti_aciklama"') ?>
                                    </div>

                                    <div class="text-end">
                                        <button type="submit" class="btn btn-danger"><i
                                                class="bx bx-save me-1"></i>Kaydet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="listPersonelKesintiler" class="mt-3">
                    <!-- Kesinti listesi buraya yüklenecek -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Bordro Detay Modal -->
<div class="modal fade" id="bordroDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-detail me-2"></i>Bordro Detayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bordroDetailBody">
                <div id="bordroDetailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="btnPrintBordro">
                    <i class="bx bx-printer me-1"></i> Yazdır / PDF
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<style>
    #bordroTable tbody tr {
        transition: background-color 0.2s ease;
    }

    #bordroTable tbody tr:hover {
        background-color: rgba(85, 110, 230, 0.1);
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        object-fit: cover;
    }

    .dropdown-item.disabled {
        opacity: 0.5;
        pointer-events: none;
    }
</style>
<script src="views/bordro/js/bordro.js?v=<?= time() ?>"></script>