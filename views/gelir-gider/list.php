<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\GelirGiderModel;
use App\Helper\Financial;
use App\Model\TanimlamalarModel;
use Random\Engine\Secure;

$GelirGider = new GelirGiderModel();
$Financial = new Financial();

/* -------- Filtre parametreleri -------- */
$selectedYil = $_GET['yil'] ?? date('Y');
$selectedAy  = $_GET['ay'] ?? '';
$selectedTip = $_GET['tip'] ?? '';

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

$tipSecenekleri = [
    ''  => 'Tüm İşlemler',
    '1' => 'Gelir',
    '2' => 'Gider',
];

// Sunucu tarafı DataTables kullanıldığı için tüm kayıtları burada çekmeye gerek yok
// $gelir_gider = $GelirGider->all($selectedYil, $selectedAy, $selectedTip);
// $kayit_sayisi = count($gelir_gider);

$Tanimlama = new TanimlamalarModel();
$summary = $GelirGider->summary(['yil' => $selectedYil, 'ay' => $selectedAy, 'tip' => $selectedTip]);

?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    :root {
        --fin-primary: #0F172A;
        --fin-secondary: #1E3A8A;
        --fin-cta: #CA8A04;
        --fin-bg: #F8FAFC;
        --fin-text: #020617;
        --fin-glass: rgba(255, 255, 255, 0.7);
    }

    /* Modal Premium Styling */
    #gelirGiderModal .modal-content {
        border: none;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        font-family: 'DM Sans', sans-serif;
        background: var(--fin-bg);
        overflow: hidden;
    }

    #gelirGiderModal .modal-header {
        background: white;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1.5rem 2rem;
    }

    #gelirGiderModal .premium-icon-box {
        width: 48px;
        height: 48px;
        background: #f1f5f9;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--fin-primary);
        margin-right: 1rem;
    }

    #gelirGiderModal .form-selectgroup-item {
        width: 100%;
    }

    #gelirGiderModal .form-selectgroup-label {
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        transition: all 0.3s ease;
        background: white;
        cursor: pointer;
    }

    #gelirGiderModal .form-selectgroup-input:checked + .form-selectgroup-label {
        border-color: var(--fin-primary);
        background: #f8fafc;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.1);
    }

    #gelirGiderModal .form-floating > .form-control:focus, 
    #gelirGiderModal .form-floating > .form-select:focus {
        border-color: var(--fin-primary);
        box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.05);
    }

    #gelirGiderModal .modal-footer {
        background: white;
        border-top: 1px solid rgba(0,0,0,0.05);
        padding: 1.25rem 2rem;
    }

    .btn-premium-save {
        background: var(--fin-primary);
        color: white;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.2s;
        border: none;
    }

    .btn-premium-save:hover {
        background: #1e293b;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        color: white;
    }

    .btn-premium-close {
        background: #f1f5f9;
        color: #475569;
        border-radius: 12px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        border: none;
    }

    /* Dark Mode Overrides */
    [data-bs-theme="dark"] #gelirGiderModal .modal-content {
        background: #1e293b;
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] #gelirGiderModal .modal-header,
    [data-bs-theme="dark"] #gelirGiderModal .modal-footer {
        background: #0f172a;
        border-color: rgba(255, 255, 255, 0.1);
    }

    [data-bs-theme="dark"] #gelirGiderModal .premium-icon-box {
        background: #334155;
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] #gelirGiderModal .form-selectgroup-label {
        background: #1e293b;
        border-color: #334155;
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] #gelirGiderModal .form-selectgroup-input:checked + .form-selectgroup-label {
        background: #334155;
        border-color: #64748b;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    [data-bs-theme="dark"] #gelirGiderModal .text-secondary {
        color: #94a3b8 !important;
    }

    [data-bs-theme="dark"] .btn-premium-close {
        background: #334155;
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] .btn-premium-close:hover {
        background: #475569;
        color: white;
    }

    [data-bs-theme="dark"] #gelirGiderModal .alert-info {
        background: rgba(14, 165, 233, 0.1);
        border: 1px solid rgba(14, 165, 233, 0.2);
        color: #7dd3fc;
    }
</style>

