<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;

use App\Model\TanimlamalarModel;
$Tanimlamalar = new TanimlamalarModel();

$kategoriler = $Tanimlamalar->getDemirbasKategorileri();

?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = "Ana Sayfa";
    $title = "Demirbaş Kategori Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-grid d-md-flex d-block">
                    <div class="card-title col-md-8">
                        <h4 class="card-title">Demirbaş Kategorileri Listesi</h4>
                        <p class="card-title-desc">Demirbaş kategorilerini görüntüleyebilir ve yeni kategori
                            ekleyebilirsiniz.
                        </p>
                    </div>

                    <div class="col-md-4">
                        <button type="button" id="actionEkle"
                            class="btn btn-primary waves-effect btn-label waves-light float-end" data-bs-toggle="modal"
                            data-bs-target="#actionModal"><i class="bx bx-plus label-icon"></i>Yeni Ekle
                        </button>
                    </div>

                </div>

                <div class="card-body overflow-auto">

                    <table id="actionTable" class="datatable table table-bordered nowrap w-100">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:5%">Sıra</th>
                                <th class="text-center">Kategori Adı</th>
                                <th class="text-center">Açıklama</th>
                                <th class="text-center">Kayıt Tarihi</th>
                                <th style="width:5%">İşlem</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            foreach ($kategoriler as $kategori) {
                                $enc_id = Security::encrypt($kategori->id);
                                ?>
                                <tr id="row_<?php echo $kategori->id; ?>">
                                    <td class="text-center">
                                        <?php echo $kategori->id ?>
                                    </td>
                                    <td class="text-center">
                                        <strong>
                                            <?php echo $kategori->tur_adi ?>
                                        </strong>
                                    </td>
                                    <td class="text-center" style="width:50%">
                                        <?php echo $kategori->aciklama ?>
                                    </td>
                                    <td class="text-center" style="width:10%">
                                        <?php echo $kategori->kayit_tarihi ?>
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
                                                            class="mdi mdi-pencil font-size-18"></span>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="actionModalLabel"><i class="bx bx-list-ul me-2"></i>Kategori İşlemleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" name="kategori_id" id="kategori_id" class="form-control" value="0">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <?php echo
                                Form::FormFloatInput(
                                    "text",
                                    "kategori_adi",
                                    "",
                                    "Kategori adı giriniz!",
                                    "Kategori Adı",
                                    "file-text",
                                    "form-control"
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
                                    "file-text"
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

<script src="views/tanimlamalar/js/demirbas-kategorileri.js?v=<?php echo time(); ?>"></script>