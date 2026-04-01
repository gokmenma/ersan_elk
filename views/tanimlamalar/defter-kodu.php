<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$defterKodlari = $Tanimlamalar->getByGrup('defter_kodu');

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Defter Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">Defter Kodları Listesi</h4>
                        <p class="card-title-desc">Defter kodlarını görüntüleyebilir ve yeni defter kodu
                            ekleyebilirsiniz.
                        </p>
                    </div>

                    <div class="col-md-4">
                        <button type="button" id="actionEkle"
                            class="btn btn-success waves-effect btn-label waves-light float-end ms-2"
                            data-bs-toggle="modal" data-bs-target="#excelModal"><i
                                class="bx bx-upload label-icon"></i>Excelden Yükle
                        </button>

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
                                <th class="text-center" data-data="id">Sıra</th>
                                <th class="text-center" data-filter="number" data-data="tur_adi">Defter Kodu</th>
                                <th class="text-center" data-filter="string" data-data="defter_bolge">Bölge</th>
                                <th class="text-center" data-filter="string" data-data="defter_mahalle">Mahalle</th>
                                <th class="text-center" data-filter="number" data-data="defter_abone_sayisi">Abone Sayısı</th>
                                <th class="text-center" data-filter="date" data-data="baslangic_tarihi">Başlangıç Tarihi</th>
                                <th class="text-center" data-filter="date" data-data="bitis_tarihi">Bitiş Tarihi</th>
                                <th class="text-center" data-data="aciklama">Açıklama</th>
                                <th style="width:5%" data-data="islem" data-orderable="false">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>

                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->

</div> <!-- container-fluid -->

<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionModalLabel">Defter Kodu İşlemleri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="id" id="id" class="form-control" value="0">
                    <input type="hidden" name="grup" value="defter_kodu">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "tur_adi",
                                    "",
                                    "Defter Kodu giriniz!",
                                    "Defter Kodu",
                                    "book",
                                    "form-control"
                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "defter_bolge",
                                    "",
                                    "Bölge giriniz!",
                                    "Bölge",
                                    "map-pin",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "defter_mahalle",
                                    "",
                                    "Mahalle giriniz!",
                                    "Mahalle",
                                    "map",
                                    "form-control"
                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "defter_abone_sayisi",
                                    "",
                                    "Abone Sayısı giriniz!",
                                    "Abone Sayısı",
                                    "users",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "baslangic_tarihi",
                                    "",
                                    "Başlangıç Tarihi giriniz!",
                                    "Başlangıç Tarihi",
                                    "calendar",
                                    "form-control flatpickr"
                                ); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "bitis_tarihi",
                                    "",
                                    "Bitiş Tarihi giriniz!",
                                    "Bitiş Tarihi",
                                    "calendar",
                                    "form-control flatpickr"
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
                                    "align-left"
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

<!-- Excel Yükle Modal -->
<div class="modal fade" id="excelModal" tabindex="-1" aria-labelledby="excelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="excelModalLabel"><i class="bx bx-plus-circle me-2"></i>Defter Kodu Ekle
                    (Excel)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="formExcelYukle" enctype="multipart/form-data">
                <div class="modal-body">
                    <div
                        class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 mb-3">
                        <div class="d-flex align-items-start">
                            <i class="bx bx-download fs-4 me-2 text-success"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><strong>Şablon Dosyasını İndirin</strong></h6>
                                <p class="mb-2 small text-muted">
                                    Tanımladığınız defter kodu parametrelerine göre hazırlanan Excel şablonunu indirin.
                                </p>
                                <a href="views/tanimlamalar/defter-kodu-excel-sablon.php"
                                    class="btn btn-sm btn-success">
                                    <i class="bx bx-download me-1"></i>Defter Kodu Şablonunu İndir
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="excelFile" class="form-label">Excel Dosyası <span
                                class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="excelFile" name="excel_file" accept=".xlsx,.xls"
                            required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success"><i class="bx bx-upload me-1"></i>Yükle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/js/defter-kodu.js"></script>