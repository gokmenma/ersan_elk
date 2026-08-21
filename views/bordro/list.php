<?php
$_pageStart = microtime(true);

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\BordroParametreModel;
use App\Helper\Form;
use App\Helper\Helper;
use App\Helper\Security;

$BordroDonem = new BordroDonemModel();
$BordroPersonel = new BordroPersonelModel();
$BordroParametre = new BordroParametreModel();




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

/**Eğer seçil dönem veritabanında yoksa seçili dönem id sessionı sıfırla */
$seciliDonemKontrol = $BordroDonem->getDonemById($selectedDonemId);
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
    // Dönem zaten yukarıda çekildi; sadece ilk dönem seçimi durumunda tekrar çek
    $selectedDonem = ($seciliDonemKontrol && $seciliDonemKontrol->id == $selectedDonemId)
        ? $seciliDonemKontrol
        : $BordroDonem->getDonemById($selectedDonemId);
    if ($selectedDonem) {
        $_sqlStart = microtime(true);
        $personeller = $BordroPersonel->getPersonellerByDonem($selectedDonemId);
        $_sqlTime = round((microtime(true) - $_sqlStart) * 1000);
        $selectedAy = date('m', strtotime($selectedDonem->baslangic_tarihi));
        $selectedYil = date('Y', strtotime($selectedDonem->baslangic_tarihi));
    }
}

if (!isset($selectedAy)) {
    $selectedAy = date('m');
}
if (!isset($selectedYil)) {
    $selectedYil = date('Y');
}
// Dönem kapalı mı kontrolü
$donemKapali = $selectedDonem ? ($selectedDonem->kapali_mi ?? 0) : 0;

$paramTarih = $selectedDonem ? $selectedDonem->baslangic_tarihi : date('Y-m-d');

$kesinti_turleri = ['' => 'Seçiniz'];
$dbKesintiler = $BordroParametre->getKesintiTurleri($paramTarih);
if (!empty($dbKesintiler)) {
    foreach ($dbKesintiler as $k) {
        $kesinti_turleri[$k->kod] = $k->etiket;
    }
} else {
    // Veritabanında henüz tanımlı değilse varsayılan liste
    $kesinti_turleri = [
        '' => "Seçiniz",
        'icra' => 'İcra',
        'avans' => 'Avans',
        'nafaka' => 'Nafaka',
        'izin_kesinti' => 'Ücretsiz İzin',
        'diger' => 'Diğer'
    ];
}

$ek_odeme_turleri = ['' => 'Seçiniz'];
$dbGelirler = $BordroParametre->getGelirTurleri($paramTarih);
if (!empty($dbGelirler)) {
    foreach ($dbGelirler as $g) {
        $ek_odeme_turleri[$g->kod] = $g->etiket;
    }
} else {
    // Veritabanında henüz tanımlı değilse varsayılan liste
    $ek_odeme_turleri = [
        '' => "Seçiniz",
        'prim' => 'Prim',
        'mesai' => 'Fazla Mesai',
        'ikramiye' => 'İkramiye',
        'yol' => 'Yol Yardımı',
        'yemek' => 'Yemek Yardımı',
        'diger' => 'Diğer'
    ];
}
?>

