<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$isTurleri = $Tanimlamalar->getIsTurleri();
$isTuruAdlari = $Tanimlamalar->getIsTurleriAdlari();


?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "İş Türü Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">

                        <h4 class="card-title">İş Türleri Listesi</h4>
                        <p class="card-title-desc">İş türlerini görüntüleyebilir ve yeni iş türü
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
                                <th class="text-center">Sıra</th>
                                <th class="text-center">İş Türü</th>
                                <th class="text-center">İş Emri Sonucu</th>
                                <th class="text-center">İş Türü Ücreti</th>
                                <th class="text-center">Rapor Sekmesi</th>
                                <th class="text-center">Açıklama</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php
                            $i = 0;
                            foreach ($isTurleri as $isTuru) {
                                $i++;
                                $enc_id = Security::encrypt($isTuru->id);
                                ?>
                                <tr id="row_<?php echo $isTuru->id; ?>">
                                    <td class="text-center">
                                        <?php echo $isTuru->id ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $isTuru->tur_adi ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $isTuru->is_emri_sonucu ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $isTuru->is_turu_ucret ?? "0,00 TL" ?>
                                    </td>
                                    <td class="text-center text-capitalize">
                                        <?php echo $isTuru->rapor_sekmesi ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $isTuru->aciklama ?>
                                    </td>


                                    <td class="text-center" style="width:5%">
                                        <div class="flex-shrink-0">
                                            <div class="dropdown align-self-start">
                                                <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                                    aria-haspopup="true" aria-expanded="false">
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
                <h5 class="modal-title" id="actionModalLabel">İş Türü İşlemleri</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="is_turu_id" id="is_turu_id" class="form-control" value="0">


                    <div class="row mb-3">

                        <div class="col-md-12">
                            <?php echo
                                Form::FormSelect2(
                                    "is_turu",
                                    $isTuruAdlari,
                                    "",
                                    "İş Türü",
                                    "briefcase",
                                    "id",
                                    "",
                                    "form-select select2",
                                    false,
                                    'width:100%',
                                    'data-placeholder="İş Türü Seçiniz veya Yazınız"'
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "is_emri_sonucu",
                                    "",
                                    "İş Emri Sonucu giriniz!",
                                    "İş Emri Sonucu",
                                    "check-circle",
                                    "form-control"
                                ); ?>
                        </div>
                    </div>
                    <div class="row mb-3">

                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "is_turu_ucret",
                                    "",
                                    "İş Türü Ücreti giriniz!",
                                    "İş Türü Ücreti",
                                    "dollar-sign",
                                    "form-control money"

                                ); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php
                            $raporSekmeleri = [
                                'okuma' => 'Endeks Okuma',
                                'kesme' => 'Kesme/Açma İşlm.',
                                'sokme_takma' => 'Sayaç Sökme Takma',
                                'muhurleme' => 'Mühürleme',
                                'kacakkontrol' => 'Kaçak Kontrol'
                            ];
                            echo Form::FormSelect2(
                                "rapor_sekmesi",
                                $raporSekmeleri,
                                "",
                                "Rapor Sekmesi",
                                "layers",
                                "key",
                                "",
                                "form-control select2"
                            );
                            ?>
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
                <h5 class="modal-title" id="excelModalLabel"><i class="bx bx-plus-circle me-2"></i>İş Türü Ekle (Excel)
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
                                    Tanımladığınız iş türü parametrelerine göre hazırlanan Excel şablonunu indirin.
                                </p>
                                <a href="views/tanimlamalar/is-turu-excel-sablon.php" class="btn btn-sm btn-success">
                                    <i class="bx bx-download me-1"></i>İş Türü Şablonunu İndir
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

<script src="views/tanimlamalar/js/is-turu.js"></script>