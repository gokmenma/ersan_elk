<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Security;
use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Helper;
use App\Model\KasaModel;


use App\Model\GelirGiderModel;
use App\Helper\Financial;
use App\Model\TanimlamalarModel;
use Random\Engine\Secure;

$GelirGider = new GelirGiderModel();
$Financial = new Financial();
$Kasa = new KasaModel();


$kasaOptions = [];
$kasalar = $Kasa->getKasaListByOwner($_SESSION['owner_id']);

foreach ($kasalar as $kasa) {
    $kasaOptions[Security::encrypt($kasa->id)] = $kasa->kasa_adi;
}


/** Get ile gelmiyorsa varsayilan kasayı al */
if (!isset($_GET['kasa_id'])) {
    $_SESSION['kasa_id'] = $Kasa->getDefaultCashboxId($_SESSION['owner_id']);
} else if (!empty($_GET['kasa_id'])) {
    $_SESSION['kasa_id'] = Security::decrypt($_GET['kasa_id']);
}

$kasa_id = $_SESSION['kasa_id'];
$decrypted_kasa_id = ($kasa_id ?? null);

$gelir_gider = $GelirGider->all($kasa_id);
$kayit_sayisi = count($gelir_gider);

$hesap_adi = $Uye->getHesapAdiFromUye();

$Tanimlama = new TanimlamalarModel();
$summary = $GelirGider->summary();


?>