<div class="container-fluid">
    <?php
    $maintitle = "Bordro";
    $title = "Bordro Yönetimi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <style>
        /* Hover Popover Styles */
        .hover-popover-trigger { cursor: help; position: relative; }
        .ref-popover-content {
            display: none;
            position: absolute;
            bottom: 125%;
            right: 0;
            background: #1e293b;
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2), 0 4px 6px -2px rgba(0,0,0,0.1);
            min-width: 280px;
            max-width: 350px;
            z-index: 1000;
            font-size: 0.75rem;
            text-align: left;
            border: 1px solid #334155;
            font-weight: normal;
        }
        .ref-popover-content::after {
            content: "";
            position: absolute;
            top: 100%;
            right: 15px;
            border-width: 6px;
            border-style: solid;
            border-color: #1e293b transparent transparent transparent;
        }
        .hover-popover-trigger:hover .ref-popover-content {
            display: block;
        }

        .transition-icon {
            transition: transform 0.2s ease;
        }

        [aria-expanded="true"] .transition-icon {
            transform: rotate(180deg);
        }

        .fs-xs {
            font-size: 0.75rem;
        }

        /* Bordro Preloader */
        .bordro-preloader {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.82);
            z-index: 1060;
            border-radius: 4px;
            backdrop-filter: blur(3px);
        }

        [data-bs-theme="dark"] .bordro-preloader {
            background: rgba(25, 30, 34, 0.85);
        }

        .bordro-preloader .loader-content {
            position: absolute;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            text-align: center;
            min-width: 250px;
        }

        [data-bs-theme="dark"] .bordro-preloader .loader-content {
            background: #2a3042;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }

        /* Tablo ilk yüklemede gizli, DataTables hazır olunca görünür */
        #bordroTable:not(.dt-ready) tbody {
            visibility: hidden;
            opacity: 0;
            height: 0;
            overflow: hidden;
        }

        .personel-img-zoom-container {
            position: relative;
            display: inline-block;
        }

        .img-preview-tooltip {
            position: absolute;
            z-index: 1070;
            display: none;
            background: #fff;
            padding: 5px;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            pointer-events: none;
            border: 1px solid #ddd;
            width: 200px;
            height: 200px;
            overflow: hidden;
            top: -210px;
            left: 50%;
            transform: translateX(-50%);
        }

        .img-preview-tooltip img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .dropdown-menu .show {
            z-index: 1060;
        }

        /* Modern Checkbox Tasarımı */
        .form-check .form-check-input {
            width: 1.35rem;
            height: 1.35rem;
            margin-top: 0;
            border: 2px solid #94a3b8;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            box-shadow: none;
            background-color: #fff;
            border-radius: 6px;
        }

        .form-check .form-check-input:hover {
            border-color: #3b82f6;
            transform: scale(1.1);
        }

        .form-check .form-check-input:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .form-check .form-check-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* Tablo içindeki checkbox hücrelerini ortala */
        #bordroTable th:first-child, 
        #bordroTable td:first-child {
            text-align: center !important;
            vertical-align: middle !important;
            padding: 0 !important;
            width: 50px !important;
            min-width: 50px !important;
            max-width: 50px !important;
        }

        #bordroTable .form-check {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
            width: 100%;
            height: 100%;
        }

        #bordroTable .form-check-input {
            margin: 0 !important;
            float: none !important;
            position: relative !important;
            left: 0 !important;
        }

        /* Seçili satır rengi */
        #bordroTable tr.selected-row {
            background-color: rgba(59, 130, 246, 0.05) !important;
        }

        #modalIzinTakvim .modal-dialog {
            max-width: 780px;
        }

        #modalIzinTakvim .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.12);
        }

        #modalIzinTakvim .modal-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
            color: #0f172a !important;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem;
        }

        #modalIzinTakvim .btn-close {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 50%;
            padding: 0.4rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        #modalIzinTakvim .btn-close:hover {
            background-color: #f1f5f9;
            transform: rotate(90deg);
        }

        #modalIzinTakvim .modal-body {
            background: #ffffff !important;
            padding: 1rem 1.25rem;
        }

        #modalIzinTakvim .modal-footer {
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            padding: 0.85rem 1.25rem;
        }

        /* Premium Summary Cards */
        .premium-summary-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }
        .premium-summary-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.05);
            border-color: #cbd5e1;
        }
        .premium-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }
        .premium-summary-card.card-total::before {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }
        .premium-summary-card.card-ucretli::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }
        .premium-summary-card.card-ucretsiz::before {
            background: linear-gradient(90deg, #f43f5e, #fb7185);
        }
        .premium-summary-card .icon-box {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .year-calendar-month {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02) !important;
            background: #ffffff;
        }

        .year-calendar-header {
            text-align: center;
            font-weight: 700;
            letter-spacing: 0.02em;
            font-size: 0.95rem !important;
            background: linear-gradient(to right, #f8fafc, #ffffff) !important;
            color: #1e293b;
            margin-bottom: 0 !important;
            padding: 0.6rem 1rem !important;
            border-bottom: 1px solid #f1f5f9;
        }

        .year-calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px;
            table-layout: fixed;
        }

        .year-calendar-table th {
            text-align: center;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.3rem 0;
            background: transparent !important;
            border: 0 !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .year-calendar-table td {
            height: 72px;
            vertical-align: top;
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 0.35rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .year-calendar-table td:hover {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .year-calendar-table td.is-filled {
            border: 1px solid transparent !important;
        }

        .year-calendar-table td.is-filled:hover {
            filter: brightness(0.96);
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }

        .year-calendar-table td.today {
            background-color: #eff6ff !important;
            border: 2px solid #3b82f6 !important;
        }

        .year-calendar-table td.passive-date {
            opacity: 0.3;
            background: #f1f5f9 !important;
            pointer-events: none;
        }

        .year-calendar-day {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            position: relative;
        }

        .year-calendar-day-number {
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1;
            color: #475569;
            align-self: flex-start;
            margin-bottom: 2px;
        }

        .year-calendar-day-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 17px;
            border-radius: 4px;
            font-size: 0.62rem;
            font-weight: 800;
            padding: 0.05rem 0.35rem;
            width: auto;
            max-width: 100%;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .year-calendar-day-desc {
            font-size: 0.58rem;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            margin-top: 2px;
            color: inherit;
            opacity: 0.9;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            width: 100%;
            word-break: break-word;
        }

        .takvim-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin-bottom: 1.1rem;
            justify-content: flex-start;
        }

        .takvim-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.42rem 0.75rem;
            font-size: 0.76rem;
            font-weight: 600;
            color: #334155;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .takvim-legend-item:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .takvim-legend-swatch {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 0;
            flex: 0 0 10px;
        }
    </style>


    <div class="row">
        <div class="col-12">
            <div class="card bordro-card">
                <div class="card-header bordro-sticky-header">
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
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <div class="dropdown">
                                <button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="mdi mdi-menu me-1"></i> İşlemler
                                    <i class="mdi mdi-chevron-down"></i>
                                </button>
                                <ul class="dropdown-menu shadow-lg border-0">
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="javascript:void(0);"
                                            data-bs-toggle="modal" data-bs-target="#yeniDonemModal">
                                            <i class="mdi mdi-plus-circle text-success fs-5 me-2"></i> Yeni Dönem Ekle
                                        </a>
                                    </li>
                                    <?php if ($selectedDonem): ?>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center justify-content-between" href="javascript:void(0);"
                                            id="btnDonemDurumToggle" data-status="<?= $donemKapali ? 'kapali' : 'acik' ?>">
                                            <span>
                                                <i class="mdi <?= $donemKapali ? 'mdi-lock-open text-success' : 'mdi-lock text-warning' ?> fs-5 me-2"></i>
                                                <?= $donemKapali ? 'Dönemi Aç' : 'Dönemi Kapat' ?>
                                            </span>
                                            <span class="badge <?= $donemKapali ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> ms-3" style="font-size: 10px;">
                                                <?= $donemKapali ? 'KAPALI' : 'AÇIK' ?>
                                            </span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center justify-content-between" href="javascript:void(0);"
                                            id="btnPersonelGorsunToggle" data-gorsun="<?= ($selectedDonem->personel_gorsun == 1) ? '1' : '0' ?>">
                                            <span>
                                                <i class="mdi <?= ($selectedDonem->personel_gorsun == 1) ? 'mdi-eye text-info' : 'mdi-eye-off text-secondary' ?> fs-5 me-2"></i>
                                                Personel Bordroyu Görsün
                                            </span>
                                            <span class="badge <?= ($selectedDonem->personel_gorsun == 1) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?> ms-3" style="font-size: 10px;">
                                                <?= ($selectedDonem->personel_gorsun == 1) ? 'GÖRÜYOR' : 'GÖRMÜYOR' ?>
                                            </span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center" href="javascript:void(0);"
                                            id="btnFixMatrah2026">
                                            <i class="mdi mdi-calculator-variant text-warning fs-5 me-2"></i> Kümülatif Vergi Matrahlarını Düzelt
                                        </a>
                                    </li>
                                    <?php if ($selectedDonem): ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center <?= $donemKapali ? 'disabled' : '' ?>"
                                            href="javascript:void(0);" id="donemSil">
                                            <i class="mdi mdi-trash-can text-danger fs-5 me-2"></i> Dönemi Sil
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
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
                                            <a class="dropdown-item py-2" href="javascript:void(0);"
                                                id="btnExportExcelYemek">
                                                <i class="mdi mdi-file-excel me-2 text-success fs-5"></i> Excel'e İndir (Muhasebe)
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
                                                href="javascript:void(0);" id="btnBulkOdemeReset">
                                                <i class="mdi mdi-refresh me-2 text-warning fs-5"></i> Tüm Ödeme Dağıtımlarını Sıfırla
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2 <?= $donemKapali ? 'disabled' : '' ?>"
                                                href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#odemeEkleModal">
                                                <i class="mdi mdi-cash-multiple me-2 text-success fs-5"></i> Ödeme Dağıt (Excel)
                                            </a>
                                        </li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li>
                                            <a class="dropdown-item py-2" href="javascript:void(0);" id="btnHataliIslemler">
                                                <i class="mdi mdi-alert-circle me-2 text-warning fs-5"></i> Hatalı İşlemler Sayıları
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
                        <?php include __DIR__ . '/partials/hesaplama.php'; ?>


                        <div id="bordroOzetAlani">
                            <?php include __DIR__ . '/partials/ozet-kartlar.php'; ?>
                        </div>

                        <div class="position-relative">
                            <!-- Preloader -->
                            <div class="bordro-preloader" id="bordro-loader">
                                <div class="loader-content">
                                    <div class="spinner-border text-primary m-1" role="status">
                                        <span class="sr-only">Yükleniyor...</span>
                                    </div>
                                    <h5 class="mt-2 mb-0">Tablo Hazırlanıyor...</h5>
                                    <p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table id="bordroTable" class="table table-hover table-bordered nowrap w-100">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 40px;">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                                </div>
                                            </th>
                                            <th style="width: 40px;">#</th>
                                            <th class="text-center" style="width: 80px;" data-filter="select">Birim</th>
                                            <th style="min-width: 150px;" data-filter="select">Ekip / Bölge</th>
                                            <th data-filter="string">Personel</th>
                                            <th class="text-center" data-filter="select">Maaş Tipi</th>
                                            <th class="text-center" data-filter="number">Gün</th>
                                            <th class="text-end" data-filter="number">Toplam Alacağı</th>
                                            <th class="text-end" data-filter="number">Kesinti Tutarı</th>
                                            <th class="text-end" data-filter="number">Net Maaş</th>
                                            <th class="text-end" data-filter="number">İcra Kesintisi</th>
                                            <th class="text-end" data-filter="number">SGK/Vergi Kesintisi</th>
                                            <th class="text-end" data-filter="number">Banka</th>
                                            <th class="text-end" data-filter="number">Sodexo</th>
                                            <th class="text-end" data-filter="number">Elden</th>
                                            <th class="text-center">İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php include __DIR__ . '/partials/tablo-satirlari.php'; ?>
                                    </tbody>
                                </table>
                            </div>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <?php
                                echo Form::FormSelect2(
                                    name: 'donem_ay',
                                    options: \App\Helper\Date::MONTHS,
                                    selectedValue: date('n'),
                                    label: 'Ay',
                                    icon: 'calendar',
                                    class: 'form-control select2',
                                    attributes: 'id="donem_ay"'
                                );
                                ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <?php
                                $years = [];
                                $currentYear = date('Y');
                                for ($i = $currentYear - 1; $i <= $currentYear + 5; $i++) {
                                    $years[$i] = $i;
                                }
                                echo Form::FormSelect2(
                                    name: 'donem_yil',
                                    options: $years,
                                    selectedValue: $currentYear,
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2',
                                    attributes: 'id="donem_yil"'
                                );
                                ?>
                            </div>
                        </div>
                        <input type="hidden" name="donem_adi" id="donem_adi_hidden">
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
                        <button type="button" class="btn btn-warning" id="btnOdemeReset">
                            <i class="bx bx-reset me-1"></i>Varsayılana Dön
                        </button>
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
        <div class="modal-dialog modal-dialog-centered modal-dialog-md">
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
                                        <form id="formPersonelGelirEkle" novalidate>
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
                                                <?= Form::FormFloatInput("number", "gelir_tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" name="tutar"') ?>
                                            </div>

                                            <div class="mb-3">
                                                <?= Form::FormFloatInput("text", "tarih", date('Y-m-d'), "", "Tarih", "calendar", "form-control flatpickr", true, null, "off", false, 'id="gelir_tarih"') ?>
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

    <style>

    </style>

    <!-- Personel Kesinti Ekle Modal -->
    <div class="modal fade" id="modalPersonelKesintiEkle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-md">
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
                                        <form id="formPersonelKesintiEkle" novalidate>
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

                                            <div class="mb-3 d-none" id="div_ucretsiz_izin_secenek">
                                                <label class="form-label fw-bold d-block mb-2"><i
                                                        class="bx bx-cog me-1"></i>Kesinti Yöntemi</label>
                                                <div class="btn-group w-100" role="group">
                                                    <input type="radio" class="btn-check" name="rad_kesinti_tip"
                                                        id="kesinti_tip_tutar" value="tutar" checked>
                                                    <label class="btn btn-outline-danger" for="kesinti_tip_tutar"><i
                                                            class="bx bx-lira me-1"></i> Tutar Gir</label>

                                                    <input type="radio" class="btn-check" name="rad_kesinti_tip"
                                                        id="kesinti_tip_gun" value="gun">
                                                    <label class="btn btn-outline-danger" for="kesinti_tip_gun"><i
                                                            class="bx bx-calendar me-1"></i> Gün Gir</label>
                                                </div>
                                            </div>

                                            <div class="mb-3 d-none" id="div_kesinti_gun">
                                                <?= Form::FormFloatInput("number", "kesinti_gun", "", "0", "Gün Sayısı", "calendar", "form-control", false, null, "off", false, 'id="kesinti_gun_sayisi" min="0" step="1"') ?>
                                            </div>

                                            <div class="mb-3" id="div_kesinti_tutar">
                                                <?= Form::FormFloatInput("number", "kesinti_tutar", "", "0,00", "Tutar (TL)", "credit-card", "form-control", true, null, "off", false, 'step="0.01" name="tutar"') ?>
                                            </div>

                                            <div class="mb-3">
                                                <?= Form::FormFloatInput("text", "tarih", date('Y-m-d'), "", "Tarih", "calendar", "form-control flatpickr", true, null, "off", false, 'id="kesinti_tarih"') ?>
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
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 80%;">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bx bx-show me-2"></i>Bordro Detayı</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bordroDetailContent">
                    <!-- İçerik AJAX ile yüklenecek -->
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap">
                    <div id="bordroDetailFooterSummary" class="d-flex flex-wrap align-items-center gap-2 text-start"></div>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <?php
                                echo Form::FormSelect2(
                                    name: 'edit_donem_ay',
                                    options: \App\Helper\Date::MONTHS,
                                    selectedValue: '',
                                    label: 'Ay',
                                    icon: 'calendar',
                                    class: 'form-control select2',
                                    attributes: 'id="edit_donem_ay"'
                                );
                                ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <?php
                                $years = [];
                                $currentYear = date('Y');
                                for ($i = $currentYear - 1; $i <= $currentYear + 5; $i++) {
                                    $years[$i] = $i;
                                }
                                echo Form::FormSelect2(
                                    name: 'edit_donem_yil',
                                    options: $years,
                                    selectedValue: '',
                                    label: 'Yıl',
                                    icon: 'calendar',
                                    class: 'form-control select2',
                                    attributes: 'id="edit_donem_yil"'
                                );
                                ?>
                            </div>
                        </div>
                        <input type="hidden" name="donem_adi" id="edit_donem_adi_hidden">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php if ($selectedDonem): ?>
    <!-- Floating Maaş Hesapla Butonu -->
    <button type="button" 
            class="btn btn-primary text-white shadow-lg align-items-center justify-content-center floating-hesapla-btn rounded-pill" 
            id="btnHesaplaFloat">
        <i class="mdi mdi-calculator me-2"></i> <span class="fw-bold">Maaş Hesapla</span>
    </button>

    <style>
    .floating-hesapla-btn {
        position: fixed !important;
        bottom: 25px !important;
        right: 40px !important;
        z-index: 10000 !important;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        opacity: 0;
        visibility: hidden;
        transform: translateY(50px) scale(0.8);
        display: flex !important;
        border: 2px solid rgba(255, 255, 255, 0.2) !important;
    }
    .floating-hesapla-btn.show-float {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0) scale(1) !important;
    }
    .floating-hesapla-btn:hover {
        transform: translateY(-5px) scale(1.05) !important;
        /* box-shadow: 0 20px 40px rgba(13, 110, 253, 0.5) !important;
        background-color: #0b5ed7 !important; */
    }
    </style>

    <script>
    (function() {
        var floatBtn = document.getElementById('btnHesaplaFloat');
        var originalBtn = document.getElementById('btnHesapla');
        
        if (floatBtn && originalBtn) {
            function handleScroll() {
                // Skote gibi temalar bazen body veya iç div üzerinden kayar
                var scrollVal = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;
                
                // Bazı mizanpajlarda header sticky olduğu için threshold biraz daha yüksek olabilir
                // Ama biz 150-200 arası bir değerde görünür yapalım
                if (scrollVal > 200) {
                    floatBtn.classList.add('show-float');
                } else {
                    floatBtn.classList.remove('show-float');
                }
            }
            
            window.addEventListener('scroll', handleScroll, { passive: true });
            // Skote layout'u için olası scroll containerlarını da dinle
            var mainContent = document.querySelector('.main-content');
            if (mainContent) mainContent.addEventListener('scroll', handleScroll, { passive: true });
            
            // İlk kontrol
            setTimeout(handleScroll, 500);
            
            floatBtn.addEventListener('click', function() {
                originalBtn.click();
                this.classList.add('btn-success');
                setTimeout(() => { this.classList.remove('btn-success'); }, 500);
            });
        }
    })();
    </script>
    <?php endif; ?>

</div>

<script src="views/bordro/js/bordro.js?v=<?= time() ?>"></script>

<!-- Hatalı İşlemler Sayıları Modal -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<div class="modal fade" id="modalHataliIslemler" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title"><i class="bx bx-error text-warning me-2"></i> Hatalı İşlemler Sayıları Raporu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2" style="font-size: 13px;">
                    <i class="bx bx-info-circle me-1"></i> Bu rapor, "İş Takip" sistemindeki ham kayıt sayıları ile Bordro'da (personel_ek_odemeler) hesaplanan adetleri karşılaştırır.
                </div>
                
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div class="form-check form-switch p-2 px-4 border rounded bg-light" style="display: inline-block;">
                        <input class="form-check-input" type="checkbox" id="checkOnlyErrors" checked>
                        <label class="form-check-label fw-bold small mb-0" for="checkOnlyErrors">Sadece Farklı Olanları Göster</label>
                    </div>
                    <div class="text-muted small">
                        Seçili Dönem: <strong id="compareDonemName">...</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0" id="tableHataliIslemler" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Personel</th>
                                <th>İş Türü</th>
                                <th class="text-center" style="width: 100px;">İş Takip</th>
                                <th class="text-center" style="width: 100px;">Bordro</th>
                                <th class="text-center" style="width: 100px;">Fark</th>
                                <th class="text-center" style="width: 100px;">Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Veriler JS ile yüklenecek -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-success" onclick="exportHataliIslemlerToExcel()">
                    <i class="bx bx-file me-1"></i> Excel'e Aktar
                </button>
                <button type="button" class="btn btn-primary" onclick="loadHataliIslemler()">
                    <i class="bx bx-refresh me-1"></i> Yenile
                </button>
            </div>
        </div>
    </div>
    <!-- İzin/Rapor Takvim Modalı -->
    </div>
    <div class="modal fade" id="modalIzinTakvim" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header premium-modal-header modal-header-primary">
                    <div class="modal-title-section">
                        <div class="modal-icon-box">
                            <i class="bx bx-layer"></i>
                        </div>
                        <div class="modal-title-group">
                            <h5 class="modal-title">
                                <strong id="takvim_personel_ad"></strong> 
                                <span class="text-muted fw-normal mx-1">/</span> 
                                <span id="takvim_yil_gosterge" class="fw-semibold"></span>
                            </h5>
                            <p class="modal-subtitle">Lütfen formu eksiksiz doldurun.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3 justify-content-center" id="modalTakvimContainer">
                        <!-- JS ile doldurulacak -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

<script>
    window.ayIsimleriModal = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
    window.gunIsimleriModal = ["Pt", "Sa", "Ça", "Pe", "Cu", "Ct", "Pz"];

    window.openTakvimModalDirect = function(el) {
        try {
            var id = $(el).attr('data-id');
            var ad = $(el).attr('data-ad');
            var iseGiris = $(el).attr('data-ise-giris');
            var istenCikis = $(el).attr('data-isten-cikis');
            var ay = $(el).attr('data-ay');
            var yil = $(el).attr('data-yil');

            if (!id || !ay || !yil) {
                console.error('Takvim modal açılamadı - eksik parametreler:', { id: id, ay: ay, yil: yil });
                return;
            }

            $('#takvim_personel_ad').text(ad || 'Bilinmeyen');
            var ayAdi = window.ayIsimleriModal && window.ayIsimleriModal[parseInt(ay) - 1];
            $('#takvim_yil_gosterge').text((ayAdi || 'Ay') + ' ' + yil);

            $('#modalTakvimContainer').html('<div class="col-12 text-center p-5"><div class="spinner-border text-primary"></div></div>');

            // Bootstrap modal'ı aç (v5+ ve eski versiyonlar için fallback)
            var modalEl = document.getElementById('modalIzinTakvim');
            if (bootstrap && bootstrap.Modal) {
                new bootstrap.Modal(modalEl).show();
            } else {
                // Eski Bootstrap versiyonları için jQuery fallback
                $('#modalIzinTakvim').modal('show');
            }

            $.ajax({
                url: 'views/personel/api/puantaj_izin.php',
                type: 'POST',
                data: {
                    action: 'get-personel-month-data',
                    personel_id: id,
                    ay: ay,
                    yil: yil
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'success') {
                        renderYearlyModalCalendar(yil, parseInt(ay) - 1, res.data, iseGiris, istenCikis, res.calisma_donemleri || []);
                    } else {
                        $('#modalTakvimContainer').html('<div class="col-12 text-center p-5"><div class="alert alert-danger"><i class="bx bx-error me-2"></i>' + (res.message || 'Veriler yüklenemedi.') + '</div></div>');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = 'Veriler yüklenirken bir hata oluştu.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                    console.error('Takvim API hatası:', { status: status, error: error, xhr: xhr });
                    $('#modalTakvimContainer').html('<div class="col-12 text-center p-5"><div class="alert alert-danger"><i class="bx bx-error me-2"></i>' + errorMsg + '</div></div>');
                }
            });
        } catch (e) {
            console.error('Takvim modal açma hatası:', e);
            alert('Takvim açılırken bir hata oluştu: ' + e.message);
        }
    };
</script>

<script>
    $(document).ready(function() {
        // Takvim modalı açma - gün sütununa tıklama
        $(document).on('click', '.btn-open-takvim', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openTakvimModalDirect(this);
        });

        $('#btnHataliIslemler').on('click', function() {
            loadHataliIslemler();
            $('#modalHataliIslemler').modal('show');
        });

        $('#checkOnlyErrors').on('change', function() {
            renderHataliIslemlerTable();
        });

        $('#btnFixMatrah2026').on('click', function() {
            Swal.fire({
                title: 'Matrah Düzeltme',
                text: '2026 Ocak-Mayıs dönemlerindeki tüm personellerin kümülatif vergi matrahı geçmişi puantaj kurallarına göre yeniden hesaplanacaktır. Onaylıyor musunuz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Düzelt',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Hesaplanıyor...',
                        text: 'Lütfen bekleyiniz, matrahlar düzeltiliyor.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    $.ajax({
                        url: 'views/bordro/api.php',
                        type: 'POST',
                        data: { action: 'fix-kumulatif-matrah-2026' },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire('Başarılı', response.message, 'success').then(() => {
                                    bordroTabloYenile();
                                });
                            } else {
                                Swal.fire('Hata', response.message || 'Bir hata oluştu.', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Hata', 'Sunucu hatası oluştu.', 'error');
                        }
                    });
                }
            });
        });
    });

    function renderYearlyModalCalendar(year, month, events, iseGiris, istenCikis, calismaDonemleri = []) {
        var summary = collectTakvimSummary(events, calismaDonemleri);

        var html = '';
        html += `<div class="col-12">`;
        
        // Özet Kartları
        html += `<div class="row g-2 mb-2">
            <div class="col-md-4">
                <div class="premium-summary-card card-total h-100 d-flex flex-column justify-content-between" style="min-height: 80px;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold text-uppercase tracking-wider" style="font-size: 0.65rem;">Toplam Gün</span>
                        <div class="icon-box" style="background-color: #f1f5f9; color: #475569;">
                            <i class="bx bx-calendar"></i>
                        </div>
                    </div>
                    <h3 class="mb-0 fw-bold text-dark" style="font-size: 1.3rem;">${summary.toplamGun} <small class="text-muted fs-6 fw-normal">Gün</small></h3>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="premium-summary-card card-ucretli h-100 d-flex flex-column justify-content-between" style="min-height: 80px;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold text-uppercase tracking-wider" style="font-size: 0.65rem;">Ücretli Günler</span>
                        <div class="icon-box" style="background-color: #ecfdf5; color: #059669;">
                            <i class="bx bx-check-circle"></i>
                        </div>
                    </div>
                    <h3 class="mb-0 fw-bold text-success" style="font-size: 1.3rem;">${summary.ucretliToplam} <small class="text-muted fs-6 fw-normal">Gün</small></h3>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        ${summary.ucretliList.map(item => `<span class="badge bg-light text-dark border px-1.5 py-0.5" style="font-size:9px; font-weight:600; border-radius: 4px;">${item.kisa_kod}: ${item.count}</span>`).join('')}
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="premium-summary-card card-ucretsiz h-100 d-flex flex-column justify-content-between" style="min-height: 80px;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted small fw-semibold text-uppercase tracking-wider" style="font-size: 0.65rem;">Ücretsiz Günler</span>
                        <div class="icon-box" style="background-color: #fff1f2; color: #e11d48;">
                            <i class="bx bx-x-circle"></i>
                        </div>
                    </div>
                    <h3 class="mb-0 fw-bold text-danger" style="font-size: 1.3rem;">${summary.ucretsizToplam} <small class="text-muted fs-6 fw-normal">Gün</small></h3>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        ${summary.ucretsizList.map(item => `<span class="badge bg-light text-dark border px-1.5 py-0.5" style="font-size:9px; font-weight:600; border-radius: 4px;">${item.kisa_kod}: ${item.count}</span>`).join('')}
                    </div>
                </div>
            </div>
        </div>`;

        html += `<div class="year-calendar-month">
                <div class="year-calendar-header">${window.ayIsimleriModal[month]}</div>
                <div class="p-3">
                    <table class="year-calendar-table">
                        <thead>
                            <tr>${window.gunIsimleriModal.map(g => `<th>${g}</th>`).join('')}</tr>
                        </thead>
                        <tbody>
                            ${getMonthRowsModal(year, month, events, iseGiris, istenCikis, calismaDonemleri)}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;
        $('#modalTakvimContainer').html(html);

        // Tooltipleri başlat
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('#modalTakvimContainer [data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, { container: '#modalIzinTakvim' });
        });
    }

    function getMonthRowsModal(year, month, events, iseGiris, istenCikis, calismaDonemleri = []) {
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);

        var startingDay = firstDay.getDay(); // 0 (Paz) - 6 (Cmt)
        startingDay = (startingDay === 0) ? 7 : startingDay;

        var totalDays = lastDay.getDate();
        var rows = '';
        var day = 1;

        for (var i = 0; i < 6; i++) {
            var cells = '';
            for (var j = 1; j <= 7; j++) {
                if (i === 0 && j < startingDay) {
                    cells += '<td></td>';
                } else if (day > totalDays) {
                    cells += '<td></td>';
                } else {
                    var dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    
                    var aktifCalismaGunu = calismaDonemleri.length > 0
                        ? calismaDonemleri.some(donem => dateStr >= donem.baslangic && (!donem.bitis || dateStr <= donem.bitis))
                        : !((iseGiris && iseGiris !== '0000-00-00' && dateStr < iseGiris)
                            || (istenCikis && istenCikis !== '0000-00-00' && dateStr > istenCikis));
                    var isPassive = !aktifCalismaGunu;

                    var dayEvents = (events && events[dateStr]) ? events[dateStr] : [];
                    var cellContent = `<div class="year-calendar-day">
                        <span class="year-calendar-day-number">${day}</span>
                    </div>`;
                    var style = '';
                    var titleAttr = '';
                    var passiveClass = isPassive ? 'passive-date' : '';
                    var filledClass = '';

                    if (dayEvents.length > 0) {
                        var event = dayEvents[0];
                        var eventStyle = getStyleFromTailwindProxyModal(event.color);
                        style = `background-color: ${eventStyle.bg} !important; color: ${eventStyle.color} !important; border-radius: 16px; font-weight: bold;`;
                        cellContent = `<div class="year-calendar-day" data-bs-toggle="tooltip" title="${event.kisa_kod} : ${event.name}">
                            <span class="year-calendar-day-number">${day}</span>
                            <span class="year-calendar-day-code" style="background-color:${eventStyle.color}; color:#fff;">${event.kisa_kod}</span>
                            <span class="year-calendar-day-desc">${event.name}</span>
                        </div>`;
                        filledClass = 'is-filled';
                    }

                    var isToday = new Date().toISOString().split('T')[0] === dateStr;
                    var todayClass = isToday ? 'today' : '';

                    cells += `<td class="${todayClass} ${passiveClass} ${filledClass}" style="${style}" ${titleAttr}>${cellContent}</td>`;
                    day++;
                }
            }
            rows += `<tr>${cells}</tr>`;
            if (day > totalDays) break;
        }
        return rows;
    }

    function getStyleFromTailwindProxyModal(tailwindClass) {
        if (!tailwindClass)
            return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };

        if (tailwindClass.startsWith("#")) {
            return {
                bg: tailwindClass + "26",
                color: tailwindClass,
            };
        }

        if (tailwindClass.includes("blue"))
            return { bg: "#dbeafe", color: "#2563eb" };
        if (tailwindClass.includes("amber") || tailwindClass.includes("warning"))
            return { bg: "#fef3c7", color: "#d97706" };
        if (tailwindClass.includes("red") || tailwindClass.includes("danger"))
            return { bg: "#fee2e2", color: "#dc2626" };
        if (tailwindClass.includes("pink"))
            return { bg: "#fce7f3", color: "#db2777" };
        if (tailwindClass.includes("gray"))
            return { bg: "#f3f4f6", color: "#4b5563" };
        if (tailwindClass.includes("green") || tailwindClass.includes("success"))
            return { bg: "#dcfce7", color: "#16a34a" };
        if (tailwindClass.includes("purple"))
            return { bg: "#f3e8ff", color: "#9333ea" };

        return { bg: "rgba(85, 110, 230, 0.15)", color: "#556ee6" };
    }

    function collectTakvimSummary(events, calismaDonemleri = []) {
        var ucretliList = [];
        var ucretsizList = [];
        var eventCounts = {};

        Object.entries(events || {}).forEach(function([dateStr, dayEntries]) {
            var aktifCalismaGunu = calismaDonemleri.length === 0
                || calismaDonemleri.some(donem => dateStr >= donem.baslangic && (!donem.bitis || dateStr <= donem.bitis));
            if (!aktifCalismaGunu) return;
            (dayEntries || []).forEach(function(entry) {
                if (!entry || !entry.name || !entry.kisa_kod) {
                    return;
                }
                var key = `${entry.kisa_kod}-${entry.name}`;
                if (!eventCounts[key]) {
                    var style = getStyleFromTailwindProxyModal(entry.color);
                    eventCounts[key] = {
                        kisa_kod: entry.kisa_kod,
                        name: entry.name,
                        color: style.color,
                        count: 0
                    };
                }
                eventCounts[key].count++;
            });
        });

        var toplamGun = 0;
        var ucretliToplam = 0;
        var ucretsizToplam = 0;

        Object.values(eventCounts).forEach(function(item) {
            toplamGun += item.count;
            var lowName = (item.name || '').toLowerCase();
            var lowKod = (item.kisa_kod || '').toLowerCase();

            var isUnpaid = lowName.includes('ücretsiz') || lowName.includes('mazeret') || lowKod.includes('üi') || lowKod.includes('mi');

            if (isUnpaid) {
                ucretsizToplam += item.count;
                ucretsizList.push(item);
            } else {
                ucretliToplam += item.count;
                ucretliList.push(item);
            }
        });

        return {
            toplamGun: toplamGun,
            ucretliToplam: ucretliToplam,
            ucretsizToplam: ucretsizToplam,
            ucretliList: ucretliList,
            ucretsizList: ucretsizList
        };
    }

    var hataliIslemlerRawData = [];

    function loadHataliIslemler() {
        var donemId = $('select[name="donemSelect"]').val() || '<?= $selectedDonemId ?>';
        if (!donemId) {
            Swal.fire('Hata', 'Lütfen önce bir dönem seçiniz.', 'error');
            return;
        }

        $('#compareDonemName').text($('#displayDonemAdi').text());
        var $tbody = $('#tableHataliIslemler tbody');
        $tbody.html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Analiz ediliyor...</td></tr>');

        $.post('views/bordro/api.php', {
            action: 'get-hatali-islem-raporu',
            donem_id: donemId
        }, function(res) {
            if (res.status === 'success') {
                hataliIslemlerRawData = res.data;
                renderHataliIslemlerTable();
            } else {
                $tbody.html('<tr><td colspan="6" class="text-center text-danger py-4">' + (res.message || 'Veri alınamadı') + '</td></tr>');
            }
        }, 'json').fail(function() {
            $tbody.html('<tr><td colspan="6" class="text-center text-danger py-4">Sistem hatası oluştu.</td></tr>');
        });
    }

    function renderHataliIslemlerTable() {
        var $tbody = $('#tableHataliIslemler tbody');
        var onlyErrors = $('#checkOnlyErrors').is(':checked');

        var groups = {};

        hataliIslemlerRawData.forEach(function(row) {
            if (onlyErrors && row.fark === 0) return;

            if (!groups[row.personel_id]) {
                groups[row.personel_id] = {
                    name: row.personel_adi,
                    maas_durumu: row.maas_durumu,
                    items: [],
                    errorCount: 0,
                    totalIsTakip: 0,
                    totalBordro: 0,
                    totalFark: 0
                };
            }
            groups[row.personel_id].items.push(row);
            groups[row.personel_id].totalIsTakip += row.is_takip_sayisi;
            groups[row.personel_id].totalBordro += row.bordro_sayisi;
            groups[row.personel_id].totalFark += row.fark;

            if (row.fark != 0) groups[row.personel_id].errorCount++;
        });

        var html = '';
        var personCount = 0;

        Object.keys(groups).forEach(function(pId) {
            var group = groups[pId];
            var hasError = group.errorCount > 0;
            var bgClass = hasError ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10';
            var icon = hasError ? 'bx-error text-danger' : 'bx-check-circle text-success';

            var isPrimUsulu = (group.maas_durumu && group.maas_durumu.toLowerCase().includes('prim'));
            var maasDurumuHtml = group.maas_durumu ? `<span class="badge ${isPrimUsulu ? 'bg-info' : 'bg-light text-dark'} ms-2">${group.maas_durumu}</span>` : '';

            var formatNum = function(num) { return Number.isInteger(num) ? num : num.toFixed(2).replace(/\.00$/, ''); };

            html += `<tr class="personel-group-row cursor-pointer ${bgClass}" data-personel-id="${pId}">
                <td colspan="2" class="fw-bold">
                    <i class="bx bx-chevron-right me-1 transition-icon"></i>
                    <i class="bx ${icon} me-1"></i>
                    ${group.name}
                    ${maasDurumuHtml}
                    <span class="badge bg-secondary ms-2">${group.items.length} İşlem</span>
                </td>
                <td class="text-center fw-bold">${formatNum(group.totalIsTakip)}</td>
                <td class="text-center fw-bold">${formatNum(group.totalBordro)}</td>
                <td class="text-center fw-bold ${group.totalFark != 0 ? 'text-danger' : 'text-success'}">${formatNum(group.totalFark)}</td>
                <td class="text-center">
                    <span class="badge ${hasError ? 'bg-danger' : 'bg-success'}">${hasError ? group.errorCount + ' Hatalı' : 'Tamam'}</span>
                </td>
            </tr>`;

            group.items.forEach(function(item) {
                var diffClass = item.fark != 0 ? 'text-danger fw-bold' : 'text-success';
                var statusHtml = item.fark != 0
                    ? '<span class="badge bg-danger">Hatalı</span>'
                    : '<span class="badge bg-success">Tamam</span>';

                html += `<tr class="detail-row detail-p-${pId}" style="display: none; background-color: #fafafa;">
                    <td class="ps-4 text-muted border-end-0" colspan="2"><i class="bx bx-subdirectory-right me-1"></i> ${item.is_turu}</td>
                    <td class="text-center">${formatNum(item.is_takip_sayisi)}</td>
                    <td class="text-center">${formatNum(item.bordro_sayisi)}</td>
                    <td class="text-center ${diffClass}">${formatNum(item.fark)}</td>
                    <td class="text-center">${statusHtml}</td>
                </tr>`;
            });
            personCount++;
        });

        if (personCount === 0) {
            html = '<tr><td colspan="6" class="text-center py-4 text-muted">' + (onlyErrors ? 'Fark bulunan kayıt yok.' : 'Kayıt bulunamadı.') + '</td></tr>';
        }

        $tbody.html(html);

        $('.personel-group-row').off('click').on('click', function() {
            var pId = $(this).data('personel-id');
            var $icon = $(this).find('.transition-icon');
            var $detailRows = $('.detail-p-' + pId);

            $detailRows.toggle();
            if ($detailRows.is(':visible')) {
                $icon.css('transform', 'rotate(90deg)');
            } else {
                $icon.css('transform', 'rotate(0deg)');
            }
        });
    }

    function exportHataliIslemlerToExcel() {
        if (!hataliIslemlerRawData || hataliIslemlerRawData.length === 0) {
            Swal.fire('Hata', 'Aktarılacak veri bulunamadı.', 'error');
            return;
        }

        var onlyErrors = $('#checkOnlyErrors').is(':checked');
        var donemLabel = $('#compareDonemName').text().replace(/\s+/g, '_');

        var excelData = [];

        excelData.push([
            "Personel",
            "Maaş Durumu",
            "İş Türü",
            "İş Takip Sayısı",
            "Bordro Sayısı",
            "Fark",
            "Durum"
        ]);

        hataliIslemlerRawData.forEach(function(row) {
            if (onlyErrors && row.fark === 0) return;

            excelData.push([
                row.personel_adi,
                row.maas_durumu,
                row.is_turu,
                row.is_takip_sayisi,
                row.bordro_sayisi,
                row.fark,
                row.durum
            ]);
        });

        if (excelData.length <= 1) {
            Swal.fire('Bilgi', 'Filtreleme sonucunda aktarılacak kayıt bulunamadı.', 'info');
            return;
        }

        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.aoa_to_sheet(excelData);

        ws['!cols'] = [
            { wch: 30 },
            { wch: 15 },
            { wch: 30 },
            { wch: 15 },
            { wch: 15 },
            { wch: 10 },
            { wch: 15 }
        ];

        XLSX.utils.book_append_sheet(wb, ws, "Hatalı_İşlemler");

        var filename = "Hatali_Islemler_Raporu_" + donemLabel + "_" + moment().format('YYYYMMDD_HHmm') + ".xlsx";

        XLSX.writeFile(wb, filename);
    }
</script>

<style>
    .personel-group-row:hover {
        filter: brightness(0.95);
    }
    .transition-icon {
        transition: transform 0.2s ease;
        display: inline-block;
    }
    .cursor-pointer {
        cursor: pointer;
    }
</style>
