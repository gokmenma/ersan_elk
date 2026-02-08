<?php


use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();




// Seçili yıl ve dönem
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedDonemId = $_GET['donem'] ?? $_SESSION['selectedDonemId'] ?? null;
/**Eğer bir kere dönem seçilmişse onu session'a ata */


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
/**Eğer dönem yoksa seçili id'yi boşalt */
if (!$donemler) {
    $selectedDonemId = null;
}

/**Eğer seçili dönem yoksa null ata */
if (!$selectedDonemId) {
    $selectedDonemId = null;
}

if ($selectedDonemId) {
    $_SESSION['selectedDonemId'] = $selectedDonemId;
}

/**Eğer seçil dönem veritabanında yoksa seçili dönem id session'a ata */
$seciliDonemKontrol = $BordroDonem->find($selectedDonemId);
if (!$seciliDonemKontrol) {
    $selectedDonemId = null;
}

// Eğer dönem seçilmemişse, seçili yıldaki ilk dönemi seç
if ((!$selectedDonemId) && isset($donemlerByYil[$selectedYil]) && !empty($donemlerByYil[$selectedYil])) {
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
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-2">
                            <div style="min-width: 150px;">
                                <?php echo Form::FormSelect2(
                                    name: 'yilSelect',
                                    options: $yil_option,
                                    selectedValue: $selectedYil,
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div style="min-width: 180px;">
                                <?php echo Form::FormSelect2(
                                    name: 'donemSelect',
                                    options: $donem_option,
                                    selectedValue: $selectedDonemId,
                                    label: 'Dönem',
                                    icon: 'calendar',
                                    class: 'form-control select2'
                                ); ?>
                            </div>
                            <?php if ($selectedDonem): ?>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                <div class="form-check form-switch px-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="switchDonemDurum"
                                        <?= $donemKapali ? 'checked' : '' ?>>
                                    <label
                                        class="form-check-label small <?= $donemKapali ? 'text-danger' : 'text-success' ?> fw-bold"
                                        for="switchDonemDurum" style="font-size: 11px;">
                                        <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button" class="btn btn-link btn-sm text-success text-decoration-none px-2"
                                data-bs-toggle="modal" data-bs-target="#yeniDonemModal" title="Yeni Dönem">
                                <i class="mdi mdi-plus-circle fs-5"></i>
                            </button>
                            <?php if ($selectedDonem && !$donemKapali): ?>
                                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                <button type="button" class="btn btn-link btn-sm text-primary text-decoration-none px-2"
                                    id="btnHeaderEditDonem" title="Düzenle">
                                    <i class="mdi mdi-pencil fs-5"></i>
                                </button>
                            <?php endif; ?>
                            <?php if (!$donemKapali) { ?>
                                <div class="vr mx-1" style="height: 20px; align-self: center;"></div>
                                <button type="button" id="donemSil"
                                    class="btn btn-link btn-sm text-danger text-decoration-none px-2" title="Dönemi Sil">
                                    <i class="mdi mdi-trash-can fs-5"></i>
                                </button>
                            <?php } ?>
                        </div>

                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
                            <?php if ($selectedDonem): ?>
                                <button type="button"
                                    class="btn btn-link btn-sm text-primary text-decoration-none px-2 d-flex align-items-center"
                                    id="btnRefreshPersonel" <?= $donemKapali ? 'disabled' : '' ?>>
                                    <i class="mdi mdi-refresh fs-5 me-1"></i> <span class="d-none d-xl-inline">Personel
                                        Güncelle</span>
                                </button>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                                 <div class="dropdown">
                                    <button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="mdi mdi-menu me-1"></i> İşlemler
                                        <i class="mdi mdi-chevron-down"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                        <li>
                                            <a class="dropdown-item py-2" href="javascript:void(0);" id="btnExportExcel">
                                                <i class="mdi mdi-file-excel me-2 text-success fs-5"></i> Excel'e İndir
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2" href="javascript:void(0);"
                                                id="btnExportExcelBanka">
                                                <i class="mdi mdi-bank me-2 text-primary fs-5"></i> Excel'e İndir (Banka)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2" href="javascript:void(0);"
                                                id="btnExportExcelSodexo">
                                                <i class="mdi mdi-food me-2 text-info fs-5"></i> Excel'e İndir (Sodexo)
                                            </a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#gelirEkleModal">
                                                <i class="mdi mdi-plus-box me-2 text-primary fs-5"></i> Gelir Ekle (Excel)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#kesintiEkleModal">
                                                <i class="mdi mdi-minus-box me-2 text-danger fs-5"></i> Kesinti Ekle (Excel)
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#odemeEkleModal">
                                                <i class="mdi mdi-wallet me-2 text-info fs-5"></i> Ödeme Dağıt (Excel)
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="vr mx-1" style="height: 25px; align-self: center;"></div>

                                <button type="button"
                                    class="btn btn-primary btn-sm text-white shadow-primary text-decoration-none px-2 d-flex align-items-center"
                                    id="btnHesapla" <?= $donemKapali ? 'disabled' : '' ?>>
                                    <i class="mdi mdi-calculator fs-5 me-1"></i> <span class="d-none d-xl-inline">Maaş
                                        Hesapla</span>
                                </button>
                               
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($selectedDonem): ?>
                        <?php
                        // Dönem toplamlarını hesapla
                        $toplamMaasTutari = 0;
                        $toplamNetMaas = 0;
                        $toplamBanka = 0;
                        $toplamSodexo = 0;
                        $toplamElden = 0;
                        $toplamEkOdeme = 0;
                        $toplamKesinti = 0;
                        foreach ($personeller as $p) {
                            $toplamMaasTutari += floatval($p->maas_tutari ?? 0);
                            $toplamNetMaas += floatval($p->net_maas ?? 0);
                            $toplamBanka += floatval($p->banka_odemesi ?? 0);
                            $toplamSodexo += floatval($p->sodexo_odemesi ?? 0);
                            $eldenP = $p->elden_odeme ?? (($p->net_maas ?? 0) - ($p->banka_odemesi ?? 0) - ($p->sodexo_odemesi ?? 0) - ($p->diger_odeme ?? 0));
                            $toplamElden += max(0, floatval($eldenP));
                            $toplamEkOdeme += floatval($p->guncel_toplam_ek_odeme ?? 0);
                            $toplamKesinti += floatval($p->guncel_toplam_kesinti ?? 0);
                        }
                        ?>
                        <div class="alert alert-primary d-flex align-items-center mb-3" role="alert">
                            <i class="bx bx-info-circle me-2 fs-4"></i>
                            <div class="d-flex align-items-center flex-wrap w-100">
                                <div class="flex-grow-1">
                                    <strong id="displayDonemAdi"><?= htmlspecialchars($selectedDonem->donem_adi) ?></strong>
                                    <?php if (!$donemKapali): ?>
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1 text-primary"
                                            id="btnEditDonemAdi" title="Dönem Adını Güncelle">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                    dönemine ait
                                    <strong><?= count($personeller) ?></strong> personel listeleniyor.
                                    <span class="ms-2 text-muted">
                                        (<?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?> -
                                        <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Dönem Toplamları Kartları -->
                        <div class="row g-2 mb-4">
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-money fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Maaş Tutarı</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamMaasTutari, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-plus-circle fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Top. Ek Ödeme</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamEkOdeme, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-minus-circle fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Top. Kesinti</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamKesinti, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-wallet fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Net Maaş</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamNetMaas, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bxs-bank fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Banka</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamBanka, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-food-menu fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Sodexo</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamSodexo, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl col-md-4 col-6">
                                <div class="card border shadow-none rounded-3 mb-0">
                                    <div class="card-body text-center p-2">
                                        <div class="avatar-xs mx-auto mb-1 rounded bg-light d-flex align-items-center justify-content-center"
                                            style="width: 32px; height: 32px;">
                                            <i class="bx bx-wallet fs-5 text-dark"></i>
                                        </div>
                                        <p class="text-muted mb-0 small text-uppercase fw-semibold"
                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">Elden</p>
                                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.85rem;">
                                            <?= number_format($toplamElden, 2, ',', '.') ?> ₺
                                        </h6>
                                    </div>
                                </div>
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
                                        <th class="text-end">Maaş Tutarı</th>
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
                                            <td colspan="13" class="text-center text-muted py-4">
                                                <i class="bx bx-user-x fs-1 d-block mb-2"></i>
                                                Bu döneme henüz personel eklenmemiş.<br>
                                                <small>"Personelleri Güncelle" butonuna tıklayarak personelleri
                                                    ekleyebilirsiniz.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($personeller as $personel):
                                            $enc_id = Security::encrypt($personel->personel_id);
                                            ?>
                                            <?php
                                            // Elden ödeme artık model'de hesaplanıp kaydediliyor
                                            // Ancak görüntüleme için yedek hesaplama yap (negatif çıkmaması için max(0,...) eklendi)
                                            $eldenOdeme = $personel->elden_odeme ?? max(0, ($personel->net_maas ?? 0) - ($personel->banka_odemesi ?? 0) - ($personel->sodexo_odemesi ?? 0) - ($personel->diger_odeme ?? 0));

                                            // İzin gün sayılarını hesapla
                                            $ucretsizIzinGunu = 0;
                                            $ucretliIzinGunu = 0;
                                            $calismaGunu = 30;
                                            if (!empty($personel->hesaplama_detay)) {
                                                $detay = json_decode($personel->hesaplama_detay, true);

                                                // Fiili çalışma gününü doğrudan JSON'dan al (varsa)
                                                if (isset($detay['matrahlar']['fiili_calisma_gunu'])) {
                                                    $calismaGunu = intval($detay['matrahlar']['fiili_calisma_gunu']);
                                                }

                                                // Ücretsiz izin günü
                                                if (isset($detay['matrahlar']['ucretsiz_izin_gunu'])) {
                                                    $ucretsizIzinGunu = intval($detay['matrahlar']['ucretsiz_izin_gunu']);
                                                } elseif (isset($detay['matrahlar']['ucretsiz_izin_kesinti']) && isset($detay['matrahlar']['brut_maas']) && $detay['matrahlar']['brut_maas'] > 0) {
                                                    $gunlukUcret = $detay['matrahlar']['brut_maas'] / 30;
                                                    $ucretsizIzinGunu = round($detay['matrahlar']['ucretsiz_izin_kesinti'] / $gunlukUcret);
                                                }

                                                // Ücretli izin günü
                                                if (isset($detay['matrahlar']['ucretli_izin_gunu'])) {
                                                    $ucretliIzinGunu = intval($detay['matrahlar']['ucretli_izin_gunu']);
                                                }

                                                // Fiili çalışma günü yoksa hesapla
                                                if (!isset($detay['matrahlar']['fiili_calisma_gunu'])) {
                                                    $calismaGunu = 30 - $ucretsizIzinGunu - $ucretliIzinGunu;
                                                }
                                            }
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
                                                            <a target="_blank"
                                                                href="index?p=personel/manage&id=<?= $enc_id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></a></span>
                                                    </div>
                                                </td>

                                                <td
                                                    class="text-center <?= ($ucretsizIzinGunu > 0 || $ucretliIzinGunu > 0) ? 'text-warning fw-bold' : 'text-secondary' ?>">
                                                    <?= $calismaGunu ?> gün
                                                    <?php if ($ucretsizIzinGunu > 0): ?>
                                                        <small class="d-block text-danger">(-<?= $ucretsizIzinGunu ?> ü.siz
                                                            izin)</small>
                                                    <?php endif; ?>
                                                    <?php if ($ucretliIzinGunu > 0): ?>
                                                        <small class="d-block text-info">(-<?= $ucretliIzinGunu ?> ü.li izin)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end text-dark fw-medium">
                                                    <?= $personel->maas_tutari ? number_format($personel->maas_tutari, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td class="text-end text-success">
                                                    <?php
                                                    // Hesaplanmış ek ödeme toplamını al (gün bazlı hesaplamalar dahil)
                                                    $hesaplananEkOdeme = $personel->guncel_toplam_ek_odeme;
                                                    if (!empty($personel->hesaplama_detay)) {
                                                        $detayEkOdeme = json_decode($personel->hesaplama_detay, true);
                                                        if (isset($detayEkOdeme['ek_odemeler']) && is_array($detayEkOdeme['ek_odemeler'])) {
                                                            $hesaplananEkOdeme = 0;
                                                            foreach ($detayEkOdeme['ek_odemeler'] as $eo) {
                                                                $hesaplananEkOdeme += floatval($eo['net_etki'] ?? $eo['tutar'] ?? 0);
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                    <?= $hesaplananEkOdeme > 0 ? number_format($hesaplananEkOdeme, 2, ',', '.') . ' ₺' : '-' ?>
                                                    <i class="mdi mdi-format-list-bulleted ms-1 text-primary cursor-pointer btn-detail-ekodeme"
                                                        data-id="<?= $personel->personel_id ?>"
                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                        title="Detayları Gör"></i>
                                                </td>
                                                <td class="text-end text-danger">
                                                    <?= $personel->guncel_toplam_kesinti > 0 ? number_format($personel->guncel_toplam_kesinti, 2, ',', '.') . ' ₺' : '-' ?>
                                                    <i class="mdi mdi-format-list-bulleted ms-1 text-danger cursor-pointer btn-detail-kesinti"
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
                                                            data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                                            aria-expanded="false">
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
                                                                    <i class="mdi mdi-wallet-outline me-2 text-primary"></i> Ödeme
                                                                    Dağıt
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-detail" href="javascript:void(0);"
                                                                    data-id="<?= $personel->id ?>">
                                                                    <i class="mdi mdi-information-outline me-2 text-info"></i> Detay
                                                                </a>
                                                            </li>
                                                            <li>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-gelir-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);"
                                                                    data-id="<?= $personel->personel_id ?>"
                                                                    data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                    <i class="mdi mdi-plus-circle-outline me-2 text-success"></i>
                                                                    Gelir Ekle
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-kesinti-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);"
                                                                    data-id="<?= $personel->personel_id ?>"
                                                                    data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                    <i class="mdi mdi-minus-circle-outline me-2 text-danger"></i>
                                                                    Kesinti Ekle
                                                                </a>
                                                            </li>
                                                            <li>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item btn-remove text-danger<?= $donemKapali ? ' disabled' : '' ?>"
                                                                    href="javascript:void(0);" data-id="<?= $personel->id ?>">
                                                                    <i class="mdi mdi-trash-can-outline me-2"></i> Dönemden Çıkar
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
                            <button type="button" class="btn btn-primary px-4 fw-bold shadow-primary" data-bs-toggle="modal"
                                data-bs-target="#yeniDonemModal">
                                <i class="mdi mdi-plus-circle me-1"></i> İlk Dönemi Oluştur
                            </button>
                        </div>
                    <?php endif; ?>
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

                            <?php
                            echo Form::FormFloatInput(
                                type: 'text',
                                name: "donem_adi",
                                value: '',
                                placeholder: "Örn: Ocak 2026",
                                label: "Dönem Adı",
                                icon: 'calendar',
                                required: true
                            )
                                ?>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">


                                <?php
                                echo Form::FormFloatInput(
                                    type: 'text',
                                    name: "baslangic_tarihi",
                                    value: '',
                                    placeholder: "Başlangıç Tarihi",
                                    label: "Başlangıç Tarihi",
                                    icon: 'calendar',
                                    class: 'form-control flatpickr',
                                    required: true,
                                    attributes: 'autocomplete="off"'
                                )
                                    ?>

                            </div>
                            <div class="col-md-6 mb-3">

                                <?php
                                echo Form::FormFloatInput(
                                    type: 'text',
                                    name: "bitis_tarihi",
                                    value: '',
                                    placeholder: "Bitiş Tarihi",
                                    label: "Bitiş Tarihi",
                                    icon: 'calendar',
                                    class: 'form-control flatpickr',
                                    required: true,
                                    attributes: 'autocomplete="off"'
                                )
                                    ?>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-1"></i>
                            <small>Dönem oluşturulduğunda, belirlenen tarih aralığında çalışan personeller otomatik
                                olarak
                                döneme eklenecektir.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success"><i class="bx bx-check me-1"></i>Dönem
                            Oluştur</button>
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
                                <input type="number" class="form-control" id="diger_odeme" name="diger_odeme"
                                    step="0.01" min="0" value="0">
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
                        <div
                            class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 mb-3">
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
                                        Mevcut personeller ve net maaş dağılımları için hazırlanan Excel şablonunu
                                        indirin.
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
                            <?php if (!$donemKapali) { ?>

                                <h2 class="accordion-header" id="headingGelir">
                                    <button class="accordion-button collapsed fw-medium" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseGelir" aria-expanded="false"
                                        aria-controls="collapseGelir">
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
                            <?php } ?>
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
                            <?php if (!$donemKapali) { ?>
                                <h2 class="accordion-header" id="headingKesinti">
                                    <button class="accordion-button collapsed fw-medium" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseKesinti" aria-expanded="false"
                                        aria-controls="collapseKesinti">
                                        <i class="bx bx-minus me-2 text-danger"></i> Yeni Kesinti Ekle
                                    </button>
                                </h2>
                                <div id="collapseKesinti" class="accordion-collapse collapse"
                                    aria-labelledby="headingKesinti" data-bs-parent="#accordionKesintiEkle">
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
                            <?php } ?>
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
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bx bx-show me-2"></i>Bordro Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bordroDetailContent">
                    <!-- İçerik AJAX ile yüklenecek -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dönem Güncelle Modal -->
    <div class="modal fade" id="modalDonemGuncelle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bx bx-edit me-2"></i>Dönem Adını Güncelle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formDonemGuncelle">
                    <input type="hidden" name="donem_id" value="<?= $selectedDonemId ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <?php
                            echo Form::FormFloatInput(
                                type: 'text',
                                name: "donem_adi",
                                value: $selectedDonem ? $selectedDonem->donem_adi : '',
                                placeholder: "Örn: Ocak 2026",
                                label: "Dönem Adı",
                                icon: 'calendar',
                                required: true,
                                attributes: 'id="edit_donem_adi"'
                            )
                                ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="views/bordro/js/bordro.js?v=<?= time() ?>"></script>