<div class="container-fluid">
    <!-- Filtre Kartı -->


    <!-- Özet Kartları -->
    <div class="row g-4">
        <!-- Gelir Kartı -->

    </div>
    <div class="container-fluid">

        <!-- start page title -->
        <?php
        $maintitle = "Gelir-Gider";
        $title = "Gelir Gider Listesi";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <div class="card filter-card mb-4 border-0">
            <div class="card-body p-4">
                <h5 class="card-title d-flex align-items-center mb-4">
                    <i class="bi bi-funnel-fill me-2 text-primary"></i>
                    <span class="fw-semibold">Filtreleme Seçenekleri</span>
                </h5>

                <div class="row g-3">
                    <!-- Tarih Filtreleri -->
                    <form action="" method="post">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "dogum_tarihi",
                                        $uye->dogum_tarihi ?? "",
                                        "Başlama Tarihi giriniz!",
                                        "Başlama Tarihi",
                                        "calendar",
                                        "form-control form-control-sm flatpickr"
                                    ); ?>
                            </div>
                            <div class="col-md-2">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "dogum_tarihi",
                                        $uye->dogum_tarihi ?? "",
                                        "Bitiş Tarihi giriniz!",
                                        "Bitiş Tarihi",
                                        "calendar",
                                        "form-control flatpickr"
                                    ); ?>
                            </div>
                            <div class="col-md-2">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "tutar_min",
                                        $uye->dogum_tarihi ?? "",
                                        "Bitiş Tarihi giriniz!",
                                        "Min Tutar",
                                        "arrow-down",
                                        "form-control money"
                                    ); ?>
                            </div>
                            <div class="col-md-2">
                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "tutar_max",
                                        $uye->dogum_tarihi ?? "",
                                        "Bitiş Tarihi giriniz!",
                                        "Max Tutar",
                                        "arrow-up",
                                        "form-control money"
                                    ); ?>
                            </div>
                            <div class="col-md-2">
                                <?php echo Form::FormSelect2(
                                    'kasa_id',
                                    $kasaOptions,
                                    $decrypted_kasa_id ?? null,
                                    'Kasa Seçin',
                                    'briefcase',
                                    'key',
                                    'Kasa Seçin'
                                ) ?>
                            </div>
                            <div class="col-md-2 mt-2 d-block align-self-end">
                                <button class="btn btn-primary px-4 me-2">
                                    <i class="bi bi-funnel me-1"></i> Filtrele
                                </button>
                                <button class="btn btn-outline-secondary px-4">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Sıfırla
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <div class="row mb-4">

            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
                rel="stylesheet">
            <style>
                :root {
                    --primary: #4361ee;
                    --income: #4cc9f0;
                    --expense: #f72585;
                    --balance: #7209b7;
                    --dark: #212529;
                    --light: #f8f9fa;
                    --radius: 24px;
                    --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                    --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
                }

                body {
                    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
                    color: var(--dark);
                }

                .card-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 30px;
                    width: 100%;
                }

                .finance-card {
                    background: rgba(255, 255, 255, 0.85);
                    backdrop-filter: blur(12px);
                    border-radius: var(--radius);
                    padding: 32px;
                    box-shadow: var(--shadow);
                    transition: var(--transition);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    position: relative;
                    overflow: hidden;
                }

                .finance-card::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 90%);
                    opacity: 0;
                    transition: var(--transition);
                }

                .finance-card:hover {
                    transform: translateY(-10px);
                    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
                }

                .finance-card:hover::before {
                    opacity: 1;
                }

                .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                }

                .card-icon {
                    width: 56px;
                    height: 56px;
                    border-radius: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    color: white;
                    flex-shrink: 0;
                }

                .income .card-icon {
                    background: linear-gradient(135deg, var(--income), #4895ef);
                }

                .expense .card-icon {
                    background: linear-gradient(135deg, var(--expense), #f15bb5);
                }

                .balance .card-icon {
                    background: linear-gradient(135deg, var(--balance), #9d4edd);
                }

                .card-title {
                    /* font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px; */
                    color: #64748b;
                    margin-bottom: 8px;
                }

                .card-value {
                    font-size: 32px;
                    font-weight: 800;
                    line-height: 1.2;
                    margin-bottom: 16px;
                    /* background: linear-gradient(180deg, currentColor, #64748b); */
                    -webkit-background-clip: text;
                    background-clip: text;
                    color: #64748b;
                }

                .card-change {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 14px;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 700;
                    backdrop-filter: blur(5px);
                }

                .income .card-change {
                    background: rgba(76, 201, 240, 0.15);
                    color: var(--income);
                }

                .expense .card-change {
                    background: rgba(247, 37, 133, 0.15);
                    color: var(--expense);
                }

                .balance .card-change {
                    background: rgba(114, 9, 183, 0.15);
                    color: var(--balance);
                }

                .card-divider {
                    border: none;
                    height: 1px;
                    background: linear-gradient(90deg, rgba(100, 116, 140, 0.1) 0%, rgba(100, 116, 140, 0.3) 50%, rgba(100, 116, 140, 0.1) 100%);
                    margin: 24px 0;
                }

                .card-footer {
                    display: flex;
                    justify-content: space-between;
                    font-size: 14px;
                    color: #64748b;
                    padding-top: 16px;
                }

                .card-footer-value {
                    font-weight: 700;
                    color: var(--dark);
                }

                .sparkline {
                    position: absolute;
                    right: 32px;
                    top: 32px;
                    width: 100px;
                    height: 60px;
                    opacity: 0.7;
                }
            </style>

            <div class="card-grid">
                <!-- Gelir Kartı -->
                <div class="finance-card income">
                    <svg class="sparkline" viewBox="0 0 100 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0,30 C20,10 40,25 60,15 80,5 100,20" stroke="#4cc9f0" stroke-width="2" fill="none"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="card-header">
                        <div>
                            <div class="card-title">Toplam Gelir</div>
                            <div class="card-value">
                                <?php echo Helper::formattedMoney($summary->toplam_gelir ?? 0); ?>
                            </div>
                            <div class="card-change">
                                <i class="bi bi-arrow-up"></i> 12.5% artış
                            </div>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span>Geçen Ay</span>
                        <span class="card-footer-value">
                            ₺28.470,00
                        </span>
                    </div>
                </div>

                <!-- Gider Kartı -->
                <div class="finance-card expense">
                    <svg class="sparkline" viewBox="0 0 100 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0,15 C20,25 40,10 60,20 80,30 100,15" stroke="#f72585" stroke-width="2" fill="none"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="card-header">
                        <div>
                            <div class="card-title">Toplam Gider</div>
                            <div class="card-value">
                                <?php echo Helper::formattedMoney($summary->toplam_gider ?? 0); ?>
                            </div>
                            <div class="card-change">
                                <i class="bi bi-arrow-up"></i> 8.2% artış
                            </div>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span>Geçen Ay</span>
                        <span class="card-footer-value">₺28.470,00</span>
                    </div>
                </div>

                <!-- Bakiye Kartı -->
                <div class="finance-card balance">
                    <svg class="sparkline" viewBox="0 0 100 40" xmlns="http://www.w3.org/2000/svg">
                        <path d="M0,20 C20,25 40,15 60,25 80,15 100,20" stroke="#7209b7" stroke-width="2" fill="none"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="card-header">
                        <div>
                            <div class="card-title">Net Bakiye</div>
                            <div class="card-value">
                                <?php echo Helper::formattedMoney($summary->bakiye ?? 0); ?>
                            </div>
                            <div class="card-change">
                                <i class="bi bi-arrow-up"></i> 2.1% artış
                            </div>
                        </div>
                        <div class="card-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span>Geçen Ay</span>
                        <span class="card-footer-value">
                            <?php echo Helper::formattedMoney($summary->bakiye ?? 0); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-grid d-md-flex d-block">
                        <div class="card-title col-md-8">

                            <h4 class="card-title">Gelir-Gider Listesi</h4>
                            <p class="card-title-desc">Gelir Gider işlemlerini görüntüleyebilir ve yeni işlem
                                ekleyebilirsiniz.

                            </p>
                        </div>


                        <div class="col-md-4">

                            <button type="button" id="gelirGiderEkle"
                                class="btn btn-primary waves-effect btn-label waves-light float-end new"
                                data-bs-toggle="modal" data-bs-target="#gelirGiderModal"><i
                                    class="bx bx-save label-icon"></i>Yeni İşlem
                            </button>
                            <button type="button" id="exportExcel"
                                class="btn btn-secondary waves-effect btn-label waves-light float-end me-2"> <i
                                    class='bx bxs-file-export label-icon'></i>
                                Excele Aktar
                            </button>
                            <a href="index?p=gelir-gider/upload-from-xls" id="importExcel"
                                class="btn btn-success waves-effect btn-label waves-light float-end me-2"><i
                                    class="bx bxs-file-import label-icon"></i>
                                Excelden Yükle
                            </a>
                        </div>

                    </div>


                    <div class="card-body overflow-auto">



                        <table id="gelirGiderTable" class="datatable table-hover table table-bordered nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="text-center">Sıra</th>
                                    <th class="text-center">Tarihi</th>
                                    <th class="text-center">İşlem Tipi</th>
                                    <th class="text-center">İşlem Adı</th>
                                    <th class="text-center">İşlem Tarihi</th>
                                    <th class="text-center">Hesap Adi</th>
                                    <th class="text-end">Tutar</th>
                                    <th class="text-end">Bakiye</th>
                                    <th class="text-center">Açıklama</th>
                                    <th style="width:5%">İşlem</th>
                                </tr>
                            </thead>


                            <tbody>

                                <?php
                                foreach ($gelir_gider as $islem) {
                                    $enc_id = Security::encrypt($islem->id);
                                    ?>
                                    <tr id="gelir_gider_<?php echo $islem->id ?>" data-id="<?php echo $enc_id ?>">
                                        <td class="text-center">
                                            <?php echo $kayit_sayisi ?>
                                        </td>
                                        <td class="text-center" style="width: 8%;">
                                            <?php echo $islem->kayit_tarihi ?>
                                        </td>

                                        <td class="text-center">
                                            <?php echo Helper::getBadge($islem->type) ?>
                                        </td>

                                        <td class="text-center">
                                            <?php echo $Tanimlama->getTurAdi($islem->islem_turu) ?>
                                        </td>
                                        <td class="text-center" style="width: 8%;">
                                            <?php echo $islem->islem_tarihi ?>
                                        </td>

                                        <td class="text">
                                            <?php
                                            echo $islem->hesap_adi;
                                            ?>
                                        </td>

                                        <td class="text-end">
                                            <?php echo Helper::formattedMoney($islem->tutar) ?>
                                        </td>

                                        <?php
                                        //Eğer 0'dan küçükse danger, büyükse success
                                        $color = $islem->bakiye < 0 ? "danger" : "success";
                                        ?>
                                        <td class="text-end text-<?php echo $color ?>">
                                            <?php
                                            echo Helper::formattedMoney($islem->bakiye) ?>
                                        </td>

                                        <td class="">
                                            <?php echo $islem->aciklama ?>
                                        </td>




                                        <td class="text-center no-export" style="width:5%">
                                            <div class="flex-shrink-0">
                                                <div class="dropdown align-self-start icon-demo-content">
                                                    <a class="dropdown-toggle" href="#" role="button"
                                                        data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                                        aria-haspopup="true" aria-expanded="false">
                                                        <i class="bx bx-list-ul font-size-24 text-dark"></i>
                                                    </a>
                                                    <div class="dropdown-menu">
                                                        <a href="#" data-id=<?php echo $enc_id; ?>
                                                            class="dropdown-item duzenle"><span
                                                                class="mdi mdi-account-edit font-size-18"></span>
                                                            Düzenle</a>
                                                        <a href="#" class="dropdown-item gelir-gider-sil"
                                                            data-id="<?php echo $enc_id; ?>"
                                                            data-name="<?php echo $islem->adi_soyadi; ?>">
                                                            <span class="mdi mdi-delete font-size-18"></span>
                                                            Sil</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                    $kayit_sayisi--;
                                } ?>
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
                <div class="modal-header">
                    <h5 class="modal-title" id="gelirGiderModalLabel">Gelir Gider İşlemler</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="gelirGiderForm">
                        <input type="hidden" name="gelir_gider_id" id="gelir_gider_id" class="form-control" value="0">

                        <div class="row form-selectgroup-boxes row mb-3">
                            <div class="col-md-6">
                                <label class="form-selectgroup-item">
                                    <input type="radio" name="type" value="1" class="form-selectgroup-input" checked="">
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
                                    <input type="radio" name="type" value="2" class="form-selectgroup-input">
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

                            <div class="col-md-12">
                                <?php
                                echo Form::FormSelect2(
                                    "islem_turu",
                                    $Financial->getGelirTurleri(),
                                    "",
                                    "İşlem Türü",
                                    "map-pin",
                                    "id",
                                    "tur_adi",

                                ); ?>

                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-12">

                                <?php echo
                                    Form::FormFloatInput(
                                        "text",
                                        "hesap_adi_text",
                                        "",
                                        "Hesap Adı giriniz!",
                                        "Hesap Adı",
                                        "user",
                                        "form-control"

                                    ); ?>

                            </div>
                        </div>

                        <div class="accordion-item mt-2 mb-3">
                            <h2 class="accordion-header" id="uye-sec-baslik">
                                <button id="uye-sec-button" class="accordion-button fw-medium collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#uye-sec" aria-expanded="false"
                                    aria-controls="uye-sec">
                                    <h4 class="card-title">Üye Seç</h4>

                                </button>
                            </h2>
                            <div id="uye-sec" class="accordion-collapse collapse mb-3" aria-labelledby="uye-sec-baslik"
                                data-bs-parent="#uye-sec">
                                <div class="accordion-body text-muted">
                                    <div class="row">

                                        <?php echo
                                            Form::FormSelect2(
                                                "hesap_adi",
                                                $hesap_adi,
                                                "",
                                                "Hesap Adı!",
                                                "user",
                                                "adi_soyadi",
                                                "adi_soyadi",
                                            ); ?>

                                    </div>
                                </div>
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
                                        $uye->aciklama ?? "",
                                        "Açıklama giriniz",
                                        "Açıklama",
                                        "map-pin",


                                    ); ?>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex">
                    <!-- yeni işlem butonu ekle -->
                    <button type="button" id="yeniIslemModal"
                        class="btn btn-success waves-effect btn-label waves-light float-left me-auto">
                        <i class="bx bx-plus label-icon"></i>Yeni İşlem
                    </button>

                    <button type="button" class="btn btn-secondary waves-effect btn-label waves-light"
                        data-bs-dismiss="modal"><i class="bx bx-x label-icon"></i>Kapat</button>
                    <button type="button" id="gelirGiderKaydet"
                        class="btn btn-primary waves-effect btn-label waves-light float-end"><i
                            class="bx bx-save label-icon"></i>Kaydet</button>
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

        /**kasa_id değişiklik olunca get ile yönlendir */
        $("#kasa_id").change(function () {
            var kasa_id = $(this).val();
            window.location.href = "index?p=gelir-gider/list&kasa_id=" + kasa_id;
        });
    </script>