<div class="container-fluid pt-3">

    <!-- start page title -->
    <?php
    $maintitle = "Gelir-Gider";
    $title = "Gelir Gider Listesi";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

        <!-- ======== FİLTRE KARTI ======== -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="index.php" method="GET" id="filterForm" class="w-100 mb-0">
                    <input type="hidden" name="p" value="gelir-gider/list">
                    
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Yıl -->
                        <?php echo Form::FormSelect2(
                            name: 'yil',
                            options: $yilSecenekleri,
                            selectedValue: $selectedYil,
                            label: 'Yıl',
                            icon: 'calendar',
                            style: 'min-width:120px'
                        ); ?>

                        <!-- Ay -->
                        <?php echo Form::FormSelect2(
                            name: 'ay',
                            options: $aySecenekleri,
                            selectedValue: $selectedAy,
                            label: 'Ay',
                            icon: 'calendar',
                            style: 'min-width:150px'
                        ); ?>

                        <!-- Tip -->
                        <?php echo Form::FormSelect2(
                            name: 'tip',
                            options: $tipSecenekleri,
                            selectedValue: $selectedTip,
                            label: 'İşlem Tipi',
                            icon: 'filter',
                            style: 'min-width:180px'
                        ); ?>

                        <div class="ms-auto d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1">
                            <button type="button" id="exportExcel" class="btn btn-link btn-sm text-secondary text-decoration-none px-2 d-flex align-items-center"> 
                                <i data-feather="file-text" class="me-1 fs-5"></i> <span class="d-none d-xl-inline">Excele Aktar</span>
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button" id="btnImportExcel" class="btn btn-link btn-sm text-success text-decoration-none px-2 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#importExcelModal">
                                <i data-feather="upload-cloud" class="me-1 fs-5"></i> <span class="d-none d-xl-inline">Excelden Yükle</span>
                            </button>
                            <div class="vr mx-1" style="height: 25px; align-self: center;"></div>
                            <button type="button" id="gelirGiderEkle" class="btn btn-dark btn-sm text-white shadow-sm text-decoration-none px-3 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#gelirGiderModal">
                                <i data-feather="plus" class="me-1 fs-5"></i> <span class="d-none d-xl-inline">Yeni İşlem</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var selects = document.querySelectorAll('#filterForm select');
                selects.forEach(function(select) {
                    $(select).on('select2:select', function (e) {
                         // Form submit yerine tabloyu reload ediyoruz
                         if (typeof reloadGelirGiderTable === 'function') {
                             reloadGelirGiderTable();
                         } else {
                             document.getElementById('filterForm').submit();
                         }
                    });
                });
            });
        </script>

        <!-- ======== ÖZET KARTLARI ======== -->
        <div class="row g-3 mb-4">
            <!-- Toplam Gelir -->
            <div class="col-xl-4 col-md-4">
                <div class="card border-0 shadow-sm h-100" style="border-bottom: 3px solid #2a9d8f !important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle p-2 me-2" style="background: rgba(42,157,143,0.1);">
                                <i data-feather="trending-up" class="fs-4 text-success"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size:0.65rem;">GELİR</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing:0.5px;opacity:0.7;">TOPLAM GELİR</p>
                        <h4 class="mb-0 fw-bold" id="card_toplam_gelir">
                            <?php echo Helper::formattedMoney($summary->toplam_gelir ?? 0); ?>
                            <span style="font-size:0.85rem;font-weight:600;">₺</span>
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Toplam Gider -->
            <div class="col-xl-4 col-md-4">
                <div class="card border-0 shadow-sm h-100" style="border-bottom: 3px solid #f43f5e !important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle p-2 me-2" style="background: rgba(244,63,94,0.1);">
                                <i data-feather="trending-down" class="fs-4 text-danger"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size:0.65rem;">GİDER</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing:0.5px;opacity:0.7;">TOPLAM GİDER</p>
                        <h4 class="mb-0 fw-bold" id="card_toplam_gider">
                            <?php echo Helper::formattedMoney($summary->toplam_gider ?? 0); ?>
                            <span style="font-size:0.85rem;font-weight:600;">₺</span>
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Net Bakiye -->
            <?php $bakiyeColor = ($summary->bakiye ?? 0) < 0 ? '#f43f5e' : '#0ea5e9'; ?>
            <div class="col-xl-4 col-md-4">
                <div class="card border-0 shadow-sm h-100" style="border-bottom: 3px solid <?= $bakiyeColor ?> !important;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="rounded-circle p-2 me-2" style="background: rgba(14,165,233,0.1);">
                                <i data-feather="activity" class="fs-4" style="color: <?= $bakiyeColor ?>;"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size:0.65rem;">BAKİYE</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing:0.5px;opacity:0.7;">NET BAKİYE</p>
                        <h4 class="mb-0 fw-bold" id="card_net_bakiye">
                            <?php echo Helper::formattedMoney($summary->bakiye ?? 0); ?>
                            <span style="font-size:0.85rem;font-weight:600;">₺</span>
                        </h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body overflow-auto p-3">



                        <table id="gelirGiderTable" class="table-hover table table-bordered nowrap w-100">
                            <thead>
                                <tr>
                                    <th data-data="id" style="width: 7%;" class="text-center">Sıra</th>
                                    <th data-data="kayit_tarihi" data-filter="date" class="text-center">Kayıt Tarihi</th>
                                    <th data-data="type" data-filter="select" class="text-center">Tür</th>
                                    <th data-data="hesap_adi" data-filter="select" class="text-center">Hesap Adı</th>
                                    <th data-data="kategori_adi" data-filter="select" class="text-center">Kategori</th>
                                    <th data-data="tarih" data-filter="date" class="text-center">İşlem Tarihi</th>
                                    <th data-data="tutar" data-filter="number" class="text-end">Tutar</th>
                                    <th data-data="bakiye" data-filter="number" class="text-end">Bakiye</th>
                                    <th data-data="aciklama" data-filter="string">Açıklama</th>
                                    <th data-data="actions" style="width:5%">İşlem</th>
                                </tr>
                            </thead>


                            <tbody>
                                <!-- Veriler AJAX ile yüklenecek -->
                            </tbody>
                        </table>

                    </div>
                </div>
            </div> <!-- end col -->
        </div> <!-- end row -->

    </div> <!-- container-fluid -->

    <div class="modal fade" id="gelirGiderModal" tabindex="-1" aria-labelledby="gelirGiderModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <div class="premium-icon-box">
                        <i data-feather="layers"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="gelirGiderModalLabel">Gelir Gider İşlemler</h5>
                        <small class="text-muted">Lütfen formu eksiksiz doldurun.</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="gelirGiderForm">
                        <input type="hidden" name="gelir_gider_id" id="gelir_gider_id" class="form-control" value="0">

                        <div class="row form-selectgroup-boxes row mb-3">
                            <div class="col-md-6">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="type" value="1" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <span class="me-3">
                                            <span class="form-selectgroup-check"></span>
                                        </span>
                                        <span class="">
                                            <span class="form-selectgroup-title strong mb-1">Gelir</span>
                                            <span class="d-block text-secondary">Gelir Türünü seçiniz</span>
                                        </span>
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="type" value="2" class="form-selectgroup-input" checked="">
                                    <span class="form-selectgroup-label d-flex align-items-center p-3">
                                        <span class="me-3">
                                            <span class="form-selectgroup-check"></span>
                                        </span>
                                        <span class="form-selectgroup-label-content">
                                            <span class="form-selectgroup-title strong mb-1">Gider</span>
                                            <span class="d-block text-secondary">Gider türünü seçiniz</span>
                                        </span>
                                    </span>
                                </label>
                            </div>



                        </div>
                        <div class="row mb-3">
                            <!--Listede olmayan kategori için manuel olarak yazabilirsiniz-->
                            <div class="alert alert-info">
                                <div class="alert-title">
                                    <i data-feather="info"></i>
                                    <span>Bilgi</span>
                                </div>
                                <div class="alert-text">
                                    Listede olmayan Hesap Adı veya Kategori için manuel olarak yazıt Enter'a basın!
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <?php
                                echo Form::FormSelect2(
                                    "hesap_adi",
                                    [],
                                    "",
                                    "Hesap Adı",
                                    "user",
                                    "id",
                                    "hesap_adi",
                                ); ?>
                            </div>
                            <div class="col-md-12">
                                <?php
                                echo Form::FormSelect2(
                                    "islem_turu",
                                    [],
                                    "",
                                    "Kategori",
                                    "map-pin",
                                    "id",
                                    "tur_adi",

                                ); ?>

                            </div>
                        </div>



                        <div class="row mb-3">
                            <div class="col-md-6">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "islem_tarihi",
                                        date("d.m.Y"),
                                        "İşlem Tarihi giriniz!",
                                        "İşlem Tarihi",
                                        "calendar",
                                        "form-control flatpickr"

                                    ); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "tutar",
                                        "",
                                        "Tutar giriniz!",
                                        "Tutar",
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
                                        "map-pin",


                                    ); ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" id="yeniIslemModal" class="btn btn-outline-success border-2 rounded-3 me-auto px-3 py-2 fw-bold d-flex align-items-center">
                        <i data-feather="plus" class="me-2"></i> Yeni İşlem
                    </button>

                    <button type="button" class="btn btn-premium-close waves-effect" data-bs-dismiss="modal">
                        <i data-feather="x" class="me-1" style="width:18px"></i> Kapat
                    </button>
                    <button type="button" id="gelirGiderKaydet" class="btn btn-premium-save waves-effect">
                        <i data-feather="save" class="me-1" style="width:18px"></i> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Excel Import Modal -->
    <div class="modal fade" id="importExcelModal" tabindex="-1" aria-labelledby="importExcelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center">
                    <div class="premium-icon-box bg-success bg-opacity-10 text-success">
                        <i data-feather="upload-cloud"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="importExcelModalLabel">Excel'den Gelir-Gider Yükle</h5>
                        <small class="text-muted">Lütfen geçerli bir excel dosyası seçiniz.</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success bg-success bg-opacity-10 border-0 mb-4 p-3 rounded-4">
                        <div class="d-flex align-items-start">
                            <div class="rounded-circle p-2 bg-success text-white me-3">
                                <i data-feather="download" style="width:16px;height:16px"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fw-bold text-success font-size-14">Şablon Dosyasını İndirin</h6>
                                <p class="mb-2 small text-muted">İşlemleri doğru yüklemek için şablon dosyasını kullanın.</p>
                                <a href="files/gelir_gider_sablon.xlsx" class="btn btn-sm btn-success rounded-3 px-3">
                                    <i data-feather="file-text" class="me-1" style="width:14px"></i> Şablonu İndir
                                </a>
                            </div>
                        </div>
                    </div>
                    <form id="importExcelForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="excelFile" class="form-label fw-bold text-secondary small">Excel Dosyası (.xlsx, .xls)</label>
                            <input class="form-control rounded-3" type="file" id="excelFile" name="excelFile" accept=".xlsx, .xls" required>
                        </div>
                    </form>
                    <div class="alert alert-info bg-info bg-opacity-10 border-0 rounded-4 p-3 mb-0">
                        <h6 class="fw-bold font-size-13 mb-2"><i data-feather="info" class="me-1" style="width:14px"></i> İşlem Tipleri</h6>
                        <p class="mb-1 small"><strong>GELİR:</strong> Kasaya eklenecek tutarlar.</p>
                        <p class="mb-0 small"><strong>GİDER:</strong> Kasadan düşülecek tutarlar.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-premium-close" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-success rounded-3 px-4 py-2 fw-bold" id="btnUploadExcel">
                        <i data-feather="upload" class="me-1" style="width:18px"></i> Yükle
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            flatpickr(".flatpickr", {
                dateFormat: "d.m.Y H:i",
                enableTime: true,
                time_24hr: true,
                allowInput: true,
                minuteIncrement: 1,
            });
        });

        $(document).ready(function() {
            // Select2 tag desteği
            $("#islem_turu").select2({
                tags: true,
                placeholder: "Kategori Seçiniz",
                allowClear: true,
                dropdownParent: $('#gelirGiderModal')
            });

            $("#hesap_adi").select2({
                tags: true,
                placeholder: "Hesap Adı Seçiniz",
                allowClear: true,
                dropdownParent: $('#gelirGiderModal')
            });

            // Hesap adlarını yükle
            function loadHesapAdlari() {
                $.ajax({
                    url: 'views/gelir-gider/api.php',
                    type: 'POST',
                    data: { action: 'hesap-adlari-getir' },
                    dataType: 'json',
                    success: function(response) {
                        let currentVal = $("#hesap_adi").val();
                        $("#hesap_adi").empty().append('<option></option>');
                        response.forEach(function(item) {
                            if(item != '0' && item != '' && item != null) {
                                $("#hesap_adi").append(new Option(item, item));
                            }
                        });
                        if (currentVal) $("#hesap_adi").val(currentVal).trigger('change');
                    }
                });
            }

            // Modal açıldığında hesap adlarını yenile
            $('#gelirGiderModal').on('show.bs.modal', function() {
                loadHesapAdlari();
            });
        });
    </script>