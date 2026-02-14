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
                                    <button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false">
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
                        $toplamAlacagi = 0;
                        $toplamIcra = 0;
                        $toplamBanka = 0;
                        $toplamSodexo = 0;
                        $toplamElden = 0;

                        foreach ($personeller as $p) {
                            // Prim Usülü personellerde ek ödeme/prim maaş tutarı olarak gösteriliyor
                            $hesaplananEkOdeme = $p->guncel_toplam_ek_odeme;
                            if (!empty($p->hesaplama_detay)) {
                                $detayEkOdeme = json_decode($p->hesaplama_detay, true);
                                if (isset($detayEkOdeme['ek_odemeler']) && is_array($detayEkOdeme['ek_odemeler'])) {
                                    $hesaplananEkOdeme = 0;
                                    foreach ($detayEkOdeme['ek_odemeler'] as $eo) {
                                        $hesaplananEkOdeme += floatval($eo['net_etki'] ?? $eo['tutar'] ?? 0);
                                    }
                                }
                            }

                            $isPrimUsulu = ($p->maas_durumu ?? '') == 'Prim Usülü';
                            $displayMaas = $isPrimUsulu ? $hesaplananEkOdeme : $p->maas_tutari;

                            $toplamAlacagi += $displayMaas;
                            $toplamBanka += floatval($p->banka_odemesi ?? 0);
                            $toplamSodexo += floatval($p->sodexo_odemesi ?? 0);

                            // Elden hesaplama
                            $eldenP = $p->elden_odeme ?? (($p->net_maas ?? 0) - ($p->banka_odemesi ?? 0) - ($p->sodexo_odemesi ?? 0) - ($p->diger_odeme ?? 0));
                            $toplamElden += max(0, floatval($eldenP));

                            // İcra hesaplama
                            if (!empty($p->hesaplama_detay)) {
                                $detay = json_decode($p->hesaplama_detay, true);
                                $toplamIcra += $detay['odeme_dagilimi']['icra_kesintisi'] ?? 0;
                            }
                        }

                        // En son hesaplama tarihini bul
                        $latestCalculation = null;
                        foreach ($personeller as $p) {
                            if ($p->hesaplama_tarihi) {
                                if (!$latestCalculation || $p->hesaplama_tarihi > $latestCalculation) {
                                    $latestCalculation = $p->hesaplama_tarihi;
                                }
                            }
                        }
                        ?>


                        <!-- Üst Bilgi Çubuğu (Dashboard Stili) -->
                        <div class="card border-0 shadow-sm mb-4 bordro-info-bar"
                            style="border-radius: 20px; background: rgba(231, 111, 81, 0.03); border: 1px solid rgba(231, 111, 81, 0.1) !important;">
                            <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="bg-white rounded-3 shadow-sm p-2 me-3 d-flex align-items-center justify-content-center"
                                        style="width: 45px; height: 45px;">
                                        <i class="bx bx-calendar-event fs-3" style="color: #E76F51;"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <h5 class="mb-0 fw-bold bordro-text-heading" id="displayDonemAdi">
                                                <?= htmlspecialchars($selectedDonem->donem_adi) ?>
                                            </h5>
                                            <?php if (!$donemKapali): ?>
                                                <button type="button" class="btn btn-sm btn-link p-0 ms-2 text-muted"
                                                    id="btnEditDonemAdi" title="Dönem Adını Güncelle">
                                                    <i class="bx bx-edit-alt fs-6"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted fw-medium">
                                            <i class="bx bx-time-five me-1"></i>
                                            <?= date('d.m.Y', strtotime($selectedDonem->baslangic_tarihi)) ?> -
                                            <?= date('d.m.Y', strtotime($selectedDonem->bitis_tarihi)) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="d-none d-md-flex align-items-center gap-3">
                                    <div class="text-end me-2">
                                        <p class="text-muted mb-0 small fw-bold">TOPLAM PERSONEL</p>
                                        <h5 class="mb-0 fw-bold bordro-text-heading"><?= count($personeller) ?> <span
                                                class="small text-muted fw-normal">Kişi</span></h5>
                                    </div>
                                    <div class="vr text-muted opacity-25" style="height: 35px;"></div>
                                    <div class="d-flex align-items-start gap-2">
                                        <span
                                            class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                            style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                            <span class="rounded-circle me-2"
                                                style="width: 8px; height: 8px; background: <?= $donemKapali ? '#f43f5e' : '#10b981' ?>;"></span>
                                            <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                        </span>
                                        <?php if ($latestCalculation): ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span
                                                    class="badge shadow-sm border rounded-pill px-3 py-2 fw-bold d-flex align-items-center"
                                                    style="background: var(--bs-card-bg); color: var(--bs-body-color) !important;">
                                                    <span class="rounded-circle me-2"
                                                        style="width: 8px; height: 8px; background: #10b981;"></span>
                                                    HESAPLANDI
                                                </span>
                                                <div class="text-muted mt-1"
                                                    style="font-size: 9px; font-weight: 600; opacity: 0.8;">
                                                    <i
                                                        class="bx bx-check-double me-1"></i><?= date('d.m.Y H:i', strtotime($latestCalculation)) ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dönem Toplamları Kartları (Dashboard Stili) -->
                        <div class="row g-3 mb-4">
                            <!-- Toplam Alacağı -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #E76F51; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(231, 111, 81, 0.1);">
                                                <i class="bx bx-receipt fs-4" style="color: #E76F51;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">HAKEDİŞ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">TOPLAM ALACAĞI</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamAlacagi, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- İcra Kesintisi -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f43f5e; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(244, 63, 94, 0.1);">
                                                <i class="bx bx-minus-circle fs-4 text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-bold"
                                                style="font-size: 0.65rem;">KESİNTİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">İCRA KESİNTİSİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamIcra, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Banka -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #0ea5e9; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(14, 165, 233, 0.1);">
                                                <i class="bx bxs-bank fs-4 text-info"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">RESMİ</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">BANKA ÖDEMESİ</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <?= number_format($toplamBanka, 2, ',', '.') ?> <span
                                                style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Sodexo -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #8b5cf6; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(139, 92, 246, 0.1);">
                                                <i class="bx bx-food-menu fs-4" style="color: #8b5cf6;"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">YEMEK</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">SODEXO</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-sodexo"><?= number_format($toplamSodexo, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Elden -->
                            <div class="col-xl col-md-4">
                                <div class="card border-0 shadow-sm h-100 bordro-summary-card"
                                    style="--card-color: #f59e0b; border-bottom: 3px solid var(--card-color) !important;">
                                    <div class="card-body p-3">
                                        <div class="icon-label-container">
                                            <div class="icon-box" style="background: rgba(245, 158, 11, 0.1);">
                                                <i class="bx bx-wallet-alt fs-4 text-warning"></i>
                                            </div>
                                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">NAKİT</span>
                                        </div>
                                        <p class="text-muted mb-1 small fw-bold"
                                            style="letter-spacing: 0.5px; opacity: 0.7;">ELDEN ÖDEME</p>
                                        <h4 class="mb-0 fw-bold bordro-text-heading">
                                            <span id="total-elden"><?= number_format($toplamElden, 2, ',', '.') ?></span>
                                            <span style="font-size: 0.85rem; font-weight: 600;">₺</span>
                                        </h4>
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
                                        <th class="text-center" style="width: 80px;">Birim</th>
                                        <th style="min-width: 150px;">Ekip / Bölge</th>
                                        <th>Personel</th>
                                        <th class="text-center">Maaş Tipi</th>
                                        <th class="text-center">Gün</th>
                                        <th class="text-end">Toplam Alacağı</th>
                                        <th class="text-end">İcra Kesintisi</th>
                                        <th class="text-end">Banka</th>
                                        <th class="text-end">Sodexo</th>
                                        <th class="text-end">Elden</th>
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

                                            $isPrimUsulu = ($personel->maas_durumu ?? '') == 'Prim Usülü';
                                            $displayMaas = ($personel->net_maas > 0) ? $personel->net_maas : ($isPrimUsulu ? $hesaplananEkOdeme : $personel->maas_tutari);
                                            $displayEkOdeme = $isPrimUsulu ? 0 : $hesaplananEkOdeme;

                                            // İcra kesintisini al
                                            $icraKesintisi = 0;
                                            if (!empty($personel->hesaplama_detay)) {
                                                $detay = json_decode($personel->hesaplama_detay, true);
                                                $icraKesintisi = $detay['odeme_dagilimi']['icra_kesintisi'] ?? 0;
                                            }
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

                                                // Fiili çalışma gununu doğrudan JSON'dan al (varsa)
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
                                                <td class="text-center">
                                                    <?php
                                                    $deptName = $personel->departman ?? '-';
                                                    $deptUp = mb_convert_case($deptName, MB_CASE_UPPER, "UTF-8");
                                                    $dInfo = ['code' => '??', 'color' => '#6c757d'];

                                                    if (strpos($deptUp, 'OKUMA') !== false)
                                                        $dInfo = ['code' => 'EO', 'color' => '#0ea5e9'];
                                                    elseif (strpos($deptUp, 'KESME') !== false)
                                                        $dInfo = ['code' => 'KA', 'color' => '#f43f5e'];
                                                    elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false)
                                                        $dInfo = ['code' => 'ST', 'color' => '#10b981'];
                                                    elseif (strpos($deptUp, 'KAÇAK') !== false)
                                                        $dInfo = ['code' => 'KÇ', 'color' => '#8b5cf6'];
                                                    else {
                                                        $words = explode(' ', $deptUp);
                                                        if (count($words) >= 2) {
                                                            $dInfo['code'] = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
                                                        } else {
                                                            $dInfo['code'] = mb_substr($deptUp, 0, 2);
                                                        }
                                                    }
                                                    ?>
                                                    <div class="dept-badge" style="--dept-color: <?= $dInfo['color'] ?>;"
                                                        data-bs-toggle="tooltip" title="<?= htmlspecialchars($deptName) ?>">
                                                        <?= $dInfo['code'] ?>
                                                    </div>
                                                    <span class="d-none"><?= $dInfo['code'] ?>
                                                        <?= htmlspecialchars($deptName) ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    if (!empty($personel->ekip_adi) && $personel->ekip_adi !== "YOK") {
                                                        $badgeColors = [
                                                            "bg-primary-subtle text-primary border-primary-subtle",
                                                            "bg-success-subtle text-success border-success-subtle",
                                                            "bg-info-subtle text-info border-info-subtle",
                                                            "bg-warning-subtle text-warning border-warning-subtle",
                                                            "bg-danger-subtle text-danger border-danger-subtle",
                                                            "bg-secondary-subtle text-secondary border-secondary-subtle",
                                                            "bg-dark-subtle text-dark border-dark-subtle",
                                                        ];

                                                        $ekipler = explode(',', $personel->ekip_adi);
                                                        echo '<div class="d-flex flex-wrap">';
                                                        foreach ($ekipler as $ekip) {
                                                            $cleanEkip = trim($ekip);
                                                            $cleanEkip = preg_replace('/ER-SAN ELEKTRİK|ERSAN ELEKTRİK|ER SAN ELEKTRİK/i', '', $cleanEkip);
                                                            $cleanEkip = trim($cleanEkip);

                                                            if (empty($cleanEkip))
                                                                continue;

                                                            // Hash function for color consistency
                                                            $hash = 0;
                                                            for ($i = 0; $i < strlen($cleanEkip); $i++) {
                                                                $hash = ord($cleanEkip[$i]) + (($hash << 5) - $hash);
                                                            }
                                                            $colorClass = $badgeColors[abs($hash) % count($badgeColors)];

                                                            echo '<span class="badge ' . $colorClass . ' font-size-12 px-2 py-1 mb-1 me-1 border">' . htmlspecialchars($cleanEkip) . '</span>';
                                                        }
                                                        echo '</div>';

                                                        if (!empty($personel->ekip_bolge) && $personel->ekip_bolge !== "---") {
                                                            echo '<div class="text-muted small mt-1"><i class="bx bx-map-pin"></i> ' . htmlspecialchars($personel->ekip_bolge) . '</div>';
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                            alt="" class="rounded-circle avatar-sm me-2">
                                                        <div>
                                                            <div class="fw-medium">
                                                                <a target="_blank"
                                                                    href="index?p=personel/manage&id=<?= $enc_id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></a>
                                                            </div>
                                                            <small class="text-muted"
                                                                style="font-size: 10px; letter-spacing: 0.5px;">TC:
                                                                <?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center" style="font-size: 12px;">
                                                    <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                                                        <?= htmlspecialchars($personel->maas_durumu ?? '-') ?>
                                                    </span>
                                                </td>
                                                <td class="text-center fw-bold text-secondary">
                                                    <?= $calismaGunu ?>
                                                </td>

                                                <td class="text-end text-dark fw-bold">
                                                    <span class="cursor-pointer btn-detail text-primary"
                                                        data-id="<?= $personel->id ?>" title="Bordro Detayını Gör">
                                                        <?= $displayMaas > 0 ? number_format($displayMaas, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </span>
                                                </td>
                                                <td class="text-end text-danger fw-medium">
                                                    <?php if ($icraKesintisi > 0): ?>
                                                        <span class="btn-icra-detail cursor-pointer text-decoration-underline"
                                                            data-id="<?= $personel->id ?>" title="İcra Detaylarını Gör">
                                                            <?= number_format($icraKesintisi, 2, ',', '.') . ' ₺' ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end text-primary">
                                                    <?= $personel->banka_odemesi ? number_format($personel->banka_odemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                </td>
                                                <td class="text-end text-info td-sodexo" style="width: 150px;">
                                                    <div class="sodexo-wrapper d-flex align-items-center justify-content-end gap-2">
                                                        <span class="sodexo-value fw-bold">
                                                            <?= $personel->sodexo_odemesi > 0 ? number_format($personel->sodexo_odemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                        </span>
                                                        <input type="text"
                                                            class="form-control form-control-sm text-end update-sodexo money d-none"
                                                            style="width: 100px;" data-id="<?= $personel->id ?>"
                                                            data-net="<?= number_format($personel->net_maas ?? 0, 2, '.', '') ?>"
                                                            data-banka="<?= number_format($personel->banka_odemesi ?? 0, 2, '.', '') ?>"
                                                            data-diger="<?= number_format($personel->diger_odeme ?? 0, 2, '.', '') ?>"
                                                            data-icra="<?= number_format($icraKesintisi, 2, '.', '') ?>"
                                                            data-current-val="<?= $personel->sodexo_odemesi ?? 0 ?>"
                                                            value="<?= Helper::formattedMoney($personel->sodexo_odemesi ?? 0) ?>">
                                                        <a href="javascript:void(0);" class="btn-edit-sodexo-inline text-muted"
                                                            title="Düzenle">
                                                            <i data-feather="edit-3" style="width: 14px; height: 14px;"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-end text-warning fw-bold td-elden">
                                                    <?= $eldenOdeme > 0 ? number_format($eldenOdeme, 2, ',', '.') . ' ₺' : '-' ?>
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
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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

    <!-- İcra Detay Modal -->
    <div class="modal fade" id="modalIcraDetay" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bx bx-file me-2"></i>İcra Kesintisi Detayları</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-0 mb-3">
                        <i class="bx bx-user me-1"></i> <strong id="icra_detay_personel_ad"></strong>
                    </div>
                    <div id="icra_detay_content">
                        <!-- İçerik AJAX ile yüklenecek -->
                    </div>
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